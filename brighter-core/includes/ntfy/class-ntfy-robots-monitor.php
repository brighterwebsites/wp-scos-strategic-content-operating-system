<?php
/**
 * ntfy robots.txt Monitor
 *
 * File: class-ntfy-robots-monitor.php
 * Version: 1.0.0
 *
 * Purpose: Verify robots.txt is accessible and not returning errors
 * Priority: MEDIUM - Important for SEO
 * 
 * @package BrighterCore
 * @subpackage Ntfy
 */

if (!defined('ABSPATH')) exit;

class Brighter_Ntfy_Robots_Monitor {
    
    private $client;
    private $rate_limit_option = 'ntfy_robots_last_alert';
    
    public function __construct($client) {
        $this->client = $client;
        $this->init();
    }
    
    private function init() {
        // Schedule daily checks
        add_action('init', [$this, 'schedule_checks']);
        add_action('ntfy_robots_check', [$this, 'check_robots_txt']);
    }
    
    /**
     * Schedule daily checks
     */
    public function schedule_checks() {
        if (!wp_next_scheduled('ntfy_robots_check')) {
            wp_schedule_event(time(), 'daily', 'ntfy_robots_check');
        }
    }
    
    /**
     * Check robots.txt accessibility
     */
    public function check_robots_txt() {
        $robots_url = home_url('/robots.txt');
        
        $response = wp_remote_get($robots_url, [
            'timeout' => 10,
            'sslverify' => false, // Some sites have SSL issues
        ]);
        
        if (is_wp_error($response)) {
            $this->send_alert('Request failed: ' . $response->get_error_message(), $robots_url);
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        // Alert on any non-200 status
        if ($status_code !== 200) {
            $this->send_alert('HTTP ' . $status_code . ' error', $robots_url);
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
        
        $message = "robots.txt issue detected on " . get_bloginfo('name') . "\n\n";
        $message .= "URL: " . $url . "\n";
        $message .= "Error: " . $error . "\n";
        $message .= "\nThis may impact search engine crawling.";
        
        $topic = Brighter_Ntfy_Notifications::get_topic_prefix() . '-robots';
        $result = $this->client->send($topic, $message, [
            'title' => '🤖 robots.txt Error',
            'priority' => 'default',
            'tags' => ['seo', 'warning'],
            'click' => $url,
        ]);
        
        if (!is_wp_error($result)) {
            update_option($this->rate_limit_option, time());
        }
        
        error_log('[ntfy Robots Monitor] Alert sent: ' . $error);
    }
}
