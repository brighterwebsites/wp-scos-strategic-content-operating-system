<?php
/**
 * Cache Helper
 *
 * Standardized caching helper for all Site Essentials modules.
 * Uses WordPress Object Cache API with fallback to transients.
 * Provides "remember" pattern for easy cache management.
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
 * Cache Helper Class
 *
 * Static helper methods for consistent caching across all modules.
 *
 * @since 1.0.0
 */
class Cache_Helper {
    /**
     * Default cache group
     *
     * @since 1.0.0
     * @var   string
     */
    const GROUP = 'site_essentials';

    /**
     * Default cache duration (1 hour)
     *
     * @since 1.0.0
     * @var   int
     */
    const DEFAULT_DURATION = 3600;

    /**
     * Remember pattern - Get from cache or execute callback and cache result
     *
     * This is the recommended way to use caching in modules.
     *
     * Usage:
     * ```php
     * $data = Cache_Helper::remember('my_key', function() {
     *     return expensive_query();
     * }, 3600, 'my_module');
     * ```
     *
     * @since  1.0.0
     * @param  string   $key        Cache key
     * @param  callable $callback   Callback to execute if cache miss
     * @param  int      $expiration Optional. Cache duration in seconds
     * @param  string   $group      Optional. Cache group (module ID recommended)
     * @return mixed    Cached value or callback result
     */
    public static function remember($key, $callback, $expiration = null, $group = '') {
        // Try to get from cache first
        $cached = self::get($key, $group);

        if ($cached !== false) {
            return $cached;
        }

        // Cache miss - execute callback
        $value = $callback();

        // Cache the result
        self::set($key, $value, $expiration, $group);

        return $value;
    }

    /**
     * Get value from cache
     *
     * @since  1.0.0
     * @param  string $key   Cache key
     * @param  string $group Optional. Cache group
     * @return mixed  Cached value or false if not found
     */
    public static function get($key, $group = '') {
        $group = self::get_group($group);
        $found = false;
        $value = wp_cache_get($key, $group, false, $found);

        // If object cache not available, try transient
        if (!$found && !wp_using_ext_object_cache()) {
            $transient_key = self::get_transient_key($key, $group);
            $value = get_transient($transient_key);
            $found = ($value !== false);
        }

        return $found ? $value : false;
    }

    /**
     * Set value in cache
     *
     * @since 1.0.0
     * @param string $key        Cache key
     * @param mixed  $value      Value to cache
     * @param int    $expiration Optional. Cache duration in seconds
     * @param string $group      Optional. Cache group
     * @return bool True on success
     */
    public static function set($key, $value, $expiration = null, $group = '') {
        $group = self::get_group($group);
        $expiration = $expiration ?? self::DEFAULT_DURATION;

        // Set in object cache
        $result = wp_cache_set($key, $value, $group, $expiration);

        // If object cache not available, use transient as fallback
        if (!wp_using_ext_object_cache()) {
            $transient_key = self::get_transient_key($key, $group);
            set_transient($transient_key, $value, $expiration);
        }

        return $result;
    }

    /**
     * Delete value from cache
     *
     * @since 1.0.0
     * @param string $key   Cache key
     * @param string $group Optional. Cache group
     * @return bool True on success
     */
    public static function delete($key, $group = '') {
        $group = self::get_group($group);

        // Delete from object cache
        $result = wp_cache_delete($key, $group);

        // Delete transient fallback
        if (!wp_using_ext_object_cache()) {
            $transient_key = self::get_transient_key($key, $group);
            delete_transient($transient_key);
        }

        return $result;
    }

    /**
     * Flush entire cache group
     *
     * Clears all cache for a specific module/group.
     *
     * Usage:
     * ```php
     * Cache_Helper::flush('my_module'); // Clear all cache for my_module
     * ```
     *
     * @since 1.0.0
     * @param string $group Optional. Cache group to flush (empty = flush all)
     * @return bool True on success
     */
    public static function flush($group = '') {
        $group = self::get_group($group);

        // For object cache, we can't easily flush a specific group
        // So we'll increment a version number to invalidate all keys
        $version_key = 'cache_version_' . $group;
        $current_version = wp_cache_get($version_key, self::GROUP);
        $new_version = $current_version ? $current_version + 1 : 1;
        wp_cache_set($version_key, $new_version, self::GROUP, 0); // Never expire

        // For transients, we need to delete them individually
        // This is slower but necessary as fallback
        if (!wp_using_ext_object_cache()) {
            global $wpdb;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_se_' . $group . '_%'
                )
            );
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_timeout_se_' . $group . '_%'
                )
            );
        }

        return true;
    }

    /**
     * Flush all Site Essentials cache
     *
     * Clears all cache for all modules.
     *
     * @since 1.0.0
     * @return bool True on success
     */
    public static function flush_all() {
        // Increment global cache version
        $version_key = 'cache_version_global';
        $current_version = wp_cache_get($version_key, self::GROUP);
        $new_version = $current_version ? $current_version + 1 : 1;
        wp_cache_set($version_key, $new_version, self::GROUP, 0);

        // Clear all transients
        if (!wp_using_ext_object_cache()) {
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_se_%'"
            );
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_se_%'"
            );
        }

        return true;
    }

    /**
     * Get cache statistics
     *
     * Returns useful debugging info about cache usage.
     *
     * @since  1.0.0
     * @return array Cache stats
     */
    public static function get_stats() {
        global $wpdb;

        $stats = [
            'object_cache_enabled' => wp_using_ext_object_cache(),
            'cache_group'          => self::GROUP,
            'default_duration'     => self::DEFAULT_DURATION,
        ];

        // Count transients (fallback cache)
        if (!wp_using_ext_object_cache()) {
            $transient_count = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_se_%'"
            );
            $stats['transient_count'] = (int) $transient_count;
        }

        return $stats;
    }

    /**
     * Get normalized cache group
     *
     * @since  1.0.0
     * @param  string $group Group name
     * @return string Normalized group name
     */
    private static function get_group($group) {
        return empty($group) ? self::GROUP : self::GROUP . '_' . $group;
    }

    /**
     * Get transient key for fallback caching
     *
     * @since  1.0.0
     * @param  string $key   Cache key
     * @param  string $group Cache group
     * @return string Transient key
     */
    private static function get_transient_key($key, $group) {
        // Prefix with 'se_' (site essentials) to avoid conflicts
        // Keep under 172 characters (WordPress transient limit is 172)
        $transient = 'se_' . $group . '_' . $key;

        if (strlen($transient) > 172) {
            // Hash long keys
            $transient = 'se_' . $group . '_' . md5($key);
        }

        return $transient;
    }

    /**
     * Register cache invalidation hooks for a module
     *
     * Helper method to automatically clear cache when data changes.
     *
     * Usage:
     * ```php
     * Cache_Helper::register_invalidation_hooks('business_info', [
     *     'update_option_site_essentials_business_info',
     *     'save_post',
     * ]);
     * ```
     *
     * @since 1.0.0
     * @param string $group Group to clear
     * @param array  $hooks Array of hook names
     * @return void
     */
    public static function register_invalidation_hooks($group, $hooks) {
        foreach ($hooks as $hook) {
            add_action($hook, function() use ($group) {
                self::flush($group);
            }, 10, 0);
        }
    }
}
