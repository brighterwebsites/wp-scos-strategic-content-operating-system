<?php
/**
 * SEO Schema Module
 *
 * Per-page custom JSON-LD schema input with client-side validation.
 *
 * Phase 1: Single textarea for custom JSON-LD override.
 * Phase 2: Schema type selector, templating, global/post-type defaults.
 *
 * Stores in scos_schema_custom. Dual-writes to bw_custom_schema so the
 * existing scos-schema-output.php continues to inject schema into <head>
 * without modification.
 *
 * Defines SCOS_SCHEMA_ACTIVE to gate the legacy bw_schema_settings metabox
 * (Schema_Meta_Box in SiteEssentials\Modules\Seo).
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoSchema
 * @version    1.0.0
 */

namespace SiteEssentials\Modules\SeoSchema;

use SiteEssentials\Core\Module_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SeoSchema_Module implements Module_Interface {

	public static function get_id() {
		return 'seo_schema';
	}

	public static function get_name() {
		return __( 'SEO Schema', 'site-essentials' );
	}

	public static function get_description() {
		return __( 'Custom JSON-LD schema input per post/page with live validation.', 'site-essentials' );
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
		if ( ! defined( 'SCOS_SCHEMA_ACTIVE' ) ) {
			define( 'SCOS_SCHEMA_ACTIVE', true );
		}

		require_once __DIR__ . '/Meta_Box.php';
		Meta_Box::init();
	}

	public function render_settings() {
		include __DIR__ . '/views/settings.php';
	}
}
