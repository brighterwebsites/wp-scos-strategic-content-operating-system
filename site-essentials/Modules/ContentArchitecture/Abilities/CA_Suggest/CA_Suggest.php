<?php
/**
 * CA Suggest — WordPress Ability
 *
 * Reads post content and suggests search intent goal phrasings for the
 * SCOS Content Architecture meta box.
 *
 * Ability slug: scos/suggest-intent-goal (permanent — do not rename after deployment)
 * Category:     scos-content-architecture
 *
 * @package    SiteEssentials
 * @subpackage Modules\ContentArchitecture\Abilities\CA_Suggest
 *
 * v1.1 | 2026-06-23
 */

declare( strict_types=1 );

namespace SiteEssentials\Modules\ContentArchitecture\Abilities\CA_Suggest;

use WP_Error;
use WordPress\AI\Abstracts\Abstract_Ability;

use function WordPress\AI\get_post_context;
use function WordPress\AI\normalize_content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Suggest extends Abstract_Ability {

	// -------------------------------------------------------------------------
	// Ability API registration
	// -------------------------------------------------------------------------

	/**
	 * Register this ability with the WP Abilities API.
	 *
	 * Called via wp_abilities_api_init hook from Meta_Box::init() after the
	 * class_exists guard confirms both APIs are available.
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
		wp_register_ability( 'scos/suggest-intent-goal', [
			'label'         => __( 'SCOS: Suggest Intent Goal', 'site-essentials' ),
			'description'   => __( 'Reads post content and suggests search intent goal phrasings for the SCOS Content Architecture meta box.', 'site-essentials' ),
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
	 * Guideline categories used by the WP AI plugin for system guidelines.
	 *
	 * @since 1.0.0
	 * @return array<string>
	 */
	protected function guideline_categories(): array {
		return [ 'site', 'copy' ];
	}

	/**
	 * JSON Schema for the input accepted by this ability.
	 *
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
	 * JSON Schema for the output returned by this ability.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'intent_goals' => [
					'type'        => 'array',
					'description' => 'Suggested search intent goal statements, ordered by confidence.',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'goal'       => [
								'type'        => 'string',
								'description' => 'Plain-language question or goal statement.',
							],
							'confidence' => [
								'type'        => 'number',
								'description' => 'Confidence score 0–1.',
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Execute the ability — analyse content and return intent goal suggestions.
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
				esc_html__( 'Content is required to suggest an intent goal.', 'site-essentials' )
			);
		}

		// Truncate very long content: keep first 1500 + last 500 words.
		$words = explode( ' ', $content );
		if ( count( $words ) > 2500 ) {
			$content = implode( ' ', array_slice( $words, 0, 1500 ) )
				. ' [...] '
				. implode( ' ', array_slice( $words, -500 ) );
		}

		$prompt  = '<title>' . $title . '</title>';
		$prompt .= '<content>' . $content . '</content>';

		$prompt_builder = wp_ai_client_prompt( $prompt )
			->using_system_instruction( $this->get_system_instruction() )
			->using_temperature( 0.4 );

		$prompt_builder = $this->ensure_text_generation_supported(
			$prompt_builder,
			esc_html__( 'Intent goal suggestion failed. Please ensure you have a connected provider that supports text generation.', 'site-essentials' )
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

		if ( ! is_array( $parsed ) || empty( $parsed['intent_goals'] ) ) {
			return new WP_Error(
				'scos_suggest_parse_error',
				__( 'AI response could not be parsed. Please try again.', 'site-essentials' ),
				[ 'status' => 500 ]
			);
		}

		return [
			'intent_goals' => array_map(
				function ( $item ) {
					return [
						'goal'       => sanitize_text_field( $item['goal'] ?? '' ),
						'confidence' => isset( $item['confidence'] ) ? (float) $item['confidence'] : 0.0,
					];
				},
				$parsed['intent_goals']
			),
		];
	}

	/**
	 * Permission callback — mirrors the Meta_Description ability pattern.
	 *
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
	 * Ability metadata — REST and MCP exposure.
	 *
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

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

}

// Register on wp_abilities_api_init — fires after both APIs are ready.
add_action( 'wp_abilities_api_init', [ CA_Suggest::class, 'register' ] );
