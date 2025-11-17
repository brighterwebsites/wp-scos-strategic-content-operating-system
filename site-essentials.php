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
define('SITE_ESSENTIALS_PATH', __DIR__ . '/site-essentials/');

/**
 * Site Essentials Base URL
 *
 * @since 1.0.0
 */
define('SITE_ESSENTIALS_URL', plugins_url('site-essentials/', __FILE__));

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
    // Initialize Settings Manager (singleton)
    $settings = \SiteEssentials\Core\Settings_Manager::instance();

    // Register available modules
    // Add more modules here as they're created
    \SiteEssentials\Core\Module_Loader::register(
        'tweaks',
        \SiteEssentials\Modules\Tweaks\Tweaks_Module::class
    );

    // Load all enabled modules
    \SiteEssentials\Core\Module_Loader::load_modules();

    // Initialize admin UI if in admin
    if (is_admin()) {
        $admin_ui = new \SiteEssentials\Core\Admin_UI();
        $admin_ui->init();
    }
}, 5); // Priority 5 to load early

/**
 * Activation Hook
 *
 * Runs when plugin is activated (or MU plugin is first loaded).
 *
 * @since 1.0.0
 */
register_activation_hook(__FILE__, function() {
    // Set default settings
    $settings = \SiteEssentials\Core\Settings_Manager::instance();

    // Ensure defaults are set
    if (!$settings->get('version')) {
        $settings->set('version', SITE_ESSENTIALS_VERSION);
        $settings->set('first_activated', time());
    }

    // Flush rewrite rules
    flush_rewrite_rules();
});

/**
 * Deactivation Hook
 *
 * Runs when plugin is deactivated.
 *
 * @since 1.0.0
 */
register_deactivation_hook(__FILE__, function() {
    // Flush rewrite rules
    flush_rewrite_rules();
});
