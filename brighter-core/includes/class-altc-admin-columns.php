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
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_inline_edit_scripts']);
        add_action('wp_ajax_bw_altc_save_column', [__CLASS__, 'ajax_save_column']);
        add_action('quick_edit_custom_box', [__CLASS__, 'quick_edit_fields'], 10, 2);
        add_action('bulk_edit_custom_box', [__CLASS__, 'bulk_edit_fields'], 10, 2);
        add_action('admin_footer-edit.php', [__CLASS__, 'quick_edit_javascript']);
        add_action('save_post', [__CLASS__, 'save_quick_bulk_edit'], 10, 2);
    }

    /**
     * Register columns for all supported post types
     */
    public static function register_columns() {
        $post_types = BW_ALTC_Taxonomies::get_supported_post_types();

        foreach ($post_types as $post_type) {
            // Prime meta cache BEFORE columns render
            add_action("manage_{$post_type}_posts_custom_column", [__CLASS__, 'prime_meta_cache'], 1, 2);

            // Add columns
            add_filter("manage_{$post_type}_posts_columns", [__CLASS__, 'add_columns']);
            add_action("manage_{$post_type}_posts_custom_column", [__CLASS__, 'display_column'], 10, 2);
            add_filter("manage_edit-{$post_type}_sortable_columns", [__CLASS__, 'make_columns_sortable']);
        }

        // Handle sorting
        add_action('pre_get_posts', [__CLASS__, 'sort_columns']);
    }

    /**
     * Prime meta cache for all posts in list
     * This prevents N+1 query problem
     */
    public static function prime_meta_cache($column, $post_id) {
        static $primed = false;

        // Only prime once per request
        if ($primed) return;
        $primed = true;

        // Get all post IDs on current page
        global $wp_query;
        if (!isset($wp_query->posts) || empty($wp_query->posts)) return;

        $post_ids = wp_list_pluck($wp_query->posts, 'ID');

        // Prime meta cache for all ALTC-related meta keys
        update_meta_cache('post', $post_ids);
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
                        echo '<span class="bw-altc-editable" data-post="' . esc_attr($post_id) . '" data-field="bw_primary_altc_id" data-value="' . esc_attr($altc_id) . '" style="display: inline-block; padding: 3px 8px; background: #e7f5fe; color: #00a0d2; border-radius: 3px; font-size: 11px; font-weight: 600; cursor: pointer;" title="Click to edit">';
                        echo esc_html($altc_term->name);
                        echo '</span>';
                    } else {
                        echo '<span class="bw-altc-editable" data-post="' . esc_attr($post_id) . '" data-field="bw_primary_altc_id" data-value="" style="color: #999; cursor: pointer;" title="Click to set">—</span>';
                    }
                } else {
                    echo '<span class="bw-altc-editable" data-post="' . esc_attr($post_id) . '" data-field="bw_primary_altc_id" data-value="" style="color: #999; cursor: pointer;" title="Click to set">—</span>';
                }
                break;

            case 'bw_altc_topic':
                $topic_id = get_post_meta($post_id, 'bw_primary_topic_id', true);
                if ($topic_id) {
                    $topic_term = get_term($topic_id, 'altc_topic');
                    if ($topic_term && !is_wp_error($topic_term)) {
                        echo '<span class="bw-topic-editable" data-post="' . esc_attr($post_id) . '" data-field="bw_primary_topic_id" data-value="' . esc_attr($topic_id) . '" style="cursor: pointer; text-decoration: underline dotted;" title="Click to edit">';
                        echo esc_html($topic_term->name);
                        echo '</span>';
                    } else {
                        // Fallback to old bw_page_topic if exists
                        $old_topic = get_post_meta($post_id, 'bw_page_topic', true);
                        if ($old_topic) {
                            echo '<span class="bw-topic-editable" data-post="' . esc_attr($post_id) . '" data-field="bw_primary_topic_id" data-value="" style="color: #999; font-style: italic; cursor: pointer;" title="Click to migrate to taxonomy">';
                            echo esc_html($old_topic);
                            echo '</span>';
                        } else {
                            echo '<span class="bw-topic-editable" data-post="' . esc_attr($post_id) . '" data-field="bw_primary_topic_id" data-value="" style="color: #999; cursor: pointer;" title="Click to set">—</span>';
                        }
                    }
                } else {
                    // Fallback to old bw_page_topic if exists
                    $old_topic = get_post_meta($post_id, 'bw_page_topic', true);
                    if ($old_topic) {
                        echo '<span class="bw-topic-editable" data-post="' . esc_attr($post_id) . '" data-field="bw_primary_topic_id" data-value="" style="color: #999; font-style: italic; cursor: pointer;" title="Click to migrate to taxonomy">';
                        echo esc_html($old_topic);
                        echo '</span>';
                    } else {
                        echo '<span class="bw-topic-editable" data-post="' . esc_attr($post_id) . '" data-field="bw_primary_topic_id" data-value="" style="color: #999; cursor: pointer;" title="Click to set">—</span>';
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

                    echo '<span class="bw-maturity-editable" data-post="' . esc_attr($post_id) . '" data-field="bw_cont_maturity" data-value="' . esc_attr($maturity) . '" style="display: inline-block; padding: 3px 8px; background: ' . esc_attr($color['bg']) . '; color: ' . esc_attr($color['color']) . '; border-radius: 3px; font-size: 11px; font-weight: 600; cursor: pointer;" title="Click to edit">';
                    echo esc_html($label);
                    echo '</span>';
                } else {
                    echo '<span class="bw-maturity-editable" data-post="' . esc_attr($post_id) . '" data-field="bw_cont_maturity" data-value="" style="color: #999; cursor: pointer;" title="Click to set">—</span>';
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

    /**
     * Enqueue inline edit scripts
     */
    public static function enqueue_inline_edit_scripts($hook) {
        if ($hook !== 'edit.php') {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, BW_ALTC_Taxonomies::get_supported_post_types(), true)) {
            return;
        }

        // Get ALTC terms
        $altc_terms = get_terms([
            'taxonomy' => 'altc_strategic_lens',
            'hide_empty' => false,
            'parent' => 0,
        ]);

        // Get topic terms
        $topic_terms = get_terms([
            'taxonomy' => 'altc_topic',
            'hide_empty' => false,
        ]);

        $maturity_options = BW_ALTC_Taxonomies::get_maturity_options();

        // Prepare data for JavaScript
        $altc_data = [];
        if (!is_wp_error($altc_terms)) {
            foreach ($altc_terms as $term) {
                $altc_data[$term->term_id] = $term->name;
            }
        }

        $topic_data = [];
        if (!is_wp_error($topic_terms)) {
            foreach ($topic_terms as $term) {
                $topic_data[$term->term_id] = $term->name;
            }
        }

        wp_register_script('bw-altc-inline-edit', false, ['jquery'], '1.0', true);
        wp_add_inline_script('bw-altc-inline-edit', '(function($){
            const nonce = "' . esc_js(wp_create_nonce('bw_altc_inline_edit')) . '";
            const altcOptions = ' . wp_json_encode($altc_data) . ';
            const topicOptions = ' . wp_json_encode($topic_data) . ';
            const maturityOptions = ' . wp_json_encode($maturity_options) . ';

            function saveField(postId, field, value) {
                return $.post(ajaxurl, {
                    action: "bw_altc_save_column",
                    post_id: postId,
                    field: field,
                    value: value,
                    _ajax_nonce: nonce
                });
            }

            // ALTC dropdown
            $(document).on("click", ".bw-altc-editable", function(e) {
                e.preventDefault();
                const $span = $(this);
                const postId = $span.data("post");
                const current = $span.data("value") || "";

                const $select = $("<select>", {
                    class: "bw-altc-dropdown",
                    "data-post": postId,
                    css: { fontSize: "11px" }
                });

                $select.append($("<option>", { value: "", text: "-- Select ALTC --" }));
                $.each(altcOptions, function(id, name) {
                    $select.append($("<option>", {
                        value: id,
                        text: name,
                        selected: id == current
                    }));
                });

                $span.replaceWith($select);
                $select.focus();
            });

            $(document).on("change blur", ".bw-altc-dropdown", function(e) {
                const $select = $(this);
                const postId = $select.data("post");
                const value = $select.val();

                $select.prop("disabled", true);
                saveField(postId, "bw_primary_altc_id", value).done(function(resp) {
                    if (resp && resp.success) {
                        location.reload();
                    } else {
                        alert("Save failed");
                        $select.prop("disabled", false).focus();
                    }
                });
            });

            // Topic dropdown
            $(document).on("click", ".bw-topic-editable", function(e) {
                e.preventDefault();
                const $span = $(this);
                const postId = $span.data("post");
                const current = $span.data("value") || "";

                const $select = $("<select>", {
                    class: "bw-topic-dropdown",
                    "data-post": postId
                });

                $select.append($("<option>", { value: "", text: "-- Select Topic --" }));
                $.each(topicOptions, function(id, name) {
                    $select.append($("<option>", {
                        value: id,
                        text: name,
                        selected: id == current
                    }));
                });

                $span.replaceWith($select);
                $select.focus();
            });

            $(document).on("change blur", ".bw-topic-dropdown", function(e) {
                const $select = $(this);
                const postId = $select.data("post");
                const value = $select.val();

                $select.prop("disabled", true);
                saveField(postId, "bw_primary_topic_id", value).done(function(resp) {
                    if (resp && resp.success) {
                        location.reload();
                    } else {
                        alert("Save failed");
                        $select.prop("disabled", false).focus();
                    }
                });
            });

            // Maturity dropdown
            $(document).on("click", ".bw-maturity-editable", function(e) {
                e.preventDefault();
                const $span = $(this);
                const postId = $span.data("post");
                const current = $span.data("value") || "";

                const $select = $("<select>", {
                    class: "bw-maturity-dropdown",
                    "data-post": postId,
                    css: { fontSize: "11px" }
                });

                $.each(maturityOptions, function(val, label) {
                    $select.append($("<option>", {
                        value: val,
                        text: label,
                        selected: val === current
                    }));
                });

                $span.replaceWith($select);
                $select.focus();
            });

            $(document).on("change blur", ".bw-maturity-dropdown", function(e) {
                const $select = $(this);
                const postId = $select.data("post");
                const value = $select.val();

                $select.prop("disabled", true);
                saveField(postId, "bw_cont_maturity", value).done(function(resp) {
                    if (resp && resp.success) {
                        location.reload();
                    } else {
                        alert("Save failed");
                        $select.prop("disabled", false).focus();
                    }
                });
            });

        })(jQuery);');
        wp_enqueue_script('bw-altc-inline-edit');

        // Hide standard WordPress taxonomy sections from bulk edit
        wp_add_inline_style('common', '
            .bulk-edit-row .inline-edit-categories label.inline-edit-tags-label[for*="altc_strategic_lens"],
            .bulk-edit-row .inline-edit-categories label.inline-edit-tags-label[for*="altc_topic"],
            .bulk-edit-row .inline-edit-group label.inline-edit-tags-label[for*="altc_strategic_lens"],
            .bulk-edit-row .inline-edit-group label.inline-edit-tags-label[for*="altc_topic"],
            .bulk-edit-row fieldset.inline-edit-col-taxonomy-altc_strategic_lens,
            .bulk-edit-row fieldset.inline-edit-col-taxonomy-altc_topic {
                display: none !important;
            }
        ');
    }

    /**
     * AJAX handler for saving column values
     */
    public static function ajax_save_column() {
        check_ajax_referer('bw_altc_inline_edit');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $field = isset($_POST['field']) ? sanitize_key($_POST['field']) : '';
        $value = isset($_POST['value']) ? wp_unslash($_POST['value']) : '';

        // Verify post type is registered before checking capabilities
        $post_type = get_post_type($post_id);
        if (!$post_id || !$post_type || !post_type_exists($post_type) || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error('No permission');
        }

        $allowed = ['bw_primary_altc_id', 'bw_primary_topic_id', 'bw_cont_maturity'];
        if (!in_array($field, $allowed, true)) {
            wp_send_json_error('Invalid field');
        }

        if (in_array($field, ['bw_primary_altc_id', 'bw_primary_topic_id'], true)) {
            $value = absint($value);
        } else {
            $value = sanitize_text_field($value);
        }

        update_post_meta($post_id, $field, $value);
        wp_send_json_success(true);
    }

    /**
     * Add quick edit fields
     */
    public static function quick_edit_fields($column, $post_type) {
        self::render_edit_fields($column, $post_type, false);
    }

    /**
     * Add bulk edit fields
     */
    public static function bulk_edit_fields($column, $post_type) {
        self::render_edit_fields($column, $post_type, true);
    }

    /**
     * Render edit fields for quick/bulk edit
     */
    private static function render_edit_fields($column, $post_type, $is_bulk = false) {
        if (!in_array($column, ['bw_altc', 'bw_altc_topic', 'bw_altc_maturity'], true)) {
            return;
        }
        if (!in_array($post_type, BW_ALTC_Taxonomies::get_supported_post_types(), true)) {
            return;
        }

        // Get terms
        $altc_terms = get_terms([
            'taxonomy' => 'altc_strategic_lens',
            'hide_empty' => false,
            'parent' => 0,
        ]);

        $topic_terms = get_terms([
            'taxonomy' => 'altc_topic',
            'hide_empty' => false,
        ]);

        $maturity_options = BW_ALTC_Taxonomies::get_maturity_options();
        $no_change_label = $is_bulk ? '-- No Change --' : '-- Select --';

        ?>
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-col">
                <?php if ($column === 'bw_altc'): ?>
                    <label><span class="title">ALTC Strategic Lens</span>
                        <select name="bw_primary_altc_id">
                            <option value=""><?php echo esc_html($no_change_label); ?></option>
                            <?php if (!is_wp_error($altc_terms)): ?>
                                <?php foreach ($altc_terms as $term): ?>
                                    <option value="<?php echo esc_attr($term->term_id); ?>">
                                        <?php echo esc_html($term->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </label>
                <?php elseif ($column === 'bw_altc_topic'): ?>
                    <label><span class="title">Primary Topic</span>
                        <select name="bw_primary_topic_id">
                            <option value=""><?php echo esc_html($no_change_label); ?></option>
                            <?php if (!is_wp_error($topic_terms)): ?>
                                <?php foreach ($topic_terms as $term): ?>
                                    <option value="<?php echo esc_attr($term->term_id); ?>">
                                        <?php echo esc_html($term->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </label>
                <?php elseif ($column === 'bw_altc_maturity'): ?>
                    <label><span class="title">Content Maturity</span>
                        <select name="bw_cont_maturity">
                            <?php if ($is_bulk): ?>
                                <option value="">-- No Change --</option>
                            <?php endif; ?>
                            <?php foreach ($maturity_options as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>">
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
            </div>
        </fieldset>
        <?php
    }

    /**
     * JavaScript to populate quick edit fields
     */
    public static function quick_edit_javascript() {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, BW_ALTC_Taxonomies::get_supported_post_types(), true)) {
            return;
        }
        ?>
        <script>
        jQuery(function($) {
            var $qe = inlineEditPost.edit;
            inlineEditPost.edit = function(id) {
                $qe.apply(this, arguments);
                var postId = (typeof id === 'object') ? this.getId(id) : id;
                var $row = $('#post-' + postId);

                // Get values from data attributes
                var altcId = $row.find('.bw-altc-editable').data('value') || '';
                var topicId = $row.find('.bw-topic-editable').data('value') || '';
                var maturity = $row.find('.bw-maturity-editable').data('value') || '';

                // Set quick edit values
                $('select[name="bw_primary_altc_id"]', '.inline-edit-row').val(altcId);
                $('select[name="bw_primary_topic_id"]', '.inline-edit-row').val(topicId);
                $('select[name="bw_cont_maturity"]', '.inline-edit-row').val(maturity);
            };
        });
        </script>
        <?php
    }

    /**
     * Save quick edit and bulk edit values
     */
    public static function save_quick_bulk_edit($post_id, $post) {
        // Check if this is an autosave or revision
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check if this is a quick/bulk edit request
        if (!isset($_REQUEST['_inline_edit']) && !isset($_REQUEST['bulk_edit'])) {
            return;
        }

        // Check post type support
        if (!in_array($post->post_type, BW_ALTC_Taxonomies::get_supported_post_types(), true)) {
            return;
        }

        // Verify post type is registered before checking capabilities
        if (!post_type_exists($post->post_type)) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $is_bulk_edit = isset($_REQUEST['bulk_edit']);

        // Save ALTC Strategic Lens (skip if bulk edit and value is empty = "no change")
        if (isset($_REQUEST['bw_primary_altc_id'])) {
            $altc_id = $_REQUEST['bw_primary_altc_id'];
            // Skip if bulk edit and empty (no change selected)
            if (!$is_bulk_edit || $altc_id !== '') {
                update_post_meta($post_id, 'bw_primary_altc_id', absint($altc_id));
            }
        }

        // Save Primary Topic (skip if bulk edit and value is empty = "no change")
        if (isset($_REQUEST['bw_primary_topic_id'])) {
            $topic_id = $_REQUEST['bw_primary_topic_id'];
            // Skip if bulk edit and empty (no change selected)
            if (!$is_bulk_edit || $topic_id !== '') {
                update_post_meta($post_id, 'bw_primary_topic_id', absint($topic_id));
            }
        }

        // Save Content Maturity (skip if bulk edit and value is empty = "no change")
        if (isset($_REQUEST['bw_cont_maturity'])) {
            $maturity = sanitize_text_field($_REQUEST['bw_cont_maturity']);
            // Skip if bulk edit and empty (no change selected)
            if (!$is_bulk_edit || $maturity !== '') {
                update_post_meta($post_id, 'bw_cont_maturity', $maturity);
            }
        }
    }
}

// Initialize
// Re-enabled with meta cache priming to prevent N+1 query problem
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
