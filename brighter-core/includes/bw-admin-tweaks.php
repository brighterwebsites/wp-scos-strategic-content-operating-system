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
            <?php
            $support_href = 'https://brighterwebsites.com.au/support';
            if ( function_exists( 'scos_se_agency_get' ) ) {
                $base = rtrim( (string) scos_se_agency_get( 'url' ), '/' );
                if ( $base !== '' ) {
                    $support_href = preg_match( '#/support$#i', $base ) ? $base : trailingslashit( $base ) . 'support';
                }
            }
            ?>
            <a href="<?php echo esc_url( $support_href ); ?>" target="_blank" rel="noopener">💬 Support</a>
            <a href="<?php echo esc_url(admin_url('edit.php')); ?>">📊 Dashboard</a>

            <?php if ($post && $post->ID):
                $post_id         = $post->ID;
                $wp_edit_url     = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
                $seo_url         = $wp_edit_url . '#scos_seo_meta';
                $bd_data         = get_post_meta( $post_id, '_breakdance_data', true );
                $edit_page_url   = ! empty( $bd_data )
                    ? home_url( '/?breakdance=builder&id=' . $post_id )
                    : $wp_edit_url;
            ?>
                <a href="<?php echo esc_url( $seo_url ); ?>">🔍 Edit SEO</a>
                <a href="<?php echo esc_url( $edit_page_url ); ?>">✏️ Edit Page</a>
            <?php endif; ?>
        </div>
        <?php
    }
});

/**
 * Redirect all users to Brighter Support page on login
 * Compatible with WPGhost redirect override
 */
// SCOS-SUPPORT-PASS2 — login redirect wired to Agency Access tab options
add_filter( 'login_redirect', 'bw_redirect_to_support_page', 100, 3 );
function bw_redirect_to_support_page( $redirect_to, $request, $user ) {
    // Only redirect on successful login (user object exists)
    if ( ! isset( $user->ID ) || ! isset( $user->roles ) ) {
        return $redirect_to;
    }

    // Set a transient to show the notice (expires in 60 seconds)
    set_transient( 'bw_backup_reminder_' . $user->ID, true, 60 );

    // SCOS-SUPPORT-PASS2 — removed hardcoded redirect, now controlled via Agency > Access tab
    $fallback = admin_url( 'admin.php?page=site-essentials-support' ); // SCOS-SUPPORT-PASS2 — updated fallback from brighter_support to site-essentials-support
    $roles    = (array) $user->roles;

    if ( in_array( 'administrator', $roles, true ) ) { // SCOS-SUPPORT-PASS2 — administrator redirect
        $url = get_option( 'se_agency_login_redirect_admin', '' );
        return $url ? admin_url( ltrim( $url, '/' ) ) : $fallback;
    }

    if ( in_array( 'shop_manager', $roles, true ) ) { // SCOS-SUPPORT-PASS2 — shop_manager redirect
        $url = get_option( 'se_agency_login_redirect_shop_manager', '' );
        return $url ? admin_url( ltrim( $url, '/' ) ) : $fallback;
    }

    if ( in_array( 'editor', $roles, true ) ) { // SCOS-SUPPORT-PASS2 — editor redirect
        $url = get_option( 'se_agency_login_redirect_editor', '' );
        return $url ? admin_url( ltrim( $url, '/' ) ) : $fallback;
    }

    return $redirect_to;
}

/**
 * Display backup reminder notice after login redirect should move this to agency settings
 */
add_action( 'admin_notices', 'bw_backup_reminder_notice' );
function bw_backup_reminder_notice() {
    $user_id = get_current_user_id();
    
    // Check if the transient exists for this user
    if ( get_transient( 'bw_backup_reminder_' . $user_id ) ) {
        // Delete the transient so it only shows once
        delete_transient( 'bw_backup_reminder_' . $user_id );
        
        $backup_url = admin_url( 'admin.php?page=WPvivid' );
        ?>
        <div class="notice notice-warning is-dismissible" style="border-left-width: 6px; padding: 20px 30px; margin: 20px 20px 20px 0;">
            <p style="font-size: 16px; line-height: 1.6; margin: 0;">
                <strong style="font-size: 18px;">💾 Making big changes today?</strong><br>
                <span style="font-size: 15px;">Take a manual backup first! <a href="<?php echo esc_url( $backup_url ); ?>" style="font-weight: 600; text-decoration: none;">Go to backup page →</a></span>
            </p>
        </div>        <?php
    }
}