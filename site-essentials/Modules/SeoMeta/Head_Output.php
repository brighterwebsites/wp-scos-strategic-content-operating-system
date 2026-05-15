<?php
/**
 * SEO Meta — Head Output
 *
 * Outputs <title>, <meta name="description">, <meta name="robots">,
 * <link rel="canonical">, and rel="prev/next" pagination links to the
 * <head> for both singular posts/pages and archive views.
 *
 * Read priority for singulars:
 *   1. scos_seo_*   — set via our SeoMeta metabox
 *   2. _seopress_*  — legacy/migrated data (backward compat)
 *   3. WordPress defaults
 *
 * Read priority for archives:
 *   1. scos_seo_archive_{slug} option — set via SEO > Meta Tags admin tab
 *   2. WordPress defaults (title only)
 *
 * SEOPress suppression:
 *   When this class is active we try to remove SEOPress's wp_head meta
 *   output on singulars to avoid duplicate tags. Our wp_robots filter at
 *   priority 99 overrides SEOPress regardless of whether its hook removal
 *   succeeds.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Head_Output {

	public static function init() {
		// Title — priority 100 overrides SEOPress (priority 10) and theme filters.
		add_filter( 'pre_get_document_title', [ __CLASS__, 'get_title' ], 100 );

		// Robots — WordPress 5.7+ filter; priority 99 overrides SEOPress.
		add_filter( 'wp_robots', [ __CLASS__, 'filter_robots' ], 99 );

		// Description + Canonical — priority 2, AFTER WordPress outputs title + robots
		// at priority 1 (_wp_render_title_tag, wp_robots), giving the natural order:
		// <title> → <meta robots> → <meta description> → <link canonical>
		add_action( 'wp_head', [ __CLASS__, 'output_meta' ], 2 );

		// Rel prev/next — priority 3 so they appear immediately after canonical.
		add_action( 'wp_head', [ __CLASS__, 'output_rel_links' ], 3 );

		// Remove WordPress core's own rel_canonical() hook (priority 10) — it would
		// produce a second <link rel="canonical"> alongside ours.
		remove_action( 'wp_head', 'rel_canonical' );

		// Try to suppress SEOPress head output on singulars to avoid duplicates.
		add_action( 'template_redirect', [ __CLASS__, 'suppress_seopress_head' ], 1 );
	}

	// ── Context helper ────────────────────────────────────────────────────────

	/**
	 * Return the archive slug for the current request, or null if not an archive
	 * we manage.
	 *
	 * 'post'        → blog / posts index (is_home())
	 * <cpt_name>    → CPT archive (is_post_type_archive())
	 * 'author'      → author archive (is_author())
	 * 'date'        → date archive (is_date())
	 * 'search'      → search results (is_search())
	 * '404'         → 404 not found (is_404())
	 * null          → not a managed archive context
	 *
	 * @return string|null
	 */
	private static function archive_slug(): ?string {
		if ( is_home() ) {
			return 'post';
		}
		if ( is_post_type_archive() ) {
			$obj = get_queried_object();
			return ( $obj instanceof \WP_Post_Type ) ? $obj->name : null;
		}
		if ( is_author() )  { return 'author'; }
		if ( is_date() )    { return 'date'; }
		if ( is_search() )  { return 'search'; }
		if ( is_404() )     { return '404'; }
		// Taxonomy term archives (is_category, is_tag, is_tax all return a WP_Term)
		if ( is_category() || is_tag() || is_tax() ) {
			$obj = get_queried_object();
			return ( $obj instanceof \WP_Term ) ? $obj->taxonomy : null;
		}
		return null;
	}

	// ── Title ─────────────────────────────────────────────────────────────────

	/**
	 * @param  string $title Existing document title.
	 * @return string
	 */
	public static function get_title( $title ) {
		// ── Archive branch ────────────────────────────────────────────────────
		$slug = self::archive_slug();
		if ( null !== $slug ) {
			$settings = Archive_Settings::get( $slug );
			if ( ! empty( $settings['title'] ) ) {
				$paged = max( 0, (int) get_query_var( 'paged' ) );
				return Archive_Settings::parse_tokens( $settings['title'], $slug, $paged );
			}
			return $title; // WP default archive title
		}

		// ── Singular branch ───────────────────────────────────────────────────
		if ( ! is_singular() ) {
			return $title;
		}
		$pid = get_the_ID();
		if ( ! $pid ) {
			return $title;
		}

		$val = get_post_meta( $pid, 'scos_seo_title', true );
		if ( ! empty( $val ) ) {
			return $val;
		}

		// Fallback: SEOPress stored value (populated by our dual-write on save)
		$sp = get_post_meta( $pid, '_seopress_titles_title', true );
		if ( ! empty( $sp ) ) {
			return $sp;
		}

		return $title; // WordPress default
	}

	// ── Robots ────────────────────────────────────────────────────────────────

	/**
	 * Merge our robots directives into the WordPress wp_robots array.
	 *
	 * @param  array $robots
	 * @return array
	 */
	public static function filter_robots( $robots ) {
		// ── Archive branch ────────────────────────────────────────────────────
		$slug = self::archive_slug();
		if ( null !== $slug ) {
			$settings = Archive_Settings::get( $slug );
			$directives = is_array( $settings['robots'] ) ? $settings['robots'] : [];

			// Paginated noindex override
			$paged = max( 0, (int) get_query_var( 'paged' ) );
			if ( $settings['pagination_noindex'] && $paged > 1 ) {
				$directives[] = 'noindex';
			}

			return self::apply_robots_array( $robots, array_unique( $directives ) );
		}

		// ── Singular branch ───────────────────────────────────────────────────
		if ( ! is_singular() ) {
			return $robots;
		}
		$pid = get_the_ID();
		if ( ! $pid ) {
			return $robots;
		}

		$scos = get_post_meta( $pid, 'scos_seo_robots', true );

		// No scos data — try SEOPress legacy keys
		if ( ! is_array( $scos ) || empty( $scos ) ) {
			$sp_noindex  = get_post_meta( $pid, '_seopress_robots_index', true );
			$sp_nofollow = get_post_meta( $pid, '_seopress_robots_follow', true );
			if ( '1' === $sp_noindex ) {
				$robots['noindex'] = true;
				unset( $robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview'] );
			}
			if ( '1' === $sp_nofollow ) {
				$robots['nofollow'] = true;
			}
			return $robots;
		}

		return self::apply_robots_array( $robots, $scos );
	}

	/**
	 * Apply a list of robots directive strings to the $robots array.
	 *
	 * @param  array    $robots     Existing wp_robots array.
	 * @param  string[] $directives Subset of: noindex, nofollow, noimageindex, nosnippet.
	 * @return array
	 */
	private static function apply_robots_array( array $robots, array $directives ): array {
		if ( in_array( 'noindex', $directives, true ) ) {
			$robots['noindex'] = true;
			unset( $robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview'] );
		} else {
			unset( $robots['noindex'] );
			$robots['max-image-preview'] = 'large';
			$robots['max-snippet']       = -1;
			$robots['max-video-preview'] = -1;
		}

		if ( in_array( 'nofollow', $directives, true ) ) {
			$robots['nofollow'] = true;
		} else {
			unset( $robots['nofollow'] );
		}

		if ( in_array( 'noimageindex', $directives, true ) ) {
			$robots['noimageindex'] = true;
		} else {
			unset( $robots['noimageindex'] );
		}

		if ( in_array( 'nosnippet', $directives, true ) ) {
			$robots['nosnippet'] = true;
		} else {
			unset( $robots['nosnippet'] );
		}

		return $robots;
	}

	// ── Description + Canonical ───────────────────────────────────────────────

	/**
	 * Output <meta name="description">, <link rel="canonical">, and Open Graph
	 * tags at priority 2. Handles both singulars and managed archives.
	 *
	 * Note: og:image is handled separately by brighter_inject_og_image_tags()
	 * (image-optimisation.php, priority 1) which fires before this method.
	 */
	public static function output_meta() {
		if ( is_admin() ) {
			return;
		}

		// ── Archive branch ────────────────────────────────────────────────────
		$slug = self::archive_slug();
		if ( null !== $slug ) {
			self::output_archive_meta( $slug );
			return;
		}

		// ── Singular branch ───────────────────────────────────────────────────
		if ( ! is_singular() ) {
			return;
		}
		$pid = get_the_ID();
		if ( ! $pid ) {
			return;
		}

		echo "\n<!-- SCOS SEO Meta -->\n";

		// ── Meta description ─────────────────────────────────────────────────
		$desc = get_post_meta( $pid, 'scos_seo_description', true );

		if ( empty( $desc ) ) {
			$desc = get_post_meta( $pid, '_seopress_titles_desc', true );
		}

		if ( empty( $desc ) ) {
			$post = get_post( $pid );
			if ( $post && has_excerpt( $pid ) ) {
				$desc = wp_strip_all_tags( get_the_excerpt( $post ) );
			}
		}

		if ( ! empty( $desc ) ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}

		// ── Canonical ────────────────────────────────────────────────────────
		$canonical = get_post_meta( $pid, 'scos_seo_canonical', true );

		if ( empty( $canonical ) ) {
			$canonical = get_post_meta( $pid, '_seopress_robots_canonical', true );
		}

		if ( empty( $canonical ) ) {
			$canonical = get_permalink( $pid );
		}

		if ( ! empty( $canonical ) ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
		}

		// ── Open Graph ───────────────────────────────────────────────────────
		$og_url       = ! empty( $canonical ) ? $canonical : get_permalink( $pid );
		$og_title     = get_post_meta( $pid, 'scos_seo_title', true );
		if ( empty( $og_title ) ) {
			$og_title = get_the_title( $pid );
		}
		$og_locale    = str_replace( '-', '_', get_locale() ) ?: 'en_AU';
		$og_site_name = get_bloginfo( 'name' );

		// Pages → og:type=website; posts and CPTs → og:type=article
		$is_article = is_singular() && ! is_page();
		$og_type    = $is_article ? 'article' : 'website';

		echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $og_url ) . '" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( $og_site_name ) . '" />' . "\n";
		echo '<meta property="og:locale" content="' . esc_attr( $og_locale ) . '" />' . "\n";

		if ( ! empty( $og_title ) ) {
			echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
		}
		if ( ! empty( $desc ) ) {
			echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}

		// ── Article-specific Open Graph ───────────────────────────────────────
		if ( $is_article ) {
			$published = (string) get_the_date( 'c', $pid );
			$modified  = (string) get_the_modified_date( 'c', $pid );
			$author_id  = (int) get_post_field( 'post_author', $pid );
			$author_url = get_author_posts_url( $author_id );

			echo '<meta property="article:published_time" content="' . esc_attr( $published ) . '" />' . "\n";
			echo '<meta property="article:modified_time" content="' . esc_attr( $modified ) . '" />' . "\n";

			if ( ! empty( $author_url ) ) {
				echo '<meta property="article:author" content="' . esc_url( $author_url ) . '" />' . "\n";
			}

			// article:section — first ALTC topic assigned to this post
			$topics = wp_get_post_terms( $pid, 'altc_topic', [ 'fields' => 'names' ] );
			if ( ! empty( $topics ) && ! is_wp_error( $topics ) ) {
				echo '<meta property="article:section" content="' . esc_attr( $topics[0] ) . '" />' . "\n";
			}
		} else {
			// Static page: og:updated_time instead of article times
			$updated = (string) get_the_modified_date( 'c', $pid );
			echo '<meta property="og:updated_time" content="' . esc_attr( $updated ) . '" />' . "\n";
		}

		echo "<!-- /SCOS SEO Meta -->\n\n";
	}

	/**
	 * Output archive description, canonical, and OG image.
	 *
	 * @param string $slug Archive slug.
	 */
	private static function output_archive_meta( string $slug ): void {
		$settings = Archive_Settings::get( $slug );
		$paged    = max( 0, (int) get_query_var( 'paged' ) );

		echo "\n<!-- SCOS SEO Meta (archive) -->\n";

		// ── Meta description ─────────────────────────────────────────────────
		if ( ! empty( $settings['description'] ) ) {
			$desc = Archive_Settings::parse_tokens( $settings['description'], $slug, $paged );
			if ( '' !== $desc ) {
				echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
			}
		}

		// ── Canonical ────────────────────────────────────────────────────────
		$base_url = self::archive_base_url( $slug );

		if ( ! empty( $base_url ) ) {
			$canonical = ( $settings['canonical_paged'] && $paged > 1 )
				? get_pagenum_link( $paged )
				: $base_url;
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
		}

		// ── OG image ─────────────────────────────────────────────────────────
		$og_image_id = (int) $settings['og_image_id'];
		if ( $og_image_id > 0 ) {
			$img = wp_get_attachment_image_src( $og_image_id, 'full' );
			if ( $img ) {
				[ $src, $width, $height ] = $img;
				$mime = get_post_mime_type( $og_image_id );
				$alt  = trim( (string) get_post_meta( $og_image_id, '_wp_attachment_image_alt', true ) );
				echo '<meta property="og:image" content="' . esc_url( $src ) . '" />' . "\n";
				if ( $width )  { echo '<meta property="og:image:width" content="' . esc_attr( (string) $width ) . '" />' . "\n"; }
				if ( $height ) { echo '<meta property="og:image:height" content="' . esc_attr( (string) $height ) . '" />' . "\n"; }
				if ( $mime )   { echo '<meta property="og:image:type" content="' . esc_attr( $mime ) . '" />' . "\n"; }
				if ( $alt )    { echo '<meta property="og:image:alt" content="' . esc_attr( $alt ) . '" />' . "\n"; }
			}
		}

		echo "<!-- /SCOS SEO Meta (archive) -->\n\n";
	}

	// ── Rel prev / next ───────────────────────────────────────────────────────

	/**
	 * Output rel="prev" and rel="next" pagination links for archives where the
	 * setting is enabled.
	 *
	 * Hooked to wp_head at priority 3 (after canonical at priority 2).
	 */
	public static function output_rel_links(): void {
		if ( is_admin() ) {
			return;
		}

		$slug = self::archive_slug();
		if ( null === $slug ) {
			return;
		}

		$settings = Archive_Settings::get( $slug );
		if ( empty( $settings['rel_prevnext'] ) ) {
			return;
		}

		global $wp_query;
		$max_pages = (int) $wp_query->max_num_pages;
		$paged     = max( 1, (int) get_query_var( 'paged' ) );

		if ( $paged > 1 ) {
			echo '<link rel="prev" href="' . esc_url( get_pagenum_link( $paged - 1 ) ) . '" />' . "\n";
		}
		if ( $paged < $max_pages ) {
			echo '<link rel="next" href="' . esc_url( get_pagenum_link( $paged + 1 ) ) . '" />' . "\n";
		}
	}

	// ── SEOPress suppression ──────────────────────────────────────────────────

	/**
	 * Attempt to remove SEOPress's wp_head meta output on singulars and archives.
	 * Our wp_robots filter (priority 99) and wp_head priority 0 ensure our
	 * tags win regardless, but removing SEOPress avoids duplicate tags.
	 */
	public static function suppress_seopress_head() {
		if ( ! is_singular() && null === self::archive_slug() ) {
			return;
		}

		// SEOPress ≤ 6.x — function-based hook
		if ( function_exists( 'seopress_add_meta' ) ) {
			remove_action( 'wp_head', 'seopress_add_meta', 1 );
		}

		// SEOPress 7.x class-based hooks (gracefully ignored if class not found)
		foreach ( [ '\SEOPRESS_FRONT_CLASS', '\SeoPress\Modules\Front\Head\Meta' ] as $class ) {
			if ( class_exists( $class ) && method_exists( $class, 'get_instance' ) ) {
				$inst = call_user_func( [ $class, 'get_instance' ] );
				if ( $inst ) {
					foreach ( [ 'seopress_add_meta', 'add_meta_tags', 'render' ] as $method ) {
						if ( method_exists( $inst, $method ) ) {
							remove_action( 'wp_head', [ $inst, $method ], 1 );
						}
					}
				}
			}
		}
	}

	// ── URL helpers ───────────────────────────────────────────────────────────

	/**
	 * Resolve the canonical base URL for an archive (non-paginated page 1).
	 * Returns empty string for contexts where a canonical is inappropriate
	 * (search results, 404).
	 *
	 * @param  string $slug
	 * @return string
	 */
	private static function archive_base_url( string $slug ): string {
		switch ( $slug ) {
			case 'post':
				$blog_page_id = (int) get_option( 'page_for_posts' );
				return $blog_page_id
					? (string) get_permalink( $blog_page_id )
					: home_url( '/' );

			case 'author':
				return (string) get_author_posts_url( get_queried_object_id() );

			case 'date':
				if ( is_year() ) {
					return (string) get_year_link( (int) get_query_var( 'year' ) );
				}
				if ( is_month() ) {
					return (string) get_month_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ) );
				}
				if ( is_day() ) {
					return (string) get_day_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ), (int) get_query_var( 'day' ) );
				}
				return '';

			case 'search':
			case '404':
				// No canonical — search is query-specific; 404 has no URL to canonicalise to.
				return '';

			default:
				// Taxonomy term archive — canonical is the term's own URL
				if ( taxonomy_exists( $slug ) ) {
					$term = get_queried_object();
					if ( $term instanceof \WP_Term ) {
						$link = get_term_link( $term );
						return is_wp_error( $link ) ? '' : (string) $link;
					}
					return '';
				}
				// CPT archive
				return (string) ( get_post_type_archive_link( $slug ) ?: '' );
		}
	}
}
