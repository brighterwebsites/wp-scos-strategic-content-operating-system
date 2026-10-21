<?php
/**
 * Agentic — Content REST API
 *
 * v1.1 | 2026-10-21
 *
 * v1.1 | 2026-10-21 — site-info describes the site's own business from the
 *                     Business Info fields (Business_Profile). The default was
 *                     a hardcoded agency description, so every site without a
 *                     stored override told agents it was that agency.
 *
 * Public, read-only content endpoints under the scos/v1 namespace. Designed
 * for consumption by WebMCP tools, AI agents, and the WP-CLI/MCP layer.
 *
 * Routes:
 *   GET /wp-json/scos/v1/search          keyword + optional purpose/intent filters
 *   GET /wp-json/scos/v1/list            full list, optional purpose/intent filters
 *   GET /wp-json/scos/v1/content/{slug}  full body for one item by slug
 *   GET /wp-json/scos/v1/site-info       the business, from Business Info (Business_Profile)
 *
 * All routes are unauthenticated GET — no write endpoints, no nonce required.
 * Filter params map to the real SCOS CA meta fields (scos_ca_purpose, scos_ca_intent).
 *
 * site-info comes from the Business Info fields (Business_Profile::get()).
 * A stored scos_webmcp_site_info option still overrides it completely, for a
 * site that needs hand-written framing:
 *   wp option update scos_webmcp_site_info '{"name":"..."}' --format=json
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Content_API {

	const NAMESPACE = 'scos/v1';

	// ── Boot ─────────────────────────────────────────────────────────────────

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	// ── Route registration ────────────────────────────────────────────────────

	public static function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/search', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'handle_search' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'query' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => 'Keyword to search for in post title and content.',
				],
				'purpose' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => 'Filter by scos_ca_purpose (e.g. service-page, pillar, case-study).',
				],
				'intent' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => 'Filter by scos_ca_intent (e.g. informational, commercial, transact_lead).',
				],
			],
		] );

		register_rest_route( self::NAMESPACE, '/list', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'handle_list' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'purpose' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => 'Filter by scos_ca_purpose.',
				],
				'intent' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => 'Filter by scos_ca_intent.',
				],
			],
		] );

		register_rest_route( self::NAMESPACE, '/content/(?P<slug>[a-zA-Z0-9-_]+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'handle_content' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'slug' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_title',
					'description'       => 'Post slug.',
				],
			],
		] );

		register_rest_route( self::NAMESPACE, '/site-info', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'handle_site_info' ],
			'permission_callback' => '__return_true',
		] );
	}

	// ── Handlers ──────────────────────────────────────────────────────────────

	/**
	 * Search content by keyword with optional purpose/intent filters.
	 */
	public static function handle_search( \WP_REST_Request $request ): \WP_REST_Response {
		$query   = (string) $request->get_param( 'query' );
		$purpose = (string) $request->get_param( 'purpose' );
		$intent  = (string) $request->get_param( 'intent' );

		$args = [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'no_found_rows'  => true,
			's'              => $query,
		];

		$args = self::apply_meta_filters( $args, $purpose, $intent );

		$posts = get_posts( $args );
		update_meta_cache( 'post', wp_list_pluck( $posts, 'ID' ) );

		return new \WP_REST_Response( array_map( [ __CLASS__, 'format_lightweight' ], $posts ), 200 );
	}

	/**
	 * List all published content with optional purpose/intent filters.
	 */
	public static function handle_list( \WP_REST_Request $request ): \WP_REST_Response {
		$purpose = (string) $request->get_param( 'purpose' );
		$intent  = (string) $request->get_param( 'intent' );

		$args = [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'no_found_rows'  => true,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$args = self::apply_meta_filters( $args, $purpose, $intent );

		$posts = get_posts( $args );
		update_meta_cache( 'post', wp_list_pluck( $posts, 'ID' ) );

		return new \WP_REST_Response( array_map( [ __CLASS__, 'format_lightweight' ], $posts ), 200 );
	}

	/**
	 * Return full content for one item by slug.
	 */
	public static function handle_content( \WP_REST_Request $request ): \WP_REST_Response {
		$slug = (string) $request->get_param( 'slug' );

		$posts = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'publish',
			'name'           => $slug,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		] );

		if ( empty( $posts ) ) {
			return new \WP_REST_Response( [ 'error' => 'Not found.' ], 404 );
		}

		$post    = $posts[0];
		$content = self::strip_to_readable( $post->post_content );

		$response = [
			'title'          => esc_html( get_the_title( $post ) ),
			'slug'           => $post->post_name,
			'url'            => get_permalink( $post ),
			'published_date' => get_the_date( 'Y-m-d', $post ),
			'purpose'        => get_post_meta( $post->ID, 'scos_ca_purpose', true ),
			'intent'         => get_post_meta( $post->ID, 'scos_ca_intent', true ),
			'content'        => $content,
		];

		// Surface the Breakdance limitation to agents so they know to use the
		// markdown endpoint instead (Accept: text/markdown on the page URL).
		if ( '' === $content ) {
			$response['_note'] = 'Content is empty — this page is built with Breakdance and its body is not stored in post_content. Request the page URL with the Accept: text/markdown header to retrieve full readable text.';
		}

		return new \WP_REST_Response( $response, 200 );
	}

	/**
	 * Return the business profile, or the scos_webmcp_site_info override.
	 */
	public static function handle_site_info( \WP_REST_Request $request ): \WP_REST_Response {
		$stored = get_option( 'scos_webmcp_site_info', [] );

		if ( empty( $stored ) || ! is_array( $stored ) ) {
			$stored = self::get_site_info_default();
		}

		return new \WP_REST_Response( $stored, 200 );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Apply scos_ca_purpose and scos_ca_intent meta_query filters when provided.
	 *
	 * @param  array  $args    Existing WP_Query args.
	 * @param  string $purpose scos_ca_purpose value to filter by (empty = no filter).
	 * @param  string $intent  scos_ca_intent value to filter by (empty = no filter).
	 * @return array
	 */
	private static function apply_meta_filters( array $args, string $purpose, string $intent ): array {
		$clauses = [];

		if ( '' !== $purpose ) {
			$clauses[] = [
				'key'     => 'scos_ca_purpose',
				'value'   => $purpose,
				'compare' => '=',
			];
		}

		if ( '' !== $intent ) {
			$clauses[] = [
				'key'     => 'scos_ca_intent',
				'value'   => $intent,
				'compare' => '=',
			];
		}

		if ( ! empty( $clauses ) ) {
			$clauses['relation'] = 'AND';
			$args['meta_query']  = $clauses;
		}

		return $args;
	}

	/**
	 * Lightweight post shape for search/list endpoints.
	 *
	 * @param  \WP_Post $post
	 * @return array
	 */
	private static function format_lightweight( \WP_Post $post ): array {
		return [
			'title'   => esc_html( get_the_title( $post ) ),
			'slug'    => $post->post_name,
			'url'     => get_permalink( $post ),
			'excerpt' => wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ?: $post->post_content ), 30, '...' ),
			'purpose' => get_post_meta( $post->ID, 'scos_ca_purpose', true ),
			'intent'  => get_post_meta( $post->ID, 'scos_ca_intent', true ),
		];
	}

	/**
	 * Strip shortcodes and HTML from post content for clean agent consumption.
	 *
	 * KNOWN LIMITATION — Breakdance pages return empty content:
	 * Breakdance stores its layout in post meta (_breakdance_data), not in
	 * post_content, so pages built with Breakdance will have an empty "content"
	 * field in the /content/{slug} response. This is expected and not a bug.
	 *
	 * Future: a Breakdance-aware extractor could parse _breakdance_data and
	 * extract text nodes, or the /mcp/ page can note that agents should use
	 * the Markdown endpoint (Accept: text/markdown) for full page text instead.
	 *
	 * @param  string $content Raw post_content.
	 * @return string
	 */
	private static function strip_to_readable( string $content ): string {
		$content = do_shortcode( $content );
		$content = wp_strip_all_tags( $content );
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( '/\n{3,}/', "\n\n", $content ) );
	}

	/**
	 * Site-info used when no scos_webmcp_site_info override is stored.
	 *
	 * @return array
	 */
	public static function get_site_info_default(): array {
		return \SiteEssentials\Modules\BusinessInfo\Business_Profile::get();
	}
}
