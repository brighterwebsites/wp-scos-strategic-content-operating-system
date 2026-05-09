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
        if ( ! defined( 'SCOS_TWEAKS_ACTIVE' ) ) {
            define( 'SCOS_TWEAKS_ACTIVE', true );
        }

        // Get enabled tweaks
        $tweaks = $this->settings->get_module_setting('tweaks', 'enabled_tweaks', $this->get_default_tweaks());

        // Apply each enabled tweak
        foreach ($tweaks as $tweak => $enabled) {
            if ($enabled) {
                $this->apply_tweak($tweak);
            }
        }

        // Restore WordPress defaults for any tweaks brighter-core removed unconditionally.
        // brighter-core runs its removals at init priority 1; we run at priority 10.
        $this->restore_defaults($tweaks);

        // Register admin settings if in admin
        if (is_admin()) {
            add_action('admin_init', [$this, 'register_settings']);
        }
    }

    /**
     * Restore WordPress default hooks that brighter-core removes unconditionally.
     * Called after apply_tweak loop so we only restore what the user hasn't opted into removing.
     *
     * @since 1.0.0
     * @param array $tweaks Current tweak settings.
     * @return void
     */
    private function restore_defaults( $tweaks ) {
        // ── Emoji ───────────────────────────────────────────────────────────────
        if ( empty( $tweaks['disable_emojis'] ) ) {
            add_action( 'wp_head', 'print_emoji_detection_script', 7 );
            add_action( 'wp_print_styles', 'print_emoji_styles' );
            add_action( 'admin_print_scripts', 'print_emoji_detection_script' );
            add_action( 'admin_print_styles', 'print_emoji_styles' );
        }

        // ── RSD / WLW / WP version ──────────────────────────────────────────────
        if ( empty( $tweaks['remove_rsd_link'] ) ) {
            add_action( 'wp_head', 'rsd_link' );
        }
        if ( empty( $tweaks['remove_wlw_link'] ) ) {
            add_action( 'wp_head', 'wlwmanifest_link' );
        }
        if ( empty( $tweaks['remove_wp_version'] ) ) {
            add_action( 'wp_head', 'wp_generator' );
        }

        // ── Heartbeat ───────────────────────────────────────────────────────────
        // brighter-core always sets interval to 60s via an anonymous closure.
        // We override at higher priority to restore the WP default (15s).
        if ( empty( $tweaks['optimize_heartbeat'] ) ) {
            add_filter( 'heartbeat_settings', static function ( $settings ) {
                $settings['interval'] = 15;
                return $settings;
            }, 20 );
        }

        // ── Google Fonts ────────────────────────────────────────────────────────
        // brighter-tweaks::boot() always schedules remove_google_fonts on wp_loaded.
        // We always unschedule it — if the option is ON we handle it ourselves
        // with a cleaner dequeue + filter approach (no output buffering).
        if ( class_exists( 'Brighter_Tweaks' ) ) {
            remove_action( 'wp_loaded', [ 'Brighter_Tweaks', 'remove_google_fonts' ] );
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
            case 'disable_embeds_outbound':
                $this->disable_embeds_outbound();
                break;

            case 'disable_embeds_inbound':
                $this->disable_embeds_inbound();
                break;

            case 'remove_google_fonts':
                $this->remove_google_fonts();
                break;

            case 'remove_shortlink':
                $this->remove_shortlink();
                break;

            case 'remove_rest_api_links':
                $this->remove_rest_api_links();
                break;

            case 'disable_rest_api':
                $this->disable_rest_api();
                break;

            case 'restrict_rest_users':
                add_filter( 'rest_endpoints', function( $endpoints ) {
                    if ( ! is_user_logged_in() ) {
                        unset( $endpoints['/wp/v2/users'] );
                        unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
                    }
                    return $endpoints;
                } );
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
        // Prevent authentication
        add_filter( 'xmlrpc_enabled', '__return_false' );

        // Strip X-Pingback header
        add_filter( 'wp_headers', static function ( $headers ) {
            unset( $headers['X-Pingback'] );
            return $headers;
        } );

        // Block POST requests too — returns 403 before any XML-RPC call is processed
        add_action( 'xmlrpc_call', static function () {
            wp_die(
                __( 'XML-RPC is disabled on this site.', 'site-essentials' ),
                __( 'XML-RPC Disabled', 'site-essentials' ),
                [ 'response' => 403 ]
            );
        }, 1 );
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
     * Remove query string from URL — frontend only, never admin.
     *
     * @since  1.0.0
     * @param  string $src URL
     * @return string URL without query string
     */
    public function remove_query_string($src) {
        // Never strip ?ver= on admin pages — it's required for cache-busting after deploys.
        if ( is_admin() ) {
            return $src;
        }
        if (strpos($src, '?ver=')) {
            $src = remove_query_arg('ver', $src);
        }
        return $src;
    }

    /**
     * Disable outbound embeds — stops WordPress auto-converting pasted URLs into iframes.
     *
     * @since 1.0.0
     * @return void
     */
    private function disable_embeds_outbound() {
        // Must run after WP_Embed is instantiated (it hooks itself at wp-settings load time).
        // Priority 9999 on init fires after our own init-10 bootstrap; $wp_embed is already set.
        add_action( 'init', static function () {
            global $wp_embed;
            if ( ! empty( $wp_embed ) ) {
                // This is the key hook: WordPress scans content for plain URLs and
                // auto-converts them to [embed] shortcodes then to iframes.
                remove_filter( 'the_content', [ $wp_embed, 'autoembed' ], 8 );
            }
            // Also block discovery of new oEmbed providers from unknown URLs
            add_filter( 'embed_oembed_discover', '__return_false' );
        }, 9999 );
    }

    /**
     * Disable inbound embeds — stops other sites from embedding this site's content.
     *
     * wp_oembed_add_host_js is intentionally NOT removed: the wp-embed.js script it
     * enqueues is also used by WordPress to render outbound embed players on your own
     * pages — removing it would break YouTube/Vimeo iframes on your own site.
     *
     * @since 1.0.0
     * @return void
     */
    private function disable_embeds_inbound() {
        add_action( 'init', static function () {
            // Remove the REST API endpoint that oEmbed clients call to get your embed HTML
            remove_action( 'rest_api_init', 'wp_oembed_register_route' );
            // Remove the <link rel="alternate" type="application/json+oembed"> discovery tags
            remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
        }, 9999 );
    }

    /**
     * Remove Google Fonts — delegates to Brighter_Tweaks if available, else own implementation.
     *
     * @since 1.0.0
     * @return void
     */
    private function remove_google_fonts() {
        // Step 1 — dequeue known handles at priority 999 (fires after all plugins enqueue).
        // Covers: Breakdance ('breakdance-google-fonts'), themes, and common plugin handles.
        add_action( 'wp_enqueue_scripts', static function () {
            $handles = [
                'google-fonts',
                'breakdance-google-fonts',
                'breakdance-fonts',
                'bde-google-fonts',
            ];
            foreach ( $handles as $handle ) {
                wp_dequeue_style( $handle );
                wp_deregister_style( $handle );
            }
        }, 999 );

        // Step 2 — sweep all registered styles; dequeue any whose src is fonts.googleapis.com.
        // Catches dynamically-named handles we can't predict.
        add_action( 'wp_print_styles', static function () {
            global $wp_styles;
            if ( ! $wp_styles instanceof \WP_Styles ) {
                return;
            }
            foreach ( array_keys( $wp_styles->registered ) as $handle ) {
                $src = $wp_styles->registered[ $handle ]->src ?? '';
                if ( $src && strpos( $src, 'fonts.googleapis.com' ) !== false ) {
                    wp_dequeue_style( $handle );
                }
            }
        }, 1 );

        // Step 3 — final catch-all on the rendered tag (handles any that slipped through).
        add_filter( 'style_loader_tag', static function ( $tag ) {
            return strpos( $tag, 'fonts.googleapis.com' ) !== false ? '' : $tag;
        }, 20, 1 );

        // Step 4 — suppress LiteSpeed Cache's Google Fonts pipeline.
        // LiteSpeed has two settings that re-inject fonts AFTER our dequeue:
        //   optm.gfonts_remove  — LiteSpeed's own "Remove Google Fonts" (strips then re-adds async)
        //   optm.gfonts         — "Load Google Fonts Asynchronously" (injects via WebFont JS)
        // Forcing both off here means our removal wins regardless of the LiteSpeed settings.
        add_filter( 'litespeed_conf', static function ( $val, $key = '' ) {
            if ( 'optm.gfonts_remove' === $key ) {
                return false;
            }
            if ( 'optm.gfonts' === $key ) {
                return '';
            }
            return $val;
        }, PHP_INT_MAX, 2 );

        // Belt-and-suspenders: LiteSpeed also exposes a direct action for its font output.
        add_filter( 'litespeed_optm_gfonts', '__return_empty_string', PHP_INT_MAX );
    }

    /**
     * Remove shortlink <link> tag from <head>
     *
     * @since 1.0.0
     * @return void
     */
    private function remove_shortlink() {
        remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
        // Also suppress the Link: header sent via wp_shortlink_header
        remove_action( 'send_headers', 'wp_shortlink_header', 10 );
    }

    /**
     * Remove REST API discovery <link> tags from <head>.
     *
     * Removes:
     *   <link rel="https://api.w.org/" href="…/wp-json/" />
     *   <link rel="alternate" type="application/json" href="…/wp-json/wp/v2/pages/N" />
     *
     * @since 1.0.0
     * @return void
     */
    private function remove_rest_api_links() {
        remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
        remove_action( 'template_redirect', 'rest_output_link_header', 11 );
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
            
            // CRITICAL: Whitelist specific REST API endpoints that need unauthenticated access
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            
            // Whitelist patterns:
            $whitelist_patterns = [
                '/wp-json/wc/',           // WooCommerce endpoints
                '/wp-json/brighter/',     // Brighter custom endpoints (GPT, Make, etc.)
                '/wp-json/brighter-core/', // Brighter Core endpoints (social amplification, etc.)
                '/wp-json/brighter-x/',   // Brighter-X endpoints
            ];
            
            foreach ($whitelist_patterns as $pattern) {
                if (strpos($request_uri, $pattern) !== false) {
                    return $result; // Allow this endpoint
                }
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
            // Performance & Speed
            'disable_emojis'           => false,
            'remove_jquery_migrate'    => false,
            'optimize_heartbeat'       => false,
            'remove_query_strings'     => false,
            'disable_embeds_outbound'  => false,
            'remove_google_fonts'      => false,
            // Security & Hardening
            'disable_xmlrpc'           => false,
            'disable_rest_api'         => false,
            'restrict_rest_users'      => false,
            // SEO & Metadata Code Cleanup
            'remove_rsd_link'          => false,
            'remove_wlw_link'          => false,
            'remove_wp_version'        => false,
            'remove_shortlink'         => false,
            'remove_rest_api_links'    => false,
            'disable_embeds_inbound'   => false,
            // Legacy key — preserved for backwards compat but hidden from UI
            'disable_embeds'           => false,
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
