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
 * @version    1.2 | 2026-05-25
 */

if (!defined('ABSPATH')) exit;

/**
 * Posts index crumb: Settings → Reading “Posts page”, or post type archive link + fallback label.
 *
 * @return array{name:string,url:string,current:bool}
 */
function bw_get_posts_archive_crumb_item() {
    $posts_page_id = (int) get_option('page_for_posts');
    if ($posts_page_id > 0) {
        $title = get_the_title($posts_page_id);
        if ($title === '' || $title === false) {
            $title = __('Posts', 'brighterwebsites');
        }
        $url = get_permalink($posts_page_id);
        if (!$url) {
            $url = get_post_type_archive_link('post') ?: home_url('/');
        }
        return [
            'name' => $title,
            'url' => $url,
            'current' => false,
        ];
    }
    $archive = get_post_type_archive_link('post');
    $post_obj = get_post_type_object('post');
    $name = ($post_obj && !empty($post_obj->labels->name)) ? $post_obj->labels->name : __('Blog', 'brighterwebsites');
    return [
        'name' => $name,
        'url' => $archive ?: home_url('/'),
        'current' => false,
    ];
}

/**
 * Apply extensions (e.g. Site Essentials permalink segments).
 *
 * @param array $items Breadcrumb rows.
 * @return array
 */
function bw_apply_breadcrumb_items_filter($items) {
    return apply_filters('bw_breadcrumb_items', is_array($items) ? $items : []);
}

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
        return bw_apply_breadcrumb_items_filter($breadcrumbs);
    }
    
    // BLOG ARCHIVE (is_home() = true for blog posts page)
    if (is_home()) {
        $crumb = bw_get_posts_archive_crumb_item();
        $crumb['current'] = true;
        $breadcrumbs[] = $crumb;
        return bw_apply_breadcrumb_items_filter($breadcrumbs);
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
        return bw_apply_breadcrumb_items_filter($breadcrumbs);
    }
    
    // SINGLES (posts, pages, CPTs)
    if (is_singular()) {
        $post_type = get_post_type();
        
        // Add post type archive for posts
        if ($post_type === 'post') {
            $breadcrumbs[] = bw_get_posts_archive_crumb_item();
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
        
        // Current page - check for breadcrumb override (SCOS SEO field)
        $breadcrumb_override = get_post_meta($post_id, 'scos_seo_breadcrumb_title', true);
        $current_name = !empty($breadcrumb_override) ? $breadcrumb_override : get_the_title();
        
        $breadcrumbs[] = [
            'name' => $current_name,
            'url' => get_permalink(),
            'current' => true
        ];
    }

    return bw_apply_breadcrumb_items_filter($breadcrumbs);
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

