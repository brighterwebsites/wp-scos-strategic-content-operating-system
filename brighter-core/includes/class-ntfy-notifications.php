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
        // Always add admin UI hooks (so tab shows even if not configured yet)
        add_filter('brighter_support_tabs', [__CLASS__, 'add_monitoring_tab'], 20);
        add_filter('brighter_support_tab_content', [__CLASS__, 'render_monitoring_tab'], 10, 2);
        
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
    
    /**
     * Add monitoring tab to Agency Settings
     * Only show to agency users (@brighterwebsites.com.au)
     */
    public static function add_monitoring_tab($tabs) {
        // Check if user is an agency user
        if (function_exists('brighter_support_is_agency_user') && brighter_support_is_agency_user()) {
            $tabs['monitoring'] = 'Monitoring';
        }
        return $tabs;
    }
    
    /**
     * Render monitoring tab content
     */
    public static function render_monitoring_tab($content, $current_tab) {
        if ($current_tab !== 'monitoring') {
            return $content;
        }
        
        ob_start();
        ?>
        <div class="wrap brighter-monitoring">
            <h2>ntfy Monitoring Status</h2>
            
            <?php if (!self::is_enabled()): ?>
                <div class="notice notice-warning">
                    <p><strong>ntfy is not enabled.</strong> Add the following to your <code>wp-config.php</code>:</p>
                    <pre style="background: #f5f5f5; padding: 15px; border-left: 4px solid #ffb900;">
define('NTFY_ENABLED', true);
define('NTFY_SERVER_URL', 'https://ntfy.bweb1.com.au');
define('NTFY_USERNAME', 'vanessa');
define('NTFY_PASSWORD', 'your-password-here');</pre>
                </div>
            <?php else: ?>
                
                <!-- Connection Status -->
                <div class="card" style="max-width: 100%; margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccc; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                    <h3 style="margin-top: 0;">Connection Status</h3>
                    <?php
                    $client = self::get_client();
                    if ($client && $client->is_configured()):
                    ?>
                        <p style="color: #46b450; font-size: 16px;">✅ <strong>Connected</strong></p>
                        <p>Server: <code><?php echo esc_html(defined('NTFY_SERVER_URL') ? NTFY_SERVER_URL : 'Not set'); ?></code></p>
                        <p>Topic Prefix: <code><?php echo esc_html(self::get_topic_prefix()); ?></code></p>
                        
                        <form method="post" action="" style="margin-top: 15px;">
                            <?php wp_nonce_field('test_ntfy_connection', 'test_ntfy_nonce'); ?>
                            <input type="hidden" name="action" value="test_ntfy">
                            <button type="submit" class="button button-secondary">Send Test Notification</button>
                        </form>
                        
                        <?php
                        // Handle test notification
                        if (isset($_POST['action']) && $_POST['action'] === 'test_ntfy' && 
                            isset($_POST['test_ntfy_nonce']) && wp_verify_nonce($_POST['test_ntfy_nonce'], 'test_ntfy_connection')) {
                            $result = $client->send('bw-test', 
                                'Manual test from ' . get_bloginfo('name') . ' (' . home_url() . ')',
                                [
                                    'title' => '✅ Manual ntfy Test',
                                    'priority' => 'default',
                                    'tags' => ['test', 'white_check_mark'],
                                    'click' => admin_url('admin.php?page=brighter_support&tab=monitoring'),
                                ]
                            );
                            
                            if (is_wp_error($result)) {
                                echo '<div class="notice notice-error inline"><p>❌ Test failed: ' . esc_html($result->get_error_message()) . '</p></div>';
                            } else {
                                echo '<div class="notice notice-success inline"><p>✅ Test notification sent! Check your ntfy app.</p></div>';
                            }
                        }
                        ?>
                    <?php else: ?>
                        <p style="color: #dc3232; font-size: 16px;">❌ <strong>Not configured</strong></p>
                        <p>Check your wp-config.php constants.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Active Monitors -->
                <div class="card" style="max-width: 100%; margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccc; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                    <h3 style="margin-top: 0;">Active Monitors</h3>
                    
                    <table class="widefat" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th>Monitor</th>
                                <th>Status</th>
                                <th>Topic</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $monitor_info = [
                                'smtp' => ['name' => 'Email (SMTP)', 'desc' => 'Alerts on email send failures', 'topic' => 'smtp'],
                                'downtime' => ['name' => 'Site Health', 'desc' => '5-minute health checks', 'topic' => 'downtime'],
                                'robots' => ['name' => 'robots.txt', 'desc' => 'Daily verification', 'topic' => 'robots'],
                                'sitemap' => ['name' => 'XML Sitemap', 'desc' => 'Daily verification', 'topic' => 'sitemap'],
                                'cron' => ['name' => 'WP Cron', 'desc' => 'Missed scheduled events', 'topic' => 'cron'],
                                'forms' => ['name' => 'Form Submissions', 'desc' => 'New form submissions', 'topic' => 'forms'],
                            ];
                            
                            foreach ($monitor_info as $key => $info) {
                                $enabled = self::is_monitor_enabled($key);
                                $status_icon = $enabled ? '✅' : '⚪';
                                $status_text = $enabled ? 'Enabled' : 'Disabled';
                                $topic = self::get_topic_prefix() . '-' . $info['topic'];
                                if ($key === 'forms') {
                                    $topic = get_bloginfo('name') . '-forms';
                                }
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($info['name']); ?></strong></td>
                                    <td><?php echo esc_html($status_icon . ' ' . $status_text); ?></td>
                                    <td><code><?php echo esc_html($topic); ?></code></td>
                                    <td><?php echo esc_html($info['desc']); ?></td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa;">
                        <p style="margin: 0;"><strong>💡 To enable/disable monitors:</strong> Add constants to <code>wp-config.php</code></p>
                        <pre style="background: #fff; padding: 10px; margin-top: 10px; font-size: 12px;">
// Enable/disable specific monitors
define('NTFY_MONITOR_SMTP', true);
define('NTFY_MONITOR_DOWNTIME', true);
define('NTFY_MONITOR_ROBOTS', true);
define('NTFY_MONITOR_SITEMAP', true);
define('NTFY_MONITOR_CRON', true);
define('NTFY_MONITOR_FORMS', false);  // Opt-in</pre>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
        
        <style>
        .brighter-monitoring .card h3 {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .brighter-monitoring .notice.inline {
            margin: 15px 0;
        }
        </style>
        <?php
        return ob_get_clean();
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', ['Brighter_Ntfy_Notifications', 'init'], 20);
