<?php
/**
 * Image Meta — Writing Instructions
 *
 * The one place that says how image alt text and media titles are written on
 * a SCOS site: where each is stored, how long, and how to write it. Anything
 * that writes image meta follows these — the Fill Image Meta ability, an MCP
 * agent describing images it can see, a site plugin.
 *
 * This class is the *what*. Batching, choosing a media category and tagging a
 * project are how Fill Image Meta works, and stay with that ability.
 *
 * Exposed to agents as the scos/get-image-meta-instructions ability and to
 * WP-CLI as `wp scos image-meta-instructions`.
 *
 * Site plugins extend or override the result through the
 * `scos_image_meta_instructions` filter.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 *
 * v1.0 | 2026-09-30
 */

declare( strict_types=1 );

namespace SiteEssentials\Modules\SeoMeta;

use SiteEssentials\Core\Writing_Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Image_Meta_Instructions {

	/** Bump when the shipped rules change, so consumers can tell. */
	const VERSION = '1.0';

	/**
	 * Build the instructions, for one image or for images in general.
	 *
	 * @param array<string,mixed> $args {
	 *     All optional.
	 *
	 *     @type int      $attachment_id Image the meta is for. Adds current values and the page it is attached to.
	 *     @type string[] $fields        Limit to these fields: alt, title.
	 * }
	 * @return array<string,mixed>
	 */
	public static function get( array $args = [] ): array {
		$attachment_id = isset( $args['attachment_id'] ) ? absint( $args['attachment_id'] ) : 0;
		$attachment    = $attachment_id ? get_post( $attachment_id ) : null;
		if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
			$attachment = null;
		}

		$wanted = ! empty( $args['fields'] ) && is_array( $args['fields'] )
			? array_map( 'sanitize_key', $args['fields'] )
			: [];

		$fields = [];
		foreach ( self::fields() as $key => $field ) {
			if ( $wanted && ! in_array( $key, $wanted, true ) ) {
				continue;
			}
			if ( $attachment ) {
				$field['current'] = isset( $field['meta_key'] )
					? (string) get_post_meta( $attachment->ID, $field['meta_key'], true )
					: (string) $attachment->post_title;
			}
			$fields[ $key ] = $field;
		}

		$parent = $attachment && $attachment->post_parent ? get_post( $attachment->post_parent ) : null;

		$instructions = [
			'version'       => self::VERSION,
			'attachment'    => $attachment
				? [
					'id'  => $attachment->ID,
					'url' => (string) wp_get_attachment_url( $attachment->ID ),
				]
				: [],
			'attached_to'   => $parent instanceof \WP_Post
				? [
					'id'    => $parent->ID,
					'title' => $parent->post_title,
					'type'  => $parent->post_type,
				]
				: [],
			'business'      => Writing_Context::business(),
			'fields'        => $fields,
			'general_rules' => self::general_rules(),
			'facts'         => [],
			'saving'        => [
				'Alt text is post meta on the attachment, under the meta_key given.',
				'The title is the attachment\'s own post_title, not post meta.',
				'Changing the title does not rename the file or change its URL.',
				'Values are plain text. No HTML, no markdown, no surrounding quotes.',
			],
			'notes'         => [],
		];

		/**
		 * Filters the image meta writing instructions.
		 *
		 * @param array<string,mixed> $instructions The instructions.
		 * @param array<string,mixed> $args         The arguments passed to get().
		 */
		return (array) apply_filters( 'scos_image_meta_instructions', $instructions, $args );
	}

	/**
	 * The image fields: where each is stored, its limits, and how to write it.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function fields(): array {
		return [
			'alt'   => [
				'meta_key' => '_wp_attachment_image_alt',
				'label'    => 'Alt text',
				'unit'     => 'characters',
				'max'      => 125,
				'format'   => 'plain text',
				'rules'    => [
					'Hard limit — count every character.',
					'Start with a specific description of what is shown.',
					'Describe the most important subject first, then the context or setting.',
					'Be specific: "plumber replacing kitchen tap under cabinet", not "plumber doing work".',
					'When the image belongs to a page, weave in that page\'s topic or service naturally. Do not keyword-stuff, and do not copy the page title verbatim.',
					'When the image is not attached to a page, write a fully self-contained description.',
					'Never start with "Image of", "Photo of" or "Picture of", and never use the words "image", "photo" or "picture".',
					'No punctuation at the end.',
				],
			],
			'title' => [
				'post_field' => 'post_title',
				'label'      => 'Media title',
				'unit'       => 'words',
				'min'        => 3,
				'max'        => 5,
				'format'     => 'plain text, all lowercase',
				'rules'      => [
					'Hard limit — never more than five words.',
					'Written to be found when searching the media library.',
					'Blend the topic of the page the image belongs to with the key thing shown: [topic or subject] [descriptor or context] — for example "concrete driveway before restoration".',
					'When the image is not attached to a page, use the key subject and its context only.',
					'No punctuation, no quotes.',
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
			'Describe only what can be seen. Do not invent people, places, brands or outcomes.',
			'Look at the image itself. A file name, URL or existing title is not evidence of what it shows.',
			'Two different images never get the same alt text or title — say what sets each one apart.',
		];
	}

	/**
	 * Render instructions as plain text for a system prompt.
	 *
	 * @param array<string,mixed> $instructions Result of get().
	 * @return string
	 */
	public static function to_prompt( array $instructions ): string {
		return Writing_Context::fields_to_prompt( (array) ( $instructions['fields'] ?? [] ) )
			. Writing_Context::to_prompt( $instructions );
	}
}
