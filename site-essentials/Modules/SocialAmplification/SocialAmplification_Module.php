<?php
/**
 * Social Amplification Module
 *
 * Per-page social amplification panel:
 *  - YOURLS shortlink slug  (scos_sa_shortlink_slug)
 *  - "Create Social Post" button (triggers existing bw_trigger_social_webhook AJAX action)
 *  - Webhook status / last trigger time
 *
 * No platform-specific suffix tracking (no -fb, -ig etc.).
 * No social platform type fields (Phase 2 if needed).
 *
 * Defines SCOS_SA_ACTIVE to gate legacy BW_Social_Webhook_Manual meta box and
 * its admin columns so they don't duplicate the new UI.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification
 * @version    1.0.0
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
		return __( 'YOURLS shortlink slug and manual Make.com webhook trigger for social post creation.', 'site-essentials' );
	}

	public static function get_tier() {
		return 'pro';
	}

	public static function get_dependencies() {
		return [];
	}

	public static function get_version() {
		return '1.0.0';
	}

	public function init() {
		if ( ! defined( 'SCOS_SA_ACTIVE' ) ) {
			define( 'SCOS_SA_ACTIVE', true );
		}

		require_once __DIR__ . '/Meta_Fields.php';
		require_once __DIR__ . '/Meta_Box.php';

		Meta_Fields::init();
		Meta_Box::init();
	}

	public function render_settings() {
		include __DIR__ . '/views/settings.php';
	}
}
