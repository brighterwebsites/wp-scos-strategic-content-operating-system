<?php
/**
 * Site Schema Module
 *
 * Manages site-wide JSON-LD schema templates (Local Business, Success Stories,
 * Product, Service) previously managed by brighter-core/bw-schema-admin.php.
 *
 * When active:
 * - Defines SCOS_SITE_SCHEMA_ACTIVE (hides legacy brighter-schema admin page)
 * - Stores schemas under scos_site_schema_* option keys
 * - Migrates existing bw_*_schema options on first admin_init
 * - Updates scos-schema-output.php reads via fallback in that file
 *
 * @package    SiteEssentials
 * @subpackage Modules\SiteSchema
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\SiteSchema;

use SiteEssentials\Core\Module_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteSchema_Module implements Module_Interface {

	public static function get_id() {
		return 'site_schema';
	}

	public static function get_name() {
		return __( 'Site Schema', 'site-essentials' );
	}

	public static function get_description() {
		return __( 'Site-wide JSON-LD schema templates: Local Business, Success Stories, Product, and Service.', 'site-essentials' );
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
		if ( ! defined( 'SCOS_SITE_SCHEMA_ACTIVE' ) ) {
			define( 'SCOS_SITE_SCHEMA_ACTIVE', true );
		}

		add_action( 'admin_init', [ __CLASS__, 'run_migration' ], 5 );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_save' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * One-time migration: copy bw_*_schema options to scos_site_schema_*.
	 */
	public static function run_migration() {
		if ( get_option( 'scos_site_schema_migration_done' ) ) {
			return;
		}
		$map = [
			'bw_local_business_schema'  => 'scos_site_schema_local_business',
			'bw_success_stories_schema' => 'scos_site_schema_success_stories',
			'bw_product_schema'         => 'scos_site_schema_product',
			'bw_service_schema'         => 'scos_site_schema_service',
			'bw_product_post_ids'       => 'scos_site_schema_product_ids',
			'bw_service_post_ids'       => 'scos_site_schema_service_ids',
		];
		foreach ( $map as $old => $new ) {
			$old_val = get_option( $old );
			if ( $old_val !== false && $old_val !== '' ) {
				if ( get_option( $new ) === false || get_option( $new ) === '' ) {
					update_option( $new, $old_val );
				}
			}
		}
		update_option( 'scos_site_schema_migration_done', '1' );
	}

	/**
	 * Register schema options.
	 */
	public static function register_settings() {
		$options = [
			'scos_site_schema_local_business',
			'scos_site_schema_success_stories',
			'scos_site_schema_product',
			'scos_site_schema_service',
		];
		foreach ( $options as $option ) {
			register_setting( 'scos_site_schema_group', $option, [
				'type'              => 'string',
				'sanitize_callback' => function( $v ) { return is_string( $v ) ? $v : ''; },
				'default'           => '',
			] );
		}
		register_setting( 'scos_site_schema_group', 'scos_site_schema_product_ids', [
			'type'              => 'string',
			'sanitize_callback' => [ __CLASS__, 'sanitize_post_ids' ],
			'default'           => '',
		] );
		register_setting( 'scos_site_schema_group', 'scos_site_schema_service_ids', [
			'type'              => 'string',
			'sanitize_callback' => [ __CLASS__, 'sanitize_post_ids' ],
			'default'           => '',
		] );
	}

	public static function sanitize_post_ids( $value ) {
		$value = wp_strip_all_tags( $value );
		$ids   = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY ) ) );
		return implode( ',', $ids );
	}

	/**
	 * Handle direct form saves (nonce-verified POST, not via Settings API).
	 */
	public static function handle_save() {
		if ( ! isset( $_POST['scos_site_schema_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scos_site_schema_nonce'] ) ), 'scos_site_schema_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$schema_keys = [
			'scos_site_schema_local_business',
			'scos_site_schema_success_stories',
			'scos_site_schema_product',
			'scos_site_schema_service',
		];
		foreach ( $schema_keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_option( $key, wp_unslash( trim( $_POST[ $key ] ) ) );
			}
		}
		if ( isset( $_POST['scos_site_schema_product_ids'] ) ) {
			update_option( 'scos_site_schema_product_ids', self::sanitize_post_ids( wp_unslash( $_POST['scos_site_schema_product_ids'] ) ) );
		}
		if ( isset( $_POST['scos_site_schema_service_ids'] ) ) {
			update_option( 'scos_site_schema_service_ids', self::sanitize_post_ids( wp_unslash( $_POST['scos_site_schema_service_ids'] ) ) );
		}

		wp_safe_redirect( add_query_arg( [ 'page' => 'site-essentials-schema', 'scos_schema_saved' => '1', 'tab' => isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'local-business' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Enqueue JSON validation JS on our schema page.
	 */
	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'site-essentials-schema' ) === false ) {
			return;
		}
		$asset_dir = plugin_dir_path( __FILE__ ) . 'assets/';
		$asset_url = plugin_dir_url( __FILE__ ) . 'assets/';
		wp_enqueue_script(
			'scos-site-schema-js',
			$asset_url . 'site-schema.js',
			[],
			file_exists( $asset_dir . 'site-schema.js' ) ? filemtime( $asset_dir . 'site-schema.js' ) : '1.0.0',
			true
		);
		// Pass business info data for the Generate button
		$biz_fields = function_exists( 'brighter_get_business_info_fields' ) ? brighter_get_business_info_fields() : [];
		$biz_data   = [];
		foreach ( $biz_fields as $field ) {
			$biz_data[ $field ] = function_exists( 'brighter_get_option' ) ? brighter_get_option( $field ) : get_option( 'scos_biz_' . $field, '' );
		}
		wp_localize_script( 'scos-site-schema-js', 'scosBizData', $biz_data );
		wp_localize_script( 'scos-site-schema-js', 'scosSiteSchema', [
			'homeUrl' => home_url( '/' ),
		] );
	}

	public function render_settings() {
		include __DIR__ . '/views/settings.php';
	}
}
