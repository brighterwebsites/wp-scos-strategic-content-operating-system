<?php
/**
 * ntfy Client Wrapper
 *
 * File: class-ntfy-client.php
 * Version: 1.0.0
 *
 * Purpose: HTTP client for sending notifications to ntfy server
 * 
 * @package BrighterCore
 * @subpackage Ntfy
 */

if (!defined('ABSPATH')) exit;

class Brighter_Ntfy_Client {
    
    /**
     * ntfy server URL
     */
    private $server_url;
    
    /**
     * HTTP Basic Auth username
     */
    private $username;
    
    /**
     * HTTP Basic Auth password
     */
    private $password;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->server_url = defined('NTFY_SERVER_URL') ? NTFY_SERVER_URL : '';
        $this->username = defined('NTFY_USERNAME') ? NTFY_USERNAME : '';
        $this->password = defined('NTFY_PASSWORD') ? NTFY_PASSWORD : '';
    }
    
    /**
     * Check if ntfy is configured
     * 
     * @return bool
     */
    public function is_configured() {
        return !empty($this->server_url) && !empty($this->username) && !empty($this->password);
    }
    
    /**
     * Send a notification to ntfy
     * 
     * @param string $topic Topic name (e.g., 'bw-agency-downtime')
     * @param string $message Message body
     * @param array $options Additional options (title, priority, tags, etc.)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function send($topic, $message, $options = []) {
        if (!$this->is_configured()) {
            return new WP_Error('ntfy_not_configured', 'ntfy is not configured. Check wp-config.php constants.');
        }
        
        // Build URL
        $url = trailingslashit($this->server_url) . $topic;
        
        // Build headers
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
            'Content-Type' => 'text/plain; charset=utf-8',
        ];
        
        // Add optional headers
        if (!empty($options['title'])) {
            $headers['Title'] = sanitize_text_field($options['title']);
        }
        if (!empty($options['priority'])) {
            $headers['Priority'] = sanitize_key($options['priority']); // urgent, high, default, low, min
        }
        if (!empty($options['tags'])) {
            $headers['Tags'] = is_array($options['tags']) ? implode(',', $options['tags']) : $options['tags'];
        }
        if (!empty($options['click'])) {
            $headers['Click'] = esc_url_raw($options['click']);
        }
        if (!empty($options['actions'])) {
            $headers['Actions'] = $options['actions']; // JSON string
        }
        
        // Send request
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $message,
            'timeout' => 10,
        ]);
        
        // Check for errors
        if (is_wp_error($response)) {
            error_log('[ntfy Client] Send failed: ' . $response->get_error_message());
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $error = new WP_Error('ntfy_send_failed', sprintf('ntfy returned status %d', $code));
            error_log('[ntfy Client] Send failed with status: ' . $code);
            return $error;
        }
        
        return true;
    }
    
    /**
     * Test connection to ntfy server
     * 
     * @return bool|WP_Error
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return new WP_Error('ntfy_not_configured', 'ntfy is not configured.');
        }
        
        return $this->send('bw-test', 'Test notification from ' . get_bloginfo('name'), [
            'title' => 'ntfy Connection Test',
            'priority' => 'low',
            'tags' => ['test', 'white_check_mark'],
        ]);
    }
}
