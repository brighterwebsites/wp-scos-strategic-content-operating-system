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
 * - Admin bar logo replacement (backend only)
 * - Login page styling, logo, and support link are in the login-styling module.
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
// Login page fallback (only when login-styling module is not loaded, e.g. old deploy or missing file)
// When login-styling.php is loaded it owns this; we no-op so styles apply on all sites regardless of deploy order.
// ==========================
add_action('login_enqueue_scripts', 'brighter_admin_fallback_login_logo', 10);
add_filter('login_headerurl', 'brighter_admin_fallback_login_headerurl', 10);
add_filter('login_headertext', 'brighter_admin_fallback_login_headertext', 10);
add_action('login_footer', 'brighter_admin_fallback_login_support', 10);

function brighter_admin_fallback_login_logo() {
    if (function_exists('brighter_get_login_logo_url')) {
        return;
    }
    $logo_url = _brighter_fallback_login_logo_url();
    $logo_css = '';
    if ($logo_url !== '') {
        $logo_css = "
.login h1 a {
    background-image: url('" . $logo_url . "') !important;
    background-size: contain !important;
    width: 180px !important;
    height: 62px !important;
}";
    }
    $css = "body.login {
    background-color: #ffffff !important;
    font-family: \"Poppins\", sans-serif;
    background-position: center;
    background-size: cover;
    background-repeat: no-repeat;
}
" . $logo_css . "
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
}";
    $deps = wp_style_is('login', 'registered') ? ['login'] : [];
    wp_register_style('brighter-login', false, $deps);
    wp_enqueue_style('brighter-login');
    wp_add_inline_style('brighter-login', $css);
}

function brighter_admin_fallback_login_headerurl() {
    return home_url();
}

function brighter_admin_fallback_login_headertext() {
    return get_bloginfo('name');
}

function brighter_admin_fallback_login_support() {
    if (function_exists('brighter_get_login_logo_url')) {
        return;
    }
    $logo_url = (defined('BRIGHTER_CORE_URL') ? BRIGHTER_CORE_URL : plugin_dir_url(dirname(__FILE__, 2))) . 'assets/brighter-logo.png';
    $version = defined('BRIGHTER_CORE_VERSION') ? BRIGHTER_CORE_VERSION : '?';
    echo '<div class="support">
        <a href="https://brighterwebsites.com.au/support/" target="_blank">
            <img src="' . esc_url($logo_url) . '" alt="Support">
            <p class="support-text">Need help? – Get Support (' . esc_html($version) . ')</p>
        </a>
    </div>';
}

/**
 * Mirror of brighter_get_login_logo_url() for when login-styling module is not loaded.
 */
function _brighter_fallback_login_logo_url() {
    $url = get_option('brighter_login_logo');
    if ($url && is_string($url)) {
        return esc_url($url);
    }
    if (function_exists('get_site_icon_url')) {
        $url = get_site_icon_url(512);
        if ($url) {
            return esc_url($url);
        }
    }
    $url = get_option('bw_business_logo');
    if ($url && is_string($url)) {
        return esc_url($url);
    }
    $url = get_option('bw_site_icon');
    if ($url && is_string($url)) {
        return esc_url($url);
    }
    return '';
}
