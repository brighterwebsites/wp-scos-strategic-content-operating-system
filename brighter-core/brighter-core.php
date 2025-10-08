<?php
/**
 * Brighter Core MU Plugin Loader
 * Version: 4.1.0
 *
 * File: brighter-core.php
 * Purpose: Load all Brighter Core modules and manage plugin infrastructure
 *
 * Changelog:
 * 4.1.0 - Performance: Lazy loading, conditional module loading, optimized hooks
 * 4.0.0 - Version sync fixed, improved module loading with error handling
 */

if (!defined('ABSPATH')) exit;

// Define plugin constants
define('BRIGHTER_CORE_VERSION', '4.1.0');
define('BRIGHTER_CORE_PATH', plugin_dir_path(__FILE__));
define('BRIGHTER_CORE_URL', plugin_dir_url(__FILE__));

/**
 * Module configuration with lazy loading support
 * 
 * Format: 'module-file' => [
 *     'enabled' => true/false,
 *     'admin_only' => true/false (load only in admin),
 *     'priority' => 10 (lower = loads earlier)
 * ]
 */
function brighter_get_module_config() {
    static $config = null;
    
    if ($config !== null) {
        return $config;
    }
    
    $config = [
        // Core modules (always load)
        'brighter-buinessinfo' => [
            'enabled' => true,
            'admin_only' => false,
            'priority' => 1
        ],
        'brighter-support' => [
            'enabled' => true,
            'admin_only' => true,
            'priority' => 2
        ],
        
        // Feature modules (conditional loading)
        'brighter-support-image-settings' => [
            'enabled' => true,
            'admin_only' => true,
            'priority' => 10
        ],
        'custom-admin' => [
            'enabled' => true,
            'admin_only' => true,
            'priority' => 5
        ],
        'image-optimisation' => [
            'enabled' => true,
            'admin_only' => false,
            'priority' => 15
        ],
        'bw-custposts' => [
            'enabled' => true,
            'admin_only' => false,
            'priority' => 8
        ],
        
        // Optional modules (enable as needed)
        'brighter-tweaks' => [
            'enabled' => true,
            'admin_only' => true,
            'priority' => 20
        ],
        'brighter-settings' => [
            'enabled' => false,  // Enable if file exists
            'admin_only' => true,
            'priority' => 10
        ],
        'custom-wpemail' => [
            'enabled' => false,
            'admin_only' => false,
            'priority' => 10
        ],
        'helpers' => [
            'enabled' => false,
            'admin_only' => false,
            'priority' => 5
        ],
        'login-styling' => [
            'enabled' => false,
            'admin_only' => false,
            'priority' => 10
        ],
        'php-limits' => [
            'enabled' => false,
            'admin_only' => true,
            'priority' => 10
        ],
        'privacy-policy-style' => [
            'enabled' => false,
            'admin_only' => false,
            'priority' => 10
        ],
        'technical-settings' => [
            'enabled' => false,
            'admin_only' => true,
            'priority' => 10
        ],
        'bw-content-strategy' => [
            'enabled' => false,
            'admin_only' => true,
            'priority' => 10
        ],
    ];
    
    return $config;
}

/**
 * Load modules based on context (admin vs frontend)
 */
function brighter_load_modules() {
    $config = brighter_get_module_config();
    $is_admin = is_admin();
    $modules_to_load = [];
    
    // Filter modules based on context
    foreach ($config as $module => $settings) {
        if (!$settings['enabled']) continue;
        
        // Skip admin-only modules on frontend
        if ($settings['admin_only'] && !$is_admin) continue;
        
        $modules_to_load[$module] = $settings['priority'];
    }
    
    // Sort by priority (lower number = higher priority)
    asort($modules_to_load);
    
    // Load modules in order
    foreach (array_keys($modules_to_load) as $module) {
        brighter_load_module($module);
    }
}

/**
 * Load a single module with error handling
 */
function brighter_load_module($module) {
    static $loaded = [];
    
    // Prevent double-loading
    if (isset($loaded[$module])) {
        return true;
    }
    
    $path = BRIGHTER_CORE_PATH . 'includes/' . $module . '.php';
    
    if (!file_exists($path)) {
        error_log("Brighter Core: Module file not found: $path");
        return false;
    }
    
    try {
        require_once $path;
        $loaded[$module] = true;
        return true;
    } catch (Exception $e) {
        error_log("Brighter Core: Error loading module {$module}: " . $e->getMessage());
        return false;
    }
}

// Load modules on init (after WordPress is ready)
add_action('init', 'brighter_load_modules', 1);

/**
 * Enqueue admin styles (only when needed, deferred loading)
 */
function brighter_core_enqueue_admin_styles($hook) {
    // Only load on our admin pages
    if (strpos($hook, 'brighter') === false) {
        return;
    }
    
    // Check if CSS file exists (cached check)
    static $css_exists = null;
    if ($css_exists === null) {
        $css_exists = file_exists(BRIGHTER_CORE_PATH . 'css/admin-support.css');
    }
    
    if (!$css_exists) {
        return;
    }
    
    wp_enqueue_style(
        'brighter-admin-support', 
        BRIGHTER_CORE_URL . 'css/admin-support.css',
        array(),
        BRIGHTER_CORE_VERSION
    );
}
add_action('admin_enqueue_scripts', 'brighter_core_enqueue_admin_styles', 20);

/**
 * Enqueue frontend styles (only when needed, cached check)
 */
function brighter_core_enqueue_frontend_styles() {
    if (is_admin()) return;
    
    // Cached file existence check
    static $css_exists = null;
    if ($css_exists === null) {
        $css_exists = file_exists(BRIGHTER_CORE_PATH . 'css/frontend.css');
    }
    
    if (!$css_exists) {
        return;
    }
    
    wp_enqueue_style(
        'brighter-frontend', 
        BRIGHTER_CORE_URL . 'css/frontend.css',
        array(),
        BRIGHTER_CORE_VERSION
    );
}
add_action('wp_enqueue_scripts', 'brighter_core_enqueue_frontend_styles', 20);

/**
 * Plugin activation hook
 */
function brighter_core_activate() {
    if (!get_option('brighter_core_version')) {
        update_option('brighter_core_version', BRIGHTER_CORE_VERSION);
        update_option('brighter_core_activated', current_time('mysql'));
        
        // Set default cache duration
        update_option('brighter_cache_duration', HOUR_IN_SECONDS);
    }
    
    // Clear any existing caches on activation
    wp_cache_flush();
}
register_activation_hook(__FILE__, 'brighter_core_activate');

/**
 * Admin notice if critical modules are missing (cached to reduce checks)
 */
add_action('admin_notices', function() {
    // Check once per day
    $transient_key = 'brighter_module_check';
    $cached_check = get_transient($transient_key);
    
    if ($cached_check !== false) {
        if ($cached_check === 'ok') return;
        // Show cached error
        echo $cached_check;
        return;
    }
    
    $critical_modules = ['brighter-buinessinfo', 'brighter-support'];
    $missing = [];
    
    foreach ($critical_modules as $module) {
        $path = BRIGHTER_CORE_PATH . 'includes/' . $module . '.php';
        if (!file_exists($path)) {
            $missing[] = $module;
        }
    }
    
    if (!empty($missing)) {
        $notice = '<div class="notice notice-error"><p>';
        $notice .= '<strong>Brighter Core:</strong> Missing critical modules: ' . implode(', ', $missing);
        $notice .= '</p></div>';
        
        set_transient($transient_key, $notice, DAY_IN_SECONDS);
        echo $notice;
    } else {
        set_transient($transient_key, 'ok', DAY_IN_SECONDS);
    }
});

/**
 * Performance monitoring (only in debug mode)
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('shutdown', function() {
        if (!current_user_can('manage_options')) return;
        
        $queries = get_num_queries();
        $timer = timer_stop(0, 3);
        $memory = size_format(memory_get_peak_usage());
        
        error_log(sprintf(
            'Brighter Core Performance: %d queries in %s seconds using %s memory',
            $queries,
            $timer,
            $memory
        ));
    });
}

/**
 * Heartbeat optimization - reduce frequency
 */
add_filter('heartbeat_settings', function($settings) {
    // Slow down heartbeat to reduce server load
    $settings['interval'] = 60; // Default is 15 seconds, we set to 60
    return $settings;
});

/**
 * Disable unnecessary features for performance
 */
add_action('init', function() {
    // Remove emoji support (saves HTTP requests)
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');
    
    // Remove RSD link (rarely used)
    remove_action('wp_head', 'rsd_link');
    
    // Remove Windows Live Writer link
    remove_action('wp_head', 'wlwmanifest_link');
    
    // Remove WordPress version meta tag (security + performance)
    remove_action('wp_head', 'wp_generator');
}, 1);

/**
 * Object cache helper - ensures persistent caching if available
 */
function brighter_cache_set($key, $value, $group = 'brighter', $expiration = HOUR_IN_SECONDS) {
    return wp_cache_set($key, $value, $group, $expiration);
}

function brighter_cache_get($key, $group = 'brighter', &$found = null) {
    return wp_cache_get($key, $group, false, $found);
}

function brighter_cache_delete($key, $group = 'brighter') {
    return wp_cache_delete($key, $group);
}

/**
 * Batch option loader - reduces queries for multiple options
 */
function brighter_get_options($option_names) {
    global $wpdb;
    
    $placeholders = implode(',', array_fill(0, count($option_names), '%s'));
    $query = $wpdb->prepare(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($placeholders)",
        $option_names
    );
    
    $results = $wpdb->get_results($query);
    
    $options = [];
    foreach ($results as $row) {
        $options[$row->option_name] = maybe_unserialize($row->option_value);
    }
    
    return $options;
}