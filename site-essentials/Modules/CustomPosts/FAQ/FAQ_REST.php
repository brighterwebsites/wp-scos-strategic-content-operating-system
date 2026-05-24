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
 * v1.1 | 2026-05-22 — Added intent_goal context to search (includes drafts);
 *                      added POST /faqs to create stub FAQ from CA meta box.
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
		// GET all published FAQs (block selector).
		register_rest_route(
			self::NAMESPACE_PREFIX,
			'/faqs',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ self::class, 'get_all' ],
					'permission_callback' => [ self::class, 'permission_check' ],
				],
				// POST — create a stub FAQ from the CA meta box intent goal picker.
				[
					'methods'             => 'POST',
					'callback'            => [ self::class, 'create_stub' ],
					'permission_callback' => [ self::class, 'permission_check' ],
					'args'                => [
						'title'          => [
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'topic_id'       => [
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						],
						'source_post_id' => [
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		// GET search — supports ?context=intent_goal to include drafts.
		register_rest_route(
			self::NAMESPACE_PREFIX,
			'/faqs/search',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'search' ],
				'permission_callback' => [ self::class, 'permission_check' ],
				'args'                => [
					'q'       => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'context' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);
	}

	/**
	 * Permission callback — editor-context only.
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
	 * GET /site-essentials/v1/faqs/search?q=keyword[&context=intent_goal]
	 *
	 * When context=intent_goal, includes draft FAQs so newly created stubs
	 * appear immediately in the picker without requiring publication.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function search( \WP_REST_Request $request ): \WP_REST_Response {
		$keyword = (string) $request->get_param( 'q' );
		$context = (string) $request->get_param( 'context' );

		$statuses = ( 'intent_goal' === $context )
			? [ 'publish', 'draft' ]
			: [ 'publish' ];

		$faqs = get_posts( [
			'post_type'      => FAQ_Module::POST_TYPE,
			'post_status'    => $statuses,
			'posts_per_page' => 30,
			's'              => $keyword,
			'orderby'        => 'relevance',
			'no_found_rows'  => true,
		] );

		return new \WP_REST_Response(
			array_map( [ self::class, 'format_faq_for_intent_goal' ], $faqs ),
			200
		);
	}

	/**
	 * POST /site-essentials/v1/faqs
	 *
	 * Create a draft stub FAQ for use as an intent goal. Returns the new FAQ
	 * formatted as format_faq_for_intent_goal so the meta box JS can display
	 * the linked FAQ panel immediately.
	 *
	 * @since 1.1.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function create_stub( \WP_REST_Request $request ) {
		// Resolve Intent_Goal_Resolver — loaded by ContentArchitecture_Module.
		if ( ! class_exists( \SiteEssentials\Modules\ContentArchitecture\Intent_Goal_Resolver::class ) ) {
			return new \WP_REST_Response(
				[ 'message' => __( 'Intent_Goal_Resolver not available.', 'site-essentials' ) ],
				500
			);
		}

		$title          = (string) $request->get_param( 'title' );
		$topic_id       = (int) $request->get_param( 'topic_id' );
		$source_post_id = (int) $request->get_param( 'source_post_id' );

		$result = \SiteEssentials\Modules\ContentArchitecture\Intent_Goal_Resolver::create_stub_faq(
			$title,
			$topic_id,
			$source_post_id
		);

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				[ 'message' => $result->get_error_message() ],
				400
			);
		}

		$faq = get_post( $result );
		if ( ! $faq ) {
			return new \WP_REST_Response( [ 'message' => __( 'FAQ created but could not be retrieved.', 'site-essentials' ) ], 500 );
		}

		return new \WP_REST_Response( self::format_faq_for_intent_goal( $faq ), 201 );
	}

	/**
	 * Shape a WP_Post into the response array expected by the block JS (original format).
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

	/**
	 * Shape a WP_Post into the richer array used by the intent goal picker.
	 *
	 * Includes topic, status, and incomplete flag from Intent_Goal_Resolver.
	 *
	 * @since 1.1.0
	 * @param \WP_Post $faq FAQ post.
	 * @return array
	 */
	public static function format_faq_for_intent_goal( \WP_Post $faq ): array {
		$topic      = '';
		$topic_slug = '';
		$terms      = get_the_terms( $faq->ID, 'scos_topic' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$topic      = $terms[0]->name;
			$topic_slug = $terms[0]->slug;
		}

		$incomplete = false;
		if ( class_exists( \SiteEssentials\Modules\ContentArchitecture\Intent_Goal_Resolver::class ) ) {
			$incomplete = \SiteEssentials\Modules\ContentArchitecture\Intent_Goal_Resolver::is_faq_incomplete( $faq->ID );
		}

		return [
			'id'         => (int) $faq->ID,
			'title'      => (string) get_the_title( $faq->ID ),
			'status'     => $faq->post_status,
			'topic'      => $topic,
			'topic_slug' => $topic_slug,
			'edit_url'   => (string) get_edit_post_link( $faq->ID, 'raw' ),
			'incomplete' => $incomplete,
		];
	}
}
