<?php
/**
 * Store-only WooCommerce assets.
 *
 * WooCommerce and Breakdance's WooCommerce integration load their CSS and JS
 * on every page. Outside the store nothing uses them, and the scripts are the
 * only reason jQuery loads on most Breakdance pages.
 *
 * A request counts as "store" when it is a WooCommerce page (shop, product,
 * product taxonomy, cart, checkout, account), when the queried post itself
 * uses WooCommerce (Breakdance Woo elements, Woo shortcodes or blocks), or
 * when a published Breakdance header/footer contains a cart, in which case
 * the assets load everywhere. Override with the
 * `scos_commerce_is_store_request` filter, e.g. for a Woo element inside a
 * global block, which the content check does not follow.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Commerce
 *
 * v1.0 | 2026-11-08
 */

namespace SiteEssentials\Modules\Commerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Store_Assets {

	const CART_TRANSIENT = 'se_commerce_sitewide_cart';

	/**
	 * Breakdance element types that need WooCommerce assets. Matched as a
	 * prefix after `EssentialElements\`, so `Woo` covers WooShopPage,
	 * WooBreadcrumb, WooLoopCartButton and so on.
	 */
	const ELEMENT_PREFIXES = [ 'Woo', 'Product', 'FProduct', 'MiniCart', 'Cart', 'Checkout', 'MyAccount' ];

	/** Cart elements that put WooCommerce on every page when in a header/footer. */
	const CART_ELEMENTS = [ 'MiniCart', 'WooMiniCart', 'Cart' ];

	const STYLE_HANDLES = [
		'woocommerce-general',
		'woocommerce-layout',
		'woocommerce-smallscreen',
		'woocommerce-inline',
		'wc-blocks-style',
		'wc-blocks-vendors-style',
		'breakdance-woo-inline',
	];

	const SCRIPT_HANDLES = [
		'woocommerce',
		'wc-add-to-cart',
		'wc-add-to-cart-variation',
		'wc-cart-fragments',
		'wc-jquery-blockui',
		'jquery-blockui',
		'wc-js-cookie',
		'js-cookie',
		'sourcebuster-js',
		'wc-order-attribution',
		'breakdance-woo-qty',
	];

	public static function init() {
		// 'wp': the main query is parsed, so the conditional tags work, and
		// nothing has been enqueued or rendered yet.
		add_action( 'wp', [ self::class, 'maybe_strip' ] );
	}

	/**
	 * Decide once per request, then register the removals.
	 */
	public static function maybe_strip() {
		if ( self::is_exempt_request() || self::is_store_request() ) {
			return;
		}

		// WooCommerce's own stylesheets (used when Breakdance's Woo styles are off).
		add_filter( 'woocommerce_enqueue_styles', '__return_empty_array', 99 );

		// Priority 100: after WooCommerce and Breakdance have enqueued.
		add_action( 'wp_enqueue_scripts', [ self::class, 'dequeue' ], 100 );

		// Breakdance's breakdance-woocommerce.css is not a WP handle: it sits on
		// a list Breakdance prints itself when it renders the page.
		self::drop_breakdance_woo_stylesheets();

		// The inline script and body class that swap woocommerce-no-js.
		remove_action( 'wp_footer', 'wc_no_js' );
		remove_filter( 'body_class', 'wc_body_class' );
	}

	public static function dequeue() {
		$styles  = apply_filters( 'scos_commerce_strip_style_handles', self::STYLE_HANDLES );
		$scripts = apply_filters( 'scos_commerce_strip_script_handles', self::SCRIPT_HANDLES );

		foreach ( $styles as $handle ) {
			wp_dequeue_style( $handle );
		}
		foreach ( $scripts as $handle ) {
			wp_dequeue_script( $handle );
		}
	}

	private static function drop_breakdance_woo_stylesheets() {
		$class = '\Breakdance\GlobalDefaultStylesheets\GlobalDefaultStylesheetsController';
		if ( ! class_exists( $class ) ) {
			return;
		}

		$controller = $class::getInstance();
		if ( ! isset( $controller->stylesheetUrls ) || ! is_array( $controller->stylesheetUrls ) ) {
			return;
		}

		$controller->stylesheetUrls = array_values(
			array_filter(
				$controller->stylesheetUrls,
				static function ( $url ) {
					return false === strpos( (string) $url, '/plugin/woocommerce/' );
				}
			)
		);
	}

	/**
	 * Requests this module must never touch.
	 */
	private static function is_exempt_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return true;
		}
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return true;
		}
		// Breakdance builder canvas, iframes and SSR previews.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['breakdance'] ) || isset( $_GET['breakdance_iframe'] ) || isset( $_GET['_breakdance_doing_ajax'] ) ) {
			return true;
		}
		if ( function_exists( '\Breakdance\isRequestFromBuilderIframe' ) && \Breakdance\isRequestFromBuilderIframe() ) {
			return true;
		}
		return false;
	}

	/**
	 * Does this request need WooCommerce's front-end assets?
	 *
	 * @return bool
	 */
	public static function is_store_request() {
		$is_store = false;

		if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
			$is_store = true;
		} elseif ( self::has_sitewide_cart() ) {
			$is_store = true;
		} elseif ( is_singular() ) {
			$is_store = self::post_uses_woocommerce( get_queried_object_id() );
		}

		/**
		 * Filter whether this request loads WooCommerce assets.
		 *
		 * @param bool $is_store True when the request needs WooCommerce assets.
		 */
		return (bool) apply_filters( 'scos_commerce_is_store_request', $is_store );
	}

	/**
	 * Does the post's own content use WooCommerce?
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function post_uses_woocommerce( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$content = (string) $post->post_content;
		if ( false !== strpos( $content, '<!-- wp:woocommerce/' ) ) {
			return true;
		}
		if ( preg_match( '/\[(products|product|product_page|product_category|product_categories|add_to_cart|add_to_cart_url|shop_messages|woocommerce_[a-z_]+)[\s\]]/', $content ) ) {
			return true;
		}

		$tree = get_post_meta( $post->ID, '_breakdance_data', true );
		return is_string( $tree ) && '' !== $tree && self::tree_has_elements( $tree, self::ELEMENT_PREFIXES );
	}

	/**
	 * Is there a cart in a published Breakdance header or footer? Then the
	 * assets have to load everywhere. Cached until a header/footer is saved.
	 *
	 * @return bool
	 */
	public static function has_sitewide_cart() {
		$cached = get_transient( self::CART_TRANSIENT );
		if ( false !== $cached ) {
			return 'yes' === $cached;
		}

		$ids = get_posts(
			[
				'post_type'      => [ 'breakdance_header', 'breakdance_footer' ],
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);
		update_meta_cache( 'post', $ids );

		$found = false;
		foreach ( $ids as $id ) {
			$tree = get_post_meta( $id, '_breakdance_data', true );
			if ( is_string( $tree ) && self::tree_has_elements( $tree, self::CART_ELEMENTS, true ) ) {
				$found = true;
				break;
			}
		}

		set_transient( self::CART_TRANSIENT, $found ? 'yes' : 'no', DAY_IN_SECONDS );
		return $found;
	}

	/**
	 * @param int $post_id Post being saved.
	 */
	public static function flush_sitewide_cart_cache( $post_id ) {
		$type = get_post_type( $post_id );
		if ( 'breakdance_header' === $type || 'breakdance_footer' === $type ) {
			delete_transient( self::CART_TRANSIENT );
		}
	}

	/**
	 * Match element types in raw `_breakdance_data`. The tree is JSON inside
	 * JSON, so the namespace separator appears with a varying number of
	 * backslashes; match one or more.
	 *
	 * @param string   $raw      Raw meta value.
	 * @param string[] $names    Element type names (or prefixes).
	 * @param bool     $exact    True to match whole type names only.
	 * @return bool
	 */
	private static function tree_has_elements( $raw, array $names, $exact = false ) {
		$alts    = implode( '|', array_map( 'preg_quote', $names ) );
		$pattern = '/EssentialElements(?:\\\\)+(?:' . $alts . ')' . ( $exact ? '(?=\\\\*")' : '' ) . '/i';
		return (bool) preg_match( $pattern, $raw );
	}
}
