<?php
/**
 * Agentic — ARD catalog (Agentic Resource Discovery) — EXPERIMENTAL
 *
 * v1.1 | 2026-10-30 — Link headers, <head> links and a robots.txt Agentmap line point at the catalog
 * v1.0 | 2026-10-21
 *
 * WHAT THIS IS FOR
 * ----------------
 * An ARD catalog lists the agent-usable resources a site offers, so a
 * discovery agent can find them in one fetch. Here the entries are the
 * public, read-only resources this module already serves: the RFC 9727 API
 * catalog, the OpenAPI description of scos/v1, and the MCP server card.
 *
 * WHY IT IS BEHIND AN OPT-IN (scos_agentic_ard_enabled, default off)
 * -----------------------------------------------------------------
 * - ARD is a proposal (v0.91, Aug 2026) and has already moved its path once.
 * - Released Lighthouse (13.5) validates an older schema: specVersion "1.0",
 *   and ONLY specVersion / host / entries at the top level. Without a file the
 *   audit is Not Applicable; with one, any mismatch scores 0. So this must
 *   never reach a client site by accident.
 *
 * SHAPE
 * -----
 * The same document is served at two paths:
 *   /.well-known/ard.json         current spec path (Lighthouse main reads it)
 *   /.well-known/ai-catalog.json  legacy path (isitagentready, Lighthouse 13.5)
 * with Access-Control-Allow-Origin: * as the checkers expect. Top-level keys
 * are limited to the three Lighthouse 13.5 accepts. Every entry url points at
 * a document this module serves, so none can dangle. Nothing authenticated is
 * listed (no WordPress MCP endpoint).
 *
 * Filter: scos_ard_catalog (array $catalog) — add or adjust entries.
 *
 * Decision log: issue #200, D14.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Ard_Catalog {

	const OPTION = 'scos_agentic_ard_enabled';
	const PATHS  = [ 'ard.json', 'ai-catalog.json' ];

	// ── Boot ─────────────────────────────────────────────────────────────────

	public static function init(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		add_filter( 'scos_well_known_response', [ __CLASS__, 'maybe_serve' ], 10, 2 );
		add_action( 'send_headers', [ __CLASS__, 'send_link_headers' ] );
		add_action( 'wp_head', [ __CLASS__, 'output_head_links' ] );
		// After Virtual_Files (99), which replaces the whole file with the site's own content.
		add_filter( 'robots_txt', [ __CLASS__, 'add_robots_line' ], 100, 2 );
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
		if ( ! in_array( $path, self::PATHS, true ) ) {
			return $response;
		}

		return [
			'content' => wp_json_encode( self::build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			'type'    => 'application/json',
			'headers' => [ 'Access-Control-Allow-Origin' => '*' ],
		];
	}

	public static function get_url(): string {
		return home_url( '/.well-known/ard.json' );
	}

	public static function get_legacy_url(): string {
		return home_url( '/.well-known/ai-catalog.json' );
	}

	// ── Pointers to the catalog ───────────────────────────────────────────────

	/**
	 * Link headers on every response: rel="ard" (spec) and rel="ai-catalog" (checkers).
	 */
	public static function send_link_headers(): void {
		header( 'Link: <' . esc_url_raw( self::get_url() ) . '>; rel="ard"', false );
		header( 'Link: <' . esc_url_raw( self::get_legacy_url() ) . '>; rel="ai-catalog"', false );
	}

	public static function output_head_links(): void {
		printf( '<link rel="ard" href="%s">' . "\n", esc_url( self::get_url() ) );
		printf( '<link rel="ai-catalog" href="%s">' . "\n", esc_url( self::get_legacy_url() ) );
	}

	/**
	 * Append an Agentmap line to robots.txt unless the site's own content already has one.
	 * Agentmap is not a robots.txt standard; crawlers ignore lines they don't know.
	 *
	 * @param string $output Robots.txt content.
	 * @param bool   $public Whether the site allows indexing.
	 */
	public static function add_robots_line( string $output, $public ): string {
		if ( false !== stripos( $output, 'agentmap:' ) ) {
			return $output;
		}
		return rtrim( $output ) . "\n\nAgentmap: " . self::get_legacy_url() . "\n";
	}

	// ── Document ──────────────────────────────────────────────────────────────

	/**
	 * @return array
	 */
	public static function build(): array {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$name = self::site_name();

		$catalog = [
			'specVersion' => '1.0',
			'host'        => [
				'displayName' => $name,
				'identifier'  => 'did:web:' . $host,
			],
			'entries'     => [
				[
					'identifier'            => 'urn:air:' . $host . ':api:catalog',
					'displayName'           => $name . ' API catalog',
					'type'                  => 'application/linkset+json',
					'url'                   => Api_Catalog::get_url(),
					'representativeQueries' => [
						/* translators: %s: business name */
						sprintf( __( 'what services does %s offer', 'site-essentials' ), $name ),
						/* translators: %s: business name */
						sprintf( __( 'find articles and guides from %s', 'site-essentials' ), $name ),
					],
				],
				[
					'identifier'            => 'urn:air:' . $host . ':api:content',
					'displayName'           => $name . ' content API (OpenAPI)',
					'type'                  => 'application/vnd.oai.openapi+json',
					'url'                   => OpenAPI_Spec::get_spec_url(),
					'representativeQueries' => [
						/* translators: %s: business name */
						sprintf( __( 'search %s content by keyword', 'site-essentials' ), $name ),
						/* translators: %s: business name */
						sprintf( __( 'list %s service pages and case studies', 'site-essentials' ), $name ),
					],
				],
				[
					'identifier'            => 'urn:air:' . $host . ':mcp:content-tools',
					'displayName'           => $name . ' content tools (MCP server card)',
					'type'                  => 'application/mcp-server-card+json',
					'url'                   => Mcp_Server_Card::get_url(),
					'representativeQueries' => [
						/* translators: %s: business name */
						sprintf( __( 'what tools does %s expose to agents', 'site-essentials' ), $name ),
						/* translators: %s: business name */
						sprintf( __( 'get the full text of a %s page', 'site-essentials' ), $name ),
					],
				],
			],
		];

		return apply_filters( 'scos_ard_catalog', $catalog );
	}

	private static function site_name(): string {
		$name = trim( (string) get_option( 'scos_biz_business_name', '' ) );
		return '' !== $name ? $name : (string) get_bloginfo( 'name' );
	}
}
