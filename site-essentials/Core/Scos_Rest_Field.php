<?php
/**
 * `scos` REST field — SCOS meta and Content Architecture terms, edit context only.
 *
 * The scos_* meta keys and the scos_topic / scos_content_cluster taxonomies are
 * registered with show_in_rest=false on purpose: flipping them would put
 * workflow labels (archive, merge, no_index) in public view-context responses
 * and add Gutenberg panels. External readers that already authenticate (the
 * CRM page sync, agents) get the same data from this one field instead:
 *
 *   GET /wp/v2/<type>?context=edit&_fields=id,scos
 *   → { "scos": { "meta": { scos_*: value }, "terms": { taxonomy: [slugs] } } }
 *
 * The field is declared for the edit context only and returns null unless the
 * current user can edit the post, so unauthenticated and view-context
 * responses never carry it.
 *
 * Lives in Core, not a module, so it is present on every site regardless of
 * which modules are enabled.
 *
 * @package    SiteEssentials
 * @subpackage Core
 *
 * v1.0 | 2026-10-08
 */

namespace SiteEssentials\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Scos_Rest_Field {

	const FIELD = 'scos';

	/**
	 * Meta keys exposed, in response order.
	 */
	const META_KEYS = [
		'scos_seo_title',
		'scos_seo_description',
		'scos_seo_breadcrumb_title',
		'scos_seo_tldr',
		'scos_seo_robots',
		'scos_seo_canonical',
		'scos_seo_sitemap_exclude',
		'scos_ca_index_status',
		'scos_ca_purpose',
		'scos_ca_intent',
		'scos_ca_maturity',
		'scos_ca_next_step',
		'scos_ca_optimization_progress',
		'scos_ca_word_count',
		'scos_ca_last_analyzed',
	];

	/**
	 * Taxonomies exposed as slug lists.
	 */
	const TAXONOMIES = [
		'scos_topic',
		'scos_content_cluster',
	];

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_field' ] );
	}

	/**
	 * Register the field on every public post type that is in the REST API.
	 */
	public static function register_field(): void {
		$post_types = get_post_types( [ 'public' => true, 'show_in_rest' => true ] );

		register_rest_field(
			array_values( $post_types ),
			self::FIELD,
			[
				'get_callback' => [ self::class, 'get_field' ],
				'schema'       => [
					'description' => 'SCOS SEO and Content Architecture meta plus topic/cluster term slugs. Edit context only.',
					'type'        => 'object',
					'context'     => [ 'edit' ],
					'readonly'    => true,
				],
			]
		);
	}

	/**
	 * REST get_callback.
	 *
	 * @param array $post Prepared post response data.
	 * @return array|null Null when the current user cannot edit the post.
	 */
	public static function get_field( array $post ): ?array {
		$post_id = isset( $post['id'] ) ? absint( $post['id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return null;
		}

		return self::get_post_data( $post_id );
	}

	/**
	 * SCOS meta and term slugs for one post. No capability check — callers
	 * outside REST (WP-CLI, abilities) must run their own.
	 *
	 * Meta and terms come from the object caches the REST posts query has
	 * already primed, so this adds no queries per item in a collection.
	 *
	 * @param int $post_id Post ID.
	 * @return array{meta: array<string, mixed>, terms: array<string, string[]>}
	 */
	public static function get_post_data( int $post_id ): array {
		$meta = [];
		foreach ( self::META_KEYS as $key ) {
			$meta[ $key ] = get_post_meta( $post_id, $key, true );
		}

		$terms = [];
		foreach ( self::TAXONOMIES as $taxonomy ) {
			$slugs              = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'slugs' ] );
			$terms[ $taxonomy ] = is_wp_error( $slugs ) ? [] : array_values( $slugs );
		}

		return [
			'meta'  => $meta,
			'terms' => $terms,
		];
	}
}
