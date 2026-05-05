<?php
/**
 * Brighter Tools: Settings
 *
 * File: brighter-settings.php
 * Purpose: Provides admin-side settings for Brighter Support, including
 * links to manuals, ranking tools, login branding, and API token control.
 *
 * Version: 4.0.0
 *
 * Responsibilities:
 * - Enqueue custom admin styles on Brighter Support admin pages only.
 * - Register and manage support settings (manual links, ranking links,
 *   login logo, and API token).
 * - Render input fields for editing URLs and generating a secure API token.
 *
 * Notes:
 * - Styles are only loaded on the `brighter_support` admin page
 *   (avoids polluting other admin screens).
 * - The `brc_token` option is used for REST API authentication via
 *   the `X-Brighter-Token` header. The “Generate” button creates
 *   a new 32-character random token. (not currently in use - was preparing for integration with custom GPT)
 * - Manual/Quick Links and Ranks Pro links allow dynamic site-specific
 *   references to training material and ranking tools.
 * - `brighter_login_logo` option sets a custom login page logo,
 *   replacing the default WordPress branding.
 */

if ( ! defined('ABSPATH') ) exit;

/**
 * Enqueue styles (optional, if you want separate styling for settings)
 */

add_action('admin_enqueue_scripts', function($hook) {

    // Only load our CSS on Brighter Support + AI Tracker pages
    $allowed_pages = [
        'toplevel_page_brighter_support',   // MU plugin support page
           ];

    if (!in_array($hook, $allowed_pages)) {
        return; // ? stop loading CSS everywhere else
    }

    wp_enqueue_style(
        'brighter-admin',
        plugin_dir_url(__FILE__) . 'css/admin-support.css',
        [],
        '1.0.0'
    );
});



/**
 * Register settings for Manual Links + API Token
 */
add_action('admin_init', function() {

    register_setting('brighter_support_settings', 'brighter_login_logo');
    register_setting('brighter_support_settings', 'brc_token'); // ðŸ‘ˆ token saved here

    add_settings_section(
        'manual_links_section',
        'Login branding & API token',
        function () {
            echo '<p>' . esc_html__( 'Manual and ranking URLs are configured in Site Essentials ? Support ? Support settings.', 'brighterwebsites' ) . '</p>';
        },
        'brighter_support_page'
    );

    // ðŸ”’ Login Page Logo
    add_settings_field('brighter_login_logo', 'Login Page Logo URL', function() {
        echo '<input type="url" name="brighter_login_logo" value="' . esc_url(get_option('brighter_login_logo')) . '" class="regular-text">';
        echo '<p class="description">Paste the URL of the image you want to show on the login page.</p>';
    }, 'brighter_support_page', 'manual_links_section');

    // ðŸ”‘ API Token (with Generate button)
    add_settings_field('brc_token', 'Brighter API Token', function() {
        $val = esc_attr(get_option('brc_token'));
        $new_token = wp_generate_password(32, false, false); // 32-char random, letters/numbers only

        echo '<input type="text" class="regular-text" id="brc_token" name="brc_token" value="' . $val . '" />';
        echo '<p class="description">Used in REST API requests as <code>X-Brighter-Token</code>.</p>';
        echo '<p><button type="button" class="button" onclick="document.getElementById(\'brc_token\').value=\'' . $new_token . '\'">Generate New Token</button></p>';
    }, 'brighter_support_page', 'manual_links_section');
});
