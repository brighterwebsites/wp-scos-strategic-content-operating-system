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
     * Page slug
     *
     * @since 1.0.0
     * @var   string
     */
    const PAGE_SLUG = 'site-essentials';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->settings = Settings_Manager::instance();
    }

    /**
     * Initialize admin UI
     *
     * Registers hooks for admin menu, assets, etc.
     *
     * @since 1.0.0
     * @return void
     */
    public function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_site_essentials_toggle_module', [$this, 'ajax_toggle_module']);
        add_action('wp_ajax_site_essentials_export_settings', [$this, 'ajax_export_settings']);
        add_action('wp_ajax_site_essentials_import_settings', [$this, 'ajax_import_settings']);
        add_action('admin_post_site_essentials_save_tweaks', [$this, 'save_tweaks_settings']);
    }

    /**
     * Add admin menu item
     *
     * @since 1.0.0
     * @return void
     */
    public function add_admin_menu() {
        add_options_page(
            __('Site Essentials', 'site-essentials'),
            __('Site Essentials', 'site-essentials'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );
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
                function() use ($module) {
                    echo '<p>' . esc_html($module::get_description()) . '</p>';
                },
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
        // Only load on our settings page
        if ($hook !== 'settings_page_' . self::PAGE_SLUG) {
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

        include SITE_ESSENTIALS_PATH . 'views/settings-page.php';
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

            include SITE_ESSENTIALS_PATH . 'views/module-toggle.php';
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

        if ($enabled) {
            $this->settings->enable_module($module_id);
        } else {
            $this->settings->disable_module($module_id);
        }

        wp_send_json_success([
            'message' => $enabled ? 'Module enabled' : 'Module disabled',
            'enabled' => $enabled,
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
            'page' => self::PAGE_SLUG,
            'tab' => 'modules',
            'updated' => 'true',
        ], admin_url('options-general.php'));

        wp_safe_redirect($redirect_url);
        exit;
    }
}
