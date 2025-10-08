<?php
/**
 * Plugin Name: Brighter Tracking 
 * Description: SEO & GA 4 Tracking
 * Author: Brighter Websites
 * Version: 2.0.0
 */

if (!defined('ABSPATH')) exit;

// Load unified GA4 tracking script
function brighter_websites_ga4_tracking() {
    if (!is_admin()) {
        wp_enqueue_script(
            'brighter-ga4-tracking',
            plugin_dir_url(__FILE__) . 'js/brighter-ga4-tracking.js',
            array(),
            '2.0.0',
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'brighter_websites_ga4_tracking');

// Allow Labrika bot IPs (SEO crawler whitelist)
function brighter_allow_seo_bot_ips($allow, $ip) {
    $seo_bot_ips = array(
        '178.32.114.61', // Labrika bot 1
        '162.55.244.68'  // Labrika bot 2
    );
    
    if (in_array($ip, $seo_bot_ips)) {
        $allow = true;
    }
    
    return $allow;
}
add_filter('block_ips', 'brighter_allow_seo_bot_ips', 10, 2);