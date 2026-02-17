<?php
/**
 * Brighter Tools: Frontend Features
 *
 * File: brighter-frontend.php
 * Version: 4.3.0
 *
 * Purpose: Frontend-only features for client sites including shortcodes,
 * branding elements, and design credits.
 *
 * Responsibilities:
 * - [brighter_credit] shortcode for footer credits
 * - Design credit meta tags (designer, web_author, generator)
 * - Auto-generated humans.txt file
 *
 * Changelog:
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
