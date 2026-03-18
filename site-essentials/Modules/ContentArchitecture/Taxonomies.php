<?php
/**
 * Content Architecture — Taxonomy Registration
 *
 * Registers scos_content_cluster and scos_topic as proper WordPress taxonomies
 * with meta_box_cb => false so only our single Content Architecture metabox
 * manages term assignment (via wp_set_post_terms). No WP default checkboxes.
 *
 * Timing: called at WordPress init priority 5.
 * Post type association is deferred to init priority 20 so all CPTs registered
 * at the default priority 10 are included.
 *
 * @package    SiteEssentials
 * @subpackage Modules\ContentArchitecture
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\ContentArchitecture;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Taxonomies {

	public static function init() {
		// Register the taxonomy objects now (priority 5).
		// We pass an empty post-type list and wire up the real post types at
		// priority 20 so all CPTs registered at the default priority (10) are
		// already available when we call register_taxonomy_for_object_type().
		self::register_taxonomies();

		// Associate with all supported public post types after they're registered.
		add_action( 'init', [ __CLASS__, 'associate_post_types' ], 20 );
	}

	/**
	 * Register both taxonomies with no post-type association yet.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_taxonomies() {
		register_taxonomy(
			'scos_content_cluster',
			[],
			[
				'labels'             => [
					'name'              => __( 'Content Clusters', 'site-essentials' ),
					'singular_name'     => __( 'Content Cluster', 'site-essentials' ),
					'menu_name'         => __( 'Content Clusters', 'site-essentials' ),
					'all_items'         => __( 'All Content Clusters', 'site-essentials' ),
					'edit_item'         => __( 'Edit Content Cluster', 'site-essentials' ),
					'view_item'         => __( 'View Content Cluster', 'site-essentials' ),
					'update_item'       => __( 'Update Content Cluster', 'site-essentials' ),
					'add_new_item'      => __( 'Add New Content Cluster', 'site-essentials' ),
					'new_item_name'     => __( 'New Content Cluster Name', 'site-essentials' ),
					'search_items'      => __( 'Search Content Clusters', 'site-essentials' ),
					'not_found'         => __( 'No content clusters found.', 'site-essentials' ),
				],
				'hierarchical'       => true,
				'show_ui'            => true,   // Enables edit-tags.php management pages.
				'meta_box_cb'        => false,  // No default sidebar boxes — our metabox handles this.
				'show_in_nav_menus'  => false,
				'show_tagcloud'      => false,
				'show_admin_column'  => false,  // Managed by Admin_Columns.
				'show_in_rest'       => false,
				'rewrite'            => [ 'slug' => 'content-cluster', 'with_front' => false ],
				'query_var'          => true,
			]
		);

		register_taxonomy(
			'scos_topic',
			[],
			[
				'labels'             => [
					'name'              => __( 'Topics', 'site-essentials' ),
					'singular_name'     => __( 'Topic', 'site-essentials' ),
					'menu_name'         => __( 'Topics', 'site-essentials' ),
					'all_items'         => __( 'All Topics', 'site-essentials' ),
					'edit_item'         => __( 'Edit Topic', 'site-essentials' ),
					'view_item'         => __( 'View Topic', 'site-essentials' ),
					'update_item'       => __( 'Update Topic', 'site-essentials' ),
					'add_new_item'      => __( 'Add New Topic', 'site-essentials' ),
					'new_item_name'     => __( 'New Topic Name', 'site-essentials' ),
					'search_items'      => __( 'Search Topics', 'site-essentials' ),
					'not_found'         => __( 'No topics found.', 'site-essentials' ),
				],
				'hierarchical'       => false,
				'show_ui'            => true,
				'meta_box_cb'        => false,
				'show_in_nav_menus'  => false,
				'show_tagcloud'      => false,
				'show_admin_column'  => false,
				'show_in_rest'       => false,
				'rewrite'            => [ 'slug' => 'topic', 'with_front' => false ],
				'query_var'          => true,
			]
		);
	}

	/**
	 * Associate both taxonomies with all supported public post types.
	 * Runs at init priority 20 so all CPTs are already registered.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function associate_post_types() {
		foreach ( self::get_post_types() as $post_type ) {
			register_taxonomy_for_object_type( 'scos_content_cluster', $post_type );
			register_taxonomy_for_object_type( 'scos_topic', $post_type );
		}
	}

	/**
	 * Get all public post types that Content Architecture applies to.
	 * Excludes WooCommerce, system types, and other non-editorial types.
	 *
	 * @since  1.0.0
	 * @return array Post type slugs.
	 */
	public static function get_post_types() {
		static $types = null;
		if ( null !== $types ) {
			return $types;
		}

		$exclude = [
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			// WooCommerce
			'product',
			'product_variation',
			'shop_order',
			'shop_coupon',
			'shop_webhook',
		];

		$all   = get_post_types( [ 'public' => true ], 'names' );
		$types = array_values( array_diff( $all, $exclude ) );

		return $types;
	}
}
