<?php
/**
 * SCOS Field Migration Tool
 *
 * Migrates legacy brighter-core (bw_* / altc_* / _seopress_*) fields to
 * the new site-essentials scos_* meta keys and taxonomies.
 *
 * Place in the mu-plugins root. REMOVE THIS FILE once migration is complete.
 *
 * @package BrighterWebsites
 */

defined( 'ABSPATH' ) || exit;

// ──────────────────────────────────────────────────────────────────────────────
// Boot
// ──────────────────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'scos_migration_add_page' );
add_action( 'admin_init', 'scos_migration_handle_post' );

function scos_migration_add_page(): void {
	add_management_page(
		'SCOS Field Migration',
		'⚠ SCOS Migration',
		'manage_options',
		'scos-migration',
		'scos_migration_render_page'
	);
}

// ──────────────────────────────────────────────────────────────────────────────
// Post handler
// ──────────────────────────────────────────────────────────────────────────────

function scos_migration_handle_post(): void {
	if ( empty( $_POST['scos_migration_action'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}
	check_admin_referer( 'scos_migration', 'scos_migration_nonce' );

	$action = sanitize_key( $_POST['scos_migration_action'] );

	switch ( $action ) {
		case 'dry_run_ca':
			set_transient( 'scos_dry_run_ca', scos_migration_dry_run( 'ca' ), 120 );
			break;

		case 'run_ca':
			set_transient( 'scos_migration_ca_result', scos_migration_run_ca(), 120 );
			update_option( 'scos_migration_ca_done', current_time( 'mysql' ) );
			break;

		case 'reset_ca':
			delete_option( 'scos_migration_ca_done' );
			delete_transient( 'scos_migration_ca_result' );
			break;

		case 'dry_run_seo':
			set_transient( 'scos_dry_run_seo', scos_migration_dry_run( 'seo' ), 120 );
			break;

		case 'run_seo':
			set_transient( 'scos_migration_seo_result', scos_migration_run_seo(), 120 );
			update_option( 'scos_migration_seo_done', current_time( 'mysql' ) );
			break;

		case 'reset_seo':
			delete_option( 'scos_migration_seo_done' );
			delete_transient( 'scos_migration_seo_result' );
			break;

		case 'dry_run_stats':
			set_transient( 'scos_dry_run_stats', scos_migration_dry_run( 'stats' ), 120 );
			break;

		case 'run_stats':
			set_transient( 'scos_migration_stats_result', scos_migration_run_stats(), 120 );
			update_option( 'scos_migration_stats_done', current_time( 'mysql' ) );
			break;

		case 'reset_stats':
			delete_option( 'scos_migration_stats_done' );
			delete_transient( 'scos_migration_stats_result' );
			break;
	}

	wp_safe_redirect( add_query_arg( 'page', 'scos-migration', admin_url( 'tools.php' ) ) );
	exit;
}

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Return all post types that participate in content architecture.
 * Uses SiteEssentials\Taxonomies::get_post_types() when available;
 * falls back to all public post types minus WP internals.
 *
 * @return string[]
 */
function scos_migration_post_types(): array {
	if ( class_exists( 'SiteEssentials\\Modules\\ContentArchitecture\\Taxonomies' ) ) {
		return \SiteEssentials\Modules\ContentArchitecture\Taxonomies::get_post_types();
	}
	$exclude = [
		'attachment', 'revision', 'nav_menu_item', 'custom_css',
		'customize_changeset', 'oembed_cache', 'user_request',
		'wp_block', 'wp_template', 'wp_template_part',
		'wp_global_styles', 'wp_navigation',
	];
	return array_values( array_diff( get_post_types( [ 'public' => true ], 'names' ), $exclude ) );
}

/**
 * Get all post IDs across CA post types.
 *
 * @return int[]
 */
function scos_migration_get_all_post_ids(): array {
	return get_posts( [
		'post_type'      => scos_migration_post_types(),
		'post_status'    => [ 'publish', 'draft', 'private', 'pending', 'future' ],
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	] );
}

/**
 * Map old maturity slug to the new vocabulary.
 * Old: entry | learner | practitioner | professional | expert | thought_leader | industry_authority
 * New: entry | professional | expert | thought_leader | industry_authority
 */
function scos_migration_remap_maturity( string $old ): string {
	$map = [
		'learner'      => 'entry',        // promoted down to Entry
		'practitioner' => 'professional', // closest equivalent
	];
	return $map[ $old ] ?? $old;
}

/**
 * Find an existing term in $taxonomy by slug or name, or create it.
 *
 * @return int|WP_Error  Term ID on success.
 */
function scos_migration_find_or_create_term( string $name, string $slug, string $taxonomy ) {
	$existing = get_term_by( 'slug', $slug, $taxonomy );
	if ( $existing && ! is_wp_error( $existing ) ) {
		return (int) $existing->term_id;
	}
	$existing = get_term_by( 'name', $name, $taxonomy );
	if ( $existing && ! is_wp_error( $existing ) ) {
		return (int) $existing->term_id;
	}
	$result = wp_insert_term( $name, $taxonomy, [ 'slug' => $slug ] );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	return (int) $result['term_id'];
}

// ──────────────────────────────────────────────────────────────────────────────
// Dry-run: count affected posts
// ──────────────────────────────────────────────────────────────────────────────

function scos_migration_dry_run( string $type ): array {
	$ids    = scos_migration_get_all_post_ids();
	$counts = [];

	if ( 'ca' === $type ) {
		$fields = [
			'Cluster (altc_strategic_lens → scos_content_cluster)' => [ 'old' => 'bw_primary_altc_id',    'new_tax' => 'scos_content_cluster' ],
			'Topic (altc_topic → scos_topic)'                       => [ 'old' => 'bw_primary_topic_id',   'new_tax' => 'scos_topic' ],
			'Maturity (bw_cont_maturity)'                           => [ 'old' => 'bw_cont_maturity',      'new_meta' => 'scos_ca_maturity' ],
			'Purpose (bw_purpose)'                                  => [ 'old' => 'bw_purpose',            'new_meta' => 'scos_ca_purpose' ],
			'Intent (bw_intent)'                                    => [ 'old' => 'bw_intent',             'new_meta' => 'scos_ca_intent' ],
			'Pillar Page (bw_pillar_page_id)'                       => [ 'old' => 'bw_pillar_page_id',     'new_meta' => 'scos_ca_pillar_page_id' ],
			'Service Pathway (bw_service_pathway_id)'               => [ 'old' => 'bw_service_pathway_id', 'new_meta' => 'scos_ca_service_pathway_id' ],
			'Index Status (bw_index_status)'                        => [ 'old' => 'bw_index_status',       'new_meta' => 'scos_ca_index_status' ],
			'Optimization Progress (workflow_progress)'             => [ 'old' => 'workflow_progress',     'new_meta' => 'scos_ca_optimization_progress' ],
			'Next Step (content_plan)'                              => [ 'old' => 'content_plan',          'new_meta' => 'scos_ca_next_step' ],
			'Intent Goal / Notes (bw_altc_notes)'                   => [ 'old' => 'bw_altc_notes',         'new_meta' => 'scos_ca_intent_goal' ],
		];
	} elseif ( 'seo' === $type ) {
		$fields = [
			'Breadcrumb Title – primary (bw_custom_schema)'               => [ 'old' => 'bw_custom_schema',              'new_meta' => 'scos_seo_breadcrumb_title' ],
			'Breadcrumb Title – fallback (_seopress_robots_breadcrumbs)'  => [ 'old' => '_seopress_robots_breadcrumbs',   'new_meta' => 'scos_seo_breadcrumb_title' ],
			'Per-page Schema JSON-LD (bw_breadcrumb_schema)'              => [ 'old' => 'bw_breadcrumb_schema',           'new_meta' => 'scos_schema_custom' ],
			'Shortlink Slug (_bw_breadcrumb)'                             => [ 'old' => '_bw_breadcrumb',                 'new_meta' => 'scos_sa_shortlink_slug' ],
			'SEO Title (_seopress_titles_title)'                          => [ 'old' => '_seopress_titles_title',          'new_meta' => 'scos_seo_title' ],
			'SEO Description (_seopress_titles_desc)'                     => [ 'old' => '_seopress_titles_desc',           'new_meta' => 'scos_seo_description' ],
			'Robots – noindex (_seopress_robots_index)'                   => [ 'old' => '_seopress_robots_index',          'new_meta' => 'scos_seo_robots' ],
			'Canonical URL (_seopress_robots_canonical)'                  => [ 'old' => '_seopress_robots_canonical',      'new_meta' => 'scos_seo_canonical' ],
		];
	} else {
		// stats
		$fields = [
			'Word Count (bw_word_count)'                => [ 'old' => 'bw_word_count',          'new_meta' => 'scos_ca_word_count' ],
			'Image Count (bw_image_count)'              => [ 'old' => 'bw_image_count',         'new_meta' => 'scos_ca_image_count' ],
			'H2 Count (bw_h2_count)'                    => [ 'old' => 'bw_h2_count',            'new_meta' => 'scos_ca_h2_count' ],
			'Internal Link Count (bw_internal_link_count)' => [ 'old' => 'bw_internal_link_count', 'new_meta' => 'scos_ca_links_to_internal' ],
			'Last Analysed (_bw_last_analyzed)'         => [ 'old' => '_bw_last_analyzed',      'new_meta' => 'scos_ca_last_analyzed' ],
		];
	}

	foreach ( $fields as $label => $cfg ) {
		$with_source   = 0;
		$already_done  = 0;
		$would_migrate = 0;

		foreach ( $ids as $post_id ) {
			$old_val = get_post_meta( $post_id, $cfg['old'], true );
			if ( $old_val === '' || $old_val === false || $old_val === null ) {
				continue;
			}
			$with_source++;

			if ( isset( $cfg['new_meta'] ) ) {
				$new_val = get_post_meta( $post_id, $cfg['new_meta'], true );
				if ( $new_val !== '' && $new_val !== false && $new_val !== null ) {
					$already_done++;
				} else {
					$would_migrate++;
				}
			} elseif ( isset( $cfg['new_tax'] ) ) {
				$terms = wp_get_post_terms( $post_id, $cfg['new_tax'] );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$already_done++;
				} else {
					$would_migrate++;
				}
			}
		}

		$counts[ $label ] = [
			'source'  => $with_source,
			'done'    => $already_done,
			'migrate' => $would_migrate,
		];
	}

	return $counts;
}

// ──────────────────────────────────────────────────────────────────────────────
// Run: Content Architecture migration
// ──────────────────────────────────────────────────────────────────────────────

function scos_migration_run_ca(): array {
	$ids    = scos_migration_get_all_post_ids();
	$result = [
		'cluster'   => [ 'migrated' => 0, 'skipped' => 0, 'error' => [] ],
		'topic'     => [ 'migrated' => 0, 'skipped' => 0, 'error' => [] ],
		'maturity'  => [ 'migrated' => 0, 'skipped' => 0, 'remapped' => 0 ],
		'purpose'   => [ 'migrated' => 0, 'skipped' => 0 ],
		'intent'    => [ 'migrated' => 0, 'skipped' => 0 ],
		'pillar'    => [ 'migrated' => 0, 'skipped' => 0 ],
		'pathway'   => [ 'migrated' => 0, 'skipped' => 0 ],
		'index'     => [ 'migrated' => 0, 'skipped' => 0 ],
		'progress'  => [ 'migrated' => 0, 'skipped' => 0 ],
		'next_step' => [ 'migrated' => 0, 'skipped' => 0 ],
		'notes'     => [ 'migrated' => 0, 'skipped' => 0 ],
	];

	// Old intent option slugs were identical to new — no remap needed.
	// Old index_status slugs differ slightly; map known divergences.
	$index_remap = [
		'not_indexed' => 'no_index',
		'pending'     => 'requested',
	];

	foreach ( $ids as $post_id ) {

		// ── Cluster ──
		$old_altc_id = (int) get_post_meta( $post_id, 'bw_primary_altc_id', true );
		if ( $old_altc_id ) {
			$existing = wp_get_post_terms( $post_id, 'scos_content_cluster' );
			if ( is_wp_error( $existing ) || empty( $existing ) ) {
				$old_term = get_term( $old_altc_id, 'altc_strategic_lens' );
				if ( $old_term && ! is_wp_error( $old_term ) ) {
					$new_id = scos_migration_find_or_create_term( $old_term->name, $old_term->slug, 'scos_content_cluster' );
					if ( is_wp_error( $new_id ) ) {
						$result['cluster']['error'][] = "Post {$post_id}: " . $new_id->get_error_message();
					} else {
						wp_set_post_terms( $post_id, [ $new_id ], 'scos_content_cluster' );
						$result['cluster']['migrated']++;
					}
				}
			} else {
				$result['cluster']['skipped']++;
			}
		}

		// ── Topic ──
		$old_topic_id = (int) get_post_meta( $post_id, 'bw_primary_topic_id', true );
		if ( $old_topic_id ) {
			$existing = wp_get_post_terms( $post_id, 'scos_topic' );
			if ( is_wp_error( $existing ) || empty( $existing ) ) {
				$old_term = get_term( $old_topic_id, 'altc_topic' );
				if ( $old_term && ! is_wp_error( $old_term ) ) {
					$new_id = scos_migration_find_or_create_term( $old_term->name, $old_term->slug, 'scos_topic' );
					if ( is_wp_error( $new_id ) ) {
						$result['topic']['error'][] = "Post {$post_id}: " . $new_id->get_error_message();
					} else {
						wp_set_post_terms( $post_id, [ $new_id ], 'scos_topic' );
						$result['topic']['migrated']++;
					}
				}
			} else {
				$result['topic']['skipped']++;
			}
		}

		// ── Maturity ──
		$old_mat = get_post_meta( $post_id, 'bw_cont_maturity', true );
		if ( $old_mat !== '' && $old_mat !== false ) {
			$new_mat = get_post_meta( $post_id, 'scos_ca_maturity', true );
			if ( empty( $new_mat ) ) {
				$remapped = scos_migration_remap_maturity( $old_mat );
				update_post_meta( $post_id, 'scos_ca_maturity', $remapped );
				$result['maturity']['migrated']++;
				if ( $remapped !== $old_mat ) {
					$result['maturity']['remapped']++;
				}
			} else {
				$result['maturity']['skipped']++;
			}
		}

		// ── Purpose ──
		scos_migration_copy_meta( $post_id, 'bw_purpose', 'scos_ca_purpose', $result['purpose'] );

		// ── Intent ──
		scos_migration_copy_meta( $post_id, 'bw_intent', 'scos_ca_intent', $result['intent'] );

		// ── Pillar Page ──
		scos_migration_copy_meta( $post_id, 'bw_pillar_page_id', 'scos_ca_pillar_page_id', $result['pillar'] );

		// ── Service Pathway ──
		scos_migration_copy_meta( $post_id, 'bw_service_pathway_id', 'scos_ca_service_pathway_id', $result['pathway'] );

		// ── Index Status ──
		$old_index = get_post_meta( $post_id, 'bw_index_status', true );
		if ( ! empty( $old_index ) ) {
			$new_index = get_post_meta( $post_id, 'scos_ca_index_status', true );
			if ( empty( $new_index ) ) {
				$mapped = $index_remap[ $old_index ] ?? $old_index;
				update_post_meta( $post_id, 'scos_ca_index_status', $mapped );
				$result['index']['migrated']++;
			} else {
				$result['index']['skipped']++;
			}
		}

		// ── Optimization Progress (workflow_progress → scos_ca_optimization_progress) ──
		// Old values were stored as a serialised array.
		$old_progress = get_post_meta( $post_id, 'workflow_progress', true );
		if ( ! empty( $old_progress ) ) {
			$new_progress = get_post_meta( $post_id, 'scos_ca_optimization_progress', true );
			if ( empty( $new_progress ) ) {
				$progress_arr = is_array( $old_progress ) ? $old_progress : [ $old_progress ];
				update_post_meta( $post_id, 'scos_ca_optimization_progress', array_map( 'sanitize_text_field', $progress_arr ) );
				$result['progress']['migrated']++;
			} else {
				$result['progress']['skipped']++;
			}
		}

		// ── Next Step (content_plan → scos_ca_next_step) ──
		scos_migration_copy_meta( $post_id, 'content_plan', 'scos_ca_next_step', $result['next_step'] );

		// ── Intent Goal / Notes ──
		scos_migration_copy_meta( $post_id, 'bw_altc_notes', 'scos_ca_intent_goal', $result['notes'] );
	}

	return $result;
}

/**
 * Copy a scalar meta value if the destination is empty.
 * Mutates $stat array by reference.
 */
function scos_migration_copy_meta( int $post_id, string $old_key, string $new_key, array &$stat ): void {
	$old = get_post_meta( $post_id, $old_key, true );
	if ( $old === '' || $old === false || $old === null ) {
		return;
	}
	$new = get_post_meta( $post_id, $new_key, true );
	if ( $new === '' || $new === false || $new === null ) {
		update_post_meta( $post_id, $new_key, $old );
		$stat['migrated']++;
	} else {
		$stat['skipped']++;
	}
}

// ──────────────────────────────────────────────────────────────────────────────
// Run: SEO / Schema migration
// ──────────────────────────────────────────────────────────────────────────────

function scos_migration_run_seo(): array {
	$ids    = scos_migration_get_all_post_ids();
	$result = [
		'breadcrumb_title' => [ 'migrated' => 0, 'skipped' => 0 ],
		'schema_json'      => [ 'migrated' => 0, 'skipped' => 0 ],
		'shortlink_slug'   => [ 'migrated' => 0, 'skipped' => 0 ],
		'seo_title'        => [ 'migrated' => 0, 'skipped' => 0 ],
		'seo_description'  => [ 'migrated' => 0, 'skipped' => 0 ],
		'seo_robots'       => [ 'migrated' => 0, 'skipped' => 0 ],
		'seo_canonical'    => [ 'migrated' => 0, 'skipped' => 0 ],
	];

	foreach ( $ids as $post_id ) {

		// ── Breadcrumb Title ──
		$new_bc = get_post_meta( $post_id, 'scos_seo_breadcrumb_title', true );
		if ( empty( $new_bc ) ) {
			$bc_primary  = get_post_meta( $post_id, 'bw_custom_schema', true );
			$bc_fallback = get_post_meta( $post_id, '_seopress_robots_breadcrumbs', true );
			$bc_value    = ! empty( $bc_primary ) ? $bc_primary : $bc_fallback;
			// Only copy if value looks like a short title (not JSON-LD).
			if ( ! empty( $bc_value ) && strlen( $bc_value ) < 200 && strpos( $bc_value, '{' ) === false ) {
				update_post_meta( $post_id, 'scos_seo_breadcrumb_title', sanitize_text_field( $bc_value ) );
				$result['breadcrumb_title']['migrated']++;
			}
		} elseif ( get_post_meta( $post_id, 'bw_custom_schema', true ) || get_post_meta( $post_id, '_seopress_robots_breadcrumbs', true ) ) {
			$result['breadcrumb_title']['skipped']++;
		}

		// ── Per-page Schema JSON-LD ──
		scos_migration_copy_meta( $post_id, 'bw_breadcrumb_schema', 'scos_schema_custom', $result['schema_json'] );

		// ── Shortlink Slug ──
		$old_slug = get_post_meta( $post_id, '_bw_breadcrumb', true );
		if ( ! empty( $old_slug ) ) {
			$new_slug = get_post_meta( $post_id, 'scos_sa_shortlink_slug', true );
			if ( empty( $new_slug ) ) {
				update_post_meta( $post_id, 'scos_sa_shortlink_slug', sanitize_text_field( $old_slug ) );
				$result['shortlink_slug']['migrated']++;
			} else {
				$result['shortlink_slug']['skipped']++;
			}
		}

		// ── SEO Title ──
		$old_title = get_post_meta( $post_id, '_seopress_titles_title', true );
		if ( ! empty( $old_title ) ) {
			$new_title = get_post_meta( $post_id, 'scos_seo_title', true );
			if ( empty( $new_title ) ) {
				update_post_meta( $post_id, 'scos_seo_title', sanitize_text_field( $old_title ) );
				$result['seo_title']['migrated']++;
			} else {
				$result['seo_title']['skipped']++;
			}
		}

		// ── SEO Description ──
		$old_desc = get_post_meta( $post_id, '_seopress_titles_desc', true );
		if ( ! empty( $old_desc ) ) {
			$new_desc = get_post_meta( $post_id, 'scos_seo_description', true );
			if ( empty( $new_desc ) ) {
				update_post_meta( $post_id, 'scos_seo_description', sanitize_textarea_field( $old_desc ) );
				$result['seo_description']['migrated']++;
			} else {
				$result['seo_description']['skipped']++;
			}
		}

		// ── Robots ──
		$old_noindex = get_post_meta( $post_id, '_seopress_robots_index', true );
		if ( ! empty( $old_noindex ) ) {
			$new_robots = get_post_meta( $post_id, 'scos_seo_robots', true );
			if ( empty( $new_robots ) ) {
				$directives   = ( $old_noindex === 'yes' || $old_noindex === '1' ) ? [ 'noindex' ] : [];
				$old_nofollow = get_post_meta( $post_id, '_seopress_robots_follow', true );
				if ( $old_nofollow === 'yes' || $old_nofollow === '1' ) {
					$directives[] = 'nofollow';
				}
				if ( ! empty( $directives ) ) {
					update_post_meta( $post_id, 'scos_seo_robots', $directives );
					$result['seo_robots']['migrated']++;
				}
			} else {
				$result['seo_robots']['skipped']++;
			}
		}

		// ── Canonical ──
		$old_canonical = get_post_meta( $post_id, '_seopress_robots_canonical', true );
		if ( ! empty( $old_canonical ) ) {
			$new_canonical = get_post_meta( $post_id, 'scos_seo_canonical', true );
			if ( empty( $new_canonical ) ) {
				update_post_meta( $post_id, 'scos_seo_canonical', esc_url_raw( $old_canonical ) );
				$result['seo_canonical']['migrated']++;
			} else {
				$result['seo_canonical']['skipped']++;
			}
		}
	}

	return $result;
}

// ──────────────────────────────────────────────────────────────────────────────
// Run: Content Analysis Stats migration
// ──────────────────────────────────────────────────────────────────────────────

function scos_migration_run_stats(): array {
	$ids    = scos_migration_get_all_post_ids();
	$result = [
		'word_count'    => [ 'migrated' => 0, 'skipped' => 0 ],
		'image_count'   => [ 'migrated' => 0, 'skipped' => 0 ],
		'h2_count'      => [ 'migrated' => 0, 'skipped' => 0 ],
		'int_links'     => [ 'migrated' => 0, 'skipped' => 0 ],
		'last_analyzed' => [ 'migrated' => 0, 'skipped' => 0 ],
	];

	foreach ( $ids as $post_id ) {
		scos_migration_copy_meta( $post_id, 'bw_word_count',          'scos_ca_word_count',       $result['word_count'] );
		scos_migration_copy_meta( $post_id, 'bw_image_count',         'scos_ca_image_count',      $result['image_count'] );
		scos_migration_copy_meta( $post_id, 'bw_h2_count',            'scos_ca_h2_count',         $result['h2_count'] );
		scos_migration_copy_meta( $post_id, 'bw_internal_link_count', 'scos_ca_links_to_internal', $result['int_links'] );
		scos_migration_copy_meta( $post_id, '_bw_last_analyzed',      'scos_ca_last_analyzed',    $result['last_analyzed'] );
	}

	return $result;
}

// ──────────────────────────────────────────────────────────────────────────────
// Render
// ──────────────────────────────────────────────────────────────────────────────

function scos_migration_render_page(): void {
	$ca_done    = get_option( 'scos_migration_ca_done' );
	$seo_done   = get_option( 'scos_migration_seo_done' );
	$stats_done = get_option( 'scos_migration_stats_done' );

	$dry_ca    = get_transient( 'scos_dry_run_ca' );
	$dry_seo   = get_transient( 'scos_dry_run_seo' );
	$dry_stats = get_transient( 'scos_dry_run_stats' );
	$res_ca    = get_transient( 'scos_migration_ca_result' );
	$res_seo   = get_transient( 'scos_migration_seo_result' );
	$res_stats = get_transient( 'scos_migration_stats_result' );

	delete_transient( 'scos_dry_run_ca' );
	delete_transient( 'scos_dry_run_seo' );
	delete_transient( 'scos_dry_run_stats' );
	delete_transient( 'scos_migration_ca_result' );
	delete_transient( 'scos_migration_seo_result' );
	delete_transient( 'scos_migration_stats_result' );

	$post_types = scos_migration_post_types();
	?>
	<div class="wrap">
		<h1>⚠ SCOS Field Migration Tool</h1>

		<div style="background:#fff3cd;border:1px solid #f0ad4e;padding:12px 16px;border-radius:4px;margin:16px 0">
			<strong>Take a full database backup before running any migration.</strong>
			Fields are only written if the destination is empty — existing <code>scos_*</code> values are never overwritten.
			Remove <code>scos-migration.php</code> from mu-plugins once complete.
		</div>

		<div style="background:#f0f7ff;border:1px solid #c8e0f7;padding:10px 16px;border-radius:4px;margin-bottom:16px;font-size:13px">
			<strong>Active post types (<?php echo count( $post_types ); ?>):</strong>
			<?php echo esc_html( implode( ', ', $post_types ) ); ?>
		</div>

		<?php scos_migration_render_section(
			'Content Architecture',
			'Maps bw_* / altc_* meta keys and ALTC taxonomies to scos_ca_* keys and scos_content_cluster / scos_topic taxonomies.<br>
			<strong>Maturity remap:</strong> <code>learner → entry</code>, <code>practitioner → professional</code>.',
			'ca',
			$ca_done,
			$dry_ca,
			$res_ca,
			[
				'Cluster'              => 'bw_primary_altc_id → <code>scos_content_cluster</code> taxonomy',
				'Topic'                => 'bw_primary_topic_id → <code>scos_topic</code> taxonomy',
				'Maturity'             => 'bw_cont_maturity → <code>scos_ca_maturity</code> (with value remapping)',
				'Purpose'              => 'bw_purpose → <code>scos_ca_purpose</code>',
				'Intent'               => 'bw_intent → <code>scos_ca_intent</code>',
				'Pillar Page'          => 'bw_pillar_page_id → <code>scos_ca_pillar_page_id</code>',
				'Service Pathway'      => 'bw_service_pathway_id → <code>scos_ca_service_pathway_id</code>',
				'Index Status'         => 'bw_index_status → <code>scos_ca_index_status</code>',
				'Optimization Progress'=> 'workflow_progress → <code>scos_ca_optimization_progress</code>',
				'Next Step'            => 'content_plan → <code>scos_ca_next_step</code>',
				'Intent Goal'          => 'bw_altc_notes → <code>scos_ca_intent_goal</code>',
			]
		); ?>

		<?php scos_migration_render_section(
			'SEO &amp; Schema',
			'Maps _seopress_* and legacy bw_* SEO/schema keys to scos_seo_* and scos_schema_* keys.',
			'seo',
			$seo_done,
			$dry_seo,
			$res_seo,
			[
				'Breadcrumb Title'  => 'bw_custom_schema (primary) + _seopress_robots_breadcrumbs (fallback) → <code>scos_seo_breadcrumb_title</code>',
				'Schema JSON-LD'    => 'bw_breadcrumb_schema → <code>scos_schema_custom</code>',
				'Shortlink Slug'    => '_bw_breadcrumb → <code>scos_sa_shortlink_slug</code>',
				'SEO Title'         => '_seopress_titles_title → <code>scos_seo_title</code>',
				'SEO Description'   => '_seopress_titles_desc → <code>scos_seo_description</code>',
				'Robots Directives' => '_seopress_robots_index + _seopress_robots_follow → <code>scos_seo_robots</code> array',
				'Canonical URL'     => '_seopress_robots_canonical → <code>scos_seo_canonical</code>',
			]
		); ?>

		<?php scos_migration_render_section(
			'Content Analysis Stats',
			'Copies previously-computed analysis stats from the legacy bw_* keys. For a full fresh re-analysis, use the
			<a href="' . esc_url( admin_url( 'admin.php?page=scos-content-architecture' ) ) . '">Run Analysis</a>
			button on the Content Architecture overview page instead.',
			'stats',
			$stats_done,
			$dry_stats,
			$res_stats,
			[
				'Word Count'          => 'bw_word_count → <code>scos_ca_word_count</code>',
				'Image Count'         => 'bw_image_count → <code>scos_ca_image_count</code>',
				'H2 Count'            => 'bw_h2_count → <code>scos_ca_h2_count</code>',
				'Internal Link Count' => 'bw_internal_link_count → <code>scos_ca_links_to_internal</code>',
				'Last Analysed Date'  => '_bw_last_analyzed → <code>scos_ca_last_analyzed</code>',
			]
		); ?>
	</div>
	<?php
}

function scos_migration_render_section(
	string $title,
	string $description,
	string $type,
	$done_at,
	$dry_results,
	$run_results,
	array  $field_map
): void {
	$status_badge = $done_at
		? '<span style="display:inline-block;background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600">✓ Completed ' . esc_html( $done_at ) . '</span>'
		: '<span style="display:inline-block;background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600">Pending</span>';
	?>
	<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:20px;margin-bottom:24px">
		<h2 style="margin-top:0">
			<?php echo esc_html( $title ); ?>
			&nbsp;&nbsp;<?php echo $status_badge; ?>
		</h2>
		<p><?php echo wp_kses_post( $description ); ?></p>

		<table class="widefat striped" style="margin-bottom:16px">
			<thead>
				<tr><th>Field</th><th>Mapping</th></tr>
			</thead>
			<tbody>
				<?php foreach ( $field_map as $label => $mapping ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $label ); ?></strong></td>
					<td><?php echo wp_kses_post( $mapping ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $dry_results ) : ?>
		<div style="background:#f0f7ff;border:1px solid #c8e0f7;border-radius:4px;padding:12px 16px;margin-bottom:16px">
			<strong>Dry-Run Results:</strong>
			<table class="widefat" style="margin-top:8px">
				<thead>
					<tr>
						<th>Field</th>
						<th>Has source data</th>
						<th>Already migrated (skipped)</th>
						<th>Would migrate</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $dry_results as $label => $counts ) : ?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td><?php echo (int) $counts['source']; ?></td>
						<td style="color:#6b7280"><?php echo (int) $counts['done']; ?></td>
						<td style="color:<?php echo $counts['migrate'] > 0 ? '#15803d' : '#6b7280'; ?>;font-weight:600">
							<?php echo (int) $counts['migrate']; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<?php if ( $run_results ) : ?>
		<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:4px;padding:12px 16px;margin-bottom:16px">
			<strong>Migration Results:</strong>
			<ul style="margin:8px 0 0 16px">
				<?php foreach ( $run_results as $key => $counts ) : ?>
				<li>
					<strong><?php echo esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ); ?>:</strong>
					<?php if ( isset( $counts['migrated'] ) ) : ?>
						<?php echo (int) $counts['migrated']; ?> migrated,
						<?php echo (int) $counts['skipped']; ?> skipped
						<?php if ( isset( $counts['remapped'] ) && $counts['remapped'] > 0 ) : ?>
							<em>(<?php echo (int) $counts['remapped']; ?> values remapped)</em>
						<?php endif; ?>
						<?php if ( ! empty( $counts['error'] ) ) : ?>
							<span style="color:#dc2626"> — <?php echo count( $counts['error'] ); ?> errors:
								<?php echo esc_html( implode( '; ', array_slice( $counts['error'], 0, 5 ) ) ); ?>
							</span>
						<?php endif; ?>
					<?php endif; ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>

		<form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
			<?php wp_nonce_field( 'scos_migration', 'scos_migration_nonce' ); ?>

			<button type="submit" name="scos_migration_action" value="dry_run_<?php echo esc_attr( $type ); ?>"
				class="button button-secondary">
				🔍 Dry Run (count only)
			</button>

			<button type="submit" name="scos_migration_action" value="run_<?php echo esc_attr( $type ); ?>"
				class="button button-primary"
				onclick="return confirm('Run the <?php echo esc_js( $title ); ?> migration?\n\nEmpty destination fields will be populated. Existing scos_* values are never overwritten.\n\nThis cannot be undone — ensure you have a backup.')">
				▶ Run Migration
			</button>

			<?php if ( $done_at ) : ?>
			<button type="submit" name="scos_migration_action" value="reset_<?php echo esc_attr( $type ); ?>"
				class="button button-link-delete"
				style="margin-left:auto"
				onclick="return confirm('Reset completed status? This does NOT undo any data changes.')">
				Reset status
			</button>
			<?php endif; ?>
		</form>
	</div>
	<?php
}
