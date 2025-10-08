<?php
/**
 * Brighter Tools: Login Styling
 *
 * File: login-styling.php
 * Purpose: Customises the WordPress login screen branding and adds a support link.
 *
 * Version: 4.0.0
 *
 * Responsibilities:
 * - Replace the default WordPress login logo with a custom logo set in plugin options.
 * - Update the login header link and title to point to the site home and blog name.
 * - Inject a branded support section in the login footer linking to Brighter Websites support.
 *
 * Notes:
 * - The logo URL is pulled from the `brighter_login_logo` option (set in Technical/Manual Links settings).
 * - Fallback logo for the support footer is included from the plugin’s assets (`brighter-logo.png`).
 * - Styling includes background, fonts, and responsive sizing for the login page logo.
 * - Uses inline CSS via `login_enqueue_scripts`, which ensures styles only load on the login screen.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Admin login logo styling from plugin options
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
// Login logo link and title
add_filter('login_headerurl', function() {
    return home_url();
});

add_filter('login_headertext', function() {
    return get_bloginfo('name');
});

add_action( 'login_footer', 'brighterwebsites_custom_login_support' );
function brighterwebsites_custom_login_support() {
    $logo_url = plugin_dir_url(__FILE__) . '../assets/brighter-logo.png';

    echo '<div class="support">
        <a href="https://brighterwebsites.com.au/support/" target="_blank">
            <img src="' . esc_url($logo_url) . '" alt="Support">
            <p class="support-text">Need help? â€“ Get Support</p>
        </a>
    </div>';
}