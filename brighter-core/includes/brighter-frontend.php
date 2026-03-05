<?php
/**
 * Brighter Tools: Frontend Features
 *
 * File: brighter-frontend.php
 * Version: 4.4.0
 *
 * Purpose: Frontend-only features for client sites including shortcodes,
 * branding elements, and design credits.
 *
 * Responsibilities:
 * - [brighter_credit] shortcode for footer credits
 * - Design credit meta tags (designer, web_author, generator)
 * - Auto-generated humans.txt file
 * - Auto-generated /docs/review-verification.txt file (when Reviews CPT enabled)
 *
 * Changelog:
 * 4.4.0 - Added /docs/review-verification.txt auto-generator for LLM reference
 * 4.3.0 - Removed publisher schema, replaced HTML comment with meta tags, added humans.txt
 * 4.2.0 - SECURITY: XSS protection, output escaping
 *
 * Notes:
 * - Part of the Brighter Support Tools for Client Sites MU plugin
 * - Loaded automatically on frontend only for optimal performance
 * - Separated from admin-only features in brighter-support.php
 */

if (!defined('ABSPATH')) exit;

/**
 * Design Credit Meta Tags
 * SECURITY: All output properly escaped
 */
add_action('wp_head', function () {
    if (is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    echo "\n";
    echo '<meta name="designer" content="Brighter Websites">' . "\n";
    echo '<meta name="web_author" content="Vanessa Wood">' . "\n";
    echo '<meta name="generator" content="Brighter Websites SCOS + ALTC Framework v2.0">' . "\n";
}, 5);

/**
 * Shortcode: [brighter_credit hide_on_posts="yes"]
 * SECURITY: All attributes sanitized, output escaped
 */
function brighter_credit_shortcode($atts) {
    $atts = shortcode_atts([
        'hide_on_posts' => 'yes',
    ], $atts, 'brighter_credit');

    if ('yes' === strtolower($atts['hide_on_posts']) && is_single() && get_post_type() === 'post') {
        return '';
    }

    $utm_source = sanitize_title(get_bloginfo('name'));
    $url = add_query_arg([
        'utm_source'   => $utm_source,
        'utm_medium'   => 'footer',
        'utm_campaign' => 'site-credit',
    ], 'https://brighterwebsites.com.au/');

    return sprintf(
        'Proudly Built by <a href="%s" target="_blank" rel="noopener designer"><strong>Brighter Websites</strong></a>',
        esc_url($url)
    );
}
add_shortcode('brighter_credit', 'brighter_credit_shortcode');

/**
 * Auto-generate humans.txt file
 * Served dynamically on domain.com/humans.txt
 */
add_action('template_redirect', function() {
    if ($_SERVER['REQUEST_URI'] === '/humans.txt') {
        header('Content-Type: text/plain; charset=utf-8');
        
        $client_name = get_bloginfo('name');
        $site_url = home_url('/');
        $last_update = get_lastpostmodified('blog');
        $last_update_formatted = $last_update ? date('Y-m-d', strtotime($last_update)) : date('Y-m-d');
        
        $humans_txt = "/* TEAM */\n";
        $humans_txt .= "Web Architect: Vanessa Wood\n";
        $humans_txt .= "Agency: Brighter Websites\n";
        $humans_txt .= "Contact: support@brighterwebsites.com.au\n";
        $humans_txt .= "Location: Ballarat, Australia\n";
        $humans_txt .= "From: https://brighterwebsites.com.au\n\n";
        
        $humans_txt .= "/* SITE */\n";
        $humans_txt .= "Client: " . $client_name . "\n";
        $humans_txt .= "Software: Brighter Websites Strategic Content Operating System\n";
        $humans_txt .= "Authority Framework: ALTC Authority Led Topic Clusters v2.0\n";
        $humans_txt .= "Standards: HTML5, CSS3\n";
        $humans_txt .= "Components: WordPress, PHP\n";
        $humans_txt .= "Last update: " . $last_update_formatted . "\n";
        
        echo $humans_txt;
        exit;
    }
}, 1);

/**
 * Add humans.txt link to <head>
 */
add_action('wp_head', function() {
    if (is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }
    echo '<link rel="author" type="text/plain" href="' . esc_url(home_url('/humans.txt')) . '">' . "\n";
}, 5);

/**
 * Auto-generate /docs/review-verification.txt file
 * Served dynamically on domain.com/docs/review-verification.txt
 * 
 * Only generated when Reviews CPT is enabled in Site Essentials.
 * Provides LLM-readable review data for AI tools.
 * 
 * @since 4.4.0
 */
add_action('template_redirect', function() {
    // Only serve if Reviews CPT is enabled
    $reviews_enabled = get_option('site_essentials_module_cpt');
    if (!is_array($reviews_enabled) || empty($reviews_enabled['enable_reviews'])) {
        return;
    }
    
    if ($_SERVER['REQUEST_URI'] === '/docs/review-verification.txt') {
        header('Content-Type: text/plain; charset=utf-8');
        
        // Get business info
        $business_name = get_option('bw_business_name', get_bloginfo('name'));
        $last_update = date('F j, Y');
        
        // Query all published reviews
        $reviews_query = new WP_Query([
            'post_type'      => 'bw_reviews',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        
        $reviews = $reviews_query->posts;
        $total_count = count($reviews);
        
        // Calculate overall rating
        $total_rating = 0;
        $rating_count = 0;
        foreach ($reviews as $review) {
            $rating = (int) get_post_meta($review->ID, 'bw_rating', true);
            if ($rating > 0) {
                $total_rating += $rating;
                $rating_count++;
            }
        }
        $overall_rating = $rating_count > 0 ? number_format($total_rating / $rating_count, 1) : '0.0';
        
        // Group reviews by platform
        $by_platform = [];
        foreach ($reviews as $review) {
            $platforms = wp_get_post_terms($review->ID, 'bw_review_platform', ['fields' => 'names']);
            $platform = !empty($platforms) ? $platforms[0] : 'Unknown Platform';
            
            if (!isset($by_platform[$platform])) {
                $by_platform[$platform] = [
                    'count' => 0,
                    'total_rating' => 0,
                ];
            }
            
            $rating = (int) get_post_meta($review->ID, 'bw_rating', true);
            if ($rating > 0) {
                $by_platform[$platform]['count']++;
                $by_platform[$platform]['total_rating'] += $rating;
            }
        }
        
        // Start output
        $output = "# **{$business_name} — Client Reviews**\n\n";
        $output .= "**Overall Rating:** {$overall_rating} / 5.0 from {$total_count} Review" . ($total_count != 1 ? 's' : '') . "\n";
        
        // Platform breakdown
        foreach ($by_platform as $platform => $data) {
            if ($data['count'] > 0) {
                $platform_avg = number_format($data['total_rating'] / $data['count'], 1);
                $output .= "**{$platform}:** {$platform_avg} / 5.0 from {$data['count']} Review" . ($data['count'] != 1 ? 's' : '') . "\n";
            }
        }
        
        $output .= "\n**Last Updated:** {$last_update}\n\n";
        $output .= "---\n\n";
        
        // Output each review by platform
        foreach ($by_platform as $platform => $data) {
            $platform_reviews = array_filter($reviews, function($review) use ($platform) {
                $platforms = wp_get_post_terms($review->ID, 'bw_review_platform', ['fields' => 'names']);
                return !empty($platforms) && $platforms[0] === $platform;
            });
            
            if (empty($platform_reviews)) {
                continue;
            }
            
            $platform_count = count($platform_reviews);
            $output .= "## **Client Reviews ({$platform_count} Verified {$platform} Review" . ($platform_count != 1 ? 's' : '') . ")**\n\n";
            
            $counter = 1;
            foreach ($platform_reviews as $review) {
                // Get review metadata
                $customer_name = get_the_title($review->ID);
                $customer_detail = get_post_meta($review->ID, 'bw_customer_detail', true);
                $rating = (int) get_post_meta($review->ID, 'bw_rating', true);
                $date = get_post_meta($review->ID, 'bw_date', true);
                $content = get_the_content(null, false, $review->ID);
                $content = strip_tags($content);
                $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $content = trim($content);
                $success_outcome = get_post_meta($review->ID, 'bw_success_outcome', true);
                $source_url = get_post_meta($review->ID, 'bw_source_url', true);
                
                // Format date
                $formatted_date = $date ? date('F j, Y', strtotime($date)) : 'Date not specified';
                
                // Format rating
                $rating_display = $rating > 0 ? "{$rating}.0" : 'No rating';
                
                // Build review entry
                $output .= "### **{$counter}. {$customer_name}";
                if ($customer_detail) {
                    $output .= " ({$customer_detail})";
                }
                $output .= "**\n\n";
                
                $output .= "**Date:** {$formatted_date}\n";
                $output .= "**Rating:** {$rating_display}\n\n";
                
                if ($content) {
                    $output .= "\"{$content}\"\n\n";
                }
                
                if ($success_outcome) {
                    $output .= "**What this proves:** {$success_outcome}\n\n";
                }
                
                if ($source_url) {
                    $output .= "**Canonical Link:** [{$platform}]({$source_url})\n\n";
                }
                
                $output .= "---\n\n";
                $counter++;
            }
        }
        
        // Footer
        $output .= "\n---\n\n";
        $output .= "**Note:** This file is auto-generated from {$business_name}'s verified client reviews.\n";
        $output .= "For the latest reviews, visit: " . home_url('/') . "\n";
        
        echo $output;
        wp_reset_postdata();
        exit;
    }
}, 1);
