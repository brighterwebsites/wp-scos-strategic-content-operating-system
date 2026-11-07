<?php
/**
 * Single URL Guard
 *
 * Some SCOS post types are data sources with no pages of their own: Reviews always,
 * and FAQs when "FAQ single pages" is switched off. Their posts must still work in
 * loops, blocks and WP_Query, so the post types stay `public`. Only requests for a
 * single post are stopped here, and sent somewhere useful with a 301.
 *
 * Caught, for each post type without singles:
 *   - any request that resolves to one of its posts (pretty URL, ?{query_var}=, ?p=)
 *   - ?{query_var}=anything, even when it resolves to nothing
 *   - any 404 under the post type's rewrite base, e.g. /faq/old-topic/old-slug/
 *     (but not the bare base, /faq/, which belongs to the archive settings)
 *
 * Runs on template_redirect at priority 2, after Redirections (1), so a hand-written
 * 301 rule for a specific URL still wins over this blanket redirect.
 *
 * The list of post types is filterable: `scos_post_types_without_singles`.
 * The destination is filterable: `scos_single_url_redirect_target`.
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 *
 * v1.0 | 2026-11-07
 */

namespace SiteEssentials\Modules\CustomPosts;

use SiteEssentials\Modules\CustomPosts\FAQ\FAQ_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Single_Url_Guard {

	/** Option: where review single URLs go. Empty = home page. */
	const OPTION_REVIEWS_REDIRECT = 'scos_reviews_single_redirect';

	/** @var string[]|null Per-request cache of post types without singles. */
	private static $types = null;

	public static function init(): void {
		// Priority 2: after Redirections::handle_redirect (1), before Image_SEO (3) and Archive_Settings (5).
		add_action( 'template_redirect', [ self::class, 'maybe_redirect' ], 2 );
		add_filter( 'wp_sitemaps_post_types', [ self::class, 'remove_from_core_sitemap' ] );

		if ( is_admin() ) {
			add_action( 'admin_notices', [ self::class, 'notice_missing_faq_target' ] );
		}
	}

	// =========================================================================
	// Which post types have no singles
	// =========================================================================

	/**
	 * Post types whose single URLs are switched off on this site.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		if ( null !== self::$types ) {
			return self::$types;
		}

		$types = [];
		if ( post_type_exists( Cpt_Module::POST_TYPE_REVIEWS ) ) {
			$types[] = Cpt_Module::POST_TYPE_REVIEWS;
		}
		if ( class_exists( FAQ_Module::class ) && post_type_exists( FAQ_Module::POST_TYPE ) && ! FAQ_Module::singles_enabled() ) {
			$types[] = FAQ_Module::POST_TYPE;
		}

		/**
		 * Filter the post types that have no single URLs.
		 *
		 * @param string[] $types Post type slugs.
		 */
		$types = (array) apply_filters( 'scos_post_types_without_singles', $types );

		// Don't cache until every post type is registered (FAQs at init 16, reviews at 20).
		$clean = array_values( array_unique( array_filter( $types, 'is_string' ) ) );
		if ( did_action( 'wp_loaded' ) ) {
			self::$types = $clean;
		}
		return $clean;
	}

	/**
	 * Whether a post type has its single URLs switched off.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	public static function is_blocked( string $post_type ): bool {
		return '' !== $post_type && in_array( $post_type, self::post_types(), true );
	}

	// =========================================================================
	// Front-end redirect
	// =========================================================================

	/**
	 * 301 any single-post request for a post type without singles.
	 *
	 * @return void
	 */
	public static function maybe_redirect(): void {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$match = self::match_request();
		if ( null === $match ) {
			return;
		}
		[ $post_type, $post_id ] = $match;

		// Editors can still preview a draft.
		if ( $post_id && is_preview() && current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$target = self::target( $post_type, $post_id );

		wp_redirect( esc_url_raw( $target ), 301, 'SCOS' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- admin-set or local URL
		exit;
	}

	/**
	 * Work out whether this request is for a single post of a blocked type.
	 *
	 * @return array{0:string,1:int}|null [ post type, post ID (0 when unknown) ], or null.
	 */
	private static function match_request(): ?array {
		$blocked = self::post_types();
		if ( empty( $blocked ) ) {
			return null;
		}

		if ( is_singular( $blocked ) ) {
			$post_id = (int) get_queried_object_id();
			return [ (string) get_post_type( $post_id ), $post_id ];
		}

		$path = self::request_path();

		foreach ( $blocked as $post_type ) {
			$obj = get_post_type_object( $post_type );
			if ( ! $obj ) {
				continue;
			}

			// ?faq=… / ?bw_reviews=… — read raw, because a type that isn't publicly
			// queryable never turns the query var into a query.
			$query_var = ( is_string( $obj->query_var ) && '' !== $obj->query_var ) ? $obj->query_var : $post_type;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing check
			if ( isset( $_GET[ $query_var ] ) && '' !== $_GET[ $query_var ] ) {
				return [ $post_type, 0 ];
			}

			// A 404 below the rewrite base: an old or mistyped single URL.
			$base = self::rewrite_base( $obj );
			if ( '' !== $base && is_404() && 0 === strpos( $path, '/' . $base . '/' ) && $path !== '/' . $base . '/' ) {
				return [ $post_type, 0 ];
			}
		}

		return null;
	}

	/**
	 * Where a blocked single URL should go.
	 *
	 * @param string $post_type Post type slug.
	 * @param int    $post_id   Post ID, or 0 when the request didn't resolve to a post.
	 * @return string Absolute URL.
	 */
	public static function target( string $post_type, int $post_id ): string {
		if ( class_exists( FAQ_Module::class ) && FAQ_Module::POST_TYPE === $post_type ) {
			$url = FAQ_Module::single_redirect_target( $post_id );
		} elseif ( Cpt_Module::POST_TYPE_REVIEWS === $post_type ) {
			$url = (string) get_option( self::OPTION_REVIEWS_REDIRECT, '' );
		} else {
			$url = '';
		}

		/**
		 * Filter where a single URL of a post type without singles redirects to.
		 *
		 * @param string $url       Destination. Empty = home page.
		 * @param string $post_type Post type slug.
		 * @param int    $post_id   Post ID, or 0 when unknown.
		 */
		$url = (string) apply_filters( 'scos_single_url_redirect_target', $url, $post_type, $post_id );

		return '' !== $url ? $url : home_url( '/' );
	}

	/**
	 * The fixed leading part of a post type's rewrite slug: 'faq/%scos_topic%' → 'faq'.
	 *
	 * @param \WP_Post_Type $obj Post type object.
	 * @return string Empty when the type has no rewrite slug.
	 */
	private static function rewrite_base( \WP_Post_Type $obj ): string {
		if ( ! is_array( $obj->rewrite ) || empty( $obj->rewrite['slug'] ) ) {
			return '';
		}
		$slug = (string) strtok( (string) $obj->rewrite['slug'], '%' );
		return trim( $slug, '/' );
	}

	/**
	 * The request path relative to the home URL, with leading and trailing slashes.
	 *
	 * @return string e.g. '/faq/pricing/how-much/'
	 */
	public static function request_path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- path only, compared not output
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = '/' . trim( $home_path, '/' );
		if ( '/' !== $home_path && 0 === strpos( $path, $home_path ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}

		return '/' . ltrim( trailingslashit( $path ), '/' );
	}

	// =========================================================================
	// Sitemaps
	// =========================================================================

	/**
	 * Keep post types without singles out of the WordPress core sitemap.
	 *
	 * @param array $post_types Post type objects keyed by name.
	 * @return array
	 */
	public static function remove_from_core_sitemap( $post_types ) {
		foreach ( self::post_types() as $post_type ) {
			unset( $post_types[ $post_type ] );
		}
		return $post_types;
	}

	// =========================================================================
	// Admin flag
	// =========================================================================

	/**
	 * Flag FAQ singles switched off with nowhere to send them.
	 *
	 * The FAQ settings form refuses that combination, but the options can also be set
	 * by WP-CLI, MCP or a direct DB write. When it happens, FAQ URLs go to the home
	 * page; this notice says so until a redirect is set.
	 *
	 * @return void
	 */
	public static function notice_missing_faq_target(): void {
		if ( ! class_exists( FAQ_Module::class ) || ! current_user_can( 'manage_options' ) || ! self::is_blocked( FAQ_Module::POST_TYPE ) ) {
			return;
		}
		if ( '' !== FAQ_Module::configured_single_redirect() ) {
			return;
		}

		$url = add_query_arg(
			[
				'page' => \SiteEssentials\Core\Admin_UI::CPT_PAGE_SLUG,
				'tab'  => 'faq',
			],
			admin_url( 'admin.php' )
		);
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'FAQ single pages are off, but no redirect URL is set.', 'site-essentials' ); ?></strong>
				<?php esc_html_e( 'Old FAQ URLs are being sent to the home page. Set "Redirect /faq/ to" (scos_faq_archive_redirect).', 'site-essentials' ); ?>
				<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'FAQ settings', 'site-essentials' ); ?></a>
			</p>
		</div>
		<?php
	}
}
