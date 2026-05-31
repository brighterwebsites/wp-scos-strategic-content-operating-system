<?php
// v1.0 | 2026-06-01

/**
 * Review schema token resolver.
 *
 * Registers the %%_scos_review_cards_json%% token via the
 * `bw_schema_resolve_variable` filter exposed by
 * brighter-core/includes/scos-schema-output.php.
 *
 * At resolve time the class walks the Breakdance element tree for the current
 * post, collects every ScosReviewCard element configured in "specific" mode,
 * fetches the associated bw_reviews data, and returns a PHP array of
 * schema.org Review objects.
 *
 * Because bw_schema_replace_variables() replaces a whole-value token with
 * whatever the resolver returns (including arrays — same behaviour as
 * %%post_thumbnail%%), the token can be used directly in per-post JSON-LD:
 *
 *   { "@type": "Service", "review": "%%_scos_review_cards_json%%" }
 *
 * Only "specific" mode elements contribute reviews (loop-mode elements don't
 * hold an explicit post ID so cannot be safely resolved at schema-output time).
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
	 * Token name (without %% delimiters) this resolver handles.
	 */
	const TOKEN_NAME = '_scos_review_cards_json';

	/**
	 * Register the token resolver filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'bw_schema_resolve_variable', [ self::class, 'resolve_token' ], 10, 3 );
	}

	/**
	 * Resolve %%_scos_review_cards_json%% to a PHP array of Review objects.
	 *
	 * Hooked onto `bw_schema_resolve_variable`.
	 *
	 * @param mixed  $value   Current resolved value (pass-through for other tokens).
	 * @param string $name    Token name without %% delimiters.
	 * @param int    $post_id Current page post ID.
	 * @return mixed
	 */
	public static function resolve_token( $value, string $name, int $post_id ) {
		if ( self::TOKEN_NAME !== $name ) {
			return $value;
		}
		return self::get_review_array( $post_id );
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
	 * Read `_breakdance_data` for the page and collect bw_reviews post IDs
	 * from every ScosReviewCard element configured in "specific" mode.
	 *
	 * Returns an empty array on any decode error or when no matching element
	 * is found — these are "no reviews from BD" outcomes, not errors.
	 *
	 * @param int $post_id Page post ID.
	 * @return int[]
	 */
	private static function collect_review_ids_from_breakdance( int $post_id ): array {
		// Try both meta key variants used across BD versions.
		$raw = get_post_meta( $post_id, '_breakdance_data', true );
		if ( empty( $raw ) || ! is_string( $raw ) ) {
			$raw = get_post_meta( $post_id, 'breakdance_data', true );
		}
		if ( empty( $raw ) ) {
			return [];
		}

		// Cheap pre-check before JSON decoding — skip pages without this element.
		$haystack = is_string( $raw ) ? $raw : wp_json_encode( $raw );
		if ( false === strpos( (string) $haystack, self::BD_ELEMENT_TYPE_SHORT ) ) {
			return [];
		}

		$tree = self::decode_bd_tree( $raw );
		if ( null === $tree ) {
			return [];
		}

		$collected = [];
		self::walk_bd_tree( $tree, $collected );

		return $collected;
	}

	/**
	 * Unwrap `_breakdance_data` to the root node of the tree.
	 *
	 * Mirrors FAQ_Schema_Graph::decode_bd_tree().
	 *
	 * @param mixed $raw Raw meta value (string or pre-decoded array).
	 * @return array|null Root node, or null when the data cannot be parsed.
	 */
	private static function decode_bd_tree( $raw ) {
		$outer = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
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
	 * nodes that are configured in "specific" mode.
	 *
	 * Tolerates both fully-qualified and short class-name `type` values, and
	 * accepts both `data.type` and flat `type` node shapes. Recurses into every
	 * array value (not just `children`) for resilience across BD versions.
	 *
	 * @param array $node      Current tree fragment.
	 * @param array $collected Reference; review IDs are appended.
	 * @return void
	 */
	private static function walk_bd_tree( array $node, array &$collected ): void {
		$type = self::resolve_node_type( $node );

		if ( self::BD_ELEMENT_TYPE === $type || self::BD_ELEMENT_TYPE_SHORT === $type ) {
			self::harvest_review_node( $node, $collected );
		}

		foreach ( $node as $value ) {
			if ( is_array( $value ) ) {
				self::walk_bd_tree( $value, $collected );
			}
		}
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
	 * Extract the review post ID from a matched ScosReviewCard node.
	 *
	 * Only "specific" mode elements carry an explicit review ID.
	 * Property path mirrors element.php: content.source.{mode, review_id}.
	 *
	 * @param array $node      The matched element node.
	 * @param array $collected Reference; review IDs are appended.
	 * @return void
	 */
	private static function harvest_review_node( array $node, array &$collected ): void {
		$properties = [];
		if ( isset( $node['data']['properties'] ) && is_array( $node['data']['properties'] ) ) {
			$properties = $node['data']['properties'];
		} elseif ( isset( $node['properties'] ) && is_array( $node['properties'] ) ) {
			$properties = $node['properties'];
		}

		$content = isset( $properties['content'] ) && is_array( $properties['content'] )
			? $properties['content']
			: [];

		$source = isset( $content['source'] ) && is_array( $content['source'] )
			? $content['source']
			: [];

		$mode = isset( $source['mode'] ) ? (string) $source['mode'] : 'loop';

		if ( 'specific' !== $mode || empty( $source['review_id'] ) ) {
			return;
		}

		$id = self::extract_post_id( $source['review_id'] );
		if ( $id > 0 ) {
			$collected[] = $id;
		}
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
