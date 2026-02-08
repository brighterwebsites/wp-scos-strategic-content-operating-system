<?php
/**  Completed migration - depreciate and archive on next read
 * Migrate TLDR Field
 * 
 * One-time migration: Copy data from ACF 'tldr' field to standardized 'bw_tldr' field
 * 
 * WHY: ACF fields are plugin-dependent. Standardizing to bw_ prefix ensures:
 * - Data persists even if ACF is deactivated
 * - Consistent field naming across all content meta
 * - Better integration with Airtable sync
 * 
 * USAGE: Automatic admin notice appears when migration is available
 */

if (!defined('ABSPATH')) exit;

class BW_TLDR_Migration {
    
    /**
     * Initialize migration hooks
     */
    public static function init() {
        // Admin notice to run migration
        add_action('admin_notices', [__CLASS__, 'show_migration_notice']);
        
        // Handle migration action
        add_action('admin_post_bw_migrate_tldr', [__CLASS__, 'run_migration']);
    }
    
    /**
     * Show admin notice if migration is needed
     */
    public static function show_migration_notice() {
        // Only show to admins
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if migration already completed
        if (get_option('bw_tldr_migration_completed')) {
            return;
        }
        
        // Check if there are posts with old field
        global $wpdb;
        $old_field_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = %s 
             AND meta_value != ''",
            'tldr'
        ));
        
        if (!$old_field_count) {
            // No old data found, mark as completed
            update_option('bw_tldr_migration_completed', true);
            return;
        }
        
        $migration_url = admin_url('admin-post.php?action=bw_migrate_tldr&_wpnonce=' . wp_create_nonce('bw_migrate_tldr'));
        
        ?>
        <div class="notice notice-warning is-dismissible">
            <h3>🔄 TLDR Field Migration Available</h3>
            <p>
                <strong><?php echo number_format($old_field_count); ?> posts</strong> have data in the old ACF 'tldr' field.<br>
                Click below to migrate to the new standardized 'bw_tldr' field.
            </p>
            <p>
                <strong>What this does:</strong>
            </p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>Copies data from <code>tldr</code> (ACF) → <code>bw_tldr</code> (standard)</li>
                <li>Updates Airtable sync to use new field</li>
                <li>Preserves original ACF field (safe to delete ACF field after testing)</li>
                <li>One-time operation (won't run again)</li>
            </ul>
            <p>
                <a href="<?php echo esc_url($migration_url); ?>" 
                   class="button button-primary"
                   onclick="return confirm('Migrate <?php echo $old_field_count; ?> TLDR fields? This is safe and reversible.');">
                    Migrate Now (<?php echo number_format($old_field_count); ?> posts)
                </a>
                <a href="#" class="button" onclick="this.closest('.notice').remove(); return false;">
                    Dismiss
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Run the migration
     */
    public static function run_migration() {
        // Verify nonce and permissions
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'bw_migrate_tldr')) {
            wp_die('Invalid security token');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        
        // Get all posts with old 'tldr' field
        $posts_to_migrate = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_value 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = %s 
             AND meta_value != ''",
            'tldr'
        ));
        
        $migrated = 0;
        $skipped = 0;
        
        foreach ($posts_to_migrate as $row) {
            $post_id = $row->post_id;
            $tldr_value = $row->meta_value;
            
            // Skip if new field already has data (don't overwrite)
            $existing = get_post_meta($post_id, 'bw_tldr', true);
            if (!empty($existing)) {
                $skipped++;
                continue;
            }
            
            // Migrate to new field
            update_post_meta($post_id, 'bw_tldr', $tldr_value);
            $migrated++;
        }
        
        // Mark migration as completed
        update_option('bw_tldr_migration_completed', true);
        update_option('bw_tldr_migration_date', current_time('mysql'));
        update_option('bw_tldr_migration_stats', [
            'migrated' => $migrated,
            'skipped' => $skipped,
            'total' => count($posts_to_migrate)
        ]);
        
        // Redirect back with success message
        $redirect_url = add_query_arg([
            'bw_tldr_migrated' => $migrated,
            'bw_tldr_skipped' => $skipped
        ], admin_url());
        
        wp_redirect($redirect_url);
        exit;
    }
}

// Initialize if in admin
if (is_admin()) {
    BW_TLDR_Migration::init();
}

// Show success message after migration
add_action('admin_notices', function() {
    if (isset($_GET['bw_tldr_migrated'])) {
        $migrated = intval($_GET['bw_tldr_migrated']);
        $skipped = intval($_GET['bw_tldr_skipped']);
        ?>
        <div class="notice notice-success is-dismissible">
            <h3>✅ TLDR Migration Complete!</h3>
            <p>
                <strong><?php echo number_format($migrated); ?> posts</strong> migrated successfully.<br>
                <?php if ($skipped > 0): ?>
                    <em><?php echo number_format($skipped); ?> posts skipped (already had bw_tldr data).</em>
                <?php endif; ?>
            </p>
            <p>
                <strong>Next steps:</strong>
            </p>
            <ol style="margin-left: 20px;">
                <li>Test posts to confirm TLDR displays correctly</li>
                <li>Update Breakdance templates with <code>[tldr]</code> shortcode</li>
                <li>After testing, you can safely delete the ACF 'tldr' field</li>
            </ol>
        </div>
        <?php
    }
});

