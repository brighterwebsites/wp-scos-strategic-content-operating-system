<?php
/**
 * AI Knowledge — logged-in viewer for knowledge files and agency reports.
 *
 * wp-content/ai-knowledge/ holds client strategy, brand voice and agency
 * reports, and is blocked over HTTP (#249). Admin screens and the onboarding
 * email still need to open those files, so they link here instead:
 *
 *   admin-post.php?action=scos_view_knowledge&file=202-authority-content-strategy.md
 *   admin-post.php?action=scos_view_knowledge&file=agency-reports/agency-reports-index.html
 *   admin-post.php?action=scos_view_knowledge&file=agency-reports/   (file list)
 *
 * Access: logged in with `edit_pages`, so client editors and shop managers can
 * open their reports and subscribers or customers can't (decided 2026-11-27).
 * A logged-out visitor is sent to the login page and back to the file.
 *
 * It's a read-only GET with no side effects, so there is no nonce: the email
 * link has to work in a fresh session. The capability check is the gate.
 *
 * Every response is `private, no-store` and `noindex`, so neither LiteSpeed
 * Cache nor Cloudflare keeps a copy. HTML reports are served under a CSP
 * sandbox: their scripts run, but in an opaque origin, with no access to the
 * viewer's cookies or wp-admin.
 *
 * v1.0 | 2026-11-27
 *
 * @package    SiteEssentials
 * @subpackage Core
 */

declare( strict_types=1 );

namespace SiteEssentials\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ai_Knowledge_Viewer {

	/** admin-post.php action. */
	const ACTION = 'scos_view_knowledge';

	/** Capability needed to open any file. */
	const CAP = 'edit_pages';

	/** The one subfolder that can be opened, and what it may serve. */
	const REPORTS_DIR = 'agency-reports';

	public static function init(): void {
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION, [ self::class, 'handle' ] );
		// After brighter-core's login_redirect (100), which replaces redirect_to with the
		// role's landing page: someone who logged in to open a report should get the report.
		add_filter( 'login_redirect', [ self::class, 'keep_viewer_redirect' ], 101, 3 );
	}

	/**
	 * Viewer URL for a knowledge file, e.g. `202-authority-content-strategy.md`
	 * or `agency-reports/agency-reports-index.html`. Not escaped.
	 */
	public static function url( string $file ): string {
		return add_query_arg(
			[
				'action' => self::ACTION,
				'file'   => rawurlencode( $file ),
			],
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Map a requested name to a file on disk.
	 *
	 * Accepts a bare `.md` name in ai-knowledge/ (through Ai_Knowledge::path()),
	 * a bare `.md`/`.html` name in agency-reports/, or the agency-reports folder
	 * itself. Nothing deeper, no `..`, no absolute paths.
	 *
	 * @return array{path:string,type:string}|null type is md, html or dir.
	 */
	public static function resolve( string $file ): ?array {
		$file = trim( str_replace( '\\', '/', $file ) );
		$base = trailingslashit( WP_CONTENT_DIR ) . Ai_Knowledge::DIR;

		if ( self::REPORTS_DIR === rtrim( $file, '/' ) ) {
			$path = $base . '/' . self::REPORTS_DIR;
			return [ 'path' => $path, 'type' => 'dir' ];
		}

		if ( 0 === strpos( $file, self::REPORTS_DIR . '/' ) ) {
			$name = substr( $file, strlen( self::REPORTS_DIR ) + 1 );
			if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]*\.(md|html?)$/', $name, $m ) ) {
				return null;
			}
			$path = $base . '/' . self::REPORTS_DIR . '/' . $name;
			$type = 'md' === strtolower( $m[1] ) ? 'md' : 'html';
		} else {
			$path = Ai_Knowledge::path( $file );
			$type = 'md';
		}

		if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		// Belt and braces against symlinks pointing out of the folder.
		$real = realpath( $path );
		$root = realpath( $base );
		if ( false === $real || false === $root || 0 !== strpos( $real, $root . DIRECTORY_SEPARATOR ) ) {
			return null;
		}

		return [ 'path' => $real, 'type' => $type ];
	}

	/**
	 * admin-post handler, for logged-in and logged-out requests.
	 */
	public static function handle(): void {
		$file = isset( $_GET['file'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['file'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, capability-gated.

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::url( $file ) ) );
			exit;
		}

		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You don\'t have access to this file.', 'site-essentials' ), '', [ 'response' => 403 ] );
		}

		$found = self::resolve( $file );
		if ( null === $found ) {
			wp_die( esc_html__( 'That file doesn\'t exist.', 'site-essentials' ), '', [ 'response' => 404 ] );
		}

		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: same-origin' );

		switch ( $found['type'] ) {
			case 'dir':
				header( 'Content-Type: text/html; charset=utf-8' );
				echo self::render_listing( $found['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				break;

			case 'html':
				header( 'Content-Type: text/html; charset=utf-8' );
				header( 'Content-Security-Policy: sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox allow-downloads' );
				readfile( $found['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				break;

			default:
				header( 'Content-Type: text/plain; charset=utf-8' );
				echo Ai_Knowledge::read_path( $found['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- served as text/plain.
		}
		exit;
	}

	/**
	 * Keep a requested viewer URL through brighter-core's role-based login redirect.
	 *
	 * @param string            $redirect_to Where the login will go.
	 * @param string            $requested   The redirect_to the login form was given.
	 * @param \WP_User|\WP_Error $user        The user logging in.
	 */
	public static function keep_viewer_redirect( $redirect_to, $requested, $user ) {
		if ( ! $user instanceof \WP_User || ! is_string( $requested ) || '' === $requested ) {
			return $redirect_to;
		}
		$viewer = admin_url( 'admin-post.php' );
		if ( 0 !== strpos( $requested, $viewer ) || false === strpos( $requested, 'action=' . self::ACTION ) ) {
			return $redirect_to;
		}
		return user_can( $user, self::CAP ) ? $requested : $redirect_to;
	}

	/**
	 * File list for agency-reports/.
	 */
	private static function render_listing( string $dir ): string {
		$items = [];
		if ( is_dir( $dir ) ) {
			foreach ( (array) scandir( $dir ) as $name ) {
				if ( is_string( $name ) && preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]*\.(md|html?)$/', $name ) ) {
					$items[ $name ] = (int) filemtime( $dir . '/' . $name );
				}
			}
		}
		arsort( $items );

		$title = __( 'Your reports', 'site-essentials' );
		$html  = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<meta name="robots" content="noindex,nofollow"><title>' . esc_html( $title . ' - ' . get_bloginfo( 'name' ) ) . '</title>'
			. '<style>body{font:16px/1.6 system-ui,sans-serif;max-width:44rem;margin:3rem auto;padding:0 1rem;color:#1f2937}a{color:#4f46e5}li{margin:.4rem 0}small{color:#6b7280}</style>'
			. '</head><body><h1>' . esc_html( $title ) . '</h1>';

		if ( ! $items ) {
			$html .= '<p>' . esc_html__( 'No reports yet. They will appear here once they\'re ready.', 'site-essentials' ) . '</p>';
		} else {
			$html .= '<ul>';
			foreach ( $items as $name => $mtime ) {
				$html .= '<li><a href="' . esc_url( self::url( self::REPORTS_DIR . '/' . $name ) ) . '">' . esc_html( $name ) . '</a> '
					. '<small>' . esc_html( wp_date( get_option( 'date_format' ), $mtime ) ) . '</small></li>';
			}
			$html .= '</ul>';
		}

		return $html . '</body></html>';
	}
}
