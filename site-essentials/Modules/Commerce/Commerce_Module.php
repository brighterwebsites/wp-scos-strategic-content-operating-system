<?php
/**
 * Commerce Module
 *
 * WooCommerce front-end hygiene for Breakdance sites. Turn it on when a site
 * runs WooCommerce:
 *
 *  - Store-only assets (default on): pages outside the store load none of
 *    WooCommerce's or Breakdance's WooCommerce CSS and JS. On a blog post that
 *    is ~35 KB of gzipped CSS plus WooCommerce's scripts, and with them jQuery.
 *  - Hide loop add-to-cart (default off): catalogue / quote-led stores drop the
 *    add-to-cart button and quantity input from product loops.
 *
 * Settings live in the module option `site_essentials_commerce`:
 *   [ 'store_only_assets' => bool, 'hide_loop_add_to_cart' => bool ]
 * e.g. wp option patch insert site_essentials_commerce hide_loop_add_to_cart 1
 *
 * @package    SiteEssentials
 * @subpackage Modules\Commerce
 * @since      1.2.0
 *
 * v1.0 | 2026-11-08
 */

namespace SiteEssentials\Modules\Commerce;

use SiteEssentials\Core\Module_Interface;
use SiteEssentials\Core\Settings_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Commerce_Module implements Module_Interface {

	const DEFAULTS = [
		'store_only_assets'     => true,
		'hide_loop_add_to_cart' => false,
	];

	public static function get_id() {
		return 'commerce';
	}

	public static function get_name() {
		return __( 'Commerce', 'site-essentials' );
	}

	public static function get_description() {
		return __( 'WooCommerce hygiene for Breakdance sites. Pages outside the store stop loading WooCommerce CSS and JS (and with them jQuery). Optional catalogue mode hides add-to-cart in product loops.', 'site-essentials' );
	}

	public static function get_tier() {
		return 'basic';
	}

	public static function get_dependencies() {
		return [];
	}

	public static function get_version() {
		return '1.0.0';
	}

	/**
	 * Current settings merged over the defaults.
	 *
	 * @return array{store_only_assets:bool,hide_loop_add_to_cart:bool}
	 */
	public static function get_settings() {
		$saved = Settings_Manager::instance()->get_module_setting( self::get_id() );
		$saved = is_array( $saved ) ? $saved : [];

		$settings = [];
		foreach ( self::DEFAULTS as $key => $default ) {
			$settings[ $key ] = array_key_exists( $key, $saved ) ? (bool) $saved[ $key ] : $default;
		}
		return $settings;
	}

	public function init() {
		// Nothing to do without WooCommerce. The module can stay on harmlessly.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		require_once __DIR__ . '/Store_Assets.php';
		require_once __DIR__ . '/Loop_Add_To_Cart.php';

		$settings = self::get_settings();

		// Always registered: keeps the header/footer cart scan fresh even while
		// store-only assets is off, so switching it on is accurate immediately.
		add_action( 'save_post', [ Store_Assets::class, 'flush_sitewide_cart_cache' ] );

		if ( $settings['store_only_assets'] ) {
			Store_Assets::init();
		}

		if ( $settings['hide_loop_add_to_cart'] ) {
			Loop_Add_To_Cart::init();
		}
	}

	/**
	 * Status panel. This module has no settings screen yet: settings are set
	 * with WP-CLI (see the class docblock).
	 */
	public function render_settings() {
		$settings = self::get_settings();
		$cart     = class_exists( __NAMESPACE__ . '\Store_Assets' ) ? Store_Assets::has_sitewide_cart() : null;

		echo '<table class="widefat striped" style="max-width:640px"><tbody>';
		printf(
			'<tr><th>%s</th><td>%s</td></tr>',
			esc_html__( 'Store-only assets', 'site-essentials' ),
			$settings['store_only_assets'] ? esc_html__( 'On', 'site-essentials' ) : esc_html__( 'Off', 'site-essentials' )
		);
		printf(
			'<tr><th>%s</th><td>%s</td></tr>',
			esc_html__( 'Hide loop add-to-cart', 'site-essentials' ),
			$settings['hide_loop_add_to_cart'] ? esc_html__( 'On', 'site-essentials' ) : esc_html__( 'Off', 'site-essentials' )
		);
		if ( null !== $cart ) {
			printf(
				'<tr><th>%s</th><td>%s</td></tr>',
				esc_html__( 'Cart in header/footer', 'site-essentials' ),
				$cart
					? esc_html__( 'Yes: WooCommerce assets load on every page so the cart works.', 'site-essentials' )
					: esc_html__( 'No', 'site-essentials' )
			);
		}
		echo '</tbody></table>';
	}
}
