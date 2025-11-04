<?php
/**
 * Brighter Tools: Admin Branding
 *
 * File: brighter-admin-branding.php
 * Version: 4.2.0
 *
 * Purpose: Handles all admin area and login page branding for client sites.
 *
 * Responsibilities:
 * - Custom login page styling and logo
 * - Admin bar logo replacement (backend only)
 * - Login screen support link
 *
 * Notes:
 * - Part of the Brighter Support Tools for Client Sites MU plugin
 * - Loaded automatically by /mu-plugins/brighter-core/brighter-core.php
 * - Admin-only loading for performance
 */

if (!defined('ABSPATH')) exit;

// ==========================
// Custom Admin Bar Logo (Backend Only)
// ==========================
function brighter_admin_bar_logo() {
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
add_action('admin_head', 'brighter_admin_bar_logo');

// ==========================
// Login Page Styling
// ==========================
add_action('login_enqueue_scripts', 'brighter_login_logo');
function brighter_login_logo() {
    $logo_url = esc_url(get_option('brighter_login_logo'));
    if (!$logo_url) return;

    echo "
   <style>

    body.login {
        background-color:  #ffffff;
        font-family: \"Poppins\", sans-serif;
        background-position: center;
        background-size: cover;
        background-repeat: no-repeat;
    }

    .login h1 a {
        background-image: url('{$logo_url}') !important;
        background-size: contain !important;
        width: 180px !important;
        height: 62px !important;
    }

    .support {
        width: 280px !important;
        padding: 5px !important;
        margin: auto !important;
    }

    .support a {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #2271b1;
        text-decoration: none;
    }

    .support a img {
        width: 60px;
    }

    .support-text {
        color: #2271b1;
        text-decoration: underline;
        margin: 0;
    }
</style>
  ";
}

// ==========================
// Login Page Links
// ==========================
add_filter('login_headerurl', function() {
    return home_url();
});

add_filter('login_headertext', function() {
    return get_bloginfo('name');
});

// ==========================
// Login Footer Support Link
// ==========================
add_action('login_footer', 'brighter_login_support_link');
function brighter_login_support_link() {
    $logo_url = plugin_dir_url(__FILE__) . '../assets/brighter-logo.png';

    echo '<div class="support">
        <a href="https://brighterwebsites.com.au/support/" target="_blank">
            <img src="' . esc_url($logo_url) . '" alt="Support">
            <p class="support-text">Need help? – Get Support</p>
        </a>
    </div>';
}
