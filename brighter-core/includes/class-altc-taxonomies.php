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
        // Priority 20: run after Site Essentials loads enabled modules at init:5
        // (defines SCOS_CA_ACTIVE) so show_ui/meta_box_cb reflect the new CA module.
        add_action('init', [__CLASS__, 'register_taxonomies'], 20);
        add_action('init', [__CLASS__, 'register_post_meta']);
        add_action('init', [__CLASS__, 'register_term_meta']);
        
        // Add sameAs URL field to topic taxonomy forms
        add_action('altc_topic_add_form_fields', [__CLASS__, 'add_topic_sameas_field']);
        add_action('altc_topic_edit_form_fields', [__CLASS__, 'edit_topic_sameas_field']);
        add_action('created_altc_topic', [__CLASS__, 'save_topic_sameas_field']);
        add_action('edited_altc_topic', [__CLASS__, 'save_topic_sameas_field']);
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
        $exclude = [
            'attachment', 
            'nav_menu_item', 
            'wp_block', 
            'wp_template', 
            'wp_template_part', 
            'wp_navigation',
            // WooCommerce post types
            'product',
            'product_variation',
            'shop_order',
            'shop_coupon',
            'shop_webhook',
        ];

        return array_values(array_diff($post_types, $exclude));
    }

    /**
     * Register ALTC taxonomies
     */
    public static function register_taxonomies() {
        $post_types = self::get_supported_post_types();

        // Keep legacy taxonomies registered for DB/query compat, but hide admin UI when
        // Content Architecture is active OR Site Essentials is present (legacy sidebars
        // are redundant; enable the CA module to edit cluster/topic via Content Architecture).
        $ui_active = ! defined( 'SCOS_CA_ACTIVE' ) && ! defined( 'SITE_ESSENTIALS_VERSION' );

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
            'show_ui'           => $ui_active,
            'show_admin_column' => false,
            'show_in_nav_menus' => $ui_active,
            'show_in_rest'      => $ui_active,
            'show_tagcloud'     => false,
            'meta_box_cb'       => false, // Managed by custom meta box, not default WP box.
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
            'show_ui'           => $ui_active,
            'show_admin_column' => false,
            'show_in_nav_menus' => false,
            'show_in_rest'      => $ui_active,
            'show_tagcloud'     => false,
            'meta_box_cb'       => false, // Managed by custom meta box, not default WP box.
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

        // sameAs URL for topic (used in schema)
        register_term_meta('altc_topic', 'topic_sameas_url', [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'esc_url_raw',
            'show_in_rest'      => true,
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

    /**
     * Add sameAs URL field to topic add form
     */
    public static function add_topic_sameas_field() {
        ?>
        <div class="form-field">
            <label for="topic_sameas_url"><?php esc_html_e('sameAs URL', 'brighterwebsites'); ?></label>
            <input type="url" name="topic_sameas_url" id="topic_sameas_url" value="" />
            <p class="description"><?php esc_html_e('External authoritative URL for this topic (used in schema sameAs). Example: https://www.wikidata.org/wiki/Q12345', 'brighterwebsites'); ?></p>
        </div>
        <?php
    }

    /**
     * Add sameAs URL field to topic edit form
     */
    public static function edit_topic_sameas_field($term) {
        $sameas_url = get_term_meta($term->term_id, 'topic_sameas_url', true);
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="topic_sameas_url"><?php esc_html_e('sameAs URL', 'brighterwebsites'); ?></label>
            </th>
            <td>
                <input type="url" name="topic_sameas_url" id="topic_sameas_url" value="<?php echo esc_attr($sameas_url); ?>" class="regular-text" />
                <p class="description"><?php esc_html_e('External authoritative URL for this topic (used in schema sameAs). Example: https://www.wikidata.org/wiki/Q12345', 'brighterwebsites'); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save sameAs URL field
     */
    public static function save_topic_sameas_field($term_id) {
        if (!isset($_POST['topic_sameas_url'])) {
            return;
        }

        // Check permissions
        if (!current_user_can('manage_categories')) {
            return;
        }

        $sameas_url = esc_url_raw($_POST['topic_sameas_url']);

        if (!empty($sameas_url)) {
            update_term_meta($term_id, 'topic_sameas_url', $sameas_url);
        } else {
            delete_term_meta($term_id, 'topic_sameas_url');
        }
    }
}

// Initialize
BW_ALTC_Taxonomies::init();
