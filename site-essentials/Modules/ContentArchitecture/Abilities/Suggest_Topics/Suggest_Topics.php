<?php
/**
 * Suggest Topics — WordPress Ability
 *
 * Fetches the scos_topic taxonomy term list server-side and asks the AI to
 * identify which existing topics best match the post content. Returns term_ids
 * that can be directly applied to the scos_ca_topic select in the meta box.
 *
 * Ability slug: scos/suggest-topics (permanent — do not rename after deployment)
 * Category:     scos-content-architecture
 *
 * @package    SiteEssentials
 * @subpackage Modules\ContentArchitecture\Abilities\Suggest_Topics
 *
 * v1.0 | 2026-06-24
 */

declare( strict_types=1 );

namespace SiteEssentials\Modules\ContentArchitecture\Abilities\Suggest_Topics;

use WP_Error;
use WordPress\AI\Abstracts\Abstract_Ability;

use function WordPress\AI\get_post_context;
use function WordPress\AI\normalize_content;
use function WordPress\AI\get_preferred_models_for_text_generation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Suggest_Topics extends Abstract_Ability {

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
		wp_register_ability( 'scos/suggest-topics', [
			'label'         => __( 'SCOS: Suggest Topics', 'site-essentials' ),
			'description'   => __( 'Reads post content and suggests matching scos_topic taxonomy terms for the SCOS Content Architecture meta box.', 'site-essentials' ),
			'category'      => 'scos-content-architecture',
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
					'description' => 'The post ID to analyse. When provided, post content is fetched server-side.',
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
				'suggestions' => [
					'type'        => 'array',
					'description' => 'Suggested topics from the existing scos_topic taxonomy, ordered by confidence.',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'term_id'        => [
								'type'        => 'integer',
								'description' => 'Exact term_id from scos_topic taxonomy.',
							],
							'name'           => [
								'type'        => 'string',
								'description' => 'Human-readable topic name.',
							],
							'confidence'     => [
								'type'        => 'string',
								'enum'        => [ 'high', 'medium', 'low' ],
								'description' => 'Confidence level for this topic match.',
							],
							'topic_coverage' => [
								'type'        => 'string',
								'description' => 'Rough estimate of how thoroughly the content covers this topic (e.g. "~70%").',
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Execute the ability — fetch topics and return suggestions.
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

		$content = '';
		$title   = $args['title'] ?? '';

		// ---- Fetch and validate all available scos_topic terms ----
		$raw_terms = get_terms( [
			'taxonomy'   => 'scos_topic',
			'hide_empty' => false,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => 100,
		] );

		if ( is_wp_error( $raw_terms ) || empty( $raw_terms ) ) {
			return new WP_Error(
				'scos_suggest_no_topics',
				__( 'No scos_topic terms found. Create topics first in Content Architecture.', 'site-essentials' )
			);
		}

		// Build a term_id → name map for validation later.
		$term_map = [];
		foreach ( $raw_terms as $term ) {
			$term_map[ (int) $term->term_id ] = $term->name;
		}

		// ---- Fetch post content ----
		if ( $args['post_id'] ) {
			$post = get_post( (int) $args['post_id'] );
			if ( ! $post instanceof \WP_Post ) {
				return new WP_Error(
					'post_not_found',
					/* translators: %d: Post ID. */
					sprintf( esc_html__( 'Post with ID %d not found.', 'site-essentials' ), absint( $args['post_id'] ) )
				);
			}
			$post_context = get_post_context( $post->ID );
			$content      = $post_context['content'] ?? '';
			if ( empty( $title ) && ! empty( $post->post_title ) ) {
				$title = $post->post_title;
			}
		}

		if ( $args['content'] ) {
			$content = normalize_content( $args['content'] );
		}

		if ( empty( $content ) ) {
			return new WP_Error(
				'content_not_provided',
				esc_html__( 'Content is required to suggest topics.', 'site-essentials' )
			);
		}

		// Truncate very long content: keep first 1500 + last 500 words.
		$words = explode( ' ', $content );
		if ( count( $words ) > 2000 ) {
			$content = implode( ' ', array_slice( $words, 0, 1500 ) )
				. ' [...] '
				. implode( ' ', array_slice( $words, -500 ) );
		}

		// ---- Build the available topics list for the prompt ----
		$topic_lines = [];
		foreach ( $raw_terms as $term ) {
			$topic_lines[] = $term->term_id . ': ' . $term->name;
		}
		$available_topics = implode( "\n", $topic_lines );

		// ---- Check for an already-assigned topic (reassessment context) ----
		$current_topic_tag = '';
		if ( $args['post_id'] ) {
			$assigned = get_the_terms( (int) $args['post_id'], 'scos_topic' );
			if ( $assigned && ! is_wp_error( $assigned ) ) {
				$assigned_name    = $assigned[0]->name;
				$current_topic_tag = '<current_topic>' . esc_html( $assigned_name ) . '</current_topic>' . "\n";
			}
		}

		// ---- Build prompt ----
		$prompt  = '<available_topics>' . "\n" . $available_topics . "\n" . '</available_topics>' . "\n";
		$prompt .= $current_topic_tag;
		$prompt .= '<title>' . $title . '</title>' . "\n";
		$prompt .= '<content>' . $content . '</content>';

		$prompt_builder = wp_ai_client_prompt( $prompt )
			->using_system_instruction( $this->get_system_instruction() )
			->using_temperature( 0.3 )
			->using_model_preference( ...get_preferred_models_for_text_generation() );

		$prompt_builder = $this->ensure_text_generation_supported(
			$prompt_builder,
			esc_html__( 'Topic suggestion failed. Please ensure you have a connected provider that supports text generation.', 'site-essentials' )
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

		if ( ! is_array( $parsed ) || empty( $parsed['suggestions'] ) ) {
			return new WP_Error(
				'scos_suggest_topics_parse_error',
				__( 'AI response could not be parsed. Please try again.', 'site-essentials' ),
				[ 'status' => 500 ]
			);
		}

		$valid_confidences = [ 'high', 'medium', 'low' ];
		$suggestions       = [];

		foreach ( $parsed['suggestions'] as $item ) {
			$term_id = isset( $item['term_id'] ) ? (int) $item['term_id'] : 0;

			// Validate the returned term_id exists in our fetched list.
			if ( $term_id <= 0 || ! isset( $term_map[ $term_id ] ) ) {
				continue;
			}

			$confidence = isset( $item['confidence'] ) && in_array( $item['confidence'], $valid_confidences, true )
				? $item['confidence']
				: 'low';

			$suggestions[] = [
				'term_id'        => $term_id,
				'name'           => $term_map[ $term_id ], // use server-side name, not AI-provided
				'confidence'     => $confidence,
				'topic_coverage' => sanitize_text_field( $item['topic_coverage'] ?? '' ),
			];
		}

		if ( empty( $suggestions ) ) {
			return new WP_Error(
				'scos_suggest_topics_no_valid',
				__( 'No valid topic suggestions returned. Please try again.', 'site-essentials' ),
				[ 'status' => 500 ]
			);
		}

		return [ 'suggestions' => $suggestions ];
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
					'scos_suggest_post_not_found',
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

add_action( 'wp_abilities_api_init', [ Suggest_Topics::class, 'register' ] );
