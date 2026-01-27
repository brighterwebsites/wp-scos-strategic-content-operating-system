<?php
/**
 * Brighter API Endpoints Handler
 *
 * Registers and handles all REST API endpoints for Custom GPT access
 *
 * @package BrighterCore
 * @subpackage API
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class Brighter_API_Endpoints {

    /**
     * @var Brighter_API_Auth Authentication handler
     */
    private $auth;

    /**
     * API namespace
     */
    const NAMESPACE = 'brighter-core/v1';

    /**
     * Cache duration in seconds (5 minutes)
     */
    const CACHE_DURATION = 300;

    /**
     * Constructor
     *
     * @param Brighter_API_Auth $auth Authentication handler
     */
    public function __construct($auth) {
        $this->auth = $auth;
    }

    /**
     * Register all REST API routes
     */
    public function register_routes() {
        // Standard endpoints - always available on all sites
        // Note: 'pages' is handled separately as a special endpoint for configured pages
        $standard_endpoints = array(
            'posts' => 'post',      // Standard WordPress posts
            'faqs' => 'faq'         // FAQ custom post type (registered by MU plugin)
        );

        // Optional endpoints - only register if post type exists
        // These are site-specific custom post types
        $optional_endpoints = array(
            // BW-specific custom post types
            'our-work' => 'folio',     // Portfolio (BW only)
            'kb' => 'kb',              // Knowledge Base (BW only)
            'news' => 'news',          // News (BW only)
            
            // GS-specific custom post types
            'project' => 'projects'   // Projects (GS only - note: post type is 'projects', not 'project')
        );

        // Register standard endpoints (always available)
        foreach ($standard_endpoints as $route => $post_type) {
            // Only register if post type exists (faq might not be registered on some sites)
            if (post_type_exists($post_type)) {
                $this->register_content_route($route, $post_type);
            }
        }

        // Register optional endpoints (only if post type exists)
        foreach ($optional_endpoints as $route => $post_type) {
            if (post_type_exists($post_type)) {
                // Avoid duplicate registration - check if route already registered
                $already_registered = false;
                foreach ($standard_endpoints as $std_route => $std_type) {
                    if ($std_route === $route && $std_type === $post_type) {
                        $already_registered = true;
                        break;
                    }
                }
                
                if (!$already_registered) {
                    $this->register_content_route($route, $post_type);
                }
            }
        }

        // Pages endpoint (special handling - for configured pages, always available)
        register_rest_route(self::NAMESPACE, '/pages', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_pages'),
            'permission_callback' => array($this->auth, 'verify_token')
        ));
    }

    /**
     * Register a content route
     *
     * @param string $route Route name
     * @param string $post_type Post type
     */
    private function register_content_route($route, $post_type) {
        register_rest_route(self::NAMESPACE, '/' . $route, array(
            'methods' => 'GET',
            'callback' => function($request) use ($post_type) {
                return $this->get_content($request, $post_type);
            },
            'permission_callback' => array($this->auth, 'verify_token'),
            'args' => $this->get_content_endpoint_args()
        ));
    }

    /**
     * Get list of registered endpoints
     * Used by admin interface to show available endpoints
     *
     * @return array Array of endpoint info: ['route' => ['name' => '...', 'post_type' => '...', 'registered' => bool]]
     */
    public function get_registered_endpoints() {
        $endpoints = array();

        // Standard endpoints
        $standard = array(
            'posts' => array('name' => 'Blog Posts', 'post_type' => 'post'),
            'faqs' => array('name' => 'FAQs', 'post_type' => 'faq')
        );

        // Optional endpoints - site-specific custom post types
        $optional = array(
            // BW-specific
            'our-work' => array('name' => 'Portfolio', 'post_type' => 'folio'),
            'kb' => array('name' => 'Knowledge Base', 'post_type' => 'kb'),
            'news' => array('name' => 'News Articles', 'post_type' => 'news'),
            
            // GS-specific
            'project' => array('name' => 'Projects', 'post_type' => 'projects')
        );

        // Check standard endpoints
        foreach ($standard as $route => $data) {
            $exists = post_type_exists($data['post_type']);
            $endpoints[$route] = array_merge($data, array('registered' => $exists));
        }

        // Check optional endpoints
        foreach ($optional as $route => $data) {
            $exists = post_type_exists($data['post_type']);
            // Only include if not already in standard endpoints
            if (!isset($endpoints[$route])) {
                $endpoints[$route] = array_merge($data, array('registered' => $exists));
            }
        }

        // Always include /pages endpoint (special handling)
        $endpoints['pages'] = array(
            'name' => 'Configured Pages',
            'post_type' => 'page',
            'registered' => true,
            'special' => true // Indicates special handling (configured pages)
        );

        return $endpoints;
    }

    /**
     * Get arguments for content endpoints
     *
     * @return array Endpoint arguments
     */
    private function get_content_endpoint_args() {
        return array(
            'page' => array(
                'description' => 'Page number',
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
                'sanitize_callback' => 'absint'
            ),
            'per_page' => array(
                'description' => 'Items per page',
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 10,
                'sanitize_callback' => 'absint'
            ),
            'status' => array(
                'description' => 'Post status filter',
                'type' => 'string',
                'default' => 'publish',
                'enum' => array('publish', 'draft', 'any'),
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
    }

    /**
     * Get content for a post type
     *
     * @param WP_REST_Request $request Request object
     * @param string $post_type Post type to query
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_content($request, $post_type) {
        try {
            // Get parameters
            $page = $request->get_param('page');
            $per_page = $request->get_param('per_page');
            $status = $request->get_param('status');

            // Cap per_page to prevent memory issues with large content
            // Some sites have very large posts, so reduce default if not specified
            if ($per_page > 10) {
                // For sites with potentially large content, cap at 10
                // This prevents 500 errors on sites like Guerilla Steel
                $per_page = min($per_page, 10);
            }

            // Generate cache key
            $cache_key = "brighter_api_{$post_type}_{$page}_{$per_page}_{$status}";

            // Try to get cached response
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return rest_ensure_response($cached);
            }

            // Increase memory limit temporarily for large content processing
            $original_memory = ini_get('memory_limit');
            @ini_set('memory_limit', '256M');

            // Query posts
            $query_args = array(
                'post_type' => $post_type,
                'post_status' => $status,
                'posts_per_page' => $per_page,
                'paged' => $page,
                'orderby' => 'date',
                'order' => 'DESC',
                'no_found_rows' => false // We need total count for pagination
            );

            $query = new WP_Query($query_args);

            // Format response with error handling for each item
            $items = array();
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    try {
                        $item = $this->format_content_item(get_post());
                        $items[] = $item;
                    } catch (Exception $e) {
                        // Log error but continue processing other items
                        error_log('Brighter API: Error formatting post ' . get_the_ID() . ': ' . $e->getMessage());
                        // Skip this item and continue
                        continue;
                    }
                }
                wp_reset_postdata();
            }

            // Restore original memory limit
            @ini_set('memory_limit', $original_memory);

            // Build response with pagination
            $response = array(
                'items' => $items,
                'pagination' => array(
                    'total' => $query->found_posts,
                    'total_pages' => $query->max_num_pages,
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'has_more' => $page < $query->max_num_pages
                )
            );

            // Cache for 5 minutes
            set_transient($cache_key, $response, self::CACHE_DURATION);

            return rest_ensure_response($response);

        } catch (Exception $e) {
            // Log the error
            error_log('Brighter API: Error in get_content: ' . $e->getMessage());
            
            // Return a proper error response instead of causing a 500
            return new WP_Error(
                'api_error',
                'An error occurred while retrieving content. Please try again with a smaller per_page value.',
                array('status' => 500)
            );
        }
    }

    /**
     * Get configured pages
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_pages($request) {
        // Get configured page IDs from settings
        $page_ids = get_option('brighter_api_pages', array());

        if (empty($page_ids)) {
            return rest_ensure_response(array('pages' => array()));
        }

        // Generate cache key
        $cache_key = 'brighter_api_pages_' . md5(serialize($page_ids));

        // Try to get cached response
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        // Get pages
        $pages = array();
        foreach ($page_ids as $page_id) {
            $post = get_post($page_id);
            if ($post && $post->post_status === 'publish') {
                $pages[] = $this->format_page_item($post);
            }
        }

        $response = array('pages' => $pages);

        // Cache for 5 minutes
        set_transient($cache_key, $response, self::CACHE_DURATION);

        return rest_ensure_response($response);
    }

    /**
     * Format a content item for API response
     *
     * @param WP_Post $post Post object
     * @return array Formatted item
     */
    private function format_content_item($post) {
        $post_id = $post->ID;

        try {
            // Get featured image
            $featured_image = $this->get_featured_image($post_id);

            // Get excerpt (auto-generate if empty)
            $excerpt = $post->post_excerpt;
            if (empty($excerpt)) {
                $excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 200, '...');
            }

            // Get categories and tags
            $categories = $this->get_term_names($post_id, 'category');
            $tags = $this->get_term_names($post_id, 'post_tag');

            // Safely get content with error handling
            $content = '';
            try {
                $content = apply_filters('the_content', $post->post_content);
            } catch (Exception $e) {
                // Fallback to raw content if filters fail
                $content = $post->post_content;
                error_log('Brighter API: Error applying content filters for post ' . $post_id . ': ' . $e->getMessage());
            }

            // Build base item
            $item = array(
                'id' => $post_id,
                'title' => get_the_title($post_id) ?: '',
                'excerpt' => $excerpt ?: '',
                'content' => $content,
                'slug' => $post->post_name ?: '',
                'url' => get_permalink($post_id) ?: '',
                'date' => get_the_date('c', $post_id) ?: '',
                'modified' => get_the_modified_date('c', $post_id) ?: '',
                'featured_image' => $featured_image,
                'categories' => $categories,
                'tags' => $tags,
                'meta_description' => $this->get_meta_description($post_id),
                'altc_content_data' => $this->get_altc_data($post_id),
                'custom_fields' => $this->get_custom_fields($post_id)
            );

            return $item;
        } catch (Exception $e) {
            // Return minimal item if formatting fails completely
            error_log('Brighter API: Critical error formatting post ' . $post_id . ': ' . $e->getMessage());
            return array(
                'id' => $post_id,
                'title' => get_the_title($post_id) ?: 'Error loading post',
                'excerpt' => '',
                'content' => '',
                'slug' => $post->post_name ?: '',
                'url' => get_permalink($post_id) ?: '',
                'date' => get_the_date('c', $post_id) ?: '',
                'modified' => get_the_modified_date('c', $post_id) ?: '',
                'featured_image' => null,
                'categories' => array(),
                'tags' => array(),
                'meta_description' => '',
                'altc_content_data' => array(),
                'custom_fields' => array()
            );
        }
    }

    /**
     * Format a page item for API response
     *
     * @param WP_Post $post Post object
     * @return array Formatted item
     */
    private function format_page_item($post) {
        $post_id = $post->ID;

        // Get excerpt (auto-generate if empty)
        $excerpt = $post->post_excerpt;
        if (empty($excerpt)) {
            $excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 200, '...');
        }

        return array(
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'content' => apply_filters('the_content', $post->post_content),
            'url' => get_permalink($post_id),
            'excerpt' => $excerpt,
            'meta_description' => $this->get_meta_description($post_id)
        );
    }

    /**
     * Get featured image data
     *
     * @param int $post_id Post ID
     * @return array|null Featured image data or null
     */
    private function get_featured_image($post_id) {
        $image_id = get_post_thumbnail_id($post_id);

        if (!$image_id) {
            return null;
        }

        $image_data = wp_get_attachment_image_src($image_id, 'full');
        $alt_text = get_post_meta($image_id, '_wp_attachment_image_alt', true);

        if (!$image_data) {
            return null;
        }

        return array(
            'url' => $image_data[0],
            'width' => $image_data[1],
            'height' => $image_data[2],
            'alt' => $alt_text
        );
    }

    /**
     * Get term names for a taxonomy
     *
     * @param int $post_id Post ID
     * @param string $taxonomy Taxonomy name
     * @return array Term names
     */
    private function get_term_names($post_id, $taxonomy) {
        $terms = get_the_terms($post_id, $taxonomy);

        if (!$terms || is_wp_error($terms)) {
            return array();
        }

        return array_map(function($term) {
            return $term->name;
        }, $terms);
    }

    /**
     * Get meta description (SEOPress or Yoast)
     *
     * @param int $post_id Post ID
     * @return string Meta description
     */
    private function get_meta_description($post_id) {
        // Try SEOPress first
        $meta_desc = get_post_meta($post_id, '_seopress_titles_desc', true);

        // Fall back to Yoast
        if (empty($meta_desc)) {
            $meta_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        }

        return $meta_desc ?: '';
    }

    /**
     * Get ALTC content data
     *
     * @param int $post_id Post ID
     * @return array ALTC data with resolved taxonomy names
     */
    private function get_altc_data($post_id) {
        $data = array();

        // Get ALTC Strategic Lens (resolve to name)
        $altc_id = get_post_meta($post_id, 'bw_primary_altc_id', true);
        if ($altc_id) {
            $altc_term = get_term($altc_id, 'altc_strategic_lens');
            if ($altc_term && !is_wp_error($altc_term)) {
                $data['primary_altc'] = array(
                    'id' => $altc_term->term_id,
                    'name' => $altc_term->name,
                    'slug' => $altc_term->slug
                );
            }
        }

        // Get ALTC Topic (resolve to name)
        $topic_id = get_post_meta($post_id, 'bw_primary_topic_id', true);
        if ($topic_id) {
            $topic_term = get_term($topic_id, 'altc_topic');
            if ($topic_term && !is_wp_error($topic_term)) {
                $data['primary_topic'] = array(
                    'id' => $topic_term->term_id,
                    'name' => $topic_term->name,
                    'slug' => $topic_term->slug
                );
            }
        }

        // Content maturity
        $maturity = get_post_meta($post_id, 'bw_cont_maturity', true);
        if ($maturity) {
            $data['content_maturity'] = $maturity;
        }

        // Content strategy fields
        $intent = get_post_meta($post_id, 'bw_intent', true);
        if ($intent) {
            $data['intent'] = $intent;
        }

        $purpose = get_post_meta($post_id, 'bw_purpose', true);
        if ($purpose) {
            $data['purpose'] = $purpose;
        }

        // Pillar page (resolve to title)
        $pillar_id = get_post_meta($post_id, 'bw_pillar_page_id', true);
        if ($pillar_id) {
            $pillar_post = get_post($pillar_id);
            if ($pillar_post) {
                $data['pillar_page'] = array(
                    'id' => $pillar_id,
                    'title' => get_the_title($pillar_id),
                    'url' => get_permalink($pillar_id)
                );
            }
        }

        $notes = get_post_meta($post_id, 'bw_notes', true);
        if ($notes) {
            $data['notes'] = $notes;
        }

        $opt_status = get_post_meta($post_id, '_brt_opt_status', true);
        if ($opt_status) {
            $data['optimization_status'] = $opt_status;
        }

        $index_status = get_post_meta($post_id, 'bw_index_status', true);
        if ($index_status) {
            $data['index_status'] = $index_status;
        }

        return $data;
    }

    /**
     * Get custom ACF fields
     *
     * @param int $post_id Post ID
     * @return array Custom fields
     */
    private function get_custom_fields($post_id) {
        $fields = array();

        // SEOPress fields
        $seo_title = get_post_meta($post_id, '_seopress_titles_title', true);
        if ($seo_title) {
            $fields['seo_title'] = $seo_title;
        }

        $target_keyword = get_post_meta($post_id, '_seopress_analysis_target_kw', true);
        if ($target_keyword) {
            $fields['target_keyword'] = $target_keyword;
        }

        // Short link (ACF)
        $short_link = get_field('bw_short_link', $post_id);
        if ($short_link) {
            $fields['short_link'] = $short_link;
        }

        // Internal notes (ACF)
        $internal_notes = get_field('article_notes_internal', $post_id);
        if ($internal_notes) {
            $fields['internal_notes'] = $internal_notes;
        }

        return $fields;
    }

    /**
     * Clear all API caches
     *
     * Called when content is updated or settings changed
     */
    public static function clear_cache() {
        global $wpdb;

        // Delete all transients starting with brighter_api_
        $wpdb->query(
            "DELETE FROM $wpdb->options
             WHERE option_name LIKE '_transient_brighter_api_%'
             OR option_name LIKE '_transient_timeout_brighter_api_%'"
        );
    }
}
