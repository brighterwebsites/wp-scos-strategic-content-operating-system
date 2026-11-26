<?php
/**
 * Get URL SEO State — WordPress Ability
 *
 * What a URL actually serves for SEO, and why: effective robots, canonical,
 * whether it is in the XML and HTML sitemaps, and any redirect that runs
 * first. Each value names its source (the post, the post type default, the
 * archive settings, WordPress). `warnings` lists where the page and the
 * sitemaps disagree. Works for posts, pages, CPTs and archives (terms, CPT
 * archives, author, date, blog, search).
 *
 * Tool ability — no AI inside, read-only. All logic lives in Url_Seo_State,
 * which the page output and the sitemaps use too.
 *
 * Ability slug: scos/get-url-seo-state (permanent — do not rename after deployment)
 * Category:     scos-seo-meta
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\Abilities\Get_Url_Seo_State
 *
 * v1.0 | 2026-11-26
 */

declare( strict_types=1 );

namespace SiteEssentials\Modules\SeoMeta\Abilities\Get_Url_Seo_State;

use SiteEssentials\Core\Abilities\Abstract_Scos_Ability;
use SiteEssentials\Core\Abilities\Ability_Support;
use SiteEssentials\Core\Settings_Manager;
use SiteEssentials\Modules\Seo\Seo_Module;
use SiteEssentials\Modules\SeoMeta\Url_Seo_State;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Get_Url_Seo_State extends Abstract_Scos_Ability {

	/** Most URLs + post IDs per call. */
	const MAX_ITEMS = 50;

	/**
	 * Register this ability with the WordPress core Abilities API.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! Ability_Support::is_abilities_api_available() ) {
			return;
		}
		wp_register_ability( 'scos/get-url-seo-state', [
			'label'         => __( 'SCOS: Get URL SEO State', 'site-essentials' ),
			'description'   => __( 'For up to 50 front-end URLs or post IDs: the robots directives the page serves, its canonical, whether it is in the XML and HTML sitemaps, and any redirect or 410 that runs first. Each value names its source (post, post_type_default, archive_setting, blog_public, wordpress_default…). warnings lists where the page and the sitemaps disagree, e.g. noindex but listed in the sitemap. Covers posts, pages, custom post types and archives (categories and other terms, CPT archives, author, date, blog, search). Read-only, no AI inside.', 'site-essentials' ),
			'category'      => 'scos-seo-meta',
			'ability_class' => self::class,
			'meta'          => [
				'show_in_rest' => true,
				'mcp'          => [
					'public' => true,
					'type'   => 'tool',
				],
			],
		] );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'default'    => [],
			'properties' => [
				'urls'             => [
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
					'description' => 'Front-end URLs on this site, or paths starting with "/". Archives, paged URLs (/blog/page/2/) and search (/?s=term) work too.',
				],
				'post_ids'         => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'description' => 'Post IDs. A published post is checked at its permalink, so the posts page and the shop page report what they really serve.',
				],
				'include_settings' => [
					'type'        => 'boolean',
					'default'     => true,
					'description' => 'Include the site-wide sitemap and search-visibility settings once.',
				],
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'items'    => [
					'type'        => 'array',
					'description' => 'One per URL or post ID, in input order (URLs first). Each: input, target {type, post_id|slug, paged…}, robots {directives, index, follow, source}, canonical {url, source}, in_xml_sitemap {included, reason}, in_html_sitemap {included, reason}, redirects {to, status, reason} or null, warnings [], or error {code, message}.',
					'items'       => [ 'type' => 'object' ],
				],
				'settings' => [
					'type'        => 'object',
					'description' => 'blog_public, sitemap settings in effect and their source (stored|defaults).',
				],
			],
		];
	}

	/**
	 * @param mixed $input Validated input array.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_callback( $input ) {
		$input    = is_array( $input ) ? $input : [];
		$urls     = array_values( array_filter( array_map( 'strval', (array) ( $input['urls'] ?? [] ) ), 'strlen' ) );
		$post_ids = array_values( array_filter( array_map( 'intval', (array) ( $input['post_ids'] ?? [] ) ) ) );

		if ( empty( $urls ) && empty( $post_ids ) ) {
			return new \WP_Error( 'missing_input', __( 'Pass urls and/or post_ids.', 'site-essentials' ), [ 'status' => 400 ] );
		}
		if ( count( $urls ) + count( $post_ids ) > self::MAX_ITEMS ) {
			return new \WP_Error(
				'too_many_items',
				/* translators: %d: maximum number of URLs and post IDs */
				sprintf( __( 'At most %d URLs and post IDs per call.', 'site-essentials' ), self::MAX_ITEMS ),
				[ 'status' => 400 ]
			);
		}

		$settings = Seo_Module::sitemap_settings();
		$items    = [];

		foreach ( $urls as $url ) {
			$ctx = Url_Seo_State::context_for_url( $url );
			$items[] = is_wp_error( $ctx )
				? self::error_item( $url, $ctx )
				: Url_Seo_State::report( $ctx, self::absolute( $url ), $settings );
		}

		foreach ( $post_ids as $post_id ) {
			$items[] = self::post_item( $post_id, $settings );
		}

		$out = [ 'items' => $items ];
		if ( ! isset( $input['include_settings'] ) || $input['include_settings'] ) {
			$stored          = Settings_Manager::instance()->get_module_setting( 'seo', 'sitemap', null );
			$out['settings'] = [
				'blog_public' => '0' !== (string) get_option( 'blog_public' ),
				'sitemap'     => $settings,
				'source'      => is_array( $stored ) ? 'stored' : 'defaults',
				'notes'       => [
					'sitemap.enabled is saved but not checked when /sitemap.xml is served.',
					'The HTML sitemap only shows where the [site_essentials_sitemap] shortcode is placed.',
				],
			];
		}
		return $out;
	}

	/**
	 * Report for one post ID.
	 *
	 * @param int   $post_id
	 * @param array $settings
	 * @return array
	 */
	private static function post_item( int $post_id, array $settings ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return [ 'input' => $post_id, 'error' => [ 'code' => 'post_not_found', 'message' => 'Post not found.' ] ];
		}

		$permalink = (string) get_permalink( $post );
		if ( 'publish' === $post->post_status && '' !== $permalink ) {
			$ctx = Url_Seo_State::context_for_url( $permalink );
			if ( ! is_wp_error( $ctx ) && 'none' !== $ctx['type'] ) {
				$item          = Url_Seo_State::report( $ctx, $permalink, $settings );
				$item['input'] = $post_id;
				$item['url']   = $permalink;
				return $item;
			}
		}

		// Not public yet (or its URL doesn't route): what it would serve as a single.
		$item               = Url_Seo_State::report( [ 'type' => 'singular', 'post_id' => $post_id, 'query' => null ], $permalink, $settings );
		$item['input']      = $post_id;
		$item['url']        = $permalink;
		$item['warnings'][] = sprintf( 'Status is "%s": the page is not public, so these are the values it would serve once published.', $post->post_status );
		return $item;
	}

	private static function error_item( string $url, \WP_Error $error ): array {
		return [ 'input' => $url, 'error' => [ 'code' => $error->get_error_code(), 'message' => $error->get_error_message() ] ];
	}

	private static function absolute( string $url ): string {
		$url = trim( $url );
		return ( '' !== $url && '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) ? home_url( $url ) : $url;
	}

	/**
	 * Same capability as the other SEO meta tools.
	 *
	 * @param mixed $input Validated input array.
	 * @return bool
	 */
	public function permission_callback( $input ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function meta(): array {
		return [
			'show_in_rest' => true,
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'mcp'          => [
				'public' => true,
				'type'   => 'tool',
			],
		];
	}
}

add_action( 'wp_abilities_api_init', [ Get_Url_Seo_State::class, 'register' ] );
