<?php
/**
 * Breadcrumb Shortcode
 * 
 * Displays breadcrumb navigation matching schema breadcrumbs.
 * Uses same logic as scos-schema-output.php for consistency.
 * 
 * Usage: [breadcrumbs] or [bw_breadcrumbs]
 * 
 * Output:
 * <ul class="bw-breadcrumbs">
 *   <li><a href="...">Home</a></li>
 *   <li><a href="...">Parent</a></li>
 *   <li>Current Page</li>
 * </ul>
 * 
 * @package    BrighterCore
 * @subpackage Breadcrumbs
 * @version    1.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Build breadcrumb array (shared logic with schema)
 * 
 * @return array Array of breadcrumb items with 'name', 'url', 'current'
 */
function bw_get_breadcrumb_items() {
    $breadcrumbs = [];
    $post_id = is_singular() ? get_the_ID() : 0;
    
    // Home - always first
    $breadcrumbs[] = [
        'name' => 'Home',
        'url' => home_url('/'),
        'current' => false
    ];
    
    // FRONT PAGE - just Home
    if (is_front_page()) {
        $breadcrumbs[0]['current'] = true;
        return $breadcrumbs;
    }
    
    // BLOG ARCHIVE (is_home() = true for blog posts page)
    if (is_home()) {
        $breadcrumbs[] = [
            'name' => 'Blog',
            'url' => get_post_type_archive_link('post') ?: home_url('/blog/'),
            'current' => true
        ];
        return $breadcrumbs;
    }
    
    // OTHER ARCHIVES (categories, tags, CPT archives, date archives)
    if (is_archive()) {
        $post_type = get_post_type() ?: 'post';
        $post_type_obj = get_post_type_object($post_type);
        
        if ($post_type_obj) {
            $breadcrumbs[] = [
                'name' => $post_type_obj->labels->name,
                'url' => get_post_type_archive_link($post_type),
                'current' => true
            ];
        }
        return $breadcrumbs;
    }
    
    // SINGLES (posts, pages, CPTs)
    if (is_singular()) {
        $post_type = get_post_type();
        
        // Add post type archive for posts
        if ($post_type === 'post') {
            $breadcrumbs[] = [
                'name' => 'Blog',
                'url' => get_post_type_archive_link('post') ?: home_url('/blog/'),
                'current' => false
            ];
        }
        
        // Add CPT archive for custom post types (not pages)
        if ($post_type !== 'post' && $post_type !== 'page') {
            $post_type_obj = get_post_type_object($post_type);
            if ($post_type_obj && $post_type_obj->has_archive) {
                $breadcrumbs[] = [
                    'name' => $post_type_obj->labels->name,
                    'url' => get_post_type_archive_link($post_type),
                    'current' => false
                ];
            }
        }
        
        // Add parent pages for hierarchical post types (pages)
        if (is_page()) {
            $ancestors = get_post_ancestors($post_id);
            $ancestors = array_reverse($ancestors);
            
            foreach ($ancestors as $ancestor_id) {
                $breadcrumbs[] = [
                    'name' => get_the_title($ancestor_id),
                    'url' => get_permalink($ancestor_id),
                    'current' => false
                ];
            }
        }
        
        // Current page - check for breadcrumb override (matches schema)
        $breadcrumb_override = get_post_meta($post_id, 'bw_breadcrumb_schema', true);
        $current_name = !empty($breadcrumb_override) ? $breadcrumb_override : get_the_title();
        
        $breadcrumbs[] = [
            'name' => $current_name,
            'url' => get_permalink(),
            'current' => true
        ];
    }
    
    return $breadcrumbs;
}

/**
 * Render breadcrumb HTML
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function bw_render_breadcrumbs($atts = []) {
    $atts = shortcode_atts([
        'class' => '',           // Additional CSS classes
        'separator' => '',       // Separator between items (CSS handles this by default)
        'home_icon' => false,    // Use icon instead of "Home" text
    ], $atts);
    
    $breadcrumbs = bw_get_breadcrumb_items();
    
    if (empty($breadcrumbs)) {
        return '';
    }
    
    // Build class attribute
    $classes = ['bw-breadcrumbs'];
    if (!empty($atts['class'])) {
        $classes[] = sanitize_html_class($atts['class']);
    }
    $class_attr = implode(' ', $classes);
    
    // Build HTML with wrapper for overflow detection
    $output = '<div class="bw-breadcrumbs-wrap">';
    $output .= '<ul class="' . esc_attr($class_attr) . '">';
    
    $count = count($breadcrumbs);
    foreach ($breadcrumbs as $i => $crumb) {
        $is_last = ($i === $count - 1);
        
        $output .= '<li>';
        
        if ($is_last || $crumb['current']) {
            // Current page - no link
            $output .= esc_html($crumb['name']);
        } else {
            // Linked breadcrumb
            $output .= sprintf(
                '<a href="%s" target="_self" data-type="url">%s</a>',
                esc_url($crumb['url']),
                esc_html($crumb['name'])
            );
        }
        
        $output .= '</li>';
    }
    
    $output .= '</ul>';
    $output .= '</div>';
    
    return $output;
}

// Register shortcodes
add_shortcode('breadcrumbs', 'bw_render_breadcrumbs');
add_shortcode('bw_breadcrumbs', 'bw_render_breadcrumbs');

