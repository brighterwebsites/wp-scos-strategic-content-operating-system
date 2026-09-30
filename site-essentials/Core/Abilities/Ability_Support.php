<?php
/**
 * SCOS Ability support helpers.
 *
 * SCOS-owned replacements for the handful of `WordPress\AI\*` helper functions
 * the abilities used to call directly. Each one degrades cleanly when the `ai`
 * plugin is absent, so an ability can always be registered and executed even
 * when text generation is not possible on that site.
 *
 * Provider and model selection stays with the WordPress AI Client: when the
 * client is present its preferred-model list is used verbatim, and when it is
 * absent no model preference is applied at all. No model string is ever
 * declared here — see CLAUDE.md section 6.
 *
 * @package    SiteEssentials
 * @subpackage Core\Abilities
 * @since      1.3.0
 *
 * v1.0 | 2026-09-29
 * v1.1 | 2026-09-30 — get_post_content_for_prompt() renders the page or parses builder data when no stored markdown exists.
 */

declare( strict_types=1 );

namespace SiteEssentials\Core\Abilities;

use WP_Error;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ability_Support {

	/**
	 * Whether the WordPress core Abilities API is available.
	 *
	 * This is the only requirement for registering a SCOS ability. Shipped in
	 * core since WP 6.9 (`wp-includes/abilities-api/`).
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	public static function is_abilities_api_available(): bool {
		return class_exists( 'WP_Ability' ) && function_exists( 'wp_register_ability' );
	}

	/**
	 * Whether the WordPress AI Client is available for text generation.
	 *
	 * Abilities that generate server-side must check this before prompting, and
	 * return a WP_Error rather than fatalling when it is false.
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	public static function is_ai_client_available(): bool {
		return function_exists( 'wp_ai_client_prompt' );
	}

	/**
	 * Build a text prompt via the WordPress AI Client.
	 *
	 * Applies the client's own preferred-model list when it exposes one, and
	 * applies no model preference otherwise. Returns a WP_Error when the client
	 * is not installed so callers have a single failure path.
	 *
	 * @since 1.3.0
	 *
	 * @param string      $prompt            The user prompt.
	 * @param string      $system_instruction System instruction for the request.
	 * @param float       $temperature       Sampling temperature.
	 * @param string|null $error_message     Optional user-visible message for the unavailable case.
	 * @return mixed|WP_Error Prompt builder, or WP_Error when unavailable.
	 */
	public static function text_prompt(
		string $prompt,
		string $system_instruction = '',
		float $temperature = 0.4,
		?string $error_message = null
	) {
		if ( ! self::is_ai_client_available() ) {
			return new WP_Error(
				'scos_ai_client_unavailable',
				$error_message ?? esc_html__( 'AI text generation is not available on this site. Install and configure an AI provider, or pass the content in directly.', 'site-essentials' ),
				array( 'status' => 503 )
			);
		}

		$prompt_builder = wp_ai_client_prompt( $prompt );

		if ( '' !== $system_instruction ) {
			$prompt_builder = $prompt_builder->using_system_instruction( $system_instruction );
		}

		$prompt_builder = $prompt_builder->using_temperature( $temperature );

		$models = self::preferred_text_models();
		if ( ! empty( $models ) ) {
			$prompt_builder = $prompt_builder->using_model_preference( ...$models );
		}

		return $prompt_builder;
	}

	/**
	 * Preferred models for text generation, as resolved by the AI Client.
	 *
	 * Returns an empty array when the client is absent so that callers skip
	 * `using_model_preference()` entirely rather than substituting a list.
	 *
	 * @since 1.3.0
	 * @return array<int, array{string, string}>
	 */
	public static function preferred_text_models(): array {
		if ( ! function_exists( 'WordPress\AI\get_preferred_models_for_text_generation' ) ) {
			return array();
		}

		return (array) \WordPress\AI\get_preferred_models_for_text_generation();
	}

	/**
	 * Formatted editorial guidelines for prompt injection.
	 *
	 * The guidelines store belongs to the `ai` plugin, so this returns an empty
	 * string when the plugin is absent.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string> $categories Guideline categories to include.
	 * @param string|null   $block_name Optional block name for block guidelines.
	 * @return string Formatted guidelines, or an empty string.
	 */
	public static function get_guidelines_for_prompt( array $categories, ?string $block_name = null ): string {
		if ( empty( $categories ) ) {
			return '';
		}

		if ( ! function_exists( 'WordPress\AI\format_guidelines_for_prompt' ) ) {
			return '';
		}

		$allowed = array_values(
			array_intersect( $categories, array( 'site', 'copy', 'images', 'additional' ) )
		);

		if ( empty( $allowed ) ) {
			return '';
		}

		return (string) \WordPress\AI\format_guidelines_for_prompt( $allowed, $block_name );
	}

	/**
	 * Normalise content to plain text suitable for a prompt.
	 *
	 * Strips entities, converts breaks to spaces, removes markup and unwrapped
	 * shortcodes. Mirrors what the abilities previously got from
	 * `WordPress\AI\normalize_content()`.
	 *
	 * @since 1.3.0
	 *
	 * @param string $content Raw content.
	 * @return string Normalised plain text.
	 */
	public static function normalize_content( string $content ): string {
		// Strip HTML entities.
		$content = preg_replace( '/&#?[a-z0-9]{2,8};/i', '', $content ) ?? $content;

		// HTML line breaks become paragraph breaks.
		$content = preg_replace( '#<br\s?/?>#', "\n\n", $content ) ?? $content;

		// Collapse newlines to spaces so sentences don't run together.
		$content = str_replace( array( "\r", "\n" ), ' ', (string) $content );

		$content = wp_strip_all_tags( (string) $content );

		// Unwrap shortcodes that were never rendered, keeping their inner text.
		$content = preg_replace( '#\[.+\](.+)\[/.+\]#', '$1', $content ) ?? $content;

		/**
		 * Filters SCOS-normalised content before it reaches a prompt.
		 *
		 * @since 1.3.0
		 *
		 * @param string $content The normalised content.
		 */
		return trim( (string) apply_filters( 'scos_ability_normalize_content', (string) $content ) );
	}

	/**
	 * Context for a post, for use as prompt input.
	 *
	 * Replaces `WordPress\AI\get_post_context()`. Returns the rendered,
	 * normalised content plus basic identifying fields.
	 *
	 * No capability check is performed here — callers must run their own before
	 * exposing this data, the same contract the previous helper carried.
	 *
	 * @since 1.3.0
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,string>
	 */
	public static function get_post_context( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$content = (string) $post->post_content;
		if ( '' !== $content ) {
			/** This filter is documented in wp-includes/post-template.php */
			$content = (string) apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}

		return array_filter(
			array(
				'id'           => (string) $post->ID,
				'title'        => (string) $post->post_title,
				'excerpt'      => self::normalize_content( (string) $post->post_excerpt ),
				'content'      => self::normalize_content( $content ),
				'content_type' => (string) $post->post_type,
				'link'         => (string) get_permalink( $post ),
			)
		);
	}

	/**
	 * Rendered content for a post, preferring the pre-built markdown.
	 *
	 * Builder pages keep nothing in post_content, so reading that alone leaves
	 * a Breakdance page with no content to write from. In order:
	 *
	 * 1. `scos_ca_content_md` — written by the Content Architecture analysis;
	 *    covers Breakdance, ACF, Query Loops and Post Repeaters.
	 * 2. A live render of the published page, for posts that analysis has not
	 *    reached (module off, never analysed). Cached by the extractor.
	 * 3. The builder data and ACF fields parsed directly — the only source for
	 *    a draft, which has no public URL to render.
	 * 4. post_content.
	 *
	 * @since 1.3.0
	 *
	 * @param int $post_id Post ID.
	 * @return string Content, or an empty string when there is none.
	 */
	public static function get_post_content_for_prompt( int $post_id ): string {
		$markdown = (string) get_post_meta( $post_id, 'scos_ca_content_md', true );

		if ( '' !== $markdown ) {
			return $markdown;
		}

		$extractor = '\SiteEssentials\Modules\ContentArchitecture\Rendered_Content_Extractor';
		if ( class_exists( $extractor ) ) {
			$markdown = trim( (string) $extractor::get_markdown( $post_id ) );
			if ( '' !== $markdown ) {
				return $markdown;
			}
		}

		// TODO: migrate to site-essentials — the builder-data parser still lives in brighter-core.
		if ( class_exists( '\BW_Content_Analysis' ) && function_exists( 'bw_cs_post_types' ) ) {
			$aggregated = self::normalize_content( (string) \BW_Content_Analysis::get_aggregated_content( $post_id ) );
			if ( '' !== $aggregated ) {
				return $aggregated;
			}
		}

		$context = self::get_post_context( $post_id );

		return $context['content'] ?? '';
	}
}
