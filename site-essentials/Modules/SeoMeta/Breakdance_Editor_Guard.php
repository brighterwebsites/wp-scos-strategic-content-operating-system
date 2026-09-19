<?php
/**
 * Breakdance editor launcher guard — reduce accidental "Use default editor" usage.
 *
 * When _breakdance_data exists, optionally hide or de-emphasize the
 * "Use default editor" control on post.php. Role-based restrictions are out of scope (phase 2).
 *
 * The launcher lives in two places:
 *  - Classic editor: markup in the admin page itself.
 *  - Block editor: the breakdance/block-breakdance-launcher block, rendered inside
 *    the editor-canvas iframe. Styles only reach the iframe when enqueued on
 *    enqueue_block_assets, so the CSS is added there as well.
 *
 * @package SiteEssentials
 * v1.1 | 2026-09-15 — Also style the launcher inside the block editor's iframe (the
 *                      admin-page CSS never reached it, so Guard/Protect did nothing
 *                      on WP 7.1 + Breakdance 3.0); selectors outrank Breakdance's own
 *                      !important launcher styles.
 */

namespace SiteEssentials\Modules\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Breakdance_Editor_Guard {

	const HANDLE = 'scos-breakdance-editor-guard';

	/**
	 * @return void
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}
		// Classic editor — the launcher is part of the admin page.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ], 99 );
		// Block editor — the launcher block renders inside the editor-canvas iframe.
		add_action( 'enqueue_block_assets', [ __CLASS__, 'enqueue_in_editor_canvas' ] );
	}

	/**
	 * @param string $hook_suffix Current admin screen.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		self::add_css();
	}

	/**
	 * enqueue_block_assets callback. WordPress collects what this enqueues into
	 * the iframed editor canvas (it also fires on the front end and in the site
	 * editor, hence the screen check).
	 *
	 * @return void
	 */
	public static function enqueue_in_editor_canvas(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}
		self::add_css();
	}

	/**
	 * @return void
	 */
	private static function add_css(): void {
		$mode = (string) get_option( Redirections::OPTION_BREAKDANCE_GUARD, 'off' );
		if ( ! in_array( $mode, [ 'guard', 'protect' ], true ) ) {
			return;
		}

		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( ! self::post_has_breakdance_data( (int) $post->ID ) ) {
			return;
		}

		if ( wp_style_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}

		wp_register_style( self::HANDLE, false, [], SITE_ESSENTIALS_VERSION );
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_style( self::HANDLE, self::build_css( $mode ) );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function post_has_breakdance_data( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}
		$bd = get_post_meta( $post_id, '_breakdance_data', true );
		if ( is_string( $bd ) && $bd !== '' ) {
			return true;
		}
		if ( is_array( $bd ) && ! empty( $bd ) ) {
			return true;
		}
		$legacy = get_post_meta( $post_id, 'breakdance_data', true );
		return ( is_string( $legacy ) && $legacy !== '' ) || ( is_array( $legacy ) && ! empty( $legacy ) );
	}

	/**
	 * The doubled .breakdance-launcher class lifts specificity above Breakdance's
	 * own `.breakdance-launcher .breakdance-launcher-link {… !important}` rules,
	 * so the result doesn't depend on which stylesheet loads last.
	 *
	 * @param string $mode guard|protect.
	 * @return string
	 */
	private static function build_css( string $mode ): string {
		$scope = '.breakdance-launcher.breakdance-launcher';

		if ( 'protect' === $mode ) {
			return "{$scope} .breakdance-launcher-link{display:none!important;}";
		}

		$msg = wp_strip_all_tags(
			__( 'Warning: The block editor can overwrite Breakdance layout if you save. Only use this if you intend to replace the page.', 'site-essentials' )
		);
		$msg = trim( preg_replace( '/\s+/', ' ', $msg ) );

		return sprintf(
			'%1$s .breakdance-launcher__buttons{display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding-top:6px;position:relative;}
%1$s .breakdance-launcher__buttons::before{content:%2$s;display:block;flex:0 0 100%%;width:100%%;font-size:12px;line-height:1.45;color:#b32d2e;margin:0 0 6px;padding:8px 10px;background:#fcf0f1;border:1px solid #d63638;border-radius:4px;box-sizing:border-box;}
%1$s .breakdance-launcher-link{border-color:#b32d2e!important;color:#b32d2e!important;font-size:11px!important;margin-left:auto!important;}',
			$scope,
			json_encode( $msg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS )
		);
	}
}
