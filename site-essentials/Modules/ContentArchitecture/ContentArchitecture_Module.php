<?php
/**
 * Content Architecture Module
 *
 * Provides content strategy, classification, workflow tracking, and content
 * analysis for all public post types. Replaces the legacy brighter-core
 * ALTC + content-strategy system with clean scos_ca_* meta keys and proper
 * WordPress taxonomy relationships.
 *
 * @package    SiteEssentials
 * @subpackage Modules\ContentArchitecture
 * @version    1.1.0
 * @since      1.0.0
 *
 * v1.1 | 2026-05-22 — Load Intent_Goal_Resolver.
 */

namespace SiteEssentials\Modules\ContentArchitecture;

use SiteEssentials\Core\Module_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ContentArchitecture_Module implements Module_Interface {

	public static function get_id() {
		return 'content_architecture';
	}

	public static function get_name() {
		return __( 'Content Architecture', 'site-essentials' );
	}

	public static function get_description() {
		return __( 'Content strategy, cluster/topic taxonomies, workflow tracking, and content analysis for all post types.', 'site-essentials' );
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

	/**
	 * Initialize the module.
	 *
	 * Called at WordPress init priority 5 by the Module_Loader.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {
		// Signal to legacy modules that the new CA module is active.
		// This constant is checked in brighter-core to prevent duplicate UI.
		if ( ! defined( 'SCOS_CA_ACTIVE' ) ) {
			define( 'SCOS_CA_ACTIVE', true );
		}

		// Load sub-components (PSR-4 autoloader also resolves these, but explicit
		// require_once makes load order clear).
		require_once __DIR__ . '/Taxonomies.php';
		require_once __DIR__ . '/Meta_Fields.php';
		require_once __DIR__ . '/Intent_Goal_Resolver.php';
		require_once __DIR__ . '/Meta_Box.php';
		require_once __DIR__ . '/Content_Analysis.php';
		require_once __DIR__ . '/Admin_Columns.php';
		require_once __DIR__ . '/Admin_Menu.php';
		require_once __DIR__ . '/CA_Defaults.php';

		Taxonomies::init();
		Meta_Fields::init();
		Meta_Box::init();
		Content_Analysis::init();
		Admin_Columns::init();
		Admin_Menu::init();
		CA_Defaults::init();

		// Register WP-CLI commands
		$this->register_cli_commands();
	}

	/**
	 * Register WP-CLI commands for Content Architecture.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function register_cli_commands() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		require_once __DIR__ . '/CLI/Content_Inventory_Command.php';

		\WP_CLI::add_command(
			'scos content-inventory',
			CLI\Content_Inventory_Command::class,
			[
				'shortdesc' => 'Gather WordPress content inventory with analysis metadata.',
				'longdesc'  => 'Collects all published posts/pages with analysis metadata, taxonomies, and URLs. ' .
					'Supports incremental collection via --since.',
			]
		);
	}

	/**
	 * Render settings section for the Site Essentials settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings() {
		include __DIR__ . '/views/settings.php';
	}
}
