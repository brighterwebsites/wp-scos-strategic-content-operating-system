<?php
/**
 * Revision Viewer Module
 *
 * Front-end revision browser for editors and admins.
 * Activates on posts/pages whose scos_ca_next_step is "revise" or "review".
 * Zero additional meta keys — reads directly from WordPress core revisions.
 *
 * @package    SiteEssentials
 * @subpackage Modules\RevisionViewer
 * @since      1.0.0
 *
 * v1.0 | 2026-06-10
 */

namespace SiteEssentials\Modules\RevisionViewer;

use SiteEssentials\Core\Module_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevisionViewer_Module implements Module_Interface {

	public static function get_id() {
		return 'revision_viewer';
	}

	public static function get_name() {
		return __( 'Revision Viewer', 'site-essentials' );
	}

	public static function get_description() {
		return __( 'Browse rendered front-end revisions via a floating panel. Activates only on posts/pages with workflow status set to Revise or Review.', 'site-essentials' );
	}

	public static function get_tier() {
		return 'pro';
	}

	public static function get_dependencies() {
		return [ 'content_architecture' ];
	}

	public static function get_version() {
		return '1.0.0';
	}

	public function init() {
		require_once __DIR__ . '/Revision_Viewer.php';
		Revision_Viewer::init();
	}

	public function render_settings() {
		// No configurable settings — activation is driven entirely by scos_ca_next_step.
		echo '<p class="description">' . esc_html__( 'Revision Viewer is active. It appears automatically on any post or page whose Content Architecture workflow status is set to Revise or Review.', 'site-essentials' ) . '</p>';
	}
}
