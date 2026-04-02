<?php
/**
 * SEO Meta — Archive Settings
 *
 * Stores, retrieves, and processes SEO settings for the Posts archive (blog)
 * and all public CPTs that have `has_archive` enabled.
 *
 * Option key pattern: scos_seo_archive_{slug}
 *
 * Each option is a serialised array:
 *  title             – template string with tokens
 *  description       – template string with tokens
 *  breadcrumb_title  – plain string
 *  tldr              – plain string
 *  robots            – array subset of: noindex, nofollow, noimageindex, nosnippet
 *  sitemap_exclude   – array subset of: xml, html, news
 *  og_image_id       – attachment ID (int)
 *  pagination_noindex – bool — noindex on /page/2+
 *  canonical_paged    – bool — self-canonical on paginated pages
 *  rel_prevnext       – bool — output rel="prev" / rel="next"
 *
 * Token set (used in title and description fields):
 *  %title%    Archive name (post type plural label or blog page title)
 *  %sitename% get_bloginfo('name')
 *  %sep%      Separator, filterable via scos_seo_title_sep (default –)
 *  %page%     "Page N" on paginated views, empty on page 1
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

	const OPTION_PREFIX = 'scos_seo_archive_';

	// ── Defaults ─────────────────────────────────────────────────────────────

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

	// ── Archive discovery ─────────────────────────────────────────────────────

	/**
	 * Returns slug → label pairs for all configurable archives.
	 *
	 * Order: Posts (blog) first, then CPTs with has_archive sorted alphabetically
	 * by their plural label.
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
			if ( empty( $pt->has_archive ) ) {
				continue;
			}
			$cpt_archives[ $pt->name ] = $pt->labels->name;
		}

		asort( $cpt_archives );

		return $archives + $cpt_archives;
	}

	// ── CRUD helpers ──────────────────────────────────────────────────────────

	/**
	 * Retrieve settings for one archive, merged with defaults.
	 *
	 * @param  string $slug Archive slug ('post' or CPT name).
	 * @return array
	 */
	public static function get( string $slug ): array {
		$saved = get_option( self::OPTION_PREFIX . sanitize_key( $slug ), [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		return array_merge( self::defaults(), $saved );
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

		// %title%
		if ( 'post' === $slug ) {
			$blog_page_id = (int) get_option( 'page_for_posts' );
			$title        = $blog_page_id
				? get_the_title( $blog_page_id )
				: get_bloginfo( 'name' );
		} else {
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

		$tokens = [
			'%title%'    => $title,
			'%sitename%' => get_bloginfo( 'name' ),
			'%sep%'      => $sep,
			'%page%'     => $page_str,
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

		$archives = self::get_archives();
		$posted   = ( isset( $_POST['scos_archive'] ) && is_array( $_POST['scos_archive'] ) )
			? wp_unslash( $_POST['scos_archive'] )
			: [];

		foreach ( array_keys( $archives ) as $slug ) {
			$raw = ( isset( $posted[ $slug ] ) && is_array( $posted[ $slug ] ) )
				? $posted[ $slug ]
				: [];

			$data = [
				'title'              => sanitize_text_field( $raw['title'] ?? '' ),
				'description'        => sanitize_textarea_field( $raw['description'] ?? '' ),
				'breadcrumb_title'   => sanitize_text_field( $raw['breadcrumb_title'] ?? '' ),
				'tldr'               => sanitize_textarea_field( $raw['tldr'] ?? '' ),
				'robots'             => self::sanitize_checkboxes(
					$raw['robots'] ?? [],
					[ 'noindex', 'nofollow', 'noimageindex', 'nosnippet' ]
				),
				'sitemap_exclude'    => self::sanitize_checkboxes(
					$raw['sitemap_exclude'] ?? [],
					[ 'xml', 'html', 'news' ]
				),
				'og_image_id'        => absint( $raw['og_image_id'] ?? 0 ),
				'pagination_noindex' => ! empty( $raw['pagination_noindex'] ),
				'canonical_paged'    => ! empty( $raw['canonical_paged'] ),
				'rel_prevnext'       => ! empty( $raw['rel_prevnext'] ),
			];

			self::save( $slug, $data );
		}

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

	/**
	 * Called from SeoMeta_Module::init() — no hooks registered here;
	 * all hooks live in Admin_UI (save handler) and Head_Output (frontend output).
	 */
	public static function init(): void {}
}
