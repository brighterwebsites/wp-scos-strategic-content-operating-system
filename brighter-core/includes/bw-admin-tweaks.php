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
            <a href="https://brighterwebsites.com.au/support" target="_blank" rel="noopener">💬 Support</a>
            <a href="<?php echo esc_url(admin_url('edit.php')); ?>">📊 Dashboard</a>

<a href="#" class="gs-purge-cache">🔄 Purge Cache</a>
            <?php if ($post && $post->ID): ?>
                <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">✏️ Edit This Page</a>
            <?php endif; ?>
        </div>
        <?php
    }
});

/**
 * Redirect all users to Brighter Support page on login
 * Compatible with WPGhost redirect override
 */
add_filter( 'login_redirect', 'bw_redirect_to_support_page', 100, 3 );
function bw_redirect_to_support_page( $redirect_to, $request, $user ) {
    // Only redirect on successful login (user object exists)
    if ( isset( $user->ID ) ) {
        // Set a transient to show the notice (expires in 60 seconds)
        set_transient( 'bw_backup_reminder_' . $user->ID, true, 60 );
        
        // Redirect to Brighter Support page
        return admin_url( 'admin.php?page=brighter_support&tab=support' );
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