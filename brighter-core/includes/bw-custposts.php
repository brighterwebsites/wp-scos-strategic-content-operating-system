<?php
/**
 * 
 * Brighter Tools: Cusomisations for Single and Archive Page Posts and CPTs
 * File:  
 * Purpose:  
 *  
 * Version: 4.0.0
 *
 * Responsibilities:
 * - Force self-referencing canonicals on paginated archives (fixes double page/2/page/2 issue)
 * - 
 * - 
 * - 
 * -  
*
 * Notes:
 * - 
 * - 
 *
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