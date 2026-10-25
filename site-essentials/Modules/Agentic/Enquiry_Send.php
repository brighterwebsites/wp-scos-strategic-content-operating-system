<?php
/**
 * Agentic — agent-sent enquiries, confirmed by the person by email
 *
 * v1.0 | 2026-10-25
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
 * Nothing outlives the pending window: confirmed or expired, it's deleted.
 *
 * Settings:
 *   scos_agentic_enquiry_send_enabled  on/off (off by default; needs the
 *                                      enquiry page from Enquiry_Form)
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Enquiry_Send {

	const OPTION_ENABLED = 'scos_agentic_enquiry_send_enabled';
	const PENDING        = 'se_enquiry_pending_';
	const RATE           = 'se_enquiry_rate_';
	const QUERY_VAR      = 'scos-enquiry';
	const TTL            = 2 * DAY_IN_SECONDS;

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

	// ── Submit ───────────────────────────────────────────────────────────────

	/**
	 * Hold an agent's enquiry and email the person to confirm it.
	 *
	 * @param  array  $values field name => string or string[].
	 * @param  string $agent  The assistant's name, if it gave one.
	 * @return array|\WP_Error { status, sent_to, expires_in_hours, ignored[], next }
	 */
	public static function submit( array $values, string $agent = '' ) {
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

		$limited = self::hit_limits( $email );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$token = wp_generate_password( 32, false, false );
		set_transient(
			self::PENDING . $token,
			[
				'values'  => $clean['values'],
				'email'   => $email,
				'agent'   => mb_substr( sanitize_text_field( $agent ), 0, 60 ),
				'created' => time(),
			],
			self::TTL
		);

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

			if ( ! self::deliver( $pending, $schema ) ) {
				set_transient( self::PENDING . $token, $pending, self::TTL );
				self::page( __( 'That didn\'t go through', 'site-essentials' ), '<p>' . esc_html__( 'Your enquiry could not be sent just now. Please try again in a few minutes.', 'site-essentials' ) . '</p>' . self::form_link(), 500 );
			}

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
				$note           = self::source_note( $pending['agent'] );
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
			$sent = self::run_breakdance_form( (int) $target['post_id'], (int) $target['form_id'], $values );
			if ( null !== $sent ) {
				return $sent;
			}
		}

		return self::email_site( $values, $fields, $pending['email'] );
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
	 * @return bool|null true sent, false failed, null when the form can't be read (fall back).
	 */
	private static function run_breakdance_form( int $post_id, int $form_id, array $values ): ?bool {
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
				'userAgent' => 'SCOS agent enquiry (confirmed by email)',
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
	private static function email_site( array $values, array $fields, string $reply_to ): bool {
		$lines = [];
		foreach ( $values as $key => $items ) {
			$lines[] = self::label( $key, $fields ) . ': ' . implode( ', ', $items );
		}

		return wp_mail(
			(string) get_option( 'admin_email' ),
			/* translators: %s: site name */
			sprintf( __( 'New enquiry for %s (via an AI assistant)', 'site-essentials' ), self::site_name() ),
			implode( "\n\n", $lines ),
			[ 'Reply-To: ' . $reply_to ]
		);
	}

	// ── Email to the person ──────────────────────────────────────────────────

	private static function send_confirmation( string $token, string $email, array $values, array $fields, string $agent ): bool {
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
	private static function hit_limits( string $email ) {
		$ip      = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		$buckets = [
			'email' => self::RATE . 'e_' . md5( strtolower( $email ) ),
			'ip'    => self::RATE . 'i_' . md5( wp_salt( 'nonce' ) . $ip ),
			'site'  => self::RATE . 'site',
		];

		foreach ( $buckets as $kind => $key ) {
			$hits = get_transient( $key );
			if ( is_array( $hits ) && $hits['n'] >= self::LIMITS[ $kind ][0] ) {
				return new \WP_Error( 'scos_enquiry_rate_limited', __( 'Too many enquiries have been prepared recently. Give the person the pre-filled link from /enquiry/link instead.', 'site-essentials' ), [ 'status' => 429 ] );
			}
		}
		foreach ( $buckets as $kind => $key ) {
			$hits = get_transient( $key );
			$hits = is_array( $hits ) ? $hits : [ 'n' => 0, 'until' => time() + self::LIMITS[ $kind ][1] ];
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
