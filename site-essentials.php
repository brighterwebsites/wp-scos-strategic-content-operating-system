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
 * Bootstrap Site Essentials
 *
 * Initializes the plugin:
 * 1. Load core classes
 * 2. Initialize Settings Manager
 * 3. Register modules
 * 4. Load enabled modules
 *
 * @since 1.0.0
 */
add_action('plugins_loaded', function() {
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

        // CRITICAL: Disable WordPress core sitemaps if SEO module is enabled
        // WordPress core registers sitemaps with: add_action('init', 'wp_sitemaps_get_server', 5)
        // We remove this action BEFORE it runs, then add our filter as backup
        if ($settings->is_module_enabled('seo')) {
            add_action('init', function() {
                // Remove WP core sitemap initialization (runs at priority 5)
                remove_action('init', 'wp_sitemaps_get_server', 5);
            }, 1); // Priority 1 = runs BEFORE WP core

            // Backup: Also add filter in case WP core changes their implementation
            add_filter('wp_sitemaps_enabled', '__return_false', 1);
        }

        // Load all enabled modules
        \SiteEssentials\Core\Module_Loader::load_modules();

        // Initialize admin UI if in admin
        if (is_admin()) {
            $admin_ui = new \SiteEssentials\Core\Admin_UI();
            $admin_ui->init();
        }
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
