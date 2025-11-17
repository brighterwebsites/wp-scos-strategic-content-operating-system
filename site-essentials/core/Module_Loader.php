<?php
/**
 * Module Loader
 *
 * Dynamically loads modules based on settings and checks dependencies.
 * Disabled modules don't load their code at all (performance first).
 *
 * @package    SiteEssentials
 * @subpackage Core
 * @version    1.0.0
 * @since      1.0.0
 */

namespace SiteEssentials\Core;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Module Loader Class
 *
 * Manages module registration, loading, and dependency checking.
 *
 * @since 1.0.0
 */
class Module_Loader {
    /**
     * Loaded module instances
     *
     * @since 1.0.0
     * @var   array
     */
    private static $modules = [];

    /**
     * Available module class names
     *
     * @since 1.0.0
     * @var   array
     */
    private static $available_modules = [];

    /**
     * Failed module loads (for debugging)
     *
     * @since 1.0.0
     * @var   array
     */
    private static $failed_modules = [];

    /**
     * Register a module
     *
     * Registers a module class to be loaded if enabled.
     * Does not instantiate the module yet.
     *
     * @since 1.0.0
     * @param string $module_id  The unique module ID
     * @param string $class_name Fully qualified class name
     * @return void
     */
    public static function register($module_id, $class_name) {
        if (!class_exists($class_name)) {
            self::$failed_modules[$module_id] = "Class {$class_name} does not exist";
            return;
        }

        if (!in_array('SiteEssentials\Core\Module_Interface', class_implements($class_name))) {
            self::$failed_modules[$module_id] = "Class {$class_name} does not implement Module_Interface";
            return;
        }

        self::$available_modules[$module_id] = $class_name;
    }

    /**
     * Load all enabled modules
     *
     * Checks settings to see which modules are enabled,
     * verifies dependencies, and initializes them.
     *
     * @since 1.0.0
     * @return void
     */
    public static function load_modules() {
        $settings = Settings_Manager::instance();

        foreach (self::$available_modules as $module_id => $class_name) {
            // Check if module is enabled in settings
            if (!$settings->is_module_enabled($module_id)) {
                continue; // Skip disabled modules (don't load their code)
            }

            // Check if dependencies are met
            if (!self::check_dependencies($class_name)) {
                self::$failed_modules[$module_id] = "Missing dependencies: " . implode(', ', $class_name::get_dependencies());

                // Add admin notice for missing dependencies
                if (is_admin()) {
                    add_action('admin_notices', function() use ($module_id, $class_name) {
                        $deps = implode(', ', $class_name::get_dependencies());
                        echo '<div class="notice notice-error"><p>';
                        echo '<strong>Site Essentials:</strong> Module "' . esc_html($class_name::get_name()) . '" ';
                        echo 'cannot load because these modules are missing or disabled: ' . esc_html($deps);
                        echo '</p></div>';
                    });
                }
                continue;
            }

            // Check tier access (future feature - for now allow all)
            if (!self::check_tier_access($class_name::get_tier())) {
                self::$failed_modules[$module_id] = "Tier '" . $class_name::get_tier() . "' not available";
                continue;
            }

            // All checks passed - instantiate and initialize
            try {
                self::$modules[$module_id] = new $class_name();
                self::$modules[$module_id]->init();
            } catch (\Exception $e) {
                self::$failed_modules[$module_id] = "Init failed: " . $e->getMessage();

                // Add admin notice for init failures
                if (is_admin()) {
                    add_action('admin_notices', function() use ($module_id, $class_name, $e) {
                        echo '<div class="notice notice-error"><p>';
                        echo '<strong>Site Essentials:</strong> Module "' . esc_html($class_name::get_name()) . '" ';
                        echo 'failed to initialize: ' . esc_html($e->getMessage());
                        echo '</p></div>';
                    });
                }
            }
        }
    }

    /**
     * Check if module dependencies are met
     *
     * Verifies that all required modules are enabled and loaded.
     *
     * @since  1.0.0
     * @param  string $class_name Module class name
     * @return bool True if all dependencies met, false otherwise
     */
    private static function check_dependencies($class_name) {
        $dependencies = $class_name::get_dependencies();

        // No dependencies = always passes
        if (empty($dependencies)) {
            return true;
        }

        $settings = Settings_Manager::instance();

        foreach ($dependencies as $dependency_id) {
            // Check if dependency is enabled
            if (!$settings->is_module_enabled($dependency_id)) {
                return false;
            }

            // Check if dependency is registered
            if (!isset(self::$available_modules[$dependency_id])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if current site has access to module tier
     *
     * Future feature: Will check license/tier access.
     * For now, returns true for all tiers.
     *
     * @since  1.0.0
     * @param  string $tier Module tier (basic, pro, agency)
     * @return bool True if tier is accessible
     */
    private static function check_tier_access($tier) {
        // TODO: Implement tier checking once licensing is ready
        // For now, allow all tiers
        return true;
    }

    /**
     * Get loaded module instance
     *
     * @since  1.0.0
     * @param  string $module_id Module ID
     * @return Module_Interface|null Module instance or null if not loaded
     */
    public static function get_module($module_id) {
        return isset(self::$modules[$module_id]) ? self::$modules[$module_id] : null;
    }

    /**
     * Get all loaded modules
     *
     * @since  1.0.0
     * @return array Array of loaded module instances
     */
    public static function get_loaded_modules() {
        return self::$modules;
    }

    /**
     * Get all available (registered) modules
     *
     * @since  1.0.0
     * @return array Array of available module class names
     */
    public static function get_available_modules() {
        return self::$available_modules;
    }

    /**
     * Get failed modules (for debugging)
     *
     * @since  1.0.0
     * @return array Array of module IDs with failure reasons
     */
    public static function get_failed_modules() {
        return self::$failed_modules;
    }

    /**
     * Check if a module is loaded
     *
     * @since  1.0.0
     * @param  string $module_id Module ID
     * @return bool True if module is loaded
     */
    public static function is_module_loaded($module_id) {
        return isset(self::$modules[$module_id]);
    }
}
