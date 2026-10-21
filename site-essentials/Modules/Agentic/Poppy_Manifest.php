<?php
/**
 * Agentic — Personal Agent Protocol manifest ("Poppy") — EXPERIMENTAL
 *
 * v1.0 | 2026-10-21
 *
 * WHAT THIS IS FOR
 * ----------------
 * The Personal Agent Protocol (Sierra + Meta, Draft 0.2) lets a business tell
 * a person's own AI agent who it is and how that agent may interact with it.
 * The manifest lives at /.well-known/poppy.json.
 *
 * WHAT THIS DECLARES — AND WHY SO LITTLE
 * --------------------------------------
 * Website only: protocol_version, organization {name, domain}, and an empty
 * "web" object. Declaring an agent, APIs or browser sessions requires
 * auth.issuer — a real OAuth authorization server with RFC 8414 metadata,
 * token and revocation endpoints. A service-business site has none, and
 * pointing at one that does not exist would be a lie a client acts on.
 *
 * organization.domain must equal the host serving the file, so it is taken
 * from home_url(), never typed in.
 *
 * Behind an opt-in (scos_agentic_poppy_enabled, default off): the spec is a
 * draft and will change before 1.0. isitagentready reports it as
 * informational only (no score impact).
 *
 * Filter: scos_poppy_manifest (array $manifest).
 *
 * Decision log: issue #200, D14.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Poppy_Manifest {

	const OPTION  = 'scos_agentic_poppy_enabled';
	const PATH    = 'poppy.json';
	const VERSION = '0.2';

	// ── Boot ─────────────────────────────────────────────────────────────────

	public static function init(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		add_filter( 'scos_well_known_response', [ __CLASS__, 'maybe_serve' ], 10, 2 );
	}

	public static function is_enabled(): bool {
		return (bool) get_option( self::OPTION, false );
	}

	/**
	 * @param  array|null $response Response from an earlier handler, if any.
	 * @param  string     $path     Requested path minus the /.well-known/ prefix.
	 * @return array|null
	 */
	public static function maybe_serve( $response, string $path ) {
		if ( self::PATH !== $path ) {
			return $response;
		}

		return [
			'content' => wp_json_encode( self::build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			'type'    => 'application/json',
		];
	}

	public static function get_url(): string {
		return home_url( '/.well-known/' . self::PATH );
	}

	// ── Document ──────────────────────────────────────────────────────────────

	/**
	 * @return array
	 */
	public static function build(): array {
		$name = trim( (string) get_option( 'scos_biz_business_name', '' ) );

		$manifest = [
			'protocol_version' => self::VERSION,
			'organization'     => [
				'name'   => '' !== $name ? $name : (string) get_bloginfo( 'name' ),
				'domain' => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			],
			'web'              => new \stdClass(),
		];

		return apply_filters( 'scos_poppy_manifest', $manifest );
	}
}
