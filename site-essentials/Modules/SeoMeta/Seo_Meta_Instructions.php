<?php
/**
 * SEO Meta — Writing Instructions
 *
 * The one place that says how SCOS SEO meta is written: which meta keys, how
 * long, and how to write each field for a given content type. Anything that
 * writes SEO meta follows these — the in-editor Suggest button, an MCP agent,
 * a site plugin generating on save.
 *
 * This class is the *what*. How the result is used (three options for a person
 * to pick from, one value written unattended) belongs to the caller.
 *
 * Exposed to agents as the scos/get-seo-meta-instructions ability and to
 * WP-CLI as `wp scos seo-meta-instructions`.
 *
 * What is shared with every other kind of writing — the business, the brand
 * voice, the vocabulary to avoid, the page's purpose — comes from
 * Core\Writing_Context.
 *
 * Site plugins extend or override the result through the
 * `scos_seo_meta_instructions` filter — that is where site-specific rules and
 * post-specific facts belong, not in this file.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 *
 * v1.0 | 2026-09-30
 * v1.1 | 2026-09-30 — Business context and page purpose in the result; purpose picks the content type;
 *                      products never mention price or stock; shared parts moved to Writing_Context.
 * v1.2 | 2026-09-30 — TLDR is a field here too; the post's search intent goal is returned.
 */

declare( strict_types=1 );

namespace SiteEssentials\Modules\SeoMeta;

use SiteEssentials\Core\Writing_Context;
use SiteEssentials\Core\Abilities\Ability_Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Meta_Instructions {

	/** Bump when the shipped rules change, so consumers can tell. */
	const VERSION = '1.2';

	/**
	 * Build the instructions for a post, a post type, or the site in general.
	 *
	 * @param array<string,mixed> $args {
	 *     All optional.
	 *
	 *     @type int      $post_id      Post the meta is for. Sets the content type and adds current values.
	 *     @type string   $post_type    Post type, when there is no post yet.
	 *     @type string   $content_type Force a content type instead of deriving it from the page purpose or post type.
	 *     @type string[] $fields       Limit to these fields: breadcrumb_title, title, description, tldr.
	 * }
	 * @return array<string,mixed>
	 */
	public static function get( array $args = [] ): array {
		$post_id   = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		$post      = $post_id ? get_post( $post_id ) : null;
		$post_type = $post instanceof \WP_Post
			? $post->post_type
			: ( isset( $args['post_type'] ) ? sanitize_key( (string) $args['post_type'] ) : '' );

		$purpose      = Writing_Context::purpose( $post instanceof \WP_Post ? $post->ID : 0 );
		$content_type = ! empty( $args['content_type'] )
			? sanitize_key( (string) $args['content_type'] )
			: Writing_Context::content_type( $post_type, $purpose['key'] );

		$type_rules = self::type_rules( $content_type );
		$wanted     = ! empty( $args['fields'] ) && is_array( $args['fields'] )
			? array_map( 'sanitize_key', $args['fields'] )
			: [];

		$fields = [];
		foreach ( self::fields() as $key => $field ) {
			if ( $wanted && ! in_array( $key, $wanted, true ) ) {
				continue;
			}
			$field['type_rules'] = $type_rules[ $key ] ?? [];
			if ( $post instanceof \WP_Post ) {
				$field['current'] = (string) get_post_meta( $post->ID, $field['meta_key'], true );
			}
			$fields[ $key ] = $field;
		}

		$voice = Writing_Context::voice();

		$instructions = [
			'version'              => self::VERSION,
			'content_type'         => $content_type,
			'post_type'            => $post_type,
			'post'                 => $post instanceof \WP_Post
				? [
					'id'    => $post->ID,
					'title' => $post->post_title,
				]
				: [],
			'business'             => Writing_Context::business(),
			'purpose'              => $purpose,
			'intent_goal'          => Writing_Context::intent_goal( $post instanceof \WP_Post ? $post->ID : 0 ),
			'fields'               => $fields,
			'general_rules'        => self::general_rules(),
			'avoid'                => Writing_Context::avoid(),
			'voice'                => $voice,
			'editorial_guidelines' => Ability_Support::get_guidelines_for_prompt( [ 'site', 'copy' ] ),
			'facts'                => [],
			'saving'               => [
				'Write each value to the meta_key given for its field, as post meta on the post.',
				'Values are plain text unless the field\'s format says otherwise. No markdown, no surrounding quotes.',
				'Count before saving. A value outside its min–max is wrong, not close enough.',
				'Never write to _seopress_* keys.',
			],
			'notes'                => $voice['found'] ? [] : [ Writing_Context::missing_voice_note() ],
		];

		/**
		 * Filters the SEO meta writing instructions.
		 *
		 * Site plugins use this to add site-specific rules, override limits, or
		 * supply post-specific facts in `facts` (a list of short strings, e.g.
		 * a product's common name and size).
		 *
		 * @param array<string,mixed> $instructions The instructions.
		 * @param array<string,mixed> $args         The arguments passed to get().
		 */
		return (array) apply_filters( 'scos_seo_meta_instructions', $instructions, $args );
	}

	/**
	 * The SEO meta fields: where each is stored, its limits, and how to write it.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function fields(): array {
		return [
			'breadcrumb_title' => [
				'meta_key' => 'scos_seo_breadcrumb_title',
				'label'    => 'Breadcrumb label',
				'unit'     => 'words',
				'min'      => 2,
				'max'      => 5,
				'format'   => 'plain text',
				'rules'    => [
					'A short navigation label, plain text.',
					'Reflects the page topic, not a marketing phrase.',
					'No punctuation, no quotes.',
				],
			],
			'title'            => [
				'meta_key' => 'scos_seo_title',
				'label'    => 'Meta title',
				'unit'     => 'characters',
				'min'      => 50,
				'max'      => 60,
				'target'   => 55,
				'format'   => 'plain text',
				'rules'    => [
					'Hard limit — count every character including spaces.',
					'Must differ meaningfully from the post title: add an angle, an audience or an outcome.',
					'Include one specific entity naturally if it fits (topic, then location, then brand — in that priority).',
					'Informational content: [Topic]: [Unique Angle] for [Audience].',
					'How-to content: How to [Achieve Outcome]: [Method].',
					'Commercial content: [Service/Outcome] for [Audience] | [Brand or Location].',
				],
			],
			'description'      => [
				'meta_key' => 'scos_seo_description',
				'label'    => 'Meta description',
				'unit'     => 'characters',
				'min'      => 150,
				'max'      => 160,
				'target'   => 155,
				'format'   => 'plain text',
				'rules'    => [
					'Hard limit — count every character.',
					'The first 60 characters carry the most specific differentiator: a proof point, a number, a named entity or a unique claim.',
					'The rest expands on the promise of the title and adds method or context.',
					'End with a soft outcome or action signal.',
					'Must not repeat the title verbatim.',
				],
			],
			'tldr'             => [
				'meta_key' => 'scos_seo_tldr',
				'label'    => 'TLDR summary',
				'unit'     => 'sentences',
				'min'      => 2,
				'max'      => 4,
				'format'   => 'plain text; bold, lists and links are allowed as simple HTML',
				'rules'    => [
					'Direct and voice-search friendly — written as if answering a spoken question.',
					'Lead with the most specific claim, outcome or differentiator from the content.',
					'Reference the actual content. Do not generalise, and do not restate the title.',
					'No marketing filler — write for the reader, not for the brand.',
					'When the page has a search question to answer, answer it directly in the opening sentence; the remaining sentences add specifics from the content.',
				],
			],
		];
	}

	/**
	 * Rules that apply to every field.
	 *
	 * @return string[]
	 */
	public static function general_rules(): array {
		return [
			'Base all copy on the substance of the content, not on title keywords alone.',
			'Reflect what the reader actually needs.',
			'No invented claims — nothing that is not present in the content or the supplied facts.',
		];
	}

	/**
	 * Extra rules per field for one content type.
	 *
	 * @param string $content_type e.g. `product`.
	 * @return array<string,string[]> Keyed by field. Empty for an unknown type.
	 */
	public static function type_rules( string $content_type ): array {
		// Prices and stock change after the meta is written, so they go stale
		// in search results. A site that regenerates on every price change can
		// lift this through the scos_seo_meta_instructions filter.
		$no_price_or_stock = 'Never mention price or stock availability, unless the site\'s facts or instructions direct otherwise.';

		$rules = [
			'article'    => [
				'title'       => [
					'Lead with the question or topic the article answers.',
				],
				'description' => [
					'State the specific takeaway the reader leaves with.',
				],
			],
			'page'       => [
				'title'       => [
					'Say plainly what the page is for.',
				],
				'description' => [
					'Summarise what the visitor can find or do on the page.',
				],
			],
			'service'    => [
				'title'       => [
					'Name the service, and the area served when the content gives one.',
				],
				'description' => [
					'Say who the service is for and the outcome they get.',
					'End with a clear next step, such as getting a quote or making an enquiry.',
				],
			],
			'product'    => [
				'breadcrumb_title' => [
					'Use the product name, shortened if needed.',
				],
				'title'            => [
					'Lead with the product name and the variant that defines it (size, model, capacity) as given in the content or facts.',
					$no_price_or_stock,
				],
				'description'      => [
					'Say what the product is and what or who it suits, in plain language a shop assistant would use.',
					'End with a simple, clear call to action.',
					$no_price_or_stock,
				],
				'tldr'             => [
					$no_price_or_stock,
				],
			],
			'case-study' => [
				'title'       => [
					'Name the type of project and the result or the location.',
				],
				'description' => [
					'Lead with what was done and the outcome, using a number where the content gives one.',
				],
			],
		];

		return $rules[ $content_type ] ?? [];
	}

	/**
	 * Render instructions as plain text for a system prompt.
	 *
	 * Leaves out `editorial_guidelines`: Abstract_Scos_Ability appends those
	 * to every ability's system instruction itself.
	 *
	 * @param array<string,mixed> $instructions Result of get().
	 * @return string
	 */
	public static function to_prompt( array $instructions ): string {
		return Writing_Context::fields_to_prompt(
			(array) ( $instructions['fields'] ?? [] ),
			(string) ( $instructions['content_type'] ?? '' )
		) . Writing_Context::to_prompt( $instructions );
	}
}
