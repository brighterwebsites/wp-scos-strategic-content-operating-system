<?php
/**
 * One-Time Migration: Reading Time & Views Fields
 * 
 * Migrates data from old fields to new bw_ prefixed fields:
 * - post_wordcount → bw_word_count
 * - post_reading_minutes → bw_reading_time
 * - post_reading_iso → bw_reading_time_iso
 * - post_views_count (ACF) → bw_views_count
 * 
 * Run this once, then delete this file.
 */

if (!defined('ABSPATH')) exit;

class BW_Reading_Time_Migration {
    
    /**
     * Initialize migration
     */
    public static function init() {
        // Check if migration is needed
        if (get_option('bw_reading_time_migrated') === 'yes') {
            return; // Already migrated
        }
        
        // Add admin notice
        add_action('admin_notices', [__CLASS__, 'migration_notice']);
        
        // Handle migration action
        add_action('admin_init', [__CLASS__, 'handle_migration']);
    }
    
    /**
     * Show admin notice
     */
    public static function migration_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $screen = get_current_screen();
        if ($screen->id !== 'dashboard') {
            return; // Only show on dashboard
        }
        
        ?>
        <div class="notice notice-warning is-dismissible">
            <h3>Content Analysis: Reading Time Migration Required</h3>
            <p>
                <strong>Action Required:</strong> Migrate reading time & views data to new consolidated fields.
            </p>
            <p>
                This will copy data from old fields to new <code>bw_</code> prefixed fields:<br>
                <code>post_wordcount</code> → <code>bw_word_count</code><br>
                <code>post_reading_minutes</code> → <code>bw_reading_time</code><br>
                <code>post_reading_iso</code> → <code>bw_reading_time_iso</code><br>
                <code>post_views_count</code> (ACF, KB only) → <code>bw_views_count</code> (all post types)
            </p>
            <p>
                <strong>This is a one-time migration. Old fields will not be deleted (safe).</strong>
            </p>
            <p>
                <a href="<?php echo wp_nonce_url(admin_url('index.php?bw_migrate_reading_time=1'), 'bw_migrate_reading_time'); ?>" 
                   class="button button-primary">
                    Run Migration Now
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Handle migration action
     */
    public static function handle_migration() {
        if (!isset($_GET['bw_migrate_reading_time'])) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_admin_referer('bw_migrate_reading_time');
        
        // Run migration
        $results = self::migrate_all_posts();
        
        // Mark as migrated
        update_option('bw_reading_time_migrated', 'yes');
        
        // Show success notice
        add_action('admin_notices', function() use ($results) {
            ?>
            <div class="notice notice-success is-dismissible">
                <h3>Migration Complete!</h3>
                <p>
                    <strong>Posts migrated:</strong> <?php echo $results['migrated']; ?><br>
                    <strong>Posts skipped:</strong> <?php echo $results['skipped']; ?> (no data to migrate)<br>
                    <strong>Total processed:</strong> <?php echo $results['total']; ?>
                </p>
                <p>
                    Old fields have been preserved (not deleted). New fields are now active.<br>
                    You can now safely disable the old reading time script in Shortcodes.md.
                </p>
            </div>
            <?php
        });
        
        // Redirect to dashboard
        wp_redirect(admin_url('index.php'));
        exit;
    }
    
    /**
     * Migrate all posts
     */
    private static function migrate_all_posts() {
        global $wpdb;
        
        $migrated = 0;
        $skipped = 0;
        
        // Get all posts with old fields
        $posts = $wpdb->get_results("
            SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key IN ('post_wordcount', 'post_reading_minutes', 'post_reading_iso', 'post_views_count')
        ");
        
        foreach ($posts as $row) {
            $post_id = $row->post_id;
            
            // Check if post exists
            if (!get_post($post_id)) {
                continue;
            }
            
            $migrated_any = false;
            
            // Migrate word count (post_wordcount → bw_word_count)
            $old_wordcount = get_post_meta($post_id, 'post_wordcount', true);
            if ($old_wordcount && !get_post_meta($post_id, 'bw_word_count', true)) {
                update_post_meta($post_id, 'bw_word_count', $old_wordcount);
                $migrated_any = true;
            }
            
            // Migrate reading time (post_reading_minutes → bw_reading_time)
            $old_reading = get_post_meta($post_id, 'post_reading_minutes', true);
            if ($old_reading && !get_post_meta($post_id, 'bw_reading_time', true)) {
                update_post_meta($post_id, 'bw_reading_time', $old_reading);
                $migrated_any = true;
            }
            
            // Migrate reading ISO (post_reading_iso → bw_reading_time_iso)
            $old_iso = get_post_meta($post_id, 'post_reading_iso', true);
            if ($old_iso && !get_post_meta($post_id, 'bw_reading_time_iso', true)) {
                update_post_meta($post_id, 'bw_reading_time_iso', $old_iso);
                $migrated_any = true;
            }
            
            // Migrate views (post_views_count → bw_views_count)
            $old_views = get_post_meta($post_id, 'post_views_count', true);
            if ($old_views && !get_post_meta($post_id, 'bw_views_count', true)) {
                update_post_meta($post_id, 'bw_views_count', $old_views);
                $migrated_any = true;
            }
            
            if ($migrated_any) {
                $migrated++;
            } else {
                $skipped++;
            }
        }
        
        return [
            'migrated' => $migrated,
            'skipped' => $skipped,
            'total' => count($posts)
        ];
    }
}

// Initialize
BW_Reading_Time_Migration::init();

