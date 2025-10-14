<?php
/**
 * Brighter Tools: Custom Admin
 *
 * File: custom-admin.php
 * Purpose: Enhancements and modifications to the WordPress admin UI.
 *
 * Version: 4.1.0
 *
 * Changelog:
 * 4.1.0 - CLEANED: Removed optimization status (moved to bw-content-strategy.php)
 * 4.0.0 - Initial version
 *
 * Responsibilities:
 * - Custom admin bar logo
 * - Frontend admin bar replacement
 *
 * Notes:
 * - Part of the Brighter Support Tools for Client Sites MU plugin 
 * - Loaded automatically by /mu-plugins/brighter-core.php
 */

if (!defined('ABSPATH')) exit;

// ==========================
// Custom Admin Bar Logo
// ==========================
function brighterwebsites_admin_logo() {
    $logo_url = site_url('/wp-content/mu-plugins/brighter-core/assets/icon-white.png');
    ?>
    <style>
    #wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon:before {
        content: "" !important;
        background-image: url('<?php echo esc_url($logo_url); ?>') !important;
        background-size: contain !important;
        background-repeat: no-repeat !important;
        background-position: center center !important;
        width: 20px !important;
        height: 20px !important;
        display: inline-block !important;
    }
    #wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon {
        background: none !important;
    }
    </style>
    <?php
}
add_action('admin_head', 'brighterwebsites_admin_logo');
add_action('wp_head', 'brighterwebsites_admin_logo');

// ==========================
// Frontend Admin Bar Replacement
// ==========================
add_filter('show_admin_bar', '__return_false');

add_action('wp_footer', function() {
    if (current_user_can('edit_posts') && !is_admin()) {
        global $post;
        ?>
        <style>
            .gs-admin-bar-links {
                position: fixed;
                bottom: 20px;
                right: 20px;
                display: flex;
                gap: 10px;
                z-index: 9999;
            }
            .gs-admin-bar-links a {
                background-color: rgba(0, 0, 0, 0.8);
                color: #fff;
                padding: 5px 10px;
                border-radius: 999px;
                font-size: 14px;
                text-decoration: none;
                font-family: sans-serif;
                transition: background 0.3s ease;
            }
            .gs-admin-bar-links a:hover {
                background-color: #000;
            }
        </style>
         <div class="gs-admin-bar-links">
            <a href="https://brighterwebsites.com.au/support" target="_blank" rel="noopener">?? Support</a>
            <a href="<?php echo esc_url(admin_url('edit.php')); ?>">?? Dashboard</a>
 		
<a href="#" class="gs-purge-cache">?? Purge Cache</a>
            <?php if ($post && $post->ID): ?>
                <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">?? Edit This Page</a>
            <?php endif; ?>
        </div>
        <?php
    }
});

