<?php
/**
 * Brighter Tools: Reusable Helper Functions
 *
 * File: helpers.php
 * Purpose: Provides helper functions and admin enhancements such as 
 * content duplication, taxonomy management, custom email handling,
 * and editor capabilities.
 *
 * Version: 4.0.0
 *
 * Responsibilities:
 * - Add UI helpers: enable excerpts on pages, duplicate post/page action, 
 *   and admin success notices.
 * - Register and manage a "Page Type" taxonomy for pages, including 
 *   dropdown filters in the admin list view.
 * - Customise email behaviour: adjust comment moderation subject, 
 *   redirect user registration emails to business/admin email, and 
 *   allow editors access to Breakdance form submissions.
 *
 * Notes:
 * - Duplication preserves content, taxonomies, and meta fields 
 *   (excluding `_wp_old_slug`).
 * - Registers "Page Types" taxonomy twice — likely a duplicate block 
 *   that can be safely removed to avoid redundancy.
 * - Customises new user notification emails to route to the 
 *   Business Info email (with fallback to site admin email).
 * - Expands permissions for editors by lowering the Breakdance 
 *   submission capability to `edit_posts`.
 * - Useful for site admins needing quick content duplication, 
 *   taxonomy-based organisation, and improved notification workflows.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Enable excerpts for pages
add_action('init', function() {
    add_post_type_support('page', 'excerpt');
});

if (is_admin()) {
    // Add Duplicate action
    add_filter('post_row_actions', 'brighterwebsites_duplicate_post_link', 10, 2);
    add_filter('page_row_actions', 'brighterwebsites_duplicate_post_link', 10, 2);
    function brighterwebsites_duplicate_post_link($actions, $post) {
        if (current_user_can('edit_posts')) {
            $url = wp_nonce_url(
                add_query_arg([
                    'action' => 'brighterwebsites_duplicate_post_as_draft',
                    'post'   => $post->ID,
                ], 'admin.php'),
                basename(__FILE__),
                'duplicate_nonce'
            );
            $actions['duplicate'] = '<a href="' . esc_url($url) . '" title="Duplicate this item" rel="permalink">Duplicate</a>';
        }
        return $actions;
    }

    // Handle duplication
    add_action('admin_action_brighterwebsites_duplicate_post_as_draft', 'brighterwebsites_duplicate_post_as_draft');
    function brighterwebsites_duplicate_post_as_draft() {
        if (!isset($_GET['post']) || !isset($_GET['duplicate_nonce']) || !wp_verify_nonce($_GET['duplicate_nonce'], basename(__FILE__))) {
            wp_die('Invalid request.');
        }

        $post_id = absint($_GET['post']);
        $post = get_post($post_id);

        if (!$post) {
            wp_die('Original post not found.');
        }

        $args = [
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
            'post_author'    => get_current_user_id(),
            'post_content'   => $post->post_content,
            'post_excerpt'   => $post->post_excerpt,
            'post_name'      => $post->post_name,
            'post_parent'    => $post->post_parent,
            'post_password'  => $post->post_password,
            'post_status'    => 'draft',
            'post_title'     => $post->post_title,
            'post_type'      => $post->post_type,
            'to_ping'        => $post->to_ping,
            'menu_order'     => $post->menu_order,
        ];

        $new_post_id = wp_insert_post($args);

        // Copy taxonomies
        foreach (get_object_taxonomies($post->post_type) as $taxonomy) {
            $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'slugs']);
            wp_set_object_terms($new_post_id, $terms, $taxonomy, false);
        }

        // Copy meta
        foreach (get_post_meta($post_id) as $meta_key => $meta_values) {
            if ($meta_key === '_wp_old_slug') continue;
            foreach ($meta_values as $meta_value) {
                add_post_meta($new_post_id, $meta_key, maybe_unserialize($meta_value));
            }
        }

        wp_redirect(admin_url('edit.php?post_type=' . $post->post_type . '&saved=post_duplication_created'));
        exit;
    }

    // Admin notice
    add_action('admin_notices', function() {
        if (isset($_GET['saved']) && $_GET['saved'] === 'post_duplication_created') {
            echo '<div class="notice notice-success is-dismissible"><p>Post copy created successfully.</p></div>';
        }
    });
}


add_action('init', function() {
    $labels = [
        "name" => "Page Types",
        "singular_name" => "Page Type",
    ];
    register_taxonomy("pagetype", ["page"], [
        "label" => "Page Types",
        "labels" => $labels,
        "hierarchical" => true,
        "public" => true,
        "show_ui" => true,
        "show_in_menu" => true,
        "show_admin_column" => true,
        "show_in_rest" => true,
        "rewrite" => ['slug' => 'pagetype'],
        "default_term" => ['name' => 'General'],
    ]);
});

add_action('init', function() {
    $labels = [
        "name" => "Page Types",
        "singular_name" => "Page Type",
    ];
    register_taxonomy("pagetype", ["page"], [
        "label" => "Page Types",
        "labels" => $labels,
        "hierarchical" => true,
        "public" => true,
        "show_ui" => true,
        "show_in_menu" => true,
        "show_admin_column" => true,
        "show_in_rest" => true,
        "rewrite" => ['slug' => 'pagetype'],
        "default_term" => ['name' => 'General'],
    ]);
});


// Add taxonomy filter dropdown
add_action('restrict_manage_posts', function() {
    global $typenow;
    if ($typenow === 'page') {
        wp_dropdown_categories([
            'show_option_all' => 'Show all Page Types',
            'taxonomy'        => 'pagetype',
            'name'            => 'pagetype',
            'orderby'         => 'name',
            'selected'        => $_GET['pagetype'] ?? '',
            'show_count'      => true,
            'hide_empty'      => false,
        ]);
    }
});

// Make the filter work
add_filter('parse_query', function($query) {
    global $pagenow;
    if ($pagenow === 'edit.php' && isset($_GET['pagetype']) && is_numeric($_GET['pagetype'])) {
        $term = get_term_by('id', $_GET['pagetype'], 'pagetype');
        if ($term) {
            $query->query_vars['pagetype'] = $term->slug;
        }
    }
});


// Custom subject for comment moderation emails
add_filter('comment_moderation_subject', function($email_subject, $comment_id) {
    return 'New Comment Pending Moderation on ' . get_bloginfo('name');
}, 10, 2);

// Customise admin notification email for new user registrations
add_filter('wp_new_user_notification_email_admin', 'custom_new_user_admin_notification', 10, 3);

function custom_new_user_admin_notification( $email, $user, $blogname ) {
    // Try to get custom business email from Business Info
    $custom_admin_email = get_option('email');

    // Use site admin email as fallback
    if (empty($custom_admin_email) || !is_email($custom_admin_email)) {
        $custom_admin_email = get_option('admin_email');
    }

    $username   = $user->user_login;
    $user_email = $user->user_email;

    $email['to']      = $custom_admin_email;
    $email['subject'] = '[' . $blogname . '] New Customer/User Registration';
    $email['headers'] = ['Content-Type: text/html; charset=UTF-8'];
    $email['message'] = '
        <p>There is a new user/customer registration on your site <strong>' . esc_html($blogname) . '</strong>:</p>
        <ul>
            <li><strong>Username:</strong> ' . esc_html($username) . '</li>
            <li><strong>Email:</strong> ' . esc_html($user_email) . '</li>
        </ul>
    ';

    return $email;
}

// allow Editors to access Breakdance form submissions
add_filter("breakdance_form_submission_capability",function() 
{
    return "edit_posts";
});

// Kill any Google Fonts <link> tag in the head, regardless of source
add_action('template_redirect', function () {
    ob_start(function ($html) {
        return preg_replace('/<link[^>]+fonts\.googleapis\.com[^>]+>/', '', $html);
    });
});

add_action('rest_api_init', function() {
    do_action('litespeed_rest_api_init');
}, 20);

