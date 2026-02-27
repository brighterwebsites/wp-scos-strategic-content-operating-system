<?php
/**
 * WordPress Tweaks Module
 *
 * Provides various WordPress tweaks and optimizations:
 * - Disable emojis
 * - Remove jQuery Migrate
 * - Disable XML-RPC
 * - Remove RSD/Windows Live Writer links
 * - Remove WordPress version meta
 * - Heartbeat optimization
 * - And more...
 *
 * @package    SiteEssentials
 * @subpackage Modules\Tweaks
 * @version    1.0.0
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\Tweaks;

use SiteEssentials\Core\Module_Interface;
use SiteEssentials\Core\Settings_Manager;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tweaks Module Class
 *
 * Implements various WordPress optimizations and tweaks.
 *
 * @since 1.0.0
 */
class Tweaks_Module implements Module_Interface {
    /**
     * Settings Manager instance
     *
     * @since 1.0.0
     * @var   Settings_Manager
     */
    private $settings;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->settings = Settings_Manager::instance();
    }

    /**
     * Get module ID
     *
     * @since  1.0.0
     * @return string
     */
    public static function get_id() {
        return 'tweaks';
    }

    /**
     * Get module name
     *
     * @since  1.0.0
     * @return string
     */
    public static function get_name() {
        return __('WordPress Tweaks', 'site-essentials');
    }

    /**
     * Get module description
     *
     * @since  1.0.0
     * @return string
     */
    public static function get_description() {
        return __('Performance and security tweaks for WordPress. Disable unnecessary features and optimize WordPress behavior.', 'site-essentials');
    }

    /**
     * Get module tier
     *
     * @since  1.0.0
     * @return string
     */
    public static function get_tier() {
        return 'basic';
    }

    /**
     * Get module dependencies
     *
     * @since  1.0.0
     * @return array
     */
    public static function get_dependencies() {
        return []; // No dependencies
    }

    /**
     * Get module version
     *
     * @since  1.0.0
     * @return string
     */
    public static function get_version() {
        return '1.0.0';
    }

    /**
     * Initialize module
     *
     * Register hooks and filters based on enabled tweaks.
     *
     * @since 1.0.0
     * @return void
     */
    public function init() {
        // Get enabled tweaks
        $tweaks = $this->settings->get_module_setting('tweaks', 'enabled_tweaks', $this->get_default_tweaks());

        // Apply each enabled tweak
        foreach ($tweaks as $tweak => $enabled) {
            if ($enabled) {
                $this->apply_tweak($tweak);
            }
        }

        // Register admin settings if in admin
        if (is_admin()) {
            add_action('admin_init', [$this, 'register_settings']);
        }
    }

    /**
     * Apply a specific tweak
     *
     * @since 1.0.0
     * @param string $tweak Tweak ID
     * @return void
     */
    private function apply_tweak($tweak) {
        switch ($tweak) {
            case 'disable_emojis':
                $this->disable_emojis();
                break;

            case 'remove_jquery_migrate':
                $this->remove_jquery_migrate();
                break;

            case 'disable_xmlrpc':
                $this->disable_xmlrpc();
                break;

            case 'remove_rsd_link':
                remove_action('wp_head', 'rsd_link');
                break;

            case 'remove_wlw_link':
                remove_action('wp_head', 'wlwmanifest_link');
                break;

            case 'remove_wp_version':
                remove_action('wp_head', 'wp_generator');
                break;

            case 'optimize_heartbeat':
                $this->optimize_heartbeat();
                break;

            case 'remove_query_strings':
                $this->remove_query_strings();
                break;

            case 'disable_embeds':
                $this->disable_embeds();
                break;

            case 'disable_rest_api':
                $this->disable_rest_api();
                break;
        }
    }

    /**
     * Disable WordPress emojis
     *
     * @since 1.0.0
     * @return void
     */
    private function disable_emojis() {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

        add_filter('tiny_mce_plugins', function($plugins) {
            if (is_array($plugins)) {
                return array_diff($plugins, ['wpemoji']);
            }
            return $plugins;
        });

        add_filter('wp_resource_hints', function($urls, $relation_type) {
            if ('dns-prefetch' === $relation_type) {
                $emoji_svg_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/');
                $urls = array_diff($urls, [$emoji_svg_url]);
            }
            return $urls;
        }, 10, 2);
    }

    /**
     * Remove jQuery Migrate
     *
     * @since 1.0.0
     * @return void
     */
    private function remove_jquery_migrate() {
        add_action('wp_default_scripts', function($scripts) {
            if (!is_admin() && isset($scripts->registered['jquery'])) {
                $script = $scripts->registered['jquery'];
                if ($script->deps) {
                    $script->deps = array_diff($script->deps, ['jquery-migrate']);
                }
            }
        });
    }

    /**
     * Disable XML-RPC
     *
     * @since 1.0.0
     * @return void
     */
    private function disable_xmlrpc() {
        add_filter('xmlrpc_enabled', '__return_false');

        add_filter('wp_headers', function($headers) {
            unset($headers['X-Pingback']);
            return $headers;
        });
    }

    /**
     * Optimize Heartbeat API
     *
     * @since 1.0.0
     * @return void
     */
    private function optimize_heartbeat() {
        add_filter('heartbeat_settings', function($settings) {
            $settings['interval'] = 60; // Default 15s -> 60s
            return $settings;
        });

        // Disable on front-end
        add_action('init', function() {
            if (!is_admin()) {
                wp_deregister_script('heartbeat');
            }
        }, 1);
    }

    /**
     * Remove query strings from static resources
     *
     * @since 1.0.0
     * @return void
     */
    private function remove_query_strings() {
        add_filter('script_loader_src', [$this, 'remove_query_string'], 15, 1);
        add_filter('style_loader_src', [$this, 'remove_query_string'], 15, 1);
    }

    /**
     * Remove query string from URL
     *
     * @since  1.0.0
     * @param  string $src URL
     * @return string URL without query string
     */
    public function remove_query_string($src) {
        if (strpos($src, '?ver=')) {
            $src = remove_query_arg('ver', $src);
        }
        return $src;
    }

    /**
     * Disable WordPress embeds
     *
     * @since 1.0.0
     * @return void
     */
    private function disable_embeds() {
        add_action('init', function() {
            // Remove the REST API endpoint
            remove_action('rest_api_init', 'wp_oembed_register_route');

            // Turn off oEmbed auto discovery
            add_filter('embed_oembed_discover', '__return_false');

            // Don't filter oEmbed results
            remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);

            // Remove oEmbed discovery links
            remove_action('wp_head', 'wp_oembed_add_discovery_links');

            // Remove oEmbed-specific JavaScript
            remove_action('wp_head', 'wp_oembed_add_host_js');
        }, 9999);
    }

    /**
     * Disable REST API for non-logged users
     *
     * @since 1.0.0
     * @return void
     */
    private function disable_rest_api() {
        add_filter('rest_authentication_errors', function($result) {
            // Allow if user is logged in
            if (is_user_logged_in()) {
                return $result;
            }
            
            // CRITICAL: Whitelist WooCommerce REST API endpoints
            // WooCommerce needs unauthenticated access for:
            // - Storefront product data
            // - Add to cart operations
            // - Checkout process
            // - Payment webhooks (Stripe, PayPal, etc.)
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            
            // Allow WooCommerce endpoints
            if (strpos($request_uri, '/wp-json/wc/') !== false) {
                return $result; // Allow WooCommerce
            }
            
            // Block all other unauthenticated REST requests
            return new \WP_Error(
                'rest_not_logged_in',
                __('You are not currently logged in.', 'site-essentials'),
                ['status' => 401]
            );
        });
    }

    /**
     * Get default tweaks (all disabled by default)
     *
     * @since  1.0.0
     * @return array
     */
    private function get_default_tweaks() {
        return [
            'disable_emojis'        => false,
            'remove_jquery_migrate' => false,
            'disable_xmlrpc'        => false,
            'remove_rsd_link'       => false,
            'remove_wlw_link'       => false,
            'remove_wp_version'     => false,
            'optimize_heartbeat'    => false,
            'remove_query_strings'  => false,
            'disable_embeds'        => false,
            'disable_rest_api'      => false,
        ];
    }

    /**
     * Register settings
     *
     * @since 1.0.0
     * @return void
     */
    public function register_settings() {
        // Settings will be handled by Settings_Manager
        // This is a placeholder for future admin UI integration
    }

    /**
     * Render settings section
     *
     * @since 1.0.0
     * @return void
     */
    public function render_settings() {
        $tweaks = $this->settings->get_module_setting('tweaks', 'enabled_tweaks', $this->get_default_tweaks());

        include __DIR__ . '/views/settings.php';
    }
}
