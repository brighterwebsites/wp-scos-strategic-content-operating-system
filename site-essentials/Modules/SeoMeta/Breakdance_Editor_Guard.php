<?php
/**
 * Breakdance editor launcher guard — reduce accidental "Use default editor" usage.
 *
 * When _breakdance_data exists, optionally hide or de-emphasize the
 * "Use default editor" control on post.php. Role-based restrictions are out of scope (phase 2).
 *
 * @package SiteEssentials
 */

namespace SiteEssentials\Modules\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Breakdance_Editor_Guard {

	/**
	 * @return void
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ], 99 );
	}

	/**
	 * @param string $hook_suffix Current admin screen.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

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

		$css = self::build_css( $mode );
		if ( $css === '' ) {
			return;
		}

		$handle = 'scos-breakdance-editor-guard';
		wp_register_style( $handle, false, [], SITE_ESSENTIALS_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );
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
	 * @param string $mode guard|protect.
	 * @return string
	 */
	private static function build_css( string $mode ): string {
		if ( 'protect' === $mode ) {
			return '.breakdance-launcher .breakdance-launcher-link{display:none!important;}';
		}

		$msg = wp_strip_all_tags(
			__( 'Warning: The block editor can overwrite Breakdance layout if you save. Only use this if you intend to replace the page.', 'site-essentials' )
		);
		$msg = trim( preg_replace( '/\s+/', ' ', $msg ) );

		return sprintf(
			'.breakdance-launcher__buttons{flex-wrap:wrap;gap:8px;align-items:flex-start;padding-top:6px;position:relative;}
.breakdance-launcher__buttons::before{content:%s;display:block;width:100%%;font-size:12px;line-height:1.45;color:#b32d2e;margin:0 0 6px;padding:8px 10px;background:#fcf0f1;border:1px solid #d63638;border-radius:4px;}
.breakdance-launcher .breakdance-launcher-link{border-color:#b32d2e!important;color:#b32d2e!important;font-size:11px!important;margin-left:auto;}',
			json_encode( $msg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS )
		);
	}
}
