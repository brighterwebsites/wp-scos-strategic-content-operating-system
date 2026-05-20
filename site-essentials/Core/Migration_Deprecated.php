<?php
/**
 * Retired SCOS field migration tool (Tools → SCOS Migration).
 *
 * Legacy scos-migration.php in mu-plugins registered wp-admin/tools.php?page=scos-migration.
 * All client sites are migrated; this class hides the menu and blocks re-runs.
 *
 * @package    SiteEssentials
 * @subpackage Core
 * @version    1.0
 * @since      1.0.0
 */

namespace SiteEssentials\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deprecation guard for the one-time bw_* → scos_* migration admin UI.
 */
class Migration_Deprecated {

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_init', [ __CLASS__, 'block_legacy_post' ], 0 );
		add_action( 'admin_menu', [ __CLASS__, 'remove_tools_menu' ], 999 );
		add_action( 'load-tools_page_scos-migration', [ __CLASS__, 'redirect_deprecated_page' ], 1 );
	}

	/**
	 * Block POST actions if a legacy scos-migration.php mu-plugin is still loaded.
	 *
	 * @return void
	 */
	public static function block_legacy_post(): void {
		if ( empty( $_POST['scos_migration_action'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_die(
			esc_html__(
				'The SCOS field migration tool has been retired. All sites use the new scos_* meta keys. Remove scos-migration.php from mu-plugins if it is still present.',
				'site-essentials'
			),
			esc_html__( 'Migration tool retired', 'site-essentials' ),
			[ 'response' => 410, 'back_link' => true ]
		);
	}

	/**
	 * Remove Tools submenu entry (bookmark/direct URL handled separately).
	 *
	 * @return void
	 */
	public static function remove_tools_menu(): void {
		remove_submenu_page( 'tools.php', 'scos-migration' );
	}

	/**
	 * Redirect direct visits to the retired migration screen.
	 *
	 * @return void
	 */
	public static function redirect_deprecated_page(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=' . Admin_UI::PAGE_SLUG ) );
		exit;
	}
}
