<?php
/**
 * Agentic — Markdown cache rule (.htaccess)
 *
 * v1.0 | 2026-10-21
 *
 * LiteSpeed serves a cached page before PHP runs, so a request with
 * Accept: text/markdown for a cached URL gets the cached HTML and
 * Markdown_Renderer never sees it. Markdown_Renderer::bypass_page_cache()
 * only stops a markdown response from being stored; it cannot stop a stored
 * HTML copy from being served.
 *
 * This class keeps a marker block in .htaccess that tells LiteSpeed to skip
 * the cache when the Accept header asks for markdown:
 *
 *   # BEGIN SCOS Markdown
 *   <IfModule LiteSpeed>
 *   RewriteEngine On
 *   RewriteCond %{HTTP_ACCEPT} text/markdown [NC]
 *   RewriteRule .* - [E=Cache-Control:no-cache]
 *   </IfModule>
 *   # END SCOS Markdown
 *
 * The block is written above "# BEGIN WordPress", whose final RewriteRule
 * ends rewrite processing with [L]. Anything appended below it never runs,
 * which is why insert_with_markers() (append-only for new markers) is not used.
 *
 * Kept in step with scos_agentic_markdown_enabled:
 *   - on the option being added or updated (the settings page save);
 *   - on admin_init when RULE_VERSION differs from the synced version, so a
 *     deploy to a site that already had the setting on picks the rule up;
 *   - by `wp scos markdown-cache-rule sync`.
 *
 * `wp scos markdown-cache-rule status` exits non-zero when the setting is on
 * and the block is missing. The server-level fleet check uses it.
 *
 * Disabling the Agentic module leaves the block in place. That is harmless:
 * markdown-only requests then reach WordPress uncached and get HTML.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Markdown_Cache_Rule {

	const MARKER = 'SCOS Markdown';

	/** Bump when the block's lines change, so admin_init rewrites it. */
	const RULE_VERSION = '1';

	/** Option recording which RULE_VERSION was last synced (or 'off'). */
	const SYNCED_OPTION = 'scos_agentic_markdown_rule_synced';

	// ── Boot ─────────────────────────────────────────────────────────────────

	public static function init(): void {
		add_action( 'add_option_scos_agentic_markdown_enabled', [ __CLASS__, 'sync' ] );
		add_action( 'update_option_scos_agentic_markdown_enabled', [ __CLASS__, 'sync' ] );
		add_action( 'admin_init', [ __CLASS__, 'maybe_sync' ] );
	}

	/**
	 * Re-sync once per rule version / setting change. One autoloaded option
	 * read per admin request; the file is only touched when they differ.
	 */
	public static function maybe_sync(): void {
		if ( get_option( self::SYNCED_OPTION ) !== self::expected_state() ) {
			self::sync();
		}
	}

	// ── State ─────────────────────────────────────────────────────────────────

	private static function wants_rule(): bool {
		return (bool) get_option( 'scos_agentic_markdown_enabled', false );
	}

	private static function expected_state(): string {
		return self::wants_rule() ? self::RULE_VERSION : 'off';
	}

	public static function get_path(): string {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		return get_home_path() . '.htaccess';
	}

	/** @return string[] */
	private static function block_lines(): array {
		return [
			'# BEGIN ' . self::MARKER,
			'# Agents asking for Markdown skip the page cache, so a cached HTML copy',
			'# is never served in place of the Markdown response. Managed by SCOS.',
			'<IfModule LiteSpeed>',
			'RewriteEngine On',
			'RewriteCond %{HTTP_ACCEPT} text/markdown [NC]',
			'RewriteRule .* - [E=Cache-Control:no-cache]',
			'</IfModule>',
			'# END ' . self::MARKER,
		];
	}

	/**
	 * Current state, for the settings page, WP-CLI and the fleet check.
	 *
	 * @return array{enabled: bool, present: bool, current: bool, writable: bool, ok: bool, path: string}
	 */
	public static function status(): array {
		$path     = self::get_path();
		$contents = is_readable( $path ) ? (string) file_get_contents( $path ) : '';
		$block    = implode( "\n", self::block_lines() );
		$enabled  = self::wants_rule();
		$present  = false !== strpos( $contents, '# BEGIN ' . self::MARKER );
		$current  = false !== strpos( str_replace( "\r\n", "\n", $contents ), $block );

		return [
			'enabled'  => $enabled,
			'present'  => $present,
			'current'  => $current,
			'writable' => file_exists( $path ) ? is_writable( $path ) : is_writable( dirname( $path ) ),
			'ok'       => $enabled ? $current : ! $present,
			'path'     => $path,
		];
	}

	// ── Write ─────────────────────────────────────────────────────────────────

	/**
	 * Add, refresh or remove the block to match the setting.
	 *
	 * @return true|\WP_Error
	 */
	public static function sync() {
		$path     = self::get_path();
		$exists   = file_exists( $path );
		$contents = $exists ? (string) file_get_contents( $path ) : '';
		$updated  = self::strip_block( $contents );

		if ( self::wants_rule() ) {
			$updated = self::insert_block( $updated );
		}

		if ( $updated !== $contents ) {
			if ( $exists ? ! is_writable( $path ) : ! is_writable( dirname( $path ) ) ) {
				error_log( '[SCOS Markdown_Cache_Rule] .htaccess not writable: ' . $path );
				return new \WP_Error( 'scos_htaccess_not_writable', '.htaccess is not writable.' );
			}
			if ( false === file_put_contents( $path, $updated, LOCK_EX ) ) {
				error_log( '[SCOS Markdown_Cache_Rule] .htaccess write failed: ' . $path );
				return new \WP_Error( 'scos_htaccess_write_failed', '.htaccess could not be written.' );
			}
		}

		update_option( self::SYNCED_OPTION, self::expected_state(), true );
		return true;
	}

	/** Remove our block (and the blank line after it) wherever it sits. */
	private static function strip_block( string $contents ): string {
		$pattern = '/^# BEGIN ' . preg_quote( self::MARKER, '/' ) . '\R.*?^# END ' . preg_quote( self::MARKER, '/' ) . '\R?(\R)?/ms';
		return (string) preg_replace( $pattern, '', $contents );
	}

	/** Insert above "# BEGIN WordPress", or at the top when that block is absent. */
	private static function insert_block( string $contents ): string {
		$eol   = false !== strpos( $contents, "\r\n" ) ? "\r\n" : "\n";
		$block = implode( $eol, self::block_lines() ) . $eol . $eol;
		$pos   = strpos( $contents, '# BEGIN WordPress' );

		if ( false === $pos ) {
			return $block . $contents;
		}
		return substr( $contents, 0, $pos ) . $block . substr( $contents, $pos );
	}
}
