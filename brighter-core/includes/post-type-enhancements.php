<?php
/**
 * Enable author support for custom post types
 * 
 * Add to: brighter-core/includes/post-type-enhancements.php
 */

if (!defined('ABSPATH')) exit;

/**
 * Add author support to custom post types
 * Runs late (priority 99) to ensure CPTs are registered first
 */
add_action('init', function() {
    // Post types to add author support to
    $post_types = array(
        'projects',  // Customer Success Stories
        'folio',     // Portfolio
    );
    
    foreach ($post_types as $post_type) {
        if (post_type_exists($post_type)) {
            add_post_type_support($post_type, 'author');
        }
    }
}, 99);
