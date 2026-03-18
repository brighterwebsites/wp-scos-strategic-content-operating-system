<?php
/**
 * SCOS Content Architecture Record (CAR) Injection
 *
 * Outputs window.brighterSCOS into <head> on every page.
 * Reads from new scos_ca_* meta keys / scos_* taxonomies first,
 * falling back to legacy bw_* keys for posts not yet migrated.
 *
 * Structure:
 *   car     — semantic intent, topical authority, metrics
 *   meta    — post ID, type, version, timestamp
 *
 * The legacy `tracking` block has been removed — GA4 config is managed
 * entirely by the GA4 scripts and does not belong in the CAR.
 *
 * @package BrighterCore
 * @subpackage Analytics
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Inject SCOS CAR into <head>.
 * Priority 5 — loads before GA4 tracking scripts (priority 99).
 */
add_action( 'wp_head', function () {

	// ── Resolve post ID ──────────────────────────────────────────────────────
	$post_id = null;
	if ( is_singular() ) {
		$post_id = get_the_ID();
	} elseif ( is_front_page() ) {
		$front_page_id = (int) get_option( 'page_on_front' );
		if ( $front_page_id ) {
			$post_id = $front_page_id;
		}
	}

	// ── Minimal CAR for archives / blog home ─────────────────────────────────
	if ( ! $post_id ) {
		$scos = [
			'car'  => [
				'cluster'       => 'not_set',
				'topic'         => 'not_set',
				'maturity'      => 'not_set',
				'intent'        => 'not_set',
				'search-intent' => 'not_set',
				'purpose'       => 'not_set',
				'pillar'        => null,
				'service_pathway' => null,
			],
			'meta' => [
				'post_id'       => 0,
				'post_type'     => get_post_type() ?: 'archive',
				'scos_version'  => defined( 'SCOS_VERSION' ) ? SCOS_VERSION : '4.4.0',
				'car_generated' => current_time( 'c' ),
			],
		];
		scos_output_car( $scos );
		return;
	}

	// ── Helper: scos_ key first, bw_ key fallback ────────────────────────────
	$val = function ( $scos_key, $legacy_key = null ) use ( $post_id ) {
		$v = get_post_meta( $post_id, $scos_key, true );
		if ( $v !== '' && $v !== null && $v !== false ) {
			return $v;
		}
		if ( $legacy_key ) {
			$v = get_post_meta( $post_id, $legacy_key, true );
			return ( $v !== '' && $v !== null && $v !== false ) ? $v : 'not_set';
		}
		return 'not_set';
	};

	// ── Cluster (scos_content_cluster taxonomy → legacy altc_strategic_lens) ─
	$cluster_name = 'not_set';
	$cluster_terms = wp_get_post_terms( $post_id, 'scos_content_cluster', [ 'fields' => 'names' ] );
	if ( ! is_wp_error( $cluster_terms ) && ! empty( $cluster_terms ) ) {
		$cluster_name = $cluster_terms[0];
	} else {
		// Legacy fallback
		$legacy_altc_id = get_post_meta( $post_id, 'bw_primary_altc_id', true );
		if ( $legacy_altc_id ) {
			$t = get_term( $legacy_altc_id, 'altc_strategic_lens' );
			if ( $t && ! is_wp_error( $t ) ) { $cluster_name = $t->name; }
		}
		if ( $cluster_name === 'not_set' ) {
			$legacy_terms = wp_get_post_terms( $post_id, 'altc_strategic_lens', [ 'fields' => 'names' ] );
			if ( ! is_wp_error( $legacy_terms ) && ! empty( $legacy_terms ) ) {
				$cluster_name = $legacy_terms[0];
			}
		}
	}

	// ── Topic (scos_topic taxonomy → legacy altc_topic) ──────────────────────
	$topic_name = 'not_set';
	$topic_terms = wp_get_post_terms( $post_id, 'scos_topic', [ 'fields' => 'names' ] );
	if ( ! is_wp_error( $topic_terms ) && ! empty( $topic_terms ) ) {
		$topic_name = $topic_terms[0];
	} else {
		$legacy_topic_id = get_post_meta( $post_id, 'bw_primary_topic_id', true );
		if ( $legacy_topic_id ) {
			$t = get_term( $legacy_topic_id, 'altc_topic' );
			if ( $t && ! is_wp_error( $t ) ) { $topic_name = $t->name; }
		}
		if ( $topic_name === 'not_set' ) {
			$legacy_terms = wp_get_post_terms( $post_id, 'altc_topic', [ 'fields' => 'names' ] );
			if ( ! is_wp_error( $legacy_terms ) && ! empty( $legacy_terms ) ) {
				$topic_name = $legacy_terms[0];
			}
		}
		// Final fallback: old free-text field
		if ( $topic_name === 'not_set' ) {
			$old = get_post_meta( $post_id, 'bw_page_topic', true );
			if ( $old ) { $topic_name = $old; }
		}
	}

	// ── Pillar relationship ───────────────────────────────────────────────────
	$pillar    = null;
	$pillar_id = (int) get_post_meta( $post_id, 'scos_ca_pillar_page_id', true )
	          ?: (int) get_post_meta( $post_id, 'bw_pillar_page_id', true );
	if ( $pillar_id > 0 ) {
		$pillar_purpose = get_post_meta( $pillar_id, 'scos_ca_purpose', true )
		               ?: get_post_meta( $pillar_id, 'bw_purpose', true );
		$pillar = [
			'id'    => $pillar_id,
			'title' => get_the_title( $pillar_id ),
			'type'  => ( $pillar_purpose === 'service-page' ) ? 'service' : 'pillar',
		];
	}

	// ── Service pathway ───────────────────────────────────────────────────────
	$service_pathway    = null;
	$service_pathway_id = (int) get_post_meta( $post_id, 'scos_ca_service_pathway_id', true )
	                   ?: (int) get_post_meta( $post_id, 'bw_service_pathway_id', true );
	if ( $service_pathway_id > 0 ) {
		$service_pathway = [
			'id'    => $service_pathway_id,
			'title' => get_the_title( $service_pathway_id ),
		];
	}

	// ── Content metrics ───────────────────────────────────────────────────────
	$metrics = [
		'word_count'     => (int) ( get_post_meta( $post_id, 'scos_ca_word_count', true )
		                         ?: get_post_meta( $post_id, 'bw_word_count', true ) ),
		'reading_time'   => (int) ( get_post_meta( $post_id, 'scos_ca_reading_time', true )
		                         ?: get_post_meta( $post_id, 'bw_reading_time', true ) ),
		'internal_links' => (int) ( get_post_meta( $post_id, 'scos_ca_links_to_internal', true )
		                         ?: get_post_meta( $post_id, 'bw_internal_link_count', true ) ),
		'external_links' => (int) ( get_post_meta( $post_id, 'scos_ca_links_to_external', true )
		                         ?: get_post_meta( $post_id, 'bw_external_link_count', true ) ),
		'last_updated'   => get_the_modified_date( 'Y-m-d', $post_id ),
	];

	// ── Assemble CAR ─────────────────────────────────────────────────────────
	$scos = [
		'car' => [
			'cluster'         => $cluster_name,
			'topic'           => $topic_name,
			'maturity'        => $val( 'scos_ca_maturity',    'bw_cont_maturity' ),
			'intent'          => $val( 'scos_ca_intent',      'bw_intent' ),
			'search-intent'   => $val( 'scos_ca_intent_goal', 'bw_search_intent' ),
			'purpose'         => $val( 'scos_ca_purpose',     'bw_purpose' ),
			'pillar'          => $pillar,
			'service_pathway' => $service_pathway,
			'metrics'         => $metrics,
		],
		'meta' => [
			'post_id'       => $post_id,
			'post_type'     => get_post_type( $post_id ),
			'scos_version'  => defined( 'SCOS_VERSION' ) ? SCOS_VERSION : '4.4.0',
			'car_generated' => current_time( 'c' ),
		],
	];

	scos_output_car( $scos );

}, 5 );

/**
 * Output the window.brighterSCOS script tag.
 *
 * @param array $scos Data structure to JSON-encode.
 */
if ( ! function_exists( 'scos_output_car' ) ) :
function scos_output_car( array $scos ) {
	echo "\n" . '<script data-no-optimize="1" data-cfasync="false" data-litespeed-no-optimize="1">' . "\n";
	echo '// SCOS Content Architecture Record — semantic intent and topical authority mapping.' . "\n";
	echo 'window.brighterSCOS = ' . wp_json_encode( $scos, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . ';' . "\n";
	echo '</script>' . "\n";
}
endif;
