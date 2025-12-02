<?php
/**
 * Social Amplification Webhook Trigger
 *
 * Sends post data to Make.com when posts are published/updated
 *
 * @package BrighterCore
 * @subpackage SocialAmplification
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class BW_Social_Webhook_Trigger {

    /**
     * Make.com webhook URL
     */
    private $webhook_url;

    /**
     * Post types to trigger on
     */
    private $post_types = array('post', 'page', 'folio', 'projects');

    /**
     * Initialize hooks
     */
    public function init() {
        // Get webhook URL from settings (or use default for now)
        $this->webhook_url = get_option('bw_social_webhook_url', 'https://hook.us2.make.com/uokkbphdfvfdf7anyvmejmfi5nh47grq');

        // Hook into post publish/update
        add_action('publish_post', array($this, 'trigger_webhook'), 10, 2);
        add_action('publish_page', array($this, 'trigger_webhook'), 10, 2);
        add_action('publish_folio', array($this, 'trigger_webhook'), 10, 2);
        add_action('publish_projects', array($this, 'trigger_webhook'), 10, 2);

        // Add settings page hook
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Register webhook settings
     */
    public function register_settings() {
        register_setting('brighter_social_amplification', 'bw_social_webhook_url', array(
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));

        register_setting('brighter_social_amplification', 'bw_social_webhook_enabled', array(
            'sanitize_callback' => 'absint',
            'default' => 0
        ));
    }

    /**
     * Trigger webhook when post is published/updated
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function trigger_webhook($post_id, $post) {
        // Check if webhook is enabled
        if (!get_option('bw_social_webhook_enabled', 0)) {
            return;
        }

        // Don't trigger for revisions or autosaves
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        // Only trigger for specified post types
        if (!in_array($post->post_type, $this->post_types, true)) {
            return;
        }

        // Only trigger for published posts
        if ($post->post_status !== 'publish') {
            return;
        }

        // Get breadcrumb and content type
        $breadcrumb = BW_Breadcrumbs_Meta::get_breadcrumb($post_id);
        $content_type = BW_Content_Type_Helper::get_content_type($post_id, $post->post_type);

        // Prepare payload
        $payload = array(
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'post_title' => get_the_title($post_id),
            'post_type' => $post->post_type,
            'post_excerpt' => get_the_excerpt($post_id),
            'post_date' => get_the_date('c', $post_id),
            'post_modified' => get_the_modified_date('c', $post_id),
            'breadcrumb' => $breadcrumb,
            'content_type' => $content_type,
            'site_url' => get_site_url(),
            'trigger_time' => current_time('mysql'),
        );

        // Send webhook
        $this->send_webhook($payload);
    }

    /**
     * Send webhook to Make.com
     *
     * @param array $payload Data to send
     */
    private function send_webhook($payload) {
        if (empty($this->webhook_url)) {
            error_log('BW Social Amplification: Webhook URL not configured');
            return;
        }

        // Send async request (don't wait for response)
        wp_remote_post($this->webhook_url, array(
            'body' => json_encode($payload),
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 5,
            'blocking' => false, // Don't wait for response
            'sslverify' => true
        ));

        // Log for debugging
        error_log(sprintf(
            'BW Social Amplification: Webhook triggered for %s (ID: %d)',
            $payload['post_title'],
            $payload['post_id']
        ));
    }

    /**
     * Manual trigger via button (for future enhancement)
     *
     * @param int $post_id Post ID
     */
    public function manual_trigger($post_id) {
        $post = get_post($post_id);
        if ($post) {
            $this->trigger_webhook($post_id, $post);
        }
    }
}
