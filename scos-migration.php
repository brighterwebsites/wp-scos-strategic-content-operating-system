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
	}

	wp_safe_redirect( add_query_arg( 'page', 'scos-migration', admin_url( 'tools.php' ) ) );
	exit;
}

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Return all post types that participate in content architecture.
 *
 * @return string[]
 */
function scos_migration_post_types(): array {
	return [ 'page', 'post', 'service', 'resource', 'case_study', 'faq_item' ];
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
	// Try slug first (most reliable match).
	$existing = get_term_by( 'slug', $slug, $taxonomy );
	if ( $existing && ! is_wp_error( $existing ) ) {
		return (int) $existing->term_id;
	}
	// Fall back to name.
	$existing = get_term_by( 'name', $name, $taxonomy );
	if ( $existing && ! is_wp_error( $existing ) ) {
		return (int) $existing->term_id;
	}
	// Create.
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
			'Service Pathway (bw_service_pathway_id)'               => [ 'old' => 'bw_service_pathway_id', 'new_meta' => 'scos_ca_service_pathway_id' ],
			'Intent Goal / Notes (bw_altc_notes)'                   => [ 'old' => 'bw_altc_notes',         'new_meta' => 'scos_ca_intent_goal' ],
		];
	} else {
		$fields = [
			'Breadcrumb Title – primary (bw_custom_schema)'         => [ 'old' => 'bw_custom_schema',              'new_meta' => 'scos_seo_breadcrumb_title' ],
			'Breadcrumb Title – fallback (_seopress_robots_breadcrumbs)' => [ 'old' => '_seopress_robots_breadcrumbs', 'new_meta' => 'scos_seo_breadcrumb_title' ],
			'Per-page Schema JSON-LD (bw_breadcrumb_schema)'        => [ 'old' => 'bw_breadcrumb_schema',           'new_meta' => 'scos_schema_custom' ],
			'Shortlink Slug (_bw_breadcrumb)'                       => [ 'old' => '_bw_breadcrumb',                 'new_meta' => 'scos_sa_shortlink_slug' ],
			'SEO Title (_seopress_titles_title)'                    => [ 'old' => '_seopress_titles_title',          'new_meta' => 'scos_seo_title' ],
			'SEO Description (_seopress_titles_desc)'               => [ 'old' => '_seopress_titles_desc',           'new_meta' => 'scos_seo_description' ],
			'Robots – noindex (_seopress_robots_index)'             => [ 'old' => '_seopress_robots_index',          'new_meta' => 'scos_seo_robots' ],
			'Canonical URL (_seopress_robots_canonical)'            => [ 'old' => '_seopress_robots_canonical',      'new_meta' => 'scos_seo_canonical' ],
		];
	}

	foreach ( $fields as $label => $cfg ) {
		$with_source   = 0;
		$already_done  = 0;
		$would_migrate = 0;

		foreach ( $ids as $post_id ) {
			$old_val = get_post_meta( $post_id, $cfg['old'], true );
			if ( empty( $old_val ) ) {
				continue;
			}
			$with_source++;

			if ( isset( $cfg['new_meta'] ) ) {
				$new_val = get_post_meta( $post_id, $cfg['new_meta'], true );
				if ( ! empty( $new_val ) ) {
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
			'source'   => $with_source,
			'done'     => $already_done,
			'migrate'  => $would_migrate,
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
		'pathway'   => [ 'migrated' => 0, 'skipped' => 0 ],
		'notes'     => [ 'migrated' => 0, 'skipped' => 0 ],
	];

	foreach ( $ids as $post_id ) {

		// ── Cluster (bw_primary_altc_id → altc_strategic_lens → scos_content_cluster) ──
		$old_altc_id = (int) get_post_meta( $post_id, 'bw_primary_altc_id', true );
		if ( $old_altc_id ) {
			$existing = wp_get_post_terms( $post_id, 'scos_content_cluster' );
			if ( is_wp_error( $existing ) || empty( $existing ) ) {
				$old_term = get_term( $old_altc_id, 'altc_strategic_lens' );
				if ( $old_term && ! is_wp_error( $old_term ) ) {
					$new_id = scos_migration_find_or_create_term(
						$old_term->name,
						$old_term->slug,
						'scos_content_cluster'
					);
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

		// ── Topic (bw_primary_topic_id → altc_topic → scos_topic) ──
		$old_topic_id = (int) get_post_meta( $post_id, 'bw_primary_topic_id', true );
		if ( $old_topic_id ) {
			$existing = wp_get_post_terms( $post_id, 'scos_topic' );
			if ( is_wp_error( $existing ) || empty( $existing ) ) {
				$old_term = get_term( $old_topic_id, 'altc_topic' );
				if ( $old_term && ! is_wp_error( $old_term ) ) {
					$new_id = scos_migration_find_or_create_term(
						$old_term->name,
						$old_term->slug,
						'scos_topic'
					);
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

		// ── Maturity (bw_cont_maturity → scos_ca_maturity) ──
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

		// ── Purpose (bw_purpose → scos_ca_purpose) ──
		$old_purpose = get_post_meta( $post_id, 'bw_purpose', true );
		if ( ! empty( $old_purpose ) ) {
			$new_purpose = get_post_meta( $post_id, 'scos_ca_purpose', true );
			if ( empty( $new_purpose ) ) {
				update_post_meta( $post_id, 'scos_ca_purpose', $old_purpose );
				$result['purpose']['migrated']++;
			} else {
				$result['purpose']['skipped']++;
			}
		}

		// ── Service Pathway (bw_service_pathway_id → scos_ca_service_pathway_id) ──
		$old_pathway = get_post_meta( $post_id, 'bw_service_pathway_id', true );
		if ( ! empty( $old_pathway ) ) {
			$new_pathway = get_post_meta( $post_id, 'scos_ca_service_pathway_id', true );
			if ( empty( $new_pathway ) ) {
				update_post_meta( $post_id, 'scos_ca_service_pathway_id', $old_pathway );
				$result['pathway']['migrated']++;
			} else {
				$result['pathway']['skipped']++;
			}
		}

		// ── Intent Goal / Notes (bw_altc_notes → scos_ca_intent_goal) ──
		$old_notes = get_post_meta( $post_id, 'bw_altc_notes', true );
		if ( ! empty( $old_notes ) ) {
			$new_notes = get_post_meta( $post_id, 'scos_ca_intent_goal', true );
			if ( empty( $new_notes ) ) {
				update_post_meta( $post_id, 'scos_ca_intent_goal', $old_notes );
				$result['notes']['migrated']++;
			} else {
				$result['notes']['skipped']++;
			}
		}
	}

	return $result;
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

		// ── Breadcrumb Title: bw_custom_schema (primary) → scos_seo_breadcrumb_title ──
		// Fallback: _seopress_robots_breadcrumbs if primary is empty
		$new_bc = get_post_meta( $post_id, 'scos_seo_breadcrumb_title', true );
		if ( empty( $new_bc ) ) {
			$bc_primary  = get_post_meta( $post_id, 'bw_custom_schema', true );
			$bc_fallback = get_post_meta( $post_id, '_seopress_robots_breadcrumbs', true );
			$bc_value    = ! empty( $bc_primary ) ? $bc_primary : $bc_fallback;

			// Only set if the value looks like a title (not JSON-LD / large block of text).
			if ( ! empty( $bc_value ) && strlen( $bc_value ) < 200 && strpos( $bc_value, '{' ) === false ) {
				update_post_meta( $post_id, 'scos_seo_breadcrumb_title', sanitize_text_field( $bc_value ) );
				$result['breadcrumb_title']['migrated']++;
			}
		} else {
			if ( get_post_meta( $post_id, 'bw_custom_schema', true ) || get_post_meta( $post_id, '_seopress_robots_breadcrumbs', true ) ) {
				$result['breadcrumb_title']['skipped']++;
			}
		}

		// ── Per-page Schema JSON-LD: bw_breadcrumb_schema → scos_schema_custom ──
		$old_schema = get_post_meta( $post_id, 'bw_breadcrumb_schema', true );
		if ( ! empty( $old_schema ) ) {
			$new_schema = get_post_meta( $post_id, 'scos_schema_custom', true );
			if ( empty( $new_schema ) ) {
				update_post_meta( $post_id, 'scos_schema_custom', $old_schema );
				$result['schema_json']['migrated']++;
			} else {
				$result['schema_json']['skipped']++;
			}
		}

		// ── Shortlink Slug: _bw_breadcrumb → scos_sa_shortlink_slug ──
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

		// ── SEO Title: _seopress_titles_title → scos_seo_title ──
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

		// ── SEO Description: _seopress_titles_desc → scos_seo_description ──
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

		// ── Robots – noindex: _seopress_robots_index → scos_seo_robots (array) ──
		// SEOPress stores "yes" = noindex, blank = index.
		$old_noindex = get_post_meta( $post_id, '_seopress_robots_index', true );
		if ( ! empty( $old_noindex ) ) {
			$new_robots = get_post_meta( $post_id, 'scos_seo_robots', true );
			if ( empty( $new_robots ) ) {
				// scos_seo_robots is stored as a serialised array of directives.
				$directives = ( $old_noindex === 'yes' || $old_noindex === '1' ) ? [ 'noindex' ] : [];
				// Also check _seopress_robots_follow for nofollow.
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

		// ── Canonical: _seopress_robots_canonical → scos_seo_canonical ──
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
// Render
// ──────────────────────────────────────────────────────────────────────────────

function scos_migration_render_page(): void {
	$ca_done  = get_option( 'scos_migration_ca_done' );
	$seo_done = get_option( 'scos_migration_seo_done' );

	$dry_ca  = get_transient( 'scos_dry_run_ca' );
	$dry_seo = get_transient( 'scos_dry_run_seo' );
	$res_ca  = get_transient( 'scos_migration_ca_result' );
	$res_seo = get_transient( 'scos_migration_seo_result' );

	// Delete transients so results show once.
	delete_transient( 'scos_dry_run_ca' );
	delete_transient( 'scos_dry_run_seo' );
	delete_transient( 'scos_migration_ca_result' );
	delete_transient( 'scos_migration_seo_result' );
	?>
	<div class="wrap">
		<h1>⚠ SCOS Field Migration Tool</h1>

		<div style="background:#fff3cd;border:1px solid #f0ad4e;padding:12px 16px;border-radius:4px;margin:16px 0">
			<strong>Remember to take a full database backup before running any migration.</strong>
			Fields are only written if the destination is empty (existing scos_* values are never overwritten).
			<br>Remove <code>scos-migration.php</code> from mu-plugins once migration is complete.
		</div>

		<?php scos_migration_render_section(
			'Content Architecture',
			'Maps bw_* / altc_* meta keys and ALTC taxonomies to the new scos_ca_* keys and scos_content_cluster / scos_topic taxonomies.<br>
			<strong>Maturity remap:</strong> <code>learner → entry</code>, <code>practitioner → professional</code>.',
			'ca',
			$ca_done,
			$dry_ca,
			$res_ca,
			[
				'Cluster'        => 'bw_primary_altc_id → <code>scos_content_cluster</code> taxonomy (term lookup / create)',
				'Topic'          => 'bw_primary_topic_id → <code>scos_topic</code> taxonomy (term lookup / create)',
				'Maturity'       => 'bw_cont_maturity → <code>scos_ca_maturity</code> (with value remapping)',
				'Purpose'        => 'bw_purpose → <code>scos_ca_purpose</code>',
				'Service Pathway'=> 'bw_service_pathway_id → <code>scos_ca_service_pathway_id</code>',
				'Intent Goal'    => 'bw_altc_notes → <code>scos_ca_intent_goal</code>',
			]
		); ?>

		<?php scos_migration_render_section(
			'SEO &amp; Schema',
			'Maps _seopress_* and legacy bw_* SEO/schema keys to the new scos_seo_* and scos_schema_* keys.',
			'seo',
			$seo_done,
			$dry_seo,
			$res_seo,
			[
				'Breadcrumb Title' => 'bw_custom_schema (primary) + _seopress_robots_breadcrumbs (fallback) → <code>scos_seo_breadcrumb_title</code>',
				'Schema JSON-LD'   => 'bw_breadcrumb_schema → <code>scos_schema_custom</code>',
				'Shortlink Slug'   => '_bw_breadcrumb → <code>scos_sa_shortlink_slug</code>',
				'SEO Title'        => '_seopress_titles_title → <code>scos_seo_title</code>',
				'SEO Description'  => '_seopress_titles_desc → <code>scos_seo_description</code>',
				'Robots Directives'=> '_seopress_robots_index + _seopress_robots_follow → <code>scos_seo_robots</code> array',
				'Canonical URL'    => '_seopress_robots_canonical → <code>scos_seo_canonical</code>',
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
				<tr>
					<th>Field</th>
					<th>Mapping</th>
				</tr>
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
							<span style="color:#dc2626"><?php echo count( $counts['error'] ); ?> errors:
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
				onclick="return confirm('Run the <?php echo esc_js( $title ); ?> migration? Empty destination fields will be populated. This cannot be undone.')">
				▶ Run Migration
			</button>

			<?php if ( $done_at ) : ?>
			<button type="submit" name="scos_migration_action" value="reset_<?php echo esc_attr( $type ); ?>"
				class="button button-link-delete"
				style="margin-left:auto"
				onclick="return confirm('Reset the completed status? This does not undo any data changes.')">
				Reset status
			</button>
			<?php endif; ?>
		</form>
	</div>
	<?php
}
