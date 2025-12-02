<?php
/**
 * Talking Points Manager
 *
 * Manages talking points custom post type for social amplification
 *
 * @package BrighterCore
 * @subpackage SocialAmplification
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class BW_Talking_Points {

    /**
     * Initialize hooks
     */
    public function init() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomy'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_bw_talking_point', array($this, 'save_meta_boxes'), 10, 2);
    }

    /**
     * Register talking points custom post type
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => __('Talking Points', 'brighterwebsites'),
            'singular_name'         => __('Talking Point', 'brighterwebsites'),
            'menu_name'             => __('Talking Points', 'brighterwebsites'),
            'add_new'               => __('Add New', 'brighterwebsites'),
            'add_new_item'          => __('Add New Talking Point', 'brighterwebsites'),
            'edit_item'             => __('Edit Talking Point', 'brighterwebsites'),
            'new_item'              => __('New Talking Point', 'brighterwebsites'),
            'view_item'             => __('View Talking Point', 'brighterwebsites'),
            'search_items'          => __('Search Talking Points', 'brighterwebsites'),
            'not_found'             => __('No talking points found', 'brighterwebsites'),
            'not_found_in_trash'    => __('No talking points found in trash', 'brighterwebsites'),
        );

        $args = array(
            'labels'                => $labels,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => 'brighter-support',
            'show_in_rest'          => true,
            'capability_type'       => 'post',
            'hierarchical'          => false,
            'supports'              => array('title', 'editor'),
            'menu_icon'             => 'dashicons-megaphone',
            'has_archive'           => false,
            'rewrite'               => false,
        );

        register_post_type('bw_talking_point', $args);
    }

    /**
     * Register content type taxonomy
     */
    public function register_taxonomy() {
        $labels = array(
            'name'              => __('Content Types', 'brighterwebsites'),
            'singular_name'     => __('Content Type', 'brighterwebsites'),
            'search_items'      => __('Search Content Types', 'brighterwebsites'),
            'all_items'         => __('All Content Types', 'brighterwebsites'),
            'edit_item'         => __('Edit Content Type', 'brighterwebsites'),
            'update_item'       => __('Update Content Type', 'brighterwebsites'),
            'add_new_item'      => __('Add New Content Type', 'brighterwebsites'),
            'new_item_name'     => __('New Content Type Name', 'brighterwebsites'),
            'menu_name'         => __('Content Types', 'brighterwebsites'),
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => false,
        );

        register_taxonomy('bw_content_type', array('bw_talking_point'), $args);
    }

    /**
     * Add meta boxes for talking point fields
     */
    public function add_meta_boxes() {
        add_meta_box(
            'bw_talking_point_details',
            __('Talking Point Details', 'brighterwebsites'),
            array($this, 'render_meta_box'),
            'bw_talking_point',
            'normal',
            'high'
        );
    }

    /**
     * Render meta box content
     */
    public function render_meta_box($post) {
        wp_nonce_field('bw_talking_point_meta', 'bw_talking_point_nonce');

        $context = get_post_meta($post->ID, '_bw_tp_context', true);
        $example = get_post_meta($post->ID, '_bw_tp_example', true);
        $cta_example = get_post_meta($post->ID, '_bw_tp_cta_example', true);
        $word_count_min = get_post_meta($post->ID, '_bw_tp_word_count_min', true) ?: 50;
        $word_count_max = get_post_meta($post->ID, '_bw_tp_word_count_max', true) ?: 130;
        ?>
        <style>
            .bw-tp-field { margin-bottom: 20px; }
            .bw-tp-field label { display: block; font-weight: 600; margin-bottom: 5px; }
            .bw-tp-field textarea { width: 100%; rows: 3; }
            .bw-tp-field input[type="text"] { width: 100%; }
            .bw-tp-field input[type="number"] { width: 100px; }
            .bw-tp-field .description { font-style: italic; color: #666; margin-top: 5px; }
        </style>

        <div class="bw-tp-field">
            <label for="bw_tp_context"><?php _e('Context', 'brighterwebsites'); ?></label>
            <textarea id="bw_tp_context" name="bw_tp_context" rows="3"><?php echo esc_textarea($context); ?></textarea>
            <p class="description"><?php _e('Guidance for what this talking point should cover', 'brighterwebsites'); ?></p>
        </div>

        <div class="bw-tp-field">
            <label for="bw_tp_example"><?php _e('Example Hooks', 'brighterwebsites'); ?></label>
            <textarea id="bw_tp_example" name="bw_tp_example" rows="3"><?php echo esc_textarea($example); ?></textarea>
            <p class="description"><?php _e('Example opening lines or angles to use', 'brighterwebsites'); ?></p>
        </div>

        <div class="bw-tp-field">
            <label for="bw_tp_cta_example"><?php _e('CTA Examples', 'brighterwebsites'); ?></label>
            <textarea id="bw_tp_cta_example" name="bw_tp_cta_example" rows="3"><?php echo esc_textarea($cta_example); ?></textarea>
            <p class="description"><?php _e('Example call-to-action phrases', 'brighterwebsites'); ?></p>
        </div>

        <div class="bw-tp-field">
            <label for="bw_tp_word_count_min"><?php _e('Word Count Range', 'brighterwebsites'); ?></label>
            <input type="number" id="bw_tp_word_count_min" name="bw_tp_word_count_min" value="<?php echo esc_attr($word_count_min); ?>" min="20" max="200" />
            to
            <input type="number" id="bw_tp_word_count_max" name="bw_tp_word_count_max" value="<?php echo esc_attr($word_count_max); ?>" min="20" max="200" />
            words
            <p class="description"><?php _e('Target word count range for posts using this talking point', 'brighterwebsites'); ?></p>
        </div>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_meta_boxes($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['bw_talking_point_nonce']) || !wp_verify_nonce($_POST['bw_talking_point_nonce'], 'bw_talking_point_meta')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save fields
        if (isset($_POST['bw_tp_context'])) {
            update_post_meta($post_id, '_bw_tp_context', sanitize_textarea_field($_POST['bw_tp_context']));
        }

        if (isset($_POST['bw_tp_example'])) {
            update_post_meta($post_id, '_bw_tp_example', sanitize_textarea_field($_POST['bw_tp_example']));
        }

        if (isset($_POST['bw_tp_cta_example'])) {
            update_post_meta($post_id, '_bw_tp_cta_example', sanitize_textarea_field($_POST['bw_tp_cta_example']));
        }

        if (isset($_POST['bw_tp_word_count_min'])) {
            update_post_meta($post_id, '_bw_tp_word_count_min', absint($_POST['bw_tp_word_count_min']));
        }

        if (isset($_POST['bw_tp_word_count_max'])) {
            update_post_meta($post_id, '_bw_tp_word_count_max', absint($_POST['bw_tp_word_count_max']));
        }
    }

    /**
     * Get all talking points by content type
     *
     * @param string $content_type Content type slug
     * @return array Talking points with metadata
     */
    public function get_talking_points_by_content_type($content_type = '') {
        $args = array(
            'post_type'      => 'bw_talking_point',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order title',
            'order'          => 'ASC'
        );

        if ($content_type) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'bw_content_type',
                    'field'    => 'slug',
                    'terms'    => $content_type
                )
            );
        }

        $query = new WP_Query($args);
        $talking_points = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $talking_points[] = array(
                    'id'             => $post_id,
                    'name'           => get_the_title(),
                    'context'        => get_post_meta($post_id, '_bw_tp_context', true),
                    'example'        => get_post_meta($post_id, '_bw_tp_example', true),
                    'cta_example'    => get_post_meta($post_id, '_bw_tp_cta_example', true),
                    'word_count_min' => get_post_meta($post_id, '_bw_tp_word_count_min', true) ?: 50,
                    'word_count_max' => get_post_meta($post_id, '_bw_tp_word_count_max', true) ?: 130,
                    'content_types'  => wp_get_post_terms($post_id, 'bw_content_type', array('fields' => 'slugs'))
                );
            }
            wp_reset_postdata();
        }

        return $talking_points;
    }

    /**
     * Get all content types
     *
     * @return array Content type terms
     */
    public function get_content_types() {
        $terms = get_terms(array(
            'taxonomy'   => 'bw_content_type',
            'hide_empty' => false
        ));

        if (is_wp_error($terms)) {
            return array();
        }

        return array_map(function($term) {
            return array(
                'id'   => $term->term_id,
                'slug' => $term->slug,
                'name' => $term->name
            );
        }, $terms);
    }
}
