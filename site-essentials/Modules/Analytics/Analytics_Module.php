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

		add_action( 'admin_init', [ __CLASS__, 'save_settings' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_seed_reset' ] );
		add_action( 'wp_ajax_brighter_ga4_seed_complete', [ __CLASS__, 'ajax_seed_complete' ] );
	}

	/**
	 * Save GA4 Measurement ID — POST handler.
	 */
	public static function save_settings() {
		if ( ! isset( $_POST['scos_analytics_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scos_analytics_nonce'] ) ), 'scos_analytics_settings' ) ) {
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
		wp_safe_redirect( admin_url( 'admin.php?page=site-essentials-analytics&scos_analytics_saved=1' ) );
		exit;
	}

	/**
	 * Handle seed reset via GET param.
	 */
	public static function handle_seed_reset() {
		if (
			isset( $_GET['page'], $_GET['scos_reset_seed'] ) &&
			'site-essentials-analytics' === $_GET['page'] &&
			current_user_can( 'manage_options' )
		) {
			delete_transient( 'brighter_ga4_events_seeded' );
			delete_transient( 'brighter_ga4_seed_date' );
			wp_safe_redirect( admin_url( 'admin.php?page=site-essentials-analytics' ) );
			exit;
		}
	}

	/**
	 * AJAX: mark GA4 events as seeded (fired by the seeder script on the frontend).
	 */
	public static function ajax_seed_complete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		set_transient( 'brighter_ga4_events_seeded', true, 90 * DAY_IN_SECONDS );
		set_transient( 'brighter_ga4_seed_date', current_time( 'mysql' ), 90 * DAY_IN_SECONDS );
		wp_send_json_success( [
			'message' => 'GA4 events seeded successfully',
			'date'    => current_time( 'mysql' ),
		] );
	}

	public function render_settings() {
		include __DIR__ . '/views/settings.php';
	}
}
