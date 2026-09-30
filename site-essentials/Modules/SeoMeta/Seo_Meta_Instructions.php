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
 * Site plugins extend or override the result through the
 * `scos_seo_meta_instructions` filter — that is where site-specific rules and
 * post-specific facts belong, not in this file.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 *
 * v1.0 | 2026-09-30
 */

declare( strict_types=1 );

namespace SiteEssentials\Modules\SeoMeta;

use SiteEssentials\Core\Ai_Knowledge;
use SiteEssentials\Core\Abilities\Ability_Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Meta_Instructions {

	/** Bump when the shipped rules change, so consumers can tell. */
	const VERSION = '1.0';

	/** Optional per-site brand voice, in wp-content/ai-knowledge/. */
	const VOICE_FILE = '205-brand-voice.md';

	/** Content type used when a post type has no mapping. */
	const DEFAULT_CONTENT_TYPE = 'page';

	/**
	 * Build the instructions for a post, a post type, or the site in general.
	 *
	 * @param array<string,mixed> $args {
	 *     All optional.
	 *
	 *     @type int      $post_id      Post the meta is for. Sets the post type and adds current values.
	 *     @type string   $post_type    Post type, when there is no post yet.
	 *     @type string   $content_type Force a content type instead of deriving it from the post type.
	 *     @type string[] $fields       Limit to these fields: breadcrumb_title, title, description.
	 * }
	 * @return array<string,mixed>
	 */
	public static function get( array $args = [] ): array {
		$post_id   = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		$post      = $post_id ? get_post( $post_id ) : null;
		$post_type = $post instanceof \WP_Post
			? $post->post_type
			: ( isset( $args['post_type'] ) ? sanitize_key( (string) $args['post_type'] ) : '' );

		$content_type = ! empty( $args['content_type'] )
			? sanitize_key( (string) $args['content_type'] )
			: self::content_type_for_post_type( $post_type );

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

		$voice = self::voice();
		$notes = [];
		if ( ! $voice['found'] ) {
			$notes[] = sprintf(
				'No brand voice file on this site (wp-content/%1$s/%2$s). Write in plain, specific language and follow the rules as given.',
				Ai_Knowledge::DIR,
				self::VOICE_FILE
			);
		}

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
			'fields'               => $fields,
			'general_rules'        => self::general_rules(),
			'avoid'                => self::avoid(),
			'voice'                => $voice,
			'editorial_guidelines' => Ability_Support::get_guidelines_for_prompt( [ 'site', 'copy' ] ),
			'facts'                => [],
			'saving'               => [
				'Write each value to the meta_key given for its field, as post meta on the post.',
				'Values are plain text. No HTML, no markdown, no surrounding quotes.',
				'Count characters before saving. A value outside its min–max is wrong, not close enough.',
				'Never write to _seopress_* keys.',
			],
			'notes'                => $notes,
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
				'rules'    => [
					'Hard limit — count every character.',
					'The first 60 characters carry the most specific differentiator: a proof point, a number, a named entity or a unique claim.',
					'The rest expands on the promise of the title and adds method or context.',
					'End with a soft outcome or action signal.',
					'Must not repeat the title verbatim.',
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
	 * Words and phrases that must not appear in any field.
	 *
	 * @return string[]
	 */
	public static function avoid(): array {
		return [
			'learn more',
			'click here',
			'discover',
			'solutions',
			'leverage',
			'cutting-edge',
			'game-changing',
			'synergy',
			'next-level',
			'seamless',
			'robust',
			'empower',
		];
	}

	/**
	 * Map a post type to the content type its rules are written for.
	 *
	 * @param string $post_type Post type slug. Empty returns the default.
	 * @return string One of: article, page, product, service, case-study (or a filtered value).
	 */
	public static function content_type_for_post_type( string $post_type ): string {
		$map = [
			'post'     => 'article',
			'page'     => 'page',
			'product'  => 'product',
			'service'  => 'service',
			'services' => 'service',
			'project'  => 'case-study',
			'projects' => 'case-study',
		];

		$content_type = $map[ $post_type ] ?? self::DEFAULT_CONTENT_TYPE;

		/**
		 * Filters the content type used to pick writing rules for a post type.
		 *
		 * @param string $content_type The resolved content type.
		 * @param string $post_type    The post type slug.
		 */
		return (string) apply_filters( 'scos_writing_content_type', $content_type, $post_type );
	}

	/**
	 * Extra rules per field for one content type.
	 *
	 * @param string $content_type e.g. `product`.
	 * @return array<string,string[]> Keyed by field. Empty for an unknown type.
	 */
	public static function type_rules( string $content_type ): array {
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
				],
				'description'      => [
					'Say what the product is and what or who it suits, in plain language a shop assistant would use.',
					'End with a simple, clear call to action.',
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
	 * The site's brand voice, when it has one.
	 *
	 * Reads wp-content/ai-knowledge/205-brand-voice.md. A site without the
	 * file is normal: `found` is false and `text` is empty.
	 *
	 * @return array{found:bool,source:string,text:string}
	 */
	public static function voice(): array {
		$text = Ai_Knowledge::read( self::VOICE_FILE );

		return [
			'found'  => '' !== $text,
			'source' => Ai_Knowledge::DIR . '/' . self::VOICE_FILE,
			'text'   => $text,
		];
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
		$content_type = (string) ( $instructions['content_type'] ?? '' );
		$lines        = [];

		foreach ( (array) ( $instructions['fields'] ?? [] ) as $field ) {
			$limit = sprintf( '%d–%d %s', (int) $field['min'], (int) $field['max'], (string) $field['unit'] );
			if ( ! empty( $field['target'] ) ) {
				$limit .= sprintf( ', aim for %d', (int) $field['target'] );
			}

			$lines[] = sprintf( 'Rules for the %s (%s):', strtolower( (string) $field['label'] ), $limit );
			foreach ( (array) $field['rules'] as $rule ) {
				$lines[] = '- ' . $rule;
			}
			foreach ( (array) ( $field['type_rules'] ?? [] ) as $rule ) {
				$lines[] = sprintf( '- For this content type (%s): %s', $content_type, $rule );
			}
			$lines[] = '';
		}

		$lines[] = 'General rules:';
		foreach ( (array) ( $instructions['general_rules'] ?? [] ) as $rule ) {
			$lines[] = '- ' . $rule;
		}

		$avoid = (array) ( $instructions['avoid'] ?? [] );
		if ( $avoid ) {
			$lines[] = '- Never use these words or phrases: "' . implode( '", "', $avoid ) . '"';
		}

		$facts = (array) ( $instructions['facts'] ?? [] );
		if ( $facts ) {
			$lines[] = '';
			$lines[] = 'Facts about this post — use them, do not contradict them:';
			foreach ( $facts as $fact ) {
				$lines[] = '- ' . (string) $fact;
			}
		}

		$voice = (string) ( $instructions['voice']['text'] ?? '' );
		if ( '' !== $voice ) {
			$lines[] = '';
			$lines[] = 'Brand voice for this site — match it:';
			$lines[] = $voice;
		}

		return implode( "\n", $lines );
	}
}
