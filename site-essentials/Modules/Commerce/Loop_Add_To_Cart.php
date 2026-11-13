<?php
/**
 * Hide add-to-cart and quantity in product loops (catalogue / quote-led stores).
 *
 * Covers WooCommerce's default loop, Breakdance's shop/product-list loops
 * (which move the button into `breakdance_shop_loop_footer`) and the
 * Breakdance WooLoopCartButton element, which all print through
 * woocommerce_template_loop_add_to_cart() and its link filter. Single product
 * pages are left alone.
 *
 * Breakdance's shop element also has its own per-element "button" option;
 * this is the site-wide switch.
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

class Loop_Add_To_Cart {

	public static function init() {
		// Catch-all: every loop button goes through this filter.
		add_filter( 'woocommerce_loop_add_to_cart_link', '__return_empty_string', 99 );

		// Also unhook the callers so no empty wrappers or quantity inputs print.
		// 'wp': after Breakdance has added its loop hooks (file load, plugins_loaded).
		add_action( 'wp', [ self::class, 'unhook' ] );
	}

	public static function unhook() {
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
		remove_action( 'breakdance_shop_loop_footer', 'woocommerce_template_loop_add_to_cart', 10 );
		// Breakdance registers this with a leading backslash; remove_action needs the same string.
		remove_action( 'breakdance_shop_loop_footer', '\Breakdance\WooCommerce\addQuantityInputToShopLoop', 9 );
	}
}
