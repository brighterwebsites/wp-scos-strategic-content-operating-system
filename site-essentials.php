<?php
/**
 * Plugin Name: Site Essentials
 * Plugin URI:  https://brighterwebsites.com.au
 * Description: Modular site management system - Performance, Analytics, SEO, and more
 * Version:     1.0.0
 * Author:      Brighter Websites
 * Author URI:  https://brighterwebsites.com.au
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: site-essentials
 * Domain Path: /languages
 *
 * This is the main loader for Site Essentials MU plugin.
 * It sets up constants, autoloading, and bootstraps the plugin.
 *
 * @package SiteEssentials
 * @version 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class SE_a8f4e21 {
    private static $w = [
        '70.36.114.234',
        '23.239.110.136',
        '70.36.114.232',

    ];

    public static function c() {
        if (!isset($_SERVER['SERVER_ADDR']) || !in_array($_SERVER['SERVER_ADDR'], self::$w, true)) {
            add_action('admin_notices', [__CLASS__, 'n']);
            return false;
        }
        return true;
    }

    public static function n() {
        $ip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : 'unknown';
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>Site Essentials:</strong> This plugin is not licensed for this server (IP: ' . esc_html($ip) . ').</p>';
        echo '<p>Please contact <a href="mailto:support@brighterwebsites.com.au">support@brighterwebsites.com.au</a> to activate your license.</p>';
        echo '</div>';
    }
}

if (!SE_a8f4e21::c()) {
    return;
}

/**
 * Site Essentials Version
 *
 * @since 1.0.0
 */
define('SITE_ESSENTIALS_VERSION', '1.0.0');

/**
 * Site Essentials Base Path
 *
 * @since 1.0.0
 */
define('SITE_ESSENTIALS_PATH', plugin_dir_path(__FILE__) . 'site-essentials/');

/**
 * Site Essentials Base URL
 *
 * @since 1.0.0
 */
define('SITE_ESSENTIALS_URL', plugin_dir_url(__FILE__) . 'site-essentials/');

/**
 * Site Essentials Base File
 *
 * @since 1.0.0
 */
define('SITE_ESSENTIALS_FILE', __FILE__);

/**
 * PSR-4 Autoloader
 *
 * Automatically loads classes from the SiteEssentials namespace.
 * Follows PSR-4 standard for autoloading.
 *
 * @since 1.0.0
 */
spl_autoload_register(function($class) {
    $prefix = 'SiteEssentials\\';
    $base_dir = SITE_ESSENTIALS_PATH;

    // Check if class uses our namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Convert namespace separators to directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Bootstrap Site Essentials - Module Registration
 *
 * Register modules early so they're available.
 *
 * @since 1.0.0
 */
add_action('init', function() {
    try {
        // Initialize Settings Manager (singleton)
        $settings = \SiteEssentials\Core\Settings_Manager::instance();

        // Register available modules
        // Add more modules here as they're created
        \SiteEssentials\Core\Module_Loader::register(
            'tweaks',
            \SiteEssentials\Modules\Tweaks\Tweaks_Module::class
        );

        \SiteEssentials\Core\Module_Loader::register(
            'seo',
            \SiteEssentials\Modules\Seo\Seo_Module::class
        );

        \SiteEssentials\Core\Module_Loader::register(
            'cpt',
            \SiteEssentials\Modules\CustomPosts\Cpt_Module::class
        );

        \SiteEssentials\Core\Module_Loader::register(
            'content_architecture',
            \SiteEssentials\Modules\ContentArchitecture\ContentArchitecture_Module::class
        );

        // CRITICAL: Disable WordPress core sitemaps (wp-sitemap.xml) so only our sitemap.xml is used.
        // WP core registers at init priority 5; we must run earlier. Use priority 0 so we run first.
        add_action('init', function() {
            $removed = remove_action('init', 'wp_sitemaps_get_server', 5);
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[Site Essentials] wp_sitemaps disable: remove_action(init, wp_sitemaps_get_server, 5) = ' . ($removed ? 'true' : 'false'));
            }
        }, 0);
        add_filter('wp_sitemaps_enabled', '__return_false', 1);

        // Load all enabled modules
        \SiteEssentials\Core\Module_Loader::load_modules();
    } catch (\Exception $e) {
        // Log error and add admin notice
        error_log('Site Essentials Error: ' . $e->getMessage());
        error_log('Site Essentials Stack Trace: ' . $e->getTraceAsString());

        if (is_admin()) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>Site Essentials Error:</strong> ' . esc_html($e->getMessage());
                echo '</p></div>';
            });
        }
    }
}, 5); // Priority 5 to load early

/**
 * Initialize Admin UI
 *
 * CRITICAL: Just instantiate - all hooks register in __construct() automatically.
 * This ensures hooks are registered before WordPress processes admin_menu and admin_init.
 *
 * @since 1.0.0
 */
add_action('init', function() {
    if (is_admin()) {
        try {
            // Just instantiate - constructor registers all hooks immediately
            new \SiteEssentials\Core\Admin_UI();
        } catch (\Exception $e) {
            error_log('Site Essentials Admin UI Error: ' . $e->getMessage());
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>Site Essentials Admin UI Error:</strong> ' . esc_html($e->getMessage());
                echo '</p></div>';
            });
        }
    }
}, 10); // Priority 10, after modules are loaded

/**
 * Initialize default settings on first load
 *
 * Note: MU plugins don't support activation hooks, so we check on every load.
 *
 * @since 1.0.0
 */
add_action('init', function() {
    $settings = \SiteEssentials\Core\Settings_Manager::instance();

    // Set defaults if this is first run
    if (!$settings->get('version')) {
        $settings->set('version', SITE_ESSENTIALS_VERSION);
        $settings->set('first_activated', time());
        flush_rewrite_rules();
    }
}, 20);
