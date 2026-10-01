<?php
/**
 * Brighter Tools: Customisations for Single and Archive Page Posts and CPTs
 *
 * v4.0.0
 * v4.1.0 | 2026-06-29 — Remove pagetype taxonomy (unused).
 * v4.2.0 | 2026-10-01 — Paged canonical stands down when SCOS SEO Meta printed one.
 *
 * Responsibilities:
 * - Force self-referencing canonicals on paginated archives (fixes double page/2/page/2 issue)
 * - Enable page excerpts
 */

if (!defined('ABSPATH')) exit;


// Force self-referencing canonicals on paginated archives (fixes double page/2/page/2 issue)
// Fallback only: stands down when SCOS SEO Meta has already printed a canonical,
// so a page never carries two. Still needed on sites with the SEO module off.
// TODO: migrate to site-essentials — delete once every site runs the SEO module.
add_action('wp_head', function() {
    $seo_meta = '\SiteEssentials\Modules\SeoMeta\Head_Output';
    if ( class_exists( $seo_meta, false ) && $seo_meta::canonical_printed() ) {
        return;
    }

    if ( is_paged() && ! is_singular() ) {
        global $wp;

        // Build canonical from current request (already includes /page/2/)
        $canonical = home_url( user_trailingslashit( $wp->request ) );

        echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
    }
}, 99);


// ==========================
// Page Excerpts
// ==========================
add_action('init', function() {
    add_post_type_support('page', 'excerpt');
});
