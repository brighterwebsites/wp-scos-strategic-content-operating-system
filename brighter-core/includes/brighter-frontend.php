<?php
/**
 * Brighter Tools: Frontend Features
 *
 * File: brighter-frontend.php
 * Version: 4.2.0
 *
 * Purpose: Frontend-only features for client sites including shortcodes,
 * branding elements, and SEO schema.
 *
 * Responsibilities:
 * - [brighter_credit] shortcode for footer credits
 * - Footer branding HTML comment
 * - JSON-LD schema markup for SEO
 *
 * Notes:
 * - Part of the Brighter Support Tools for Client Sites MU plugin
 * - Loaded automatically on frontend only for optimal performance
 * - Separated from admin-only features in brighter-support.php
 */

if (!defined('ABSPATH')) exit;

/**
 * Design Credit Hook - JSON-LD Schema
 * SECURITY: All output properly escaped
 */
add_action('wp_head', function () {
    if (is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    $site_name = get_bloginfo('name');
    $site_url = home_url('/');

    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'WebSite',
        'name'       => $site_name,
        'url'        => $site_url,
        'publisher'  => [
            '@type' => 'Organization',
            'name'  => 'Brighter Websites',
            'url'   => 'https://brighterwebsites.com.au',
        ],
    ];

    echo "\n<script type=\"application/ld+json\">" . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}, 20);

/**
 * Footer branding: comment only
 */
add_action('wp_footer', function () {
    if (is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }
    echo "\n<!-- Website built by Brighter Websites - https://brighterwebsites.com.au -->\n";
}, 99);

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
        'Proudly Built by <a href="%s" target="_blank" rel="noopener"><strong>BRIGHTER WEBSITES</strong></a>',
        esc_url($url)
    );
}
add_shortcode('brighter_credit', 'brighter_credit_shortcode');
