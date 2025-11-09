<?php
/**
 * Content Analysis Background Seeder
 *
 * File: class-content-analysis-seeder.php
 * Version: 1.0.0
 *
 * Responsibilities:
 * - Slowly analyze existing posts that haven't been analyzed
 * - Process in small batches to avoid performance issues
 * - Use WP-Cron for background processing
 */

if (!defined('ABSPATH')) exit;

class BW_Content_Analysis_Seeder {

    /**
     * How many posts to analyze per batch
     */
    const BATCH_SIZE = 5;

    /**
     * Initialize seeder
     */
    public static function init() {
        // Register WP-Cron event
        add_action('bw_analyze_content_batch', [__CLASS__, 'process_batch']);

        // Schedule cron if not already scheduled
        if (!wp_next_scheduled('bw_analyze_content_batch')) {
            wp_schedule_event(time(), 'hourly', 'bw_analyze_content_batch');
        }

        // Add admin notice showing progress
        add_action('admin_notices', [__CLASS__, 'show_progress_notice']);

        // Add manual trigger button (for admins)
        add_action('admin_bar_menu', [__CLASS__, 'add_admin_bar_button'], 100);
        add_action('admin_init', [__CLASS__, 'handle_manual_trigger']);
    }

    /**
     * Process a batch of posts
     */
    public static function process_batch() {
        // Get posts that need analysis
        $posts_to_analyze = get_posts([
            'post_type' => bw_cs_post_types(),
            'posts_per_page' => self::BATCH_SIZE,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_bw_last_analyzed',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_bw_last_analyzed',
                    'value' => '',
                    'compare' => '=',
                ],
            ],
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        if (empty($posts_to_analyze)) {
            // All posts analyzed!
            return;
        }

        foreach ($posts_to_analyze as $post) {
            // Analyze this post
            if (class_exists('BW_Content_Analysis')) {
                BW_Content_Analysis::analyze_content($post->ID, $post, true);
            }
        }

        // Log progress
        error_log(sprintf(
            'BW Content Analysis Seeder: Analyzed %d posts (Batch complete)',
            count($posts_to_analyze)
        ));
    }

    /**
     * Get count of posts needing analysis
     */
    public static function get_pending_count() {
        $count = wp_count_posts_by_meta([
            'post_type' => bw_cs_post_types(),
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_bw_last_analyzed',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_bw_last_analyzed',
                    'value' => '',
                    'compare' => '=',
                ],
            ],
        ]);

        // Fallback to slower query if helper doesn't exist
        if (!function_exists('wp_count_posts_by_meta')) {
            global $wpdb;
            $post_types = bw_cs_post_types();
            $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

            $sql = "SELECT COUNT(DISTINCT p.ID)
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_bw_last_analyzed'
                    WHERE p.post_type IN ($placeholders)
                    AND p.post_status IN ('publish', 'draft', 'pending', 'private')
                    AND (pm.post_id IS NULL OR pm.meta_value = '')";

            $count = $wpdb->get_var($wpdb->prepare($sql, ...$post_types));
        }

        return absint($count);
    }

    /**
     * Show admin notice with progress
     */
    public static function show_progress_notice() {
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'edit') return;

        $pending = self::get_pending_count();
        if ($pending === 0) return;

        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong>Content Analysis:</strong>
                <?php echo absint($pending); ?> posts pending analysis.
                Background seeder is processing <?php echo self::BATCH_SIZE; ?> posts per hour.
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('edit.php?bw_analyze_now=1'), 'bw_analyze_now')); ?>" class="button button-small">
                    Analyze <?php echo self::BATCH_SIZE; ?> Now
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Add button to admin bar
     */
    public static function add_admin_bar_button($wp_admin_bar) {
        if (!current_user_can('manage_options')) return;

        $pending = self::get_pending_count();
        if ($pending === 0) return;

        $wp_admin_bar->add_node([
            'id' => 'bw-analyze-content',
            'title' => sprintf('Analyze Content (%d)', $pending),
            'href' => wp_nonce_url(admin_url('edit.php?bw_analyze_now=1'), 'bw_analyze_now'),
            'meta' => [
                'title' => sprintf('%d posts need content analysis', $pending),
            ],
        ]);
    }

    /**
     * Handle manual trigger from admin
     */
    public static function handle_manual_trigger() {
        if (!isset($_GET['bw_analyze_now'])) return;
        if (!current_user_can('manage_options')) return;
        if (!wp_verify_nonce($_GET['_wpnonce'], 'bw_analyze_now')) return;

        // Process a batch immediately
        self::process_batch();

        // Redirect back with success message
        wp_safe_redirect(remove_query_arg(['bw_analyze_now', '_wpnonce']));
        exit;
    }
}

// Initialize
BW_Content_Analysis_Seeder::init();
