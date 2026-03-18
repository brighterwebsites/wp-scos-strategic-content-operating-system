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
     * Post types that support social posts (all content-strategy post types)
     */
    /**
     * Get post types for social amplification
     * Returns all public post types that should have social amplification
     */
    private function get_post_types() {
        // Use Content Strategy post types if available
        if (function_exists('bw_cs_post_types')) {
            $cs_types = bw_cs_post_types();
        } else {
            // Fallback: get all public post types
            $cs_types = get_post_types([
                'public' => true,
                'show_ui' => true
            ], 'names');
        }
        
        // Also explicitly include these post types (in case they register late)
        $explicit_types = array('post', 'page', 'folio', 'projects', 'kb', 'news');
        
        // Merge and dedupe
        $all_types = array_unique(array_merge($cs_types, $explicit_types));
        
        // Exclude unwanted types
        $exclude = array(
            'attachment', 
            'nav_menu_item', 
            'wp_block', 
            'wp_template', 
            'wp_template_part', 
            'wp_navigation',
            'product',
            'product_variation',
            'shop_order',
            'shop_coupon',
            'shop_webhook'
        );
        
        return array_values(array_diff($all_types, $exclude));
    }

    /**
     * Initialize hooks
     */
    public function init() {
        // Add meta box to post editor
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        
        // Add admin column for all post types
        // Hook very late (priority 99) to ensure all CPTs (projects, folio, etc.) are registered
        add_action('init', array($this, 'register_admin_columns'), 99);
        
        // AJAX handler for manual trigger
        add_action('wp_ajax_bw_trigger_social_webhook', array($this, 'ajax_trigger_webhook'));
        
        // Output inline JavaScript in footer (works on all admin pages)
        add_action('admin_footer', array($this, 'output_inline_script'));
    }
    
    /**
     * Add meta box to post editor
     */
    public function add_meta_box() {
        foreach ($this->get_post_types() as $post_type) {
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
        
        if (empty($webhook_url)) {
            ?>
            <p style="color: #d63638;">
                ⚠️ <strong>Webhook not configured</strong><br>
                <small>Go to Social Amplification settings to configure URL:</small>
                <code style="display: block; margin-top: 5px; font-size: 11px;">
                    https://hook.us2.make.com/j0ajwegj6uftmc5j4gwxm9p42epfiopk
                </code>
            </p>
            <?php
            return;
        }
        
        // Note: Manual triggers work regardless of "Enable Webhook" setting
        // That setting only controls automatic triggers on post publish
        
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
     * Register admin column hooks for all content-strategy post types
     * Runs on init:20 so CPTs (faq, projects, etc.) are registered
     */
    public function register_admin_columns() {
        foreach ($this->get_post_types() as $post_type) {
            add_action("manage_{$post_type}_posts_columns", array($this, 'add_admin_column'));
            add_action("manage_{$post_type}_posts_custom_column", array($this, 'render_admin_column'), 10, 2);
        }
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
     *
     * Always shows a "Create" button. Same action as single-post meta box (Make "create social post").
     * Button disabled when not published or webhook not configured; tooltip explains why.
     */
    public function render_admin_column($column, $post_id) {
        if ($column !== 'bw_social') {
            return;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            echo '—';
            return;
        }
        
        $webhook_url = get_option('bw_social_webhook_url', '');
        $can_trigger = ($post->post_status === 'publish' && !empty($webhook_url));
        $title = 'Create social post (sends to Make.com)';
        if ($post->post_status !== 'publish') {
            $title = 'Publish first to create social post';
        } elseif (empty($webhook_url)) {
            $title = 'Configure webhook in Social Amplification settings';
        }
        
        $last_trigger = get_post_meta($post_id, '_bw_social_last_trigger', true);
        ?>
        <button type="button" 
                class="button button-small bw-trigger-social-webhook-inline <?php echo $can_trigger ? '' : 'disabled'; ?>"
                data-post-id="<?php echo esc_attr($post_id); ?>"
                data-can-trigger="<?php echo $can_trigger ? '1' : '0'; ?>"
                title="<?php echo esc_attr($title); ?>"
                <?php echo $can_trigger ? '' : ' disabled="disabled"'; ?>>
            <span class="dashicons dashicons-megaphone" style="font-size: 13px; width: 13px; height: 13px;"></span>
            Create
        </button>
        <?php if ($last_trigger): ?>
            <br><small style="color: #50575e; font-size: 11px;">
                <?php echo esc_html(human_time_diff(strtotime($last_trigger), current_time('timestamp'))); ?> ago
            </small>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Output inline JavaScript in admin footer
     */
    public function output_inline_script() {
        // Output on all admin pages (button may appear in meta box or admin columns)
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
           //  console.log('BW Social: JavaScript loaded');
          //  console.log('BW Social: Button exists?', $('#bw-trigger-social-webhook').length > 0);
            
            // Handle meta box button click
            $(document).on('click', '#bw-trigger-social-webhook', function(e) {
                console.log('BW Social: Button clicked!');
                e.preventDefault();
                var $btn = $(this);
                var postId = $btn.data('post-id');
                var $status = $('#bw-social-webhook-status');
                
                console.log('BW Social: Post ID:', postId);
                console.log('BW Social: ajaxurl:', ajaxurl);
                
                // Disable button
                $btn.prop('disabled', true).text('Sending...');
                $status.html('');
                
                console.log('BW Social: Sending AJAX request...');
                
                // Send AJAX request
                $.post(ajaxurl, {
                    action: 'bw_trigger_social_webhook',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('bw_social_webhook'); ?>'
                }, function(response) {
                    console.log('BW Social: AJAX response received:', response);
                    if (response.success) {
                        $status.html('<p style="color: #00a32a; margin: 0;">✅ ' + response.data.message + '</p>');
                        $btn.html('<span class="dashicons dashicons-yes" style="vertical-align: middle;"></span> Sent!');
                        
                        // Reset button after 3 seconds
                        setTimeout(function() {
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-megaphone" style="vertical-align: middle;"></span> Create Social Post');
                        }, 3000);
                    } else {
                        console.log('BW Social: AJAX error:', response.data.message);
                        $status.html('<p style="color: #d63638; margin: 0;">❌ Error: ' + response.data.message + '</p>');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-megaphone" style="vertical-align: middle;"></span> Create Social Post');
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    console.log('BW Social: AJAX request failed!');
                    console.log('BW Social: Status:', textStatus);
                    console.log('BW Social: Error:', errorThrown);
                    console.log('BW Social: Response:', jqXHR.responseText);
                    $status.html('<p style="color: #d63638; margin: 0;">❌ Request failed: ' + textStatus + '</p>');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-megaphone" style="vertical-align: middle;"></span> Create Social Post');
                });
            });
            
            // Handle inline button click (in admin columns)
            $(document).on('click', '.bw-trigger-social-webhook-inline', function(e) {
                e.preventDefault();
                var $btn = $(this);
                if ($btn.prop('disabled') || $btn.data('can-trigger') !== 1) return;
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
        error_log('=== BW SOCIAL AJAX DEBUG START ===');
        error_log('AJAX handler called');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bw_social_webhook')) {
            error_log('AJAX ERROR: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        error_log('Nonce verified');
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            error_log('AJAX ERROR: User lacks permissions');
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        error_log('Permissions OK');
        
        // Get post ID
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        error_log('Post ID: ' . $post_id);
        if (!$post_id) {
            error_log('AJAX ERROR: Invalid post ID');
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
        
        // Get post
        $post = get_post($post_id);
        if (!$post) {
            error_log('AJAX ERROR: Post not found');
            wp_send_json_error(array('message' => 'Post not found'));
        }
        error_log('Post found: ' . $post->post_title);
        
        // Check if published
        if ($post->post_status !== 'publish') {
            error_log('AJAX ERROR: Post not published (status: ' . $post->post_status . ')');
            wp_send_json_error(array('message' => 'Post must be published'));
        }
        error_log('Post is published');
        
        // Check if webhook URL is configured
        $webhook_url = get_option('bw_social_webhook_url', '');
        error_log('Webhook URL from settings: ' . ($webhook_url ? $webhook_url : '(empty)'));
        if (empty($webhook_url)) {
            error_log('AJAX ERROR: Webhook URL not configured');
            wp_send_json_error(array('message' => 'Webhook URL not configured in settings'));
        }
        
        // Trigger webhook via the BW_Social_Webhook_Trigger class
        global $bw_social_webhook_trigger;
        error_log('Global webhook trigger exists: ' . (isset($bw_social_webhook_trigger) ? 'YES' : 'NO'));
        if (!$bw_social_webhook_trigger || !method_exists($bw_social_webhook_trigger, 'manual_trigger')) {
            error_log('AJAX ERROR: Webhook trigger class not available');
            wp_send_json_error(array('message' => 'Webhook trigger not available'));
        }
        
        error_log('Calling manual_trigger() method...');
        // Call manual trigger
        $success = $bw_social_webhook_trigger->manual_trigger($post_id);
        error_log('manual_trigger() returned: ' . ($success ? 'true' : 'false'));
        
        if ($success) {
            // Update last trigger time
            update_post_meta($post_id, '_bw_social_last_trigger', current_time('mysql'));
            error_log('SUCCESS: Webhook sent, timestamp updated');
            error_log('=== BW SOCIAL AJAX DEBUG END ===');
            
            wp_send_json_success(array(
                'message' => 'Social post sent to Make.com! Check your scenario for processing.',
                'timestamp' => current_time('mysql')
            ));
        } else {
            error_log('AJAX ERROR: manual_trigger() returned false');
            error_log('=== BW SOCIAL AJAX DEBUG END ===');
            wp_send_json_error(array('message' => 'Failed to trigger webhook. Check error logs for details.'));
        }
    }
}

// Initialize
$bw_social_webhook_manual = new BW_Social_Webhook_Manual();
$bw_social_webhook_manual->init();

