<?php
/**
 * SEO Meta — Archive Settings
 *
 * Stores, retrieves, and processes SEO settings for:
 *  - Posts (blog) archive
 *  - All public non-built-in CPTs
 *  - Author archives
 *  - Date archives
 *  - Search results
 *  - 404 Not Found
 *
 * Option key pattern: scos_seo_archive_{slug}
 *
 * Common fields (all archive types):
 *  title             – template string with tokens
 *  description       – template string with tokens
 *  breadcrumb_title  – plain string
 *  robots            – array subset of: noindex, nofollow, noimageindex, nosnippet
 *
 * CPT / Posts archive only:
 *  tldr              – plain string
 *  sitemap_exclude   – array subset of: xml, html, news
 *  og_image_id       – attachment ID (int)
 *  pagination_noindex – bool — noindex on /page/2+
 *  canonical_paged    – bool — self-canonical on paginated pages
 *  rel_prevnext       – bool — output rel="prev" / rel="next"
 *
 * Special archive extras:
 *  disabled      – bool — redirect to redirect_url (author / date / search)
 *  redirect_url  – string — destination when disabled (empty = home)
 *  author_slug   – string — custom prefix for /author/ URLs (author only)
 *
 * Token set:
 *  %title%    Archive name, author name, search query, or "Page Not Found"
 *  %sitename% get_bloginfo('name')
 *  %sep%      Separator, filterable via scos_seo_title_sep (default –)
 *  %page%     "Page N" on paginated views, blank on page 1
 *  %search%   Current search query (search context only)
 *  %author%   Author display name (author context only)
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 * @since      1.1.0
 */

namespace SiteEssentials\Modules\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Archive_Settings {

	const OPTION_PREFIX  = 'scos_seo_archive_';
	const SPECIAL_SLUGS  = [ 'author', 'date', 'search', '404' ];

	// ── Defaults ─────────────────────────────────────────────────────────────

	/** Base defaults shared by all archive types. */
	private static function defaults(): array {
		return [
			'title'              => '',
			'description'        => '',
			'breadcrumb_title'   => '',
			'tldr'               => '',
			'robots'             => [],
			'sitemap_exclude'    => [],
			'og_image_id'        => 0,
			'pagination_noindex' => false,
			'canonical_paged'    => false,
			'rel_prevnext'       => false,
		];
	}

	/**
	 * Extra defaults for special archive types (author, date, search, 404).
	 * Merged on top of the base defaults when the slug is a special type.
	 */
	private static function special_defaults( string $slug ): array {
		switch ( $slug ) {
			case 'author':
				return [
					'disabled'    => false,
					'redirect_url'=> '',
					'author_slug' => '', // empty = keep WP default 'author'
				];
			case 'date':
			case 'search':
				return [
					'disabled'    => false,
					'redirect_url'=> '',
				];
			case '404':
				return []; // no extra fields; base defaults + robots are enough
		}
		return [];
	}

	// ── Archive discovery ─────────────────────────────────────────────────────

	/**
	 * Returns slug → label pairs for all configurable archives.
	 *
	 * Includes the Posts (blog) archive and every public, non-built-in CPT.
	 * Whether or not a CPT currently has has_archive enabled, storing settings
	 * now means they are ready when archives are turned on.
	 *
	 * Order: Posts (blog) first, then CPTs sorted alphabetically by plural label.
	 *
	 * @return array<string, string>
	 */
	public static function get_archives(): array {
		$archives = [
			'post' => __( 'Posts (Blog)', 'site-essentials' ),
		];

		$post_types = get_post_types(
			[
				'public'   => true,
				'_builtin' => false,
			],
			'objects'
		);

		$cpt_archives = [];
		foreach ( $post_types as $pt ) {
			$cpt_archives[ $pt->name ] = $pt->labels->name;
		}

		asort( $cpt_archives );

		return $archives + $cpt_archives;
	}

	/**
	 * Returns the four special archive types with their display labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_special_archives(): array {
		return [
			'author' => __( 'Author Archives', 'site-essentials' ),
			'date'   => __( 'Date Archives', 'site-essentials' ),
			'search' => __( 'Search Results', 'site-essentials' ),
			'404'    => __( '404 Not Found', 'site-essentials' ),
		];
	}

	// ── CRUD helpers ──────────────────────────────────────────────────────────

	/**
	 * Retrieve settings for one archive, merged with defaults.
	 *
	 * @param  string $slug Archive slug ('post', CPT name, or special slug).
	 * @return array
	 */
	public static function get( string $slug ): array {
		$saved = get_option( self::OPTION_PREFIX . sanitize_key( $slug ), [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		$base = in_array( $slug, self::SPECIAL_SLUGS, true )
			? array_merge( self::defaults(), self::special_defaults( $slug ) )
			: self::defaults();
		return array_merge( $base, $saved );
	}

	/**
	 * Persist settings for one archive.
	 *
	 * @param string $slug
	 * @param array  $data
	 */
	public static function save( string $slug, array $data ): void {
		update_option( self::OPTION_PREFIX . sanitize_key( $slug ), $data, false );
	}

	// ── Token parser ──────────────────────────────────────────────────────────

	/**
	 * Replace template tokens in a raw string.
	 *
	 * @param  string $raw   Input string that may contain %token% placeholders.
	 * @param  string $slug  Archive slug used to resolve %title%.
	 * @param  int    $paged Current pagination page number (0 or 1 = first page).
	 * @return string
	 */
	public static function parse_tokens( string $raw, string $slug, int $paged = 0 ): string {
		if ( '' === $raw ) {
			return '';
		}

		// %title% — context-dependent
		switch ( $slug ) {
			case 'post':
				$blog_page_id = (int) get_option( 'page_for_posts' );
				$title        = $blog_page_id
					? get_the_title( $blog_page_id )
					: get_bloginfo( 'name' );
				break;
			case 'author':
				$author_obj = get_queried_object();
				$title      = ( $author_obj instanceof \WP_User )
					? $author_obj->display_name
					: __( 'Author', 'site-essentials' );
				break;
			case 'date':
				if ( is_year() ) {
					$title = get_query_var( 'year' );
				} elseif ( is_month() ) {
					$title = date_i18n( 'F Y', mktime( 0, 0, 0, (int) get_query_var( 'monthnum' ), 1, (int) get_query_var( 'year' ) ) );
				} elseif ( is_day() ) {
					$title = date_i18n( get_option( 'date_format' ), mktime( 0, 0, 0, (int) get_query_var( 'monthnum' ), (int) get_query_var( 'day' ), (int) get_query_var( 'year' ) ) );
				} else {
					$title = __( 'Date Archive', 'site-essentials' );
				}
				break;
			case 'search':
				$title = get_search_query( false );
				break;
			case '404':
				$title = __( 'Page Not Found', 'site-essentials' );
				break;
			default:
				$pt    = get_post_type_object( $slug );
				$title = $pt ? $pt->labels->name : $slug;
		}

		// %sep%
		$sep = apply_filters( 'scos_seo_title_sep', '–' );

		// %page%
		$page_str = ( $paged > 1 )
			/* translators: %d: page number */
			? sprintf( __( 'Page %d', 'site-essentials' ), $paged )
			: '';

		// %search% — current search query (alias; same as %title% in search context)
		$search_q = is_search() ? get_search_query( false ) : '';

		// %author% — author display name
		$author_name = '';
		if ( is_author() ) {
			$author_obj  = get_queried_object();
			$author_name = ( $author_obj instanceof \WP_User ) ? $author_obj->display_name : '';
		}

		$tokens = [
			'%title%'    => $title,
			'%sitename%' => get_bloginfo( 'name' ),
			'%sep%'      => $sep,
			'%page%'     => $page_str,
			'%search%'   => $search_q,
			'%author%'   => $author_name,
		];

		return str_replace( array_keys( $tokens ), array_values( $tokens ), $raw );
	}

	// ── Form save handler ─────────────────────────────────────────────────────

	/**
	 * Process the admin-post form submission.
	 * Hooked to admin_post_site_essentials_save_archive_meta in Admin_UI.
	 */
	public static function handle_save(): void {
		if ( ! isset( $_POST['scos_archive_meta_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scos_archive_meta_nonce'] ) ), 'scos_save_archive_meta' ) ) {
			wp_die( esc_html__( 'Security check failed', 'site-essentials' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'site-essentials' ) );
		}

		$all_slugs = array_merge(
			array_keys( self::get_archives() ),
			array_keys( self::get_special_archives() )
		);
		$posted = ( isset( $_POST['scos_archive'] ) && is_array( $_POST['scos_archive'] ) )
			? wp_unslash( $_POST['scos_archive'] )
			: [];

		foreach ( $all_slugs as $slug ) {
			$raw = ( isset( $posted[ $slug ] ) && is_array( $posted[ $slug ] ) )
				? $posted[ $slug ]
				: [];

			// Base fields (all archive types)
			$data = [
				'title'            => sanitize_text_field( $raw['title'] ?? '' ),
				'description'      => sanitize_textarea_field( $raw['description'] ?? '' ),
				'breadcrumb_title' => sanitize_text_field( $raw['breadcrumb_title'] ?? '' ),
				'robots'           => self::sanitize_checkboxes(
					$raw['robots'] ?? [],
					[ 'noindex', 'nofollow', 'noimageindex', 'nosnippet' ]
				),
			];

			if ( ! in_array( $slug, self::SPECIAL_SLUGS, true ) ) {
				// CPT / Posts archive–only fields
				$data['tldr']               = sanitize_textarea_field( $raw['tldr'] ?? '' );
				$data['sitemap_exclude']     = self::sanitize_checkboxes(
					$raw['sitemap_exclude'] ?? [],
					[ 'xml', 'html', 'news' ]
				);
				$data['og_image_id']         = absint( $raw['og_image_id'] ?? 0 );
				$data['pagination_noindex']  = ! empty( $raw['pagination_noindex'] );
				$data['canonical_paged']     = ! empty( $raw['canonical_paged'] );
				$data['rel_prevnext']        = ! empty( $raw['rel_prevnext'] );
			} else {
				// Special archive extras
				if ( 'author' === $slug || 'date' === $slug || 'search' === $slug ) {
					$data['disabled']     = ! empty( $raw['disabled'] );
					$data['redirect_url'] = esc_url_raw( $raw['redirect_url'] ?? '' );
				}
				if ( 'author' === $slug ) {
					$custom_slug = sanitize_title( $raw['author_slug'] ?? '' );
					$data['author_slug'] = ( '' !== $custom_slug && 'author' !== $custom_slug )
						? $custom_slug
						: '';
					// Author also gets TLDR and OG image
					$data['tldr']        = sanitize_textarea_field( $raw['tldr'] ?? '' );
					$data['og_image_id'] = absint( $raw['og_image_id'] ?? 0 );
				}
			}

			self::save( $slug, $data );
		}

		// Flush rewrite rules if author slug changed
		delete_option( 'scos_seo_author_rewrite_ver' );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => \SiteEssentials\Core\Admin_UI::SEO_PAGE_SLUG,
					'tab'     => 'meta',
					'updated' => 'true',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Sanitise a checkbox array, keeping only values from the allowed list.
	 *
	 * @param  mixed    $input   Raw POST value.
	 * @param  string[] $allowed Permitted values.
	 * @return string[]
	 */
	private static function sanitize_checkboxes( $input, array $allowed ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}
		return array_values(
			array_intersect(
				array_map( 'sanitize_key', $input ),
				$allowed
			)
		);
	}

	// ── Redirect handler (template_redirect) ─────────────────────────────────

	/**
	 * Redirect disabled special archive types to their configured redirect URL
	 * (or home). Hooked to template_redirect at priority 5.
	 */
	public static function maybe_redirect(): void {
		$map = [
			'is_author' => 'author',
			'is_date'   => 'date',
			'is_search' => 'search',
		];
		foreach ( $map as $fn => $slug ) {
			if ( ! $fn() ) {
				continue;
			}
			$s = self::get( $slug );
			if ( empty( $s['disabled'] ) ) {
				continue;
			}
			$url = ! empty( $s['redirect_url'] ) ? $s['redirect_url'] : home_url( '/' );
			wp_redirect( $url, 301 );
			exit;
		}
	}

	// ── Author slug rewrite ───────────────────────────────────────────────────

	/**
	 * Override the /author/ URL prefix when a custom author_slug is configured.
	 * Hooked to init at priority 1 so it fires before rewrite rules are built.
	 */
	public static function set_author_slug(): void {
		$s    = self::get( 'author' );
		$slug = ! empty( $s['author_slug'] ) ? sanitize_title( $s['author_slug'] ) : '';
		if ( '' === $slug ) {
			return;
		}
		global $wp_rewrite;
		$wp_rewrite->author_base = $slug;

		// Flush rewrite rules once after a slug change
		$stored_ver = (string) get_option( 'scos_seo_author_rewrite_ver', '' );
		$current_ver = md5( $slug );
		if ( $stored_ver !== $current_ver ) {
			flush_rewrite_rules( false );
			update_option( 'scos_seo_author_rewrite_ver', $current_ver, false );
		}
	}

	// ── Bootstrap ────────────────────────────────────────────────────────────

	/**
	 * Called from SeoMeta_Module::init().
	 * Registers frontend redirect, author-slug, and admin media hooks.
	 */
	public static function init(): void {
		// Author slug rewrite — must be priority 1, before WP builds rules.
		add_action( 'init', [ __CLASS__, 'set_author_slug' ], 1 );

		// Redirect disabled archives — early priority so it fires before output.
		add_action( 'template_redirect', [ __CLASS__, 'maybe_redirect' ], 5 );

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {
				if ( false !== strpos( $hook, 'site-essentials-seo' ) ) {
					wp_enqueue_media();
				}
			} );
		}
	}
}
