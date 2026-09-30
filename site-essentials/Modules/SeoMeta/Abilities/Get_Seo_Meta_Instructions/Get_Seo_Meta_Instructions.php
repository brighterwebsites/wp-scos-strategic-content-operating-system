<?php
/**
 * Get SEO Meta Instructions — WordPress Ability
 *
 * Returns how SCOS SEO meta is written: the meta keys, length limits, rules
 * per field and per content type, vocabulary to avoid and the site's brand
 * voice. Any agent or tool that writes SEO meta calls this first, so every
 * writer follows the same rules.
 *
 * Tool ability — no AI inside, and it works on a site with no AI plugin.
 * All logic lives in Seo_Meta_Instructions; this class only exposes it.
 *
 * Ability slug: scos/get-seo-meta-instructions (permanent — do not rename after deployment)
 * Category:     scos-seo-meta
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\Abilities\Get_Seo_Meta_Instructions
 *
 * v1.0 | 2026-09-30
 * v1.1 | 2026-09-30 — Output gains business and purpose.
 */

declare( strict_types=1 );

namespace SiteEssentials\Modules\SeoMeta\Abilities\Get_Seo_Meta_Instructions;

use WP_Error;
use SiteEssentials\Core\Abilities\Abstract_Scos_Ability;
use SiteEssentials\Core\Abilities\Ability_Support;
use SiteEssentials\Modules\SeoMeta\Seo_Meta_Instructions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Get_Seo_Meta_Instructions extends Abstract_Scos_Ability {

	/**
	 * Register this ability with the WordPress core Abilities API.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! Ability_Support::is_abilities_api_available() ) {
			return;
		}
		wp_register_ability( 'scos/get-seo-meta-instructions', [
			'label'         => __( 'SCOS: Get SEO Meta Instructions', 'site-essentials' ),
			'description'   => __( 'Returns the rules for writing SCOS SEO meta (breadcrumb label, meta title, meta description): the meta keys, length limits, how to write each field for the post\'s content type, vocabulary to avoid and the site\'s brand voice. Call this before writing or updating SEO meta by any route. Read-only, no AI inside.', 'site-essentials' ),
			'category'      => 'scos-seo-meta',
			'ability_class' => self::class,
			'meta'          => [
				'show_in_rest' => true,
				'mcp'          => [
					'public' => true,
					'type'   => 'tool',
				],
			],
		] );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'default'    => [],
			'properties' => [
				'post_id'      => [
					'type'        => 'integer',
					'description' => 'The post the meta is for. Sets the content type and returns the current value of each field.',
				],
				'post_type'    => [
					'type'        => 'string',
					'description' => 'Post type slug, when there is no post yet. Ignored when post_id is given.',
				],
				'content_type' => [
					'type'        => 'string',
					'description' => 'Force a content type (article, page, product, service, case-study) instead of deriving it from the page purpose or post type.',
				],
				'fields'       => [
					'type'        => 'array',
					'description' => 'Limit the result to these fields. Omit for all.',
					'items'       => [
						'type' => 'string',
						'enum' => array_keys( Seo_Meta_Instructions::fields() ),
					],
				],
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'version'              => [
					'type'        => 'string',
					'description' => 'Version of the shipped rules.',
				],
				'content_type'         => [
					'type'        => 'string',
					'description' => 'Content type the type_rules were chosen for.',
				],
				'post_type'            => [ 'type' => 'string' ],
				'post'                 => [
					'type'        => 'object',
					'description' => 'The post id and title, when post_id was given.',
				],
				'business'             => [
					'type'        => 'object',
					'description' => 'Who the business is: name, category, location, offering, description. Any may be empty.',
				],
				'purpose'              => [
					'type'        => 'object',
					'description' => 'The page\'s Content Architecture purpose: key and label. Empty strings when not set.',
				],
				'fields'               => [
					'type'        => 'object',
					'description' => 'Keyed by field. Each has meta_key, label, unit, min, max, optional target, rules, type_rules and (with post_id) current.',
				],
				'general_rules'        => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'avoid'                => [
					'type'        => 'array',
					'description' => 'Words and phrases that must not appear.',
					'items'       => [ 'type' => 'string' ],
				],
				'voice'                => [
					'type'        => 'object',
					'description' => 'Site brand voice: found (bool), source (file), text. Empty text when the site has none.',
				],
				'editorial_guidelines' => [
					'type'        => 'string',
					'description' => 'Site editorial guidelines from the WordPress AI plugin, when present.',
				],
				'facts'                => [
					'type'        => 'array',
					'description' => 'Post-specific facts supplied by site plugins.',
					'items'       => [ 'type' => 'string' ],
				],
				'saving'               => [
					'type'        => 'array',
					'description' => 'How to store the values.',
					'items'       => [ 'type' => 'string' ],
				],
				'notes'                => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
			],
		];
	}

	/**
	 * Execute the ability — return the instructions.
	 *
	 * @param mixed $input Validated input array.
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute_callback( $input ) {
		$input   = is_array( $input ) ? $input : [];
		$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

		if ( $post_id && ! get_post( $post_id ) instanceof \WP_Post ) {
			return new WP_Error(
				'post_not_found',
				/* translators: %d: Post ID. */
				sprintf( esc_html__( 'Post with ID %d not found.', 'site-essentials' ), $post_id ),
				[ 'status' => 404 ]
			);
		}

		return Seo_Meta_Instructions::get( $input );
	}

	/**
	 * @param mixed $input Validated input array.
	 * @return bool|WP_Error
	 */
	public function permission_callback( $input ) {
		$post_id = is_array( $input ) && isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

		if ( $post_id > 0 ) {
			return current_user_can( 'edit_post', $post_id );
		}

		return current_user_can( 'edit_posts' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function meta(): array {
		return [
			'show_in_rest' => true,
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'mcp'          => [
				'public' => true,
				'type'   => 'tool',
			],
		];
	}
}

add_action( 'wp_abilities_api_init', [ Get_Seo_Meta_Instructions::class, 'register' ] );
