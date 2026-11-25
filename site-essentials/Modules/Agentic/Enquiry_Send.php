<?php
/**
 * Agentic — agent-sent enquiries, confirmed by the person by email
 *
 * v1.0 | 2026-10-25
 * v1.1 | 2026-10-25 — submit() takes a channel. Through the MCP connector every
 *                      request comes from the assistant's servers, so the
 *                      per-IP limit is skipped there (#229).
 * v1.2 | 2026-11-24 — A confirmed enquiry is recorded in the lead table
 *                      (Lead_Store, channel agent), for CRMs to pull (#269).
 * v2.0 | 2026-11-25 — Delivery mode (#267): confirm_first (as before) or
 *                      deliver_first, where the business gets it at once,
 *                      marked unverified, and the person's click verifies it.
 *
 * WHAT THIS IS FOR
 * ----------------
 * The pre-filled link (Enquiry_Form) makes the person open the form and
 * press send. An agent that can make requests can do better: send the
 * enquiry from the conversation. But a public write route with no human
 * step is a spam and impersonation hole. So the agent submits, and the
 * person confirms (#225):
 *
 *   1. POST /enquiry/send → values checked against the form, held as pending
 *      (transient, 48 h), confirmation email sent to the person's address.
 *   2. The email links to ?scos-enquiry=<token>: a page showing exactly what
 *      will be sent. Its Send button is a POST, so mail scanners and link
 *      previews that fetch the URL can't confirm it.
 *   3. On Send the enquiry runs through the form's own actions (email,
 *      stored submission, Slack, webhook) like a typed one. Mailing-list and
 *      browser-only actions are never run, and agents can't tick consent or
 *      newsletter boxes. Without a Breakdance form behind the page, it is
 *      emailed to the site's admin address instead.
 *
 *   4. The confirmed enquiry is recorded as a lead (Lead_Store, channel
 *      agent, status verified), which the site's CRM can pull.
 *
 * The pending record never outlives its window: confirmed or expired, it's
 * deleted.
 *
 * DELIVERY MODES (scos_agentic_enquiry_delivery)
 * ----------------------------------------------
 *   confirm_first  (default) Steps 1-4 above. Nothing reaches the business
 *                  until the person confirms; the lead is written verified.
 *   deliver_first  For high-value leads, where an unconfirmed enquiry is a
 *                  lost one. On submit the enquiry is delivered at once, with
 *                  "Unverified" and the lead ref in the subject (business
 *                  addresses only) and the message, and the lead is written
 *                  unverified. The person still gets the email; their click
 *                  sets the lead to verified and emails the business a short
 *                  follow-up quoting the same ref. Not confirmed within 48 h,
 *                  the lead becomes expired (Lead_Store::expire_unverified).
 *                  With no human step before delivery the limits are lower
 *                  (LIMITS_DELIVER_FIRST).
 * The pending record says which mode it was made in, so changing the setting
 * never strands an enquiry already in flight.
 *
 * Settings:
 *   scos_agentic_enquiry_send_enabled  on/off (off by default; needs the
 *                                      enquiry page from Enquiry_Form)
 *   scos_agentic_enquiry_delivery      confirm_first | deliver_first
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Enquiry_Send {

	const OPTION_ENABLED  = 'scos_agentic_enquiry_send_enabled';
	const OPTION_DELIVERY = 'scos_agentic_enquiry_delivery';
	const CONFIRM_FIRST   = 'confirm_first';
	const DELIVER_FIRST   = 'deliver_first';
	const PENDING        = 'se_enquiry_pending_';
	const RATE           = 'se_enquiry_rate_';
	const QUERY_VAR      = 'scos-enquiry';
	const TTL            = 2 * DAY_IN_SECONDS;
	const CHANNEL_REST   = 'rest';
	const CHANNEL_MCP    = 'mcp';

	/** Breakdance form actions run on a confirmed enquiry. Anything else (mailing lists, popups, custom JS) is skipped. */
	const ACTIONS = [ 'email', 'store_submission', 'slack', 'discord', 'webhook' ];

	/** Checkbox fields that look like consent are never filled by an agent. */
	const CONSENT_PATTERN = '/subscri|newsletter|mailing|marketing|consent|opt.?in|privacy|terms/i';

	/** [ limit, window in seconds ] per bucket. */
	const LIMITS = [
		'email' => [ 2, DAY_IN_SECONDS ],
		'ip'    => [ 5, HOUR_IN_SECONDS ],
		'site'  => [ 25, DAY_IN_SECONDS ],
	];

	/** Lower limits when enquiries reach the business before anyone confirms. */
	const LIMITS_DELIVER_FIRST = [
		'email' => [ 1, DAY_IN_SECONDS ],
		'ip'    => [ 3, HOUR_IN_SECONDS ],
		'site'  => [ 10, DAY_IN_SECONDS ],
	];

	// ── Boot ─────────────────────────────────────────────────────────────────

	public static function init(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		add_action( 'template_redirect', [ __CLASS__, 'handle_confirm' ], 0 ); // before builders take over the request
	}

	public static function is_enabled(): bool {
		return (bool) get_option( self::OPTION_ENABLED, false ) && Enquiry_Form::is_enabled();
	}

	/** confirm_first or deliver_first. */
	public static function delivery_mode(): string {
		return self::DELIVER_FIRST === get_option( self::OPTION_DELIVERY, self::CONFIRM_FIRST ) ? self::DELIVER_FIRST : self::CONFIRM_FIRST;
	}

	/** Agents can send, and what they send reaches the business before the person confirms. */
	public static function delivers_first(): bool {
		return self::is_enabled() && self::DELIVER_FIRST === self::delivery_mode();
	}

	// ── Submit ───────────────────────────────────────────────────────────────

	/**
	 * Take an agent's enquiry: hold it and email the person to confirm it, or
	 * (deliver_first) deliver it now and email them to verify it.
	 *
	 * @param  array  $values  field name => string or string[].
	 * @param  string $agent   The assistant's name, if it gave one.
	 * @param  string $channel CHANNEL_REST or CHANNEL_MCP.
	 * @return array|\WP_Error { status, sent_to, expires_in_hours, ignored[], next }
	 *                          plus reference (the lead ref) in deliver_first.
	 */
	public static function submit( array $values, string $agent = '', string $channel = self::CHANNEL_REST ) {
		$clean = Enquiry_Form::clean_values( $values );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		$ignored = $clean['ignored'];
		$fields  = array_column( $clean['schema']['fields'], null, 'name' );

		// Consent is the person's to give, on the form, never the agent's.
		foreach ( array_keys( $clean['values'] ) as $key ) {
			if ( 'checkbox' === ( $fields[ $key ]['type'] ?? '' ) && self::is_consent_field( $fields[ $key ] ) ) {
				unset( $clean['values'][ $key ] );
				$ignored[] = $key . ': ' . __( 'consent boxes are left for the person to tick', 'site-essentials' );
			}
		}

		if ( $clean['missing'] ) {
			return new \WP_Error(
				'scos_enquiry_missing',
				/* translators: %s: comma-separated field names */
				sprintf( __( 'Required fields are missing: %s. Ask the person, then send again.', 'site-essentials' ), implode( ', ', $clean['missing'] ) ),
				[ 'status' => 400, 'missing_required' => $clean['missing'], 'ignored' => $ignored ]
			);
		}

		$email = self::email_of( $clean['values'], $fields );
		if ( '' === $email ) {
			return new \WP_Error(
				'scos_enquiry_no_email',
				__( 'The enquiry needs the person\'s email address: they confirm it by email before it is sent. Ask them for it, then send again.', 'site-essentials' ),
				[ 'status' => 400, 'ignored' => $ignored ]
			);
		}

		$limited = self::hit_limits( $email, self::CHANNEL_MCP !== $channel );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$pending = [
			'values'  => $clean['values'],
			'email'   => $email,
			'agent'   => mb_substr( sanitize_text_field( $agent ), 0, 60 ),
			'channel' => $channel,
			'created' => time(),
		];

		if ( self::DELIVER_FIRST === self::delivery_mode() ) {
			return self::submit_deliver_first( $pending, $clean['schema'], $fields, $ignored );
		}

		$token = wp_generate_password( 32, false, false );
		set_transient( self::PENDING . $token, $pending, self::TTL );

		if ( ! self::send_confirmation( $token, $email, $clean['values'], $fields, $agent ) ) {
			delete_transient( self::PENDING . $token );
			return new \WP_Error( 'scos_enquiry_mail_failed', __( 'The confirmation email could not be sent. Give the person the pre-filled link from /enquiry/link instead.', 'site-essentials' ), [ 'status' => 502 ] );
		}

		return [
			'status'           => 'pending_confirmation',
			'sent_to'          => self::mask( $email ),
			'expires_in_hours' => (int) ( self::TTL / HOUR_IN_SECONDS ),
			'ignored'          => $ignored,
			'next'             => sprintf(
				/* translators: 1: masked email address, 2: site name */
				__( 'Not sent yet. Tell the person: we have emailed %1$s a link to check the enquiry and send it to %2$s. Nothing reaches the business until they press Send on that page. The link expires in 48 hours.', 'site-essentials' ),
				self::mask( $email ),
				self::site_name()
			),
		];
	}

	/**
	 * deliver_first: deliver now, write the lead unverified, then email the
	 * person a link that verifies it.
	 *
	 * @return array|\WP_Error
	 */
	private static function submit_deliver_first( array $pending, array $schema, array $fields, array $ignored ) {
		$pending['ref']       = Lead_Store::new_ref();
		$pending['delivered'] = true;

		if ( ! self::deliver( $pending, $schema ) ) {
			return new \WP_Error( 'scos_enquiry_delivery_failed', __( 'The enquiry could not be delivered just now. Give the person the pre-filled link from /enquiry/link instead.', 'site-essentials' ), [ 'status' => 502 ] );
		}
		self::record_lead( $pending, $schema, 'unverified' );

		$token = wp_generate_password( 32, false, false );
		set_transient( self::PENDING . $token, $pending, self::TTL );
		$mailed = self::send_confirmation( $token, $pending['email'], $pending['values'], $fields, $pending['agent'], true );
		if ( ! $mailed ) {
			delete_transient( self::PENDING . $token ); // Nothing can verify it now; the lead expires.
		}

		$site = self::site_name();
		return [
			'status'           => 'delivered_unverified',
			'reference'        => $pending['ref'],
			'sent_to'          => $mailed ? self::mask( $pending['email'] ) : '',
			'expires_in_hours' => (int) ( self::TTL / HOUR_IN_SECONDS ),
			'ignored'          => $ignored,
			'next'             => $mailed
				? sprintf(
					/* translators: 1: site name, 2: lead reference, 3: masked email address */
					__( 'Sent. Tell the person: your enquiry has gone to %1$s (reference %2$s). We have also emailed %3$s a link to confirm it came from you. Please click it, so the business knows the enquiry is genuine. The link expires in 48 hours.', 'site-essentials' ),
					$site,
					$pending['ref'],
					self::mask( $pending['email'] )
				)
				: sprintf(
					/* translators: 1: site name, 2: lead reference */
					__( 'Sent. Tell the person: your enquiry has gone to %1$s (reference %2$s). We could not email them a confirmation link, so the business sees it as unconfirmed.', 'site-essentials' ),
					$site,
					$pending['ref']
				),
		];
	}

	// ── Confirm ──────────────────────────────────────────────────────────────

	/**
	 * ?scos-enquiry=<token>. GET shows the enquiry with a Send button; the
	 * button POSTs back here, which delivers it.
	 */
	public static function handle_confirm(): void {
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		// Personal details on this page: never cache it.
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'scos enquiry confirmation' );

		$token   = preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET[ self::QUERY_VAR ] ) );
		$pending = '' !== $token ? get_transient( self::PENDING . $token ) : false;

		if ( ! is_array( $pending ) ) {
			self::page(
				__( 'This link has expired', 'site-essentials' ),
				'<p>' . esc_html__( 'The enquiry was already sent, or the link is more than 48 hours old. Nothing more will be sent from it.', 'site-essentials' ) . '</p>'
				. self::form_link(),
				410
			);
		}

		$schema = Enquiry_Form::get_schema();
		$fields = $schema ? array_column( $schema['fields'], null, 'name' ) : [];

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			$nonce = isset( $_POST['_scos_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_scos_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'scos_enquiry_' . $token ) ) {
				self::page( __( 'Please try again', 'site-essentials' ), '<p>' . esc_html__( 'This page was open too long. Go back to the link in your email and press Send again.', 'site-essentials' ) . '</p>', 403 );
			}

			// Claim it first, so a double click can't send it twice.
			delete_transient( self::PENDING . $token );

			if ( ! empty( $pending['delivered'] ) ) {
				self::verify_delivered( $pending, $schema );
			}

			if ( ! self::deliver( $pending, $schema ) ) {
				set_transient( self::PENDING . $token, $pending, self::TTL );
				self::page( __( 'That didn\'t go through', 'site-essentials' ), '<p>' . esc_html__( 'Your enquiry could not be sent just now. Please try again in a few minutes.', 'site-essentials' ) . '</p>' . self::form_link(), 500 );
			}

			self::record_lead( $pending, $schema );

			self::page(
				__( 'Enquiry sent', 'site-essentials' ),
				'<p>' . esc_html( sprintf(
					/* translators: %s: site name */
					__( 'Thanks. Your enquiry is on its way to %s.', 'site-essentials' ),
					self::site_name()
				) ) . '</p>'
				. '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Back to the website', 'site-essentials' ) . '</a></p>'
			);
		}

		if ( ! empty( $pending['delivered'] ) ) {
			self::page_verify( $token, $pending, $fields );
		}

		$body  = '<p>' . esc_html( sprintf(
			/* translators: %s: site name */
			__( 'An AI assistant prepared this enquiry to %s for you. Nothing has been sent yet. Check the details, then press Send.', 'site-essentials' ),
			self::site_name()
		) ) . '</p>';
		$body .= self::details_html( $pending['values'], $fields );
		$body .= '<form method="post" action="' . esc_url( add_query_arg( self::QUERY_VAR, $token, home_url( '/' ) ) ) . '">'
			. '<input type="hidden" name="_scos_nonce" value="' . esc_attr( wp_create_nonce( 'scos_enquiry_' . $token ) ) . '">'
			. '<p><button type="submit" class="button button-primary button-large">' . esc_html__( 'Send my enquiry', 'site-essentials' ) . '</button></p>'
			. '</form>';
		$body .= '<p>' . esc_html__( 'Something wrong? Don\'t press Send:', 'site-essentials' ) . ' ' . self::form_link( false ) . '</p>';

		self::page( __( 'Check and send your enquiry', 'site-essentials' ), $body );
	}

	/** deliver_first, GET: the enquiry already went; ask the person to confirm it was them. */
	private static function page_verify( string $token, array $pending, array $fields ): void {
		$body  = '<p>' . esc_html( sprintf(
			/* translators: 1: site name, 2: lead reference */
			__( 'An AI assistant sent this enquiry to %1$s for you (reference %2$s). Press Confirm to let them know it really came from you.', 'site-essentials' ),
			self::site_name(),
			$pending['ref']
		) ) . '</p>';
		$body .= self::details_html( $pending['values'], $fields );
		$body .= '<form method="post" action="' . esc_url( add_query_arg( self::QUERY_VAR, $token, home_url( '/' ) ) ) . '">'
			. '<input type="hidden" name="_scos_nonce" value="' . esc_attr( wp_create_nonce( 'scos_enquiry_' . $token ) ) . '">'
			. '<p><button type="submit" class="button button-primary button-large">' . esc_html__( 'Confirm it was me', 'site-essentials' ) . '</button></p>'
			. '</form>';
		$body .= '<p>' . esc_html__( 'Didn\'t ask for this? Don\'t press Confirm. The business will see that it was never confirmed.', 'site-essentials' ) . '</p>';

		self::page( __( 'Confirm your enquiry', 'site-essentials' ), $body );
	}

	/** deliver_first, POST: mark the lead verified, tell the business, stop. */
	private static function verify_delivered( array $pending, ?array $schema ): void {
		$ref = (string) ( $pending['ref'] ?? '' );
		if ( '' === $ref || ! Lead_Store::set_status( $ref, 'verified' ) ) {
			error_log( 'SCOS enquiry send: lead ' . $ref . ' not marked verified' );
		}
		self::notify_verified( $pending, $schema );

		self::page(
			__( 'Thanks for confirming', 'site-essentials' ),
			'<p>' . esc_html( sprintf(
				/* translators: %s: site name */
				__( '%s now knows your enquiry came from you.', 'site-essentials' ),
				self::site_name()
			) ) . '</p>'
			. '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Back to the website', 'site-essentials' ) . '</a></p>'
		);
	}

	/**
	 * deliver_first: a short "verified" email to the addresses that got the
	 * enquiry. The business's own notification, not a CRM push.
	 */
	private static function notify_verified( array $pending, ?array $schema ): void {
		$ref    = (string) ( $pending['ref'] ?? '' );
		$fields = $schema ? array_column( $schema['fields'], null, 'name' ) : [];
		$to     = self::business_recipients( $schema ) ?: [ (string) get_option( 'admin_email' ) ];

		$lines = [
			sprintf(
				/* translators: 1: lead reference, 2: date the enquiry was sent */
				__( 'The person who sent enquiry %1$s on %2$s has confirmed by email that it came from them.', 'site-essentials' ),
				$ref,
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $pending['created'] )
			),
			'',
		];
		foreach ( $pending['values'] as $key => $items ) {
			if ( 'textarea' !== ( $fields[ $key ]['type'] ?? '' ) ) {
				$lines[] = self::label( $key, $fields ) . ': ' . implode( ', ', $items );
			}
		}

		$sent = wp_mail(
			$to,
			/* translators: 1: lead reference, 2: site name */
			sprintf( __( '[Verified · %1$s] Enquiry to %2$s confirmed', 'site-essentials' ), $ref, self::site_name() ),
			implode( "\n", $lines ),
			[ 'Reply-To: ' . $pending['email'] ]
		);
		if ( ! $sent ) {
			error_log( 'SCOS enquiry send: verified follow-up for ' . $ref . ' not sent' );
		}
	}

	/**
	 * The fixed addresses the form's Email action sends to: the business's
	 * own inboxes. Addresses built from a field ({email}) are the person's
	 * autoresponder and are left out.
	 *
	 * @return string[]
	 */
	private static function business_recipients( ?array $schema ): array {
		$target = $schema['target'] ?? null;
		if ( ! $target || 'breakdance' !== $target['builder'] || ! function_exists( '\\Breakdance\\Forms\\getFormSettings' ) ) {
			return [];
		}
		try {
			$settings = \Breakdance\Forms\getFormSettings( (int) $target['post_id'], (int) $target['form_id'] );
		} catch ( \Throwable $e ) {
			return [];
		}
		$to = [];
		foreach ( (array) ( $settings['actions']['email']['emails'] ?? [] ) as $email ) {
			if ( self::is_business_address( (string) ( $email['to'] ?? '' ) ) ) {
				$to = array_merge( $to, array_filter( array_map( 'trim', explode( ',', $email['to'] ) ), 'is_email' ) );
			}
		}
		return array_values( array_unique( $to ) );
	}

	/** A recipient list with no {field} tokens goes to the business, not the person. */
	private static function is_business_address( string $to ): bool {
		return '' !== trim( $to ) && false === strpos( $to, '{' );
	}

	// ── Deliver ──────────────────────────────────────────────────────────────

	/**
	 * Send a confirmed enquiry through the form's own actions, or by email.
	 *
	 * @param array      $pending The held enquiry.
	 * @param array|null $schema  The form schema.
	 * @return bool
	 */
	private static function deliver( array $pending, ?array $schema ): bool {
		$values = $pending['values'];
		$fields = $schema ? array_column( $schema['fields'], null, 'name' ) : [];

		// Say where it came from, in the message the business reads.
		foreach ( $fields as $key => $field ) {
			if ( 'textarea' === $field['type'] ) {
				$note           = empty( $pending['delivered'] )
					? self::source_note( $pending['agent'] )
					: self::unverified_note( $pending['agent'], (string) $pending['ref'] );
				$values[ $key ] = [ $note . "\n\n" . implode( "\n", $values[ $key ] ?? [] ) ];
				break;
			}
		}

		/**
		 * Filter: scos_enquiry_send_values — the values delivered on confirm.
		 *
		 * @param array $values  field name => string[].
		 * @param array $pending The held enquiry.
		 */
		$values = (array) apply_filters( 'scos_enquiry_send_values', $values, $pending );

		$target = $schema['target'] ?? null;
		if ( $target && 'breakdance' === $target['builder'] && self::breakdance_available() ) {
			$sent = self::run_breakdance_form( (int) $target['post_id'], (int) $target['form_id'], $values, (string) ( $pending['ref'] ?? '' ) );
			if ( null !== $sent ) {
				return $sent;
			}
		}

		return self::email_site( $values, $fields, $pending['email'], (string) ( $pending['ref'] ?? '' ) );
	}

	/**
	 * Record an enquiry in the lead table: verified when the person confirmed
	 * first, unverified when it was delivered first. A failure is logged,
	 * never shown: the business already has the enquiry.
	 */
	private static function record_lead( array $pending, ?array $schema, string $status = 'verified' ): void {
		$fields = $schema ? array_column( $schema['fields'], null, 'name' ) : [];
		$target = $schema['target'] ?? [];

		$list = [];
		foreach ( $pending['values'] as $key => $items ) {
			$list[] = [
				'name'  => $key,
				'label' => $fields[ $key ]['label'] ?? '',
				'type'  => $fields[ $key ]['type'] ?? 'text',
				'value' => array_values( (array) $items ),
			];
		}

		$ref = Lead_Store::insert( [
			'ref'     => (string) ( $pending['ref'] ?? '' ),
			'channel' => 'agent',
			'status'  => $status,
			'source'  => (string) ( $pending['channel'] ?? self::CHANNEL_REST ),
			'agent'   => (string) ( $pending['agent'] ?? '' ),
			'post_id' => (int) ( $target['post_id'] ?? 0 ),
			'form_id' => (int) ( $target['form_id'] ?? 0 ),
			'fields'  => $list,
			'context' => [ 'delivery' => empty( $pending['delivered'] ) ? self::CONFIRM_FIRST : self::DELIVER_FIRST ],
		] );
		if ( is_wp_error( $ref ) ) {
			error_log( 'SCOS enquiry send: lead not recorded: ' . $ref->get_error_message() );
		}
	}

	private static function breakdance_available(): bool {
		foreach ( [ 'getFormSettings', 'getFormActions', 'getFormData', 'validateFormData', 'buildFormExtra', 'executeActions', 'handleSubmissionCleanup' ] as $fn ) {
			if ( ! function_exists( '\\Breakdance\\Forms\\' . $fn ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Run the Breakdance form's server-side actions, as a typed submission does,
	 * minus its security checks (the email confirmation replaces them).
	 *
	 * @param string $ref deliver_first: the lead ref. Marks the subject of
	 *                    emails to the business "[Unverified · ref]".
	 * @return bool|null true sent, false failed, null when the form can't be read (fall back).
	 */
	private static function run_breakdance_form( int $post_id, int $form_id, array $values, string $ref = '' ): ?bool {
		try {
			$settings = \Breakdance\Forms\getFormSettings( $post_id, $form_id );
			if ( ! $settings || empty( $settings['form']['fields'] ) ) {
				return null;
			}

			/** @param string[] $allowed Breakdance action slugs run on a confirmed agent enquiry. */
			$allowed = (array) apply_filters( 'scos_enquiry_send_actions', self::ACTIONS );
			$actions = array_values( array_filter(
				\Breakdance\Forms\getFormActions( $settings ),
				static function ( $action ) use ( $allowed ) {
					return in_array( $action::slug(), $allowed, true );
				}
			) );
			if ( ! $actions ) {
				return null;
			}

			if ( '' !== $ref ) {
				foreach ( (array) ( $settings['actions']['email']['emails'] ?? [] ) as $i => $email ) {
					if ( self::is_business_address( (string) ( $email['to'] ?? '' ) ) ) {
						$settings['actions']['email']['emails'][ $i ]['subject'] = self::unverified_subject( $ref, (string) ( $email['subject'] ?? '' ) ?: 'New contact form message' );
					}
				}
			}

			// Breakdance takes one value per field, or a list for multiple choice.
			$submitted = [];
			foreach ( $values as $key => $items ) {
				$submitted[ $key ] = count( $items ) > 1 ? array_values( $items ) : (string) reset( $items );
			}

			$data  = \Breakdance\Forms\getFormData( $settings['form']['fields'], $submitted );
			$valid = \Breakdance\Forms\validateFormData( $data, [], $form_id, $post_id );
			if ( is_wp_error( $valid ) ) {
				error_log( 'SCOS enquiry send: form rejected the values: ' . $valid->get_error_message() );
				return false;
			}

			$extra    = \Breakdance\Forms\buildFormExtra( $form_id, $post_id, $submitted, [], [], [
				'ip'        => '',
				'referer'   => Enquiry_Form::page_url(),
				'userAgent' => '' !== $ref ? 'SCOS agent enquiry (delivered before confirmation)' : 'SCOS agent enquiry (confirmed by email)',
				'userId'    => 0,
			] );
			$response = \Breakdance\Forms\executeActions( $actions, [ $data, $settings, $extra ] );
			\Breakdance\Forms\handleSubmissionCleanup( $response, [], false );

			$ok = false;
			foreach ( (array) $response as $slug => $result ) {
				if ( in_array( $result['type'] ?? '', [ 'error', 'user-error' ], true ) ) {
					error_log( 'SCOS enquiry send: action ' . $slug . ' failed: ' . ( $result['message'] ?? '' ) );
				} elseif ( in_array( $slug, [ 'email', 'store_submission' ], true ) ) {
					$ok = true; // The business has it, by email or in stored submissions.
				}
			}
			return $ok;
		} catch ( \Throwable $e ) {
			error_log( 'SCOS enquiry send: ' . $e->getMessage() );
			return null;
		}
	}

	/** Fallback: email the enquiry to the site's admin address. */
	private static function email_site( array $values, array $fields, string $reply_to, string $ref = '' ): bool {
		$lines = [];
		foreach ( $values as $key => $items ) {
			$lines[] = self::label( $key, $fields ) . ': ' . implode( ', ', $items );
		}

		/* translators: %s: site name */
		$subject = sprintf( __( 'New enquiry for %s (via an AI assistant)', 'site-essentials' ), self::site_name() );

		return wp_mail(
			(string) get_option( 'admin_email' ),
			'' !== $ref ? self::unverified_subject( $ref, $subject ) : $subject,
			implode( "\n\n", $lines ),
			[ 'Reply-To: ' . $reply_to ]
		);
	}

	// ── Email to the person ──────────────────────────────────────────────────

	private static function send_confirmation( string $token, string $email, array $values, array $fields, string $agent, bool $delivered = false ): bool {
		if ( $delivered ) {
			return self::send_verify_request( $token, $email, $values, $fields, $agent );
		}
		$site = self::site_name();
		$url  = add_query_arg( self::QUERY_VAR, $token, home_url( '/' ) );

		$intro = '' !== $agent
			/* translators: 1: assistant name, 2: site name */
			? sprintf( __( '%1$s prepared an enquiry to %2$s for you.', 'site-essentials' ), $agent, $site )
			/* translators: %s: site name */
			: sprintf( __( 'An AI assistant prepared an enquiry to %s for you.', 'site-essentials' ), $site );

		$message = '<p>' . esc_html( $intro ) . ' ' . esc_html__( 'Nothing has been sent yet.', 'site-essentials' ) . '</p>'
			. self::details_html( $values, $fields )
			. '<p style="margin:24px 0"><a href="' . esc_url( $url ) . '" style="background:#1d4ed8;color:#ffffff;padding:12px 20px;border-radius:6px;text-decoration:none;font-weight:600">'
			. esc_html__( 'Check and send my enquiry', 'site-essentials' ) . '</a></p>'
			. '<p>' . esc_html__( 'Or copy this link into your browser:', 'site-essentials' ) . '<br>' . esc_html( $url ) . '</p>'
			. '<p style="color:#555">' . esc_html__( 'If you didn\'t ask for this, ignore this email. Nothing will be sent, and the link expires in 48 hours.', 'site-essentials' ) . '</p>';

		return (bool) wp_mail(
			$email,
			/* translators: %s: site name */
			sprintf( __( 'Confirm your enquiry to %s', 'site-essentials' ), $site ),
			$message,
			[ 'Content-Type: text/html; charset=UTF-8' ]
		);
	}

	/** deliver_first: the enquiry has gone; ask the person to confirm it was them. */
	private static function send_verify_request( string $token, string $email, array $values, array $fields, string $agent ): bool {
		$site = self::site_name();
		$url  = add_query_arg( self::QUERY_VAR, $token, home_url( '/' ) );

		$intro = '' !== $agent
			/* translators: 1: assistant name, 2: site name */
			? sprintf( __( '%1$s sent this enquiry to %2$s for you.', 'site-essentials' ), $agent, $site )
			/* translators: %s: site name */
			: sprintf( __( 'An AI assistant sent this enquiry to %s for you.', 'site-essentials' ), $site );

		$message = '<p>' . esc_html( $intro ) . ' ' . esc_html__( 'Please confirm it came from you, so the business knows it is genuine.', 'site-essentials' ) . '</p>'
			. self::details_html( $values, $fields )
			. '<p style="margin:24px 0"><a href="' . esc_url( $url ) . '" style="background:#1d4ed8;color:#ffffff;padding:12px 20px;border-radius:6px;text-decoration:none;font-weight:600">'
			. esc_html__( 'Confirm it was me', 'site-essentials' ) . '</a></p>'
			. '<p>' . esc_html__( 'Or copy this link into your browser:', 'site-essentials' ) . '<br>' . esc_html( $url ) . '</p>'
			. '<p style="color:#555">' . esc_html__( 'If you didn\'t ask for this, ignore this email. The business will see that the enquiry was never confirmed. The link expires in 48 hours.', 'site-essentials' ) . '</p>';

		return (bool) wp_mail(
			$email,
			/* translators: %s: site name */
			sprintf( __( 'Please confirm your enquiry to %s', 'site-essentials' ), $site ),
			$message,
			[ 'Content-Type: text/html; charset=UTF-8' ]
		);
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	private static function is_consent_field( array $field ): bool {
		$text = implode( ' ', [ $field['name'], $field['label'] ?? '', $field['description'] ?? '', implode( ' ', $field['options'] ?? [] ) ] );
		return (bool) preg_match( self::CONSENT_PATTERN, $text );
	}

	private static function email_of( array $values, array $fields ): string {
		foreach ( $values as $key => $items ) {
			if ( 'email' === ( $fields[ $key ]['type'] ?? '' ) && is_email( (string) reset( $items ) ) ) {
				return (string) reset( $items );
			}
		}
		return '';
	}

	/**
	 * Count this request against each limit; refuse once any is reached.
	 *
	 * @return true|\WP_Error
	 */
	private static function hit_limits( string $email, bool $per_ip = true ) {
		$limits  = self::DELIVER_FIRST === self::delivery_mode() ? self::LIMITS_DELIVER_FIRST : self::LIMITS;
		$ip      = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		$buckets = [
			'email' => self::RATE . 'e_' . md5( strtolower( $email ) ),
			'ip'    => self::RATE . 'i_' . md5( wp_salt( 'nonce' ) . $ip ),
			'site'  => self::RATE . 'site',
		];
		if ( ! $per_ip ) {
			unset( $buckets['ip'] ); // Every connector request shares the assistant's IP.
		}

		foreach ( $buckets as $kind => $key ) {
			$hits = get_transient( $key );
			if ( is_array( $hits ) && $hits['n'] >= $limits[ $kind ][0] ) {
				return new \WP_Error( 'scos_enquiry_rate_limited', __( 'Too many enquiries have been prepared recently. Give the person the pre-filled link from /enquiry/link instead.', 'site-essentials' ), [ 'status' => 429 ] );
			}
		}
		foreach ( $buckets as $kind => $key ) {
			$hits = get_transient( $key );
			$hits = is_array( $hits ) ? $hits : [ 'n' => 0, 'until' => time() + $limits[ $kind ][1] ];
			++$hits['n'];
			set_transient( $key, $hits, max( 1, $hits['until'] - time() ) );
		}
		return true;
	}

	private static function source_note( string $agent ): string {
		return '' !== $agent
			/* translators: %s: assistant name */
			? sprintf( __( '[Sent by %s on the person\'s behalf. They confirmed it by email.]', 'site-essentials' ), $agent )
			: __( '[Sent by an AI assistant on the person\'s behalf. They confirmed it by email.]', 'site-essentials' );
	}

	private static function unverified_note( string $agent, string $ref ): string {
		return '' !== $agent
			/* translators: 1: assistant name, 2: lead reference */
			? sprintf( __( '[UNVERIFIED. Sent by %1$s on the person\'s behalf. We have emailed them to confirm it came from them, and you will get a follow-up if they do. Ref %2$s]', 'site-essentials' ), $agent, $ref )
			/* translators: %s: lead reference */
			: sprintf( __( '[UNVERIFIED. Sent by an AI assistant on the person\'s behalf. We have emailed them to confirm it came from them, and you will get a follow-up if they do. Ref %s]', 'site-essentials' ), $ref );
	}

	private static function unverified_subject( string $ref, string $subject ): string {
		/* translators: 1: lead reference, 2: the original subject */
		return sprintf( __( '[Unverified · %1$s] %2$s', 'site-essentials' ), $ref, $subject );
	}

	private static function details_html( array $values, array $fields ): string {
		$rows = '';
		foreach ( $values as $key => $items ) {
			$rows .= '<tr><th style="text-align:left;vertical-align:top;padding:6px 12px 6px 0">' . esc_html( self::label( $key, $fields ) ) . '</th>'
				. '<td style="padding:6px 0">' . nl2br( esc_html( implode( ', ', $items ) ) ) . '</td></tr>';
		}
		return '<table>' . $rows . '</table>';
	}

	private static function label( string $key, array $fields ): string {
		return ( $fields[ $key ]['label'] ?? '' ) ?: ucfirst( str_replace( [ '_', '-' ], ' ', $key ) );
	}

	private static function form_link( bool $sentence = true ): string {
		$link = '<a href="' . esc_url( Enquiry_Form::page_url() ) . '">' . esc_html__( 'use the form on our website', 'site-essentials' ) . '</a>';
		return $sentence ? '<p>' . esc_html__( 'You can also', 'site-essentials' ) . ' ' . $link . '.</p>' : $link . '.';
	}

	private static function mask( string $email ): string {
		[ $user, $domain ] = array_pad( explode( '@', $email, 2 ), 2, '' );
		return mb_substr( $user, 0, 1 ) . str_repeat( '*', max( 1, mb_strlen( $user ) - 1 ) ) . '@' . $domain;
	}

	private static function site_name(): string {
		return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/** Render a minimal page and stop. */
	private static function page( string $title, string $body, int $status = 200 ): void {
		wp_die(
			'<h1>' . esc_html( $title ) . '</h1>' . $body,
			esc_html( $title . ' · ' . self::site_name() ),
			[ 'response' => $status ]
		);
	}
}
