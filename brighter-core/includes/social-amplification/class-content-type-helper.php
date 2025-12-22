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
     * Logic:
     * - Pages: Check bw_purpose meta field (home, about, service, etc.)
     * - Other CPTs: Map post type to friendly name
     *
     * @param int $post_id Post ID
     * @param string $post_type Post type slug
     * @return string Content type for UTM parameters
     */
    public static function get_content_type($post_id, $post_type = null) {
        if (!$post_type) {
            $post = get_post($post_id);
            $post_type = $post ? $post->post_type : 'post';
        }

        // Pages: Check for bw_purpose meta
        if ($post_type === 'page') {
            $purpose = get_post_meta($post_id, 'bw_purpose', true);

            if (!empty($purpose)) {
                // Map purpose to content type
                $purpose_map = array(
                    'pillar'        => 'pillar',
                    'service-page'  => 'service',
                    'about'         => 'about',
                    'team'          => 'about',
                    'contact'       => 'contact',
                    'home'          => 'home',
                );

                if (isset($purpose_map[$purpose])) {
                    return $purpose_map[$purpose];
                }

                // Use purpose value directly if not in map
                return sanitize_key($purpose);
            }

            // Default for pages without purpose
            return 'page';
        }

        // Other CPTs: Map post type to friendly name
        $post_type_map = array(
            'post'     => 'blog',
            'folio'    => 'project',
            'projects' => 'project',
            'kb'       => 'kb',
            'news'     => 'news',
            'faq'      => 'faq',
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
            'utm_source'   => $platform,
            'utm_medium'   => 'social',
            'utm_content'  => self::build_utm_content($content_type, $format),
            'utm_campaign' => $campaign
        ));
    }
}
