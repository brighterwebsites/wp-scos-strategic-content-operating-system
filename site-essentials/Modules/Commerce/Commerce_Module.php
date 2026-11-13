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
 * Set them at Site Essentials › Commerce, or with WP-CLI, e.g.
 *   wp option patch insert site_essentials_commerce hide_loop_add_to_cart 1
 *
 * @package    SiteEssentials
 * @subpackage Modules\Commerce
 * @since      1.2.0
 *
 * v1.1 | 2026-11-11 — Settings page (views/settings.php): toggles, what each
 *                     option changes, live store status. save_settings() owns
 *                     sanitising so the page and WP-CLI share one path.
 * v1.0 | 2026-11-08
 */

namespace SiteEssentials\Modules\Commerce;

use SiteEssentials\Core\Module_Interface;
use SiteEssentials\Core\Settings_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Commerce_Module implements Module_Interface {

	/** Module option, as stored by Settings_Manager (site_essentials_ + module ID). */
	const OPTION = 'site_essentials_commerce';

	/** Admin page slug (Site Essentials › Commerce). */
	const PAGE_SLUG = 'site-essentials-commerce';

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
		return '1.1.0';
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

	/**
	 * Save settings. Unknown keys are dropped; values are cast to bool.
	 * Callers own nonce and capability checks.
	 *
	 * @param array $input Raw key => value pairs (missing keys save as false).
	 * @return array The saved settings.
	 */
	public static function save_settings( array $input ) {
		$settings = [];
		foreach ( array_keys( self::DEFAULTS ) as $key ) {
			$settings[ $key ] = ! empty( $input[ $key ] );
		}
		Settings_Manager::instance()->update_module_settings( self::get_id(), $settings );
		return $settings;
	}

	/**
	 * admin-post handler for the settings page.
	 */
	public static function handle_save() {
		if ( ! isset( $_POST['scos_commerce_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scos_commerce_nonce'] ) ), 'scos_save_commerce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'site-essentials' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'site-essentials' ) );
		}

		$raw   = isset( $_POST[ self::OPTION ] ) && is_array( $_POST[ self::OPTION ] ) ? wp_unslash( $_POST[ self::OPTION ] ) : [];
		$input = array_map( 'absint', array_intersect_key( $raw, self::DEFAULTS ) );
		self::save_settings( $input );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => self::PAGE_SLUG,
					'updated' => 'true',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function init() {
		if ( ! defined( 'SCOS_COMMERCE_ACTIVE' ) ) {
			define( 'SCOS_COMMERCE_ACTIVE', true );
		}

		// Registered before the WooCommerce check so settings can be saved
		// (and the page explains itself) on a site without WooCommerce.
		add_action( 'admin_post_scos_save_commerce', [ self::class, 'handle_save' ] );

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
	 * Settings page body. Admin_UI::render_commerce_page() wraps it in
	 * <div class="wrap scos"> after its capability check.
	 */
	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'site-essentials' ) );
		}

		// Class only (no hooks), so the page can list what it strips even
		// before WooCommerce is installed.
		require_once __DIR__ . '/Store_Assets.php';

		$settings    = self::get_settings();
		$woo_active  = class_exists( 'WooCommerce' );
		$sitewide    = $woo_active && Store_Assets::has_sitewide_cart();
		$style_list  = Store_Assets::STYLE_HANDLES;
		$script_list = Store_Assets::SCRIPT_HANDLES;

		include __DIR__ . '/views/settings.php';
	}
}
