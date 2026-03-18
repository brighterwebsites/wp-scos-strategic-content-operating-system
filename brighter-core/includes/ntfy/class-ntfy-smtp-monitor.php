<?php
/**
 * ntfy SMTP Monitor
 *
 * File: class-ntfy-smtp-monitor.php
 * Version: 1.0.0
 *
 * Purpose: Monitor email send failures and send ntfy alerts
 * Priority: HIGH - Critical for client communication
 * 
 * @package BrighterCore
 * @subpackage Ntfy
 */

if (!defined('ABSPATH')) exit;

class Brighter_Ntfy_SMTP_Monitor {
    
    private $client;
    private $rate_limit_option = 'ntfy_smtp_last_alert';
    
    public function __construct($client) {
        $this->client = $client;
        $this->init();
    }
    
    private function init() {
        // Hook into WordPress email failures
        add_action('wp_mail_failed', [$this, 'handle_mail_failure']);
    }
    
    /**
     * Handle email send failure
     */
    public function handle_mail_failure($wp_error) {
        // Rate limiting: Max 1 alert per 5 minutes
        $last_alert = get_option($this->rate_limit_option, 0);
        if ((time() - $last_alert) < 300) {
            return; // Too soon since last alert
        }
        
        // Get error details
        $error_message = $wp_error->get_error_message();
        $error_data = $wp_error->get_error_data();
        
        // Build notification message
        $message = "Email send failure on " . get_bloginfo('name') . "\n\n";
        $message .= "Error: " . $error_message . "\n";
        
        if (is_array($error_data) && isset($error_data['to'])) {
            $message .= "To: " . implode(', ', (array)$error_data['to']) . "\n";
        }
        
        $message .= "\nSite: " . home_url();
        
        // Send notification
        $topic = Brighter_Ntfy_Notifications::get_topic_prefix() . '-smtp';
        $result = $this->client->send($topic, $message, [
            'title' => '📧 Email Failure Alert',
            'priority' => 'urgent',
            'tags' => ['email', 'warning'],
            'click' => admin_url('admin.php?page=brighter_support&tab=monitoring'),
        ]);
        
        // Update rate limit
        if (!is_wp_error($result)) {
            update_option($this->rate_limit_option, time());
        }
        
        error_log('[ntfy SMTP Monitor] Email failure alert sent: ' . $error_message);
    }
}
