<?php
/**
 * Redirect Validator — checks 301 and 410 rule text before it goes live.
 *
 * One source of truth for what a valid rule is. Used by the Redirections save
 * handler (errors block the save), the Redirections tab (stored rules are
 * re-checked on load), `wp scos redirects validate` and the
 * scos/validate-redirects ability, so agents can check rules before writing them.
 *
 * Errors — the rule would never fire, or would break the page:
 *   missing_arrow, empty_side, source_is_url, source_no_slash, source_root,
 *   source_query, relative_target, self_redirect, loop
 * Warnings — the rule works, but probably not as intended:
 *   uppercase, duplicate, covers_live, overlap_410, chain, target_gone
 *
 * Paths are compared the way Redirections::handle_redirect() matches them:
 * exact and case-sensitive, trailing slash stripped.
 *
 * v1.0 | 2026-10-25
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 */

namespace SiteEssentials\Modules\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Redirect_Validator {

	/** Caches the check of the stored rules, keyed on their content. */
	const CACHE_TRANSIENT = 'se_redirects_check';

	/** Redirect hops followed before a chain is treated as a loop. */
	const MAX_HOPS = 10;

	/**
	 * Validate 301 and 410 rule text.
	 *
	 * @param string $raw_301 301 textarea content (one `/from => /to` per line).
	 * @param string $raw_410 410 textarea content (one path per line).
	 * @return array{valid: bool, errors: array, warnings: array, counts: array}
	 *         Each issue: list (301|410), line (1-based), rule, code, message.
	 */
	public static function validate( string $raw_301, string $raw_410 ): array {
		$errors   = [];
		$warnings = [];

		$add = static function ( string $level, string $list, int $line, string $rule, string $code, string $message ) use ( &$errors, &$warnings ) {
			$issue = compact( 'list', 'line', 'rule', 'code', 'message' );
			if ( 'error' === $level ) {
				$errors[] = $issue;
			} else {
				$warnings[] = $issue;
			}
		};

		// ── 410 paths (first: 301 checks need the gone list) ──
		$gone      = []; // path => line
		$lines_410 = self::lines( $raw_410 );
		foreach ( $lines_410 as $n => $line ) {
			$problem = self::source_problem( $line );
			if ( $problem ) {
				$add( 'error', '410', $n, $line, $problem[0], $problem[1] );
				continue;
			}
			$path = self::normalise( $line );
			if ( isset( $gone[ $path ] ) ) {
				/* translators: %d: line number. */
				$add( 'warning', '410', $n, $line, 'duplicate', sprintf( __( 'Same path as line %d.', 'site-essentials' ), $gone[ $path ] ) );
				continue;
			}
			$gone[ $path ] = $n;
			self::check_source_warnings( $line, $line, $path, '410', $n, $add );
		}

		// ── 301 rules ──
		$rules = []; // from => [ 'to' => string, 'line' => int, 'rule' => string ]
		foreach ( self::lines( $raw_301 ) as $n => $line ) {
			if ( ! str_contains( $line, '=>' ) ) {
				$add( 'error', '301', $n, $line, 'missing_arrow', __( 'No "=>" between the old path and the destination, so this line is ignored.', 'site-essentials' ) );
				continue;
			}
			[ $from, $to ] = array_map( 'trim', explode( '=>', $line, 2 ) );
			if ( '' === $from || '' === $to ) {
				$add( 'error', '301', $n, $line, 'empty_side', __( 'Needs an old path and a destination either side of "=>".', 'site-essentials' ) );
				continue;
			}
			$problem = self::source_problem( $from );
			if ( $problem ) {
				$add( 'error', '301', $n, $line, $problem[0], $problem[1] );
				continue;
			}
			if ( ! self::is_absolute_target( $to ) ) {
				$add( 'error', '301', $n, $line, 'relative_target', __( 'The destination must start with "/" or "https://". A relative destination is resolved against the old URL and lands on the wrong page.', 'site-essentials' ) );
				continue;
			}
			$path = self::normalise( $from );
			if ( self::internal_path( $to ) === $path ) {
				$add( 'error', '301', $n, $line, 'self_redirect', __( 'Redirects to itself: visitors get a redirect loop.', 'site-essentials' ) );
				continue;
			}
			if ( isset( $rules[ $path ] ) ) {
				/* translators: %d: line number. */
				$add( 'warning', '301', $n, $line, 'duplicate', sprintf( __( 'Same old path as line %d. This line wins.', 'site-essentials' ), $rules[ $path ]['line'] ) );
			} else {
				self::check_source_warnings( $line, $from, $path, '301', $n, $add );
			}
			if ( isset( $gone[ $path ] ) ) {
				/* translators: %d: line number in the 410 list. */
				$add( 'warning', '301', $n, $line, 'overlap_410', sprintf( __( 'This path is also in the 410 list (line %d). The 410 wins, so this redirect never fires.', 'site-essentials' ), $gone[ $path ] ) );
			}
			$rules[ $path ] = [ 'to' => $to, 'line' => $n, 'rule' => $line ];
		}

		// ── Destinations: loops, chains, gone targets (needs the full rule map) ──
		foreach ( $rules as $path => $rule ) {
			$target = self::internal_path( $rule['to'] );
			if ( null === $target ) {
				continue; // External destination.
			}
			if ( isset( $gone[ $target ] ) ) {
				$add( 'warning', '301', $rule['line'], $rule['rule'], 'target_gone', __( 'The destination is in the 410 list, so visitors are redirected to a "Gone" page.', 'site-essentials' ) );
				continue;
			}
			if ( ! isset( $rules[ $target ] ) ) {
				continue;
			}
			$hops = self::follow( $path, $rules );
			if ( null === $hops ) {
				$add( 'error', '301', $rule['line'], $rule['rule'], 'loop', __( 'Redirect loop: following the rules from here never reaches a final page.', 'site-essentials' ) );
			} else {
				/* translators: %d: number of redirects. */
				$add( 'warning', '301', $rule['line'], $rule['rule'], 'chain', sprintf( __( 'The destination redirects again (%d redirects in a row). Point this rule at the final URL.', 'site-essentials' ), $hops ) );
			}
		}

		usort( $errors, [ __CLASS__, 'by_position' ] );
		usort( $warnings, [ __CLASS__, 'by_position' ] );

		return [
			'valid'    => empty( $errors ),
			'errors'   => $errors,
			'warnings' => $warnings,
			'counts'   => [
				'rules_301' => count( $rules ),
				'paths_410' => count( $gone ),
				'errors'    => count( $errors ),
				'warnings'  => count( $warnings ),
			],
		];
	}

	/**
	 * Validate the rules currently saved, cached until they change.
	 *
	 * @return array Same shape as validate().
	 */
	public static function check_stored(): array {
		$raw_301 = Redirections::get_301_raw();
		$raw_410 = Redirections::get_410_raw();
		$hash    = md5( $raw_301 . "\0" . $raw_410 );

		$cached = get_transient( self::CACHE_TRANSIENT );
		if ( is_array( $cached ) && ( $cached['hash'] ?? '' ) === $hash ) {
			return $cached['report'];
		}

		$report = self::validate( $raw_301, $raw_410 );
		// Published posts can change under the rules, so don't cache for long.
		set_transient( self::CACHE_TRANSIENT, [ 'hash' => $hash, 'report' => $report ], HOUR_IN_SECONDS );
		return $report;
	}

	/**
	 * One issue as a single line of text, for notices and WP-CLI.
	 *
	 * @param array $issue An entry from validate()['errors'] or ['warnings'].
	 * @return string
	 */
	public static function format_issue( array $issue ): string {
		/* translators: 1: 301 or 410, 2: line number, 3: the rule, 4: the problem. */
		return sprintf( __( '%1$s line %2$d: %3$s — %4$s', 'site-essentials' ), $issue['list'], $issue['line'], $issue['rule'], $issue['message'] );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Non-empty, non-comment lines keyed by 1-based line number.
	 *
	 * @param string $raw
	 * @return array<int, string>
	 */
	private static function lines( string $raw ): array {
		$out = [];
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $i => $line ) {
			$line = trim( $line );
			if ( '' !== $line && ! str_starts_with( $line, '#' ) ) {
				$out[ $i + 1 ] = $line;
			}
		}
		return $out;
	}

	/**
	 * Why a source path can never match a request, or null when it can.
	 *
	 * @param string $source
	 * @return array{0: string, 1: string}|null [ code, message ]
	 */
	private static function source_problem( string $source ): ?array {
		if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $source ) || str_starts_with( $source, '//' ) ) {
			return [ 'source_is_url', __( 'Use the path only (e.g. /old-page), not the full URL. Requests are matched on their path.', 'site-essentials' ) ];
		}
		if ( ! str_starts_with( $source, '/' ) ) {
			return [ 'source_no_slash', __( 'The path must start with "/", otherwise it never matches.', 'site-essentials' ) ];
		}
		if ( '' === self::normalise( $source ) ) {
			return [ 'source_root', __( 'The homepage can\'t be redirected or removed here.', 'site-essentials' ) ];
		}
		if ( strpbrk( $source, '?#' ) ) {
			return [ 'source_query', __( 'Remove the "?" or "#" part. Requests are matched on their path only, so this never matches.', 'site-essentials' ) ];
		}
		return null;
	}

	/**
	 * Warnings that apply to any valid source path (301 or 410).
	 *
	 * @param string   $rule   The line, as shown in the report.
	 * @param string   $source Source path as written.
	 * @param string   $path   Normalised source.
	 * @param string   $list   301 or 410.
	 * @param int      $n      Line number.
	 * @param callable $add    Issue collector.
	 */
	private static function check_source_warnings( string $rule, string $source, string $path, string $list, int $n, callable $add ): void {
		if ( preg_match( '/[A-Z]/', $source ) ) {
			$add( 'warning', $list, $n, $rule, 'uppercase', __( 'Contains capital letters. Matching is case-sensitive, so a request in lower case won\'t match. Use the exact case the old URL had.', 'site-essentials' ) );
		}
		$post_id = url_to_postid( home_url( $path . '/' ) );
		if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
			$message = '410' === $list
				/* translators: %s: post title. */
				? __( 'This is the URL of a published page ("%s"). It will return 410 Gone instead of the page.', 'site-essentials' )
				/* translators: %s: post title. */
				: __( 'This is the URL of a published page ("%s"). The redirect hides it, so visitors can no longer reach it.', 'site-essentials' );
			$add( 'warning', $list, $n, $rule, 'covers_live', sprintf( $message, get_the_title( $post_id ) ) );
		}
	}

	/** Strip the trailing slash, as the parser and matcher do. */
	private static function normalise( string $path ): string {
		return rtrim( $path, '/' );
	}

	/** True for "/path" and "http(s)://…"; false for anything resolved relative to the request. */
	private static function is_absolute_target( string $to ): bool {
		return ( str_starts_with( $to, '/' ) && ! str_starts_with( $to, '//' ) )
			|| (bool) preg_match( '#^https?://[^/\s]+#i', $to );
	}

	/**
	 * Normalised path of a destination on this site, or null for an external one.
	 *
	 * @param string $to
	 * @return string|null
	 */
	private static function internal_path( string $to ): ?string {
		if ( str_starts_with( $to, '/' ) && ! str_starts_with( $to, '//' ) ) {
			return self::normalise( (string) wp_parse_url( $to, PHP_URL_PATH ) );
		}
		$host = strtolower( (string) wp_parse_url( $to, PHP_URL_HOST ) );
		$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( '' === $host || preg_replace( '/^www\./', '', $host ) !== preg_replace( '/^www\./', '', $home ) ) {
			return null;
		}
		return self::normalise( (string) wp_parse_url( $to, PHP_URL_PATH ) );
	}

	/**
	 * Follow internal redirects from a path.
	 *
	 * @param string $path  Starting source path.
	 * @param array  $rules Normalised rule map.
	 * @return int|null Number of redirects before a final URL; null for a loop.
	 */
	private static function follow( string $path, array $rules ): ?int {
		$seen = [ $path => true ];
		$hops = 0;
		while ( isset( $rules[ $path ] ) ) {
			$next = self::internal_path( $rules[ $path ]['to'] );
			++$hops;
			if ( null === $next ) {
				return $hops;
			}
			if ( isset( $seen[ $next ] ) || $hops >= self::MAX_HOPS ) {
				return null;
			}
			$seen[ $next ] = true;
			$path          = $next;
		}
		return $hops;
	}

	private static function by_position( array $a, array $b ): int {
		return [ $a['list'], $a['line'] ] <=> [ $b['list'], $b['line'] ];
	}
}
