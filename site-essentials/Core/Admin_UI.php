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
    const CPT_PAGE_SLUG = 'site-essentials-cpt';
    const BUSINESS_INFO_PAGE_SLUG = 'site-essentials-business-info';
    const SETTINGS_PAGE_SLUG = 'site-essentials-settings';
    const ANALYTICS_PAGE_SLUG = 'site-essentials-analytics';
    const SITE_SCHEMA_PAGE_SLUG = 'site-essentials-schema';

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
        add_action('admin_post_site_essentials_save_cpt', [$this, 'save_cpt_settings']);
        // Asset Preload form POSTs to the Performance page URL (not admin-post) so save is handled here
        add_action('admin_init', [$this, 'maybe_save_asset_preload'], 1);
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

        // SEO submenu (always visible, shows notice if disabled)
        add_submenu_page(
            self::PAGE_SLUG,                                     // Parent slug
            __('SEO Basics', 'site-essentials'),                // Page title
            __('SEO', 'site-essentials'),                        // Menu title
            'manage_options',                                    // Capability
            self::SEO_PAGE_SLUG,                                // Menu slug
            [$this, 'render_seo_page']                          // Callback
        );

        // Performance submenu (WordPress Tweaks, Image Optimization, Asset Preloading)
        add_submenu_page(
            self::PAGE_SLUG,                                     // Parent slug
            __('Performance', 'site-essentials'),                // Page title
            __('Performance', 'site-essentials'),                // Menu title
            'manage_options',                                    // Capability
            self::ESSENTIALS_PAGE_SLUG,                         // Menu slug (unchanged: site-essentials-essentials)
            [$this, 'render_performance_page']                  // Callback
        );

        // Custom Posts (Recommended CPT) submenu
        add_submenu_page(
            self::PAGE_SLUG,                                     // Parent slug
            __('Recommended Custom Posts & Fields', 'site-essentials'),  // Page title
            __('Custom Posts', 'site-essentials'),               // Menu title
            'manage_options',                                    // Capability
            self::CPT_PAGE_SLUG,                                // Menu slug
            [$this, 'render_cpt_page']                          // Callback
        );

        // Business Info submenu — only when BusinessInfo module is active
        if ( defined( 'SCOS_BIZ_ACTIVE' ) ) {
            add_submenu_page(
                self::PAGE_SLUG,
                __( 'Business Info', 'site-essentials' ),
                __( 'Business Info', 'site-essentials' ),
                'manage_options',
                self::BUSINESS_INFO_PAGE_SLUG,
                [ $this, 'render_business_info_page' ]
            );
        }

        // Analytics submenu (only when Analytics module is active)
        if ( defined( 'SCOS_ANALYTICS_ACTIVE' ) ) {
            add_submenu_page(
                self::PAGE_SLUG,
                __( 'Analytics', 'site-essentials' ),
                __( 'Analytics', 'site-essentials' ),
                'manage_options',
                self::ANALYTICS_PAGE_SLUG,
                [ $this, 'render_analytics_page' ]
            );
        }

        // Site Schema submenu (only when SiteSchema module is active)
        if ( defined( 'SCOS_SITE_SCHEMA_ACTIVE' ) ) {
            add_submenu_page(
                self::PAGE_SLUG,
                __( 'Site Schema', 'site-essentials' ),
                __( 'Site Schema', 'site-essentials' ),
                'manage_options',
                self::SITE_SCHEMA_PAGE_SLUG,
                [ $this, 'render_site_schema_page' ]
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

        // Keep first submenu item (Site Essentials -> welcome page) so top-level menu links to welcome, not SEO
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
            'toplevel_page_' . self::PAGE_SLUG,
            'toplevel_page_' . self::SEO_PAGE_SLUG,
            self::PAGE_SLUG . '_page_' . self::SEO_PAGE_SLUG,
            self::PAGE_SLUG . '_page_' . self::ESSENTIALS_PAGE_SLUG,
            self::PAGE_SLUG . '_page_' . self::CPT_PAGE_SLUG,
            self::PAGE_SLUG . '_page_' . self::BUSINESS_INFO_PAGE_SLUG,
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

        // Check if SEO module is enabled
        if (!$this->settings->is_module_enabled('seo')) {
            $this->render_module_disabled_notice('SEO', 'seo');
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'sitemaps';

        // Get SEO module if loaded
        $seo_module = Module_Loader::get_module('seo');

        include SITE_ESSENTIALS_PATH . 'Views/seo-page.php';
    }

    /**
     * Render Performance page (WordPress Tweaks, Image Optimization, Asset Preloading)
     *
     * @since 1.0.0
     * @return void
     */
    public function render_performance_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'tweaks';
        $tweaks_module = Module_Loader::get_module('tweaks');

        // Image Optimization tab: brighter-support-image-settings must be loaded (brighter-core)
        $image_settings_available = function_exists('brighter_get_image_sizes_config');

        include SITE_ESSENTIALS_PATH . 'Views/performance-page.php';
    }

    /**
     * Render Custom Posts (Recommended CPT) page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_cpt_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        if (!$this->settings->is_module_enabled('cpt')) {
            $this->render_module_disabled_notice(__('Custom Posts', 'site-essentials'), 'cpt');
            return;
        }

        $cpt_module = Module_Loader::get_module('cpt');

        if (!$cpt_module || !is_object($cpt_module)) {
            echo '<div class="wrap"><div class="notice notice-warning"><p>';
            esc_html_e('Custom Posts module is not loaded.', 'site-essentials');
            echo '</p></div></div>';
            return;
        }

        if (isset($_GET['updated']) && $_GET['updated'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'site-essentials') . '</p></div>';
        }

        echo '<div class="wrap site-essentials-wrap">';
        echo '<h1>' . esc_html__('Recommended Custom Posts & Fields', 'site-essentials') . '</h1>';
        echo '<div class="site-essentials-content">';
        echo '<div class="card se-module-settings-card" data-module-id="cpt">';
        $cpt_module->render_settings();
        echo '</div></div></div>';
    }

    /**
     * Render Analytics page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_analytics_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'site-essentials' ) );
        }

        $analytics_module = Module_Loader::get_module( 'analytics' );

        if ( ! $analytics_module ) {
            echo '<div class="wrap"><div class="notice notice-warning"><p>';
            esc_html_e( 'Analytics module is not loaded.', 'site-essentials' );
            echo '</p></div></div>';
            return;
        }

        if ( isset( $_GET['scos_analytics_saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'site-essentials' ) . '</p></div>';
        }

        echo '<div class="wrap site-essentials-wrap">';
        echo '<h1>' . esc_html__( 'Analytics', 'site-essentials' ) . '</h1>';
        echo '<div class="site-essentials-content">';
        echo '<div class="card se-module-settings-card" data-module-id="analytics">';
        $analytics_module->render_settings();
        echo '</div></div></div>';
    }

    /**
     * Render Business Info page
     *
     * When BusinessInfo module is active, delegates to the module's render_settings().
     * Falls back to the legacy brighter-core form if the module is not loaded.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_business_info_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'site-essentials' ) );
        }

        echo '<div class="wrap site-essentials-wrap">';
        echo '<h1>' . esc_html__( 'Business Info', 'site-essentials' ) . '</h1>';
        echo '<div class="site-essentials-content">';
        echo '<div class="card se-module-settings-card" data-module-id="business_info">';

        $biz_module = Module_Loader::get_module( 'business_info' );
        if ( $biz_module ) {
            $biz_module->render_settings();
        } elseif ( function_exists( 'brighterweb_render_business_info_form' ) ) {
            brighterweb_render_business_info_form();
        } else {
            echo '<div class="notice notice-warning"><p>';
            esc_html_e( 'Business Info module is not loaded. Ensure brighter-core is active.', 'site-essentials' );
            echo '</p></div>';
        }

        echo '</div></div></div>';
    }

    /**
     * Render Site Schema page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_site_schema_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'site-essentials' ) );
        }

        $schema_module = Module_Loader::get_module( 'site_schema' );

        if ( ! $schema_module ) {
            echo '<div class="wrap"><div class="notice notice-warning"><p>';
            esc_html_e( 'Site Schema module is not loaded.', 'site-essentials' );
            echo '</p></div></div>';
            return;
        }

        echo '<div class="wrap site-essentials-wrap">';
        echo '<h1>' . esc_html__( 'Site Schema', 'site-essentials' ) . '</h1>';
        echo '<div class="site-essentials-content">';
        echo '<div class="card se-module-settings-card" data-module-id="site_schema">';
        $schema_module->render_settings();
        echo '</div></div></div>';
    }

    /**
     * Render module disabled notice
     *
     * Shows a friendly notice when trying to access a disabled module,
     * with option to enable it or go back to settings.
     *
     * @since 1.0.0
     * @param string $module_name Display name of the module (e.g., "SEO", "Essentials")
     * @param string $module_id   Module ID slug (e.g., "seo", "tweaks")
     * @return void
     */
    private function render_module_disabled_notice($module_name, $module_id) {
        $settings_url = admin_url('admin.php?page=' . self::SETTINGS_PAGE_SLUG . '&tab=modules');
        $enable_url = wp_nonce_url(
            add_query_arg([
                'action' => 'enable_module',
                'module' => $module_id
            ], admin_url('admin.php?page=' . self::PAGE_SLUG)),
            'enable_module_' . $module_id
        );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($module_name); ?></h1>

            <div class="notice notice-warning" style="margin-top: 20px; padding: 20px; max-width: 800px;">
                <h2 style="margin-top: 0;">
                    <?php
                    /* translators: %s: module name */
                    printf(esc_html__('⚠️ %s Module Disabled', 'site-essentials'), esc_html($module_name));
                    ?>
                </h2>
                <p>
                    <?php
                    /* translators: %s: module name */
                    printf(
                        esc_html__('The %s module is currently turned off. Enable it to access its features.', 'site-essentials'),
                        '<strong>' . esc_html($module_name) . '</strong>'
                    );
                    ?>
                </p>
                <p style="margin-bottom: 0;">
                    <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary">
                        <?php esc_html_e('Go to Module Settings', 'site-essentials'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>" class="button">
                        <?php esc_html_e('← Back to Dashboard', 'site-essentials'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
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
        error_log("========== AJAX TOGGLE START ==========");
        error_log("[Admin_UI] ajax_toggle_module() called");
        error_log("[Admin_UI] POST data: " . json_encode($_POST));

        check_ajax_referer('site_essentials_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            error_log("[Admin_UI] FAILED: Insufficient permissions");
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $module_id = isset($_POST['module_id']) ? sanitize_key($_POST['module_id']) : '';

        // CRITICAL: filter_var() properly handles string "false" and "true" from AJAX
        // (bool) "false" would incorrectly evaluate to true!
        $enabled = isset($_POST['enabled']) ? filter_var($_POST['enabled'], FILTER_VALIDATE_BOOLEAN) : false;

        error_log("[Admin_UI] Module: {$module_id}, Action: " . ($enabled ? 'ENABLE' : 'DISABLE'));
        error_log("[Admin_UI] Raw POST enabled value: " . var_export($_POST['enabled'], true) . " (type: " . gettype($_POST['enabled']) . ")");

        if (empty($module_id)) {
            error_log("[Admin_UI] FAILED: Module ID required");
            wp_send_json_error(['message' => 'Module ID required']);
        }

        // Get state BEFORE toggle
        $before_memory = $this->settings->get('enabled_modules', []);
        $before_db = get_option(Settings_Manager::CORE_OPTION, []);
        $before_db_modules = isset($before_db['enabled_modules']) ? $before_db['enabled_modules'] : [];

        error_log("[Admin_UI] State BEFORE toggle - Memory: " . json_encode($before_memory));
        error_log("[Admin_UI] State BEFORE toggle - DB: " . json_encode($before_db_modules));

        // Perform the toggle
        $result = false;
        if ($enabled) {
            error_log("[Admin_UI] Calling enable_module({$module_id})");
            $result = $this->settings->enable_module($module_id);

            // Flush rewrite rules when enabling SEO module to register sitemap endpoints
            if ($module_id === 'seo') {
                flush_rewrite_rules();
            }
            // Flush rewrite rules when enabling CPT module (projects archive)
            if ($module_id === 'cpt') {
                flush_rewrite_rules();
            }
        } else {
            error_log("[Admin_UI] Calling disable_module({$module_id})");
            $result = $this->settings->disable_module($module_id);

            // CRITICAL: Clear caches and rewrite rules when disabling modules
            // This ensures sitemap URLs and other module functionality is properly removed
            if ($module_id === 'seo') {
                // Clear sitemap cache
                Cache_Helper::flush('seo');
                // Flush rewrite rules to remove sitemap endpoints
                flush_rewrite_rules();
                // Clear LiteSpeed cache if active
                if (function_exists('litespeed_purge_all')) {
                    litespeed_purge_all();
                }
                // Clear standard WordPress cache
                wp_cache_flush();
            }
            if ($module_id === 'cpt') {
                flush_rewrite_rules();
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

        error_log("[Admin_UI] State AFTER toggle - Memory: " . json_encode($after_memory));
        error_log("[Admin_UI] State AFTER toggle - DB (get_option): " . json_encode($after_db_modules_getoption));
        error_log("[Admin_UI] State AFTER toggle - DB (direct query): " . json_encode($after_db_modules_direct));
        error_log("[Admin_UI] Verification: " . ($verified ? 'PASSED' : 'FAILED'));
        error_log("[Admin_UI] Expected: {$module_id} should be " . ($enabled ? 'ENABLED' : 'DISABLED'));
        error_log("[Admin_UI] Actual in DB: {$module_id} is " . ($is_now_enabled_in_db ? 'ENABLED' : 'DISABLED'));

        // Check if there's a cache mismatch
        $cache_mismatch = ($after_db_modules_getoption !== $after_db_modules_direct);
        error_log("[Admin_UI] Cache mismatch: " . ($cache_mismatch ? 'YES' : 'NO'));
        error_log("========== AJAX TOGGLE END ==========");

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
     * Handle Asset Preload form when POSTed to Performance > Asset Preloading (same-page submit).
     * Does not use admin-post.php so it works regardless of brighter-core load order.
     *
     * @since 1.0.0
     */
    public function maybe_save_asset_preload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        if (!isset($_GET['page']) || $_GET['page'] !== self::ESSENTIALS_PAGE_SLUG) {
            return;
        }
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'asset-preloading') {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        if (empty($_POST['bw_tweaks_nonce']) || !wp_verify_nonce($_POST['bw_tweaks_nonce'], 'bw_tweaks_save')) {
            return;
        }
        if (!class_exists('Brighter_Tweaks')) {
            return;
        }
        if (!\Brighter_Tweaks::process_save()) {
            return;
        }
        $redirect = add_query_arg([
            'page'           => self::ESSENTIALS_PAGE_SLUG,
            'tab'            => 'asset-preloading',
            'tweaks_saved'    => '1',
        ], admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
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
     * Save Custom Posts (CPT) settings
     *
     * @since 1.0.0
     * @return void
     */
    public function save_cpt_settings() {
        if (!isset($_POST['site_essentials_cpt_nonce']) ||
            !wp_verify_nonce($_POST['site_essentials_cpt_nonce'], 'site_essentials_cpt')) {
            wp_die(__('Security check failed', 'site-essentials'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'site-essentials'));
        }

        $cpt_options = isset($_POST['cpt_options']) && is_array($_POST['cpt_options']) ? $_POST['cpt_options'] : [];

        $opts = [
            'customer_success_stories'  => !empty($cpt_options['customer_success_stories']),
            'include_categories'        => !empty($cpt_options['include_categories']),
            'include_tags'              => !empty($cpt_options['include_tags']),
            'archive_slug'              => isset($cpt_options['archive_slug']) ? sanitize_title($cpt_options['archive_slug']) : 'projects',
            'enable_faq'                => !empty($cpt_options['enable_faq']),
            'enable_author_extension'   => !empty($cpt_options['enable_author_extension']),
            'enable_reviews'            => !empty($cpt_options['enable_reviews']),
        ];

        $this->settings->update_module_settings('cpt', $opts);
        
        // Module 15: Sync Author Extension enabled state
        if (!empty($opts['enable_author_extension'])) {
            update_option('bw_author_extension_enabled', true);
        } else {
            update_option('bw_author_extension_enabled', false);
        }

        flush_rewrite_rules();

        $redirect_url = add_query_arg([
            'page'    => self::CPT_PAGE_SLUG,
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
