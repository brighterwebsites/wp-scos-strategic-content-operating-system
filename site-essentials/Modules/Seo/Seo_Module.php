<?php
/**
 * SEO Module
 *
 * Provides comprehensive SEO features:
 * - XML Sitemap generation
 * - Image sitemaps
 * - Sitemap index
 * - Google Search Console integration (future)
 *
 * @package    SiteEssentials
 * @subpackage Modules\Seo
 * @version    1.0.0
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\Seo;

use SiteEssentials\Core\Module_Interface;
use SiteEssentials\Core\Settings_Manager;
use SiteEssentials\Core\Cache_Helper;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SEO Module Class
 *
 * Handles sitemap generation and SEO features.
 *
 * @since 1.0.0
 */
class Seo_Module implements Module_Interface {
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
        return 'seo';
    }

    /**
     * Get module name
     *
     * @since  1.0.0
     * @return string
     */
    public static function get_name() {
        return __('SEO & Sitemaps', 'site-essentials');
    }

    /**
     * Get module description
     *
     * @since  1.0.0
     * @return string
     */
    public static function get_description() {
        return __('XML sitemap generation, image sitemaps, and SEO optimization. Replacement for SEOPress sitemap issues.', 'site-essentials');
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
     * Register sitemap endpoints and hooks.
     *
     * @since 1.0.0
     * @return void
     */
    public function init() {
        // Add rewrite rules for sitemaps
        add_action('init', [$this, 'register_sitemap_rewrites']);

        // Handle sitemap requests
        add_action('template_redirect', [$this, 'serve_sitemap'], 1);

        // Flush rewrite rules on activation
        if (!get_option('se_sitemap_rules_flushed')) {
            flush_rewrite_rules();
            update_option('se_sitemap_rules_flushed', true);
        }

        // Add admin notices for conflicting plugins
        if (is_admin()) {
            add_action('admin_notices', [$this, 'check_conflicts']);
        }
    }

    /**
     * Register sitemap rewrite rules
     *
     * @since 1.0.0
     * @return void
     */
    public function register_sitemap_rewrites() {
        // Main sitemap index
        add_rewrite_rule(
            '^sitemap\.xml$',
            'index.php?se_sitemap=index',
            'top'
        );

        // Post type sitemaps
        add_rewrite_rule(
            '^sitemap-([a-z0-9_-]+)\.xml$',
            'index.php?se_sitemap=$matches[1]',
            'top'
        );

        // Register query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'se_sitemap';
            return $vars;
        });
    }

    /**
     * Serve sitemap based on request
     *
     * @since 1.0.0
     * @return void
     */
    public function serve_sitemap() {
        $sitemap = get_query_var('se_sitemap');

        if (!$sitemap) {
            return;
        }

        // Get sitemap settings
        $settings = $this->get_sitemap_settings();

        if ($sitemap === 'index') {
            $this->render_sitemap_index($settings);
        } else {
            $this->render_post_type_sitemap($sitemap, $settings);
        }

        exit;
    }

    /**
     * Get sitemap settings
     *
     * @since  1.0.0
     * @return array Sitemap settings
     */
    private function get_sitemap_settings() {
        return $this->settings->get_module_setting('seo', 'sitemap', $this->get_default_sitemap_settings());
    }

    /**
     * Get default sitemap settings
     *
     * @since  1.0.0
     * @return array Default settings
     */
    private function get_default_sitemap_settings() {
        return [
            'enabled'         => true,
            'post_types'      => ['post', 'page'],
            'include_images'  => true,
            'exclude_ids'     => [],
            'entries_per_sitemap' => 2000,
        ];
    }

    /**
     * Render sitemap index
     *
     * @since 1.0.0
     * @param array $settings Sitemap settings
     * @return void
     */
    private function render_sitemap_index($settings) {
        $cache_key = 'sitemap_index';
        $xml = Cache_Helper::remember($cache_key, function() use ($settings) {
            return $this->generate_sitemap_index($settings);
        }, 3600, 'seo');

        header('Content-Type: application/xml; charset=utf-8');
        echo $xml;
    }

    /**
     * Generate sitemap index XML
     *
     * @since  1.0.0
     * @param  array $settings Sitemap settings
     * @return string XML content
     */
    private function generate_sitemap_index($settings) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $post_types = $settings['post_types'];

        foreach ($post_types as $post_type) {
            $post_type_obj = get_post_type_object($post_type);
            if (!$post_type_obj) {
                continue;
            }

            // Get last modified date
            $last_modified = $this->get_post_type_last_modified($post_type);

            $xml .= "\t<sitemap>\n";
            $xml .= "\t\t<loc>" . esc_url(home_url("/sitemap-{$post_type}.xml")) . "</loc>\n";
            if ($last_modified) {
                $xml .= "\t\t<lastmod>" . mysql2date('c', $last_modified, false) . "</lastmod>\n";
            }
            $xml .= "\t</sitemap>\n";
        }

        $xml .= '</sitemapindex>';

        return $xml;
    }

    /**
     * Render post type sitemap
     *
     * @since 1.0.0
     * @param string $post_type Post type
     * @param array  $settings  Sitemap settings
     * @return void
     */
    private function render_post_type_sitemap($post_type, $settings) {
        // Validate post type
        if (!in_array($post_type, $settings['post_types'], true)) {
            status_header(404);
            return;
        }

        $cache_key = 'sitemap_' . $post_type;
        $xml = Cache_Helper::remember($cache_key, function() use ($post_type, $settings) {
            return $this->generate_post_type_sitemap($post_type, $settings);
        }, 3600, 'seo');

        header('Content-Type: application/xml; charset=utf-8');
        echo $xml;
    }

    /**
     * Generate post type sitemap XML
     *
     * @since  1.0.0
     * @param  string $post_type Post type
     * @param  array  $settings  Sitemap settings
     * @return string XML content
     */
    private function generate_post_type_sitemap($post_type, $settings) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';

        if ($settings['include_images']) {
            $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        }

        $xml .= '>' . "\n";

        // Get posts
        $posts = $this->get_sitemap_posts($post_type, $settings);

        foreach ($posts as $post) {
            $xml .= $this->generate_url_entry($post, $settings);
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Get posts for sitemap
     *
     * @since  1.0.0
     * @param  string $post_type Post type
     * @param  array  $settings  Sitemap settings
     * @return array  Posts
     */
    private function get_sitemap_posts($post_type, $settings) {
        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $settings['entries_per_sitemap'],
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'post__not_in'   => $settings['exclude_ids'],
        ];

        return get_posts($args);
    }

    /**
     * Generate URL entry for post
     *
     * @since  1.0.0
     * @param  WP_Post $post     Post object
     * @param  array   $settings Sitemap settings
     * @return string  XML URL entry
     */
    private function generate_url_entry($post, $settings) {
        $xml = "\t<url>\n";
        $xml .= "\t\t<loc>" . esc_url(get_permalink($post)) . "</loc>\n";
        $xml .= "\t\t<lastmod>" . mysql2date('c', $post->post_modified_gmt, false) . "</lastmod>\n";
        $xml .= "\t\t<changefreq>" . $this->get_changefreq($post) . "</changefreq>\n";
        $xml .= "\t\t<priority>" . $this->get_priority($post) . "</priority>\n";

        // Add images if enabled
        if ($settings['include_images']) {
            $images = $this->get_post_images($post);
            foreach ($images as $image) {
                $xml .= "\t\t<image:image>\n";
                $xml .= "\t\t\t<image:loc>" . esc_url($image['url']) . "</image:loc>\n";
                if (!empty($image['title'])) {
                    $xml .= "\t\t\t<image:title>" . esc_html($image['title']) . "</image:title>\n";
                }
                $xml .= "\t\t</image:image>\n";
            }
        }

        $xml .= "\t</url>\n";

        return $xml;
    }

    /**
     * Get change frequency for post
     *
     * @since  1.0.0
     * @param  WP_Post $post Post object
     * @return string  Change frequency
     */
    private function get_changefreq($post) {
        $age_days = (time() - strtotime($post->post_modified_gmt)) / DAY_IN_SECONDS;

        if ($age_days < 1) {
            return 'hourly';
        } elseif ($age_days < 7) {
            return 'daily';
        } elseif ($age_days < 30) {
            return 'weekly';
        } elseif ($age_days < 365) {
            return 'monthly';
        } else {
            return 'yearly';
        }
    }

    /**
     * Get priority for post
     *
     * @since  1.0.0
     * @param  WP_Post $post Post object
     * @return string  Priority value
     */
    private function get_priority($post) {
        // Homepage and key pages get higher priority
        if ($post->post_type === 'page') {
            if (get_option('page_on_front') == $post->ID) {
                return '1.0';
            }
            return '0.8';
        }

        // Posts
        return '0.6';
    }

    /**
     * Get images from post
     *
     * @since  1.0.0
     * @param  WP_Post $post Post object
     * @return array   Array of image data
     */
    private function get_post_images($post) {
        $images = [];

        // Featured image
        if (has_post_thumbnail($post->ID)) {
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            $image_url = wp_get_attachment_image_url($thumbnail_id, 'full');
            $image_title = get_the_title($thumbnail_id);

            if ($image_url) {
                $images[] = [
                    'url'   => $image_url,
                    'title' => $image_title,
                ];
            }
        }

        // Content images (basic extraction)
        preg_match_all('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $post->post_content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $img_url) {
                // Skip if already added or external
                if (strpos($img_url, home_url()) === false) {
                    continue;
                }

                $images[] = [
                    'url'   => $img_url,
                    'title' => '',
                ];
            }
        }

        // Limit to 10 images per post (Google recommendation)
        return array_slice($images, 0, 10);
    }

    /**
     * Get last modified date for post type
     *
     * @since  1.0.0
     * @param  string $post_type Post type
     * @return string|false Last modified date or false
     */
    private function get_post_type_last_modified($post_type) {
        global $wpdb;

        $last_modified = $wpdb->get_var($wpdb->prepare(
            "SELECT post_modified_gmt FROM {$wpdb->posts}
            WHERE post_type = %s
            AND post_status = 'publish'
            ORDER BY post_modified_gmt DESC
            LIMIT 1",
            $post_type
        ));

        return $last_modified;
    }

    /**
     * Check for conflicting plugins
     *
     * @since 1.0.0
     * @return void
     */
    public function check_conflicts() {
        $conflicts = [];

        // Check for SEOPress
        if (is_plugin_active('wp-seopress/seopress.php') || is_plugin_active('wp-seopress-pro/seopress-pro.php')) {
            $conflicts[] = 'SEOPress';
        }

        // Check for Yoast SEO
        if (is_plugin_active('wordpress-seo/wp-seo.php') || is_plugin_active('wordpress-seo-premium/wp-seo-premium.php')) {
            $conflicts[] = 'Yoast SEO';
        }

        // Check for Rank Math
        if (is_plugin_active('seo-by-rank-math/rank-math.php')) {
            $conflicts[] = 'Rank Math';
        }

        if (!empty($conflicts)) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>Site Essentials SEO:</strong> Detected sitemap conflict with: ' . implode(', ', $conflicts) . '. ';
            echo 'Please disable their sitemap feature to avoid duplicate sitemaps.';
            echo '</p></div>';
        }
    }

    /**
     * Render settings section
     *
     * @since 1.0.0
     * @return void
     */
    public function render_settings() {
        $sitemap_settings = $this->get_sitemap_settings();
        $all_post_types = get_post_types(['public' => true], 'objects');

        include __DIR__ . '/views/settings.php';
    }
}
