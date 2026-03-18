<?php
/**
 * Analytics Module
 *
 * Manages GA4 tracking configuration, event seeding status, and
 * content strategy analytics for the SCOS platform.
 *
 * When active:
 * - Defines SCOS_ANALYTICS_ACTIVE (hides legacy brighter-core Analytics menu)
 * - Manages GA4 Measurement ID (option: brighter_ga4_measurement_id)
 * - Renders settings panel with seeding status + how-to
 *
 * @package    SiteEssentials
 * @subpackage Modules\Analytics
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\Analytics;

use SiteEssentials\Core\Module_Interface;
use SiteEssentials\Core\Settings_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Analytics_Module implements Module_Interface {

	public static function get_id() {
		return 'analytics';
	}

	public static function get_name() {
		return __( 'Analytics', 'site-essentials' );
	}

	public static function get_description() {
		return __( 'GA4 tracking configuration, event seeding, and content strategy analytics powered by the SCOS CAR.', 'site-essentials' );
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

	public function init() {
		if ( ! defined( 'SCOS_ANALYTICS_ACTIVE' ) ) {
			define( 'SCOS_ANALYTICS_ACTIVE', true );
		}

		// Handle settings save
		add_action( 'admin_init', [ __CLASS__, 'save_settings' ] );
	}

	/**
	 * Save GA4 Measurement ID from the site-essentials settings page.
	 */
	public static function save_settings() {
		if ( ! isset( $_POST['scos_analytics_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['scos_analytics_nonce'], 'scos_analytics_settings' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_POST['brighter_ga4_measurement_id'] ) ) {
			update_option(
				'brighter_ga4_measurement_id',
				sanitize_text_field( wp_unslash( $_POST['brighter_ga4_measurement_id'] ) )
			);
		}
	}

	public function render_settings() {
		include __DIR__ . '/views/settings.php';
	}
}
