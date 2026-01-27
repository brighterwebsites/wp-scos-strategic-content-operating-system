<?php
/**
 * Content Type Helper
 *
 * Determines content type for UTM parameters and classification
 *
 * @package BrighterCore
 * @subpackage SocialAmplification
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class BW_Content_Type_Helper {

    /**
     * Get content type for a post
     *
     * Standardized logic shared across Social Amplification and Airtable:
     * - Check bw_intent first (if not empty)
     * - Pages: Check bw_purpose meta field (if intent empty)
     * - Fallback: Map post type to content type
     * - Special handling for homepage
     * - Archive only for content-collection purpose
     *
     * @param int $post_id Post ID
     * @param string $post_type Post type slug
     * @return string Content type (standardized across all systems)
     */
    public static function get_content_type($post_id, $post_type = null) {
        if (!$post_id) {
            return 'page';
        }

        if (!$post_type) {
            $post = get_post($post_id);
            $post_type = $post ? $post->post_type : 'post';
        }

        // Check if homepage
        if (is_front_page() || $post_id === get_option('page_on_front')) {
            return 'home';
        }

        // Check bw_intent first (for all post types)
        $intent = get_post_meta($post_id, 'bw_intent', true);
        if (!empty($intent)) {
            // Map intent to content type if needed, otherwise use intent directly
            // For now, return intent as-is (can add mapping later if needed)
            return sanitize_key($intent);
        }

        // Pages: Check for bw_purpose meta (only if intent is empty)
        if ($post_type === 'page') {
            $purpose = get_post_meta($post_id, 'bw_purpose', true);

            if (!empty($purpose)) {
                // Standardized purpose to content type mapping
                $purpose_to_content_type = array(
                    // Core content (educational / authority building)
                    'pillar'              => 'article',
                    'supporting'          => 'article',

                    // Commercial
                    'service-page'        => 'service',
                    'product-page'        => 'product',

                    // Conversion mechanics
                    'conversion-hub'      => 'conversion',
                    'conversion-event'    => 'conversion',
                    'conversion-endpoint' => 'functional',  // Thank you pages

                    // Proof
                    'case-study'          => 'case-study',

                    // Brand & trust
                    'authority-page'      => 'brand',

                    // Aggregation (ONLY archive mapping)
                    'content-collection'  => 'archive',

                    // Deep education
                    'resource-guide'      => 'guide',

                    // Legal & system
                    'terms'               => 'policy',
                    'functional'          => 'functional',
                );

                if (isset($purpose_to_content_type[$purpose])) {
                    return $purpose_to_content_type[$purpose];
                }

                // Use purpose value directly if not in map (sanitized)
                return sanitize_key($purpose);
            }

            // Default for pages without purpose or intent
            return 'page';
        }

        // Post type mapping (only used if bw_intent and bw_purpose are empty)
        $post_type_map = array(
            'post'     => 'article',
            'projects' => 'case-study',
            'folio'    => 'case-study',
            'kb'       => 'guide',
            'news'     => 'news',
            'faq'      => 'guide',
        );

        return isset($post_type_map[$post_type]) ? $post_type_map[$post_type] : $post_type;
    }

    /**
     * Get all available content types
     *
     * @return array Content types with labels
     */
    public static function get_all_content_types() {
        return array(
            'blog'     => __('Blog Post', 'brighterwebsites'),
            'project'  => __('Project/Portfolio', 'brighterwebsites'),
            'service'  => __('Service Page', 'brighterwebsites'),
            'pillar'   => __('Pillar Content', 'brighterwebsites'),
            'home'     => __('Homepage', 'brighterwebsites'),
            'about'    => __('About/Team', 'brighterwebsites'),
            'contact'  => __('Contact Page', 'brighterwebsites'),
            'kb'       => __('Knowledge Base', 'brighterwebsites'),
            'news'     => __('News', 'brighterwebsites'),
            'faq'      => __('FAQ', 'brighterwebsites'),
            'page'     => __('General Page', 'brighterwebsites'),
        );
    }

    /**
     * Build UTM content parameter
     *
     * Format: {content_type}_{format}
     * Example: blog_link, service_img, project_reel
     *
     * @param string $content_type Content type
     * @param string $format Format (link, img, reel, video)
     * @return string UTM content parameter
     */
    public static function build_utm_content($content_type, $format = 'link') {
        return $content_type . '_' . $format;
    }

    /**
     * Build complete UTM query string
     *
     * @param string $platform Platform (facebook, linkedin, twitter, etc.)
     * @param string $content_type Content type
     * @param string $format Format (link, img, reel)
     * @param string $campaign Campaign name (default: 'none')
     * @return string Complete UTM query string
     */
    public static function build_utm_string($platform, $content_type, $format = 'link', $campaign = 'none') {
        return http_build_query(array(
            'utm_source'   => 'social_media',
            'utm_medium'   => 'social',
            'utm_content'  => self::build_utm_content($content_type, $format),
            'utm_campaign' => $campaign
        ));
    }
}
