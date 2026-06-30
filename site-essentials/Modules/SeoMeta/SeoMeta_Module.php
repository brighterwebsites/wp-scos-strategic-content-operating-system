<?php
/**
 * SEO Meta Module
 *
 * Per-page SEO metabox with three tabs:
 *  - Core SEO   : Breadcrumb Title, TLDR, Meta Title (60 char), Meta Description (160 char)
 *  - Advanced   : Canonical URL, Meta Robots directives, Sitemap exclusions
 *  - OG Social  : Planned Phase 2 (placeholder)
 *
 * Stores authoritative data in scos_seo_* meta keys.
 * Dual-writes to SEOPress / legacy keys on save so Airtable sync, the [tldr]
 * shortcode, and SEOPress frontend output continue working without changes.
 *
 * Absorbs:
 *  - BW_TLDR_Meta_Box    (bw_tldr)
 *  - BW_Breadcrumbs_Meta (_bw_breadcrumb, _seopress_robots_breadcrumbs)
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 * @version    1.1.0
 *
 * v1.1 | 2026-06-30 — Admin columns (SEO Title, SEO Desc) + Quick Edit for Title, Description, Breadcrumb.
 */

namespace SiteEssentials\Modules\SeoMeta;

use SiteEssentials\Core\Module_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SeoMeta_Module implements Module_Interface {

	/** @var bool Prevent double init when both Seo_Module and legacy paths call bootstrap. */
	private static $features_bootstrapped = false;

	public static function get_id() {
		return 'seo_meta';
	}

	public static function get_name() {
		return __( 'SEO Meta', 'site-essentials' );
	}

	public static function get_description() {
		return __( 'Bootstrapped by the SEO Module (not a separate toggle).', 'site-essentials' );
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
	 * Load SEO meta / archive / head features (invoked from Seo_Module when the SEO Module is enabled).
	 *
	 * @since 1.0.0
	 */
	public static function bootstrap_features() {
		if ( self::$features_bootstrapped ) {
			return;
		}
		self::$features_bootstrapped = true;

		require_once __DIR__ . '/Meta_Fields.php';
		require_once __DIR__ . '/Meta_Box.php';
		require_once __DIR__ . '/Head_Output.php';
		require_once __DIR__ . '/Archive_Settings.php';
		require_once __DIR__ . '/Image_SEO.php';
		require_once __DIR__ . '/Virtual_Files.php';
		require_once __DIR__ . '/Exif_Stripper.php';
		require_once __DIR__ . '/Redirections.php';
		require_once __DIR__ . '/Author_SEO.php';
		require_once __DIR__ . '/Admin_Columns.php';

		Meta_Fields::init();
		Meta_Box::init();
		Head_Output::init();
		Archive_Settings::init();
		Image_SEO::init();
		Virtual_Files::init();
		Exif_Stripper::init();
		Redirections::init();
		Author_SEO::init();
		Admin_Columns::init();
	}

	/**
	 * @since 1.0.0
	 */
	public function init() {
		self::bootstrap_features();
	}

	public function render_settings() {
		include __DIR__ . '/views/settings.php';
	}
}
