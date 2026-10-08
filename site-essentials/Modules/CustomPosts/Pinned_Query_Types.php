<?php
/**
 * Pinned Query Types
 *
 * Lets queries that name posts by ID return SCOS CPTs that are
 * `exclude_from_search => true` (bw_reviews, FAQs).
 *
 * WP_Query expands `post_type => 'any'` to only the types with
 * exclude_from_search = false, so those CPTs are silently dropped even when
 * their IDs are listed in post__in. Breakdance's ACF relationship loop builds
 * exactly that query (`'post_type' => 'any'` + `post__in`, hardcoded in
 * breakdance/plugin/wp-query-control/query-control.php, no filter), so a
 * relationship field pointing at reviews renders an empty loop.
 *
 * When a query is 'any' AND pinned to explicit IDs AND is not a search, the
 * caller has already chosen the exact posts — widen 'any' to every public
 * type so the type filter can't discard them. Search results are untouched,
 * so these CPTs stay out of site search.
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 *
 * v1.0 | 2026-10-08
 */

namespace SiteEssentials\Modules\CustomPosts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pinned_Query_Types {

	public static function init(): void {
		add_action( 'pre_get_posts', [ self::class, 'widen_any_for_pinned_ids' ] );
	}

	/**
	 * @param \WP_Query $query Query being prepared.
	 */
	public static function widen_any_for_pinned_ids( \WP_Query $query ): void {
		if ( 'any' !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( $query->is_search() || '' !== (string) $query->get( 's' ) ) {
			return;
		}

		$ids = array_filter( array_map( 'absint', (array) $query->get( 'post__in' ) ) );
		if ( empty( $ids ) ) {
			return;
		}

		$query->set( 'post_type', array_values( get_post_types( [ 'public' => true ] ) ) );
	}
}
