<?php
/**
 * Business Info — Privacy policy shortcode (#230)
 *
 * v1.0 | 2026-11-26
 *
 * [scos_privacy_policy] renders the site's privacy policy from Business Info
 * and a few per-site switches, so every site gets the same corrected text and
 * a wording fix ships in the plugin instead of in each site's page data.
 *
 * WHAT DECIDES THE TEXT
 * ---------------------
 * - Business name, email and contact name: get_bloginfo( 'name' ),
 *   scos_biz_email, scos_biz_contact_name. No email set → "the contact
 *   details on our website", never an empty mailto link.
 * - Switches (Business Info › Privacy Policy), each with a default used until
 *   the page is first saved:
 *     scos_biz_privacy_woocommerce        order records + order retention  (default: WooCommerce active)
 *     scos_biz_privacy_stripe             Stripe card payments             (default: WooCommerce Stripe gateway enabled)
 *     scos_biz_privacy_xero               invoices through Xero            (default: off)
 *     scos_biz_privacy_ai_services        AI services line + the APP 1.7 automated-decision statement (default: on)
 *     scos_biz_privacy_analytics_ga       Google Analytics                 (default: a GA4 measurement ID is set)
 *     scos_biz_privacy_analytics_clarity  Microsoft Clarity                (default: off)
 *     scos_biz_privacy_analytics_ahrefs   Ahrefs                           (default: off)
 * - Free text, one item per line, so client-specific lines never go in code:
 *     scos_biz_privacy_payments_other    "Name | privacy URL"
 *     scos_biz_privacy_analytics_other   "Name | what it does | privacy URL"
 *     scos_biz_privacy_extra_collect     extra items under "What personal information we collect"
 *     scos_biz_privacy_extra_retention   extra items under "How long we keep your information"
 *     scos_biz_privacy_extra_cookies     extra items under "We use cookies and similar technologies to:"
 *   scos_biz_privacy_log_retention_days  server log retention stated in the policy (default 90)
 * - Agent access: when the Agentic module is on, the agent lines come from
 *   Agent_Notices::privacy_items(), the same text as [scos_agent_privacy], so
 *   the policy and the agent guide page can't drift.
 *
 * Sections and letters are numbered at render time, so switching a section
 * or a line off never leaves a gap. Cross-references name the section
 * ("see Payments below") rather than its number for the same reason.
 *
 * Filter: scos_privacy_policy_sections( array $sections, array $settings )
 *   Each section: [ 'key' => string, 'title' => string ('' = unnumbered intro),
 *   'items' => [ [ kind, html, (id) ], ... ] ]. Kinds: 'p' (paragraph, intro
 *   only), 'a' (lettered), 'i' (roman, under the last 'a'), 'text' (unlabelled),
 *   'h3' (lettered sub-heading; third value is its id), 'n' (numbered),
 *   'n.n' (sub-numbered). Item html is passed through wp_kses_post().
 *
 * MARKUP CONTRACT
 * ---------------
 * section.terms-text > h2#sNN + ul > li.c01|c02|c03, styled by brighter-core
 * frontend.css under body.page-slug-privacy-policy. This class adds that body
 * class on the page set in Settings › Privacy (wp_page_for_privacy_policy), so
 * it no longer depends on the page's slug (#137).
 *
 * @package    SiteEssentials
 * @subpackage Modules\BusinessInfo
 */

namespace SiteEssentials\Modules\BusinessInfo;

defined( 'ABSPATH' ) || exit;

class Privacy_Policy {

	const SHORTCODE        = 'scos_privacy_policy';
	const BODY_CLASS       = 'page-slug-privacy-policy';
	const SETTINGS_GROUP   = 'scos_biz_settings_group';
	const PREFIX           = 'scos_biz_privacy_';
	const DEFAULT_LOG_DAYS = 90;

	/** Switches: option suffix. */
	const TOGGLES = [
		'woocommerce',
		'stripe',
		'xero',
		'ai_services',
		'analytics_ga',
		'analytics_clarity',
		'analytics_ahrefs',
	];

	/** Free-text fields: option suffix. */
	const TEXTS = [
		'payments_other',
		'analytics_other',
		'extra_collect',
		'extra_retention',
		'extra_cookies',
	];

	public static function init(): void {
		add_shortcode( self::SHORTCODE, [ __CLASS__, 'shortcode' ] );
		add_filter( 'body_class', [ __CLASS__, 'body_class' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
	}

	// ── Settings ────────────────────────────────────────────────────────────

	/**
	 * Registered in the Business Info group, so the existing page form
	 * (options.php) saves them with everything else.
	 */
	public static function register_settings(): void {
		foreach ( self::TOGGLES as $key ) {
			register_setting( self::SETTINGS_GROUP, self::PREFIX . $key, [
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_toggle' ],
			] );
		}
		foreach ( self::TEXTS as $key ) {
			register_setting( self::SETTINGS_GROUP, self::PREFIX . $key, [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			] );
		}
		register_setting( self::SETTINGS_GROUP, self::PREFIX . 'log_retention_days', [
			'type'              => 'integer',
			'sanitize_callback' => [ __CLASS__, 'sanitize_days' ],
		] );
	}

	/**
	 * An unticked checkbox isn't posted, so options.php passes null: store '0'.
	 *
	 * @param mixed $value Posted value.
	 */
	public static function sanitize_toggle( $value ): string {
		return '1' === (string) $value ? '1' : '0';
	}

	/**
	 * @param mixed $value Posted value.
	 */
	public static function sanitize_days( $value ): int {
		$days = absint( $value );
		return $days > 0 ? min( $days, 3650 ) : self::DEFAULT_LOG_DAYS;
	}

	/**
	 * Effective settings: the saved value, or the default for a switch never saved.
	 *
	 * @return array{woocommerce:bool,stripe:bool,xero:bool,ai_services:bool,analytics_ga:bool,analytics_clarity:bool,analytics_ahrefs:bool,payments_other:string,analytics_other:string,extra_collect:string,extra_retention:string,extra_cookies:string,log_retention_days:int}
	 */
	public static function settings(): array {
		$defaults = [
			'woocommerce'       => class_exists( 'WooCommerce' ),
			'stripe'            => self::stripe_gateway_enabled(),
			'xero'              => false,
			'ai_services'       => true,
			'analytics_ga'      => '' !== trim( (string) get_option( 'brighter_ga4_measurement_id', '' ) ),
			'analytics_clarity' => false,
			'analytics_ahrefs'  => false,
		];

		$settings = [];
		foreach ( self::TOGGLES as $key ) {
			$stored           = get_option( self::PREFIX . $key, null );
			$settings[ $key ] = null === $stored ? $defaults[ $key ] : '1' === (string) $stored;
		}
		foreach ( self::TEXTS as $key ) {
			$settings[ $key ] = (string) get_option( self::PREFIX . $key, '' );
		}
		$days                           = absint( get_option( self::PREFIX . 'log_retention_days', 0 ) );
		$settings['log_retention_days'] = $days > 0 ? $days : self::DEFAULT_LOG_DAYS;

		return $settings;
	}

	private static function stripe_gateway_enabled(): bool {
		$stripe = get_option( 'woocommerce_stripe_settings', [] );
		return is_array( $stripe ) && 'yes' === ( $stripe['enabled'] ?? '' );
	}

	/**
	 * Non-empty trimmed lines of a free-text field.
	 *
	 * @return string[]
	 */
	private static function lines( string $text ): array {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $text ) ) ) );
	}

	/**
	 * "a | b | c" lines split into trimmed parts.
	 *
	 * @return array<int, string[]>
	 */
	private static function pipe_lines( string $text ): array {
		return array_map(
			static function ( $line ) {
				return array_map( 'trim', explode( '|', $line ) );
			},
			self::lines( $text )
		);
	}

	// ── Front end ───────────────────────────────────────────────────────────

	/**
	 * Scope the policy CSS to the page Settings › Privacy points at.
	 * Keeps the old slug match so a site that never set that option still works.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( array $classes ): array {
		$page_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );
		if ( ( $page_id && is_page( $page_id ) ) || is_page( 'privacy-policy' ) ) {
			$classes[] = self::BODY_CLASS;
		}
		return $classes;
	}

	/**
	 * @param array|string $atts Unused; settings come from Business Info.
	 */
	public static function shortcode( $atts = [] ): string {
		return self::render();
	}

	public static function render(): string {
		$settings = self::settings();
		$sections = (array) apply_filters( 'scos_privacy_policy_sections', self::sections( $settings ), $settings );

		$html   = '';
		$number = 0;
		foreach ( $sections as $section ) {
			$items = (array) ( $section['items'] ?? [] );
			$title = (string) ( $section['title'] ?? '' );
			if ( '' === $title ) {
				$html .= '<section class="terms-text">';
				foreach ( $items as $item ) {
					$html .= '<p>' . wp_kses_post( (string) ( $item[1] ?? '' ) ) . '</p>';
				}
				$html .= '</section>';
				continue;
			}
			if ( ! $items ) {
				continue;
			}
			++$number;
			$html .= '<section class="terms-text"><h2 class="c00" id="' . esc_attr( sprintf( 's%02d', $number ) ) . '">'
				. esc_html( $number . '. ' . $title ) . '</h2><ul>' . self::render_items( $items ) . '</ul></section>';
		}

		return $html;
	}

	/**
	 * Labels are counted here, so items can be switched off without gaps.
	 *
	 * @param array $items Section items.
	 */
	private static function render_items( array $items ): string {
		$letter = 0;
		$roman  = 0;
		$num    = 0;
		$sub    = 0;
		$html   = '';

		foreach ( $items as $item ) {
			$kind    = (string) ( $item[0] ?? 'text' );
			$content = wp_kses_post( (string) ( $item[1] ?? '' ) );

			switch ( $kind ) {
				case 'a':
					$roman = 0;
					$html .= '<li class="c01">' . self::letter( $letter++ ) . '. ' . $content . '</li>';
					break;
				case 'i':
					$html .= '<li class="c02">' . self::roman( $roman++ ) . '. ' . $content . '</li>';
					break;
				case 'h3':
					$id    = isset( $item[2] ) ? ' id="' . esc_attr( (string) $item[2] ) . '"' : '';
					$html .= '<li class="c01"' . $id . '><h3>' . self::letter( $letter++ ) . '. ' . $content . '</h3></li>';
					break;
				case 'n':
					$sub   = 0;
					$html .= '<li class="c02">' . ( ++$num ) . '. ' . $content . '</li>';
					break;
				case 'n.n':
					$html .= '<li class="c03">' . $num . '.' . ( ++$sub ) . '. ' . $content . '</li>';
					break;
				default:
					$html .= '<li class="c01">' . $content . '</li>';
			}
		}

		return $html;
	}

	private static function letter( int $index ): string {
		return chr( 97 + min( $index, 25 ) );
	}

	private static function roman( int $index ): string {
		$numerals = [ 'i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x', 'xi', 'xii' ];
		return $numerals[ min( $index, count( $numerals ) - 1 ) ];
	}

	private static function link( string $url, string $text ): string {
		return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $text ) . '</a>';
	}

	// ── The policy ──────────────────────────────────────────────────────────

	/**
	 * @param array $s Effective settings (self::settings()).
	 */
	public static function sections( array $s ): array {
		$name      = esc_html( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
		$email     = sanitize_email( (string) get_option( 'scos_biz_email', '' ) );
		$contact   = trim( (string) get_option( 'scos_biz_contact_name', '' ) );
		$mail      = $email
			? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>'
			: esc_html__( 'the contact details on our website', 'site-essentials' );
		$home      = home_url( '/' );
		$woo       = $s['woocommerce'];
		$payments  = self::payment_items( $s );
		$analytics = self::analytics_list( $s );

		$sections = [];

		$sections[] = [
			'key'   => 'intro',
			'title' => '',
			'items' => [
				[ 'p', sprintf(
					/* translators: %s: business name (used twice) */
					__( 'This website (“the website”) is owned and operated by %1$s. In this Privacy Policy, “we”, “us” and “our” refer to %1$s.', 'site-essentials' ),
					$name
				) ],
			],
		];

		$sections[] = [
			'key'   => 'respect',
			'title' => __( 'We respect your privacy', 'site-essentials' ),
			'items' => [
				/* translators: %s: business name */
				[ 'a', sprintf( __( '%s respects your right to privacy and is committed to safeguarding it. This policy explains how we collect, use, store and disclose your personal information.', 'site-essentials' ), $name ) ],
				[ 'a', __( 'We handle personal information in line with the Australian Privacy Principles in the Privacy Act 1988 (Cth) and, where it applies, the EU and UK General Data Protection Regulation (GDPR).', 'site-essentials' ) ],
				[ 'a', __( '“Personal information” means information that identifies you, or could reasonably be used to identify you.', 'site-essentials' ) ],
				/* translators: %s: email link or "the contact details on our website" */
				[ 'a', sprintf( __( 'For questions about this policy, contact us at %s.', 'site-essentials' ), $mail ) ],
			],
		];

		$collect = [
			[ 'a', __( 'We collect and store personal information you submit through our website, give to us directly, or provide to us in other ways.', 'site-essentials' ) ],
			[ 'a', $woo
				? __( 'This usually includes your name, phone number, email address and delivery or site address, so we can respond to your enquiry, prepare a quote, and supply and deliver your order.', 'site-essentials' )
				: __( 'This usually includes your name, phone number and email address and, where relevant, your postal or site address, so we can respond to your enquiry, prepare a quote and provide our products and services.', 'site-essentials' ) ],
			[ 'a', __( 'We may collect additional information when you give feedback, tell us about your needs or project, change your contact preferences, respond to surveys or promotions, or contact us for support.', 'site-essentials' ) ],
			[ 'a', __( 'We may also collect any other information you provide while dealing with us.', 'site-essentials' ) ],
		];
		foreach ( self::lines( $s['extra_collect'] ) as $line ) {
			$collect[] = [ 'a', esc_html( $line ) ];
		}
		if ( $woo ) {
			$collect[] = [ 'a', __( 'When you place an order we keep an order record: what you ordered, the price, delivery details and payment status. We do not collect or store your card details (see Payments below).', 'site-essentials' ) ];
		}
		$sections[] = [
			'key'   => 'collect',
			'title' => __( 'What personal information we collect', 'site-essentials' ),
			'items' => $collect,
		];

		$sections[] = [
			'key'   => 'how_collect',
			'title' => __( 'How we collect your personal information', 'site-essentials' ),
			'items' => [
				[ 'a', __( 'We collect personal information when you deal with us online, by phone, by email or in person, when you use our website, and when we do business with you. We may also receive personal information from third parties; if we do, we protect it as set out in this policy.', 'site-essentials' ) ],
				[ 'a', __( 'By giving us personal information, you consent to us handling it in line with this Privacy Policy.', 'site-essentials' ) ],
			],
		];

		$sections[] = [
			'key'   => 'use',
			'title' => __( 'How we use your personal information', 'site-essentials' ),
			'items' => [
				[ 'a', __( 'We use personal information only for the purposes you have consented to, or that you would reasonably expect. These include to:', 'site-essentials' ) ],
				[ 'i', $woo
					? __( 'respond to enquiries, prepare quotes, and supply and deliver products and services;', 'site-essentials' )
					: __( 'respond to enquiries, prepare quotes, and provide our products and services;', 'site-essentials' ) ],
				[ 'i', __( 'process payments and manage our accounts;', 'site-essentials' ) ],
				[ 'i', __( 'administer, research and improve our business, products and services;', 'site-essentials' ) ],
				[ 'i', __( 'tell you about our products, services and offers (you can opt out at any time);', 'site-essentials' ) ],
				[ 'i', __( 'communicate with you by phone, email, SMS or mail; and', 'site-essentials' ) ],
				[ 'i', __( 'investigate and resolve complaints.', 'site-essentials' ) ],
				[ 'a', __( 'If you choose not to provide personal information, we may not be able to respond to you, provide our products and services, or give you full access to our website.', 'site-essentials' ) ],
				[ 'a', __( 'We may disclose personal information to comply with a legal requirement, such as a law, regulation, court order, subpoena, warrant or a request from a law enforcement agency.', 'site-essentials' ) ],
				[ 'a', __( 'If our business, or its assets, are sold or transferred, we may transfer our customer records, including personal information, to the extent permitted by law.', 'site-essentials' ) ],
			],
		];

		$disclose = [
			[ 'a', __( 'We may disclose personal information to our employees, contractors, suppliers, subcontractors, insurers and professional advisers, as reasonably needed for the purposes in this policy.', 'site-essentials' ) ],
			[ 'a', __( 'We use trusted service providers to run our business. Depending on how you deal with us, your information may be handled by:', 'site-essentials' ) ],
			[ 'i', __( 'website hosting, content delivery and security services;', 'site-essentials' ) ],
			[ 'i', __( 'email, cloud storage and office software;', 'site-essentials' ) ],
			[ 'i', $s['ai_services']
				? __( 'accounting, invoicing and payment services;', 'site-essentials' )
				: __( 'accounting, invoicing and payment services; and', 'site-essentials' ) ],
			[ 'i', $s['ai_services']
				? __( 'customer records, database and workflow automation tools that move enquiries between our systems; and', 'site-essentials' )
				: __( 'customer records, database and workflow automation tools that move enquiries between our systems.', 'site-essentials' ) ],
		];
		if ( $s['ai_services'] ) {
			$disclose[] = [ 'i', __( 'artificial intelligence (AI) services that help us sort enquiries, draft replies and prepare quotes. We send these services only the information needed for the task. We do not use AI to make decisions that significantly affect you, such as whether we will supply to you or on what terms, without a person involved.', 'site-essentials' ) ];
		}
		$disclose[] = [ 'a', __( 'These providers may only use your information to provide their service to us. A current list of the providers we use is available on request.', 'site-essentials' ) ];
		$disclose[] = [ 'a', __( 'We do not sell your personal information.', 'site-essentials' ) ];
		$sections[] = [
			'key'   => 'disclose',
			'title' => __( 'Disclosure of your personal information', 'site-essentials' ),
			'items' => $disclose,
		];

		if ( $payments ) {
			$sections[] = [
				'key'   => 'payments',
				'title' => __( 'Payments', 'site-essentials' ),
				'items' => $payments,
			];
		}

		$sections[] = [
			'key'   => 'overseas',
			'title' => __( 'Storage and overseas transfers', 'site-essentials' ),
			'items' => [
				[ 'a', __( 'Some of our service providers store or process information outside Australia, including in the United States, the European Union and other countries where those providers operate.', 'site-essentials' ) ],
				[ 'a', __( 'Before we disclose personal information overseas, we take reasonable steps to make sure the recipient protects it to a standard comparable to the Australian Privacy Principles, for example through contractual terms and the provider’s published privacy and security commitments.', 'site-essentials' ) ],
				[ 'a', __( 'Information you choose to publish through our website, such as a review, may be visible anywhere in the world. We cannot control how others use published information.', 'site-essentials' ) ],
			],
		];

		$retention = [
			[ 'a', __( 'We keep personal information only for as long as we need it for the purposes in this policy, or as required by law.', 'site-essentials' ) ],
		];
		if ( $woo || $payments ) {
			$retention[] = [ 'a', __( 'Order, invoice and payment records are kept for at least five years, because Australian tax law requires us to keep business records. After that they are deleted or anonymised.', 'site-essentials' ) ];
		}
		$retention[] = [ 'a', $woo
			? __( 'Enquiries and quotes that do not become orders are kept while they remain relevant to your enquiry, then deleted.', 'site-essentials' )
			: __( 'Enquiries and quotes are kept while they remain relevant to your enquiry or our work with you, then deleted.', 'site-essentials' ) ];
		if ( $woo ) {
			$retention[] = [ 'a', __( 'Unfinished, failed or cancelled online orders are deleted after a short period.', 'site-essentials' ) ];
		}
		foreach ( self::lines( $s['extra_retention'] ) as $line ) {
			$retention[] = [ 'a', esc_html( $line ) ];
		}
		$sections[] = [
			'key'   => 'retention',
			'title' => __( 'How long we keep your information', 'site-essentials' ),
			'items' => $retention,
		];

		$sections[] = [
			'key'   => 'security',
			'title' => __( 'Security of your personal information', 'site-essentials' ),
			'items' => [
				[ 'a', __( 'We take reasonable physical, electronic and administrative steps to protect personal information from misuse, interference, loss, and unauthorised access, change or disclosure.', 'site-essentials' ) ],
				[ 'a', __( 'We only use service providers that have appropriate security measures in place.', 'site-essentials' ) ],
				[ 'a', __( 'No method of sending information over the internet is completely secure, so we cannot guarantee the security of information you send to us online.', 'site-essentials' ) ],
				[ 'a', __( 'If a data breach is likely to cause you serious harm, we will notify you and, where required, the Office of the Australian Information Commissioner (OAIC).', 'site-essentials' ) ],
			],
		];

		$sections[] = [
			'key'   => 'access',
			'title' => __( 'Access and correction', 'site-essentials' ),
			'items' => [
				/* translators: %s: email link or "the contact details on our website" */
				[ 'a', sprintf( __( 'You can ask for a copy of the personal information we hold about you, or ask us to correct it if it is inaccurate, out of date, incomplete, irrelevant or misleading. Contact us at %s.', 'site-essentials' ), $mail ) ],
				[ 'a', __( 'We may ask you to verify your identity first. In some circumstances allowed by the Privacy Act or other law we may refuse a request; if so, we will tell you why.', 'site-essentials' ) ],
			],
		];

		$attention = '' !== $contact
			/* translators: %s: contact person's name */
			? sprintf( __( ', attention %s', 'site-essentials' ), esc_html( $contact ) )
			: '';
		$sections[] = [
			'key'   => 'complaints',
			'title' => __( 'Complaints about privacy', 'site-essentials' ),
			'items' => [
				/* translators: 1: email link or "the contact details on our website", 2: ", attention <name>" or empty */
				[ 'a', sprintf( __( 'If you have a complaint about how we have handled your personal information, please send details to %1$s%2$s. We take complaints seriously and aim to respond within 30 days.', 'site-essentials' ), $mail, $attention ) ],
				/* translators: %s: link to oaic.gov.au */
				[ 'a', sprintf( __( 'If you are not satisfied with our response, you can contact the Office of the Australian Information Commissioner at %s.', 'site-essentials' ), self::link( 'https://www.oaic.gov.au', 'oaic.gov.au' ) ) ],
			],
		];

		$sections[] = [
			'key'   => 'gdpr',
			'title' => __( 'If you are in the EU or UK (GDPR)', 'site-essentials' ),
			'items' => [
				[ 'a', __( 'If the GDPR applies to you, we process your personal information on the basis of your consent, to perform a contract with you or take steps before entering one (such as a quote), to meet a legal obligation, or for our legitimate business interests.', 'site-essentials' ) ],
				[ 'a', __( 'You have the right to:', 'site-essentials' ) ],
				[ 'i', __( 'be told how your personal information is used;', 'site-essentials' ) ],
				[ 'i', __( 'access your personal information;', 'site-essentials' ) ],
				[ 'i', __( 'have inaccurate or incomplete information corrected;', 'site-essentials' ) ],
				[ 'i', __( 'have your information deleted (the “right to be forgotten”);', 'site-essentials' ) ],
				[ 'i', __( 'restrict or object to how we process it;', 'site-essentials' ) ],
				[ 'i', __( 'receive your information in a portable format; and', 'site-essentials' ) ],
				[ 'i', __( 'object to automated decision-making and profiling.', 'site-essentials' ) ],
				/* translators: %s: email link or "the contact details on our website" */
				[ 'a', sprintf( __( 'To use any of these rights, contact us at %s. We may ask you to verify your identity first. You may also complain to the data protection authority in your country.', 'site-essentials' ), $mail ) ],
				[ 'a', __( 'We do not knowingly collect personal information from children under 16 without the consent of a parent or guardian.', 'site-essentials' ) ],
			],
		];

		$sections[] = [
			'key'   => 'changes',
			'title' => __( 'Changes to this Privacy Policy', 'site-essentials' ),
			'items' => [
				[ 'a', __( 'We may update this Privacy Policy from time to time. Changes take effect when they are published on this page, so please check back periodically.', 'site-essentials' ) ],
			],
		];

		$sections[] = [
			'key'   => 'website',
			'title' => __( 'Website, analytics and cookies', 'site-essentials' ),
			'items' => self::website_items( $s, $home, $woo, $analytics ),
		];

		return $sections;
	}

	/**
	 * The Payments section, or [] when the site takes no payments.
	 *
	 * @param array $s Effective settings.
	 */
	private static function payment_items( array $s ): array {
		$others = self::pipe_lines( $s['payments_other'] );
		if ( ! $s['woocommerce'] && ! $s['stripe'] && ! $s['xero'] && ! $others ) {
			return [];
		}

		$items = [
			[ 'a', __( 'We do not collect or store your credit or debit card details.', 'site-essentials' ) ],
		];
		if ( $s['stripe'] ) {
			$items[] = [ 'a', __( 'Card payments are processed securely by Stripe, which handles your card details under its own privacy policy and security standards.', 'site-essentials' )
				. ' ' . self::link( 'https://stripe.com/au/privacy', __( 'View Stripe’s Privacy Policy', 'site-essentials' ) ) . '.' ];
		}
		if ( $s['xero'] ) {
			$xero = $s['stripe']
				? __( 'If you choose to pay by invoice, we issue the invoice through our accounting software, Xero. Invoices can be paid by card (processed by Stripe) or by bank transfer.', 'site-essentials' )
				: __( 'If you choose to pay by invoice, we issue the invoice through our accounting software, Xero.', 'site-essentials' );
			$items[] = [ 'a', $xero . ' ' . self::link( 'https://www.xero.com/au/legal/privacy/', __( 'View Xero’s Privacy Notice', 'site-essentials' ) ) . '.' ];
		}
		foreach ( $others as $parts ) {
			$label = esc_html( $parts[0] );
			$line  = sprintf(
				/* translators: %s: payment provider name */
				__( 'Some payments are processed by %s, under its own privacy policy and security standards.', 'site-essentials' ),
				$label
			);
			if ( ! empty( $parts[1] ) && wp_http_validate_url( $parts[1] ) ) {
				/* translators: %s: payment provider name */
				$line .= ' ' . self::link( $parts[1], sprintf( __( 'View %s’s Privacy Policy', 'site-essentials' ), $parts[0] ) ) . '.';
			}
			$items[] = [ 'a', $line ];
		}

		return $items;
	}

	/**
	 * <li> items for the analytics list, or [] when no tool is used.
	 *
	 * @param array $s Effective settings.
	 * @return string[]
	 */
	private static function analytics_list( array $s ): array {
		$tools = [];
		if ( $s['analytics_ga'] ) {
			$tools[] = [ 'Google Analytics', __( 'measures site traffic and how visitors use the site.', 'site-essentials' ), 'https://policies.google.com/privacy', __( 'View Privacy Policy', 'site-essentials' ) ];
		}
		if ( $s['analytics_ahrefs'] ) {
			$tools[] = [ 'Ahrefs', __( 'measures search performance.', 'site-essentials' ), 'https://ahrefs.com/privacy-policy', __( 'View Privacy Policy', 'site-essentials' ) ];
		}
		if ( $s['analytics_clarity'] ) {
			$tools[] = [ 'Microsoft Clarity', __( 'records anonymised sessions and heatmaps so we can see where the site is hard to use.', 'site-essentials' ), 'https://privacy.microsoft.com/en-us/privacystatement', __( 'View Privacy Statement', 'site-essentials' ) ];
		}
		foreach ( self::pipe_lines( $s['analytics_other'] ) as $parts ) {
			$tools[] = [ $parts[0], $parts[1] ?? '', $parts[2] ?? '', __( 'View Privacy Policy', 'site-essentials' ) ];
		}

		$list = [];
		foreach ( $tools as $tool ) {
			$li = '<strong>' . esc_html( $tool[0] ) . '</strong>';
			if ( '' !== $tool[1] ) {
				$li .= ': ' . esc_html( $tool[1] );
			}
			if ( '' !== $tool[2] && wp_http_validate_url( $tool[2] ) ) {
				$li .= ' ' . self::link( $tool[2], $tool[3] );
			}
			$list[] = '<li>' . $li . '</li>';
		}
		return $list;
	}

	/**
	 * @param array    $s         Effective settings.
	 * @param string   $home      Home URL.
	 * @param bool     $woo       Orders switch.
	 * @param string[] $analytics Analytics <li> items.
	 */
	private static function website_items( array $s, string $home, bool $woo, array $analytics ): array {
		$items = [
			[ 'h3', __( 'When you visit our website', 'site-essentials' ), 'website-visit' ],
			/* translators: %s: link to the home page */
			[ 'text', sprintf( __( 'When you visit our website (%s), we may collect information such as your browser type, operating system, device type and the website you came from. We use this in aggregate to understand how people use our site and to improve it.', 'site-essentials' ),
				'<a href="' . esc_url( $home ) . '">' . esc_html( $home ) . '</a>' ) ],
			[ 'text', sprintf(
				/* translators: %d: days server logs are kept */
				__( 'Our web servers and security services automatically record technical information about each visit, such as your IP address, browser type, the pages requested and the time. We use these records only to keep the site secure and working and to investigate misuse. Our server logs are deleted after %d days.', 'site-essentials' ),
				(int) $s['log_retention_days']
			) ],
			[ 'text', __( 'Some visitors use AI assistants or browser agents to read our site or prepare an enquiry. We treat anything submitted this way as coming from you, and we do not collect anything extra from these tools.', 'site-essentials' ) ],
		];

		// Same lines as [scos_agent_privacy] when the Agentic module is on.
		if ( shortcode_exists( 'scos_agent_privacy' ) && is_callable( [ '\SiteEssentials\Modules\Agentic\Agent_Notices', 'privacy_items' ] ) ) {
			foreach ( \SiteEssentials\Modules\Agentic\Agent_Notices::privacy_items() as $line ) {
				$items[] = [ 'text', esc_html( $line ) ];
			}
		}

		$items[] = [ 'h3', __( 'Third-party websites', 'site-essentials' ), 'third-party-sites' ];
		$items[] = [ 'text', __( 'Our site may link to websites we do not own or control. These links are for convenience only and do not mean we endorse those websites. We are not responsible for their privacy practices, so please read the privacy policy of any website you visit.', 'site-essentials' ) ];

		if ( $analytics ) {
			$intro = __( 'We use the following third-party services to measure and improve our website. They may collect information such as your IP address, device type, browser, pages visited and how you interact with the site:', 'site-essentials' );
			$outro = __( 'You can block or delete cookies in your browser settings.', 'site-essentials' );
			if ( $s['analytics_ga'] ) {
				/* translators: %s: link to the Google Analytics opt-out add-on */
				$outro = sprintf( __( 'You can block or delete cookies in your browser settings, or opt out of Google Analytics with the %s.', 'site-essentials' ),
					self::link( 'https://tools.google.com/dlpage/gaoptout', __( 'Google Analytics Opt-out Browser Add-on', 'site-essentials' ) ) );
			}
			$items[] = [ 'h3', __( 'Analytics and marketing tools', 'site-essentials' ), 'analytics-tools' ];
			$items[] = [ 'text', $intro . '<ul>' . implode( '', $analytics ) . '</ul>' . $outro ];
		}

		$items[] = [ 'h3', __( 'Cookies', 'site-essentials' ), 'cookie' ];
		$items[] = [ 'text', __( 'Cookies are small files your browser stores so a website can recognise your device. Cookies on their own do not tell us your name, address or email address.', 'site-essentials' ) ];
		$items[] = [ 'n', __( 'We use cookies and similar technologies to:', 'site-essentials' ) ];
		$items[] = [ 'n.n', $woo
			? __( 'keep the website working, including your cart and checkout;', 'site-essentials' )
			: __( 'keep the website working;', 'site-essentials' ) ];
		foreach ( self::lines( $s['extra_cookies'] ) as $line ) {
			$items[] = [ 'n.n', esc_html( $line ) ];
		}
		$items[] = [ 'n.n', __( 'monitor and improve the performance of the website;', 'site-essentials' ) ];
		if ( $analytics ) {
			$items[] = [ 'n.n', __( 'remember your preferences so the site is easier to use; and', 'site-essentials' ) ];
			$items[] = [ 'n.n', __( 'understand which pages and marketing are useful, through the analytics tools listed above.', 'site-essentials' ) ];
		} else {
			$items[] = [ 'n.n', __( 'remember your preferences so the site is easier to use.', 'site-essentials' ) ];
		}
		$items[] = [ 'n', __( 'Third-party services on our site, such as embedded maps or videos, may set their own cookies.', 'site-essentials' ) ];
		$items[] = [ 'n', $woo
			? __( 'You can set your browser to refuse cookies or warn you before one is set. If you turn cookies off, parts of the site, such as the cart and checkout, may not work properly.', 'site-essentials' )
			: __( 'You can set your browser to refuse cookies or warn you before one is set. If you turn cookies off, parts of the site may not work properly.', 'site-essentials' ) ];
		$items[] = [ 'n', __( 'If you have agreed to receive emails from us, you can unsubscribe at any time using the link in any email.', 'site-essentials' ) ];

		return $items;
	}
}
