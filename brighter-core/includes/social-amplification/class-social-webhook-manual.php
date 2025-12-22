<?php
/**
 * Social Webhook Manual Trigger
 * 
 * Adds "Create Social Post" buttons in post editor and admin columns
 * Replaces automatic webhook trigger to reduce Make.com calls
 * 
 * @package BrighterCore
 * @subpackage SocialAmplification
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class BW_Social_Webhook_Manual {
    
    /**
     * Post types that support social posts
     */
    private $post_types = array('post', 'page', 'folio', 'projects', 'kb', 'news');
    
    /**
     * Initialize hooks
     */
    public function init() {
        // Add meta box to post editor
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        
        // Add admin column
        add_action('manage_posts_columns', array($this, 'add_admin_column'));
        add_action('manage_pages_columns', array($this, 'add_admin_column'));
        add_action('manage_posts_custom_column', array($this, 'render_admin_column'), 10, 2);
        add_action('manage_pages_custom_column', array($this, 'render_admin_column'), 10, 2);
        
        // AJAX handler for manual trigger
        add_action('wp_ajax_bw_trigger_social_webhook', array($this, 'ajax_trigger_webhook'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add meta box to post editor
     */
    public function add_meta_box() {
        foreach ($this->post_types as $post_type) {
            add_meta_box(
                'bw_social_webhook_trigger',
                '🚀 Social Amplification',
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render meta box content
     */
    public function render_meta_box($post) {
        // Check if webhook is configured
        $webhook_url = get_option('bw_social_webhook_url', '');
        $webhook_enabled = get_option('bw_social_webhook_enabled', 0);
        
        if (empty($webhook_url)) {
            ?>
            <p style="color: #d63638;">
                ⚠️ <strong>Webhook not configured</strong><br>
                <small>Go to Social Amplification settings to configure.</small>
            </p>
            <?php
            return;
        }
        
        if (!$webhook_enabled) {
            ?>
            <p style="color: #d63638;">
                ⚠️ <strong>Webhook disabled</strong><br>
                <small>Enable in Social Amplification settings.</small>
            </p>
            <?php
            return;
        }
        
        // Check if post is published
        if ($post->post_status !== 'publish') {
            ?>
            <p style="color: #d63638;">
                📝 <strong>Post must be published first</strong><br>
                <small>Save as published to enable social post creation.</small>
            </p>
            <?php
            return;
        }
        
        // Show last trigger time if available
        $last_trigger = get_post_meta($post->ID, '_bw_social_last_trigger', true);
        
        ?>
        <div class="bw-social-trigger-wrapper">
            <?php if ($last_trigger): ?>
                <p style="margin: 0 0 10px 0; color: #50575e; font-size: 12px;">
                    <strong>Last sent:</strong><br>
                    <?php echo esc_html(human_time_diff(strtotime($last_trigger), current_time('timestamp')) . ' ago'); ?>
                </p>
            <?php endif; ?>
            
            <button type="button" 
                    class="button button-primary button-large" 
                    id="bw-trigger-social-webhook"
                    data-post-id="<?php echo esc_attr($post->ID); ?>"
                    style="width: 100%; height: 40px; margin-bottom: 10px;">
                <span class="dashicons dashicons-megaphone" style="vertical-align: middle;"></span>
                Create Social Post
            </button>
            
            <p class="description" style="margin: 0; font-size: 12px;">
                Sends this post to Make.com for social media content generation.
            </p>
            
            <div id="bw-social-webhook-status" style="margin-top: 10px;"></div>
        </div>
        <?php
    }
    
    /**
     * Add admin column for social post trigger
     */
    public function add_admin_column($columns) {
        // Insert before date column
        $new_columns = array();
        foreach ($columns as $key => $value) {
            if ($key === 'date') {
                $new_columns['bw_social'] = '🚀 Social';
            }
            $new_columns[$key] = $value;
        }
        return $new_columns;
    }
    
    /**
     * Render admin column content
     */
    public function render_admin_column($column, $post_id) {
        if ($column !== 'bw_social') {
            return;
        }
        
        $post = get_post($post_id);
        $webhook_url = get_option('bw_social_webhook_url', '');
        $webhook_enabled = get_option('bw_social_webhook_enabled', 0);
        
        // Only show button for published posts with webhook configured
        if ($post->post_status === 'publish' && !empty($webhook_url) && $webhook_enabled) {
            $last_trigger = get_post_meta($post_id, '_bw_social_last_trigger', true);
            ?>
            <button type="button" 
                    class="button button-small bw-trigger-social-webhook-inline"
                    data-post-id="<?php echo esc_attr($post_id); ?>"
                    title="Create social post">
                <span class="dashicons dashicons-megaphone" style="font-size: 13px; width: 13px; height: 13px;"></span>
                Create
            </button>
            <?php if ($last_trigger): ?>
                <br><small style="color: #50575e; font-size: 11px;">
                    <?php echo esc_html(human_time_diff(strtotime($last_trigger), current_time('timestamp'))); ?> ago
                </small>
            <?php endif; ?>
            <?php
        } else {
            echo '<span style="color: #d63638;">—</span>';
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        // Only load on post edit and list screens
        if (!in_array($hook, array('post.php', 'post-new.php', 'edit.php'))) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Handle meta box button click
            $(document).on('click', '#bw-trigger-social-webhook', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var postId = $btn.data('post-id');
                var $status = $('#bw-social-webhook-status');
                
                // Disable button
                $btn.prop('disabled', true).text('Sending...');
                $status.html('');
                
                // Send AJAX request
                $.post(ajaxurl, {
                    action: 'bw_trigger_social_webhook',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('bw_social_webhook'); ?>'
                }, function(response) {
                    if (response.success) {
                        $status.html('<p style="color: #00a32a; margin: 0;">✅ ' + response.data.message + '</p>');
                        $btn.html('<span class="dashicons dashicons-yes" style="vertical-align: middle;"></span> Sent!');
                        
                        // Reset button after 3 seconds
                        setTimeout(function() {
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-megaphone" style="vertical-align: middle;"></span> Create Social Post');
                        }, 3000);
                    } else {
                        $status.html('<p style="color: #d63638; margin: 0;">❌ Error: ' + response.data.message + '</p>');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-megaphone" style="vertical-align: middle;"></span> Create Social Post');
                    }
                }).fail(function() {
                    $status.html('<p style="color: #d63638; margin: 0;">❌ Request failed</p>');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-megaphone" style="vertical-align: middle;"></span> Create Social Post');
                });
            });
            
            // Handle inline button click (in admin columns)
            $(document).on('click', '.bw-trigger-social-webhook-inline', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var postId = $btn.data('post-id');
                var originalHtml = $btn.html();
                
                // Disable button
                $btn.prop('disabled', true).html('⏳');
                
                // Send AJAX request
                $.post(ajaxurl, {
                    action: 'bw_trigger_social_webhook',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('bw_social_webhook'); ?>'
                }, function(response) {
                    if (response.success) {
                        $btn.html('✅');
                        setTimeout(function() {
                            $btn.prop('disabled', false).html(originalHtml);
                        }, 2000);
                    } else {
                        $btn.html('❌');
                        alert('Error: ' + response.data.message);
                        setTimeout(function() {
                            $btn.prop('disabled', false).html(originalHtml);
                        }, 2000);
                    }
                }).fail(function() {
                    $btn.html('❌');
                    alert('Request failed');
                    setTimeout(function() {
                        $btn.prop('disabled', false).html(originalHtml);
                    }, 2000);
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for manual webhook trigger
     */
    public function ajax_trigger_webhook() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bw_social_webhook')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        // Get post ID
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
        
        // Get post
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => 'Post not found'));
        }
        
        // Check if published
        if ($post->post_status !== 'publish') {
            wp_send_json_error(array('message' => 'Post must be published'));
        }
        
        // Check if webhook URL is configured
        $webhook_url = get_option('bw_social_webhook_url', '');
        if (empty($webhook_url)) {
            wp_send_json_error(array('message' => 'Webhook URL not configured in settings'));
        }
        
        // Trigger webhook via the BW_Social_Webhook_Trigger class
        global $bw_social_webhook_trigger;
        if (!$bw_social_webhook_trigger || !method_exists($bw_social_webhook_trigger, 'manual_trigger')) {
            wp_send_json_error(array('message' => 'Webhook trigger not available'));
        }
        
        // Call manual trigger
        $success = $bw_social_webhook_trigger->manual_trigger($post_id);
        
        if ($success) {
            // Update last trigger time
            update_post_meta($post_id, '_bw_social_last_trigger', current_time('mysql'));
            
            wp_send_json_success(array(
                'message' => 'Social post sent to Make.com! Check your scenario for processing.',
                'timestamp' => current_time('mysql')
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to trigger webhook. Check error logs for details.'));
        }
    }
}

// Initialize
$bw_social_webhook_manual = new BW_Social_Webhook_Manual();
$bw_social_webhook_manual->init();

