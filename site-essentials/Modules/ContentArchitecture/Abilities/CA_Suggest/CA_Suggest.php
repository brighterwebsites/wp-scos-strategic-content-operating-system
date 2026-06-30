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
 * v1.2 | 2026-06-24 — Add topic_term_id + existing_intent_goal to input schema; inject <topic> and reassessment context into prompt.
 * v1.3 | 2026-06-24 — Prefer scos_ca_content_md (Breakdance + ACF rendered content) over raw post_content.
 * v1.4 | 2026-06-30 — Query existing FAQs and inject into prompt for deduplication; parse matched_faq from AI response.
 */

declare( strict_types=1 );

namespace SiteEssentials\Modules\ContentArchitecture\Abilities\CA_Suggest;

use WP_Error;
use WordPress\AI\Abstracts\Abstract_Ability;
use SiteEssentials\Modules\ContentArchitecture\Intent_Goal_Resolver;

use function WordPress\AI\get_post_context;
use function WordPress\AI\normalize_content;
use function WordPress\AI\get_preferred_models_for_text_generation;

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
				'post_id'              => [
					'type'        => 'integer',
					'description' => 'The post ID to analyse. When provided, post content is fetched server-side.',
				],
				'content'              => [
					'type'        => 'string',
					'description' => 'Raw post content. Used only when post_id is not provided.',
				],
				'title'                => [
					'type'        => 'string',
					'description' => 'Post title. Secondary signal — content is primary.',
				],
				'topic_term_id'        => [
					'type'        => 'integer',
					'description' => 'Optional. scos_topic term_id to scope intent goal suggestions to a specific topic perspective.',
				],
				'existing_intent_goal' => [
					'type'        => 'string',
					'description' => 'Optional. The currently saved intent goal text or FAQ title. When provided, the AI treats this as a reassessment.',
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
				'matched_faq'  => [
					'type'        => 'object',
					'description' => 'An existing FAQ that matches or closely matches the content\'s search intent. Omitted when no match is found.',
					'properties'  => [
						'faq_id'        => [ 'type' => 'integer', 'description' => 'FAQ post ID.' ],
						'match_quality' => [ 'type' => 'string', 'description' => '"good" = usable as-is. "close" = matches but could be improved.' ],
						'suggested_edit' => [ 'type' => 'string', 'description' => 'Improved title when match_quality is "close". Null otherwise.' ],
						'title'         => [ 'type' => 'string', 'description' => 'Existing FAQ title.' ],
						'status'        => [ 'type' => 'string', 'description' => 'Post status.' ],
						'topic'         => [ 'type' => 'string', 'description' => 'Assigned topic name.' ],
						'edit_url'      => [ 'type' => 'string', 'description' => 'Admin edit URL for the FAQ.' ],
						'incomplete'    => [ 'type' => 'boolean', 'description' => 'True when the FAQ has no answer yet.' ],
					],
				],
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
				'post_id'              => null,
				'content'              => null,
				'title'                => null,
				'topic_term_id'        => null,
				'existing_intent_goal' => null,
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

		$topic_term_id = (int) ( $args['topic_term_id'] ?? 0 );
		$prompt        = '';

		// Topic scoping context — when a topic is selected, scope the intent goal to it.
		if ( $topic_term_id > 0 ) {
			$topic_term = get_term( $topic_term_id, 'scos_topic' );
			if ( $topic_term && ! is_wp_error( $topic_term ) ) {
				$prompt .= '<topic>' . esc_html( $topic_term->name ) . '</topic>' . "\n";
			}
		}

		// Reassessment context — server-side lookup takes priority over client-passed value.
		$current_intent_goal = '';
		if ( $args['post_id'] ) {
			$existing_faq_id = (int) get_post_meta( (int) $args['post_id'], 'scos_ca_intent_goal_faq_id', true );
			if ( $existing_faq_id > 0 ) {
				$existing_faq        = get_post( $existing_faq_id );
				$current_intent_goal = $existing_faq ? $existing_faq->post_title : '';
			}
			if ( empty( $current_intent_goal ) ) {
				$current_intent_goal = (string) get_post_meta( (int) $args['post_id'], 'scos_ca_intent_goal', true );
			}
		}
		if ( empty( $current_intent_goal ) && ! empty( $args['existing_intent_goal'] ) ) {
			$current_intent_goal = sanitize_text_field( $args['existing_intent_goal'] );
		}
		if ( ! empty( $current_intent_goal ) ) {
			$prompt .= '<current_intent_goal>' . esc_html( $current_intent_goal ) . '</current_intent_goal>' . "\n";
		}

		// Existing FAQs — inject for deduplication check.
		$existing_faqs_text = $this->get_existing_faqs_for_prompt( $topic_term_id );
		if ( ! empty( $existing_faqs_text ) ) {
			$prompt .= "<existing_faqs>\n" . $existing_faqs_text . "\n</existing_faqs>\n";
		}

		$prompt .= '<title>' . $title . '</title>' . "\n";
		$prompt .= '<content>' . $content . '</content>';

		$prompt_builder = wp_ai_client_prompt( $prompt )
			->using_system_instruction( $this->get_system_instruction() )
			->using_temperature( 0.4 )
			->using_model_preference( ...get_preferred_models_for_text_generation() );

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

		if ( ! is_array( $parsed ) ) {
			return new WP_Error(
				'scos_suggest_parse_error',
				__( 'AI response could not be parsed. Please try again.', 'site-essentials' ),
				[ 'status' => 500 ]
			);
		}

		// Resolve matched_faq — validate the returned ID against the actual FAQ library.
		$matched_faq = null;
		if ( ! empty( $parsed['matched_faq'] ) && is_array( $parsed['matched_faq'] ) ) {
			$matched_faq_id = (int) ( $parsed['matched_faq']['faq_id'] ?? 0 );
			if ( $matched_faq_id > 0 ) {
				$summary = Intent_Goal_Resolver::get_faq_summary( $matched_faq_id );
				if ( $summary ) {
					$matched_faq = array_merge(
						$summary,
						[
							'match_quality'  => in_array( $parsed['matched_faq']['match_quality'] ?? '', [ 'good', 'close' ], true )
								? $parsed['matched_faq']['match_quality']
								: 'good',
							'suggested_edit' => ! empty( $parsed['matched_faq']['suggested_edit'] )
								? sanitize_text_field( (string) $parsed['matched_faq']['suggested_edit'] )
								: null,
						]
					);
				}
			}
		}

		$intent_goals = ! empty( $parsed['intent_goals'] ) && is_array( $parsed['intent_goals'] )
			? array_map(
				function ( $item ) {
					return [
						'goal'       => sanitize_text_field( $item['goal'] ?? '' ),
						'confidence' => isset( $item['confidence'] ) ? (float) $item['confidence'] : 0.0,
					];
				},
				$parsed['intent_goals']
			)
			: [];

		if ( empty( $intent_goals ) && ! $matched_faq ) {
			return new WP_Error(
				'scos_suggest_parse_error',
				__( 'AI response could not be parsed. Please try again.', 'site-essentials' ),
				[ 'status' => 500 ]
			);
		}

		$response = [ 'intent_goals' => $intent_goals ];
		if ( $matched_faq ) {
			$response['matched_faq'] = $matched_faq;
		}

		return $response;
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

	/**
	 * Build the <existing_faqs> prompt block for deduplication checking.
	 *
	 * Returns up to 30 FAQs tagged with the selected topic first, then up
	 * to 20 more from other topics / no topic. Titles only — no content.
	 * Returns an empty string when the FAQ library is empty.
	 *
	 * @param int $topic_term_id Selected scos_topic term ID, or 0.
	 * @return string Ready-to-inject prompt block, or empty string.
	 */
	private function get_existing_faqs_for_prompt( int $topic_term_id ): string {
		$lines    = [];
		$seen_ids = [];

		// Topic-matched FAQs first so the AI finds the best match early.
		if ( $topic_term_id > 0 ) {
			$topic_faqs = get_posts(
				[
					'post_type'      => 'faq',
					'post_status'    => [ 'publish', 'draft' ],
					'posts_per_page' => 30,
					'no_found_rows'  => true,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'tax_query'      => [
						[
							'taxonomy' => 'scos_topic',
							'field'    => 'term_id',
							'terms'    => $topic_term_id,
						],
					],
				]
			);
			if ( $topic_faqs ) {
				$lines[] = '[Matching topic]';
				foreach ( $topic_faqs as $faq ) {
					$lines[]    = $faq->ID . ': ' . $faq->post_title;
					$seen_ids[] = $faq->ID;
				}
			}
		}

		// All remaining FAQs — different topics or none assigned.
		$other_args = [
			'post_type'      => 'faq',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => 20,
			'no_found_rows'  => true,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];
		if ( ! empty( $seen_ids ) ) {
			$other_args['post__not_in'] = $seen_ids;
		}
		$other_faqs = get_posts( $other_args );
		if ( $other_faqs ) {
			$lines[] = $topic_term_id > 0 ? '[Other topics / no topic]' : '[All FAQs]';
			foreach ( $other_faqs as $faq ) {
				$lines[] = $faq->ID . ': ' . $faq->post_title;
			}
		}

		// Only 2 lines means only section headers — no actual FAQs.
		if ( count( $lines ) <= ( $topic_term_id > 0 ? 1 : 1 ) ) {
			return '';
		}

		// Need at least one actual FAQ line (not just headers).
		$faq_lines = array_filter( $lines, fn( $l ) => false !== strpos( $l, ': ' ) );
		if ( empty( $faq_lines ) ) {
			return '';
		}

		return implode( "\n", $lines );
	}

}

// Register on wp_abilities_api_init — fires after both APIs are ready.
add_action( 'wp_abilities_api_init', [ CA_Suggest::class, 'register' ] );
