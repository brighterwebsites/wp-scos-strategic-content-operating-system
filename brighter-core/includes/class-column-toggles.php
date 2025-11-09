<?php
/**
 * Admin Column Toggle Buttons
 *
 * File: class-column-toggles.php
 * Version: 1.0.0
 *
 * Responsibilities:
 * - Add toggle buttons above post list
 * - Group columns (Content Opt, ALTC, Stats, SEOPress)
 * - Save user preferences
 * - Handle show/hide via JavaScript
 */

if (!defined('ABSPATH')) exit;

class BW_Column_Toggles {

    /**
     * Initialize column toggles
     */
    public static function init() {
        add_action('manage_posts_extra_tablenav', [__CLASS__, 'render_toggle_buttons']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_bw_save_hidden_column_groups', [__CLASS__, 'ajax_save_preferences']);
    }

    /**
     * Render toggle buttons above post list
     */
    public static function render_toggle_buttons($which) {
        // Only show on top
        if ($which !== 'top') return;

        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'edit') return;

        // Check if this is a supported post type
        if (!in_array($screen->post_type, bw_cs_post_types(), true)) return;

        // Get user preferences
        $hidden_groups = get_user_meta(get_current_user_id(), 'bw_hidden_column_groups', true);
        if (!is_array($hidden_groups)) $hidden_groups = [];

        ?>
        <div class="bw-column-toggles" style="display: block !important; float: left; margin: 10px 10px 10px 0; padding: 10px; background: #f0f0f1; border: 2px solid #0073aa;">
            <button type="button" class="button bw-toggle-group <?php echo in_array('content-opt', $hidden_groups) ? '' : 'button-primary'; ?>" data-group="content-opt" title="Toggle Content Optimization columns">
                Content Opt
            </button>
            <button type="button" class="button bw-toggle-group <?php echo in_array('altc', $hidden_groups) ? '' : 'button-primary'; ?>" data-group="altc" title="Toggle ALTC columns">
                ALTC
            </button>
            <button type="button" class="button bw-toggle-group <?php echo in_array('stats', $hidden_groups) ? '' : 'button-primary'; ?>" data-group="stats" title="Toggle Stats columns">
                Stats
            </button>
            <?php if (self::has_seopress_columns()): ?>
            <button type="button" class="button bw-toggle-group <?php echo in_array('seopress', $hidden_groups) ? '' : 'button-primary'; ?>" data-group="seopress" title="Toggle SEOPress columns">
                SEOPress
            </button>
            <?php endif; ?>
            <button type="button" class="button button-secondary bw-toggle-all" title="Show all columns">
                Show All
            </button>
        </div>
        <?php
    }

    /**
     * Enqueue JavaScript and CSS
     */
    public static function enqueue_assets($hook) {
        if ($hook !== 'edit.php') return;

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, bw_cs_post_types(), true)) return;

        // Enqueue JavaScript
        wp_enqueue_script(
            'bw-column-toggles',
            BRIGHTER_CORE_URL . 'js/column-toggles.js',
            ['jquery'],
            BRIGHTER_CORE_VERSION,
            true
        );

        // Localize script with data
        wp_localize_script('bw-column-toggles', 'bwColumnToggles', [
            'nonce' => wp_create_nonce('bw_column_toggles'),
            'hiddenGroups' => get_user_meta(get_current_user_id(), 'bw_hidden_column_groups', true) ?: [],
            'columnGroups' => self::get_column_groups()
        ]);
    }

    /**
     * Get column group definitions
     */
    private static function get_column_groups() {
        return [
            'content-opt' => [
                'bw_topic',
                'bw_intent',
                'bw_purpose',
                'bw_opt',
                'bw_index',
                'bw_pillar',
                'bw_notes'
            ],
            'altc' => [
                'bw_altc',
                'bw_altc_topic',
                'bw_altc_role',
                'bw_altc_maturity'
            ],
            'stats' => [
                'bw_word_count',
                'bw_images',
                'bw_h2s',
                'bw_internal_links',
                'bw_external_links'
            ],
            'seopress' => self::detect_seopress_columns()
        ];
    }

    /**
     * Detect if SEOPress columns are present
     */
    private static function has_seopress_columns() {
        return function_exists('seopress_get_service');
    }

    /**
     * Detect SEOPress column IDs
     */
    private static function detect_seopress_columns() {
        // Common SEOPress column IDs
        // Will be populated via JavaScript inspection
        return [
            'seopress_title',
            'seopress_desc',
            'seopress_score',
            'seopress_noindex',
            'seopress_canonical'
        ];
    }

    /**
     * AJAX handler to save user preferences
     */
    public static function ajax_save_preferences() {
        check_ajax_referer('bw_column_toggles', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('No permission');
        }

        $groups = isset($_POST['groups']) && is_array($_POST['groups']) ? $_POST['groups'] : [];

        // Sanitize group names
        $allowed_groups = ['content-opt', 'altc', 'stats', 'seopress'];
        $groups = array_intersect($groups, $allowed_groups);

        update_user_meta(get_current_user_id(), 'bw_hidden_column_groups', $groups);

        wp_send_json_success(['saved' => $groups]);
    }
}

// Initialize
BW_Column_Toggles::init();
