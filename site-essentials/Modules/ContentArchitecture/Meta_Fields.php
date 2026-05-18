<?php
/**
 * Content Architecture — Post Meta Registration & Option Helpers
 *
 * All scos_ca_* post meta fields with sanitise callbacks.
 * Option arrays are defined here as single source of truth used by the
 * meta box view, admin columns, and (eventually) the REST / Airtable layers.
 *
 * NOTE: Intent and Purpose option VALUES are intentionally kept identical to
 * the legacy bw_intent / bw_purpose values because those slugs are sent to
 * GA4 and Airtable. Only the meta key names have changed.
 *
 * @package    SiteEssentials
 * @subpackage Modules\ContentArchitecture
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\ContentArchitecture;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meta_Fields {

	public static function init() {
		self::register();
	}

	/**
	 * Register all scos_ca_* post meta with WordPress.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		$string = [
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => 'sanitize_text_field',
			'show_in_rest'      => false,
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		];

		$textarea = array_merge( $string, [ 'sanitize_callback' => 'sanitize_textarea_field' ] );

		$int = [
			'type'              => 'integer',
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => false,
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		];

		// ---- Strategy fields ----
		register_post_meta( '', 'scos_ca_pillar_page_id',     $int );
		register_post_meta( '', 'scos_ca_service_pathway_id', $int );
		register_post_meta( '', 'scos_ca_intent',             $string );
		register_post_meta( '', 'scos_ca_purpose',            $string );
		register_post_meta( '', 'scos_ca_maturity',           $string );
		register_post_meta( '', 'scos_ca_intent_goal',        $textarea );

		// ---- Workflow fields ----
		register_post_meta( '', 'scos_ca_index_status',            $string );
		register_post_meta( '', 'scos_ca_next_step',               $string );
		register_post_meta(
			'',
			'scos_ca_optimization_progress',
			[
				'type'         => 'array',
				'single'       => true,
				'show_in_rest' => false,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		// ---- Analysis fields (written by Content_Analysis, read-only in UI) ----
		register_post_meta( '', 'scos_ca_word_count',             $int );
		register_post_meta( '', 'scos_ca_h2_count',               $int );
		register_post_meta( '', 'scos_ca_image_count',            $int );
		register_post_meta( '', 'scos_ca_reading_time',           $int );
		register_post_meta( '', 'scos_ca_reading_time_iso',       $string );
		register_post_meta( '', 'scos_ca_links_to_internal',      $int );
		register_post_meta( '', 'scos_ca_links_to_external',      $int );
		register_post_meta( '', 'scos_ca_last_analyzed',          $string );

		// ---- Schema tracking (written by Content_Analysis, read-only in UI) ----
		// Stores a sorted, deduplicated array of schema @type strings detected on
		// the post — e.g. ['FAQPage', 'HowTo']. Updated every time Content_Analysis
		// runs. Consumers: admin columns, MCP tools, schema audit reports.
		register_post_meta(
			'',
			'scos_ca_schema_track',
			[
				'type'         => 'array',
				'single'       => true,
				'show_in_rest' => false,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		// Link detail lists (serialised arrays)
		register_post_meta(
			'',
			'scos_ca_links_to_internal_list',
			[
				'type'         => 'array',
				'single'       => true,
				'show_in_rest' => false,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);
		register_post_meta(
			'',
			'scos_ca_links_to_external_list',
			[
				'type'         => 'array',
				'single'       => true,
				'show_in_rest' => false,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);
	}

	// ============================================================
	// Option helpers — single source of truth for all dropdowns
	// ============================================================

	/**
	 * Intent options.
	 * Values kept identical to legacy bw_intent slugs (GA4 / Airtable dependency).
	 *
	 * @return array<string, string>
	 */
	public static function intent_options() {
		return [
			''               => '— Not Set —',
			'informational'  => 'Informational',
			'informational_p' => 'Info Problem',
			'informational_s' => 'Info Solution',
			'commercial'     => 'Commercial',
			'commercial_ds'  => 'Decision Support',
			'transactional'  => 'Transactional Payment',
			'transact_lead'  => 'Transactional Lead',
			'trust'          => 'Authority Trust',
			'navigational'   => 'Navigate Hub',
			'functional'     => 'Policy Functional',
		];
	}

	/**
	 * Purpose options.
	 * Values kept identical to legacy bw_purpose slugs (GA4 / Airtable dependency).
	 *
	 * @return array<string, string>
	 */
	public static function purpose_options() {
		return [
			''                     => '— Not Set —',
			'pillar'               => 'Pillar',
			'service-page'         => 'Service',
			'product-page'         => 'Product',
			'conversion-hub'       => 'Conversion Hub',
			'conversion-event'     => 'Conversion Event',
			'conversion-endpoint'  => 'Conversion End-Point',
			'case-study'           => 'Success Story',
			'authority-page'       => 'Brand Authority',
			'supporting'           => 'Supporting Topic',
			'content-collection'   => 'Content Collection',
			'resource-guide'       => 'Resource Guide',
			'terms'                => 'Policy',
			'functional'           => 'Functional',
		];
	}

	/**
	 * Content maturity options.
	 *
	 * @return array<string, string>
	 */
	public static function maturity_options() {
		return [
			''                   => '— Not Set —',
			'entry'              => 'Entry',
			'professional'       => 'Professional',
			'expert'             => 'Expert',
			'thought_leader'     => 'Thought Leader',
			'industry_authority' => 'Industry Authority',
		];
	}

	/**
	 * Index status options.
	 *
	 * @return array<string, string>
	 */
	public static function index_status_options() {
		return [
			''           => 'Not Set',
			'crawled'    => 'Crawled',
			'discovered' => 'Discovered',
			'indexed'    => 'Indexed',
			'requested'  => 'Requested',
			'issue'      => 'Issue',
			'no_index'   => 'Do Not Index',
		];
	}

	/**
	 * Index status colour map for badges.
	 *
	 * @return array<string, array{color: string, bg: string}>
	 */
	public static function index_status_colors() {
		return [
			'indexed'    => [ 'color' => '#15803d', 'bg' => '#dcfce7' ],
			'crawled'    => [ 'color' => '#ca8a04', 'bg' => '#fef9c3' ],
			'discovered' => [ 'color' => '#2563eb', 'bg' => '#dbeafe' ],
			'requested'  => [ 'color' => '#0891b2', 'bg' => '#cffafe' ],
			'issue'      => [ 'color' => '#dc2626', 'bg' => '#fee2e2' ],
			'no_index'   => [ 'color' => '#dc2626', 'bg' => '#fee2e2' ],
		];
	}

	/**
	 * Optimization progress options (multi-select colour tags).
	 *
	 * @return array<string, array{label: string, color: string, bg: string}>
	 */
	public static function optimization_progress_options() {
		return [
			'idea'               => [ 'label' => 'Idea',                     'color' => '#7c3aed', 'bg' => '#ede9fe' ],
			'content'            => [ 'label' => 'Content',                  'color' => '#2563eb', 'bg' => '#dbeafe' ],
			'entities-semantics' => [ 'label' => 'Entity Semantic Coverage', 'color' => '#0891b2', 'bg' => '#cffafe' ],
			'conversion'         => [ 'label' => 'Conversion',               'color' => '#16a34a', 'bg' => '#dcfce7' ],
			'seo-basic'          => [ 'label' => 'Basic SEO',                'color' => '#ca8a04', 'bg' => '#fef9c3' ],
			'seo-advanced'       => [ 'label' => 'Advanced SEO',             'color' => '#ea580c', 'bg' => '#ffedd5' ],
			'authority-outreach' => [ 'label' => 'Authority Outreach',       'color' => '#be185d', 'bg' => '#fce7f3' ],
			'amplification'      => [ 'label' => 'Social Amplification',     'color' => '#4f46e5', 'bg' => '#e0e7ff' ],
		];
	}

	/**
	 * Content maturity colour map for admin column badges.
	 *
	 * @return array<string, array{color: string, bg: string}>
	 */
	public static function maturity_colors() {
		return [
			'entry'              => [ 'color' => '#92400e', 'bg' => '#fef3c7' ],
			'professional'       => [ 'color' => '#1e3a8a', 'bg' => '#bfdbfe' ],
			'expert'             => [ 'color' => '#5b21b6', 'bg' => '#ddd6fe' ],
			'thought_leader'     => [ 'color' => '#0f766e', 'bg' => '#ccfbf1' ],
			'industry_authority' => [ 'color' => '#065f46', 'bg' => '#d1fae5' ],
		];
	}

	/**
	 * Intent colour map for admin column badges.
	 *
	 * @return array<string, array{color: string, bg: string}>
	 */
	public static function intent_colors() {
		return [
			'informational'   => [ 'color' => '#1d4ed8', 'bg' => '#dbeafe' ],
			'informational_p' => [ 'color' => '#1d4ed8', 'bg' => '#dbeafe' ],
			'informational_s' => [ 'color' => '#0369a1', 'bg' => '#e0f2fe' ],
			'commercial'      => [ 'color' => '#b45309', 'bg' => '#fef3c7' ],
			'commercial_ds'   => [ 'color' => '#92400e', 'bg' => '#fef3c7' ],
			'transactional'   => [ 'color' => '#15803d', 'bg' => '#dcfce7' ],
			'transact_lead'   => [ 'color' => '#166534', 'bg' => '#dcfce7' ],
			'trust'           => [ 'color' => '#7c3aed', 'bg' => '#ede9fe' ],
			'navigational'    => [ 'color' => '#374151', 'bg' => '#f3f4f6' ],
			'functional'      => [ 'color' => '#4b5563', 'bg' => '#f9fafb' ],
		];
	}

	/**
	 * Purpose colour map for admin column badges.
	 *
	 * @return array<string, array{color: string, bg: string}>
	 */
	public static function purpose_colors() {
		return [
			'pillar'              => [ 'color' => '#6d28d9', 'bg' => '#ede9fe' ],
			'service-page'        => [ 'color' => '#15803d', 'bg' => '#dcfce7' ],
			'product-page'        => [ 'color' => '#166534', 'bg' => '#bbf7d0' ],
			'conversion-hub'      => [ 'color' => '#0f766e', 'bg' => '#ccfbf1' ],
			'conversion-event'    => [ 'color' => '#0e7490', 'bg' => '#cffafe' ],
			'conversion-endpoint' => [ 'color' => '#0369a1', 'bg' => '#e0f2fe' ],
			'case-study'          => [ 'color' => '#1d4ed8', 'bg' => '#dbeafe' ],
			'authority-page'      => [ 'color' => '#4f46e5', 'bg' => '#e0e7ff' ],
			'supporting'          => [ 'color' => '#0891b2', 'bg' => '#cffafe' ],
			'content-collection'  => [ 'color' => '#7c3aed', 'bg' => '#f5f3ff' ],
			'resource-guide'      => [ 'color' => '#6d28d9', 'bg' => '#f5f3ff' ],
			'terms'               => [ 'color' => '#4b5563', 'bg' => '#f3f4f6' ],
			'functional'          => [ 'color' => '#374151', 'bg' => '#f9fafb' ],
		];
	}

	/**
	 * Next step options (single-select colour badge).
	 *
	 * @return array<string, array{label: string, color: string, bg: string}>
	 */
	public static function next_step_options() {
		return [
			''        => [ 'label' => '— Not Set —', 'color' => '#6b7280', 'bg' => '#f3f4f6' ],
			'approve' => [ 'label' => 'Approved',    'color' => '#15803d', 'bg' => '#dcfce7' ],
			'testing' => [ 'label' => 'Testing',     'color' => '#ca8a04', 'bg' => '#fef9c3' ],
			'revise'  => [ 'label' => 'Revise',      'color' => '#2563eb', 'bg' => '#dbeafe' ],
			'merge'   => [ 'label' => 'Merge',       'color' => '#7c3aed', 'bg' => '#ede9fe' ],
			'archive' => [ 'label' => 'Archive',     'color' => '#dc2626', 'bg' => '#fee2e2' ],
		];
	}
}
