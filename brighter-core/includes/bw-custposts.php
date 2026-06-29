<?php
/**
 * Brighter Tools: Customisations for Single and Archive Page Posts and CPTs
 *
 * v4.0.0
 * v4.1.0 | 2026-06-29 — Remove pagetype taxonomy (unused).
 *
 * Responsibilities:
 * - Force self-referencing canonicals on paginated archives (fixes double page/2/page/2 issue)
 * - Enable page excerpts
 */

if (!defined('ABSPATH')) exit;


// Force self-referencing canonicals on paginated archives (fixes double page/2/page/2 issue)
add_action('wp_head', function() {
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
