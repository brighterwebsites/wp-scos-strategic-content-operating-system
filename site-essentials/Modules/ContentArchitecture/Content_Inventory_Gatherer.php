<?php
/**
 * Content Inventory Gatherer — Single-pass content export
 *
 * Gathers full WordPress content inventory for analysis and reporting.
 * Supports incremental collection via ?since= parameter.
 *
 * Performance: One server-side pass with batch meta loading, no N+1 queries.
 *
 * v1.0 | 2026-06-06
 *
 * @package    SiteEssentials
 * @subpackage Modules\ContentArchitecture
 */

namespace SiteEssentials\Modules\ContentArchitecture;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Content_Inventory_Gatherer {

	/**
	 * Post types to skip (internal/system types).
	 */
	private const SKIP_POST_TYPES = [
		'attachment',
		'revision',
		'nav_menu_item',
		'custom_css',
		'customize_changeset',
		'oembed_cache',
		'user_request',
		'wp_block',
	];

	/**
	 * Gather full content inventory, optionally filtered by modification time.
	 *
	 * @param string|null $since ISO 8601 timestamp. If set, only return posts where
	 *                           MAX(post_modified, scos_ca_last_analyzed) >= since.
	 * @return array Inventory structure with meta and posts array.
	 */
	public static function gather( $since = null ) {
		global $wpdb;

		// ──────────────────────────────────────────────────────────────────────
		// Step 1: Determine which post types to include
		// ──────────────────────────────────────────────────────────────────────

		$all_post_types = get_post_types( [], 'objects' );
		$found          = [];
		$included       = [];
		$excluded       = [];

		foreach ( $all_post_types as $name => $obj ) {
			$found[] = $name;
		}

		foreach ( $all_post_types as $name => $obj ) {
			// Skip internal types.
			if ( in_array( $name, self::SKIP_POST_TYPES, true ) ) {
				$excluded[] = $name;
				continue;
			}

			// Only include post, page, or public types.
			$is_public = ! empty( $obj->public );
			if ( 'post' !== $name && 'page' !== $name && ! $is_public ) {
				$excluded[] = $name;
				continue;
			}

			// Check if there are published posts of this type.
			$counts = wp_count_posts( $name );
			$cnt    = isset( $counts->publish ) ? (int) $counts->publish : 0;
			if ( $cnt > 0 ) {
				$included[] = $name;
			} else {
				$excluded[] = $name;
			}
		}

		// ──────────────────────────────────────────────────────────────────────
		// Step 2: Determine analysis prefix (scos_ca_ or legacy bw_)
		// ──────────────────────────────────────────────────────────────────────

		$prefix = 'scos_ca_';

		// Check first published post of first included type to detect legacy vs SCOS.
		foreach ( $included as $pt ) {
			$q = get_posts(
				[
					'post_type'       => $pt,
					'post_status'     => 'publish',
					'numberposts'     => 1,
					'fields'          => 'ids',
					'suppress_filters' => true,
				]
			);

			if ( empty( $q ) ) {
				continue;
			}

			$pid = $q[0];

			if ( get_post_meta( $pid, 'scos_ca_last_analyzed', true ) ) {
				$prefix = 'scos_ca_';
			} elseif ( get_post_meta( $pid, 'bw_last_analyzed', true ) ) {
				$prefix = 'bw_';
			} else {
				$prefix = 'scos_ca_';
			}
			break;
		}

		// ──────────────────────────────────────────────────────────────────────
		// Step 3: Query posts with optional since filter
		// ──────────────────────────────────────────────────────────────────────

		$query_args = [
			'post_type'        => $included,
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'orderby'          => 'modified',
			'order'            => 'DESC',
			'suppress_filters' => true,
		];

		// Build where clause for since filter if provided.
		if ( ! empty( $since ) ) {
			$since_sanitized = gmdate( 'Y-m-d H:i:s', strtotime( $since ) );
			$query_args['meta_query'] = [
				'relation' => 'OR',
				[
					'key'     => $prefix . 'last_analyzed',
					'value'   => $since_sanitized,
					'compare' => '>=',
					'type'    => 'DATETIME',
				],
			];

			// We also need posts where post_modified >= since.
			// WP_Query doesn't have a built-in post_modified >= filter,
			// so we'll filter manually below after fetching all posts.
		}

		$posts = get_posts( $query_args );

		// ──────────────────────────────────────────────────────────────────────
		// Step 4: Batch-load all meta for N+1 prevention
		// ──────────────────────────────────────────────────────────────────────

		$post_ids = wp_list_pluck( $posts, 'ID' );
		if ( ! empty( $post_ids ) ) {
			update_meta_cache( 'post', $post_ids );
		}

		// ──────────────────────────────────────────────────────────────────────
		// Step 5: Build inventory for each post
		// ──────────────────────────────────────────────────────────────────────

		$posts_out = [];
		$is_bw     = 'bw_' === $prefix;

		foreach ( $posts as $post ) {
			$pid = $post->ID;

			// Apply since filter on post_modified if provided.
			if ( ! empty( $since ) ) {
				$since_sanitized = gmdate( 'Y-m-d H:i:s', strtotime( $since ) );
				$post_modified   = $post->post_modified;
				$last_analyzed   = get_post_meta( $pid, $prefix . 'last_analyzed', true );

				// Only include if post_modified >= since OR last_analyzed >= since.
				if ( $post_modified < $since_sanitized && $last_analyzed < $since_sanitized ) {
					continue;
				}
			}

			$slug            = $post->post_name;
			$last_analyzed   = get_post_meta( $pid, $prefix . 'last_analyzed', true );
			$analysis_status = $last_analyzed ? 'complete' : 'pending';

			// Get taxonomy terms.
			$cluster_terms = wp_get_object_terms( $pid, 'scos_content_cluster', [ 'fields' => 'names' ] );
			$cluster       = ( ! is_wp_error( $cluster_terms ) && ! empty( $cluster_terms ) ) ? implode( ', ', $cluster_terms ) : '';

			$topic_terms = wp_get_object_terms( $pid, 'scos_topic', [ 'fields' => 'names' ] );
			$topic       = ( ! is_wp_error( $topic_terms ) && ! empty( $topic_terms ) ) ? implode( ', ', $topic_terms ) : '';

			// Build URLs.
			$rel             = '';
			$pl              = get_permalink( $pid );
			if ( $pl && ! is_wp_error( $pl ) ) {
				$rel = wp_make_link_relative( $pl );
			}
			if ( '' === $rel && $slug ) {
				$rel = '/' . $slug . '/';
			}

			$production_domain = get_option( 'siteurl' );
			$production_url    = $rel ? ( rtrim( $production_domain, '/' ) . $rel ) : null;
			$gsc_url           = $production_url
				? 'https://search.google.com/search-console/performance/search-analytics?resource_id=' . urlencode( $production_url )
				: null;
			$ga4_path          = $rel ?: null;

			// Helper: convert empty/null values to null.
			$nn = function( $v ) {
				return ( '' === $v || null === $v ) ? null : $v;
			};

			$posts_out[] = [
				'id'                           => (int) $pid,
				'title'                        => $post->post_title,
				'slug'                         => $slug,
				'post_type'                    => $post->post_type,
				'post_date'                    => $post->post_date,
				'post_modified'                => $post->post_modified,
				'analysis_status'              => $analysis_status,
				'word_count'                   => $nn( get_post_meta( $pid, $prefix . 'word_count', true ) ),
				'h2_count'                     => $nn( get_post_meta( $pid, $prefix . 'h2_count', true ) ),
				'image_count'                  => $nn( get_post_meta( $pid, $prefix . 'image_count', true ) ),
				'reading_time'                 => $nn( get_post_meta( $pid, $prefix . 'reading_time', true ) ),
				'internal_link_count'          => $nn( get_post_meta( $pid, $prefix . 'links_to_internal', true ) ),
				'external_link_count'          => $nn( get_post_meta( $pid, $prefix . 'links_to_external', true ) ),
				'last_analyzed'                => $nn( $last_analyzed ),
				'scos_ca_intent'               => $is_bw ? null : $nn( get_post_meta( $pid, 'scos_ca_intent', true ) ),
				'scos_ca_purpose'              => $is_bw ? null : $nn( get_post_meta( $pid, 'scos_ca_purpose', true ) ),
				'scos_ca_maturity'             => $is_bw ? null : $nn( get_post_meta( $pid, 'scos_ca_maturity', true ) ),
				'scos_ca_index_status'         => $is_bw ? null : $nn( get_post_meta( $pid, 'scos_ca_index_status', true ) ),
				'scos_ca_optimization_progress' => $is_bw ? null : $nn( get_post_meta( $pid, 'scos_ca_optimization_progress', true ) ),
				'scos_ca_next_step'            => $is_bw ? null : $nn( get_post_meta( $pid, 'scos_ca_next_step', true ) ),
				'scos_seo_title'               => $nn( get_post_meta( $pid, 'scos_seo_title', true ) ),
				'scos_seo_description'         => $nn( get_post_meta( $pid, 'scos_seo_description', true ) ),
				'scos_seo_robots'              => $nn( get_post_meta( $pid, 'scos_seo_robots', true ) ),
				'scos_seo_canonical'           => $nn( get_post_meta( $pid, 'scos_seo_canonical', true ) ),
				'scos_seo_breadcrumb_title'    => $nn( get_post_meta( $pid, 'scos_seo_breadcrumb_title', true ) ),
				'cluster'                      => $nn( $cluster ),
				'topic'                        => $nn( $topic ),
				'production_url'               => $production_url,
				'gsc_url'                      => $gsc_url,
				'ga4_path'                     => $ga4_path,
			];
		}

		// ──────────────────────────────────────────────────────────────────────
		// Step 6: Build response payload
		// ──────────────────────────────────────────────────────────────────────

		$total           = count( $posts_out );
		$pending_count   = 0;
		foreach ( $posts_out as $p ) {
			if ( 'pending' === $p['analysis_status'] ) {
				$pending_count++;
			}
		}
		$complete_count = $total - $pending_count;

		return [
			'meta' => [
				'collected_at'              => current_time( 'c' ),
				'total_posts_included'      => $total,
				'analysis_complete_count'   => $complete_count,
				'analysis_pending_count'    => $pending_count,
				'content_analysis_prefix'   => $prefix,
				'wp_post_types_found'       => $found,
				'wp_post_types_included'    => $included,
				'wp_post_types_excluded'    => $excluded,
			],
			'posts' => $posts_out,
		];
	}
}
