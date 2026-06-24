<?php
/**
 * Suggest TLDR — WordPress Ability
 *
 * Reads post content and returns AI-suggested TLDR/article summary options for
 * the scos_seo_tldr field in the SCOS SEO meta box.
 *
 * When the post has a linked search intent goal (scos_ca_intent_goal_faq_id →
 * FAQ title, or scos_ca_intent_goal freetext), it is injected into the prompt
 * as <intent_goal> so the TLDR is written to directly and quickly answer that
 * question.
 *
 * Ability slug: scos/suggest-tldr (permanent — do not rename after deployment)
 * Category:     scos-seo-meta
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\Abilities\Suggest_Tldr
 *
 * v1.0 | 2026-06-24
 */

declare( strict_types=1 );

namespace SiteEssentials\Modules\SeoMeta\Abilities\Suggest_Tldr;

use WP_Error;
use WordPress\AI\Abstracts\Abstract_Ability;

use function WordPress\AI\get_post_context;
use function WordPress\AI\normalize_content;
use function WordPress\AI\get_preferred_models_for_text_generation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Suggest_Tldr extends Abstract_Ability {

	// -------------------------------------------------------------------------
	// Ability API registration
	// -------------------------------------------------------------------------

	/**
	 * Register this ability with the WP Abilities API.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register(): void {
		if ( ! class_exists( 'WP_Ability' ) ) {
			return;
		}
		if ( ! class_exists( 'WordPress\AI\Abstracts\Abstract_Ability' ) ) {
			return;
		}
		wp_register_ability( 'scos/suggest-tldr', [
			'label'         => __( 'SCOS: Suggest TLDR', 'site-essentials' ),
			'description'   => __( 'Suggests TLDR article summary options. When a Search Intent Goal is linked, the TLDR is written to directly answer that question.', 'site-essentials' ),
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

	// -------------------------------------------------------------------------
	// Abstract_Ability implementation
	// -------------------------------------------------------------------------

	/**
	 * @since 1.0.0
	 * @return array<string>
	 */
	protected function guideline_categories(): array {
		return [ 'site', 'copy' ];
	}

	/**
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'post_id' => [
					'type'        => 'integer',
					'description' => 'The post ID to analyse. When provided, post content and intent goal are fetched server-side.',
				],
				'content' => [
					'type'        => 'string',
					'description' => 'Raw post content. Used only when post_id is not provided.',
				],
				'title'   => [
					'type'        => 'string',
					'description' => 'Post title. Secondary signal — content is primary.',
				],
			],
		];
	}

	/**
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'intent_goal_used' => [
					'type'        => 'string',
					'description' => 'The intent goal injected into the prompt, if any. Empty string when none available.',
				],
				'tldr_options'     => [
					'type'        => 'array',
					'description' => '3 TLDR summary options (2–4 sentences each).',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'text'           => [ 'type' => 'string' ],
							'sentence_count' => [ 'type' => 'integer' ],
						],
					],
				],
			],
		];
	}

	/**
	 * Execute the ability — analyse content and return TLDR suggestions.
	 *
	 * @since 1.0.0
	 * @param mixed $input Validated input array.
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute_callback( $input ) {
		$args = wp_parse_args(
			$input,
			[
				'post_id' => null,
				'content' => null,
				'title'   => null,
			]
		);

		$content      = '';
		$title        = $args['title'] ?? '';
		$intent_goal  = '';

		if ( $args['post_id'] ) {
			$post = get_post( (int) $args['post_id'] );
			if ( ! $post instanceof \WP_Post ) {
				return new WP_Error(
					'post_not_found',
					/* translators: %d: Post ID. */
					sprintf( esc_html__( 'Post with ID %d not found.', 'site-essentials' ), absint( $args['post_id'] ) )
				);
			}

			// Prefer scos_ca_content_md — fully rendered markdown including Breakdance
			// blocks, ACF fields, Query Loops, and Post Repeaters. Falls back to
			// get_post_context() for posts not yet analysed.
			$md_content = (string) get_post_meta( $post->ID, 'scos_ca_content_md', true );
			if ( ! empty( $md_content ) ) {
				$content = $md_content;
			} else {
				$post_context = get_post_context( $post->ID );
				$content      = $post_context['content'] ?? '';
			}

			if ( empty( $title ) && ! empty( $post->post_title ) ) {
				$title = $post->post_title;
			}

			// Resolve search intent goal — FAQ title first, freetext fallback.
			// Does not depend on the CA module class directly to avoid cross-module coupling.
			$faq_id = (int) get_post_meta( $post->ID, 'scos_ca_intent_goal_faq_id', true );
			if ( $faq_id > 0 ) {
				$faq         = get_post( $faq_id );
				$intent_goal = $faq instanceof \WP_Post ? $faq->post_title : '';
			}
			if ( empty( $intent_goal ) ) {
				$intent_goal = (string) get_post_meta( $post->ID, 'scos_ca_intent_goal', true );
			}
		}

		if ( $args['content'] ) {
			$content = normalize_content( $args['content'] );
		}

		if ( empty( $content ) ) {
			return new WP_Error(
				'content_not_provided',
				esc_html__( 'Content is required to suggest a TLDR.', 'site-essentials' )
			);
		}

		// Truncate very long content: keep first 1500 + last 500 words.
		$words = explode( ' ', $content );
		if ( count( $words ) > 2000 ) {
			$content = implode( ' ', array_slice( $words, 0, 1500 ) )
				. ' [...] '
				. implode( ' ', array_slice( $words, -500 ) );
		}

		$prompt = '';

		// Inject intent goal as context when available.
		if ( ! empty( $intent_goal ) ) {
			$prompt .= '<intent_goal>' . esc_html( $intent_goal ) . '</intent_goal>' . "\n";
		}

		$prompt .= '<title>' . $title . '</title>' . "\n";
		$prompt .= '<content>' . $content . '</content>';

		$prompt_builder = wp_ai_client_prompt( $prompt )
			->using_system_instruction( $this->get_system_instruction() )
			->using_temperature( 0.4 )
			->using_model_preference( ...get_preferred_models_for_text_generation() );

		$prompt_builder = $this->ensure_text_generation_supported(
			$prompt_builder,
			esc_html__( 'TLDR suggestion failed. Please ensure you have a connected provider that supports text generation.', 'site-essentials' )
		);

		if ( is_wp_error( $prompt_builder ) ) {
			return $prompt_builder;
		}

		$result = $prompt_builder->generate_text();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Strip markdown code fences if the model wraps output.
		$json_str = preg_replace( '/^```(?:json)?\s*/i', '', trim( (string) $result ) );
		$json_str = preg_replace( '/\s*```$/', '', $json_str );

		$parsed = json_decode( $json_str, true );

		if ( ! is_array( $parsed ) || empty( $parsed['tldr_options'] ) ) {
			return new WP_Error(
				'scos_suggest_tldr_parse_error',
				__( 'AI response could not be parsed. Please try again.', 'site-essentials' ),
				[ 'status' => 500 ]
			);
		}

		return [
			'intent_goal_used' => sanitize_text_field( $intent_goal ),
			'tldr_options'     => array_map(
				function ( $item ) {
					$text = sanitize_textarea_field( $item['text'] ?? '' );
					// Count sentences by splitting on . ! ? followed by space or end.
					$sentence_count = max( 1, preg_match_all( '/[.!?](?:\s|$)/', $text ) );
					return [
						'text'           => $text,
						'sentence_count' => $sentence_count,
					];
				},
				array_slice( $parsed['tldr_options'], 0, 3 )
			),
		];
	}

	/**
	 * @since 1.0.0
	 * @param mixed $input Validated input array.
	 * @return bool|WP_Error
	 */
	public function permission_callback( $input ) {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return new WP_Error(
					'scos_suggest_tldr_post_not_found',
					__( 'Post not found.', 'site-essentials' ),
					[ 'status' => 404 ]
				);
			}
			if ( ! get_post_type_object( $post->post_type )?->show_in_rest ) {
				return false;
			}
			return current_user_can( 'edit_post', $post_id );
		}

		return current_user_can( 'edit_posts' );
	}

	/**
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public function meta(): array {
		return [
			'show_in_rest' => true,
			'mcp'          => [
				'public' => true,
				'type'   => 'tool',
			],
		];
	}
}

add_action( 'wp_abilities_api_init', [ Suggest_Tldr::class, 'register' ] );
