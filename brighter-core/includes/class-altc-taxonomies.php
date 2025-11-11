<?php
/**
 * ALTC Taxonomies
 *
 * File: class-altc-taxonomies.php
 * Version: 1.0.0
 *
 * Responsibilities:
 * - Register ALTC Strategic Lens taxonomy
 * - Register ALTC Topic taxonomy
 * - Register term meta for topics (topic_serves_altc)
 * - Register new post meta fields (bw_primary_altc_id, bw_primary_topic_id, bw_cont_maturity)
 */

if (!defined('ABSPATH')) exit;

class BW_ALTC_Taxonomies {

    /**
     * Initialize the taxonomies
     */
    public static function init() {
        add_action('init', [__CLASS__, 'register_taxonomies']);
        add_action('init', [__CLASS__, 'register_post_meta']);
        add_action('init', [__CLASS__, 'register_term_meta']);
    }

    /**
     * Get all post types that should support ALTC
     */
    public static function get_supported_post_types() {
        // Get all public post types including custom post types
        $post_types = get_post_types([
            'public' => true,
            'show_ui' => true
        ], 'names');

        // Exclude post types that shouldn't have ALTC
        $exclude = ['attachment', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'faq'];

        return array_values(array_diff($post_types, $exclude));
    }

    /**
     * Register ALTC taxonomies
     */
    public static function register_taxonomies() {
        $post_types = self::get_supported_post_types();

        // Register ALTC Strategic Lens taxonomy
        register_taxonomy('altc_strategic_lens', $post_types, [
            'labels' => [
                'name'              => __('ALTC Strategic Lenses', 'brighterwebsites'),
                'singular_name'     => __('ALTC Strategic Lens', 'brighterwebsites'),
                'search_items'      => __('Search ALTC Lenses', 'brighterwebsites'),
                'all_items'         => __('All ALTC Lenses', 'brighterwebsites'),
                'parent_item'       => __('Parent ALTC Lens', 'brighterwebsites'),
                'parent_item_colon' => __('Parent ALTC Lens:', 'brighterwebsites'),
                'edit_item'         => __('Edit ALTC Lens', 'brighterwebsites'),
                'update_item'       => __('Update ALTC Lens', 'brighterwebsites'),
                'add_new_item'      => __('Add New ALTC Lens', 'brighterwebsites'),
                'new_item_name'     => __('New ALTC Lens Name', 'brighterwebsites'),
                'menu_name'         => __('ALTC Lenses', 'brighterwebsites'),
            ],
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => false,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'show_tagcloud'     => false,
            'capabilities'      => [
                'manage_terms' => 'manage_categories',
                'edit_terms'   => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts',
            ],
        ]);

        // Register ALTC Topic taxonomy
        register_taxonomy('altc_topic', $post_types, [
            'labels' => [
                'name'              => __('ALTC Topics', 'brighterwebsites'),
                'singular_name'     => __('ALTC Topic', 'brighterwebsites'),
                'search_items'      => __('Search Topics', 'brighterwebsites'),
                'all_items'         => __('All Topics', 'brighterwebsites'),
                'parent_item'       => __('Parent Topic', 'brighterwebsites'),
                'parent_item_colon' => __('Parent Topic:', 'brighterwebsites'),
                'edit_item'         => __('Edit Topic', 'brighterwebsites'),
                'update_item'       => __('Update Topic', 'brighterwebsites'),
                'add_new_item'      => __('Add New Topic', 'brighterwebsites'),
                'new_item_name'     => __('New Topic Name', 'brighterwebsites'),
                'menu_name'         => __('ALTC Topics', 'brighterwebsites'),
            ],
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => false,
            'show_in_nav_menus' => false,
            'show_in_rest'      => true,
            'show_tagcloud'     => false,
            'capabilities'      => [
                'manage_terms' => 'manage_categories',
                'edit_terms'   => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts',
            ],
        ]);
    }

    /**
     * Register post meta fields for ALTC
     */
    public static function register_post_meta() {
        // Primary ALTC ID (term ID of primary ALTC strategic lens)
        register_post_meta('', 'bw_primary_altc_id', [
            'type'              => 'integer',
            'single'            => true,
            'sanitize_callback' => 'absint',
            'show_in_rest'      => false,
            'auth_callback'     => function() { return current_user_can('edit_posts'); },
        ]);

        // Primary Topic ID (term ID of primary topic)
        register_post_meta('', 'bw_primary_topic_id', [
            'type'              => 'integer',
            'single'            => true,
            'sanitize_callback' => 'absint',
            'show_in_rest'      => false,
            'auth_callback'     => function() { return current_user_can('edit_posts'); },
        ]);

        // Content Maturity level
        register_post_meta('', 'bw_cont_maturity', [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => false,
            'auth_callback'     => function() { return current_user_can('edit_posts'); },
        ]);
    }

    /**
     * Register term meta for topics
     */
    public static function register_term_meta() {
        // Array of ALTC term IDs this topic serves
        register_term_meta('altc_topic', 'topic_serves_altc', [
            'type'              => 'array',
            'single'            => true,
            'sanitize_callback' => [__CLASS__, 'sanitize_altc_array'],
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'integer',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Sanitize array of ALTC IDs
     */
    public static function sanitize_altc_array($value) {
        if (!is_array($value)) {
            return [];
        }

        return array_map('absint', array_filter($value));
    }

    /**
     * Get content maturity options
     */
    public static function get_maturity_options() {
        return [
            ''                  => 'Not Set',
            'entry'             => 'Entry',
            'learner'           => 'Learner',
            'professional'      => 'Professional',
            'expert'            => 'Expert',
            'thought_leader'    => 'Thought Leader',
            'industry_authority' => 'Industry Authority',
        ];
    }
}

// Initialize
BW_ALTC_Taxonomies::init();
