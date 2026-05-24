<?php
/**
 * Business Info Module
 *
 * Migrates Business Info from brighter-core into the site-essentials module system.
 * Defines SCOS_BIZ_ACTIVE which causes brighter-business-info.php to use the
 * scos_biz_ option prefix and skip legacy hook registrations.
 *
 * Option keys: scos_biz_* (migrated from bw_* on first admin_init via scos_biz_run_migration())
 * Shortcodes:  [business_info setting="..."], [site_copyright] (still in brighter-core)
 *
 * @package    SiteEssentials
 * @subpackage Modules\BusinessInfo
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\BusinessInfo;

use SiteEssentials\Core\Module_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BusinessInfo_Module implements Module_Interface {

	public static function get_id() {
		return 'business_info';
	}

	public static function get_name() {
		return __( 'Business Info', 'site-essentials' );
	}

	public static function get_description() {
		return __( 'Central business identity, contact, media, and social info. Powers schema, shortcodes, and OG tags.', 'site-essentials' );
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
		if ( ! defined( 'SCOS_BIZ_ACTIVE' ) ) {
			define( 'SCOS_BIZ_ACTIVE', true );
		}

		// Register settings on admin_init — brighter-business-info.php's own hook
		// will also fire but will bail early due to the static gate in its function.
		add_action( 'admin_init', 'brighterweb_register_business_info_settings', 10 );
	}

	public function render_settings() {
		include __DIR__ . '/views/settings.php';
	}
}
