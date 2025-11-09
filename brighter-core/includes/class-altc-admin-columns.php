<?php
/**
 * ALTC Admin Columns & Filters
 *
 * File: class-altc-admin-columns.php
 * Version: 1.0.0
 *
 * Responsibilities:
 * - Add ALTC columns to admin post list
 * - Add ALTC filter dropdowns
 * - Handle column display
 * - Handle filter queries
 */

if (!defined('ABSPATH')) exit;

class BW_ALTC_Admin_Columns {

    /**
     * Initialize admin columns and filters
     */
    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_columns']);
        add_action('restrict_manage_posts', [__CLASS__, 'add_filter_dropdowns'], 10, 1);
        add_filter('parse_query', [__CLASS__, 'filter_posts_by_altc']);
    }

    /**
     * Register columns for all supported post types
     */
    public static function register_columns() {
        $post_types = BW_ALTC_Taxonomies::get_supported_post_types();

        foreach ($post_types as $post_type) {
            // Add columns
            add_filter("manage_{$post_type}_posts_columns", [__CLASS__, 'add_columns']);
            add_action("manage_{$post_type}_posts_custom_column", [__CLASS__, 'display_column'], 10, 2);
            add_filter("manage_edit-{$post_type}_sortable_columns", [__CLASS__, 'make_columns_sortable']);
        }

        // Handle sorting
        add_action('pre_get_posts', [__CLASS__, 'sort_columns']);
    }

    /**
     * Add ALTC columns to post list
     */
    public static function add_columns($columns) {
        $new_columns = [];

        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;

            // Add ALTC columns after the title column
            if ($key === 'title') {
                $new_columns['bw_altc'] = __('ALTC', 'brighterwebsites');
                $new_columns['bw_altc_topic'] = __('Topic', 'brighterwebsites');
                $new_columns['bw_altc_role'] = __('Role', 'brighterwebsites');
                $new_columns['bw_altc_maturity'] = __('Maturity', 'brighterwebsites');
            }
        }

        return $new_columns;
    }

    /**
     * Display column content
     */
    public static function display_column($column, $post_id) {
        switch ($column) {
            case 'bw_altc':
                $altc_id = get_post_meta($post_id, 'bw_primary_altc_id', true);
                if ($altc_id) {
                    $altc_term = get_term($altc_id, 'altc_strategic_lens');
                    if ($altc_term && !is_wp_error($altc_term)) {
                        echo '<span style="display: inline-block; padding: 3px 8px; background: #e7f5fe; color: #00a0d2; border-radius: 3px; font-size: 11px; font-weight: 600;">';
                        echo esc_html($altc_term->name);
                        echo '</span>';
                    } else {
                        echo '<span style="color: #999;">—</span>';
                    }
                } else {
                    echo '<span style="color: #999;">—</span>';
                }
                break;

            case 'bw_altc_topic':
                $topic_id = get_post_meta($post_id, 'bw_primary_topic_id', true);
                if ($topic_id) {
                    $topic_term = get_term($topic_id, 'altc_topic');
                    if ($topic_term && !is_wp_error($topic_term)) {
                        echo esc_html($topic_term->name);
                    } else {
                        // Fallback to old bw_page_topic if exists
                        $old_topic = get_post_meta($post_id, 'bw_page_topic', true);
                        if ($old_topic) {
                            echo '<span style="color: #999; font-style: italic;">';
                            echo esc_html($old_topic);
                            echo '</span>';
                        } else {
                            echo '<span style="color: #999;">—</span>';
                        }
                    }
                } else {
                    // Fallback to old bw_page_topic if exists
                    $old_topic = get_post_meta($post_id, 'bw_page_topic', true);
                    if ($old_topic) {
                        echo '<span style="color: #999; font-style: italic;">';
                        echo esc_html($old_topic);
                        echo '</span>';
                    } else {
                        echo '<span style="color: #999;">—</span>';
                    }
                }
                break;

            case 'bw_altc_role':
                $purpose = get_post_meta($post_id, 'bw_purpose', true);
                if ($purpose === 'pillar') {
                    echo '<span style="display: inline-block; padding: 3px 8px; background: #d1fae5; color: #047857; border-radius: 3px; font-size: 11px; font-weight: 600;">';
                    echo esc_html__('Pillar', 'brighterwebsites');
                    echo '</span>';
                } elseif (!empty($purpose)) {
                    echo '<span style="display: inline-block; padding: 3px 8px; background: #f3f4f6; color: #374151; border-radius: 3px; font-size: 11px; font-weight: 600;">';
                    echo esc_html__('Supporting', 'brighterwebsites');
                    echo '</span>';
                } else {
                    echo '<span style="color: #999;">—</span>';
                }
                break;

            case 'bw_altc_maturity':
                $maturity = get_post_meta($post_id, 'bw_cont_maturity', true);
                if ($maturity) {
                    $options = BW_ALTC_Taxonomies::get_maturity_options();
                    $label = isset($options[$maturity]) ? $options[$maturity] : $maturity;

                    // Color coding based on maturity level
                    $colors = [
                        'entry' => ['bg' => '#fef3c7', 'color' => '#92400e'],
                        'learner' => ['bg' => '#fde68a', 'color' => '#78350f'],
                        'professional' => ['bg' => '#dbeafe', 'color' => '#1e40af'],
                        'expert' => ['bg' => '#bfdbfe', 'color' => '#1e3a8a'],
                        'thought_leader' => ['bg' => '#ddd6fe', 'color' => '#5b21b6'],
                        'industry_authority' => ['bg' => '#d1fae5', 'color' => '#065f46'],
                    ];

                    $color = isset($colors[$maturity]) ? $colors[$maturity] : ['bg' => '#f3f4f6', 'color' => '#374151'];

                    echo '<span style="display: inline-block; padding: 3px 8px; background: ' . esc_attr($color['bg']) . '; color: ' . esc_attr($color['color']) . '; border-radius: 3px; font-size: 11px; font-weight: 600;">';
                    echo esc_html($label);
                    echo '</span>';
                } else {
                    echo '<span style="color: #999;">—</span>';
                }
                break;
        }
    }

    /**
     * Make columns sortable
     */
    public static function make_columns_sortable($columns) {
        $columns['bw_altc'] = 'bw_primary_altc_id';
        $columns['bw_altc_topic'] = 'bw_primary_topic_id';
        $columns['bw_altc_role'] = 'bw_purpose';
        $columns['bw_altc_maturity'] = 'bw_cont_maturity';

        return $columns;
    }

    /**
     * Handle column sorting
     */
    public static function sort_columns($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');

        if (in_array($orderby, ['bw_primary_altc_id', 'bw_primary_topic_id', 'bw_cont_maturity'], true)) {
            $query->set('meta_key', $orderby);
            $query->set('orderby', 'meta_value_num');
        }
    }

    /**
     * Add filter dropdowns to post list
     */
    public static function add_filter_dropdowns($post_type) {
        // Check if this post type supports ALTC
        if (!in_array($post_type, BW_ALTC_Taxonomies::get_supported_post_types(), true)) {
            return;
        }

        // Get current filter values
        $current_altc = isset($_GET['bw_filter_altc']) ? absint($_GET['bw_filter_altc']) : 0;
        $current_topic = isset($_GET['bw_filter_topic']) ? absint($_GET['bw_filter_topic']) : 0;
        $current_maturity = isset($_GET['bw_filter_maturity']) ? sanitize_text_field($_GET['bw_filter_maturity']) : '';
        $current_role = isset($_GET['bw_filter_role']) ? sanitize_text_field($_GET['bw_filter_role']) : '';

        // ALTC Filter
        $altc_terms = get_terms([
            'taxonomy' => 'altc_strategic_lens',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (!is_wp_error($altc_terms) && !empty($altc_terms)) {
            echo '<select name="bw_filter_altc" id="bw_filter_altc">';
            echo '<option value="">' . esc_html__('All ALTCs', 'brighterwebsites') . '</option>';
            foreach ($altc_terms as $term) {
                if ($term->parent == 0) { // Only show top-level
                    printf(
                        '<option value="%d"%s>%s</option>',
                        esc_attr($term->term_id),
                        selected($current_altc, $term->term_id, false),
                        esc_html($term->name)
                    );
                }
            }
            echo '</select>';
        }

        // Topic Filter
        $topic_terms = get_terms([
            'taxonomy' => 'altc_topic',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (!is_wp_error($topic_terms) && !empty($topic_terms)) {
            echo '<select name="bw_filter_topic" id="bw_filter_topic">';
            echo '<option value="">' . esc_html__('All Topics', 'brighterwebsites') . '</option>';
            foreach ($topic_terms as $term) {
                printf(
                    '<option value="%d"%s>%s</option>',
                    esc_attr($term->term_id),
                    selected($current_topic, $term->term_id, false),
                    esc_html($term->name)
                );
            }
            echo '</select>';
        }

        // Maturity Filter
        $maturity_options = BW_ALTC_Taxonomies::get_maturity_options();
        echo '<select name="bw_filter_maturity" id="bw_filter_maturity">';
        echo '<option value="">' . esc_html__('All Maturity Levels', 'brighterwebsites') . '</option>';
        foreach ($maturity_options as $value => $label) {
            if ($value !== '') { // Skip the "Not Set" option in filter
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr($value),
                    selected($current_maturity, $value, false),
                    esc_html($label)
                );
            }
        }
        echo '</select>';

        // Role Filter (Pillar/Supporting)
        echo '<select name="bw_filter_role" id="bw_filter_role">';
        echo '<option value="">' . esc_html__('All Roles', 'brighterwebsites') . '</option>';
        echo '<option value="pillar"' . selected($current_role, 'pillar', false) . '>' . esc_html__('Pillar', 'brighterwebsites') . '</option>';
        echo '<option value="supporting"' . selected($current_role, 'supporting', false) . '>' . esc_html__('Supporting', 'brighterwebsites') . '</option>';
        echo '</select>';
    }

    /**
     * Filter posts by ALTC parameters
     */
    public static function filter_posts_by_altc($query) {
        global $pagenow;

        // Only apply on admin post list
        if (!is_admin() || $pagenow !== 'edit.php' || !$query->is_main_query()) {
            return;
        }

        $meta_query = $query->get('meta_query') ?: [];

        // Filter by ALTC
        if (isset($_GET['bw_filter_altc']) && $_GET['bw_filter_altc'] !== '') {
            $meta_query[] = [
                'key' => 'bw_primary_altc_id',
                'value' => absint($_GET['bw_filter_altc']),
                'compare' => '=',
            ];
        }

        // Filter by Topic
        if (isset($_GET['bw_filter_topic']) && $_GET['bw_filter_topic'] !== '') {
            $meta_query[] = [
                'key' => 'bw_primary_topic_id',
                'value' => absint($_GET['bw_filter_topic']),
                'compare' => '=',
            ];
        }

        // Filter by Maturity
        if (isset($_GET['bw_filter_maturity']) && $_GET['bw_filter_maturity'] !== '') {
            $meta_query[] = [
                'key' => 'bw_cont_maturity',
                'value' => sanitize_text_field($_GET['bw_filter_maturity']),
                'compare' => '=',
            ];
        }

        // Filter by Role
        if (isset($_GET['bw_filter_role']) && $_GET['bw_filter_role'] !== '') {
            if ($_GET['bw_filter_role'] === 'pillar') {
                $meta_query[] = [
                    'key' => 'bw_purpose',
                    'value' => 'pillar',
                    'compare' => '=',
                ];
            } elseif ($_GET['bw_filter_role'] === 'supporting') {
                $meta_query[] = [
                    'key' => 'bw_purpose',
                    'value' => 'pillar',
                    'compare' => '!=',
                ];
            }
        }

        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
    }
}

// Initialize
BW_ALTC_Admin_Columns::init();

// Add CSS for column widths
add_action('admin_head', function() {
    $screen = get_current_screen();
    if ($screen && $screen->base === 'edit') {
        echo '<style>
            .fixed .column-bw_altc { width: 140px; }
            .fixed .column-bw_altc_topic { width: 140px; }
            .fixed .column-bw_altc_role { width: 100px; }
            .fixed .column-bw_altc_maturity { width: 120px; }
        </style>';
    }
});
