<?php
/**
 * FAQ REST endpoints — editor-context only.
 *
 * Registers `site-essentials/v1/faqs` and `site-essentials/v1/faqs/search` for the
 * FAQ Selector Gutenberg block. These routes use the WordPress auth cookie/nonce
 * (`current_user_can( 'edit_posts' )`) — they are NOT for external API consumers.
 *
 * The legacy `brighter-core/v1/faqs` route (token-authed, owned by
 * Brighter_API_Endpoints) is left untouched and remains the canonical external
 * API for GPT/MCP/Postly.
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

class FAQ_REST {

	const NAMESPACE_PREFIX = 'site-essentials/v1';

	/**
	 * Register REST routes on rest_api_init.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * Register the FAQ endpoints.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_PREFIX,
			'/faqs',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'get_all' ],
				'permission_callback' => [ self::class, 'permission_check' ],
			]
		);

		register_rest_route(
			self::NAMESPACE_PREFIX,
			'/faqs/search',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'search' ],
				'permission_callback' => [ self::class, 'permission_check' ],
				'args'                => [
					'q' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Permission callback — editor-context only.
	 *
	 * The block runs in the Gutenberg editor where the user is logged in
	 * and has at least edit_posts. WordPress core attaches the nonce
	 * automatically via wp.apiFetch.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function permission_check(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * GET /site-essentials/v1/faqs
	 *
	 * Returns all published FAQs ordered by title.
	 *
	 * @since 1.0.0
	 * @return \WP_REST_Response
	 */
	public static function get_all(): \WP_REST_Response {
		$faqs = get_posts( [
			'post_type'      => FAQ_Module::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		] );

		return new \WP_REST_Response( array_map( [ self::class, 'format_faq' ], $faqs ), 200 );
	}

	/**
	 * GET /site-essentials/v1/faqs/search?q=keyword
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function search( \WP_REST_Request $request ): \WP_REST_Response {
		$keyword = (string) $request->get_param( 'q' );
		$faqs    = FAQ_Module::search( $keyword );
		return new \WP_REST_Response( array_map( [ self::class, 'format_faq' ], $faqs ), 200 );
	}

	/**
	 * Shape a WP_Post into the response array expected by the block JS.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $faq FAQ post.
	 * @return array
	 */
	private static function format_faq( \WP_Post $faq ): array {
		$schema_answer = (string) get_post_meta( $faq->ID, FAQ_Module::META_SCHEMA_ANSWER, true );
		if ( '' === $schema_answer ) {
			$schema_answer = (string) get_post_meta( $faq->ID, FAQ_Module::LEGACY_META_SCHEMA_ANSWER, true );
		}

		$enabled = get_post_meta( $faq->ID, FAQ_Module::META_ENABLE_SCHEMA, true );
		if ( '' === $enabled ) {
			$enabled = get_post_meta( $faq->ID, FAQ_Module::LEGACY_META_ENABLE_SCHEMA, true );
		}

		return [
			'id'             => (int) $faq->ID,
			'question'       => (string) get_the_title( $faq->ID ),
			'answer'         => (string) $faq->post_content,
			'schema_answer'  => $schema_answer,
			'schema_enabled' => '0' !== (string) $enabled,
		];
	}
}
