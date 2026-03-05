<?php
/**
 * Brighter Tools: Open Graph & Meta Tags
 *
 * File: brighter-og-meta.php
 * Version: 1.0.0
 *
 * Purpose: Complete Open Graph, Twitter Card, and enhanced meta tag output
 * Designed to work alongside or replace SEOPress OG functionality
 *
 * Responsibilities:
 * - Open Graph tags (og:url, og:site_name, og:locale, og:type, og:title, og:description)
 * - Article meta tags (article:published_time, article:modified_time)
 * - Twitter Card tags
 * - OG Image tags (already handled by image-optimisation.php, enhanced here)
 *
 * Changelog:
 * 1.0.0 - Initial implementation for Site Essentials SEO module
 *
 * Notes:
 * - Runs at priority 10 to work with existing image-optimisation.php (priority 1)
 * - Uses SEOPress meta if available, fallbacks to WordPress defaults
 * - Archives get og:type="website", singles get og:type="article"
 */

if (!defined('ABSPATH')) exit;

// Debug: Log that this module is being loaded
error_log('[Brighter OG Meta] Module loaded');

/**
 * Main Open Graph & Meta Tags Output
 * Priority 2 = Right after image tags (priority 1) to avoid output buffering issues
 */
add_action('wp_head', 'brighter_output_og_meta_tags', 2);
function brighter_output_og_meta_tags() {
    // Debug: Log that function is being called
    error_log('[Brighter OG Meta] wp_head hook fired');
    
    if (is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST)) {
        error_log('[Brighter OG Meta] Skipped - admin/feed/REST');
        return;
    }
    
    error_log('[Brighter OG Meta] Outputting OG tags');

    try {
        // Get business info
        $business_name = get_option('bw_business_name', get_bloginfo('name'));
        $site_name = get_bloginfo('name');
        error_log('[Brighter OG Meta] Got business name: ' . $business_name);
        
        // Determine locale (default to en_AU, can be extended)
        $locale = get_locale();
        $og_locale = str_replace('-', '_', $locale);
        if (empty($og_locale)) {
            $og_locale = 'en_AU';
        }

        // Get current URL
        $current_url = home_url(add_query_arg(null, null));
        error_log('[Brighter OG Meta] About to echo tags...');
        
        // =========================================
        // OG: URL, Site Name, Locale
        // =========================================
        echo "\n<!-- Open Graph Meta Tags -->\n";
        echo '<meta property="og:url" content="' . esc_url($current_url) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($business_name) . '">' . "\n";
        echo '<meta property="og:locale" content="' . esc_attr($og_locale) . '">' . "\n";
        error_log('[Brighter OG Meta] Basic tags echoed');
        
        // =========================================
        // OG: Type (website vs article)
        // =========================================
        $og_type = 'website'; // Default for pages and archives
        $is_article = false;
        
        if (is_singular() && !is_page()) {
            // Posts and CPTs (non-pages) are articles
            $og_type = 'article';
            $is_article = true;
        }
        
        echo '<meta property="og:type" content="' . esc_attr($og_type) . '">' . "\n";
        error_log('[Brighter OG Meta] Type tag echoed: ' . $og_type);
        
        // =========================================
        // OG: Title
        // =========================================
        $og_title = brighter_get_og_title();
        error_log('[Brighter OG Meta] Got title: ' . $og_title);
        if ($og_title) {
            echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";
        }
        
        // =========================================
        // OG: Description
        // =========================================
        $og_description = brighter_get_og_description();
        error_log('[Brighter OG Meta] Got description: ' . substr($og_description, 0, 50));
        if ($og_description) {
            echo '<meta property="og:description" content="' . esc_attr($og_description) . '">' . "\n";
        }
        
        // =========================================
        // Article or Website Time Meta
        // =========================================
        if ($is_article && is_singular()) {
            global $post;
            
            // article:published_time
            $published_time = get_the_date('c', $post->ID); // ISO 8601 format
            echo '<meta property="article:published_time" content="' . esc_attr($published_time) . '">' . "\n";
            
            // article:modified_time
            $modified_time = get_the_modified_date('c', $post->ID); // ISO 8601 format
            echo '<meta property="article:modified_time" content="' . esc_attr($modified_time) . '">' . "\n";
            
        } elseif (is_singular()) {
            // For pages (og:type=website), use og:updated_time
            global $post;
            $updated_time = get_the_modified_date('c', $post->ID);
            echo '<meta property="og:updated_time" content="' . esc_attr($updated_time) . '">' . "\n";
        }
        
        // =========================================
        // OG: Image Tags
        // =========================================
        // NOTE: Image tags are handled by brighter_inject_og_image_tags() in image-optimisation.php
        // This runs at priority 1, so images appear before these tags
        // We'll enhance that function to remove og:image:secure_url per requirements
        
        // =========================================
        // Twitter Card
        // =========================================
        echo "\n<!-- Twitter Card Meta Tags -->\n";
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo "<!-- /Open Graph Meta Tags -->\n\n";
        
        error_log('[Brighter OG Meta] All tags output successfully');
        
    } catch (Exception $e) {
        error_log('[Brighter OG Meta] ERROR: ' . $e->getMessage());
    }
}

/**
 * Get OG Title
 * Tries SEOPress meta first, falls back to WordPress defaults
 *
 * @return string
 */
function brighter_get_og_title() {
    // Singular posts/pages
    if (is_singular()) {
        global $post;
        
        // Try SEOPress meta
        $seopress_title = get_post_meta($post->ID, '_seopress_titles_title', true);
        if (!empty($seopress_title)) {
            return $seopress_title;
        }
        
        // Fallback to post title
        return get_the_title($post->ID);
    }
    
    // Archives
    if (is_archive()) {
        return get_the_archive_title();
    }
    
    // Home page
    if (is_home() || is_front_page()) {
        $seopress_home_title = get_option('seopress_titles_home_site_title', '');
        if (!empty($seopress_home_title)) {
            return $seopress_home_title;
        }
        
        return get_bloginfo('name') . ' - ' . get_bloginfo('description');
    }
    
    // Default
    return wp_get_document_title();
}

/**
 * Get OG Description
 * Tries SEOPress meta first, falls back to WordPress defaults
 *
 * @return string
 */
function brighter_get_og_description() {
    // Singular posts/pages
    if (is_singular()) {
        global $post;
        
        // Try SEOPress meta
        $seopress_desc = get_post_meta($post->ID, '_seopress_titles_desc', true);
        if (!empty($seopress_desc)) {
            return $seopress_desc;
        }
        
        // Fallback to excerpt
        if (has_excerpt($post->ID)) {
            return wp_strip_all_tags(get_the_excerpt($post));
        }
        
        // Generate from content (first 160 chars)
        $content = wp_strip_all_tags($post->post_content);
        return wp_trim_words($content, 30, '...');
    }
    
    // Archives
    if (is_archive()) {
        $archive_desc = get_the_archive_description();
        if (!empty($archive_desc)) {
            return wp_strip_all_tags($archive_desc);
        }
    }
    
    // Home page
    if (is_home() || is_front_page()) {
        $seopress_home_desc = get_option('seopress_titles_home_site_desc', '');
        if (!empty($seopress_home_desc)) {
            return $seopress_home_desc;
        }
        
        return get_bloginfo('description');
    }
    
    // Default
    return get_bloginfo('description');
}

/**
 * Get OG Image for Archives
 * Used for blog archive pages
 * 
 * @return string|false URL of the image or false
 */
function brighter_get_archive_og_image() {
    // For blog archive, try to get the featured image from the posts page
    if (is_home() && !is_front_page()) {
        $posts_page_id = get_option('page_for_posts');
        if ($posts_page_id && has_post_thumbnail($posts_page_id)) {
            $attachment_id = get_post_thumbnail_id($posts_page_id);
            return wp_get_attachment_image_url($attachment_id, 'og-image');
        }
    }
    
    // For other archives, could be extended to use taxonomy images, etc.
    // For now, return false and let default image handling take over
    return false;
}
