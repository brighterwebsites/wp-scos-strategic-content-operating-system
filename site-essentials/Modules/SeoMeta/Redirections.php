<?php
/**
 * Redirections — 301 and 410 rule management.
 *
 * Rules are stored as plain text in two wp_options keys; no custom DB tables.
 *
 * 301 format (one rule per line):
 *   /old-path => /new-path
 *   /old-path => https://external.com/destination
 *   # Lines starting with # are comments
 *
 * 410 format (one path per line):
 *   /deleted-page
 *   /removed-resource
 *   # Lines starting with # are comments
 *
 * Matching is exact, trailing-slash normalised. Query strings on incoming
 * requests are passed through to the 301 destination automatically.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Redirections {

	const OPTION_301 = 'scos_redirects_301';
	const OPTION_410 = 'scos_redirects_410';

	/** When true, WordPress will not guess a redirect URL for 404 requests (see Redirections tab). */
	const OPTION_DISABLE_404_GUESS = 'scos_disable_404_redirect_guess';

	/**
	 * off | guard | protect — Breakdance "Use default editor" handling (see Redirections tab).
	 * Aligns with module-development.md scos_bd_protect / guard concept.
	 */
	public const OPTION_BREAKDANCE_GUARD = 'scos_breakdance_editor_guard';

	// ── Bootstrap ─────────────────────────────────────────────────────────────

	public static function init(): void {
		// Only run redirect logic on the frontend.
		if ( ! is_admin() ) {
			add_action( 'template_redirect', [ __CLASS__, 'handle_redirect' ], 1 );
		}
	}

	/**
	 * Front-end filters that must work whenever options are set (not gated on SEO module).
	 * Called from site-essentials.php on init.
	 *
	 * @return void
	 */
	public static function register_misc_http_filters(): void {
		if ( ! get_option( self::OPTION_DISABLE_404_GUESS, false ) ) {
			return;
		}
		// WP 5.5+ — canonical.php bails before DB guess when this returns false.
		add_filter( 'do_redirect_guess_404_permalink', '__return_false', 999 );
	}

	// ── Redirect handler ──────────────────────────────────────────────────────

	public static function handle_redirect(): void {
		$request_uri  = $_SERVER['REQUEST_URI'] ?? '';
		$path         = rtrim( (string) parse_url( $request_uri, PHP_URL_PATH ), '/' );
		$query_string = $_SERVER['QUERY_STRING'] ?? '';

		// ── 1. 410 Gone (checked first — no destination needed) ──
		$gone_paths = self::get_410_paths();
		if ( in_array( $path, $gone_paths, true ) ) {
			status_header( 410 );
			nocache_headers();
			echo '<!DOCTYPE html><html><head><title>410 Gone</title></head>'
			   . '<body><h1>Gone</h1><p>This resource has been permanently removed.</p></body></html>';
			exit;
		}

		// ── 2. 301 Permanent Redirects ──
		$rules  = self::parse_301_rules( self::get_301_raw() );
		$target = self::match_path( $path, $rules );

		if ( $target ) {
			// Append original query string to destination.
			if ( ! empty( $query_string ) ) {
				$sep     = str_contains( $target, '?' ) ? '&' : '?';
				$target .= $sep . $query_string;
			}
			wp_redirect( $target, 301 );
			exit;
		}
	}

	// ── Rule parsing ──────────────────────────────────────────────────────────

	/**
	 * Parse a 301 rules textarea into [ '/old-path' => 'destination' ] map.
	 *
	 * @param  string $raw Raw textarea content.
	 * @return array<string, string>
	 */
	public static function parse_301_rules( string $raw ): array {
		$rules = [];

		foreach ( explode( "\n", $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}
			if ( ! str_contains( $line, '=>' ) ) {
				continue;
			}

			[ $from, $to ] = explode( '=>', $line, 2 );
			$from = rtrim( trim( $from ), '/' ); // normalise: strip trailing slash
			$to   = trim( $to );

			if ( '' !== $from && '' !== $to ) {
				$rules[ $from ] = $to;
			}
		}

		return $rules;
	}

	/**
	 * Parse a 410 paths textarea into a list of normalised paths.
	 *
	 * @param  string $raw Raw textarea content.
	 * @return string[]
	 */
	public static function parse_410_paths( string $raw ): array {
		$paths = [];

		foreach ( explode( "\n", $raw ) as $line ) {
			$line = rtrim( trim( $line ), '/' );
			if ( '' !== $line && ! str_starts_with( $line, '#' ) ) {
				$paths[] = $line;
			}
		}

		return array_values( array_unique( $paths ) );
	}

	// ── Getters ───────────────────────────────────────────────────────────────

	public static function get_301_raw(): string {
		return (string) get_option( self::OPTION_301, '' );
	}

	public static function get_410_raw(): string {
		return (string) get_option( self::OPTION_410, '' );
	}

	public static function get_410_paths(): array {
		return self::parse_410_paths( self::get_410_raw() );
	}

	// ── Helper ────────────────────────────────────────────────────────────────

	/**
	 * Match a normalised path against a rules map.
	 * Tries the exact path, then the path with a trailing slash.
	 *
	 * @param  string               $path  Request path, trailing slash stripped.
	 * @param  array<string,string> $rules Parsed 301 rules map.
	 * @return string|null Destination or null if no match.
	 */
	private static function match_path( string $path, array $rules ): ?string {
		if ( isset( $rules[ $path ] ) ) {
			return $rules[ $path ];
		}
		// Also match rules written with a trailing slash in the source.
		if ( isset( $rules[ $path . '/' ] ) ) {
			return $rules[ $path . '/' ];
		}
		return null;
	}

	// ── Admin save handler ────────────────────────────────────────────────────

	public static function handle_save(): void {
		if ( ! isset( $_POST['scos_redirections_nonce'] ) ||
		     ! wp_verify_nonce(
		         sanitize_text_field( wp_unslash( $_POST['scos_redirections_nonce'] ) ),
		         'scos_save_redirections'
		     ) ) {
			wp_die( esc_html__( 'Security check failed.', 'site-essentials' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'site-essentials' ) );
		}

		$raw_301 = isset( $_POST['scos_301_rules'] )
			? sanitize_textarea_field( wp_unslash( $_POST['scos_301_rules'] ) )
			: '';
		$raw_410 = isset( $_POST['scos_410_rules'] )
			? sanitize_textarea_field( wp_unslash( $_POST['scos_410_rules'] ) )
			: '';

		update_option( self::OPTION_301, $raw_301, false );
		update_option( self::OPTION_410, $raw_410, false );

		$disable_guess = ! empty( $_POST['scos_disable_404_redirect_guess'] );
		update_option( self::OPTION_DISABLE_404_GUESS, $disable_guess, false );

		$bd_guard = isset( $_POST['scos_breakdance_editor_guard'] )
			? sanitize_key( wp_unslash( $_POST['scos_breakdance_editor_guard'] ) )
			: 'off';
		if ( ! in_array( $bd_guard, [ 'off', 'guard', 'protect' ], true ) ) {
			$bd_guard = 'off';
		}
		update_option( self::OPTION_BREAKDANCE_GUARD, $bd_guard, false );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => \SiteEssentials\Core\Admin_UI::SEO_PAGE_SLUG,
					'tab'     => 'redirections',
					'updated' => 'true',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
