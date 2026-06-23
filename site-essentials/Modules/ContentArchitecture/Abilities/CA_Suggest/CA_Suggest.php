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
 * v1.0 | 2026-06-23
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
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		$content = isset( $input['content'] ) ? (string) $input['content'] : '';
		$title   = isset( $input['title'] ) ? (string) $input['title'] : '';

		if ( $post_id > 0 ) {
			$context = get_post_context( $post_id );
			if ( is_wp_error( $context ) ) {
				return $context;
			}
			$content = $context['content'] ?? '';
			if ( empty( $title ) ) {
				$post  = get_post( $post_id );
				$title = $post ? $post->post_title : '';
			}
		} elseif ( ! empty( $content ) ) {
			$content = normalize_content( $content );
		} else {
			return new WP_Error(
				'scos_suggest_missing_input',
				__( 'Provide either post_id or content.', 'site-essentials' ),
				[ 'status' => 400 ]
			);
		}

		// Truncate very long content: keep first 1500 + last 500 words.
		$words      = explode( ' ', $content );
		$word_count = count( $words );
		if ( $word_count > 2500 ) {
			$first   = array_slice( $words, 0, 1500 );
			$last    = array_slice( $words, -500 );
			$content = implode( ' ', $first ) . ' [...] ' . implode( ' ', $last );
		}

		$prompt = '<title>' . esc_html( $title ) . '</title>' . "\n\n"
			. '<content>' . $content . '</content>';

		$this->ensure_text_generation_supported();

		$result = wp_ai_client_prompt( $prompt )
			->using_system_instruction( $this->get_system_instruction() )
			->using_temperature( 0.4 )
			->as_json_response( $this->suggestions_schema() )
			->generate_text();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$parsed = json_decode( $result, true );

		if ( ! is_array( $parsed ) || empty( $parsed['intent_goals'] ) ) {
			return new WP_Error(
				'scos_suggest_parse_error',
				__( 'AI response could not be parsed. Please try again.', 'site-essentials' ),
				[ 'status' => 500 ]
			);
		}

		return [
			'intent_goals' => array_map( function( $item ) {
				return [
					'goal'       => sanitize_text_field( $item['goal'] ?? '' ),
					'confidence' => isset( $item['confidence'] ) ? (float) $item['confidence'] : 0.0,
				];
			}, $parsed['intent_goals'] ),
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
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => false,
			],
		];
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * JSON Schema passed to as_json_response() for structured AI output.
	 *
	 * Mirrors output_schema() but with additionalProperties: false for strict
	 * enforcement on the AI response side.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	private function suggestions_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'intent_goals' => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'properties'           => [
							'goal'       => [ 'type' => 'string' ],
							'confidence' => [ 'type' => 'number' ],
						],
						'required'             => [ 'goal', 'confidence' ],
						'additionalProperties' => false,
					],
				],
			],
			'required'             => [ 'intent_goals' ],
			'additionalProperties' => false,
		];
	}
}

// Register on wp_abilities_api_init — fires after both APIs are ready.
add_action( 'wp_abilities_api_init', [ CA_Suggest::class, 'register' ] );
