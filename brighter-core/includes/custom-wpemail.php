<?php
/**
 * Brighter Tools: Custom WP Email
 *
 * File: custom-wpemail.php
 * Purpose: Customises WordPress email behaviour for system notifications, 
 * password resets, new user registrations, and admin alerts, while also 
 * adding a toggle to disable comments on media attachments.
 *
 * Version: 4.0.0
 *
 * Responsibilities:
 * - Standardise sender details for all wp_mail messages (from name and from address).
 * - Customise system emails: comment moderation, password resets, new user notifications, 
 *   and core update results.
 * - Provide admin setting to disable comments on media attachments.
 *
 * Notes:
 * - Uses site title as `wp_mail_from_name` and generates `no-reply@domain` dynamically.
 * - Comment moderation emails include approve/trash quick links and improved subject lines.
 * - Password reset and new user emails use friendlier subject and body templates.
 * - Prevents WP from emailing admin on password changes and on successful core updates 
 *   (only sends on failures).
 * - Adds an admin setting (`brighter_disable_media_comments`) with a checkbox toggle for 
 *   disabling comments on media attachments. Defaults to ON.
 * - This file deals only with email behaviour and comment toggling — no LS quota updates needed here.
 */

defined('ABSPATH') || exit;

/** Sender name and address for all wp_mail */
add_filter('wp_mail_from_name', function($name) {
    return get_bloginfo('name'); // site title as sender name
});

add_filter('wp_mail_from', function($email) {
    $domain = preg_replace('#^www\.#','', parse_url(home_url(), PHP_URL_HOST));
    return 'no-reply@' . $domain;
});

/** Comment moderation: subject and message */
add_filter('comment_moderation_subject', function($subj, $comment_id){
  return 'Comment pending review on ' . wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
}, 10, 2);

add_filter('comment_moderation_text', function($msg, $comment_id){
  $c = get_comment($comment_id);
  $post = get_post($c->comment_post_ID);
  $approve_url = admin_url("comment.php?action=approve&c={$comment_id}");
  $trash_url   = admin_url("comment.php?action=trash&c={$comment_id}");
  return sprintf(
    "New comment awaiting moderation on \"%s\"\n\nAuthor: %s\nEmail: %s\nURL: %s\nIP: %s\n\nContent:\n%s\n\nApprove: %s\nTrash: %s\n",
    $post->post_title,
    $c->comment_author,
    $c->comment_author_email,
    $c->comment_author_url ?: '—',
    $c->comment_author_IP,
    $c->comment_content,
    $approve_url,
    $trash_url
  );
}, 10, 2);



/** Password reset emails */
add_filter('retrieve_password_title', function($title){ return 'Reset your password at ' . get_bloginfo('name'); });
add_filter('retrieve_password_message', function($message, $key, $user_login, $user_data){
  $url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');
  return "Hi {$user_login},\n\nUse the link below to reset your password:\n{$url}\n\nIf you did not request this, you can ignore this email.\n";
}, 10, 4);



/** New user notifications (to the user) */
add_filter('wp_new_user_notification_email', function($wp_new_user_notification_email, $user, $blogname){
  $wp_new_user_notification_email['subject'] = "Welcome to {$blogname}";
  $wp_new_user_notification_email['message'] = "Hi {$user->user_login},\n\nYour account has been created at {$blogname}.\n";
  return $wp_new_user_notification_email;
}, 10, 3);


/** Optional: stop WordPress emailing the admin on password changes */
add_filter('wp_password_change_notification', '__return_false');


/** Core update noise control */
add_filter('auto_core_update_send_email', function($send, $type, $core_update, $result){
  if ($type === 'success') return false; // only email on failures
  return $send;
}, 10, 4);


/**
 * ================================================================
 * Comment Control
 * Enforce Disable Media Comments Setting
 * Default: ON (comments disabled)
 * Related: Toggle registration lives in technical-settings.php
 * -----------------------------------------------------------------
 */

// Enforce "Disable Media Comments" setting
add_filter('comments_open', function($open, $post_id) {
    $disable = get_option('brighter_disable_media_comments', true);
    if ($disable) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'attachment') {
            return false;
        }
    }
    return $open;
}, 10, 2);

