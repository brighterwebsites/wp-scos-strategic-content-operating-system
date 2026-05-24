<?php
/**
 * ntfy Downtime Monitor
 *
 * File: class-ntfy-downtime-monitor.php
 * Version: 1.0.0
 *
 * Purpose: Monitor site health with periodic checks
 * Priority: HIGH - Critical for uptime monitoring
 * 
 * @package BrighterCore
 * @subpackage Ntfy
 */

if (!defined('ABSPATH')) exit;

class Brighter_Ntfy_Downtime_Monitor {
    
    private $client;
    private $rate_limit_option = 'ntfy_downtime_last_alert';
    
    public function __construct($client) {
        $this->client = $client;
        $this->init();
    }
    
    private function init() {
        // Schedule health checks every 5 minutes
        add_action('init', [$this, 'schedule_health_checks']);
        add_action('ntfy_health_check', [$this, 'perform_health_check']);
    }
    
    /**
     * Schedule WP Cron health checks
     */
    public function schedule_health_checks() {
        if (!wp_next_scheduled('ntfy_health_check')) {
            wp_schedule_event(time(), 'ntfy_5min', 'ntfy_health_check');
        }
    }
    
    /**
     * Perform health check
     * 
     * Note: Internal checks won't detect complete PHP/Apache failures,
     * but will catch slow responses, HTTP errors, database issues, etc.
     */
    public function perform_health_check() {
        error_log('[ntfy Downtime Monitor] Starting health check');
        
        $issues = [];
        
        // 1. Check external HTTP request to home URL
        $http_check = $this->check_http_response();
        if (is_wp_error($http_check)) {
            $issues[] = $http_check->get_error_message();
        }
        
        // 2. Check database connectivity
        $db_check = $this->check_database();
        if (is_wp_error($db_check)) {
            $issues[] = $db_check->get_error_message();
        }
        
        // 3. Check filesystem writability
        $fs_check = $this->check_filesystem();
        if (is_wp_error($fs_check)) {
            $issues[] = $fs_check->get_error_message();
        }
        
        // If any issues found, send alert
        if (!empty($issues)) {
            $this->send_alert($issues);
        }
        
        error_log('[ntfy Downtime Monitor] Health check complete - Issues: ' . count($issues));
    }
    
    /**
     * Check HTTP response of site
     */
    private function check_http_response() {
        $home_url = home_url('/');
        
        $start_time = microtime(true);
        $response = wp_remote_get($home_url, [
            'timeout' => 15,
            'sslverify' => false, // Some sites have SSL issues
            'headers' => [
                'User-Agent' => 'Brighter-Health-Monitor/1.0',
            ],
        ]);
        $response_time = microtime(true) - $start_time;
        
        // Check for request errors
        if (is_wp_error($response)) {
            return new WP_Error('http_error', 'HTTP request failed: ' . $response->get_error_message());
        }
        
        // Check status code
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error('http_status', 'HTTP status ' . $status_code);
        }
        
        // Check response time (alert if > 5 seconds)
        if ($response_time > 5) {
            return new WP_Error('http_slow', sprintf('Slow response: %.2fs', $response_time));
        }
        
        return true;
    }
    
    /**
     * Check database connectivity
     */
    private function check_database() {
        global $wpdb;
        
        // Try a simple query
        $result = $wpdb->get_var("SELECT 1");
        
        if ($result !== '1') {
            return new WP_Error('db_error', 'Database query failed');
        }
        
        // Check if database is writable
        $test_option = 'ntfy_db_health_check';
        $test_value = time();
        update_option($test_option, $test_value);
        $retrieved = get_option($test_option);
        
        if ($retrieved != $test_value) {
            return new WP_Error('db_write', 'Database write/read test failed');
        }
        
        return true;
    }
    
    /**
     * Check filesystem writability
     */
    private function check_filesystem() {
        $upload_dir = wp_upload_dir();
        
        if (!empty($upload_dir['error'])) {
            return new WP_Error('fs_upload_dir', 'Upload directory error: ' . $upload_dir['error']);
        }
        
        $test_file = $upload_dir['basedir'] . '/.ntfy_health_check';
        
        // Try to write
        $write_result = @file_put_contents($test_file, time());
        if ($write_result === false) {
            return new WP_Error('fs_write', 'Cannot write to uploads directory');
        }
        
        // Try to read
        $read_result = @file_get_contents($test_file);
        if ($read_result === false) {
            return new WP_Error('fs_read', 'Cannot read from uploads directory');
        }
        
        // Clean up
        @unlink($test_file);
        
        return true;
    }
    
    /**
     * Send alert notification
     */
    private function send_alert($issues) {
        // Rate limiting: Max 1 alert per 15 minutes
        $last_alert = get_option($this->rate_limit_option, 0);
        if ((time() - $last_alert) < 900) {
            error_log('[ntfy Downtime Monitor] Alert suppressed by rate limit');
            return;
        }
        
        $message = "⚠️ Health check issues detected on " . get_bloginfo('name') . "\n\n";
        $message .= "Issues found:\n";
        foreach ($issues as $issue) {
            $message .= "• " . $issue . "\n";
        }
        $message .= "\nTime: " . current_time('Y-m-d H:i:s');
        $message .= "\nSite: " . home_url();
        
        $topic = Brighter_Ntfy_Notifications::get_topic_prefix() . '-downtime';
        $result = $this->client->send($topic, $message, [
            'title' => '⚠️ Site Health Alert',
            'priority' => 'high',
            'tags' => ['warning', 'health'],
            'click' => admin_url('admin.php?page=brighter_support&tab=support'),
        ]);
        
        if (!is_wp_error($result)) {
            update_option($this->rate_limit_option, time());
            error_log('[ntfy Downtime Monitor] Alert sent for issues: ' . implode(', ', $issues));
        } else {
            error_log('[ntfy Downtime Monitor] Failed to send alert: ' . $result->get_error_message());
        }
    }
}

// Add custom 5-minute cron interval
add_filter('cron_schedules', function($schedules) {
    $schedules['ntfy_5min'] = [
        'interval' => 300, // 5 minutes
        'display' => __('Every 5 Minutes (ntfy)')
    ];
    return $schedules;
});
