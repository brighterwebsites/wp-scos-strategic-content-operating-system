<?php
/**
 * ntfy Sitemap Monitor
 *
 * File: class-ntfy-sitemap-monitor.php
 * Version: 1.0.0
 *
 * Purpose: Verify XML sitemap is accessible and valid
 * Priority: MEDIUM - Important for SEO
 * 
 * @package BrighterCore
 * @subpackage Ntfy
 */

if (!defined('ABSPATH')) exit;

class Brighter_Ntfy_Sitemap_Monitor {
    
    private $client;
    private $rate_limit_option = 'ntfy_sitemap_last_alert';
    
    public function __construct($client) {
        $this->client = $client;
        $this->init();
    }
    
    private function init() {
        // Schedule daily checks
        add_action('init', [$this, 'schedule_checks']);
        add_action('ntfy_sitemap_check', [$this, 'check_sitemap']);
    }
    
    /**
     * Schedule daily checks
     */
    public function schedule_checks() {
        if (!wp_next_scheduled('ntfy_sitemap_check')) {
            wp_schedule_event(time(), 'daily', 'ntfy_sitemap_check');
        }
    }
    
    /**
     * Check sitemap accessibility
     */
    public function check_sitemap() {
        $sitemap_url = home_url('/sitemap.xml');
        
        $response = wp_remote_get($sitemap_url, [
            'timeout' => 10,
            'sslverify' => false,
        ]);
        
        if (is_wp_error($response)) {
            $this->send_alert('Request failed: ' . $response->get_error_message(), $sitemap_url);
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        // Alert on any non-200 status
        if ($status_code !== 200) {
            $this->send_alert('HTTP ' . $status_code . ' error', $sitemap_url);
            return;
        }
        
        // Check content type
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (strpos($content_type, 'xml') === false) {
            $this->send_alert('Invalid content type: ' . $content_type, $sitemap_url);
        }
    }
    
    /**
     * Send alert notification
     */
    private function send_alert($error, $url) {
        // Rate limiting: Max 1 alert per 24 hours
        $last_alert = get_option($this->rate_limit_option, 0);
        if ((time() - $last_alert) < 86400) {
            return;
        }
        
        $message = "XML Sitemap issue detected on " . get_bloginfo('name') . "\n\n";
        $message .= "URL: " . $url . "\n";
        $message .= "Error: " . $error . "\n";
        $message .= "\nThis may impact search engine indexing.";
        
        $topic = Brighter_Ntfy_Notifications::get_topic_prefix() . '-sitemap';
        $result = $this->client->send($topic, $message, [
            'title' => '🗺️ Sitemap Error',
            'priority' => 'default',
            'tags' => ['seo', 'warning'],
            'click' => $url,
        ]);
        
        if (!is_wp_error($result)) {
            update_option($this->rate_limit_option, time());
        }
        
        error_log('[ntfy Sitemap Monitor] Alert sent: ' . $error);
    }
}
