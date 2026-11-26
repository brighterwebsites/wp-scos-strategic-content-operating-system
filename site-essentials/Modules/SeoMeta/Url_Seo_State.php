<?php
/**
 * SEO Meta — URL SEO State
 *
 * One source for what a URL actually serves for SEO: robots, canonical, XML
 * sitemap, HTML sitemap and redirects, with where each value comes from.
 *
 * Used by:
 *   - Head_Output (the live <head>, for the current request)
 *   - Seo_Module (XML and HTML sitemap inclusion, per post)
 *   - scos/get-url-seo-state and `wp scos url-seo-state` (any URL or post ID)
 *   - the meta box Advanced-tab indicator (post_overrides())
 *
 * The rules here reproduce the front end exactly, quirks included. Where the
 * front end, the XML sitemap and the HTML sitemap disagree, report() says so in
 * `warnings` rather than this class changing what any site serves.
 *
 * A "context" is an array:
 *   [ 'type' => 'archive',  'slug' => string, 'paged' => int, 'query' => WP_Query, 'request_uri' => ?string ]
 *   [ 'type' => 'singular', 'post_id' => int, 'query' => ?WP_Query ]
 *   [ 'type' => 'none',     'query' => ?WP_Query ]
 * request_uri is null for the current request; for a resolved URL it is that URL's path + query.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 * @version    1.0 | 2026-11-26
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\SeoMeta;

use SiteEssentials\Modules\Seo\Seo_Module;
use SiteEssentials\Modules\CustomPosts\Single_Url_Guard;
use SiteEssentials\Modules\CustomPosts\FAQ\FAQ_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Url_Seo_State {

	/** Robots directives SCOS manages. */
	const DIRECTIVES = [ 'noindex', 'nofollow', 'noimageindex', 'nosnippet' ];

	// =========================================================================
	// Contexts
	// =========================================================================

	/**
	 * Context for the current front-end request (what Head_Output works from).
	 *
	 * @return array
	 */
	public static function current_context(): array {
		global $wp_query;
		$q = ( $wp_query instanceof \WP_Query ) ? $wp_query : null;

		$slug = $q ? self::archive_slug( $q ) : null;
		if ( null !== $slug ) {
			return [
				'type'        => 'archive',
				'slug'        => $slug,
				'paged'       => max( 0, (int) $q->get( 'paged' ) ),
				'query'       => $q,
				'request_uri' => null,
			];
		}
		if ( $q && $q->is_singular() ) {
			$pid = (int) get_the_ID();
			if ( $pid ) {
				return [ 'type' => 'singular', 'post_id' => $pid, 'query' => $q ];
			}
		}
		return [ 'type' => 'none', 'query' => $q ];
	}

	/**
	 * Archive slug for a query, or null when it isn't an archive SCOS manages.
	 *
	 * 'post' (blog / posts index), a CPT name, 'author', 'date', 'search', '404',
	 * or a taxonomy slug. Order matters and matches what the front end has always used.
	 *
	 * @param \WP_Query $q
	 * @return string|null
	 */
	public static function archive_slug( \WP_Query $q ): ?string {
		if ( $q->is_home() ) {
			return 'post';
		}
		if ( $q->is_post_type_archive() ) {
			$obj = $q->get_queried_object();
			return ( $obj instanceof \WP_Post_Type ) ? $obj->name : null;
		}
		if ( $q->is_author() ) { return 'author'; }
		if ( $q->is_date() )   { return 'date'; }
		if ( $q->is_search() ) { return 'search'; }
		if ( $q->is_404() )    { return '404'; }
		if ( $q->is_category() || $q->is_tag() || $q->is_tax() ) {
			$obj = $q->get_queried_object();
			return ( $obj instanceof \WP_Term ) ? $obj->taxonomy : null;
		}
		return null;
	}

	/**
	 * Resolve a front-end URL of this site to a context, the way WordPress would
	 * route a logged-out visitor's request. Read-only: no request hooks fire.
	 *
	 * @param string $url Absolute URL on this site, or a path starting with "/".
	 * @return array|\WP_Error
	 */
	public static function context_for_url( string $url ) {
		$url = trim( $url );
		if ( '' === $url ) {
			return new \WP_Error( 'empty_url', 'Empty URL.' );
		}
		if ( '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
			$url = home_url( $url );
		}
		$parts = wp_parse_url( $url );
		$home  = wp_parse_url( home_url( '/' ) );
		if ( empty( $parts['host'] ) || strtolower( $parts['host'] ) !== strtolower( (string) ( $home['host'] ?? '' ) ) ) {
			return new \WP_Error( 'foreign_url', 'Not a URL on this site.' );
		}

		$path  = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$query = isset( $parts['query'] ) ? (string) $parts['query'] : '';
		if ( preg_match( '#/(wp-admin|wp-json)(/|$)|/wp-login\.php#', $path ) ) {
			return new \WP_Error( 'not_front_end', 'Admin, login and REST URLs have no SEO state.' );
		}

		$vars = self::query_vars_for_request( $path, $query );

		// Route as a logged-out visitor: no drafts or private posts.
		$user_id = get_current_user_id();
		wp_set_current_user( 0 );
		$q = new \WP_Query();
		$q->query( $vars );
		self::apply_404_rules( $q, $vars );
		wp_set_current_user( $user_id );

		$request_uri = $path . ( '' !== $query ? '?' . $query : '' );
		$slug        = self::archive_slug( $q );
		if ( null !== $slug ) {
			return [
				'type'        => 'archive',
				'slug'        => $slug,
				'paged'       => max( 0, (int) $q->get( 'paged' ) ),
				'query'       => $q,
				'request_uri' => $request_uri,
			];
		}
		if ( $q->is_singular() && $q->post instanceof \WP_Post ) {
			return [ 'type' => 'singular', 'post_id' => (int) $q->post->ID, 'query' => $q, 'request_uri' => $request_uri ];
		}
		return [ 'type' => 'none', 'query' => $q, 'request_uri' => $request_uri ];
	}

	/**
	 * Query vars for a path + query string: WP::parse_request()'s matching, without its hooks.
	 *
	 * @param string $path  URL path.
	 * @param string $query Raw query string.
	 * @return array
	 */
	private static function query_vars_for_request( string $path, string $query ): array {
		global $wp_rewrite, $wp;

		$get = [];
		if ( '' !== $query ) {
			wp_parse_str( $query, $get );
		}

		$perma = [];
		$error = '';
		$rules = $wp_rewrite instanceof \WP_Rewrite ? $wp_rewrite->wp_rewrite_rules() : [];
		if ( ! empty( $rules ) ) {
			$home_path = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
			$req       = trim( $path, '/' );
			if ( '' !== $home_path && 0 === strpos( $req . '/', $home_path . '/' ) ) {
				$req = trim( substr( $req, strlen( $home_path ) ), '/' );
			}
			if ( '' !== $req && false === strpos( $req, 'index.php' ) ) {
				$error   = '404';
				$matched = false;
				foreach ( $rules as $match => $rewrite ) {
					if ( ! preg_match( "#^$match#", $req, $matches ) && ! preg_match( "#^$match#", urldecode( $req ), $matches ) ) {
						continue;
					}
					if ( $wp_rewrite->use_verbose_page_rules && preg_match( '/pagename=\$matches\[([0-9]+)\]/', $rewrite, $varmatch ) ) {
						$page = get_page_by_path( $matches[ $varmatch[1] ] );
						if ( ! $page ) {
							continue;
						}
						$status = get_post_status_object( $page->post_status );
						if ( $status && ! $status->public && ! $status->protected && ! $status->private && $status->exclude_from_search ) {
							continue;
						}
					}
					$rewrite = preg_replace( '!^.+\?!', '', $rewrite );
					wp_parse_str( \WP_MatchesMapRegex::apply( $rewrite, $matches ), $perma );
					$matched = true;
					break;
				}
				if ( $matched ) {
					$error = '';
				}
			}
		}

		$public = ( $wp instanceof \WP ) ? (array) apply_filters( 'query_vars', $wp->public_query_vars ) : [ 'p', 'page_id', 'name', 'pagename', 's', 'cat', 'tag', 'author', 'paged', 'year', 'monthnum', 'day', 'post_type' ];

		$post_type_vars = [];
		foreach ( get_post_types( [], 'objects' ) as $type => $obj ) {
			if ( is_post_type_viewable( $obj ) && $obj->query_var ) {
				$post_type_vars[ $obj->query_var ] = $type;
			}
		}

		$vars = [];
		foreach ( $public as $var ) {
			if ( isset( $get[ $var ] ) ) {
				$vars[ $var ] = $get[ $var ];
			} elseif ( isset( $perma[ $var ] ) ) {
				$vars[ $var ] = $perma[ $var ];
			}
			if ( ! empty( $vars[ $var ] ) && ! is_array( $vars[ $var ] ) ) {
				$vars[ $var ] = (string) $vars[ $var ];
				if ( isset( $post_type_vars[ $var ] ) ) {
					$vars['post_type'] = $post_type_vars[ $var ];
					$vars['name']      = $vars[ $var ];
				}
			}
		}
		if ( '' !== $error ) {
			$vars['error'] = $error;
		}
		return $vars;
	}

	/**
	 * WP::handle_404()'s decisions, applied to a standalone query.
	 *
	 * @param \WP_Query $q
	 * @param array     $vars
	 */
	private static function apply_404_rules( \WP_Query $q, array $vars ): void {
		if ( isset( $vars['error'] ) && '404' === (string) $vars['error'] ) {
			$q->set_404();
			return;
		}
		if ( ! empty( $q->posts ) ) {
			return;
		}
		$obj = $q->get_queried_object();
		if ( ( $q->is_tag() || $q->is_category() || $q->is_tax() || $q->is_post_type_archive() ) && $obj && ! $q->is_paged() ) {
			return;
		}
		if ( $q->is_author() && $obj instanceof \WP_User && ! $q->is_paged() ) {
			return;
		}
		if ( $q->is_home() || $q->is_search() || $q->is_feed() ) {
			return;
		}
		$q->set_404();
	}

	// =========================================================================
	// Robots
	// =========================================================================

	/**
	 * The robots rule SCOS applies, or null when SCOS leaves WordPress's robots alone.
	 *
	 * Absolute: directives listed are set, the other managed ones removed
	 * (see apply_robots_array()). Does not cover "Discourage search engines",
	 * which the caller checks first.
	 *
	 * @param array $ctx Context.
	 * @return array{directives:string[], source:string}|null
	 */
	public static function robots_rule( array $ctx ): ?array {
		if ( 'archive' === $ctx['type'] ) {
			$settings   = Archive_Settings::get( $ctx['slug'] );
			$directives = is_array( $settings['robots'] ) ? $settings['robots'] : [];
			$source     = 'archive_setting';
			if ( $settings['pagination_noindex'] && $ctx['paged'] > 1 ) {
				$directives[] = 'noindex';
				$source       = 'archive_setting+paged';
			}
			return [ 'directives' => array_values( array_unique( $directives ) ), 'source' => $source ];
		}
		if ( 'singular' === $ctx['type'] ) {
			$effective = Archive_Settings::effective_single_robots( (int) $ctx['post_id'] );
			if ( empty( $effective['directives'] ) ) {
				return null;
			}
			return [
				'directives' => $effective['directives'],
				'source'     => 'post' === $effective['source'] ? 'post' : 'post_type_default',
			];
		}
		return null;
	}

	/**
	 * Apply a list of robots directives to a wp_robots array, absolutely.
	 *
	 * @param array    $robots     Existing wp_robots array.
	 * @param string[] $directives Subset of noindex, nofollow, noimageindex, nosnippet.
	 * @return array
	 */
	public static function apply_robots_array( array $robots, array $directives ): array {
		if ( in_array( 'noindex', $directives, true ) ) {
			$robots['noindex'] = true;
			unset( $robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview'] );
		} else {
			unset( $robots['noindex'] );
			$robots['max-image-preview'] = 'large';
			$robots['max-snippet']       = -1;
			$robots['max-video-preview'] = -1;
		}

		foreach ( [ 'nofollow', 'noimageindex', 'nosnippet' ] as $d ) {
			if ( in_array( $d, $directives, true ) ) {
				$robots[ $d ] = true;
			} else {
				unset( $robots[ $d ] );
			}
		}

		return $robots;
	}

	/**
	 * Effective robots for a context, as a visitor's page would carry them.
	 *
	 * @param array $ctx Context.
	 * @return array{directives:string[], index:bool, follow:bool, source:string}
	 */
	public static function robots( array $ctx ): array {
		if ( '0' === (string) get_option( 'blog_public' ) ) {
			return self::robots_result( [ 'noindex', 'nofollow' ], 'blog_public' );
		}

		$rule = self::robots_rule( $ctx );
		if ( null !== $rule ) {
			return self::robots_result( array_values( array_intersect( self::DIRECTIVES, $rule['directives'] ) ), $rule['source'] );
		}

		// SCOS leaves WordPress's robots as they are. The one SCOS addition here:
		// Image SEO's "noindex attachment pages".
		if ( 'singular' === $ctx['type'] && 'attachment' === get_post_type( (int) $ctx['post_id'] )
			&& ! empty( Image_SEO::get()['noindex_attachments'] ) ) {
			return self::robots_result( [ 'noindex', 'nofollow' ], 'attachment_setting' );
		}

		return self::robots_result( [], 'wordpress_default' );
	}

	private static function robots_result( array $directives, string $source ): array {
		return [
			'directives' => $directives,
			'index'      => ! in_array( 'noindex', $directives, true ),
			'follow'     => ! in_array( 'nofollow', $directives, true ),
			'source'     => $source,
		];
	}

	// =========================================================================
	// Canonical
	// =========================================================================

	/**
	 * Canonical URL a context prints.
	 *
	 * @param array $ctx Context.
	 * @return array{url:string, source:string} source: post, permalink, archive_base, archive_paged or none.
	 */
	public static function canonical( array $ctx ): array {
		if ( 'singular' === $ctx['type'] ) {
			$pid       = (int) $ctx['post_id'];
			$canonical = get_post_meta( $pid, 'scos_seo_canonical', true );
			if ( ! empty( $canonical ) ) {
				return [ 'url' => (string) $canonical, 'source' => 'post' ];
			}
			$permalink = (string) get_permalink( $pid );
			return [ 'url' => $permalink, 'source' => '' !== $permalink ? 'permalink' : 'none' ];
		}

		if ( 'archive' === $ctx['type'] ) {
			$base = self::archive_base_url( $ctx['slug'], $ctx['query'] );
			if ( '' === $base ) {
				return [ 'url' => '', 'source' => 'none' ];
			}
			$settings = Archive_Settings::get( $ctx['slug'] );
			if ( $settings['canonical_paged'] && $ctx['paged'] > 1 ) {
				return [ 'url' => self::paged_url( $ctx['paged'], $ctx['request_uri'] ?? null ), 'source' => 'archive_paged' ];
			}
			return [ 'url' => $base, 'source' => 'archive_base' ];
		}

		return [ 'url' => '', 'source' => 'none' ];
	}

	/**
	 * Canonical base URL for an archive (page 1). Empty for search and 404.
	 *
	 * @param string    $slug Archive slug.
	 * @param \WP_Query $q    The archive's query.
	 * @return string
	 */
	public static function archive_base_url( string $slug, \WP_Query $q ): string {
		switch ( $slug ) {
			case 'post':
				$blog_page_id = (int) get_option( 'page_for_posts' );
				return $blog_page_id
					? (string) get_permalink( $blog_page_id )
					: home_url( '/' );

			case 'author':
				return (string) get_author_posts_url( (int) $q->get_queried_object_id() );

			case 'date':
				if ( $q->is_year() ) {
					return (string) get_year_link( (int) $q->get( 'year' ) );
				}
				if ( $q->is_month() ) {
					return (string) get_month_link( (int) $q->get( 'year' ), (int) $q->get( 'monthnum' ) );
				}
				if ( $q->is_day() ) {
					return (string) get_day_link( (int) $q->get( 'year' ), (int) $q->get( 'monthnum' ), (int) $q->get( 'day' ) );
				}
				return '';

			case 'search':
			case '404':
				// No canonical: search is query-specific; a 404 has no URL to canonicalise to.
				return '';

			default:
				if ( taxonomy_exists( $slug ) ) {
					$term = $q->get_queried_object();
					if ( $term instanceof \WP_Term ) {
						$link = get_term_link( $term );
						return is_wp_error( $link ) ? '' : (string) $link;
					}
					return '';
				}
				return (string) ( get_post_type_archive_link( $slug ) ?: '' );
		}
	}

	/**
	 * URL of page N of an archive, without the visitor's query string.
	 *
	 * get_pagenum_link() builds on the request URI, so it carries any utm_* tags
	 * or filters into canonical and prev/next links. With pretty permalinks the
	 * page number is part of the path and the query string is dropped. With
	 * plain permalinks the archive and page are themselves query args, so the
	 * URL is left as WordPress builds it.
	 *
	 * @param int         $page        Page number. 1 returns the archive's first page.
	 * @param string|null $request_uri Path + query to build from; null = the current request.
	 * @return string
	 */
	public static function paged_url( int $page, ?string $request_uri = null ): string {
		if ( null !== $request_uri ) {
			$saved                  = $_SERVER['REQUEST_URI'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- restored below
			$_SERVER['REQUEST_URI'] = $request_uri;
		}
		$url = (string) get_pagenum_link( $page, false );
		if ( null !== $request_uri ) {
			if ( null === $saved ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $saved;
			}
		}

		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return $url;
		}

		return (string) preg_replace( '/[?#].*$/', '', $url );
	}

	// =========================================================================
	// Sitemaps — per post (shared with Seo_Module)
	// =========================================================================

	/**
	 * Whether the sitemaps treat a post as noindex.
	 *
	 * Its own scos_seo_robots, else its post type's default. Also honours legacy
	 * Yoast, Rank Math and `_robots_noindex` meta, which the page itself never prints.
	 * TODO: delete the three legacy checks (0 rows on every live site, 2026-11-26).
	 *
	 * Call update_meta_cache() for a batch of posts first.
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public static function post_is_noindex( int $post_id ): bool {
		$scos_robots = get_post_meta( $post_id, 'scos_seo_robots', true );
		if ( is_array( $scos_robots ) && in_array( 'noindex', $scos_robots, true ) ) {
			return true;
		}

		// No robots of its own: the post type's single default applies (#228).
		if ( ( ! is_array( $scos_robots ) || empty( $scos_robots ) )
			&& in_array( 'noindex', Archive_Settings::single_robots( (string) get_post_type( $post_id ) ), true ) ) {
			return true;
		}

		$yoast_noindex = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
		if ( '1' === $yoast_noindex || 'noindex' === $yoast_noindex ) {
			return true;
		}

		$rankmath_robots = get_post_meta( $post_id, 'rank_math_robots', true );
		if ( is_array( $rankmath_robots ) && in_array( 'noindex', $rankmath_robots, true ) ) {
			return true;
		}

		$custom_noindex = get_post_meta( $post_id, '_robots_noindex', true );
		if ( '1' === $custom_noindex || 'yes' === $custom_noindex ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether a post's canonical points somewhere else, with no sitemap override.
	 * Trailing slashes are ignored.
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public static function post_is_non_self_canonical( int $post_id ): bool {
		if ( '1' === (string) get_post_meta( $post_id, 'scos_seo_sitemap_noindex_override', true ) ) {
			return false;
		}
		$canonical = get_post_meta( $post_id, 'scos_seo_canonical', true );
		if ( empty( $canonical ) ) {
			return false;
		}
		return rtrim( (string) $canonical, '/' ) !== rtrim( (string) get_permalink( $post_id ), '/' );
	}

	/**
	 * XML sitemap decision for one post, from its own meta (the post-type and
	 * exclude_ids checks are the sitemap query's job; see in_xml_sitemap()).
	 *
	 * @param int $post_id
	 * @return array{included:bool, reason:string} reason: excluded_by_post, override, noindex, non_self_canonical or included.
	 */
	public static function xml_post_decision( int $post_id ): array {
		$exclude = (array) get_post_meta( $post_id, 'scos_seo_sitemap_exclude', true );
		if ( in_array( 'xml', $exclude, true ) ) {
			return [ 'included' => false, 'reason' => 'excluded_by_post' ];
		}
		if ( '1' === (string) get_post_meta( $post_id, 'scos_seo_sitemap_noindex_override', true ) ) {
			return [ 'included' => true, 'reason' => 'override' ];
		}
		if ( self::post_is_noindex( $post_id ) ) {
			return [ 'included' => false, 'reason' => 'noindex' ];
		}
		if ( self::post_is_non_self_canonical( $post_id ) ) {
			return [ 'included' => false, 'reason' => 'non_self_canonical' ];
		}
		return [ 'included' => true, 'reason' => 'included' ];
	}

	/**
	 * HTML sitemap decision for one post, from its own meta. Unlike the XML sitemap
	 * it ignores the canonical and the override.
	 *
	 * @param int $post_id
	 * @return array{included:bool, reason:string} reason: noindex, excluded_by_post or included.
	 */
	public static function html_post_decision( int $post_id ): array {
		if ( self::post_is_noindex( $post_id ) ) {
			return [ 'included' => false, 'reason' => 'noindex' ];
		}
		$exclude = (array) get_post_meta( $post_id, 'scos_seo_sitemap_exclude', true );
		if ( in_array( 'html', $exclude, true ) ) {
			return [ 'included' => false, 'reason' => 'excluded_by_post' ];
		}
		return [ 'included' => true, 'reason' => 'included' ];
	}

	/**
	 * Page ID when an archive URL is actually a Page (the WooCommerce shop), else 0.
	 *
	 * @param string $archive_url
	 * @return int
	 */
	public static function archive_url_page_id( string $archive_url ): int {
		$page_id = (int) url_to_postid( $archive_url );
		return ( $page_id > 0 && 'page' === get_post_type( $page_id ) ) ? $page_id : 0;
	}

	/**
	 * Author IDs the author sitemap lists: authors with a published post, unless
	 * the author archive is disabled or noindex.
	 *
	 * @param int[] $include Limit to these user IDs (empty = all).
	 * @return int[]
	 */
	public static function sitemap_author_ids( array $include = [] ): array {
		$args = [
			'who'                 => 'authors',
			'has_published_posts' => true,
			'fields'              => 'ID',
		];
		if ( ! empty( $include ) ) {
			$args['include'] = array_map( 'intval', $include );
		}
		$users = get_users( $args );
		if ( empty( $users ) ) {
			return [];
		}

		$author = Archive_Settings::get( 'author' );
		if ( ! empty( $author['disabled'] ) ) {
			return [];
		}
		if ( in_array( 'noindex', (array) ( $author['robots'] ?? [] ), true ) ) {
			return [];
		}

		return array_map( 'intval', $users );
	}

	// =========================================================================
	// Sitemaps — per context
	// =========================================================================

	/**
	 * Whether a context's URL is in the XML sitemap, and why.
	 *
	 * @param array $ctx      Context.
	 * @param array $settings Sitemap settings (Seo_Module::sitemap_settings()).
	 * @return array{included:bool, reason:string, listed_as?:int}
	 */
	public static function in_xml_sitemap( array $ctx, array $settings ): array {
		$types = (array) ( $settings['post_types'] ?? [] );

		if ( 'singular' === $ctx['type'] ) {
			return self::xml_for_post( (int) $ctx['post_id'], $settings );
		}

		if ( 'archive' !== $ctx['type'] ) {
			return [ 'included' => false, 'reason' => 'not_listed_type' ];
		}

		$slug = $ctx['slug'];
		if ( $ctx['paged'] > 1 ) {
			return [ 'included' => false, 'reason' => 'paged_not_listed' ];
		}

		if ( 'post' === $slug ) {
			$page_id = (int) get_option( 'page_for_posts' );
			if ( ! $page_id ) {
				return [ 'included' => false, 'reason' => 'not_listed_type' ];
			}
			return self::xml_for_post( $page_id, $settings ) + [ 'listed_as' => $page_id ];
		}

		if ( 'author' === $slug ) {
			if ( empty( $settings['include_author_archive'] ) ) {
				return [ 'included' => false, 'reason' => 'author_sitemap_off' ];
			}
			$author_id = (int) $ctx['query']->get_queried_object_id();
			return in_array( $author_id, self::sitemap_author_ids( [ $author_id ] ), true )
				? [ 'included' => true, 'reason' => 'included' ]
				: [ 'included' => false, 'reason' => 'author_not_listed' ];
		}

		if ( in_array( $slug, [ 'date', 'search', '404' ], true ) ) {
			return [ 'included' => false, 'reason' => 'not_listed_type' ];
		}

		if ( taxonomy_exists( $slug ) ) {
			if ( ! in_array( $slug, (array) ( $settings['taxonomies'] ?? [] ), true ) ) {
				return [ 'included' => false, 'reason' => 'taxonomy_not_in_sitemap' ];
			}
			$term = $ctx['query']->get_queried_object();
			if ( ! ( $term instanceof \WP_Term ) || (int) $term->count < 1 ) {
				return [ 'included' => false, 'reason' => 'empty_term' ];
			}
			return [ 'included' => true, 'reason' => 'included' ];
		}

		// CPT archive.
		if ( ! in_array( $slug, $types, true ) ) {
			return [ 'included' => false, 'reason' => 'type_not_in_sitemap' ];
		}
		$obj = get_post_type_object( $slug );
		if ( ! $obj || ! $obj->has_archive || in_array( $slug, [ 'post', 'page' ], true ) ) {
			return [ 'included' => false, 'reason' => 'no_archive' ];
		}
		$archive_url = (string) get_post_type_archive_link( $slug );
		$page_id     = self::archive_url_page_id( $archive_url );
		if ( $page_id ) {
			return self::xml_for_post( $page_id, $settings ) + [ 'listed_as' => $page_id ];
		}
		return [ 'included' => true, 'reason' => 'included' ];
	}

	/**
	 * XML sitemap decision for a post, including the sitemap query's own filters.
	 */
	private static function xml_for_post( int $post_id, array $settings ): array {
		$base = self::sitemap_query_filter( $post_id, $settings );
		return null !== $base ? $base : self::xml_post_decision( $post_id );
	}

	/**
	 * The checks the sitemap queries make before per-post meta: status, post type, exclude_ids.
	 *
	 * @return array{included:bool, reason:string}|null Null when the post passes them.
	 */
	private static function sitemap_query_filter( int $post_id, array $settings ): ?array {
		$type = (string) get_post_type( $post_id );
		if ( 'attachment' === $type ) {
			return [ 'included' => false, 'reason' => 'attachment_not_listed' ];
		}
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return [ 'included' => false, 'reason' => 'not_published' ];
		}
		if ( ! in_array( $type, (array) ( $settings['post_types'] ?? [] ), true ) ) {
			return [ 'included' => false, 'reason' => 'type_not_in_sitemap' ];
		}
		if ( in_array( $post_id, array_map( 'intval', (array) ( $settings['exclude_ids'] ?? [] ) ), true ) ) {
			return [ 'included' => false, 'reason' => 'excluded_id' ];
		}
		return null;
	}

	/**
	 * Whether a context's URL is in the HTML sitemap ([site_essentials_sitemap]), and why.
	 *
	 * @param array $ctx      Context.
	 * @param array $settings Sitemap settings.
	 * @return array{included:bool, reason:string, listed_as?:int}
	 */
	public static function in_html_sitemap( array $ctx, array $settings ): array {
		if ( empty( $settings['html_sitemap_enabled'] ) ) {
			return [ 'included' => false, 'reason' => 'html_sitemap_off' ];
		}

		$post_id = 0;
		if ( 'singular' === $ctx['type'] ) {
			$post_id = (int) $ctx['post_id'];
		} elseif ( 'archive' === $ctx['type'] && 'post' === $ctx['slug'] && $ctx['paged'] < 2 && get_option( 'page_for_posts' ) ) {
			$post_id = (int) get_option( 'page_for_posts' );
		}

		if ( $post_id ) {
			$base = self::sitemap_query_filter( $post_id, $settings );
			$res  = null !== $base ? $base : self::html_post_decision( $post_id );
			return 'singular' === $ctx['type'] ? $res : $res + [ 'listed_as' => $post_id ];
		}

		// CPT archives get an arrow link beside their heading.
		if ( 'archive' === $ctx['type'] && $ctx['paged'] < 2 && post_type_exists( $ctx['slug'] )
			&& ! in_array( $ctx['slug'], [ 'post', 'page' ], true ) ) {
			$obj = get_post_type_object( $ctx['slug'] );
			if ( in_array( $ctx['slug'], (array) ( $settings['post_types'] ?? [] ), true ) && $obj && $obj->has_archive ) {
				return [ 'included' => true, 'reason' => 'archive_link' ];
			}
			return [ 'included' => false, 'reason' => 'type_not_in_sitemap' ];
		}

		return [ 'included' => false, 'reason' => 'not_listed_type' ];
	}

	// =========================================================================
	// Redirects
	// =========================================================================

	/**
	 * The redirect (or 410) a context's URL gets before any page renders, in
	 * template_redirect order. WordPress's own canonical redirects aren't covered.
	 *
	 * @param array  $ctx  Context.
	 * @param string $path The URL's path.
	 * @return array{to:string, status:int, reason:string}|null
	 */
	public static function redirects( array $ctx, string $path ): ?array {
		// Priority 1: SEO › Redirections.
		$norm = rtrim( $path, '/' );
		if ( in_array( $norm, Redirections::get_410_paths(), true ) ) {
			return [ 'to' => '', 'status' => 410, 'reason' => 'redirect_rule_410' ];
		}
		$target = Redirections::match_301( $norm );
		if ( null !== $target ) {
			return [ 'to' => $target, 'status' => 301, 'reason' => 'redirect_rule_301' ];
		}

		// Priority 2: post types without single URLs.
		if ( 'singular' === $ctx['type'] && class_exists( Single_Url_Guard::class ) ) {
			$type = (string) get_post_type( (int) $ctx['post_id'] );
			if ( Single_Url_Guard::is_blocked( $type ) ) {
				return [ 'to' => Single_Url_Guard::target( $type, (int) $ctx['post_id'] ), 'status' => 301, 'reason' => 'single_url_guard' ];
			}
		}

		// Priority 3: attachment pages → the file.
		if ( 'singular' === $ctx['type'] && 'attachment' === get_post_type( (int) $ctx['post_id'] )
			&& ! empty( Image_SEO::get()['redirect_attachments'] ) ) {
			$url = wp_get_attachment_url( (int) $ctx['post_id'] );
			return [ 'to' => $url ?: home_url( '/' ), 'status' => 301, 'reason' => 'attachment_redirect' ];
		}

		// Priority 5: disabled author, date and search archives.
		if ( 'archive' === $ctx['type'] && in_array( $ctx['slug'], [ 'author', 'date', 'search' ], true ) ) {
			$s = Archive_Settings::get( $ctx['slug'] );
			if ( ! empty( $s['disabled'] ) ) {
				return [ 'to' => ! empty( $s['redirect_url'] ) ? (string) $s['redirect_url'] : home_url( '/' ), 'status' => 301, 'reason' => 'archive_disabled' ];
			}
		}

		// Priority 10: FAQ archive and topic folders.
		if ( class_exists( FAQ_Module::class ) && $ctx['query'] instanceof \WP_Query ) {
			$archive_enabled  = (bool) get_option( 'scos_faq_archive_enabled', false );
			$archive_redirect = (string) get_option( FAQ_Module::OPTION_ARCHIVE_REDIRECT, '' );
			if ( ! $archive_enabled && $ctx['query']->is_404() && '/faq' === $norm && '' !== $archive_redirect ) {
				return [ 'to' => $archive_redirect, 'status' => 301, 'reason' => 'faq_redirect' ];
			}
			if ( '' !== (string) $ctx['query']->get( 'scos_faq_topic_browse' ) ) {
				$topic = (string) get_option( 'scos_faq_topic_redirect', '' );
				$to    = '' !== $topic ? $topic : ( $archive_enabled ? home_url( '/faq/' ) : $archive_redirect );
				if ( '' !== $to ) {
					return [ 'to' => $to, 'status' => 301, 'reason' => 'faq_redirect' ];
				}
			}
		}

		return null;
	}

	// =========================================================================
	// Full report
	// =========================================================================

	/**
	 * Everything SEO-relevant a URL serves, with sources and disagreements.
	 *
	 * @param array  $ctx      Context.
	 * @param string $url      The URL as asked about.
	 * @param array  $settings Sitemap settings.
	 * @return array
	 */
	public static function report( array $ctx, string $url, array $settings ): array {
		$path      = (string) wp_parse_url( $url, PHP_URL_PATH );
		$robots    = self::robots( $ctx );
		$canonical = self::canonical( $ctx );
		$xml       = self::in_xml_sitemap( $ctx, $settings );
		$html      = self::in_html_sitemap( $ctx, $settings );
		$redirect  = self::redirects( $ctx, '' !== $path ? $path : '/' );

		$target = [ 'type' => $ctx['type'] ];
		if ( 'singular' === $ctx['type'] ) {
			$target['post_id']   = (int) $ctx['post_id'];
			$target['post_type'] = (string) get_post_type( (int) $ctx['post_id'] );
		} elseif ( 'archive' === $ctx['type'] ) {
			$target['slug']  = $ctx['slug'];
			$target['paged'] = (int) $ctx['paged'];
			$obj             = $ctx['query']->get_queried_object();
			if ( $obj instanceof \WP_Term ) {
				$target['term_id'] = (int) $obj->term_id;
			} elseif ( $obj instanceof \WP_User ) {
				$target['author_id'] = (int) $obj->ID;
			}
		}

		return [
			'input'          => $url,
			'target'         => $target,
			'robots'         => $robots,
			'canonical'      => $canonical,
			'in_xml_sitemap' => $xml,
			'in_html_sitemap'=> $html,
			'redirects'      => $redirect,
			'warnings'       => self::warnings( $ctx, $robots, $canonical, $xml, $html, $redirect ),
		];
	}

	/**
	 * Where the front end and the sitemaps disagree for this URL.
	 *
	 * @return string[]
	 */
	private static function warnings( array $ctx, array $robots, array $canonical, array $xml, array $html, ?array $redirect ): array {
		$w = [];

		if ( 'blog_public' === $robots['source'] && $xml['included'] ) {
			$w[] = 'Search engines are discouraged (Settings › Reading), but this URL is still listed in the XML sitemap.';
		} elseif ( ! $robots['index'] && $xml['included'] && 'override' !== $xml['reason'] ) {
			$w[] = 'Served as noindex, but listed in the XML sitemap.';
		}
		if ( ! $robots['index'] && $html['included'] && 'blog_public' !== $robots['source'] ) {
			$w[] = 'Served as noindex, but listed in the HTML sitemap.';
		}

		if ( null !== $redirect ) {
			$label = 410 === $redirect['status'] ? 'returns 410 Gone' : 'redirects (' . $redirect['reason'] . ')';
			if ( $xml['included'] ) {
				$w[] = 'This URL ' . $label . ', but is listed in the XML sitemap.';
			}
			if ( $html['included'] ) {
				$w[] = 'This URL ' . $label . ', but is listed in the HTML sitemap.';
			}
		}

		if ( 'singular' === $ctx['type'] && 'post' === $canonical['source']
			&& rtrim( $canonical['url'], '/' ) !== rtrim( (string) get_permalink( (int) $ctx['post_id'] ), '/' ) && $html['included'] ) {
			$w[] = 'Canonical points to another URL, but this URL is listed in the HTML sitemap.';
		}

		if ( 'override' === $xml['reason'] && $xml['included'] && ! $html['included'] && 'noindex' === $html['reason'] ) {
			$w[] = 'In the XML sitemap by the "include anyway" override, but the HTML sitemap leaves it out (it ignores the override).';
		}

		// The posts page and the WooCommerce shop: an archive on the front end, a Page in the sitemap.
		if ( 'archive' === $ctx['type'] && isset( $xml['listed_as'] ) ) {
			$w[] = sprintf(
				'Served with the "%s" archive settings: Page %d\'s own SEO meta (title, description, robots, canonical) is not used, but the sitemap lists that Page using its own meta.',
				$ctx['slug'],
				(int) $xml['listed_as']
			);
		}

		if ( 'archive' === $ctx['type'] && ( $xml['included'] || $html['included'] ) ) {
			$exclude = Archive_Settings::get( $ctx['slug'] )['sitemap_exclude'] ?? [];
			if ( ! empty( array_filter( (array) $exclude ) ) ) {
				$w[] = 'The archive settings hide this archive from sitemaps, but that setting is not applied yet: it is still listed.';
			}
		}

		return $w;
	}

	// =========================================================================
	// Meta box: Advanced-tab values set on a post (#113)
	// =========================================================================

	/**
	 * Advanced-tab fields that hold a real value on this post.
	 *
	 * The keys are always written on save, so a key merely existing means
	 * nothing: '0', [] and '' are "not set". Any real value replaces (or stops
	 * following) the default, even one that equals today's default.
	 *
	 * @param int $post_id
	 * @return string[] Meta keys with a value.
	 */
	public static function post_overrides( int $post_id ): array {
		$set = [];

		if ( '' !== trim( (string) get_post_meta( $post_id, 'scos_seo_canonical', true ) ) ) {
			$set[] = 'scos_seo_canonical';
		}
		if ( ! empty( array_filter( (array) get_post_meta( $post_id, 'scos_seo_robots', true ) ) ) ) {
			$set[] = 'scos_seo_robots';
		}

		$freeze = (string) get_post_meta( $post_id, 'scos_seo_freeze_og_date', true );
		$global = (bool) get_option( 'scos_seo_freeze_modified_date', false );
		if ( ( $global && '0' === $freeze ) || ( ! $global && '1' === $freeze ) ) {
			$set[] = 'scos_seo_freeze_og_date';
		}

		if ( ! empty( array_filter( (array) get_post_meta( $post_id, 'scos_seo_sitemap_exclude', true ) ) ) ) {
			$set[] = 'scos_seo_sitemap_exclude';
		}
		if ( '1' === (string) get_post_meta( $post_id, 'scos_seo_sitemap_noindex_override', true ) ) {
			$set[] = 'scos_seo_sitemap_noindex_override';
		}

		return $set;
	}
}
