<?php
/**
 * SEO Meta — Head Output
 *
 * Outputs <title>, <meta name="description">, <meta name="robots">,
 * and <link rel="canonical"> for singular posts/pages.
 *
 * Read priority per field:
 *   1. scos_seo_*   — set via our SeoMeta metabox
 *   2. _seopress_*  — legacy/migrated data (backward compat)
 *   3. WordPress defaults
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

		// Remove WordPress core's own rel_canonical() hook (priority 10) — it would
		// produce a second <link rel="canonical"> alongside ours.
		remove_action( 'wp_head', 'rel_canonical' );

		// Try to suppress SEOPress head output on singulars to avoid duplicates.
		add_action( 'template_redirect', [ __CLASS__, 'suppress_seopress_head' ], 1 );
	}

	// ── Title ────────────────────────────────────────────────────────────────

	/**
	 * @param  string $title Existing document title.
	 * @return string
	 */
	public static function get_title( $title ) {
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

	// ── Robots ───────────────────────────────────────────────────────────────

	/**
	 * Merge our robots directives into the WordPress wp_robots array.
	 *
	 * @param  array $robots
	 * @return array
	 */
	public static function filter_robots( $robots ) {
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

		// Apply scos_seo_robots array
		if ( in_array( 'noindex', $scos, true ) ) {
			$robots['noindex'] = true;
			unset( $robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview'] );
		} else {
			unset( $robots['noindex'] );
			$robots['max-image-preview'] = 'large';
			$robots['max-snippet']       = -1;
			$robots['max-video-preview'] = -1;
		}

		if ( in_array( 'nofollow', $scos, true ) ) {
			$robots['nofollow'] = true;
		} else {
			unset( $robots['nofollow'] );
		}

		if ( in_array( 'noimageindex', $scos, true ) ) {
			$robots['noimageindex'] = true;
		} else {
			unset( $robots['noimageindex'] );
		}

		if ( in_array( 'nosnippet', $scos, true ) ) {
			$robots['nosnippet'] = true;
		} else {
			unset( $robots['nosnippet'] );
		}

		return $robots;
	}

	// ── Description + Canonical ──────────────────────────────────────────────

	/**
	 * Output <meta name="description"> and <link rel="canonical"> at priority 0
	 * so they appear before SEOPress (priority 1) in the rendered head.
	 */
	public static function output_meta() {
		if ( is_admin() || ! is_singular() ) {
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

		echo "<!-- /SCOS SEO Meta -->\n\n";
	}

	// ── SEOPress suppression ─────────────────────────────────────────────────

	/**
	 * Attempt to remove SEOPress's wp_head meta output on singulars.
	 * Our wp_robots filter (priority 99) and wp_head priority 0 ensure our
	 * tags win regardless, but removing SEOPress avoids duplicate tags.
	 */
	public static function suppress_seopress_head() {
		if ( ! is_singular() ) {
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
}
