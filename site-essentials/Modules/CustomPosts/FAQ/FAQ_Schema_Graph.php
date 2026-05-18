<?php
/**
 * FAQ → unified schema graph injection.
 *
 * Hooks into the `scos_schema_graph_items` filter exposed by
 * brighter-core/includes/scos-schema-output.php and pushes a `FAQPage`
 * entry into the page's @graph when the current post contains one or more
 * `brighter/faq-selector` blocks.
 *
 * Replaces the inline `<script type="application/ld+json">` previously
 * emitted by the block's render callback, so the page has exactly one
 * JSON-LD graph.
 *
 * v1.0 | 2026-05-19
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
	 * Register the filter.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'scos_schema_graph_items', [ self::class, 'filter_graph' ], 10, 2 );
	}

	/**
	 * Push a FAQPage entry into $graph when the post contains FAQ Selector blocks.
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
		if ( ! $post || empty( $post->post_content ) ) {
			return $graph;
		}

		if ( false === strpos( $post->post_content, '<!-- wp:' . FAQ_Module::BLOCK_NAME ) ) {
			// Cheap pre-check so we don't parse_blocks on every page render.
			return $graph;
		}

		$faq_ids = self::collect_faq_ids( $post->post_content );
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
	private static function collect_faq_ids( string $content ): array {
		if ( ! function_exists( 'parse_blocks' ) ) {
			return [];
		}

		$collected = [];
		$blocks    = parse_blocks( $content );

		self::walk_blocks( $blocks, $collected );

		// Deduplicate while preserving order.
		return array_values( array_unique( array_map( 'intval', $collected ) ) );
	}

	/**
	 * Recursively walk the block tree.
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
}
