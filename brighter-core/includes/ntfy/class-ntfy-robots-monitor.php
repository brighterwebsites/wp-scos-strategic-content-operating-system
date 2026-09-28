<?php
// v1.1 | 2026-09-27
/**
 * ntfy robots.txt Monitor
 *
 * File: class-ntfy-robots-monitor.php
 * Version: 1.1.0
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
     * Check the origin robots.txt for a real User-agent record.
     *
     * Requests the public URL with DNS pinned to this server, so Cloudflare
     * Bot Fight Mode cannot answer for the origin.
     */
    public function check_robots_txt() {
        $robots_url = home_url('/robots.txt');

        $response = Brighter_Ntfy_Origin_Request::get($robots_url, [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            $this->send_alert($response->get_error_message(), $robots_url);
            return;
        }

        $body = wp_remote_retrieve_body($response);
        if (Brighter_Ntfy_Origin_Request::is_cloudflare_challenge($body)) {
            $this->send_alert('Response is a Cloudflare challenge, not robots.txt. Origin pin missed.', $robots_url);
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $this->send_alert('HTTP ' . $status_code . ' error', $robots_url);
            return;
        }

        if (stripos($body, 'user-agent:') === false) {
            $this->send_alert('HTTP 200 but body is not a robots.txt (no User-agent record).', $robots_url);
            return;
        }

        error_log('[ntfy Robots Monitor] Origin robots.txt OK: ' . $robots_url);
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
