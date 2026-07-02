<?php
/**
 * Fill Image Meta — WordPress Ability
 *
 * Generates alt text and title for one or more attachment posts in a single
 * AI call. Images are batched by their post_parent so parent post context
 * (title + scos_seo_tldr / first 500 words of content) is fetched once per
 * group rather than once per image.
 *
 * Ability slug: scos/fill-image-meta (permanent — do not rename after deployment)
 * Category:     scos-media
 *
 * Fields written:
 *   _wp_attachment_image_alt — alt text (post meta)
 *   post_title               — short findable title (attachment post field)
 *   attachment_category      — MLA taxonomy term (if registered + categories available)
 *   attachment_tag           — MLA taxonomy term (when parent is a Projects post)
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\Abilities\Fill_Image_Meta
 *
 * v1.0 | 2026-07-01
 */

declare( strict_types=1 );

namespace SiteEssentials\Modules\SeoMeta\Abilities\Fill_Image_Meta;

use WP_Error;
use WordPress\AI\Abstracts\Abstract_Ability;

use function WordPress\AI\get_preferred_models_for_text_generation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fill_Image_Meta extends Abstract_Ability {

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
		wp_register_ability( 'scos/fill-image-meta', [
			'label'         => __( 'SCOS: Fill Image Meta', 'site-essentials' ),
			'description'   => __( 'Generates alt text and titles for one or more attachment images. Batches images by post_parent for efficiency — one AI call per parent group.', 'site-essentials' ),
			'category'      => 'scos-media',
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
	 * JSON Schema for the input accepted by this ability.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'attachment_ids' => [
					'type'        => 'array',
					'description' => 'Required. IDs of attachment posts to process. All should share the same post_parent for optimal efficiency.',
					'items'       => [ 'type' => 'integer' ],
				],
				'parent_post_id' => [
					'type'        => 'integer',
					'description' => 'Optional. The shared post_parent of the attachments. When provided, parent post context (title + TLDR) is injected into the prompt.',
				],
				'overwrite' => [
					'type'        => 'boolean',
					'description' => 'Optional. When true, overwrites existing alt text and titles. Default false (fill-empty-only).',
				],
			],
			'required' => [ 'attachment_ids' ],
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
				'results' => [
					'type'        => 'array',
					'description' => 'Per-image results, one entry per processed attachment.',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'id'       => [ 'type' => 'integer', 'description' => 'Attachment post ID.' ],
							'alt'      => [ 'type' => 'string',  'description' => 'Generated alt text.' ],
							'title'    => [ 'type' => 'string',  'description' => 'Generated media title (3–5 words).' ],
							'category' => [ 'type' => 'string',  'description' => 'Assigned attachment_category slug.' ],
							'tag'      => [ 'type' => 'string',  'description' => 'Assigned attachment_tag name (projects only).' ],
							'skipped'  => [ 'type' => 'boolean', 'description' => 'True when the image already had metadata and overwrite was false.' ],
						],
					],
				],
				'processed' => [ 'type' => 'integer', 'description' => 'Number of images updated.' ],
				'skipped'   => [ 'type' => 'integer', 'description' => 'Number of images skipped (already had meta, overwrite=false).' ],
				'errors'    => [ 'type' => 'integer', 'description' => 'Number of images that failed to save.' ],
			],
		];
	}

	/**
	 * Execute the ability — generate and optionally save image metadata.
	 *
	 * @since 1.0.0
	 * @param mixed $input Validated input array.
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute_callback( $input ) {
		$args = wp_parse_args(
			$input,
			[
				'attachment_ids' => [],
				'parent_post_id' => null,
				'overwrite'      => false,
			]
		);

		$raw_ids     = (array) ( $args['attachment_ids'] ?? [] );
		$attachment_ids = array_filter( array_map( 'absint', $raw_ids ) );

		if ( empty( $attachment_ids ) ) {
			return new WP_Error(
				'no_attachments',
				esc_html__( 'No attachment IDs provided.', 'site-essentials' )
			);
		}

		$overwrite      = ! empty( $args['overwrite'] );
		$parent_post_id = ! empty( $args['parent_post_id'] ) ? absint( $args['parent_post_id'] ) : 0;

		// ── Resolve attachments ───────────────────────────────────────────────

		$attachments_to_process = [];
		$skipped_results        = [];

		foreach ( $attachment_ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || 'attachment' !== $post->post_type ) {
				continue;
			}
			if ( ! wp_attachment_is_image( $id ) ) {
				continue;
			}

			$existing_alt   = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			$filename_stem  = pathinfo( (string) get_attached_file( $id ), PATHINFO_FILENAME );
			$current_title  = $post->post_title;
			$title_is_empty = empty( $current_title ) || $current_title === $filename_stem;
			$alt_is_empty   = '' === $existing_alt;

			if ( ! $overwrite && ! $alt_is_empty && ! $title_is_empty ) {
				$skipped_results[] = [ 'id' => $id, 'skipped' => true ];
				continue;
			}

			$url = wp_get_attachment_url( $id );
			if ( ! $url ) {
				continue;
			}

			$attachments_to_process[] = [
				'id'            => $id,
				'url'           => $url,
				'existing_alt'  => $existing_alt,
				'current_title' => $current_title,
			];
		}

		if ( empty( $attachments_to_process ) ) {
			return [
				'results'   => $skipped_results,
				'processed' => 0,
				'skipped'   => count( $skipped_results ),
				'errors'    => 0,
			];
		}

		// ── Gather parent post context ────────────────────────────────────────

		$parent_context = $this->get_parent_context( $parent_post_id );

		// ── Gather MLA attachment_category terms ──────────────────────────────

		$category_terms_block = $this->get_category_terms_block();

		// ── Build prompt ──────────────────────────────────────────────────────

		$prompt = $this->build_prompt( $attachments_to_process, $parent_context, $category_terms_block );

		// ── Call AI ───────────────────────────────────────────────────────────

		$prompt_builder = wp_ai_client_prompt( $prompt )
			->using_system_instruction( $this->get_system_instruction() )
			->using_temperature( 0.4 )
			->using_model_preference( ...get_preferred_models_for_text_generation() );

		$prompt_builder = $this->ensure_text_generation_supported(
			$prompt_builder,
			esc_html__( 'Image meta generation failed. Please ensure you have a connected provider that supports text generation.', 'site-essentials' )
		);

		if ( is_wp_error( $prompt_builder ) ) {
			return $prompt_builder;
		}

		$result = $prompt_builder->generate_text();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// ── Parse AI response ─────────────────────────────────────────────────

		$json_str = preg_replace( '/^```(?:json)?\s*/i', '', trim( (string) $result ) );
		$json_str = preg_replace( '/\s*```$/', '', $json_str );
		$parsed   = json_decode( $json_str, true );

		if ( ! is_array( $parsed ) || empty( $parsed['images'] ) || ! is_array( $parsed['images'] ) ) {
			return new WP_Error(
				'scos_fill_image_meta_parse_error',
				__( 'AI response could not be parsed. Please try again.', 'site-essentials' ),
				[ 'status' => 500 ]
			);
		}

		// ── Save to WordPress ─────────────────────────────────────────────────

		$processed_count = 0;
		$error_count     = 0;
		$saved_results   = [];
		$has_tag         = $parent_context['is_project'] ?? false;

		// Index AI output by ID for quick lookup.
		$ai_by_id = [];
		foreach ( $parsed['images'] as $item ) {
			if ( isset( $item['id'] ) ) {
				$ai_by_id[ (int) $item['id'] ] = $item;
			}
		}

		foreach ( $attachments_to_process as $att ) {
			$id   = $att['id'];
			$data = $ai_by_id[ $id ] ?? null;

			if ( ! $data ) {
				$error_count++;
				continue;
			}

			$alt      = sanitize_text_field( $data['alt'] ?? '' );
			$title    = sanitize_text_field( $data['title'] ?? '' );
			$cat_slug = sanitize_key( $data['category'] ?? '' );
			$tag_name = isset( $data['tag'] ) ? sanitize_text_field( $data['tag'] ) : '';

			$result_entry = [ 'id' => $id ];
			$ok           = true;

			// Save alt text.
			if ( '' !== $alt ) {
				update_post_meta( $id, '_wp_attachment_image_alt', $alt );
				$result_entry['alt'] = $alt;
			}

			// Save title (wp_update_post only if changed — avoids touching post_modified).
			if ( '' !== $title ) {
				$update = wp_update_post( [
					'ID'         => $id,
					'post_title' => $title,
				], true );
				if ( is_wp_error( $update ) ) {
					$ok = false;
					error_log( 'scos/fill-image-meta: wp_update_post failed for ID ' . $id . ': ' . $update->get_error_message() );
				} else {
					$result_entry['title'] = $title;
				}
			}

			// Assign attachment_category via MLA taxonomy.
			if ( '' !== $cat_slug && taxonomy_exists( 'attachment_category' ) ) {
				$term = get_term_by( 'slug', $cat_slug, 'attachment_category' );
				if ( $term && ! is_wp_error( $term ) ) {
					wp_set_object_terms( $id, [ $term->term_id ], 'attachment_category', false );
					$result_entry['category'] = $cat_slug;
				}
			}

			// Assign attachment_tag when parent is a Project.
			if ( $has_tag && '' !== $tag_name && taxonomy_exists( 'attachment_tag' ) ) {
				wp_set_object_terms( $id, [ $tag_name ], 'attachment_tag', false );
				$result_entry['tag'] = $tag_name;
			}

			if ( $ok ) {
				$processed_count++;
				$saved_results[] = $result_entry;
			} else {
				$error_count++;
			}
		}

		return [
			'results'   => array_merge( $saved_results, $skipped_results ),
			'processed' => $processed_count,
			'skipped'   => count( $skipped_results ),
			'errors'    => $error_count,
		];
	}

	/**
	 * Permission callback — requires upload_files capability.
	 *
	 * @since 1.0.0
	 * @param mixed $input Validated input array.
	 * @return bool|WP_Error
	 */
	public function permission_callback( $input ) {
		return current_user_can( 'upload_files' );
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
	 * Fetch parent post context: title, TLDR (or truncated content), and project flag.
	 *
	 * @param int $parent_post_id 0 for unattached images.
	 * @return array{title: string, tldr: string, is_project: bool}
	 */
	private function get_parent_context( int $parent_post_id ): array {
		$default = [ 'title' => '', 'tldr' => '', 'is_project' => false ];

		if ( $parent_post_id <= 0 ) {
			return $default;
		}

		$parent = get_post( $parent_post_id );
		if ( ! $parent instanceof \WP_Post ) {
			return $default;
		}

		$title = $parent->post_title;

		// Prefer scos_seo_tldr; fall back to first 500 words of post_content.
		$tldr = (string) get_post_meta( $parent_post_id, 'scos_seo_tldr', true );
		if ( empty( $tldr ) ) {
			$words = explode( ' ', wp_strip_all_tags( $parent->post_content ) );
			$tldr  = implode( ' ', array_slice( $words, 0, 500 ) );
		}

		$is_project = ( 'projects' === $parent->post_type );

		return [
			'title'      => $title,
			'tldr'       => $tldr,
			'is_project' => $is_project,
			'project_title' => $is_project ? $title : '',
		];
	}

	/**
	 * Fetch all attachment_category terms with descriptions for the AI to choose from.
	 * Returns an empty string when the taxonomy is not registered or has no terms.
	 *
	 * @return string XML-tagged block ready to inject into prompt, or ''.
	 */
	private function get_category_terms_block(): string {
		if ( ! taxonomy_exists( 'attachment_category' ) ) {
			return '';
		}

		$terms = get_terms( [
			'taxonomy'   => 'attachment_category',
			'hide_empty' => false,
			'number'     => 100,
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		$lines = [];
		foreach ( $terms as $term ) {
			$desc  = ! empty( $term->description ) ? ' — ' . $term->description : '';
			$lines[] = $term->slug . $desc;
		}

		return "<categories>\n" . implode( "\n", $lines ) . "\n</categories>";
	}

	/**
	 * Build the text prompt passed to the AI.
	 *
	 * @param array<int, array{id: int, url: string}> $attachments
	 * @param array{title: string, tldr: string, is_project: bool, project_title: string} $parent_context
	 * @param string $category_terms_block
	 * @return string
	 */
	private function build_prompt( array $attachments, array $parent_context, string $category_terms_block ): string {
		$prompt = '';

		// Parent post context.
		if ( ! empty( $parent_context['title'] ) ) {
			$prompt .= '<parent_post_title>' . esc_html( $parent_context['title'] ) . '</parent_post_title>' . "\n";
		}
		if ( ! empty( $parent_context['tldr'] ) ) {
			$prompt .= '<parent_post_context>' . esc_html( $parent_context['tldr'] ) . '</parent_post_context>' . "\n";
		}
		if ( ! empty( $parent_context['project_title'] ) ) {
			$prompt .= '<is_project>true</is_project>' . "\n";
			$prompt .= '<project_title>' . esc_html( $parent_context['project_title'] ) . '</project_title>' . "\n";
		}

		// Category terms.
		if ( '' !== $category_terms_block ) {
			$prompt .= $category_terms_block . "\n";
		}

		// Images list — IDs + URLs for vision-capable models.
		$prompt .= "<images>\n";
		foreach ( $attachments as $att ) {
			$prompt .= '<image id="' . absint( $att['id'] ) . '">' . esc_url( $att['url'] ) . '</image>' . "\n";
		}
		$prompt .= "</images>\n";

		$prompt .= 'Generate alt text and title for each image listed above. Return the JSON object as specified.';

		return $prompt;
	}
}

// Register on wp_abilities_api_init — fires after both APIs are ready.
add_action( 'wp_abilities_api_init', [ Fill_Image_Meta::class, 'register' ] );
