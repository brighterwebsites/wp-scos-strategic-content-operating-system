<?php
/**
 * Settings Manager
 *
 * Centralized settings management for all Site Essentials modules.
 * Provides settings storage, retrieval, import/export, and validation.
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
 * Settings Manager Class
 *
 * Singleton class that manages all plugin settings.
 *
 * @since 1.0.0
 */
class Settings_Manager {
    /**
     * Singleton instance
     *
     * @since 1.0.0
     * @var   Settings_Manager
     */
    private static $instance = null;

    /**
     * Core settings option name
     *
     * @since 1.0.0
     * @var   string
     */
    const CORE_OPTION = 'site_essentials_core';

    /**
     * Settings prefix for module options
     *
     * @since 1.0.0
     * @var   string
     */
    const MODULE_OPTION_PREFIX = 'site_essentials_';

    /**
     * Core settings cache
     *
     * @since 1.0.0
     * @var   array
     */
    private $settings = [];

    /**
     * Module settings cache
     *
     * @since 1.0.0
     * @var   array
     */
    private $module_settings = [];

    /**
     * Get singleton instance
     *
     * @since  1.0.0
     * @return Settings_Manager
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * Loads core settings from database.
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->load_settings();
    }

    /**
     * Load core settings from database
     *
     * @since 1.0.0
     * @return void
     */
    private function load_settings() {
        $this->settings = get_option(self::CORE_OPTION, $this->get_default_settings());

        // Ensure defaults are merged (in case new settings added)
        $this->settings = wp_parse_args($this->settings, $this->get_default_settings());
    }

    /**
     * Reload settings from database (clears internal cache)
     *
     * @since 1.0.0
     * @return void
     */
    public function reload() {
        // Clear WordPress object cache first
        wp_cache_delete(self::CORE_OPTION, 'options');
        // Reload from DB
        $this->load_settings();
    }

    /**
     * Get default core settings
     *
     * @since  1.0.0
     * @return array Default settings
     */
    private function get_default_settings() {
        return [
            'version'          => SITE_ESSENTIALS_VERSION,
            'enabled_modules'  => [], // Start with all modules disabled
            'first_activated'  => time(),
            'last_updated'     => time(),
        ];
    }

    /**
     * Get core settings
     *
     * @since  1.0.0
     * @param  string $key     Optional. Setting key to retrieve
     * @param  mixed  $default Optional. Default value if key not found
     * @return mixed  Setting value or all settings if no key specified
     */
    public function get($key = null, $default = null) {
        if ($key === null) {
            return $this->settings;
        }

        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Set core setting
     *
     * @since 1.0.0
     * @param string $key   Setting key
     * @param mixed  $value Setting value
     * @return bool True on success
     */
    public function set($key, $value) {
        $this->settings[$key] = $value;
        $this->settings['last_updated'] = time();
        return update_option(self::CORE_OPTION, $this->settings);
    }

    /**
     * Update multiple core settings at once
     *
     * @since 1.0.0
     * @param array $settings Array of key => value pairs
     * @return bool True on success
     */
    public function update($settings) {
        $this->settings = array_merge($this->settings, $settings);
        $this->settings['last_updated'] = time();
        return update_option(self::CORE_OPTION, $this->settings);
    }

    /**
     * Check if a module is enabled
     *
     * @since  1.0.0
     * @param  string $module_id Module ID
     * @return bool True if enabled
     */
    public function is_module_enabled($module_id) {
        $enabled_modules = $this->get('enabled_modules', []);
        return in_array($module_id, $enabled_modules, true);
    }

    /**
     * Enable a module
     *
     * @since 1.0.0
     * @param string $module_id Module ID
     * @return bool True on success
     */
    public function enable_module($module_id) {
        $enabled_modules = $this->get('enabled_modules', []);

        if (!in_array($module_id, $enabled_modules, true)) {
            $enabled_modules[] = $module_id;
            return $this->set('enabled_modules', $enabled_modules);
        }

        return true; // Already enabled
    }

    /**
     * Disable a module
     *
     * @since 1.0.0
     * @param string $module_id Module ID
     * @return bool True on success
     */
    public function disable_module($module_id) {
        $enabled_modules = $this->get('enabled_modules', []);
        $key = array_search($module_id, $enabled_modules, true);

        if ($key !== false) {
            unset($enabled_modules[$key]);
            $enabled_modules = array_values($enabled_modules); // Re-index array
            return $this->set('enabled_modules', $enabled_modules);
        }

        return true; // Already disabled
    }

    /**
     * Get module settings
     *
     * @since  1.0.0
     * @param  string $module_id Module ID
     * @param  string $key       Optional. Setting key to retrieve
     * @param  mixed  $default   Optional. Default value if key not found
     * @return mixed  Setting value or all module settings if no key specified
     */
    public function get_module_setting($module_id, $key = null, $default = null) {
        // Load module settings if not cached
        if (!isset($this->module_settings[$module_id])) {
            $option_name = self::MODULE_OPTION_PREFIX . $module_id;
            $this->module_settings[$module_id] = get_option($option_name, []);
        }

        $settings = $this->module_settings[$module_id];

        if ($key === null) {
            return $settings;
        }

        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Set module setting
     *
     * @since 1.0.0
     * @param string $module_id Module ID
     * @param string $key       Setting key
     * @param mixed  $value     Setting value
     * @return bool True on success
     */
    public function set_module_setting($module_id, $key, $value) {
        $settings = $this->get_module_setting($module_id);
        $settings[$key] = $value;

        $option_name = self::MODULE_OPTION_PREFIX . $module_id;
        $result = update_option($option_name, $settings);

        // Update cache
        $this->module_settings[$module_id] = $settings;

        return $result;
    }

    /**
     * Update multiple module settings at once
     *
     * @since 1.0.0
     * @param string $module_id Module ID
     * @param array  $settings  Array of key => value pairs
     * @return bool True on success
     */
    public function update_module_settings($module_id, $settings) {
        $current_settings = $this->get_module_setting($module_id);
        $new_settings = array_merge($current_settings, $settings);

        $option_name = self::MODULE_OPTION_PREFIX . $module_id;
        $result = update_option($option_name, $new_settings);

        // Update cache
        $this->module_settings[$module_id] = $new_settings;

        return $result;
    }

    /**
     * Export all settings as JSON
     *
     * @since  1.0.0
     * @param  array $module_ids Optional. Specific modules to export (empty = all)
     * @return string JSON string of settings
     */
    public function export($module_ids = []) {
        $export = [
            'version'                 => SITE_ESSENTIALS_VERSION,
            'export_date'             => date('Y-m-d H:i:s'),
            'site_url'                => get_site_url(),
            'se_core'                 => $this->settings,
        ];

        // Get module info for better naming
        $available_modules = \SiteEssentials\Core\Module_Loader::get_available_modules();

        // Get all module settings
        if (empty($module_ids)) {
            // Export all modules
            $enabled_modules = $this->get('enabled_modules', []);
            foreach ($enabled_modules as $module_id) {
                $export_key = $this->get_export_key($module_id, $available_modules);
                $export[$export_key] = $this->get_module_setting($module_id);
            }
        } else {
            // Export specific modules
            foreach ($module_ids as $module_id) {
                $export_key = $this->get_export_key($module_id, $available_modules);
                $export[$export_key] = $this->get_module_setting($module_id);
            }
        }

        return wp_json_encode($export, JSON_PRETTY_PRINT);
    }

    /**
     * Get export key for module
     *
     * Creates readable key like "se_wp_tweaks_basic" instead of "site_essentials_tweaks"
     *
     * @since  1.0.0
     * @param  string $module_id        Module ID
     * @param  array  $available_modules Available modules
     * @return string Export key
     */
    private function get_export_key($module_id, $available_modules) {
        // Try to get tier from module class
        $tier = 'module';
        if (isset($available_modules[$module_id])) {
            $class_name = $available_modules[$module_id];
            if (method_exists($class_name, 'get_tier')) {
                $tier = $class_name::get_tier();
            }
        }

        // Convert module_id to readable format: tweaks -> wp_tweaks
        $readable_name = str_replace('_', '_', $module_id);
        if ($module_id === 'tweaks') {
            $readable_name = 'wp_tweaks';
        }

        return 'se_' . $readable_name . '_' . $tier;
    }

    /**
     * Import settings from JSON
     *
     * @since 1.0.0
     * @param string $json          JSON string of settings
     * @param bool   $merge         Optional. Merge with existing (true) or replace (false)
     * @param array  $module_ids    Optional. Specific modules to import (empty = all)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function import($json, $merge = true, $module_ids = []) {
        $data = json_decode($json, true);

        if ($data === null) {
            return new \WP_Error('invalid_json', 'Invalid JSON format');
        }

        // Version check
        if (!isset($data['version'])) {
            return new \WP_Error('missing_version', 'Settings export missing version');
        }

        // Import core settings (handle both old and new format)
        if (isset($data[self::CORE_OPTION])) {
            if ($merge) {
                $this->settings = array_merge($this->settings, $data[self::CORE_OPTION]);
            } else {
                $this->settings = $data[self::CORE_OPTION];
            }
            update_option(self::CORE_OPTION, $this->settings);
        } elseif (isset($data['se_core'])) {
            // New format
            if ($merge) {
                $this->settings = array_merge($this->settings, $data['se_core']);
            } else {
                $this->settings = $data['se_core'];
            }
            update_option(self::CORE_OPTION, $this->settings);
        }

        // Import module settings
        foreach ($data as $option_name => $settings) {
            $module_id = null;

            // Old format: "site_essentials_tweaks"
            if (strpos($option_name, self::MODULE_OPTION_PREFIX) === 0) {
                $module_id = str_replace(self::MODULE_OPTION_PREFIX, '', $option_name);
            }
            // New format: "se_wp_tweaks_basic"
            elseif (strpos($option_name, 'se_') === 0 && $option_name !== 'se_core') {
                // Parse new format: se_{name}_{tier}
                $parts = explode('_', $option_name);
                // Remove 'se' prefix and tier suffix
                array_shift($parts); // Remove 'se'
                array_pop($parts); // Remove tier (basic/pro/agency)

                // Map back to module ID
                $name = implode('_', $parts);
                if ($name === 'wp_tweaks') {
                    $module_id = 'tweaks';
                } else {
                    $module_id = $name;
                }
            } else {
                // Skip non-module options
                continue;
            }

            // If specific modules specified, skip others
            if (!empty($module_ids) && !in_array($module_id, $module_ids, true)) {
                continue;
            }

            // Get the actual option name for storage
            $storage_option = self::MODULE_OPTION_PREFIX . $module_id;

            // Import module settings
            if ($merge) {
                $current_settings = $this->get_module_setting($module_id);
                $new_settings = array_merge($current_settings, $settings);
                update_option($storage_option, $new_settings);
            } else {
                update_option($storage_option, $settings);
            }

            // Clear cache for this module
            unset($this->module_settings[$module_id]);
        }

        return true;
    }

    /**
     * Reset all settings to defaults
     *
     * @since 1.0.0
     * @return bool True on success
     */
    public function reset() {
        // Reset core settings
        $this->settings = $this->get_default_settings();
        update_option(self::CORE_OPTION, $this->settings);

        // Delete all module settings
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                self::MODULE_OPTION_PREFIX . '%'
            )
        );

        // Clear module settings cache
        $this->module_settings = [];

        return true;
    }
}
