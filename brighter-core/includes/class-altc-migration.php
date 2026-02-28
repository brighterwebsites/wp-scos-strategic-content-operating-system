<?php
/**
 * ALTC Migration Tool
 *
 * File: class-altc-migration.php
 * Version: 1.0.0
 *
 * Responsibilities:
 * - Migrate bw_page_topic (text) to bw_primary_topic_id (term ID)
 * - Create topics from unique bw_page_topic values
 * - Admin page under Tools menu
 * - One-time migration with safeguards
 */

if (!defined('ABSPATH')) exit;

class BW_ALTC_Migration {

    /**
     * Initialize migration tool
     */
    public static function init() {
        // COMMENTED OUT: Migration tool no longer needed in production
        // Can be re-enabled if needed for future migrations or repurposed for new modular system
        // Uncomment the lines below to restore the migration tool
        
        // add_action('admin_menu', [__CLASS__, 'add_migration_page']);
        // add_action('admin_post_bw_altc_run_migration', [__CLASS__, 'run_migration']);
    }

    /**
     * Add migration page to Tools menu
     */
    public static function add_migration_page() {
        add_management_page(
            __('ALTC Migration', 'brighterwebsites'),
            __('ALTC Migration', 'brighterwebsites'),
            'manage_options',
            'bw-altc-migration',
            [__CLASS__, 'render_migration_page']
        );
    }

    /**
     * Render migration page
     */
    public static function render_migration_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'brighterwebsites'));
        }

        // Check if migration has been run
        $migration_run = get_option('bw_altc_migration_completed', false);
        $migration_date = get_option('bw_altc_migration_date', '');

        // Get migration stats
        $stats = self::get_migration_stats();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('ALTC Topic Migration', 'brighterwebsites'); ?></h1>

            <div class="card" style="max-width: 800px;">
                <h2><?php esc_html_e('Migrate Topics to Taxonomy', 'brighterwebsites'); ?></h2>

                <?php if ($migration_run): ?>
                    <div class="notice notice-success inline">
                        <p>
                            <strong><?php esc_html_e('Migration Completed', 'brighterwebsites'); ?></strong><br>
                            <?php
                            printf(
                                esc_html__('Migration was completed on %s', 'brighterwebsites'),
                                esc_html($migration_date)
                            );
                            ?>
                        </p>
                    </div>
                <?php endif; ?>

                <p><?php esc_html_e('This tool migrates the old text-based "Topic" field (bw_page_topic) to the new taxonomy-based ALTC Topic system.', 'brighterwebsites'); ?></p>

                <h3><?php esc_html_e('Migration Statistics:', 'brighterwebsites'); ?></h3>
                <table class="widefat" style="max-width: 600px;">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e('Posts with old topic field:', 'brighterwebsites'); ?></strong></td>
                            <td><?php echo absint($stats['posts_with_old_topic']); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Unique topic values:', 'brighterwebsites'); ?></strong></td>
                            <td><?php echo absint($stats['unique_topics']); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Posts already migrated:', 'brighterwebsites'); ?></strong></td>
                            <td><?php echo absint($stats['posts_migrated']); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Posts needing migration:', 'brighterwebsites'); ?></strong></td>
                            <td><?php echo absint($stats['posts_need_migration']); ?></td>
                        </tr>
                    </tbody>
                </table>

                <?php if ($stats['unique_topics'] > 0): ?>
                    <h3><?php esc_html_e('Topics to be created:', 'brighterwebsites'); ?></h3>
                    <ul style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                        <?php foreach ($stats['topic_list'] as $topic => $count): ?>
                            <li>
                                <strong><?php echo esc_html($topic); ?></strong>
                                <span style="color: #666;">(<?php echo absint($count); ?> <?php echo absint($count) === 1 ? esc_html__('post', 'brighterwebsites') : esc_html__('posts', 'brighterwebsites'); ?>)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h3><?php esc_html_e('Migration Process:', 'brighterwebsites'); ?></h3>
                <ol>
                    <li><?php esc_html_e('Extract unique topic values from all posts', 'brighterwebsites'); ?></li>
                    <li><?php esc_html_e('Create ALTC Topic taxonomy terms for each unique value', 'brighterwebsites'); ?></li>
                    <li><?php esc_html_e('Map each post\'s text topic to the corresponding term ID', 'brighterwebsites'); ?></li>
                    <li><?php esc_html_e('Save term ID to bw_primary_topic_id meta field', 'brighterwebsites'); ?></li>
                    <li><?php esc_html_e('Keep original bw_page_topic field for reference (not deleted)', 'brighterwebsites'); ?></li>
                </ol>

                <div class="notice notice-info inline">
                    <p>
                        <strong><?php esc_html_e('Note:', 'brighterwebsites'); ?></strong>
                        <?php esc_html_e('The original bw_page_topic field will NOT be deleted. It will remain for reference. You can run this migration multiple times safely.', 'brighterwebsites'); ?>
                    </p>
                </div>

                <?php if ($stats['posts_need_migration'] > 0): ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to run the migration? This will create new topic terms and update post meta.', 'brighterwebsites'); ?>');">
                        <input type="hidden" name="action" value="bw_altc_run_migration">
                        <?php wp_nonce_field('bw_altc_migration', 'bw_altc_migration_nonce'); ?>
                        <p>
                            <button type="submit" class="button button-primary button-large">
                                <?php esc_html_e('Run Migration', 'brighterwebsites'); ?>
                            </button>
                        </p>
                    </form>
                <?php else: ?>
                    <div class="notice notice-warning inline">
                        <p><?php esc_html_e('No posts need migration. All posts either have no old topic data or have already been migrated.', 'brighterwebsites'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get migration statistics
     */
    private static function get_migration_stats() {
        global $wpdb;

        $post_types = BW_ALTC_Taxonomies::get_supported_post_types();
        $post_types_placeholder = implode(',', array_fill(0, count($post_types), '%s'));

        // Get all posts with old topic field
        $query = $wpdb->prepare(
            "SELECT pm.post_id, pm.meta_value as old_topic
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = 'bw_page_topic'
            AND pm.meta_value != ''
            AND p.post_type IN ($post_types_placeholder)",
            $post_types
        );

        $results = $wpdb->get_results($query);

        $posts_with_old_topic = count($results);
        $topic_list = [];

        foreach ($results as $row) {
            $topic = trim($row->old_topic);
            if (!empty($topic)) {
                if (!isset($topic_list[$topic])) {
                    $topic_list[$topic] = 0;
                }
                $topic_list[$topic]++;
            }
        }

        $unique_topics = count($topic_list);

        // Get posts already migrated (have bw_primary_topic_id)
        $query_migrated = $wpdb->prepare(
            "SELECT COUNT(DISTINCT pm.post_id) as count
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = 'bw_primary_topic_id'
            AND pm.meta_value != ''
            AND pm.meta_value != '0'
            AND p.post_type IN ($post_types_placeholder)",
            $post_types
        );

        $migrated_result = $wpdb->get_var($query_migrated);
        $posts_migrated = absint($migrated_result);

        // Posts needing migration = posts with old topic but no new topic ID
        $query_need_migration = $wpdb->prepare(
            "SELECT COUNT(DISTINCT pm.post_id) as count
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id AND pm2.meta_key = 'bw_primary_topic_id'
            WHERE pm.meta_key = 'bw_page_topic'
            AND pm.meta_value != ''
            AND (pm2.meta_value IS NULL OR pm2.meta_value = '' OR pm2.meta_value = '0')
            AND p.post_type IN ($post_types_placeholder)",
            $post_types
        );

        $need_migration_result = $wpdb->get_var($query_need_migration);
        $posts_need_migration = absint($need_migration_result);

        return [
            'posts_with_old_topic' => $posts_with_old_topic,
            'unique_topics' => $unique_topics,
            'posts_migrated' => $posts_migrated,
            'posts_need_migration' => $posts_need_migration,
            'topic_list' => $topic_list,
        ];
    }

    /**
     * Run the migration
     */
    public static function run_migration() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'brighterwebsites'));
        }

        // Verify nonce
        if (!isset($_POST['bw_altc_migration_nonce']) || !wp_verify_nonce($_POST['bw_altc_migration_nonce'], 'bw_altc_migration')) {
            wp_die(__('Security check failed.', 'brighterwebsites'));
        }

        global $wpdb;

        $post_types = BW_ALTC_Taxonomies::get_supported_post_types();
        $post_types_placeholder = implode(',', array_fill(0, count($post_types), '%s'));

        // Get all posts with old topic field
        $query = $wpdb->prepare(
            "SELECT pm.post_id, pm.meta_value as old_topic
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = 'bw_page_topic'
            AND pm.meta_value != ''
            AND p.post_type IN ($post_types_placeholder)",
            $post_types
        );

        $results = $wpdb->get_results($query);

        // Extract unique topics and create terms
        $topic_map = []; // Map of topic name => term_id

        foreach ($results as $row) {
            $topic_name = trim($row->old_topic);

            if (empty($topic_name) || isset($topic_map[$topic_name])) {
                continue;
            }

            // Check if topic term already exists
            $existing_term = term_exists($topic_name, 'altc_topic');

            if ($existing_term) {
                $topic_map[$topic_name] = $existing_term['term_id'];
            } else {
                // Create new topic term
                $new_term = wp_insert_term($topic_name, 'altc_topic');

                if (!is_wp_error($new_term)) {
                    $topic_map[$topic_name] = $new_term['term_id'];
                }
            }
        }

        // Now update posts with the new topic IDs
        $migrated_count = 0;
        $skipped_count = 0;

        foreach ($results as $row) {
            $topic_name = trim($row->old_topic);
            $post_id = absint($row->post_id);

            if (empty($topic_name) || !isset($topic_map[$topic_name])) {
                $skipped_count++;
                continue;
            }

            // Check if post already has a topic ID
            $existing_topic_id = get_post_meta($post_id, 'bw_primary_topic_id', true);
            if (!empty($existing_topic_id) && $existing_topic_id != '0') {
                $skipped_count++;
                continue;
            }

            // Update post meta
            $term_id = $topic_map[$topic_name];
            update_post_meta($post_id, 'bw_primary_topic_id', $term_id);
            $migrated_count++;
        }

        // Mark migration as completed
        update_option('bw_altc_migration_completed', true);
        update_option('bw_altc_migration_date', current_time('mysql'));
        update_option('bw_altc_migration_stats', [
            'migrated' => $migrated_count,
            'skipped' => $skipped_count,
            'topics_created' => count($topic_map),
        ]);

        // Redirect back with success message
        wp_redirect(add_query_arg([
            'page' => 'bw-altc-migration',
            'migrated' => $migrated_count,
            'topics' => count($topic_map),
        ], admin_url('tools.php')));
        exit;
    }
}

// Initialize
BW_ALTC_Migration::init();

// Show admin notice after migration
add_action('admin_notices', function() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'bw-altc-migration') {
        return;
    }

    if (isset($_GET['migrated']) && isset($_GET['topics'])) {
        $migrated = absint($_GET['migrated']);
        $topics = absint($_GET['topics']);

        echo '<div class="notice notice-success is-dismissible"><p>';
        printf(
            esc_html__('Migration completed! Created %1$d topic terms and migrated %2$d posts.', 'brighterwebsites'),
            $topics,
            $migrated
        );
        echo '</p></div>';
    }
});
