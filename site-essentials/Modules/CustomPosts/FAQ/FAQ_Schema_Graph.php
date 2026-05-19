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
 * v1.4 | 2026-05-19
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
	 * Short type identifier — sometimes BD stores just the class basename
	 * rather than the fully-qualified name. Accepted as a fallback match.
	 */
	const BD_ELEMENT_TYPE_SHORT = 'ScosFaqs';

	/**
	 * Internal diagnostic buffer — populated when `?scos_faq_inspect=1` is
	 * in the query string AND the current user can manage_options. Emitted
	 * as an HTML comment by emit_inspection_footer() so we can see what the
	 * BD walker actually saw without sshing into the box.
	 *
	 * @var array<string,mixed>
	 */
	private static $inspect = [];

	/**
	 * Register the filter.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'scos_schema_graph_items', [ self::class, 'filter_graph' ], 10, 2 );
		add_action( 'wp_print_footer_scripts', [ self::class, 'emit_inspection_footer' ], 100 );
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
		if ( self::inspect_enabled() ) {
			self::$inspect['filter_called'] = true;
			self::$inspect['arg_post_id']   = $post_id;
		}
		if ( ! $post_id ) {
			return $graph;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $graph;
		}

		$faq_ids = [];

		// ── 1. Gutenberg blocks in post_content ──────────────────────────────
		$has_gb_marker = ! empty( $post->post_content )
			&& false !== strpos( $post->post_content, '<!-- wp:' . FAQ_Module::BLOCK_NAME );
		if ( $has_gb_marker ) {
			foreach ( self::collect_faq_ids_from_blocks( $post->post_content ) as $id ) {
				$faq_ids[] = $id;
			}
		}
		if ( self::inspect_enabled() ) {
			self::$inspect['gutenberg_marker'] = $has_gb_marker;
			self::$inspect['after_gb_ids']     = $faq_ids;
		}

		// ── 2. Breakdance Scos_Faqs elements in _breakdance_data ─────────────
		foreach ( self::collect_faq_ids_from_breakdance( $post_id ) as $id ) {
			$faq_ids[] = $id;
		}

		// Dedupe while preserving first-seen order.
		$faq_ids = array_values( array_unique( array_map( 'intval', array_filter( $faq_ids ) ) ) );
		if ( self::inspect_enabled() ) {
			self::$inspect['final_faq_ids'] = $faq_ids;
		}
		if ( empty( $faq_ids ) ) {
			return $graph;
		}

		$faqs = FAQ_Module::get_by_ids( $faq_ids );
		if ( self::inspect_enabled() ) {
			self::$inspect['fetched_faq_count'] = count( $faqs );
		}
		if ( empty( $faqs ) ) {
			return $graph;
		}

		$main_entity = [];
		$skipped     = [];
		foreach ( $faqs as $faq ) {
			$answer = FAQ_Module::get_schema_answer( (int) $faq->ID );
			if ( false === $answer || '' === $answer ) {
				$skipped[] = (int) $faq->ID;
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
		if ( self::inspect_enabled() ) {
			self::$inspect['questions_added']      = count( $main_entity );
			self::$inspect['skipped_empty_answer'] = $skipped;
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
	 * Read Breakdance post meta and collect FAQ IDs from every Scos_Faqs
	 * element on the page. Supports both selector and topic modes.
	 *
	 * The Breakdance tree is stored under one of two meta keys:
	 *   `_breakdance_data` (current convention)
	 *   `breakdance_data`  (older convention, kept as fallback — same shape
	 *                       BW_Content_Analysis::get_breakdance_content uses)
	 *
	 * The stored value is a JSON string with this shape:
	 *   { "tree_json_string": "...JSON-encoded tree...", "...": "..." }
	 *
	 * `tree_json_string` is itself a JSON string and needs a second decode.
	 * After that you get { "root": { "data": {...}, "children": [...] } }.
	 *
	 * Each node is shaped:
	 *   {
	 *     "data": {
	 *       "type": "BreakdanceCustomElements\\ScosFaqs",
	 *       "properties": { "content": {...}, "design": {...} }
	 *     },
	 *     "children": [ ...nested nodes... ]
	 *   }
	 *
	 * Returns an empty array on any decode error or missing meta — these
	 * are "no FAQs from BD" outcomes, not errors.
	 *
	 * @since 1.1.0
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	private static function collect_faq_ids_from_breakdance( int $post_id ): array {
		$inspecting = self::inspect_enabled();
		if ( $inspecting ) {
			self::$inspect['post_id'] = $post_id;
		}

		// Try both meta key variants used across BD versions.
		$raw     = get_post_meta( $post_id, '_breakdance_data', true );
		$meta_key = '_breakdance_data';
		if ( empty( $raw ) || ! is_string( $raw ) ) {
			$raw      = get_post_meta( $post_id, 'breakdance_data', true );
			$meta_key = 'breakdance_data';
		}
		if ( $inspecting ) {
			self::$inspect['meta_key']    = $meta_key;
			self::$inspect['meta_type']   = gettype( $raw );
			self::$inspect['meta_empty']  = empty( $raw );
			self::$inspect['meta_length'] = is_string( $raw ) ? strlen( $raw ) : ( is_array( $raw ) ? count( $raw ) : 0 );
		}
		if ( empty( $raw ) ) {
			return [];
		}

		// Cheap pre-check before any JSON decoding — skip BD-less posts fast.
		$haystack = is_string( $raw ) ? $raw : wp_json_encode( $raw );
		if ( $inspecting ) {
			self::$inspect['has_scosfaqs_token'] = false !== strpos( (string) $haystack, self::BD_ELEMENT_TYPE_SHORT );
			self::$inspect['haystack_sample']    = substr( (string) $haystack, 0, 400 );
		}
		if ( false === strpos( (string) $haystack, self::BD_ELEMENT_TYPE_SHORT ) ) {
			return [];
		}

		$tree = self::decode_bd_tree( $raw );
		if ( $inspecting ) {
			self::$inspect['decoded_tree_type'] = gettype( $tree );
			if ( is_array( $tree ) ) {
				self::$inspect['decoded_top_keys'] = array_slice( array_keys( $tree ), 0, 20 );
			}
		}
		if ( null === $tree ) {
			return [];
		}

		$collected            = [];
		$visited_match_count  = 0;
		self::walk_bd_tree( $tree, $collected, $visited_match_count );
		if ( $inspecting ) {
			self::$inspect['matched_nodes'] = $visited_match_count;
			self::$inspect['raw_collected'] = $collected;
		}

		return array_values( array_unique( array_map( 'intval', $collected ) ) );
	}

	/**
	 * Unwrap `_breakdance_data` to the root node of the tree.
	 *
	 * Mirrors the parse pattern used by
	 * BW_Content_Analysis::parse_breakdance_structure() in brighter-core.
	 *
	 * @since 1.3.0
	 * @param mixed $raw Raw meta value (string or pre-decoded array).
	 * @return array|null Root node, or null when the data can't be parsed.
	 */
	private static function decode_bd_tree( $raw ) {
		$outer = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		if ( ! is_array( $outer ) ) {
			return null;
		}

		// Standard wrapper: outer JSON has `tree_json_string` containing
		// the actual tree as a second JSON string.
		if ( isset( $outer['tree_json_string'] ) && is_string( $outer['tree_json_string'] ) ) {
			$tree = json_decode( $outer['tree_json_string'], true );
		} else {
			// Fallback: some older BD versions / contexts store the tree
			// directly without the wrapper.
			$tree = $outer;
		}
		if ( ! is_array( $tree ) ) {
			return null;
		}

		// Standard shape has `root` holding the tree root.
		if ( isset( $tree['root'] ) && is_array( $tree['root'] ) ) {
			return $tree['root'];
		}

		// Fallback: tree might already be a root-shaped node.
		return $tree;
	}

	/**
	 * Recursively walk any tree fragment looking for Scos_Faqs element nodes.
	 *
	 * Resilient to BD storage variations:
	 *  - Element data may sit under `$node['data']['type']` (typical when the
	 *    tree comes from `tree_json_string`) OR `$node['type']` (older shape
	 *    or pre-decoded contexts).
	 *  - `type` may be the fully-qualified class string
	 *    (`BreakdanceCustomElements\\ScosFaqs`) or just the basename
	 *    (`ScosFaqs`).
	 *  - Properties/content may live at `data.properties.content` OR
	 *    `properties.content` for the same reason.
	 *  - Recurses into every array value, not just `$node['children']` —
	 *    if BD wraps the tree in something we didn't expect we still find
	 *    the element. The performance cost is fine because the haystack
	 *    pre-check already gated this on "ScosFaqs" appearing somewhere
	 *    in the JSON.
	 *
	 * @since 1.4.0
	 * @param array $node                Current tree fragment.
	 * @param array $collected           Reference; FAQ IDs are appended.
	 * @param int   $matched_node_count  Reference; number of matched element nodes.
	 * @return void
	 */
	private static function walk_bd_tree( array $node, array &$collected, int &$matched_node_count ): void {
		// Resolve where `type` and `properties.content` actually sit on this
		// node — accept both flat and nested-under-data shapes.
		$type = self::resolve_node_type( $node );

		if ( self::BD_ELEMENT_TYPE === $type || self::BD_ELEMENT_TYPE_SHORT === $type ) {
			$matched_node_count++;
			self::harvest_faq_node( $node, $collected );
			// Don't return — element nodes can still have children we don't
			// care about, but recursing past them is harmless and cheap.
		}

		// Recurse into every array value. Skip primitives (strings/numbers/null).
		foreach ( $node as $value ) {
			if ( is_array( $value ) ) {
				// Treat each child array as a potential node — same matching
				// rules apply at any depth.
				self::walk_bd_tree( $value, $collected, $matched_node_count );
			}
		}
	}

	/**
	 * Resolve the BD element type string from a node, regardless of nesting.
	 *
	 * @since 1.4.0
	 * @param array $node
	 * @return string Empty string when no recognisable type field is present.
	 */
	private static function resolve_node_type( array $node ): string {
		// Prefer data.type (the canonical tree_json_string shape).
		if ( isset( $node['data'] ) && is_array( $node['data'] ) && isset( $node['data']['type'] ) ) {
			return self::stringify_type( $node['data']['type'] );
		}
		if ( isset( $node['type'] ) ) {
			return self::stringify_type( $node['type'] );
		}
		return '';
	}

	/**
	 * Normalise a `type` value to a string. BD has historically used either
	 * a plain string or an object with a `name` key.
	 *
	 * @since 1.4.0
	 * @param mixed $type
	 * @return string
	 */
	private static function stringify_type( $type ): string {
		if ( is_string( $type ) ) {
			return $type;
		}
		if ( is_array( $type ) && isset( $type['name'] ) && is_string( $type['name'] ) ) {
			return $type['name'];
		}
		return '';
	}

	/**
	 * Harvest FAQ IDs out of a node we've already identified as Scos_Faqs.
	 *
	 * Looks for `properties.content` at both common nesting depths. Respects
	 * the per-element `schema_enabled` toggle (default on). Handles both
	 * selector and topic modes.
	 *
	 * @since 1.4.0
	 * @param array $node      The matched element node.
	 * @param array $collected Reference; FAQ IDs are appended.
	 * @return void
	 */
	private static function harvest_faq_node( array $node, array &$collected ): void {
		// Look for properties.content at both common locations.
		$properties = [];
		if ( isset( $node['data']['properties'] ) && is_array( $node['data']['properties'] ) ) {
			$properties = $node['data']['properties'];
		} elseif ( isset( $node['properties'] ) && is_array( $node['properties'] ) ) {
			$properties = $node['properties'];
		}

		$content = isset( $properties['content'] ) && is_array( $properties['content'] )
			? $properties['content']
			: [];

		// Property paths mirror the section nesting in
		// elements/Scos_Faqs/element.php:
		//   content.faq_source.{mode, selected_faqs, topic_slug}
		//   content.display.{schema_enabled, format, heading}
		$source  = isset( $content['faq_source'] ) && is_array( $content['faq_source'] ) ? $content['faq_source'] : [];
		$display = isset( $content['display'] )    && is_array( $content['display'] )    ? $content['display']    : [];

		// Also accept flat property layout (no faq_source/display sections)
		// for forward-compat if controls are ever flattened.
		if ( empty( $source ) && ( isset( $content['mode'] ) || isset( $content['selected_faqs'] ) || isset( $content['topic_slug'] ) ) ) {
			$source = [
				'mode'          => $content['mode']          ?? null,
				'selected_faqs' => $content['selected_faqs'] ?? null,
				'topic_slug'    => $content['topic_slug']    ?? null,
			];
		}
		if ( empty( $display ) && isset( $content['schema_enabled'] ) ) {
			$display['schema_enabled'] = $content['schema_enabled'];
		}

		$schema_enabled = array_key_exists( 'schema_enabled', $display )
			? (bool) $display['schema_enabled']
			: true;
		if ( ! $schema_enabled ) {
			return;
		}

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

	// =========================================================================
	// Diagnostic / inspection helpers
	// =========================================================================

	/**
	 * Is the inspection mode active for this request? Gated on
	 * `?scos_faq_inspect=1` and manage_options capability.
	 *
	 * @since 1.4.0
	 * @return bool
	 */
	private static function inspect_enabled(): bool {
		if ( empty( $_GET['scos_faq_inspect'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		return current_user_can( 'manage_options' );
	}

	/**
	 * Emit the collected inspection report as an HTML comment in the page
	 * footer. Only outputs when the buffer has data (i.e. inspection was
	 * active for this request).
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function emit_inspection_footer(): void {
		// Show output whenever the user explicitly asked for it — empty
		// buffer is its own useful signal (the filter never ran).
		if ( ! self::inspect_enabled() ) {
			return;
		}
		if ( empty( self::$inspect ) ) {
			echo "\n<!-- scos_faq_inspect: filter scos_schema_graph_items never fired on this request (FAQ submodule disabled, schema graph not emitted, or non-singular context) -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		$json = wp_json_encode( self::$inspect, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		// HTML comments break on `--`; collapse any double-dashes to be safe.
		$json = str_replace( '--', '-_-', (string) $json );
		echo "\n<!-- scos_faq_inspect\n" . $json . "\n-->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
