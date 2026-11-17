<?php
/**
 * Agentic — enquiry handoff: the form's fields and the pre-filled link
 *
 * v1.0 | 2026-10-22
 * v1.1 | 2026-10-25 — link_format(): how an agent builds the link itself,
 *                      for agents that can only make GET requests.
 * v1.2 | 2026-10-25 — clean_values() shared with Enquiry_Send; parse() records
 *                      the Breakdance form behind the page (target), kept out
 *                      of the public schema (#225).
 * v1.3 | 2026-10-25 — get_schema()/link_format() can skip the fetch, so the
 *                      enquiry page can describe itself without requesting itself;
 *                      the schema is rebuilt by cron 30s after a save.
 * v1.4 | 2026-11-17 — The page is fetched from this server, not back through
 *                      the CDN: a zone's bot protection challenged the site's
 *                      own request (403), so /enquiry reported no form. Falls
 *                      back to the public URL. A failed read keeps its reason
 *                      for the settings page (last_error()).
 *
 * WHAT THIS IS FOR
 * ----------------
 * An agent in a chat or a terminal can't submit the site's quote form, and
 * shouldn't: a public write endpoint is a spam hole, and the person should
 * see what goes out in their name. Instead the agent drafts the enquiry and
 * hands the person a link. Opening it fills in the site's own form; the
 * person checks it and presses send. (#200 D17)
 *
 * The enquiry then goes through the form exactly like a typed one: the same
 * honeypot, the same notifications, the same inbox — on this site. Nothing
 * is stored or sent anywhere by SCOS, so there is no message routing to set
 * up and nothing crosses between sites.
 *
 * WHERE THE FIELDS COME FROM
 * --------------------------
 * The form's WebMCP annotations: the <form toolname="…"> on the enquiry page
 * and its fields' toolparamdescription attributes. Browser agents already
 * read those, so the site describes its form once. The page is fetched
 * (loopback) and parsed, then cached until any post is saved. Builder-
 * agnostic: anything that renders a form with a toolname works.
 *
 * The loopback is pinned to this server (filter scos_enquiry_loopback_ip,
 * default 127.0.0.1; '' turns pinning off). Through a CDN the request comes
 * from a datacenter IP with a WordPress user agent, which bot protection
 * such as Cloudflare's Bot Fight Mode challenges. The host name, SNI and
 * certificate check are unchanged; only where the connection goes is.
 *
 * THE LINK
 * --------
 *   {page}#scos-enquiry&name=Jo&services=Lawn+mowing&services=Weeding
 * Values ride in the URL fragment, which browsers never send to the server,
 * so names and emails stay out of access logs, cache keys and analytics.
 * Enquiry_Prefill reads it on the page and clears it.
 *
 * Settings:
 *   scos_agentic_enquiry_page_id  the page the form is on (0 = feature off)
 *   scos_agentic_enquiry_form     the form's toolname (blank = first annotated form)
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Enquiry_Form {

	const OPTION_PAGE = 'scos_agentic_enquiry_page_id';
	const OPTION_FORM = 'scos_agentic_enquiry_form';
	const TRANSIENT   = 'se_enquiry_form_schema';
	const FRAGMENT    = 'scos-enquiry';
	const MAX_LENGTH  = 2000;

	/** Input types an agent never fills. */
	const SKIP_TYPES = [ 'hidden', 'submit', 'button', 'reset', 'image', 'file', 'password', 'hpinput' ];

	// ── Boot ─────────────────────────────────────────────────────────────────

	const WARM_HOOK = 'se_enquiry_form_warm';

	public static function init(): void {
		add_action( 'save_post', [ __CLASS__, 'flush' ] );
		add_action( self::WARM_HOOK, [ __CLASS__, 'warm' ] );
	}

	public static function is_enabled(): bool {
		return '' !== self::page_url();
	}

	/**
	 * The enquiry page's URL, or '' when the feature is off or the page isn't published.
	 */
	public static function page_url(): string {
		$id = absint( get_option( self::OPTION_PAGE, 0 ) );
		if ( ! $id || 'publish' !== get_post_status( $id ) ) {
			return '';
		}
		return (string) get_permalink( $id );
	}

	public static function flush(): void {
		delete_transient( self::TRANSIENT );
		// Rebuild it shortly, so the enquiry page's own hint has the schema
		// without the page having to fetch itself (see get_schema).
		if ( self::is_enabled() && ! wp_next_scheduled( self::WARM_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::WARM_HOOK );
		}
	}

	public static function warm(): void {
		self::get_schema();
	}

	// ── Schema ───────────────────────────────────────────────────────────────

	/**
	 * The form an agent can prepare, read from the live page.
	 *
	 * @return array|null { form, description, url, fields[] }, or null when
	 *                    the feature is off or no annotated form was found.
	 */
	public static function get_schema( bool $fetch = true ): ?array {
		$url = self::page_url();
		if ( '' === $url ) {
			return null;
		}

		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) && ( $cached['url'] ?? '' ) === $url ) {
			return $cached['schema'];
		}
		// Rendering the enquiry page must not fetch the enquiry page: the
		// request would loop back through this code and cache a failed read.
		if ( ! $fetch ) {
			return null;
		}

		$error  = '';
		$schema = self::read_form( $url, $error );

		// A failed read is cached briefly so a broken page isn't refetched on every request.
		set_transient(
			self::TRANSIENT,
			[ 'url' => $url, 'schema' => $schema, 'error' => $schema ? '' : $error ],
			$schema ? 12 * HOUR_IN_SECONDS : 10 * MINUTE_IN_SECONDS
		);

		return $schema;
	}

	/**
	 * Why the last read found no form, for the settings page. Never shown to agents.
	 */
	public static function last_error(): string {
		$cached = get_transient( self::TRANSIENT );
		return is_array( $cached ) ? (string) ( $cached['error'] ?? '' ) : '';
	}

	/** Host and port being pinned for the current request, or [] when none. */
	private static $pin = [];

	private static function read_form( string $url, string &$error = '' ): ?array {
		$ip       = (string) apply_filters( 'scos_enquiry_loopback_ip', '127.0.0.1', $url );
		$response = null;

		if ( '' !== $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$response = self::fetch_pinned( $url, $ip );
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				$response = null; // This server didn't answer for the host: try the public URL.
			}
		}

		if ( null === $response ) {
			$response = wp_remote_get( $url, [ 'timeout' => 8 ] );
		}

		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$mitigated = wp_remote_retrieve_header( $response, 'cf-mitigated' );
			/* translators: 1: HTTP status code, 2: optional note */
			$error = sprintf( __( 'The enquiry page returned HTTP %1$d%2$s.', 'site-essentials' ), $code, $mitigated ? ' (' . __( 'challenged by Cloudflare', 'site-essentials' ) . ')' : '' );
			return null;
		}

		$schema = self::parse( (string) wp_remote_retrieve_body( $response ), $url );
		if ( ! $schema ) {
			$error = __( 'No form with a toolname attribute was found on the enquiry page.', 'site-essentials' );
		}
		return $schema;
	}

	/**
	 * GET the page with its host resolved to $ip. cURL transport only; on any
	 * other transport the hook never fires and this is an ordinary request.
	 *
	 * @return array|\WP_Error
	 */
	private static function fetch_pinned( string $url, string $ip ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( '' === $host || ! defined( 'CURLOPT_RESOLVE' ) ) {
			return new \WP_Error( 'scos_enquiry_no_pin', 'Pinning unavailable.' );
		}
		$port      = (int) ( wp_parse_url( $url, PHP_URL_PORT ) ?: ( 'http' === wp_parse_url( $url, PHP_URL_SCHEME ) ? 80 : 443 ) );
		self::$pin = [ 'host' => $host, 'entry' => $host . ':' . $port . ':' . ( false !== strpos( $ip, ':' ) ? '[' . $ip . ']' : $ip ) ];

		add_action( 'http_api_curl', [ __CLASS__, 'pin_curl' ], 10, 3 );
		// No redirects: a redirect to another host must not be pinned to this server.
		$response = wp_remote_get( $url, [ 'timeout' => 8, 'redirection' => 0 ] );
		remove_action( 'http_api_curl', [ __CLASS__, 'pin_curl' ], 10 );
		self::$pin = [];

		return $response;
	}

	/**
	 * http_api_curl callback: resolve the pinned host to this server.
	 *
	 * @param resource|\CurlHandle $handle
	 * @param array                $args
	 * @param string               $url
	 */
	public static function pin_curl( $handle, $args, $url ): void {
		if ( self::$pin && wp_parse_url( (string) $url, PHP_URL_HOST ) === self::$pin['host'] ) {
			curl_setopt( $handle, CURLOPT_RESOLVE, [ self::$pin['entry'] ] );
		}
	}

	/**
	 * Parse the annotated form out of a page's HTML.
	 *
	 * Public so it can be checked against saved HTML without a request.
	 */
	public static function parse( string $html, string $url ): ?array {
		if ( '' === $html || ! class_exists( 'DOMDocument' ) ) {
			return null;
		}

		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$xpath    = new \DOMXPath( $dom );
		$toolname = trim( (string) get_option( self::OPTION_FORM, '' ) );
		$form     = null;
		foreach ( $xpath->query( '//form[@toolname]' ) as $candidate ) {
			if ( '' === $toolname || $candidate->getAttribute( 'toolname' ) === $toolname ) {
				$form = $candidate;
				break;
			}
		}
		if ( ! $form ) {
			return null;
		}

		$labels = [];
		foreach ( $xpath->query( './/label[@for]', $form ) as $label ) {
			$labels[ $label->getAttribute( 'for' ) ] = self::text( $label->textContent );
		}

		$fields = [];
		foreach ( $xpath->query( './/input|.//textarea|.//select', $form ) as $el ) {
			$name = $el->getAttribute( 'name' );
			$tag  = strtolower( $el->nodeName );
			$type = 'input' === $tag ? strtolower( $el->getAttribute( 'type' ) ?: 'text' ) : $tag;

			// Hidden plumbing and honeypots (tabindex -1, off-screen) are never offered to an agent.
			if ( '' === $name || in_array( $type, self::SKIP_TYPES, true ) || '-1' === $el->getAttribute( 'tabindex' ) ) {
				continue;
			}

			$key = self::field_key( $name );
			if ( ! isset( $fields[ $key ] ) ) {
				$label = $labels[ $el->getAttribute( 'id' ) ] ?? '';
				$fields[ $key ] = array_filter( [
					'name'        => $key,
					'type'        => $type,
					'label'       => in_array( $type, [ 'checkbox', 'radio' ], true ) ? '' : $label,
					'description' => self::text( $el->getAttribute( 'toolparamdescription' ) ?: $el->getAttribute( 'placeholder' ) ),
					'required'    => $el->hasAttribute( 'required' ),
					'multiple'    => $el->hasAttribute( 'multiple' ) || '[]' === substr( $name, -2 ),
					'options'     => [],
				], [ __CLASS__, 'keep' ] );
			}

			if ( in_array( $type, [ 'checkbox', 'radio' ], true ) && '' !== $el->getAttribute( 'value' ) ) {
				$fields[ $key ]['options'][] = $el->getAttribute( 'value' );
			}
			if ( 'select' === $type ) {
				foreach ( $xpath->query( './/option', $el ) as $option ) {
					if ( '' !== $option->getAttribute( 'value' ) ) {
						$fields[ $key ]['options'][] = $option->getAttribute( 'value' );
					}
				}
			}
		}

		if ( ! $fields ) {
			return null;
		}

		$schema = [
			'form'        => $form->getAttribute( 'toolname' ),
			'description' => self::text( $form->getAttribute( 'tooldescription' ) ),
			'url'         => $url,
			'fields'      => array_values( $fields ),
		];

		// A Breakdance form posts its own form and post IDs; Enquiry_Send runs that
		// form's actions on a confirmed enquiry. Internal: public_schema() drops it.
		$ids = [];
		foreach ( [ 'form_id', 'post_id' ] as $hidden ) {
			$input = $xpath->query( './/input[@name="' . $hidden . '"]', $form )->item( 0 );
			if ( $input instanceof \DOMElement && absint( $input->getAttribute( 'value' ) ) ) {
				$ids[ $hidden ] = absint( $input->getAttribute( 'value' ) );
			}
		}
		if ( 2 === count( $ids ) ) {
			$schema['target'] = [ 'builder' => 'breakdance' ] + $ids;
		}

		return $schema;
	}

	/**
	 * The schema as agents see it: without the internal form target.
	 *
	 * @return array|null
	 */
	public static function public_schema(): ?array {
		$schema = self::get_schema();
		if ( $schema ) {
			unset( $schema['target'] );
		}
		return $schema;
	}

	// ── Link ─────────────────────────────────────────────────────────────────

	/**
	 * Build the pre-filled link from an agent's values.
	 *
	 * Unknown fields and values that aren't one of a field's options are
	 * dropped and reported, so the agent can correct itself.
	 *
	 * @param  array $values field name => string or string[].
	 * @return array|\WP_Error { url, filled[], missing_required[], ignored[] }
	 */
	public static function build_link( array $values ) {
		$clean = self::clean_values( $values );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		$pairs = [];
		foreach ( $clean['values'] as $key => $items ) {
			foreach ( $items as $item ) {
				$pairs[] = rawurlencode( $key ) . '=' . rawurlencode( $item );
			}
		}

		return [
			'url'              => $clean['schema']['url'] . '#' . implode( '&', array_merge( [ self::FRAGMENT ], $pairs ) ),
			'filled'           => array_keys( $clean['values'] ),
			'missing_required' => $clean['missing'],
			'ignored'          => $clean['ignored'],
		];
	}

	/**
	 * Check an agent's values against the form.
	 *
	 * Unknown fields and values that aren't one of a field's options are
	 * dropped and reported, so the agent can correct itself.
	 *
	 * @param  array $values field name => string or string[].
	 * @return array|\WP_Error { schema, values: [name => string[]], missing[], ignored[] }
	 */
	public static function clean_values( array $values ) {
		$schema = self::get_schema();
		if ( ! $schema ) {
			return new \WP_Error( 'scos_enquiry_unavailable', __( 'No enquiry form is available on this site.', 'site-essentials' ), [ 'status' => 503 ] );
		}

		$clean   = [];
		$ignored = [];
		$fields  = array_column( $schema['fields'], null, 'name' );

		foreach ( $values as $key => $value ) {
			$key = (string) $key;
			if ( ! isset( $fields[ $key ] ) ) {
				$ignored[] = $key;
				continue;
			}
			$field = $fields[ $key ];

			foreach ( array_slice( (array) $value, 0, 50 ) as $item ) {
				$item = self::clean_value( (string) $item, $field['type'] );
				if ( '' === $item ) {
					continue;
				}
				if ( ! empty( $field['options'] ) ) {
					$match = self::match_option( $item, $field['options'] );
					if ( null === $match ) {
						$ignored[] = $key . ': ' . $item;
						continue;
					}
					$item = $match;
				}
				$clean[ $key ][] = $item;
			}
		}

		$missing = [];
		foreach ( $fields as $key => $field ) {
			if ( ! empty( $field['required'] ) && empty( $clean[ $key ] ) ) {
				$missing[] = $key;
			}
		}

		return [
			'schema'  => $schema,
			'values'  => $clean,
			'missing' => $missing,
			'ignored' => $ignored,
		];
	}

	/**
	 * How to build the link without the POST route, for agents that can only
	 * make GET requests (search-engine AI modes, most browsing agents). The
	 * link is the form page plus a fragment, so any agent can assemble it and
	 * the values still never reach the server.
	 *
	 * @param  bool $fetch False to use only the cached schema (see get_schema).
	 * @return array|null { base, rules, example } or null with no form.
	 */
	public static function link_format( bool $fetch = true ): ?array {
		$schema = self::get_schema( $fetch );
		if ( ! $schema ) {
			return null;
		}

		$base    = $schema['url'] . '#' . self::FRAGMENT;
		$example = $base;
		// The first field and the first email field: enough to show the shape.
		$email = current( array_filter( $schema['fields'], static function ( $f ) { return 'email' === $f['type']; } ) );
		foreach ( array_filter( [ $schema['fields'][0] ?? null, $email ?: null ] ) as $field ) {
			$example .= '&' . rawurlencode( $field['name'] ) . '=' . rawurlencode( 'email' === $field['type'] ? 'jo@example.com' : 'Jo' );
		}

		return [
			'base'    => $base,
			'rules'   => __( 'Add &name=value to base for each field, using the field names above. URL-encode each value. For a multiple-choice field, repeat the name once per choice, with values exactly as listed in options.', 'site-essentials' ),
			'example' => $example,
		];
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * fields[email] → email, fields[services][] → services, plain names unchanged.
	 * Enquiry_Prefill applies the same rule in the browser.
	 */
	public static function field_key( string $name ): string {
		if ( preg_match( '/^fields\[([^\]]*)\](\[\])?$/', $name, $m ) ) {
			return $m[1];
		}
		return preg_replace( '/\[\]$/', '', $name );
	}

	private static function clean_value( string $value, string $type ): string {
		$value = 'textarea' === $type ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		if ( 'email' === $type ) {
			$value = sanitize_email( $value );
		}
		return mb_substr( $value, 0, self::MAX_LENGTH );
	}

	private static function match_option( string $value, array $options ): ?string {
		foreach ( $options as $option ) {
			if ( 0 === strcasecmp( $option, $value ) ) {
				return $option;
			}
		}
		return null;
	}

	private static function text( string $text ): string {
		return trim( preg_replace( '/\s+/u', ' ', str_replace( '*', '', $text ) ) );
	}

	/**
	 * array_filter callback: drop empty strings, empty arrays and false, keep the rest.
	 */
	private static function keep( $value ): bool {
		return ! ( '' === $value || [] === $value || false === $value );
	}
}
