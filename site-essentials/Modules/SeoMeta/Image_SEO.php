<?php
/**
 * Image SEO — attachment page optimisations and upload-time file renaming.
 *
 * Settings are stored as a single serialised array in the `scos_image_seo`
 * wp_option. All behaviour is gated behind individual toggles; nothing runs
 * unless the corresponding option is enabled.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Image_SEO {

	const OPTION_KEY = 'scos_image_seo';

	// ── Defaults ──────────────────────────────────────────────────────────────

	public static function defaults(): array {
		return [
			'noindex_attachments'     => false,
			'no_comments_attachments' => false,
			'redirect_attachments'    => false,
			'rename_files'            => false,
		];
	}

	// ── Options API ───────────────────────────────────────────────────────────

	public static function get(): array {
		return wp_parse_args(
			(array) get_option( self::OPTION_KEY, [] ),
			self::defaults()
		);
	}

	// ── Bootstrap ─────────────────────────────────────────────────────────────

	public static function init(): void {
		$opts = self::get();

		if ( ! empty( $opts['noindex_attachments'] ) ) {
			add_filter( 'wp_robots', [ __CLASS__, 'noindex_attachments' ] );
		}

		if ( ! empty( $opts['no_comments_attachments'] ) ) {
			add_filter( 'comments_open',       [ __CLASS__, 'close_attachment_comments' ], 10, 2 );
			add_filter( 'get_comments_number', [ __CLASS__, 'zero_attachment_comments' ],  10, 2 );
		}

		if ( ! empty( $opts['redirect_attachments'] ) ) {
			// Priority 3 — before most other template_redirect hooks.
			add_action( 'template_redirect', [ __CLASS__, 'redirect_attachment_pages' ], 3 );
		}

		if ( ! empty( $opts['rename_files'] ) ) {
			add_filter( 'sanitize_file_name', [ __CLASS__, 'rename_uploaded_file' ] );
		}
	}

	// ── Behaviour callbacks ───────────────────────────────────────────────────

	/**
	 * Add noindex + nofollow to attachment pages via the wp_robots filter
	 * (WP 5.7+; respects existing directives from other plugins).
	 */
	public static function noindex_attachments( array $robots ): array {
		if ( is_attachment() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	/**
	 * Prevent comments from being open on attachment post types.
	 *
	 * @param bool $open    Whether comments are open.
	 * @param int  $post_id Post ID.
	 */
	public static function close_attachment_comments( $open, $post_id ): bool {
		if ( 'attachment' === get_post_type( (int) $post_id ) ) {
			return false;
		}
		return (bool) $open;
	}

	/**
	 * Return 0 comment count for attachments so comment UI is hidden.
	 *
	 * @param mixed $count   Current comment count.
	 * @param int   $post_id Post ID.
	 * @return mixed
	 */
	public static function zero_attachment_comments( $count, $post_id ) {
		if ( 'attachment' === get_post_type( (int) $post_id ) ) {
			return 0;
		}
		return $count;
	}

	/**
	 * 301-redirect any attachment page directly to the file URL.
	 * Falls back to home URL if the file URL can't be resolved.
	 */
	public static function redirect_attachment_pages(): void {
		if ( ! is_attachment() ) {
			return;
		}
		$url = wp_get_attachment_url( get_the_ID() );
		wp_redirect( $url ?: home_url( '/' ), 301 );
		exit;
	}

	/**
	 * Sanitise uploaded filenames at save time:
	 *  - Force lowercase (name + extension).
	 *  - Replace spaces and underscores with hyphens.
	 *  - Collapse consecutive hyphens.
	 *  - Strip leading/trailing hyphens.
	 */
	public static function rename_uploaded_file( string $filename ): string {
		$ext  = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		$name = strtolower( (string) pathinfo( $filename, PATHINFO_FILENAME ) );

		$name = str_replace( [ ' ', '_' ], '-', $name );
		$name = (string) preg_replace( '/-{2,}/', '-', $name );
		$name = trim( $name, '-' );

		return $ext ? "{$name}.{$ext}" : $name;
	}

	// ── Admin save handler ────────────────────────────────────────────────────

	public static function handle_save(): void {
		if ( ! isset( $_POST['scos_image_seo_nonce'] ) ||
		     ! wp_verify_nonce(
		         sanitize_text_field( wp_unslash( $_POST['scos_image_seo_nonce'] ) ),
		         'scos_save_image_seo'
		     ) ) {
			wp_die( esc_html__( 'Security check failed.', 'site-essentials' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'site-essentials' ) );
		}

		$posted = ( isset( $_POST['scos_image_seo'] ) && is_array( $_POST['scos_image_seo'] ) )
			? $_POST['scos_image_seo']
			: [];

		update_option(
			self::OPTION_KEY,
			[
				'noindex_attachments'     => ! empty( $posted['noindex_attachments'] ),
				'no_comments_attachments' => ! empty( $posted['no_comments_attachments'] ),
				'redirect_attachments'    => ! empty( $posted['redirect_attachments'] ),
				'rename_files'            => ! empty( $posted['rename_files'] ),
			],
			false
		);

		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => \SiteEssentials\Core\Admin_UI::SEO_PAGE_SLUG,
					'tab'     => 'advanced',
					'updated' => 'true',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
