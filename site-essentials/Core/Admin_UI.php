<?php
/**
 * Admin UI
 *
 * Main settings page for Site Essentials.
 * Displays module toggles and individual module settings.
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
 * Admin UI Class
 *
 * Manages the WordPress admin interface for Site Essentials.
 *
 * @since 1.0.0
 */
class Admin_UI {
    /**
     * Settings Manager instance
     *
     * @since 1.0.0
     * @var   Settings_Manager
     */
    private $settings;

    /**
     * Page slugs
     *
     * @since 1.0.0
     * @var   string
     */
    const PAGE_SLUG = 'site-essentials';
    const SEO_PAGE_SLUG = 'site-essentials-seo';
    const ESSENTIALS_PAGE_SLUG = 'site-essentials-essentials';
    const SETTINGS_PAGE_SLUG = 'site-essentials-settings';

    /**
     * Constructor
     *
     * CRITICAL: Registers all hooks in constructor so they work regardless of when object is created.
     * This is essential for MU plugins which load before plugins_loaded hook.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->settings = Settings_Manager::instance();

        // Register all hooks immediately in constructor
        // This ensures hooks are registered before WordPress processes them
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_site_essentials_toggle_module', [$this, 'ajax_toggle_module']);
        add_action('wp_ajax_site_essentials_export_settings', [$this, 'ajax_export_settings']);
        add_action('wp_ajax_site_essentials_import_settings', [$this, 'ajax_import_settings']);
        add_action('wp_ajax_site_essentials_clear_sitemap_cache', [$this, 'ajax_clear_sitemap_cache']);
        add_action('admin_post_site_essentials_save_tweaks', [$this, 'save_tweaks_settings']);
        add_action('admin_post_site_essentials_save_seo', [$this, 'save_seo_settings']);
    }

    /**
     * Add admin menu items
     *
     * Creates top-level menu with submenu items for SEO, Essentials, and Settings.
     *
     * @since 1.0.0
     * @return void
     */
    public function add_admin_menu() {
        // Top-level menu (welcome page)
        add_menu_page(
            __('Site Essentials', 'site-essentials'),           // Page title
            __('Site Essentials', 'site-essentials'),           // Menu title
            'manage_options',                                    // Capability
            self::PAGE_SLUG,                                     // Menu slug
            [$this, 'render_welcome_page'],                     // Callback
            'dashicons-admin-generic',                           // Icon
            30                                                   // Position
        );

        // SEO submenu (only if SEO module is enabled)
        if ($this->settings->is_module_enabled('seo')) {
            add_submenu_page(
                self::PAGE_SLUG,                                     // Parent slug
                __('SEO Basics', 'site-essentials'),                // Page title
                __('SEO', 'site-essentials'),                        // Menu title
                'manage_options',                                    // Capability
                self::SEO_PAGE_SLUG,                                // Menu slug
                [$this, 'render_seo_page']                          // Callback
            );
        }

        // Essentials submenu (only if tweaks module is enabled)
        if ($this->settings->is_module_enabled('tweaks')) {
            add_submenu_page(
                self::PAGE_SLUG,                                     // Parent slug
                __('Essentials', 'site-essentials'),                // Page title
                __('Essentials', 'site-essentials'),                // Menu title
                'manage_options',                                    // Capability
                self::ESSENTIALS_PAGE_SLUG,                         // Menu slug
                [$this, 'render_essentials_page']                   // Callback
            );
        }

        // Settings submenu (always visible)
        add_submenu_page(
            self::PAGE_SLUG,                                     // Parent slug
            __('Plugin Settings', 'site-essentials'),           // Page title
            __('Settings', 'site-essentials'),                   // Menu title
            'manage_options',                                    // Capability
            self::SETTINGS_PAGE_SLUG,                           // Menu slug
            [$this, 'render_settings_page']                     // Callback
        );

        // Remove duplicate first submenu item (WordPress auto-adds parent as first submenu)
        remove_submenu_page(self::PAGE_SLUG, self::PAGE_SLUG);
    }

    /**
     * Register settings
     *
     * Uses WordPress Settings API.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_settings() {
        // Core settings section
        add_settings_section(
            'site_essentials_modules',
            __('Modules', 'site-essentials'),
            [$this, 'render_modules_section'],
            self::PAGE_SLUG
        );

        // Let loaded modules register their own settings
        $loaded_modules = Module_Loader::get_loaded_modules();
        foreach ($loaded_modules as $module_id => $module) {
            add_settings_section(
                'site_essentials_module_' . $module_id,
                $module::get_name(),
                '__return_false', // No description callback
                self::PAGE_SLUG
            );
        }
    }

    /**
     * Enqueue admin assets
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_assets($hook) {
        // Only load on Site Essentials pages
        $allowed_hooks = [
            'toplevel_page_' . self::SEO_PAGE_SLUG,
            self::PAGE_SLUG . '_page_' . self::SEO_PAGE_SLUG,
            self::PAGE_SLUG . '_page_' . self::ESSENTIALS_PAGE_SLUG,
            self::PAGE_SLUG . '_page_' . self::SETTINGS_PAGE_SLUG,
        ];

        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }

        // Enqueue admin CSS
        wp_enqueue_style(
            'site-essentials-admin',
            SITE_ESSENTIALS_URL . 'assets/css/admin.css',
            [],
            SITE_ESSENTIALS_VERSION
        );

        // Enqueue admin JS
        wp_enqueue_script(
            'site-essentials-admin',
            SITE_ESSENTIALS_URL . 'assets/js/admin.js',
            ['jquery'],
            SITE_ESSENTIALS_VERSION,
            true
        );

        // Localize script with data
        wp_localize_script('site-essentials-admin', 'siteEssentials', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('site_essentials_admin'),
        ]);
    }

    /**
     * Render welcome page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_welcome_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        include SITE_ESSENTIALS_PATH . 'Views/welcome-page.php';
    }

    /**
     * Render settings page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'modules';

        include SITE_ESSENTIALS_PATH . 'Views/settings-page.php';
    }

    /**
     * Render SEO page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_seo_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'sitemaps';

        // Get SEO module if loaded
        $seo_module = Module_Loader::get_module('seo');

        include SITE_ESSENTIALS_PATH . 'Views/seo-page.php';
    }

    /**
     * Render Essentials page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_essentials_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'tweaks';

        // Get WordPress Tweaks module if loaded
        $tweaks_module = Module_Loader::get_module('tweaks');

        include SITE_ESSENTIALS_PATH . 'Views/essentials-page.php';
    }

    /**
     * Render modules section
     *
     * Displays module toggles.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_modules_section() {
        $available_modules = Module_Loader::get_available_modules();
        $failed_modules = Module_Loader::get_failed_modules();

        if (empty($available_modules)) {
            echo '<p>' . esc_html__('No modules available.', 'site-essentials') . '</p>';
            return;
        }

        echo '<div class="site-essentials-modules">';

        foreach ($available_modules as $module_id => $class_name) {
            $is_enabled = $this->settings->is_module_enabled($module_id);
            $is_loaded = Module_Loader::is_module_loaded($module_id);
            $has_failed = isset($failed_modules[$module_id]);

            $name = $class_name::get_name();
            $description = $class_name::get_description();
            $tier = $class_name::get_tier();
            $dependencies = $class_name::get_dependencies();

            include SITE_ESSENTIALS_PATH . 'Views/module-toggle.php';
        }

        echo '</div>';
    }

    /**
     * AJAX: Toggle module on/off
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_toggle_module() {
        check_ajax_referer('site_essentials_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $module_id = isset($_POST['module_id']) ? sanitize_key($_POST['module_id']) : '';
        $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false;

        if (empty($module_id)) {
            wp_send_json_error(['message' => 'Module ID required']);
        }

        // Get state BEFORE toggle
        $before_memory = $this->settings->get('enabled_modules', []);
        $before_db = get_option(Settings_Manager::CORE_OPTION, []);
        $before_db_modules = isset($before_db['enabled_modules']) ? $before_db['enabled_modules'] : [];

        // Perform the toggle
        $result = false;
        if ($enabled) {
            $result = $this->settings->enable_module($module_id);

            // Flush rewrite rules when enabling SEO module to register sitemap endpoints
            if ($module_id === 'seo') {
                flush_rewrite_rules();
            }
        } else {
            $result = $this->settings->disable_module($module_id);

            // CRITICAL: Clear caches and rewrite rules when disabling modules
            // This ensures sitemap URLs and other module functionality is properly removed
            if ($module_id === 'seo') {
                // Clear sitemap cache
                Cache_Helper::clear_by_group('seo');
                // Flush rewrite rules to remove sitemap endpoints
                flush_rewrite_rules();
                // Clear LiteSpeed cache if active
                if (function_exists('litespeed_purge_all')) {
                    litespeed_purge_all();
                }
                // Clear standard WordPress cache
                wp_cache_flush();
            }
        }

        // CRITICAL: Nuclear cache clearing - clear EVERYTHING
        global $wpdb;

        // Clear WordPress object cache
        wp_cache_flush();

        // Clear LiteSpeed Cache if present
        if (defined('LSCWP_V')) {
            do_action('litespeed_purge_all');
        }

        // Force LiteSpeed to drop this specific option from cache
        if (function_exists('litespeed_purge_all')) {
            litespeed_purge_all();
        }

        // CRITICAL: Reload settings from DB to clear internal singleton cache
        $this->settings->reload();

        // Get state AFTER toggle - use BOTH get_option AND direct DB query
        $after_memory = $this->settings->get('enabled_modules', []);
        $after_db_via_getoption = get_option(Settings_Manager::CORE_OPTION, []);
        $after_db_modules_getoption = isset($after_db_via_getoption['enabled_modules']) ? $after_db_via_getoption['enabled_modules'] : [];

        // CRITICAL: Bypass ALL caches - read directly from database
        $raw_db_value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                Settings_Manager::CORE_OPTION
            )
        );
        $after_db_direct = maybe_unserialize($raw_db_value);
        $after_db_modules_direct = isset($after_db_direct['enabled_modules']) ? $after_db_direct['enabled_modules'] : [];

        // Verify the change in DB (not just memory)
        $is_now_enabled_in_db = in_array($module_id, $after_db_modules_direct, true);
        $verified = $is_now_enabled_in_db === $enabled;

        // Check if there's a cache mismatch
        $cache_mismatch = ($after_db_modules_getoption !== $after_db_modules_direct);

        wp_send_json_success([
            'message' => $enabled ? 'Module enabled' : 'Module disabled',
            'enabled' => $enabled,
            'verified' => $verified,
            'db_update_result' => $result,
            'reload_required' => true,
            'cache_mismatch' => $cache_mismatch,
            'litespeed_active' => defined('LSCWP_V'),
            'wp_cache_active' => defined('WP_CACHE') && WP_CACHE,
            'debug' => [
                'before_memory' => $before_memory,
                'before_db' => $before_db_modules,
                'after_memory' => $after_memory,
                'after_db_via_getoption' => $after_db_modules_getoption,
                'after_db_direct_query' => $after_db_modules_direct,
                'option_name' => Settings_Manager::CORE_OPTION,
                'cache_layer_mismatch' => $cache_mismatch ? 'YES - get_option() returned different data than direct DB query!' : 'NO - both match',
            ],
        ]);
    }

    /**
     * AJAX: Export settings
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_export_settings() {
        check_ajax_referer('site_essentials_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $module_ids = isset($_POST['module_ids']) ? (array) $_POST['module_ids'] : [];

        $json = $this->settings->export($module_ids);

        wp_send_json_success([
            'json'     => $json,
            'filename' => 'site-essentials-' . date('Y-m-d-His') . '.json',
        ]);
    }

    /**
     * AJAX: Import settings
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_import_settings() {
        check_ajax_referer('site_essentials_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $json = isset($_POST['json']) ? wp_unslash($_POST['json']) : '';
        $merge = isset($_POST['merge']) ? (bool) $_POST['merge'] : true;
        $module_ids = isset($_POST['module_ids']) ? (array) $_POST['module_ids'] : [];

        if (empty($json)) {
            wp_send_json_error(['message' => 'No settings data provided']);
        }

        $result = $this->settings->import($json, $merge, $module_ids);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => 'Settings imported successfully']);
    }

    /**
     * Save tweaks settings
     *
     * @since 1.0.0
     * @return void
     */
    public function save_tweaks_settings() {
        // Check nonce
        if (!isset($_POST['site_essentials_tweaks_nonce']) ||
            !wp_verify_nonce($_POST['site_essentials_tweaks_nonce'], 'site_essentials_tweaks')) {
            wp_die(__('Security check failed', 'site-essentials'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'site-essentials'));
        }

        // Get enabled tweaks
        $enabled_tweaks = isset($_POST['enabled_tweaks']) && is_array($_POST['enabled_tweaks'])
            ? $_POST['enabled_tweaks']
            : [];

        // Build full tweaks array (all false by default, enabled ones true)
        $all_tweaks = [
            'disable_emojis'        => isset($enabled_tweaks['disable_emojis']),
            'remove_jquery_migrate' => isset($enabled_tweaks['remove_jquery_migrate']),
            'disable_xmlrpc'        => isset($enabled_tweaks['disable_xmlrpc']),
            'remove_rsd_link'       => isset($enabled_tweaks['remove_rsd_link']),
            'remove_wlw_link'       => isset($enabled_tweaks['remove_wlw_link']),
            'remove_wp_version'     => isset($enabled_tweaks['remove_wp_version']),
            'optimize_heartbeat'    => isset($enabled_tweaks['optimize_heartbeat']),
            'remove_query_strings'  => isset($enabled_tweaks['remove_query_strings']),
            'disable_embeds'        => isset($enabled_tweaks['disable_embeds']),
            'disable_rest_api'      => isset($enabled_tweaks['disable_rest_api']),
        ];

        // Save settings
        $this->settings->update_module_settings('tweaks', ['enabled_tweaks' => $all_tweaks]);

        // Redirect back with success message
        $redirect_url = add_query_arg([
            'page'    => self::ESSENTIALS_PAGE_SLUG,
            'tab'     => 'tweaks',
            'updated' => 'true',
        ], admin_url('admin.php'));

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Save SEO settings
     *
     * @since 1.0.0
     * @return void
     */
    public function save_seo_settings() {
        // Check nonce
        if (!isset($_POST['site_essentials_seo_nonce']) ||
            !wp_verify_nonce($_POST['site_essentials_seo_nonce'], 'site_essentials_seo')) {
            wp_die(__('Security check failed', 'site-essentials'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'site-essentials'));
        }

        // Get sitemap settings
        $sitemap = isset($_POST['sitemap']) && is_array($_POST['sitemap']) ? $_POST['sitemap'] : [];

        // Process post types
        $post_types = isset($sitemap['post_types']) && is_array($sitemap['post_types'])
            ? array_map('sanitize_key', $sitemap['post_types'])
            : [];

        // Process taxonomies
        $taxonomies = isset($sitemap['taxonomies']) && is_array($sitemap['taxonomies'])
            ? array_map('sanitize_key', $sitemap['taxonomies'])
            : [];

        // Process exclude IDs
        $exclude_ids_string = isset($sitemap['exclude_ids']) ? sanitize_text_field($sitemap['exclude_ids']) : '';
        $exclude_ids = array_filter(array_map('intval', explode(',', $exclude_ids_string)));

        // Build settings array
        $sitemap_settings = [
            'enabled'              => isset($sitemap['enabled']),
            'html_sitemap_enabled' => isset($sitemap['html_sitemap_enabled']),
            'post_types'           => $post_types,
            'taxonomies'           => $taxonomies,
            'include_images'       => isset($sitemap['include_images']),
            'entries_per_sitemap'  => isset($sitemap['entries_per_sitemap']) ? absint($sitemap['entries_per_sitemap']) : 2000,
            'exclude_ids'          => $exclude_ids,
        ];

        // Save settings
        $this->settings->update_module_settings('seo', ['sitemap' => $sitemap_settings]);

        // Clear sitemap cache (internal)
        Cache_Helper::flush('seo');

        // Clear page caches (LiteSpeed, WP Super Cache, etc.)
        $this->clear_page_cache();

        // Flush rewrite rules since sitemap rules may have changed
        flush_rewrite_rules();

        // Redirect back with success message
        $redirect_url = add_query_arg([
            'page'    => self::SEO_PAGE_SLUG,
            'tab'     => 'sitemaps',
            'updated' => 'true',
        ], admin_url('admin.php'));

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * AJAX: Clear sitemap cache
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_clear_sitemap_cache() {
        check_ajax_referer('site_essentials_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Clear our internal sitemap cache
        Cache_Helper::flush('seo');

        // Clear page caches
        $this->clear_page_cache();

        wp_send_json_success([
            'message' => 'Sitemap cache cleared successfully (internal + page cache)'
        ]);
    }

    /**
     * Clear all page caches
     *
     * Detects and clears LiteSpeed, WP Super Cache, W3 Total Cache, and WP Rocket.
     *
     * @since 1.0.0
     * @return void
     */
    private function clear_page_cache() {
        // Clear LiteSpeed Cache if active
        if (function_exists('litespeed_purge_all')) {
            litespeed_purge_all();
        } elseif (class_exists('LiteSpeed_Cache_API') && method_exists('LiteSpeed_Cache_API', 'purge_all')) {
            \LiteSpeed_Cache_API::purge_all();
        }

        // Clear WP Super Cache if active
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }

        // Clear W3 Total Cache if active
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }

        // Clear WP Rocket cache if active
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
    }

    /**
     * Get deployment info for debugging
     *
     * @since 1.0.0
     * @return array
     */
    public static function get_deployment_info() {
        $info = [
            'version' => SITE_ESSENTIALS_VERSION,
            'commit' => 'unknown',
            'branch' => 'unknown',
            'deployed_at' => 'unknown',
        ];

        // Try to read version file (created by deploy script)
        $version_file = dirname(SITE_ESSENTIALS_PATH) . '/site-essentials-version.php';
        if (file_exists($version_file)) {
            $version_data = include $version_file;
            if (is_array($version_data)) {
                $info = array_merge($info, $version_data);
            }
        }

        return $info;
    }
}
