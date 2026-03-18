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
 * - Replace the default WordPress login logo with a custom logo (options, site icon, or business logo).
 * - Update the login header link and title to point to the site home and blog name.
 * - Inject a branded support section in the login footer linking to Brighter Websites support.
 *
 * Notes:
 * - Logo is resolved in order: brighter_login_logo -> Site Icon (Customizer) -> Business/Org Logo (Business Info) -> bw_site_icon.
 * - Base styles (body.login, .support) are always output so the support block is styled on all sites.
 * - Fallback logo for the support footer is included from the plugin's assets (`brighter-logo.png`).
 * - Uses inline CSS via `login_enqueue_scripts`, which ensures styles only load on the login screen.
 *
 * Why "only brighter works" on same branch: This module is loaded only if (1) brighter-core.php on that
 * server includes 'login-styling' in the $modules array, and (2) includes/login-styling.php exists.
 * If another site runs an older deploy (no 'login-styling' in the list or file missing), only
 * brighter-admin-branding runs?which now has a fallback that outputs the same CSS when this module
 * is not loaded, so all sites get the styling regardless of which files were deployed.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get the URL to use for the login screen logo.
 * Order: Manual option -> Site Icon (Customizer) -> Business/Org Logo (Business Info) -> Site Icon (Business Info).
 *
 * @return string Empty string if no logo source found.
 */
function brighter_get_login_logo_url() {
    $url = get_option( 'brighter_login_logo' );
    if ( $url && is_string( $url ) ) {
        return esc_url( $url );
    }
    if ( function_exists( 'get_site_icon_url' ) ) {
        $url = get_site_icon_url( 512 );
        if ( $url ) {
            return esc_url( $url );
        }
    }
    $url = get_option( 'bw_business_logo' );
    if ( $url && is_string( $url ) ) {
        return esc_url( $url );
    }
    $url = get_option( 'bw_site_icon' );
    if ( $url && is_string( $url ) ) {
        return esc_url( $url );
    }
    return '';
}

// Admin login logo and base login/support styling (always output so support block is styled on all sites)
// Enqueue our own stylesheet (no src) with inline CSS, dependent on 'login', so we load after login.css on all hosts/caches.
add_action( 'login_enqueue_scripts', 'brighter_login_logo', 20 );
function brighter_login_logo() {
    $logo_url = brighter_get_login_logo_url();

    $logo_css = '';
    if ( $logo_url !== '' ) {
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

    // Use our own stylesheet (no src) so our CSS always loads; depend on 'login' so we load after login.css and override it.
    $deps = wp_style_is( 'login', 'registered' ) ? [ 'login' ] : [];
    wp_register_style( 'brighter-login', false, $deps );
    wp_enqueue_style( 'brighter-login' );
    wp_add_inline_style( 'brighter-login', $css );
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
    $logo_url = ( defined( 'BRIGHTER_CORE_URL' ) ? BRIGHTER_CORE_URL : plugin_dir_url( dirname( __FILE__, 2 ) ) ) . 'assets/brighter-logo.png';

    $version = defined( 'BRIGHTER_CORE_VERSION' ) ? BRIGHTER_CORE_VERSION : '?';
    echo '<div class="support">
        <a href="https://brighterwebsites.com.au/support/" target="_blank">
            <img src="' . esc_url($logo_url) . '" alt="Support">
            <p class="support-text">Need help? ? Get Support (' . esc_html( $version ) . ')</p>
        </a>
    </div>';
}