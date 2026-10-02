<?php
// v1.3 | 2026-10-03

/**
 * Review schema token resolver.
 *
 * Registers two review-related tokens via the `bw_schema_resolve_variable`
 * filter exposed by brighter-core/includes/scos-schema-output.php.
 *
 * %%_scos_review_cards_json%%
 *   Walks the Breakdance element trees that render the current request — the
 *   post's own content plus the Breakdance template hierarchy chosen for it —
 *   collects every ScosReviewCard element, fetches the associated bw_reviews
 *   data, and returns a PHP array of schema.org Review objects. Usage:
 *     { "@type": "Service", "review": "%%_scos_review_cards_json%%" }
 *   "specific" mode elements contribute an explicit review ID.
 *   "loop" mode elements contribute the reviews returned by the Posts Loop
 *   whose repeated Global Block contains the card (the same query Breakdance runs).
 *   "connected" mode elements contribute all reviews linked to the page's
 *   project post via the bw_related_project meta key.
 *   Resolves to '' when no reviews are found, so the property is dropped.
 *
 * %%_scos_aggregate_rating_json%%
 *   Queries all published bw_reviews posts, calculates the count and average
 *   rating across ALL platforms, and returns a single schema.org
 *   AggregateRating object. Usage:
 *     { "@type": "Service", "aggregateRating": "%%_scos_aggregate_rating_json%%" }
 *
 * Both tokens replace a whole-value string with a PHP array — the token engine
 * (bw_schema_replace_variables) already handles this, same as %%post_thumbnail%%.
 *
 * Mirrors the pattern established by FAQ_Schema_Graph.
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 */

namespace SiteEssentials\Modules\CustomPosts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Review_Schema_Graph {

	/**
	 * Breakdance custom-element class identifier used inside `_breakdance_data`.
	 *
	 * Mirrors the PHP class name registered in
	 * `elements/Scos_Review_Card/element.php`.
	 */
	const BD_ELEMENT_TYPE = 'BreakdanceCustomElements\\ScosReviewCard';

	/**
	 * Short type identifier — BD sometimes stores just the class basename.
	 */
	const BD_ELEMENT_TYPE_SHORT = 'ScosReviewCard';

	/**
	 * Token name for the per-page Review array.
	 */
	const TOKEN_REVIEWS = '_scos_review_cards_json';

	/**
	 * Token name for the site-wide AggregateRating object.
	 */
	const TOKEN_AGGREGATE = '_scos_aggregate_rating_json';

	/**
	 * Breakdance elements that pull another document's tree into the page.
	 */
	const BD_GLOBAL_BLOCK = 'EssentialElements\\GlobalBlock';
	const BD_POSTS_LOOP   = 'EssentialElements\\PostsLoop';

	/**
	 * Documents nested deeper than this are ignored (circular-reference guard).
	 */
	const MAX_DEPTH = 6;

	/**
	 * Per-request memo of resolved review arrays, keyed by post ID.
	 *
	 * @var array<int, array>
	 */
	private static $memo = [];

	/**
	 * Register the token resolver filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'bw_schema_resolve_variable', [ self::class, 'resolve_token' ], 10, 3 );
	}

	/**
	 * Route token resolution for both review tokens.
	 *
	 * Hooked onto `bw_schema_resolve_variable`.
	 *
	 * @param mixed  $value   Current resolved value (pass-through for unhandled tokens).
	 * @param string $name    Token name without %% delimiters.
	 * @param int    $post_id Current page post ID.
	 * @return mixed
	 */
	public static function resolve_token( $value, string $name, int $post_id ) {
		if ( self::TOKEN_REVIEWS === $name ) {
			$reviews = self::get_review_array( $post_id );
			// '' is stripped by the graph output; [] would print "review": [].
			return empty( $reviews ) ? '' : $reviews;
		}
		if ( self::TOKEN_AGGREGATE === $name ) {
			return self::get_aggregate_rating();
		}
		return $value;
	}

	/**
	 * Build the Review array for a given page post ID.
	 *
	 * Public so it can be called directly from WP-CLI / MCP tools if needed.
	 *
	 * @param int $post_id Page post ID.
	 * @return array[] Array of schema.org Review objects. Empty when no reviews found.
	 */
	public static function get_review_array( int $post_id ): array {
		if ( ! isset( self::$memo[ $post_id ] ) ) {
			self::$memo[ $post_id ] = self::build_review_array( $post_id );
		}
		return self::$memo[ $post_id ];
	}

	/**
	 * Uncached body of get_review_array().
	 *
	 * @param int $post_id Page post ID.
	 * @return array[]
	 */
	private static function build_review_array( int $post_id ): array {
		$review_ids = self::collect_review_ids_from_breakdance( $post_id );
		$review_ids = array_values( array_unique( array_map( 'intval', array_filter( $review_ids ) ) ) );

		if ( empty( $review_ids ) ) {
			return [];
		}

		// Batch-prime the meta cache for all review IDs in one query.
		update_meta_cache( 'post', $review_ids );

		$reviews = [];
		foreach ( $review_ids as $id ) {
			$obj = self::build_review_object( $id );
			if ( null !== $obj ) {
				$reviews[] = $obj;
			}
		}

		return $reviews;
	}

	// =========================================================================
	// Breakdance tree walking
	// =========================================================================

	/**
	 * Collect bw_reviews post IDs from every ScosReviewCard that renders on
	 * the current request.
	 *
	 * Documents walked: the post's own Breakdance content, then each template
	 * in the hierarchy Breakdance chose for the request (set at
	 * template_include, before wp_head). Global Blocks and Posts Loop repeated
	 * blocks are followed into their own trees.
	 *
	 * - "specific" mode: the explicit review_id stored in element props.
	 * - "loop" mode: the IDs the enclosing Posts Loop query returns.
	 * - "connected" mode: bw_reviews where bw_related_project = $post_id.
	 *
	 * @param int $post_id Page post ID.
	 * @return int[]
	 */
	private static function collect_review_ids_from_breakdance( int $post_id ): array {
		$document_ids = array_unique( array_merge( [ $post_id ], self::get_request_template_ids() ) );

		$collected     = [];
		$has_connected = false;
		foreach ( $document_ids as $document_id ) {
			self::walk_document( (int) $document_id, $collected, $has_connected, null, [] );
		}

		// For connected-mode elements, query reviews linked to this project.
		if ( $has_connected ) {
			$connected_ids = self::get_connected_review_ids( $post_id );
			$collected     = array_merge( $collected, $connected_ids );
		}

		return $collected;
	}

	/**
	 * Template IDs (template + parents) Breakdance is rendering this request with.
	 *
	 * @return int[] Empty when Breakdance is inactive or no template applies.
	 */
	private static function get_request_template_ids(): array {
		$controller_class = '\Breakdance\Themeless\ThemelessController';
		if ( ! class_exists( $controller_class ) ) {
			return [];
		}
		$controller = $controller_class::getInstance();
		if ( ! property_exists( $controller, 'originalTemplateHierarchyForRequest' ) ) {
			return [];
		}
		$ids = $controller->originalTemplateHierarchyForRequest;
		return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
	}

	/**
	 * Load a document's Breakdance tree and walk it.
	 *
	 * @param int        $document_id   Post, template or Global Block ID.
	 * @param array      $collected     Reference; review IDs are appended.
	 * @param bool       $has_connected Reference; set when a connected-mode card is found.
	 * @param int[]|null $loop_ids      Review IDs of the enclosing Posts Loop, or null outside a loop.
	 * @param int[]      $stack         Document IDs currently being walked (recursion guard).
	 * @return void
	 */
	private static function walk_document( int $document_id, array &$collected, bool &$has_connected, ?array $loop_ids, array $stack ): void {
		if ( $document_id <= 0 || in_array( $document_id, $stack, true ) || count( $stack ) >= self::MAX_DEPTH ) {
			return;
		}

		$raw = self::get_raw_tree( $document_id );

		// Cheap pre-check before JSON decoding — skip documents that can't hold a card.
		if ( false === strpos( $raw, self::BD_ELEMENT_TYPE_SHORT )
			&& false === strpos( $raw, 'GlobalBlock' )
			&& false === strpos( $raw, 'PostsLoop' ) ) {
			return;
		}

		$tree = self::decode_bd_tree( $raw );
		if ( null === $tree ) {
			return;
		}

		$stack[] = $document_id;
		self::walk_bd_tree( $tree, $collected, $has_connected, $loop_ids, $stack );
	}

	/**
	 * Raw `_breakdance_data` for a document, as a string.
	 *
	 * @param int $document_id
	 * @return string Empty when the document has no Breakdance data.
	 */
	private static function get_raw_tree( int $document_id ): string {
		// Try both meta key variants used across BD versions.
		$raw = get_post_meta( $document_id, '_breakdance_data', true );
		if ( empty( $raw ) ) {
			$raw = get_post_meta( $document_id, 'breakdance_data', true );
		}
		if ( empty( $raw ) ) {
			return '';
		}
		return is_string( $raw ) ? $raw : (string) wp_json_encode( $raw );
	}

	/**
	 * Unwrap `_breakdance_data` to the root node of the tree.
	 *
	 * Mirrors FAQ_Schema_Graph::decode_bd_tree().
	 *
	 * @param string $raw Raw meta value.
	 * @return array|null Root node, or null when the data cannot be parsed.
	 */
	private static function decode_bd_tree( string $raw ) {
		$outer = json_decode( $raw, true );
		if ( ! is_array( $outer ) ) {
			return null;
		}

		if ( isset( $outer['tree_json_string'] ) && is_string( $outer['tree_json_string'] ) ) {
			$tree = json_decode( $outer['tree_json_string'], true );
		} else {
			$tree = $outer;
		}
		if ( ! is_array( $tree ) ) {
			return null;
		}

		if ( isset( $tree['root'] ) && is_array( $tree['root'] ) ) {
			return $tree['root'];
		}

		return $tree;
	}

	/**
	 * Recursively walk the BD tree, collecting review IDs from ScosReviewCard
	 * nodes and following Global Blocks / Posts Loop repeated blocks.
	 *
	 * Tolerates both fully-qualified and short class-name `type` values.
	 *
	 * @param array      $node          Current tree fragment.
	 * @param array      $collected     Reference; review IDs are appended.
	 * @param bool       $has_connected Reference; set when a connected-mode card is found.
	 * @param int[]|null $loop_ids      Review IDs of the enclosing Posts Loop, or null.
	 * @param int[]      $stack         Documents being walked (recursion guard).
	 * @return void
	 */
	private static function walk_bd_tree( array $node, array &$collected, bool &$has_connected, ?array $loop_ids, array $stack ): void {
		$type = self::resolve_node_type( $node );

		if ( self::BD_ELEMENT_TYPE === $type || self::BD_ELEMENT_TYPE_SHORT === $type ) {
			self::harvest_review_node( self::get_node_properties( $node ), $collected, $has_connected, $loop_ids );
		} elseif ( self::BD_GLOBAL_BLOCK === $type ) {
			$properties = self::get_node_properties( $node );
			$block_id   = self::extract_post_id( $properties['content']['content']['block'] ?? 0 );
			// A Global Block inside a loop item inherits the loop's posts.
			self::walk_document( $block_id, $collected, $has_connected, $loop_ids, $stack );
		} elseif ( self::BD_POSTS_LOOP === $type ) {
			self::harvest_posts_loop( self::get_node_properties( $node ), $collected, $has_connected, $stack );
		}

		foreach ( $node as $value ) {
			if ( is_array( $value ) ) {
				self::walk_bd_tree( $value, $collected, $has_connected, $loop_ids, $stack );
			}
		}
	}

	/**
	 * Follow a Posts Loop into its repeated Global Block.
	 *
	 * The loop query only runs when the block itself contains a review card,
	 * so loops of blog posts, products etc. cost a strpos and nothing more.
	 * A card nested a further Global Block deep inside the loop item is not
	 * detected.
	 *
	 * @param array $properties    Posts Loop element properties.
	 * @param array $collected     Reference; review IDs are appended.
	 * @param bool  $has_connected Reference.
	 * @param int[] $stack         Documents being walked.
	 * @return void
	 */
	private static function harvest_posts_loop( array $properties, array &$collected, bool &$has_connected, array $stack ): void {
		$block_id = self::extract_post_id( $properties['content']['repeated_block']['global_block'] ?? 0 );
		if ( $block_id <= 0 ) {
			return;
		}

		$loop_ids = false !== strpos( self::get_raw_tree( $block_id ), self::BD_ELEMENT_TYPE_SHORT )
			? self::get_loop_query_ids( $properties )
			: [];

		self::walk_document( $block_id, $collected, $has_connected, $loop_ids, $stack );
	}

	/**
	 * Run a Posts Loop's query the way Breakdance does, returning IDs only.
	 *
	 * Pagination is ignored (page 1 is what crawlers see). "PHP" custom queries
	 * are skipped — SCOS never evals builder code outside Breakdance's own render.
	 *
	 * @param array $properties Posts Loop element properties.
	 * @return int[]
	 */
	private static function get_loop_query_ids( array $properties ): array {
		$args_fn = '\Breakdance\WpQueryControl\getWpQueryArgumentsFromWpQueryControlProperties';
		if ( ! function_exists( $args_fn ) ) {
			return [];
		}

		$query_props = $properties['content']['query']['query'] ?? null;
		if ( is_array( $query_props ) && 'php' === ( $query_props['active'] ?? '' ) ) {
			return [];
		}

		$args = $args_fn( $query_props, [] );
		if ( is_string( $args ) ) {
			$args = wp_parse_args( $args );
		}
		if ( ! is_array( $args ) ) {
			return [];
		}

		$args['fields']                 = 'ids';
		$args['no_found_rows']          = true;
		$args['update_post_meta_cache'] = false;
		$args['update_post_term_cache'] = false;
		unset( $args['paged'], $args['offset'] );

		$query = new \WP_Query( $args );
		return array_map( 'intval', (array) $query->posts );
	}

	/**
	 * Element properties of a BD node, regardless of nesting.
	 *
	 * @param array $node
	 * @return array
	 */
	private static function get_node_properties( array $node ): array {
		if ( isset( $node['data']['properties'] ) && is_array( $node['data']['properties'] ) ) {
			return $node['data']['properties'];
		}
		if ( isset( $node['properties'] ) && is_array( $node['properties'] ) ) {
			return $node['properties'];
		}
		return [];
	}

	/**
	 * Resolve the BD element type string from a node, regardless of nesting.
	 *
	 * @param array $node
	 * @return string Empty string when no recognisable type field is present.
	 */
	private static function resolve_node_type( array $node ): string {
		if ( isset( $node['data'] ) && is_array( $node['data'] ) && isset( $node['data']['type'] ) ) {
			return self::stringify_type( $node['data']['type'] );
		}
		if ( isset( $node['type'] ) ) {
			return self::stringify_type( $node['type'] );
		}
		return '';
	}

	/**
	 * Normalise a `type` value to a string.
	 *
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
	 * Extract review data from a matched ScosReviewCard node's properties.
	 *
	 * - "specific" mode: appends the explicit review_id to $collected.
	 * - "loop" mode: appends the enclosing loop's IDs (nothing outside a loop).
	 * - "connected" mode: sets $has_connected = true (IDs resolved later from meta).
	 *
	 * @param array      $properties    The card's element properties.
	 * @param array      $collected     Reference; review IDs are appended.
	 * @param bool       $has_connected Reference; set to true for connected-mode elements.
	 * @param int[]|null $loop_ids      Review IDs of the enclosing Posts Loop, or null.
	 * @return void
	 */
	private static function harvest_review_node( array $properties, array &$collected, bool &$has_connected, ?array $loop_ids ): void {
		$source = $properties['content']['source'] ?? [];
		$source = is_array( $source ) ? $source : [];

		$mode = isset( $source['mode'] ) ? (string) $source['mode'] : 'loop';

		if ( 'connected' === $mode ) {
			$has_connected = true;
			return;
		}

		if ( 'loop' === $mode ) {
			if ( null !== $loop_ids ) {
				$collected = array_merge( $collected, $loop_ids );
			}
			return;
		}

		if ( 'specific' !== $mode || empty( $source['review_id'] ) ) {
			return;
		}

		$id = self::extract_post_id( $source['review_id'] );
		if ( $id > 0 ) {
			$collected[] = $id;
		}
	}

	/**
	 * Query bw_reviews posts linked to a project via bw_related_project meta.
	 *
	 * @param int $project_id The project post ID.
	 * @return int[]
	 */
	private static function get_connected_review_ids( int $project_id ): array {
		$query = new \WP_Query( [
			'post_type'              => 'bw_reviews',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => [ [
				'key'     => 'bw_related_project',
				'value'   => $project_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			] ],
		] );
		return array_map( 'intval', (array) $query->posts );
	}

	/**
	 * Pull a post ID out of a BD post-picker value. Tolerates plain integers
	 * and the various BD picker shapes ({id}, {value}, {post: {id}}, etc.).
	 *
	 * Mirrors FAQ_Schema_Graph::extract_post_id().
	 *
	 * @param mixed $item
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

	// =========================================================================
	// Schema object builder
	// =========================================================================

	/**
	 * Build a schema.org AggregateRating object from all published bw_reviews.
	 *
	 * Aggregates across ALL platforms. Returns null when there are no published
	 * reviews with a numeric rating (avoids emitting a zero-count aggregate).
	 *
	 * Public so it can be called directly from WP-CLI / MCP tools.
	 *
	 * @return array|null
	 */
	public static function get_aggregate_rating(): ?array {
		$query = new \WP_Query( [
			'post_type'              => 'bw_reviews',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		] );

		$total = 0.0;
		$count = 0;

		foreach ( $query->posts as $post_id ) {
			$rating = get_post_meta( (int) $post_id, 'bw_rating', true );
			if ( '' !== $rating && is_numeric( $rating ) ) {
				$total += (float) $rating;
				$count++;
			}
		}

		if ( $count === 0 ) {
			return null;
		}

		$average = round( $total / $count, 1 );

		return [
			'@type'       => 'AggregateRating',
			'ratingValue' => number_format( $average, 1 ),
			'bestRating'  => '5',
			'worstRating' => '1',
			'reviewCount' => $count,
		];
	}

	/**
	 * Build a schema.org Review object for a single bw_reviews post.
	 *
	 * Returns null when the post does not exist or is not a bw_reviews post.
	 * The `url` field (review verification link) is omitted when empty.
	 *
	 * @param int $review_id bw_reviews post ID.
	 * @return array|null
	 */
	private static function build_review_object( int $review_id ): ?array {
		$post = get_post( $review_id );
		if ( ! $post || 'bw_reviews' !== $post->post_type ) {
			return null;
		}

		$rating = (int) get_post_meta( $review_id, 'bw_rating', true );
		if ( $rating < 1 ) {
			$rating = 5; // Default to 5 if unset — better than emitting ratingValue: 0.
		}

		$excerpt_meta = get_post_meta( $review_id, 'bw_review_excerpt', true );
		$body         = $excerpt_meta
			? (string) $excerpt_meta
			: wp_strip_all_tags( $post->post_content );

		$date_raw   = (string) get_post_meta( $review_id, 'bw_date', true );
		$verify_url = (string) get_post_meta( $review_id, 'bw_verify_url', true );

		$review = [
			'@type'        => 'Review',
			'author'       => [
				'@type' => 'Person',
				'name'  => get_the_title( $review_id ),
			],
			'reviewRating' => [
				'@type'       => 'Rating',
				'ratingValue' => $rating,
				'bestRating'  => 5,
				'worstRating' => 1,
			],
			'reviewBody'    => $body,
			'datePublished' => $date_raw,
		];

		if ( '' !== $verify_url ) {
			$review['url'] = $verify_url;
		}

		return $review;
	}
}
