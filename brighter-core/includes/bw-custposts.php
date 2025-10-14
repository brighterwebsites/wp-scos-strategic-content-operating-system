<?php
/**
 * 
 * Brighter Tools: Cusomisations for Single and Archive Page Posts and CPTs
 * File:  
 * Purpose:  
 *  
 * Version: 4.0.0
 *
 * Responsibilities:
 * - Force self-referencing canonicals on paginated archives (fixes double page/2/page/2 issue)
  * - Register and manage a "Page Type" taxonomy for pages, including 
 *   dropdown filters in the admin list view.
 *   taxonomy-based organisation, and improved notification workflows.
 * -  
*
 * Notes:
 * - Page excerpts
 * - Page taxonomy (pagetype)
 *-Force self-referencing canonicals on paginated 
 */

if (!defined('ABSPATH')) exit;


// Force self-referencing canonicals on paginated archives (fixes double page/2/page/2 issue)
add_action('wp_head', function() {
    if ( is_paged() && ! is_singular() ) {
        global $wp;

        // Build canonical from current request (already includes /page/2/)
        $canonical = home_url( user_trailingslashit( $wp->request ) );

        echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
    }
}, 99);


// ==========================
// Page Excerpts
// ==========================
add_action('init', function() {
    add_post_type_support('page', 'excerpt');
});

// ==========================
// Page Taxonomy (pagetype)
// ==========================
add_filter('register_taxonomy_args', function($args, $taxonomy) {
    if ($taxonomy !== 'pagetype') return $args;

    $args['public']              = false;
    $args['publicly_queryable']  = false;
    $args['rewrite']             = false;
    $args['show_ui']             = true;
    $args['show_in_menu']        = true;
    $args['show_in_nav_menus']   = false;
    $args['show_admin_column']   = true;
    $args['show_in_quick_edit']  = true;
    $args['show_tagcloud']       = false;
    $args['show_in_rest']        = true;
    $args['hierarchical']        = true;
    $args['default_term']        = array('name' => 'General');
    
    $args['capabilities'] = array(
        'manage_terms' => 'manage_options',
        'edit_terms'   => 'manage_options',
        'delete_terms' => 'manage_options',
        'assign_terms' => 'edit_pages',
    );

    $args['labels'] = array(
        'name'          => esc_html__('Page Types', 'brighterwebsites'),
        'singular_name' => esc_html__('Page Type', 'brighterwebsites'),
        'menu_name'     => esc_html__('Page Types', 'brighterwebsites'),
        'all_items'     => esc_html__('All Page Types', 'brighterwebsites'),
        'edit_item'     => esc_html__('Edit Page Type', 'brighterwebsites'),
        'view_item'     => esc_html__('View Page Type', 'brighterwebsites'),
        'add_new_item'  => esc_html__('Add new Page Type', 'brighterwebsites'),
        'new_item_name' => esc_html__('New Page Type name', 'brighterwebsites'),
        'search_items'  => esc_html__('Search Page Types', 'brighterwebsites'),
        'not_found'     => esc_html__('No Page Types found', 'brighterwebsites'),
    );

    return $args;
}, 10, 2);

add_action('init', function() {
    if (!taxonomy_exists('pagetype')) {
        register_taxonomy('pagetype', array('page'), array());
    } else {
        register_taxonomy_for_object_type('pagetype', 'page');
    }
}, 40);

// Admin-only functionality
if (is_admin()) {
    // Filter dropdown
    add_action('restrict_manage_posts', function() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (empty($screen) || $screen->post_type !== 'page' || !taxonomy_exists('pagetype')) return;

        $selected = isset($_GET['pagetype']) ? sanitize_text_field($_GET['pagetype']) : '';
        $info_taxonomy = get_taxonomy('pagetype');

        wp_dropdown_categories(array(
            'show_option_all' => sprintf(esc_html__('Show all %s', 'brighterwebsites'), $info_taxonomy->label),
            'taxonomy'        => 'pagetype',
            'name'            => 'pagetype',
            'orderby'         => 'name',
            'selected'        => $selected,
            'show_count'      => true,
            'hide_empty'      => false,
            'hierarchical'    => true,
            'value_field'     => 'term_id',
        ));
    });

    // Convert term_id to slug
    add_filter('parse_query', function($query) {
        if (!is_admin() || !function_exists('get_current_screen')) return;
        $screen = get_current_screen();
        if (empty($screen) || $screen->base !== 'edit' || $screen->post_type !== 'page') return;

        if (isset($query->query_vars['pagetype']) && is_numeric($query->query_vars['pagetype']) && intval($query->query_vars['pagetype']) > 0) {
            $term = get_term_by('id', intval($query->query_vars['pagetype']), 'pagetype');
            if ($term && !is_wp_error($term)) {
                $query->query_vars['pagetype'] = $term->slug;
            }
        }
    });

    // Admin column
    add_filter('manage_pages_columns', function($cols) {
        $cols['pagetype'] = esc_html__('Page Type', 'brighterwebsites');
        return $cols;
    });
    
    add_action('manage_pages_custom_column', function($col, $post_id) {
        if ($col !== 'pagetype') return;
        $terms = get_the_terms($post_id, 'pagetype');
        if (is_wp_error($terms) || empty($terms)) {
            echo '—';
            return;
        }
        echo esc_html(join(', ', wp_list_pluck($terms, 'name')));
    }, 10, 2);

    // Default term on new pages
    add_action('save_post_page', function($post_id, $post, $update) {
        if ($update || wp_is_post_revision($post_id)) return;
        if (!has_term('', 'pagetype', $post_id)) {
            $term = get_term_by('name', 'General', 'pagetype');
            if ($term && !is_wp_error($term)) {
                wp_set_object_terms($post_id, array((int) $term->term_id), 'pagetype', false);
            }
        }
    }, 10, 3);
}

// Block front-end taxonomy archive
add_action('template_redirect', function() {
    if (is_tax('pagetype')) {
        wp_redirect(home_url(), 301);
        exit;
    }
});

// Add body class for CSS targeting
add_filter('body_class', function($classes) {
    if (is_page()) {
        $terms = get_the_terms(get_the_ID(), 'pagetype');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $t) {
                $classes[] = 'pagetype-' . sanitize_html_class($t->slug);
            }
        }
    }
    return $classes;
});
