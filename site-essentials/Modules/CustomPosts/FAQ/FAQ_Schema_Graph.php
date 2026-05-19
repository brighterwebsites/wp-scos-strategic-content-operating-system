<?php
/**
 * FAQ → unified schema graph injection.
 *
 * Hooks into the `scos_schema_graph_items` filter exposed by
 * brighter-core/includes/scos-schema-output.php and pushes a `FAQPage`
 * entry into the page's @graph when the current post contains:
 *
 *  - one or more `brighter/faq-selector` Gutenberg blocks (in post_content)
 *  - one or more Scos_Faqs Breakdance elements (in _breakdance_data post meta)
 *
 * Both sources are deduplicated before the FAQPage is emitted, so a page
 * that uses both the Gutenberg block AND the Breakdance element for the
 * same FAQs only lists that FAQ once.
 *
 * Replaces the inline `<script type="application/ld+json">` previously
 * emitted by the block's render callback, so the page has exactly one
 * JSON-LD graph.
 *
 * v1.2 | 2026-05-19
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts\FAQ
 */

namespace SiteEssentials\Modules\CustomPosts\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FAQ_Schema_Graph {

	/**
	 * Breakdance custom-element class identifier used inside `_breakdance_data`.
	 *
	 * Mirrors the PHP class name registered in
	 * `elements/Scos_Faqs/element.php`. Kept here as a constant so the
	 * element repo and this class can move independently as long as the slug
	 * string stays in sync.
	 */
	const BD_ELEMENT_TYPE = 'BreakdanceCustomElements\\ScosFaqs';

	/**
	 * Register the filter.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'scos_schema_graph_items', [ self::class, 'filter_graph' ], 10, 2 );
	}

	/**
	 * Push a FAQPage entry into $graph when the post contains FAQ Selector
	 * blocks (Gutenberg) and/or Scos_Faqs elements (Breakdance).
	 *
	 * @since 1.0.0
	 * @param array $graph   Current schema @graph array.
	 * @param int   $post_id Current queried object ID.
	 * @return array
	 */
	public static function filter_graph( $graph, $post_id ): array {
		$graph = is_array( $graph ) ? $graph : [];
		$post_id = (int) $post_id;
		if ( ! $post_id ) {
			return $graph;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $graph;
		}

		$faq_ids = [];

		// ── 1. Gutenberg blocks in post_content ──────────────────────────────
		if ( ! empty( $post->post_content )
			&& false !== strpos( $post->post_content, '<!-- wp:' . FAQ_Module::BLOCK_NAME )
		) {
			foreach ( self::collect_faq_ids_from_blocks( $post->post_content ) as $id ) {
				$faq_ids[] = $id;
			}
		}

		// ── 2. Breakdance Scos_Faqs elements in _breakdance_data ─────────────
		foreach ( self::collect_faq_ids_from_breakdance( $post_id ) as $id ) {
			$faq_ids[] = $id;
		}

		// Dedupe while preserving first-seen order.
		$faq_ids = array_values( array_unique( array_map( 'intval', array_filter( $faq_ids ) ) ) );
		if ( empty( $faq_ids ) ) {
			return $graph;
		}

		$faqs = FAQ_Module::get_by_ids( $faq_ids );
		if ( empty( $faqs ) ) {
			return $graph;
		}

		$main_entity = [];
		foreach ( $faqs as $faq ) {
			$answer = FAQ_Module::get_schema_answer( (int) $faq->ID );
			if ( false === $answer || '' === $answer ) {
				continue;
			}
			$main_entity[] = [
				'@type'          => 'Question',
				'name'           => get_the_title( $faq->ID ),
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $answer,
				],
			];
		}

		if ( empty( $main_entity ) ) {
			return $graph;
		}

		$graph[] = [
			'@type'      => 'FAQPage',
			'@id'        => get_permalink( $post_id ) . '#faq',
			'mainEntity' => $main_entity,
		];

		return $graph;
	}

	// =========================================================================
	// Gutenberg collection
	// =========================================================================

	/**
	 * Walk the parsed block tree and collect selectedFaqs from every FAQ
	 * Selector block — including nested blocks (e.g. inside group blocks).
	 *
	 * Respects each block's `enableSchema` attribute: blocks with schema
	 * disabled are excluded.
	 *
	 * @since 1.0.0
	 * @param string $content Post content.
	 * @return int[] Deduplicated FAQ IDs in document order.
	 */
	private static function collect_faq_ids_from_blocks( string $content ): array {
		if ( ! function_exists( 'parse_blocks' ) ) {
			return [];
		}

		$collected = [];
		$blocks    = parse_blocks( $content );

		self::walk_blocks( $blocks, $collected );

		return array_values( array_unique( array_map( 'intval', $collected ) ) );
	}

	/**
	 * Recursively walk the Gutenberg block tree.
	 *
	 * @since 1.0.0
	 * @param array $blocks    Parsed block tree.
	 * @param array $collected Reference; FAQ IDs are appended.
	 * @return void
	 */
	private static function walk_blocks( array $blocks, array &$collected ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( isset( $block['blockName'] ) && FAQ_Module::BLOCK_NAME === $block['blockName'] ) {
				$attrs          = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
				$schema_enabled = array_key_exists( 'enableSchema', $attrs ) ? (bool) $attrs['enableSchema'] : true;
				if ( $schema_enabled && ! empty( $attrs['selectedFaqs'] ) && is_array( $attrs['selectedFaqs'] ) ) {
					foreach ( $attrs['selectedFaqs'] as $id ) {
						$collected[] = (int) $id;
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::walk_blocks( $block['innerBlocks'], $collected );
			}
		}
	}

	// =========================================================================
	// Breakdance collection
	// =========================================================================

	/**
	 * Read `_breakdance_data` post meta and collect FAQ IDs from every
	 * Scos_Faqs element. Supports both "selector" mode (manual picks) and
	 * "topic" mode (resolved via FAQ_Module::get_ids_by_topic).
	 *
	 * Returns an empty array on any decode error, missing meta, or when
	 * Breakdance is not present on the post — these are all "no FAQs from
	 * Breakdance" outcomes, not errors.
	 *
	 * @since 1.1.0
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	private static function collect_faq_ids_from_breakdance( int $post_id ): array {
		$raw = get_post_meta( $post_id, '_breakdance_data', true );
		if ( empty( $raw ) ) {
			return [];
		}

		// Cheap pre-check: skip the full walk on posts that don't even
		// mention the element class.
		$haystack = is_string( $raw ) ? $raw : wp_json_encode( $raw );
		if ( false === strpos( (string) $haystack, 'ScosFaqs' ) ) {
			return [];
		}

		// Breakdance may store the data as a JSON-encoded string OR an
		// already-decoded array, depending on filter order. Handle both.
		$data = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			return [];
		}

		$collected = [];
		self::walk_bd_tree( $data, $collected );

		return array_values( array_unique( array_map( 'intval', $collected ) ) );
	}

	/**
	 * Recursively walk a Breakdance node array, collecting FAQ IDs from any
	 * Scos_Faqs element we find.
	 *
	 * The Breakdance tree shape varies between versions but always has:
	 *  - a `type` field on element nodes (string or array)
	 *  - a `properties` field with `content` sub-object
	 *  - a `children` array for nested elements
	 *
	 * We also walk every other array value defensively, so the walker still
	 * works if BD changes the wrapper shape.
	 *
	 * @since 1.1.0
	 * @param array $node      Current tree fragment.
	 * @param array $collected Reference; FAQ IDs are appended.
	 * @return void
	 */
	private static function walk_bd_tree( array $node, array &$collected ): void {
		// Element node — check the type and harvest if it's ours.
		if ( isset( $node['type'] ) ) {
			$type = is_string( $node['type'] ) ? $node['type'] : ( is_array( $node['type'] ) ? ( $node['type']['name'] ?? '' ) : '' );
			if ( self::BD_ELEMENT_TYPE === $type ) {
				$content = $node['properties']['content'] ?? [];
				$content = is_array( $content ) ? $content : [];

				// Property paths mirror the section nesting in
				// elements/Scos_Faqs/element.php:
				//   content.faq_source.{mode, selected_faqs, topic_slug}
				//   content.display.{schema_enabled, format, heading}
				$source  = isset( $content['faq_source'] ) && is_array( $content['faq_source'] ) ? $content['faq_source'] : [];
				$display = isset( $content['display'] )    && is_array( $content['display'] )    ? $content['display']    : [];

				// Respect the per-element schema toggle (default on).
				$schema_enabled = array_key_exists( 'schema_enabled', $display )
					? (bool) $display['schema_enabled']
					: true;
				if ( $schema_enabled ) {
					$mode = isset( $source['mode'] ) ? (string) $source['mode'] : 'selector';

					if ( 'topic' === $mode && ! empty( $source['topic_slug'] ) ) {
						$ids = FAQ_Module::get_ids_by_topic( sanitize_title( (string) $source['topic_slug'] ) );
						foreach ( $ids as $id ) {
							$collected[] = (int) $id;
						}
					} elseif ( ! empty( $source['selected_faqs'] ) && is_array( $source['selected_faqs'] ) ) {
						foreach ( $source['selected_faqs'] as $item ) {
							$collected[] = self::extract_post_id( $item );
						}
					}
				}
			}
		}

		// Recurse into known child containers first (cheap, common case).
		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			foreach ( $node['children'] as $child ) {
				if ( is_array( $child ) ) {
					self::walk_bd_tree( $child, $collected );
				}
			}
		}

		// Defensive recurse into every other array value so we still find
		// Scos_Faqs if BD wraps the tree in something unexpected.
		foreach ( $node as $key => $value ) {
			if ( 'children' === $key || 'type' === $key || 'properties' === $key ) {
				continue;
			}
			if ( is_array( $value ) ) {
				self::walk_bd_tree( $value, $collected );
			}
		}
	}

	/**
	 * Pull a post ID out of a repeater item. Tolerates plain integers and
	 * the various BD post-picker shapes ({id}, {value}, {post: {id}}, etc.).
	 *
	 * @since 1.1.0
	 * @param mixed $item Repeater row.
	 * @return int 0 when no recognisable post ID is present.
	 */
	private static function extract_post_id( $item ): int {
		if ( is_numeric( $item ) ) {
			return (int) $item;
		}
		if ( ! is_array( $item ) ) {
			return 0;
		}
		if ( isset( $item['id'] ) && is_numeric( $item['id'] ) ) {
			return (int) $item['id'];
		}
		if ( isset( $item['value'] ) && is_numeric( $item['value'] ) ) {
			return (int) $item['value'];
		}
		if ( isset( $item['post']['id'] ) && is_numeric( $item['post']['id'] ) ) {
			return (int) $item['post']['id'];
		}
		if ( isset( $item['post_id'] ) && is_numeric( $item['post_id'] ) ) {
			return (int) $item['post_id'];
		}
		return 0;
	}
}
