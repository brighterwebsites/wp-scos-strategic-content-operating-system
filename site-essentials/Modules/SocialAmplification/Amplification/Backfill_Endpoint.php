<?php
/**
 * REST API Endpoint — bw-social/v1/backfill
 *
 * Batch-runs amplification for existing projects posts using either:
 * - explicit post_ids
 * - date range + limit
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification\Amplification
 */

namespace SiteEssentials\Modules\SocialAmplification\Amplification;

use SiteEssentials\Modules\SocialAmplification\Publish_Hook;

defined( 'ABSPATH' ) || exit;

class Backfill_Endpoint {

	const NAMESPACE = 'bw-social/v1';
	const ROUTE     = '/backfill';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route( self::NAMESPACE, self::ROUTE, [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ __CLASS__, 'handle' ],
			'permission_callback' => [ __CLASS__, 'permission_check' ],
			'args'                => [
				'secret' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'post_ids' => [
					'required'          => false,
					'type'              => 'array',
					'sanitize_callback' => [ __CLASS__, 'sanitize_post_ids' ],
				],
				'date_from' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'date_to' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'limit' => [
					'required'          => false,
					'type'              => 'integer',
					'default'           => 5,
					'sanitize_callback' => 'absint',
				],
			],
		] );
	}

	public static function permission_check( \WP_REST_Request $request ): bool {
		$secret        = (string) $request->get_param( 'secret' );
		$stored_secret = (string) get_option( 'bw_social_webhook_secret', '' );
		return (bool) ( $stored_secret && hash_equals( $stored_secret, $secret ) );
	}

	public static function sanitize_post_ids( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		return array_values( array_filter( array_map( 'absint', $value ) ) );
	}

	public static function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$post_ids  = (array) $request->get_param( 'post_ids' );
		$date_from = (string) $request->get_param( 'date_from' );
		$date_to   = (string) $request->get_param( 'date_to' );
		$limit     = max( 1, min( 100, (int) $request->get_param( 'limit' ) ) );

		$posts = self::resolve_posts( $post_ids, $date_from, $date_to, $limit );
		if ( is_wp_error( $posts ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => $posts->get_error_message(),
			], 400 );
		}

		$results = [];
		foreach ( $posts as $post ) {
			try {
				$result = Amplification_Engine::run( (int) $post->ID );
				update_post_meta( (int) $post->ID, Publish_Hook::AMPLIFIED_META, '1' );
				$results[] = [
					'post_id' => (int) $post->ID,
					'title'   => get_the_title( $post ),
					'status'  => 'scheduled',
					'posts'   => $result['posts'] ?? [],
				];
			} catch ( \RuntimeException $e ) {
				$results[] = [
					'post_id' => (int) $post->ID,
					'title'   => get_the_title( $post ),
					'status'  => 'error',
					'error'   => $e->getMessage(),
					'posts'   => [],
				];
			}
		}

		return new \WP_REST_Response( [
			'success' => true,
			'count'   => count( $results ),
			'data'    => $results,
		], 200 );
	}

	/**
	 * @return \WP_Post[]|\WP_Error
	 */
	private static function resolve_posts( array $post_ids, string $date_from, string $date_to, int $limit ) {
		if ( ! empty( $post_ids ) ) {
			$posts = get_posts( [
				'post_type'      => 'projects',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'post__in'       => $post_ids,
				'orderby'        => 'post__in',
			] );
			return self::filter_unamplified( $posts );
		}

		if ( ! $date_from || ! $date_to ) {
			return new \WP_Error( 'invalid_params', 'Provide either post_ids or both date_from and date_to.' );
		}

		$posts = get_posts( [
			'post_type'      => 'projects',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'date_query'     => [
				[
					'after'     => $date_from,
					'before'    => $date_to,
					'inclusive' => true,
				],
			],
		] );

		return self::filter_unamplified( $posts );
	}

	/**
	 * @param \WP_Post[] $posts
	 * @return \WP_Post[]
	 */
	private static function filter_unamplified( array $posts ): array {
		return array_values( array_filter( $posts, static function ( \WP_Post $post ) {
			return get_post_meta( $post->ID, Publish_Hook::AMPLIFIED_META, true ) !== '1';
		} ) );
	}
}

