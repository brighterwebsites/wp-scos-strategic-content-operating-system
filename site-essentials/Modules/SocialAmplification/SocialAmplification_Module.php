<?php
/**
 * Social Amplification Module
 *
 * Per-page social amplification panel:
 *  - YOURLS shortlink slug  (scos_sa_shortlink_slug / scos_sma_pf_* for Post Framing)
 *  - "Create Social Post" button (triggers existing bw_trigger_social_webhook AJAX action)
 *  - Webhook status / last trigger time
 *
 * Defines SCOS_SA_ACTIVE to gate legacy BW_Social_Webhook_Manual meta box and
 * its admin columns so they don't duplicate the new UI.
 *
 * Options are now saved under the scos_sma_ prefix; values are dual-written to
 * legacy bw_* keys so existing webhook/YOURLS code continues to work.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification
 * @version    1.1.0
 */

namespace SiteEssentials\Modules\SocialAmplification;

use SiteEssentials\Core\Module_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SocialAmplification_Module implements Module_Interface {

	public static function get_id() {
		return 'social_amplification';
	}

	public static function get_name() {
		return __( 'Social Amplification', 'site-essentials' );
	}

	public static function get_description() {
		return __( 'YOURLS shortlink slug, Post Framing templates, and manual Make.com webhook trigger for social post creation.', 'site-essentials' );
	}

	public static function get_tier() {
		return 'pro';
	}

	public static function get_dependencies() {
		return [];
	}

	public static function get_version() {
		return '1.1.0';
	}

	public function init() {
		if ( ! defined( 'SCOS_SA_ACTIVE' ) ) {
			define( 'SCOS_SA_ACTIVE', true );
		}

		require_once __DIR__ . '/Meta_Fields.php';
		require_once __DIR__ . '/Meta_Box.php';
		require_once __DIR__ . '/Post_Framing.php';

		// ── Postly.ai amplification pipeline ──────────────────────────────
		require_once __DIR__ . '/Amplification/Anthropic_Client.php';
		require_once __DIR__ . '/Amplification/Postly_Client.php';
		require_once __DIR__ . '/Amplification/Amplification_Engine.php';
		require_once __DIR__ . '/Amplification/REST_Endpoint.php';
		require_once __DIR__ . '/Publish_Hook.php';

		Meta_Fields::init();
		Meta_Box::init();
		Post_Framing::init();
		Amplification\REST_Endpoint::init();
		Publish_Hook::init();

		// WP-CLI backfill command
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once __DIR__ . '/CLI/Backfill_Command.php';
			\WP_CLI::add_command( 'bw-social backfill', CLI\Backfill_Command::class );
		}

		// One-time migration: copy bw_* option values → scos_sma_* on first load
		add_action( 'admin_init', [ $this, 'maybe_migrate_options' ] );
	}

	/**
	 * Migrate legacy bw_* social options → scos_sma_* (runs once, non-destructive).
	 */
	public function maybe_migrate_options(): void {
		if ( get_option( 'scos_sma_options_migrated' ) ) {
			return;
		}

		$map = [
			'bw_social_webhook_url'       => 'scos_sma_webhook_url',
			'bw_social_webhook_enabled'   => 'scos_sma_webhook_enabled',
			'bw_yourls_api_url'           => 'scos_sma_yourls_url',
			'bw_yourls_signature'         => 'scos_sma_yourls_signature',
			'bw_yourls_username'          => 'scos_sma_yourls_username',
			'bw_yourls_password'          => 'scos_sma_yourls_password',
		];

		foreach ( $map as $old => $new ) {
			$existing = get_option( $old );
			if ( false !== $existing && '' !== $existing ) {
				// Only set if the new key doesn't already have a value
				if ( false === get_option( $new ) || '' === get_option( $new ) ) {
					update_option( $new, $existing, false );
				}
			}
		}

		update_option( 'scos_sma_options_migrated', '1', false );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Option helper: reads scos_sma_* with bw_* fallback
	// Used by Meta_Box and views/settings.php
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Get a Social Amplification option, preferring scos_sma_* over legacy bw_* keys.
	 *
	 * @param string $scos_key   e.g. 'scos_sma_webhook_url'
	 * @param string $legacy_key e.g. 'bw_social_webhook_url'
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function get_option( string $scos_key, string $legacy_key = '', $default = '' ) {
		$val = get_option( $scos_key, '' );
		if ( '' !== $val ) {
			return $val;
		}
		if ( $legacy_key ) {
			return get_option( $legacy_key, $default );
		}
		return $default;
	}

	public function render_settings() {
		include __DIR__ . '/views/settings.php';
	}
}
