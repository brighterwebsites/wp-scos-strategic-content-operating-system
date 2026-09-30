<?php
/**
 * Tweaks — Disable Comments Site-wide
 *
 * Turns comments and pingbacks off everywhere and removes the comment
 * surfaces WordPress adds: the admin menu and screen, the admin bar bubble,
 * the dashboard widget, the comments REST endpoints, pingback XML-RPC methods,
 * the X-Pingback header and comment feeds.
 *
 * Nothing is written to the database. Defaults are answered through
 * pre_option filters, so switching the tweak off restores the site exactly as
 * it was — including any comments already stored.
 *
 * WooCommerce product reviews are comments, so the `product` post type is left
 * alone while WooCommerce is active. Other post types can be exempted through
 * the `scos_disable_comments_exempt_post_types` filter.
 *
 * Loaded by Tweaks_Module when the `disable_comments` tweak is on.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Tweaks
 *
 * v1.0 | 2026-10-01
 */

declare( strict_types=1 );

namespace SiteEssentials\Modules\Tweaks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Comments_Disabler {

	/**
	 * Register every hook. Called once, from Tweaks_Module::apply_tweak().
	 *
	 * @return void
	 */
	public static function init(): void {
		// Priority 20: run after plugins that open comments on their own types.
		add_filter( 'comments_open', [ __CLASS__, 'close_unless_exempt' ], 20, 2 );
		add_filter( 'pings_open', [ __CLASS__, 'close_unless_exempt' ], 20, 2 );
		add_filter( 'comments_array', [ __CLASS__, 'hide_comments_unless_exempt' ], 20, 2 );

		add_filter( 'pre_option_default_comment_status', [ __CLASS__, 'closed' ] );
		add_filter( 'pre_option_default_ping_status', [ __CLASS__, 'closed' ] );

		// Priority 100: after custom post types have registered on init.
		add_action( 'init', [ __CLASS__, 'remove_post_type_support' ], 100 );

		// Priority 999: after every plugin has added its menus and nodes.
		add_action( 'admin_menu', [ __CLASS__, 'remove_admin_menu' ], 999 );
		add_action( 'admin_bar_menu', [ __CLASS__, 'remove_admin_bar_node' ], 999 );
		add_action( 'admin_init', [ __CLASS__, 'redirect_comments_screen' ] );
		add_action( 'wp_dashboard_setup', [ __CLASS__, 'remove_dashboard_widget' ] );

		add_filter( 'rest_endpoints', [ __CLASS__, 'remove_rest_endpoints' ] );
		add_filter( 'xmlrpc_methods', [ __CLASS__, 'remove_pingback_methods' ] );
		add_filter( 'wp_headers', [ __CLASS__, 'remove_pingback_header' ] );

		add_filter( 'feed_links_show_comments_feed', '__return_false' );
		// Priority 1: before the feed template loads.
		add_action( 'template_redirect', [ __CLASS__, 'redirect_comment_feeds' ], 1 );
	}

	/**
	 * Post types whose comments stay as they are.
	 *
	 * @return string[]
	 */
	public static function exempt_post_types(): array {
		$exempt = class_exists( 'WooCommerce' ) ? [ 'product' ] : [];

		/**
		 * Filters the post types that keep comments while the tweak is on.
		 *
		 * @param string[] $exempt Post type slugs. `product` when WooCommerce is active.
		 */
		return (array) apply_filters( 'scos_disable_comments_exempt_post_types', $exempt );
	}

	/**
	 * Whether a post type keeps its comments.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	public static function is_exempt( string $post_type ): bool {
		return '' !== $post_type && in_array( $post_type, self::exempt_post_types(), true );
	}

	/**
	 * comments_open / pings_open: closed, except on exempt post types.
	 *
	 * @param bool $open    Whether comments or pings are open.
	 * @param int  $post_id Post ID.
	 * @return bool
	 */
	public static function close_unless_exempt( $open, $post_id ): bool {
		return self::is_exempt( (string) get_post_type( (int) $post_id ) ) ? (bool) $open : false;
	}

	/**
	 * comments_array: hide comments already stored, except on exempt post types.
	 *
	 * @param array $comments Comments for the post.
	 * @param int   $post_id  Post ID.
	 * @return array
	 */
	public static function hide_comments_unless_exempt( $comments, $post_id ): array {
		return self::is_exempt( (string) get_post_type( (int) $post_id ) ) ? (array) $comments : [];
	}

	/**
	 * Default comment and ping status for new posts.
	 *
	 * @return string
	 */
	public static function closed(): string {
		return 'closed';
	}

	/**
	 * Remove comment and trackback support from every non-exempt post type,
	 * which also hides the Discussion box in the editor.
	 *
	 * @return void
	 */
	public static function remove_post_type_support(): void {
		foreach ( get_post_types() as $post_type ) {
			if ( self::is_exempt( $post_type ) ) {
				continue;
			}
			if ( post_type_supports( $post_type, 'comments' ) ) {
				remove_post_type_support( $post_type, 'comments' );
			}
			if ( post_type_supports( $post_type, 'trackbacks' ) ) {
				remove_post_type_support( $post_type, 'trackbacks' );
			}
		}
	}

	/**
	 * Remove the Comments admin menu. WooCommerce reviews have their own
	 * screen under Products, so this is safe with WooCommerce active.
	 *
	 * @return void
	 */
	public static function remove_admin_menu(): void {
		remove_menu_page( 'edit-comments.php' );
	}

	/**
	 * Send anyone who reaches the Comments screen directly back to the dashboard.
	 *
	 * @return void
	 */
	public static function redirect_comments_screen(): void {
		global $pagenow;

		if ( 'edit-comments.php' === $pagenow ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	/**
	 * Remove the comments bubble from the admin bar.
	 *
	 * @param \WP_Admin_Bar $admin_bar The admin bar.
	 * @return void
	 */
	public static function remove_admin_bar_node( $admin_bar ): void {
		$admin_bar->remove_node( 'comments' );
	}

	/**
	 * Remove the Activity dashboard widget's recent-comments sibling.
	 *
	 * @return void
	 */
	public static function remove_dashboard_widget(): void {
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}

	/**
	 * Remove the core comments REST endpoints. WooCommerce product reviews use
	 * their own /wc/ endpoints and are unaffected.
	 *
	 * @param array $endpoints Registered REST endpoints.
	 * @return array
	 */
	public static function remove_rest_endpoints( $endpoints ): array {
		unset( $endpoints['/wp/v2/comments'], $endpoints['/wp/v2/comments/(?P<id>[\d]+)'] );
		return (array) $endpoints;
	}

	/**
	 * Remove the pingback XML-RPC methods.
	 *
	 * @param array $methods XML-RPC methods.
	 * @return array
	 */
	public static function remove_pingback_methods( $methods ): array {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return (array) $methods;
	}

	/**
	 * Remove the X-Pingback response header.
	 *
	 * @param array $headers Response headers.
	 * @return array
	 */
	public static function remove_pingback_header( $headers ): array {
		unset( $headers['X-Pingback'] );
		return (array) $headers;
	}

	/**
	 * 301 comment feeds: a post's comment feed to the post, the site-wide one home.
	 *
	 * @return void
	 */
	public static function redirect_comment_feeds(): void {
		if ( ! is_comment_feed() ) {
			return;
		}

		$destination = is_singular() ? (string) get_permalink() : home_url( '/' );
		wp_safe_redirect( $destination, 301 );
		exit;
	}
}
