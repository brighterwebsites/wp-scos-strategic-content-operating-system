<?php
/**
 * ntfy Notifications Controller
 *
 * File: class-ntfy-notifications.php
 * Version: 1.0.0
 *
 * Purpose: Main controller for ntfy notification system
 * Coordinates all monitors and manages configuration
 * 
 * @package BrighterCore
 * @subpackage Ntfy
 */

if (!defined('ABSPATH')) exit;

class Brighter_Ntfy_Notifications {
    
    /**
     * ntfy client instance
     */
    private static $client = null;
    
    /**
     * Monitor instances
     */
    private static $monitors = [];
    
    /**
     * Initialize the notification system
     */
    public static function init() {
        // Support → Monitoring tab retired. Monitors still run when NTFY_ENABLED.

        // Only initialize monitors if enabled
        if (!self::is_enabled()) {
            return;
        }
        
        // Load client
        require_once BRIGHTER_CORE_PATH . 'includes/ntfy/class-ntfy-client.php';
        self::$client = new Brighter_Ntfy_Client();
        
        // Load and initialize monitors
        self::load_monitors();
    }
    
    /**
     * Check if ntfy is enabled
     */
    public static function is_enabled() {
        return defined('NTFY_ENABLED') && NTFY_ENABLED === true;
    }
    
    /**
     * Get ntfy client instance
     */
    public static function get_client() {
        return self::$client;
    }
    
    /**
     * Get topic prefix
     */
    public static function get_topic_prefix() {
        return defined('NTFY_TOPIC_PREFIX') ? NTFY_TOPIC_PREFIX : 'bw-agency';
    }
    
    /**
     * Load all monitor classes
     */
    private static function load_monitors() {
        $monitor_files = [
            'class-ntfy-smtp-monitor.php',
            'class-ntfy-downtime-monitor.php',
            'class-ntfy-robots-monitor.php',
            'class-ntfy-sitemap-monitor.php',
            'class-ntfy-cron-monitor.php',
            'class-ntfy-form-monitor.php',
        ];
        
        foreach ($monitor_files as $file) {
            $path = BRIGHTER_CORE_PATH . 'includes/ntfy/' . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        
        // Initialize monitors that are enabled
        if (self::is_monitor_enabled('smtp') && class_exists('Brighter_Ntfy_SMTP_Monitor')) {
            self::$monitors['smtp'] = new Brighter_Ntfy_SMTP_Monitor(self::$client);
        }
        
        if (self::is_monitor_enabled('downtime') && class_exists('Brighter_Ntfy_Downtime_Monitor')) {
            self::$monitors['downtime'] = new Brighter_Ntfy_Downtime_Monitor(self::$client);
        }
        
        if (self::is_monitor_enabled('robots') && class_exists('Brighter_Ntfy_Robots_Monitor')) {
            self::$monitors['robots'] = new Brighter_Ntfy_Robots_Monitor(self::$client);
        }
        
        if (self::is_monitor_enabled('sitemap') && class_exists('Brighter_Ntfy_Sitemap_Monitor')) {
            self::$monitors['sitemap'] = new Brighter_Ntfy_Sitemap_Monitor(self::$client);
        }
        
        if (self::is_monitor_enabled('cron') && class_exists('Brighter_Ntfy_Cron_Monitor')) {
            self::$monitors['cron'] = new Brighter_Ntfy_Cron_Monitor(self::$client);
        }
        
        if (self::is_monitor_enabled('forms') && class_exists('Brighter_Ntfy_Form_Monitor')) {
            self::$monitors['forms'] = new Brighter_Ntfy_Form_Monitor(self::$client);
        }
    }
    
    /**
     * Check if a specific monitor is enabled
     */
    public static function is_monitor_enabled($monitor) {
        $constant = 'NTFY_MONITOR_' . strtoupper($monitor);
        
        // If specific constant is defined, use it
        if (defined($constant)) {
            return constant($constant) === true;
        }
        
        // Otherwise, default to enabled if NTFY_ENABLED is true
        // Exception: forms are opt-in (default false)
        if ($monitor === 'forms') {
            return false;
        }
        
        return self::is_enabled();
    }
    
    /**
     * Get all loaded monitors
     */
    public static function get_monitors() {
        return self::$monitors;
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', ['Brighter_Ntfy_Notifications', 'init'], 20);
