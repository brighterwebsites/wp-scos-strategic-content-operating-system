<?php
/**
 * Agentic — /.well-known/ Router
 *
 * v1.2 | 2026-10-21
 *
 * v1.1 | 2026-10-21 — Unregistered paths now return a hard 404 instead of
 *                     falling through to WP (which served the blog index, 200).
 * v1.2 | 2026-10-21 — Optional 'headers' in a response (e.g. CORS for ARD).
 * WHAT THIS IS FOR
 * ----------------
 * RFC 8615 reserves /.well-known/ as the standard location for site-wide
 * metadata files. Rather than adding a separate rewrite rule per file
 * (the pattern Virtual_Files.php uses for llms.txt), this registers ONE
 * catch-all rule and dispatches on the captured path.
 *
 * Consumers register via the scos_well_known_response filter. Each hook
 * inspects the requested path, and either returns a response array or
 * passes through so the next handler gets a look:
 *
 *   add_filter( 'scos_well_known_response', function ( $response, $path ) {
 *       if ( 'my-file.json' !== $path ) {
 *           return $response;          // not mine — pass through
 *       }
 *       return [
 *           'content' => wp_json_encode( [ 'hello' => 'world' ] ),
 *           'type'    => 'application/json',
 *       ];
 *   }, 10, 2 );
 *
 * A filter (rather than an exact-match handler array) is used deliberately
 * so registrants can pattern-match. Agent Skills needs this — it serves
 * /.well-known/agent-skills/{slug}/SKILL.md at arbitrary slugs.
 *
 * $path excludes the /.well-known/ prefix and any leading slash, so
 * /.well-known/mcp/server-card.json arrives as "mcp/server-card.json".
 *
 * GOTCHAS WORTH KNOWING
 * ---------------------
 * 1. Leading-dot directories. Some server configs block dotfiles outright.
 *    /.well-known/ is normally exempted (ACME/Let's Encrypt depends on it),
 *    but if these 404 while /wp-json/ routes work, suspect the server config
 *    rather than this code.
 *
 * 2. ACME challenge collision. Certbot writes real files to
 *    /.well-known/acme-challenge/. Those are served from disk by the web
 *    server before WordPress ever boots, so there is no conflict — but never
 *    register a handler under that path.
 *
 * 3. Rewrite flushing. The rule only takes effect after a flush. That is
 *    handled once per version bump via the REWRITE_VER option, matching the
 *    llms.txt approach in Virtual_Files.php. Bump REWRITE_VER when the rule
 *    itself changes.
 *
 * 4. Priority 0 on template_redirect. Runs before Markdown_Renderer (which
 *    uses priority 1) so an Accept: text/markdown header cannot hijack a
 *    .well-known JSON response.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Well_Known_Router {

	const QV          = 'scos_well_known';
	const REWRITE_VER = 'scos_well_known_rewrite_ver';

	/** Bump when the rewrite rule changes, to force a one-time flush. */
	const RULE_VERSION = '1';

	// ── Boot ─────────────────────────────────────────────────────────────────

	public static function init(): void {
		add_rewrite_rule(
			'^\.well-known/(.+)$',
			'index.php?' . self::QV . '=$matches[1]',
			'top'
		);

		add_filter( 'query_vars',        [ __CLASS__, 'add_query_var' ] );
		add_action( 'template_redirect', [ __CLASS__, 'serve' ], 0 );

		self::maybe_flush_rewrites();
	}

	public static function add_query_var( array $vars ): array {
		$vars[] = self::QV;
		return $vars;
	}

	/**
	 * Flush rewrite rules once per RULE_VERSION.
	 *
	 * Flushing is expensive, so it must never run on every request.
	 */
	private static function maybe_flush_rewrites(): void {
		if ( get_option( self::REWRITE_VER ) === self::RULE_VERSION ) {
			return;
		}

		add_action( 'init', static function () {
			flush_rewrite_rules( false );
			update_option( self::REWRITE_VER, self::RULE_VERSION, false );
		}, 99 );
	}

	// ── Dispatch ──────────────────────────────────────────────────────────────

	/**
	 * Resolve the requested .well-known path and emit the response.
	 *
	 * An unregistered path gets a hard 404 from send_not_found(), queued last
	 * on template_redirect so another plugin can still serve it first.
	 */
	public static function serve(): void {
		$path = (string) get_query_var( self::QV );

		if ( '' === $path ) {
			return;
		}

		// Strip any trailing slash so "api-catalog/" and "api-catalog" both resolve.
		$path = rtrim( $path, '/' );

		/**
		 * Filter: scos_well_known_response
		 *
		 * @param array|null $response ['content' => string, 'type' => string,
		 *                             'headers' => [name => value] (optional)] or null.
		 * @param string     $path     Requested path minus the /.well-known/ prefix.
		 */
		$response = apply_filters( 'scos_well_known_response', null, $path );

		if ( ! is_array( $response ) || ! isset( $response['content'] ) ) {
			// Not ours. Stop the trailing-slash 301, give other plugins'
			// template_redirect handlers their turn, then 404 if none answered.
			remove_action( 'template_redirect', 'redirect_canonical' );
			add_action( 'template_redirect', [ __CLASS__, 'send_not_found' ], PHP_INT_MAX );
			return;
		}

		$type = $response['type'] ?? 'text/plain';

		self::bypass_page_cache();

		status_header( 200 );
		header( 'Content-Type: ' . $type . '; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );

		// These files are small, static, and safe to cache at the edge.
		header( 'Cache-Control: public, max-age=3600' );

		foreach ( (array) ( $response['headers'] ?? [] ) as $name => $value ) {
			header( $name . ': ' . $value );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-encoded JSON/markdown payload
		echo $response['content'];
		exit;
	}

	/**
	 * Hard 404 for an unregistered .well-known path, then exit.
	 *
	 * Handing back to WordPress does NOT produce a 404: the only query var set
	 * is ours, so WP treats the request as the posts page, redirect_canonical
	 * adds a trailing slash (301) and the blog index is served with a 200.
	 * Validators that probe for optional manifests (e.g. ai-catalog.json) then
	 * parse that HTML and report "malformed JSON" instead of "not present".
	 */
	public static function send_not_found(): void {
		self::bypass_page_cache();

		status_header( 404 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Cache-Control: no-store' );

		echo 'Not Found';
		exit;
	}

	// ── Cache bypass ──────────────────────────────────────────────────────────

	/**
	 * Keep these responses out of the LiteSpeed page cache.
	 *
	 * The page cache keys on URL alone and would happily store a JSON body
	 * against a URL a browser later requests as HTML. Mirrors the pattern in
	 * Markdown_Renderer::bypass_page_cache().
	 *
	 * Note this only bypasses the *page* cache — the Cache-Control header above
	 * still allows normal HTTP/CDN caching, which is what we want.
	 */
	private static function bypass_page_cache(): void {
		if ( function_exists( 'litespeed_control' ) ) {
			litespeed_control( 'no-cache' );
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( class_exists( '\LiteSpeed\API' ) && method_exists( '\LiteSpeed\API', 'set_nocache' ) ) {
			\LiteSpeed\API::set_nocache( 'well-known' );
		}
	}
}
