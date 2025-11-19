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

        // Register HTML sitemap shortcode
        add_shortcode('site_essentials_sitemap', [$this, 'render_html_sitemap_shortcode']);

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
            // Check if it's a taxonomy or post type
            if (taxonomy_exists($sitemap)) {
                $this->render_taxonomy_sitemap($sitemap, $settings);
            } else {
                $this->render_post_type_sitemap($sitemap, $settings);
            }
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
            'taxonomies'      => ['category'], // Categories ON by default, tags and custom tax OFF
            'include_images'  => true,
            'exclude_ids'     => [],
            'entries_per_sitemap' => 2000,
            'html_sitemap_enabled' => false,
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
            // Track cache generation time
            set_transient('se_sitemap_cache_time', current_time('timestamp'), DAY_IN_SECONDS);
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

        // Add post type sitemaps
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

        // Add taxonomy sitemaps
        $taxonomies = !empty($settings['taxonomies']) ? $settings['taxonomies'] : [];
        foreach ($taxonomies as $taxonomy) {
            $taxonomy_obj = get_taxonomy($taxonomy);
            if (!$taxonomy_obj) {
                continue;
            }

            // Get last modified date
            $last_modified = $this->get_taxonomy_last_modified($taxonomy);

            $xml .= "\t<sitemap>\n";
            $xml .= "\t\t<loc>" . esc_url(home_url("/sitemap-{$taxonomy}.xml")) . "</loc>\n";
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
        // Temporarily remove any query filters that might limit posts_per_page
        add_filter('post_limits', [$this, 'remove_query_limit'], 999);

        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1, // Get ALL posts for sitemap
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'post__not_in'   => $settings['exclude_ids'],
            'nopaging'       => true, // Ensure no paging
        ];

        $posts = get_posts($args);

        // Remove the filter after query
        remove_filter('post_limits', [$this, 'remove_query_limit'], 999);

        // Filter out noindex posts
        return array_filter($posts, function($post) {
            return !$this->is_noindex($post->ID);
        });
    }

    /**
     * Remove SQL LIMIT clause for sitemap queries
     *
     * Ensures all posts are retrieved for sitemaps.
     *
     * @since  1.0.0
     * @param  string $limits SQL LIMIT clause
     * @return string Empty string to remove limit
     */
    public function remove_query_limit($limits) {
        return ''; // Remove LIMIT clause entirely
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
     * Render taxonomy sitemap
     *
     * @since 1.0.0
     * @param string $taxonomy Taxonomy name
     * @param array  $settings Sitemap settings
     * @return void
     */
    private function render_taxonomy_sitemap($taxonomy, $settings) {
        // Validate taxonomy
        if (!in_array($taxonomy, $settings['taxonomies'], true)) {
            status_header(404);
            return;
        }

        $cache_key = 'sitemap_tax_' . $taxonomy;
        $xml = Cache_Helper::remember($cache_key, function() use ($taxonomy, $settings) {
            return $this->generate_taxonomy_sitemap($taxonomy, $settings);
        }, 3600, 'seo');

        header('Content-Type: application/xml; charset=utf-8');
        echo $xml;
    }

    /**
     * Generate taxonomy sitemap XML
     *
     * @since  1.0.0
     * @param  string $taxonomy Taxonomy name
     * @param  array  $settings Sitemap settings
     * @return string XML content
     */
    private function generate_taxonomy_sitemap($taxonomy, $settings) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Get terms
        $terms = $this->get_taxonomy_terms($taxonomy, $settings);

        foreach ($terms as $term) {
            $xml .= $this->generate_taxonomy_url_entry($term);
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Get terms for taxonomy sitemap
     *
     * Excludes empty terms (0 posts) to prevent 404 errors.
     *
     * @since  1.0.0
     * @param  string $taxonomy Taxonomy name
     * @param  array  $settings Sitemap settings
     * @return array  Terms
     */
    private function get_taxonomy_terms($taxonomy, $settings) {
        $args = [
            'taxonomy'   => $taxonomy,
            'hide_empty' => true, // Exclude empty terms
            'number'     => $settings['entries_per_sitemap'],
            'orderby'    => 'name',
            'order'      => 'ASC',
        ];

        return get_terms($args);
    }

    /**
     * Generate URL entry for taxonomy term
     *
     * @since  1.0.0
     * @param  WP_Term $term Term object
     * @return string  XML URL entry
     */
    private function generate_taxonomy_url_entry($term) {
        $xml = "\t<url>\n";
        $xml .= "\t\t<loc>" . esc_url(get_term_link($term)) . "</loc>\n";
        $xml .= "\t\t<changefreq>weekly</changefreq>\n";
        $xml .= "\t\t<priority>0.5</priority>\n";
        $xml .= "\t</url>\n";

        return $xml;
    }

    /**
     * Get last modified date for taxonomy
     *
     * Gets the most recent post modification date for posts in this taxonomy.
     *
     * @since  1.0.0
     * @param  string $taxonomy Taxonomy name
     * @return string|false Last modified date or false
     */
    private function get_taxonomy_last_modified($taxonomy) {
        global $wpdb;

        $last_modified = $wpdb->get_var($wpdb->prepare(
            "SELECT p.post_modified_gmt
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tt.taxonomy = %s
            AND p.post_status = 'publish'
            ORDER BY p.post_modified_gmt DESC
            LIMIT 1",
            $taxonomy
        ));

        return $last_modified;
    }

    /**
     * Check if post should be noindexed
     *
     * Checks multiple sources for noindex directives.
     *
     * @since  1.0.0
     * @param  int $post_id Post ID
     * @return bool True if noindex, false otherwise
     */
    private function is_noindex($post_id) {
        // Check Yoast SEO meta
        $yoast_noindex = get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true);
        if ($yoast_noindex === '1' || $yoast_noindex === 'noindex') {
            return true;
        }

        // Check Rank Math meta
        $rankmath_robots = get_post_meta($post_id, 'rank_math_robots', true);
        if (is_array($rankmath_robots) && in_array('noindex', $rankmath_robots, true)) {
            return true;
        }

        // Check SEOPress meta
        $seopress_noindex = get_post_meta($post_id, '_seopress_robots_index', true);
        if ($seopress_noindex === 'yes') {
            return true;
        }

        // Check generic custom field (for custom implementations)
        $custom_noindex = get_post_meta($post_id, '_robots_noindex', true);
        if ($custom_noindex === '1' || $custom_noindex === 'yes') {
            return true;
        }

        // Check via wp_robots filter (WordPress core)
        // This is a bit tricky since wp_robots is context-dependent
        // We'll apply a simplified check using the filter
        $robots = apply_filters('wp_robots', []);
        if (isset($robots['noindex']) && $robots['noindex'] === true) {
            // Note: This checks global setting, not per-post
            // For per-post, SEO plugins usually handle via their own meta
        }

        return false;
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
        $all_taxonomies = $this->get_available_taxonomies();
        $sitemap_stats = $this->calculate_sitemap_stats($sitemap_settings);

        include __DIR__ . '/views/settings.php';
    }

    /**
     * Calculate accurate sitemap stats
     *
     * Counts posts and taxonomies exactly as they appear in sitemaps.
     * Excludes noindex posts and excluded IDs.
     *
     * @since  1.0.0
     * @param  array $settings Sitemap settings
     * @return array Stats data
     */
    private function calculate_sitemap_stats($settings) {
        $stats = [
            'total_urls' => 0,
            'post_types' => [],
            'taxonomies' => [],
        ];

        // Temporarily remove any query filters that might limit posts_per_page
        add_filter('post_limits', [$this, 'remove_query_limit'], 999);

        // Count post types (matching sitemap generation logic)
        foreach ($settings['post_types'] as $post_type) {
            $posts = get_posts([
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'post__not_in'   => $settings['exclude_ids'],
                'nopaging'       => true,
            ]);

            // Filter out noindex posts
            $count = 0;
            foreach ($posts as $post_id) {
                if (!$this->is_noindex($post_id)) {
                    $count++;
                }
            }

            if ($count > 0) {
                $post_type_obj = get_post_type_object($post_type);
                $stats['post_types'][$post_type] = [
                    'label' => $post_type_obj ? $post_type_obj->labels->name : $post_type,
                    'count' => $count,
                ];
                $stats['total_urls'] += $count;
            }
        }

        // Remove the filter after queries
        remove_filter('post_limits', [$this, 'remove_query_limit'], 999);

        // Count taxonomies
        $taxonomies = !empty($settings['taxonomies']) ? $settings['taxonomies'] : [];
        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => true,
                'fields'     => 'count',
            ]);
            $term_count = is_numeric($terms) ? $terms : 0;

            if ($term_count > 0) {
                $taxonomy_obj = get_taxonomy($taxonomy);
                $stats['taxonomies'][$taxonomy] = [
                    'label' => $taxonomy_obj ? $taxonomy_obj->labels->name : $taxonomy,
                    'count' => $term_count,
                ];
                $stats['total_urls'] += $term_count;
            }
        }

        return $stats;
    }

    /**
     * Get available taxonomies for sitemap
     *
     * Only returns taxonomies with public => true AND show_ui => true
     *
     * @since  1.0.0
     * @return array Taxonomies
     */
    private function get_available_taxonomies() {
        $taxonomies = get_taxonomies([
            'public'  => true,
            'show_ui' => true,
        ], 'objects');

        return $taxonomies;
    }

    /**
     * Render HTML sitemap shortcode
     *
     * @since  1.0.0
     * @param  array $atts Shortcode attributes
     * @return string HTML sitemap output
     */
    public function render_html_sitemap_shortcode($atts = []) {
        $settings = $this->get_sitemap_settings();

        // Check if HTML sitemap is enabled
        if (empty($settings['html_sitemap_enabled'])) {
            return '<p><em>' . esc_html__('HTML sitemap is not enabled.', 'site-essentials') . '</em></p>';
        }

        // Cache the HTML sitemap
        $cache_key = 'html_sitemap';
        $html = Cache_Helper::remember($cache_key, function() use ($settings) {
            return $this->generate_html_sitemap($settings);
        }, 3600, 'seo');

        return $html;
    }

    /**
     * Generate HTML sitemap
     *
     * Groups posts by post type with headings, sorted by menu order then A-Z.
     * Shows both published and last modified dates.
     * Post types ordered: Pages first, Posts second, CPTs alphabetically.
     *
     * @since  1.0.0
     * @param  array $settings Sitemap settings
     * @return string HTML output
     */
    private function generate_html_sitemap($settings) {
        $html = '<div class="site-essentials-html-sitemap">';

        // Sort post types: Pages first, Posts second, CPTs alphabetically
        $post_types = $settings['post_types'];
        $ordered_types = [];

        // Add page first
        if (in_array('page', $post_types, true)) {
            $ordered_types[] = 'page';
        }

        // Add post second
        if (in_array('post', $post_types, true)) {
            $ordered_types[] = 'post';
        }

        // Add remaining CPTs alphabetically
        $remaining = array_diff($post_types, ['page', 'post']);
        sort($remaining);
        $ordered_types = array_merge($ordered_types, $remaining);

        foreach ($ordered_types as $post_type) {
            $post_type_obj = get_post_type_object($post_type);
            if (!$post_type_obj) {
                continue;
            }

            // Get posts for this post type
            $all_posts = get_posts([
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => ['menu_order' => 'ASC', 'title' => 'ASC'],
                'post__not_in'   => $settings['exclude_ids'],
            ]);

            // Filter out noindex posts
            $posts = array_filter($all_posts, function($post) {
                return !$this->is_noindex($post->ID);
            });

            if (empty($posts)) {
                continue;
            }

            // Post type heading
            $html .= '<div class="sitemap-section">';
            $html .= '<h2>' . esc_html($post_type_obj->labels->name) . '</h2>';
            $html .= '<ul class="sitemap-list">';

            foreach ($posts as $post) {
                $published = get_the_date('F j, Y', $post);
                $modified = get_the_modified_date('F j, Y', $post);

                $html .= '<li>';
                $html .= '<a href="' . esc_url(get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a>';
                $html .= ' <span class="sitemap-dates">';
                $html .= '<span class="published">Published: ' . esc_html($published) . '</span>';
                if ($published !== $modified) {
                    $html .= ' | <span class="modified">Updated: ' . esc_html($modified) . '</span>';
                }
                $html .= '</span>';
                $html .= '</li>';
            }

            $html .= '</ul>';
            $html .= '</div>';
        }

        $html .= '</div>';

        // Add basic styling
        $html .= '<style>
            .site-essentials-html-sitemap {
                margin: 20px 0;
            }
            .site-essentials-html-sitemap .sitemap-section {
                margin-bottom: 30px;
            }
            .site-essentials-html-sitemap h2 {
                border-bottom: 2px solid #ddd;
                padding-bottom: 10px;
                margin-bottom: 15px;
            }
            .site-essentials-html-sitemap .sitemap-list {
                list-style: none;
                padding-left: 0;
            }
            .site-essentials-html-sitemap .sitemap-list li {
                margin-bottom: 10px;
                padding: 8px;
                background: #f9f9f9;
                border-left: 3px solid #0073aa;
            }
            .site-essentials-html-sitemap .sitemap-dates {
                display: block;
                font-size: 0.9em;
                color: #666;
                margin-top: 4px;
            }
        </style>';

        return $html;
    }
}
