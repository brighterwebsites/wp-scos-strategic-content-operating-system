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
 * Only bridges when the plugin's measurement ID matches the SCOS one. A different
 * ID means the site deliberately reports ecommerce elsewhere, so the plugin is left alone.
 *
 * v1.0 | 2026-10-21 (#206)
 *
 * @package    SiteEssentials
 * @subpackage Modules\Analytics
 */

namespace SiteEssentials\Modules\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Woo_GA_Bridge {

	/** Script handle the plugin registers for gtag.js. */
	const GTAG_HANDLE = 'google-tag-manager';

	public static function init() {
		if ( ! self::should_bridge() ) {
			return;
		}

		add_filter( 'woocommerce_ga_gtag_config', [ __CLASS__, 'disable_page_view' ] );
		add_filter( 'scos_ga4_load_immediately', [ __CLASS__, 'load_now_on_order_received' ] );
		// After the plugin enqueues its tracker (wp_enqueue_scripts, priority 5).
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'empty_gtag_handle' ], 20 );
	}

	/**
	 * Bridge only when SCOS and the plugin are both configured with the same measurement ID.
	 */
	private static function should_bridge() {
		$scos_id = (string) get_option( 'brighter_ga4_measurement_id', '' );
		if ( '' === $scos_id ) {
			return false;
		}

		$settings  = get_option( 'woocommerce_google_analytics_settings', [] );
		$plugin_id = is_array( $settings ) && ! empty( $settings['ga_id'] ) ? (string) $settings['ga_id'] : '';

		return '' !== $plugin_id && 0 === strcasecmp( trim( $scos_id ), trim( $plugin_id ) );
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
