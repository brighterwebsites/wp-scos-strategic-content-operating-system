<?php
/**
 * TLDR Meta Box
 * 
 * Adds a simple meta box to post editor for TLDR/Summary field
 * Replaces ACF 'tldr' field with native WordPress meta box
 */

if (!defined('ABSPATH')) exit;

class BW_TLDR_Meta_Box {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post', [__CLASS__, 'save_meta_box'], 10, 2);
    }
    
    /**
     * Add meta box to post types
     */
    public static function add_meta_box() {
        // Use the same post type filter as content strategy for consistency
        // This excludes WooCommerce products, orders, etc.
        $post_types = function_exists('bw_cs_post_types') ? bw_cs_post_types() : ['post', 'page'];
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'bw_tldr_meta_box',
                'TLDR / Article Summary',
                [__CLASS__, 'render_meta_box'],
                $post_type,
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Render meta box content
     */
    public static function render_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('bw_tldr_meta_box', 'bw_tldr_meta_box_nonce');
        
        // Get current value
        $value = get_post_meta($post->ID, 'bw_tldr', true);
        //Migration completed on all sites remove migration hint and code.
        // Check if old ACF field has data (for migration hint)
        $old_value = get_post_meta($post->ID, 'tldr', true);
        $show_migration_hint = !empty($old_value) && empty($value);
        
        ?>
        <div class="bw-tldr-meta-box">
            <?php if ($show_migration_hint): ?>
                <div class="notice notice-info inline" style="margin: 0 0 15px 0; padding: 10px;">
                    <p>
                        <strong>💡 Migration Available:</strong> 
                        This post has data in the old ACF 'tldr' field. 
                        <a href="<?php echo admin_url('admin.php?page=brighter_support'); ?>">Run migration</a> 
                        to copy it here, or manually copy/paste below.
                    </p>
                </div>
            <?php endif; ?>
            
            <p>
                <strong>What is TLDR?</strong> A brief summary (1-3 sentences) that appears at the top of your article.
                <br>
                <em>Used for: Voice search (Google speakable), SEO, social sharing, and Airtable sync.</em>
            </p>
            
            <textarea 
                id="bw_tldr" 
                name="bw_tldr" 
                rows="4" 
                style="width: 100%; max-width: 100%;"
                placeholder="Enter a brief summary of this article (1-3 sentences)..."
            ><?php echo esc_textarea($value); ?></textarea>
            
            <p class="description">
                <strong>Tip:</strong> Keep it concise (100-200 words max). This will display using the <code>[tldr]</code> shortcode.
            </p>
            
            <?php if (!empty($old_value) && !empty($value)): ?>
                <details style="margin-top: 15px;">
                    <summary style="cursor: pointer; color: #666;">
                        <small>📋 Old ACF field data (for reference)</small>
                    </summary>
                    <div style="background: #f5f5f5; padding: 10px; margin-top: 10px; border-left: 3px solid #ccc;">
                        <small><?php echo esc_html($old_value); ?></small>
                    </div>
                </details>
            <?php endif; ?>
        </div>
        
        <style>
            .bw-tldr-meta-box textarea {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                font-size: 14px;
                line-height: 1.6;
            }
        </style>
        <?php
    }
    
    /**
     * Save meta box data
     */
    public static function save_meta_box($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['bw_tldr_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['bw_tldr_meta_box_nonce'], 'bw_tldr_meta_box')) {
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
        
        // Sanitize and save
        if (isset($_POST['bw_tldr'])) {
            $tldr = wp_kses_post($_POST['bw_tldr']); // Allow basic HTML
            $tldr = trim($tldr);
            
            if (!empty($tldr)) {
                update_post_meta($post_id, 'bw_tldr', $tldr);
            } else {
                delete_post_meta($post_id, 'bw_tldr');
            }
        }
    }
}

// Initialize
BW_TLDR_Meta_Box::init();

