<?php
/**
 * WooCommerce GA bridge
 *
 * Lets the Google Analytics for WooCommerce plugin (woocommerce-google-analytics-integration)
 * send its ecommerce events through the SCOS gtag instead of loading its own:
 * - no second gtag.js request (its `google-tag-manager` handle is emptied)
 * - no second page_view (its config gets send_page_view: false; SCOS sends page_view)
 * - gtag.js loads at once on the order-received page so `purchase` isn't lost
 *
 * A blank plugin measurement ID inherits the SCOS one, so the ID is set in one place.
 * Only bridges when the plugin's ID then matches the SCOS one. A different ID means
 * the site deliberately reports ecommerce elsewhere, so the plugin is left alone.
 *
 * v1.2 | 2026-10-22: ID inheritance registered on plugins_loaded. WooCommerce builds its
 *                     integrations at init 0, before modules load (init 5), so the filter
 *                     added in init() came too late and the plugin stayed disabled.
 * v1.1 | 2026-10-21: blank plugin ID inherits the SCOS ID (#206)
 * v1.0 | 2026-10-21 (#206)
 *
 * @package    SiteEssentials
 * @subpackage Modules\Analytics
 */

namespace SiteEssentials\Modules\Analytics;

use SiteEssentials\Core\Settings_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Woo_GA_Bridge {

	/** Script handle the plugin registers for gtag.js. */
	const GTAG_HANDLE = 'google-tag-manager';

	/** Option the plugin stores its integration settings in (ga_id lives here). */
	const SETTINGS_OPTION = 'woocommerce_google_analytics_settings';

	/**
	 * Hooked on plugins_loaded from site-essentials.php. WooCommerce reads the plugin's
	 * settings when it builds its integrations (init 0), before Module_Loader runs (init 5),
	 * so the filter can't wait for init().
	 */
	public static function register_inheritance() {
		if ( '' === self::scos_id() || ! Settings_Manager::instance()->is_module_enabled( 'analytics' ) ) {
			return;
		}

		add_filter( 'option_' . self::SETTINGS_OPTION, [ __CLASS__, 'inherit_scos_id' ] );
		add_filter( 'default_option_' . self::SETTINGS_OPTION, [ __CLASS__, 'inherit_scos_id' ] );
	}

	public static function init() {
		if ( '' === self::scos_id() || ! self::should_bridge() ) {
			return;
		}

		add_filter( 'woocommerce_ga_gtag_config', [ __CLASS__, 'disable_page_view' ] );
		add_filter( 'scos_ga4_load_immediately', [ __CLASS__, 'load_now_on_order_received' ] );
		// After the plugin enqueues its tracker (wp_enqueue_scripts, priority 5).
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'empty_gtag_handle' ], 20 );
	}

	private static function scos_id() {
		return trim( (string) get_option( 'brighter_ga4_measurement_id', '' ) );
	}

	/**
	 * Fill a blank plugin measurement ID with the SCOS one. The plugin disables all
	 * tracking while ga_id is empty. A non-blank ID is never touched.
	 */
	public static function inherit_scos_id( $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		if ( empty( $settings['ga_id'] ) ) {
			$settings['ga_id'] = self::scos_id();
		}
		return $settings;
	}

	/**
	 * Bridge only when the plugin (after inheriting) uses the SCOS measurement ID.
	 */
	private static function should_bridge() {
		$settings  = get_option( self::SETTINGS_OPTION, [] );
		$plugin_id = is_array( $settings ) && ! empty( $settings['ga_id'] ) ? trim( (string) $settings['ga_id'] ) : '';

		return '' !== $plugin_id && 0 === strcasecmp( self::scos_id(), $plugin_id );
	}

	/**
	 * SCOS sends page_view itself, with the CAR parameters.
	 */
	public static function disable_page_view( $config ) {
		if ( ! is_array( $config ) ) {
			$config = [];
		}
		$config['send_page_view'] = false;
		return $config;
	}

	/**
	 * The thank-you page fires `purchase`; don't make it wait for idle.
	 */
	public static function load_now_on_order_received( $load_now ) {
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
			return true;
		}
		return $load_now;
	}

	/**
	 * Replace the plugin's gtag.js with an empty handle. Its main.js depends on the
	 * handle, so it has to stay registered; with no src nothing is printed.
	 */
	public static function empty_gtag_handle() {
		if ( ! wp_script_is( self::GTAG_HANDLE, 'registered' ) ) {
			return;
		}
		wp_deregister_script( self::GTAG_HANDLE );
		wp_register_script( self::GTAG_HANDLE, false, [], null );
	}
}
