<?php
/**
 * ntfy Form Monitor
 *
 * File: class-ntfy-form-monitor.php
 * Version: 1.0.0
 *
 * Purpose: Send notifications on form submissions
 * Priority: LOW - Optional, per-site basis
 * 
 * @package BrighterCore
 * @subpackage Ntfy
 */

if (!defined('ABSPATH')) exit;

class Brighter_Ntfy_Form_Monitor {
    
    private $client;
    
    public function __construct($client) {
        $this->client = $client;
        $this->init();
    }
    
    private function init() {
        // Breakdance Forms (if available)
        add_action('breakdance_form_submission_success', [$this, 'handle_breakdance_form'], 10, 2);
        
        // Contact Form 7 (if available)
        add_action('wpcf7_mail_sent', [$this, 'handle_cf7_form']);
        
        // Gravity Forms (if available)
        add_action('gform_after_submission', [$this, 'handle_gravity_form'], 10, 2);
    }
    
    /**
     * Handle Breakdance form submission
     */
    public function handle_breakdance_form($form_data, $form_settings) {
        // Get form name
        $form_name = isset($form_settings['form_name']) ? $form_settings['form_name'] : 'Unknown Form';
        
        // Build message
        $message = "New form submission on " . get_bloginfo('name') . "\n\n";
        $message .= "Form: " . $form_name . "\n";
        
        // Add field data (limit to prevent spam)
        if (is_array($form_data) && count($form_data) <= 10) {
            $message .= "\nFields:\n";
            foreach ($form_data as $field => $value) {
                if (is_string($value) && strlen($value) < 100) {
                    $message .= "- " . $field . ": " . $value . "\n";
                }
            }
        }
        
        $this->send_notification($message, $form_name);
    }
    
    /**
     * Handle Contact Form 7 submission
     */
    public function handle_cf7_form($contact_form) {
        $form_name = $contact_form->title();
        
        $message = "New form submission on " . get_bloginfo('name') . "\n\n";
        $message .= "Form: " . $form_name . "\n";
        
        $this->send_notification($message, $form_name);
    }
    
    /**
     * Handle Gravity Forms submission
     */
    public function handle_gravity_form($entry, $form) {
        $form_name = isset($form['title']) ? $form['title'] : 'Unknown Form';
        
        $message = "New form submission on " . get_bloginfo('name') . "\n\n";
        $message .= "Form: " . $form_name . "\n";
        
        $this->send_notification($message, $form_name);
    }
    
    /**
     * Send form notification
     */
    private function send_notification($message, $form_name) {
        $site_slug = sanitize_title(get_bloginfo('name'));
        $topic = $site_slug . '-forms';
        
        $this->client->send($topic, $message, [
            'title' => '📝 Form: ' . $form_name,
            'priority' => 'default',
            'tags' => ['form', 'inbox_tray'],
            'click' => admin_url('admin.php?page=brighter_support&tab=support'),
        ]);
        
        error_log('[ntfy Form Monitor] Form submission notification sent: ' . $form_name);
    }
}
