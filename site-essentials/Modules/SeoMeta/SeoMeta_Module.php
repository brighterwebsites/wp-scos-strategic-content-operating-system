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
 * @version    1.0.0
 */

namespace SiteEssentials\Modules\SeoMeta;

use SiteEssentials\Core\Module_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SeoMeta_Module implements Module_Interface {

	public static function get_id() {
		return 'seo_meta';
	}

	public static function get_name() {
		return __( 'SEO Meta', 'site-essentials' );
	}

	public static function get_description() {
		return __( 'Per-page SEO meta fields: title, description, canonical, robots, TLDR, and breadcrumb label.', 'site-essentials' );
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
	 * @since 1.0.0
	 */
	public function init() {
		if ( ! defined( 'SCOS_SEO_ACTIVE' ) ) {
			define( 'SCOS_SEO_ACTIVE', true );
		}

		require_once __DIR__ . '/Meta_Fields.php';
		require_once __DIR__ . '/Meta_Box.php';
		require_once __DIR__ . '/Head_Output.php';

		Meta_Fields::init();
		Meta_Box::init();
		Head_Output::init();
	}

	public function render_settings() {
		include __DIR__ . '/views/settings.php';
	}
}
