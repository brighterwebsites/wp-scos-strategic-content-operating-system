<?php
/**
 * Airtable Helper
 *
 * Handles synchronization of Content Architecture Record (CAR) data to Airtable
 * Uses standardized content type helper and settings from options page
 *
 * @package BrighterCore
 * @subpackage SocialAmplification
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class BW_Airtable_Helper {

    /**
     * Initialize hooks
     */
    public static function init() {
        // Sync CAR data to Airtable on post save
        add_action('save_post', array(__CLASS__, 'sync_car_to_airtable'), 10, 3);
    }

    /**
     * Check if Airtable is configured
     *
     * @return bool True if all required settings are present
     */
    public static function is_configured() {
        $token = get_option('bw_airtable_api_token', '');
        $base_id = get_option('bw_airtable_base_id', '');
        $table_id = get_option('bw_airtable_table_id', '');

        return !empty($token) && !empty($base_id) && !empty($table_id);
    }

    /**
     * Get Airtable API base URL
     *
     * @return string|false Base URL or false if not configured
     */
    private static function get_base_url() {
        if (!self::is_configured()) {
            return false;
        }

        $base_id = get_option('bw_airtable_base_id', '');
        $table_id = get_option('bw_airtable_table_id', '');

        return 'https://api.airtable.com/v0/' . $base_id . '/' . $table_id;
    }

    /**
     * Get Airtable API token
     *
     * @return string|false API token or false if not configured
     */
    private static function get_api_token() {
        $token = get_option('bw_airtable_api_token', '');
        
        // Ensure token starts with "Bearer " if not already
        if (!empty($token) && strpos($token, 'Bearer ') !== 0) {
            $token = 'Bearer ' . $token;
        }

        return !empty($token) ? $token : false;
    }

    /**
     * Convert internal link URLs to post IDs (comma-separated)
     *
     * @param int $post_id Post ID
     * @return string Comma-separated post IDs
     */
    private static function get_internal_links_as_ids($post_id) {
        $internal_links = get_post_meta($post_id, 'bw_internal_links', true);
        
        if (empty($internal_links) || !is_array($internal_links)) {
            return '';
        }
        
        $post_ids = array();
        foreach ($internal_links as $url) {
            $linked_post_id = url_to_postid($url);
            if ($linked_post_id > 0) {
                $post_ids[] = $linked_post_id;
            }
        }
        
        return !empty($post_ids) ? implode(', ', $post_ids) : '';
    }

    /**
     * Convert external link URLs to comma-separated list
     *
     * @param int $post_id Post ID
     * @return string Comma-separated URLs
     */
    private static function get_external_links_as_urls($post_id) {
        $external_links = get_post_meta($post_id, 'bw_external_links', true);
        
        if (empty($external_links) || !is_array($external_links)) {
            return '';
        }
        
        return implode(', ', $external_links);
    }

    /**
     * Convert workflow stages to comma-separated list
     *
     * @param int $post_id Post ID
     * @return string Comma-separated workflow stages
     */
    private static function get_workflow_stage_as_list($post_id) {
        $workflow_stages = get_post_meta($post_id, 'workflow_process', true);
        
        if (empty($workflow_stages) || !is_array($workflow_stages)) {
            return '';
        }
        
        return implode(', ', $workflow_stages);
    }

    /**
     * Build CAR data structure for Airtable
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @return array Airtable data structure
     */
    private static function build_car_data($post_id, $post) {
        // Get ALTC Cluster and Topic names
        $primary_altc_id = get_post_meta($post_id, 'bw_primary_altc_id', true);
        $primary_topic_id = get_post_meta($post_id, 'bw_primary_topic_id', true);
        
        $altc_name = '';
        $topic_name = '';
        
        // Get ALTC Cluster name
        if ($primary_altc_id) {
            $altc_term = get_term($primary_altc_id, 'altc_strategic_lens');
            if ($altc_term && !is_wp_error($altc_term)) {
                $altc_name = $altc_term->name;
            }
        }
        
        // Fallback: Get from taxonomy if meta field empty
        if (empty($altc_name)) {
            $altc_terms = wp_get_post_terms($post_id, 'altc_strategic_lens', array('fields' => 'names'));
            $altc_name = !empty($altc_terms) ? $altc_terms[0] : '';
        }
        
        // Get Topic name
        if ($primary_topic_id) {
            $topic_term = get_term($primary_topic_id, 'altc_topic');
            if ($topic_term && !is_wp_error($topic_term)) {
                $topic_name = $topic_term->name;
            }
        }
        
        // Fallback: Get from taxonomy if meta field empty
        if (empty($topic_name)) {
            $topic_terms = wp_get_post_terms($post_id, 'altc_topic', array('fields' => 'names'));
            $topic_name = !empty($topic_terms) ? $topic_terms[0] : '';
        }

        // Use standardized content type helper
        $content_type = BW_Content_Type_Helper::get_content_type($post_id, $post->post_type);

        // Get pillar and service pathway titles
        $pillar_id = get_post_meta($post_id, 'bw_pillar_page_id', true);
        $pillar_title = $pillar_id ? get_the_title($pillar_id) : '';

        $service_pathway_id = get_post_meta($post_id, 'bw_service_pathway_id', true);
        $service_pathway_title = $service_pathway_id ? get_the_title($service_pathway_id) : '';

        // Build Airtable data structure
        $airtable_data = array(
            // Basic post info
            'Post ID' => $post_id,
            'Title' => $post->post_title,
            'URL' => get_permalink($post_id),
            'Published Date' => get_the_date('Y-m-d', $post_id),
            'Last Modified Date' => get_the_modified_date('Y-m-d', $post_id),
            'PostType' => $post->post_type,
            'PublishedStatus' => $post->post_status,
            'Content Type' => $content_type,
            
            // Taxonomy/Category
            'Category' => wp_get_post_categories($post_id, array('fields' => 'names'))[0] ?? '',
            
            // Content fields
            'TLDR' => get_post_meta($post_id, 'bw_tldr', true),
            'Excerpt' => $post->post_excerpt ?: wp_trim_words($post->post_content, 55, '...'),
            
            // ALTC Strategy fields
            'ALTC Cluster' => $altc_name,
            'Topics' => $topic_name,
            'Maturity Level' => get_post_meta($post_id, 'bw_cont_maturity', true),
            'Content Intent' => get_post_meta($post_id, 'bw_intent', true),
            'Content Purpose' => get_post_meta($post_id, 'bw_purpose', true),
            'Intent Goal' => get_post_meta($post_id, 'bw_altc_notes', true),
            
            // Pillar relationship
            'Pillar' => $pillar_title,
            'Service Pathway' => $service_pathway_title,
            
            // Optimization & Index Status
            'Workflow Next Step' => get_post_meta($post_id, 'content_plan', true),
            'Workflow Progress' => self::get_workflow_stage_as_list($post_id),
            
            // Content Analysis
            'Internal Links Count' => (int) get_post_meta($post_id, 'bw_internal_link_count', true),
            'External Links Count' => (int) get_post_meta($post_id, 'bw_external_link_count', true),
            'Internal Links' => self::get_internal_links_as_ids($post_id),
            'External Links' => self::get_external_links_as_urls($post_id),
            'Word Count' => (int) get_post_meta($post_id, 'bw_word_count', true),
            'H2 Count' => (int) get_post_meta($post_id, 'bw_h2_count', true),
            'Image Count' => (int) get_post_meta($post_id, 'bw_image_count', true),
            
            // Analyzed Date
            'Analysed Date' => ($analyzed = get_post_meta($post_id, '_bw_last_analyzed', true)) 
                ? substr($analyzed, 0, 10) // Extract YYYY-MM-DD
                : '',
            
            // SEO Fields (SEOPress/Yoast)
            'IndexTagSet' => get_post_meta($post_id, '_seopress_robots_index', true),
            'Canonical' => get_post_meta($post_id, '_seopress_robots_canonical', true),
            'MetaTitle' => get_post_meta($post_id, '_yoast_wpseo_title', true) 
                ?: get_post_meta($post_id, '_seopress_titles_title', true),
            'Meta Description' => get_post_meta($post_id, '_yoast_wpseo_metadesc', true)
                ?: get_post_meta($post_id, '_seopress_titles_desc', true),
            
            // SEO Fields (Brighter)
            'Short Link' => get_post_meta($post_id, '_bw_breadcrumb', true),
            'Breadcrumbs' => get_post_meta($post_id, 'bw_breadcrumb_schema', true),
            'Index Status' => get_post_meta($post_id, 'bw_index_status', true),
        );
        
        // Remove empty values to keep Airtable clean
        $airtable_data = array_filter($airtable_data, function($value) {
            return $value !== '' && $value !== null && $value !== 0;
        });
        
        return $airtable_data;
    }

    /**
     * Sync CAR data to Airtable on post save
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public static function sync_car_to_airtable($post_id, $post, $update) {
        // Skip autosave/revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        
        // Check if Airtable is configured
        if (!self::is_configured()) {
            return;
        }
        
        // Get post object if not provided
        if (!$post) {
            $post = get_post($post_id);
            if (!$post) return;
        }
        
        // Only sync published content
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Only sync specific post types
        $allowed_post_types = array('post', 'page', 'projects', 'folio', 'kb', 'news', 'faq');
        if (!in_array($post->post_type, $allowed_post_types, true)) {
            return;
        }
        
        // Rate limiting: Check last sync time (prevent rapid-fire updates)
        $last_sync = get_post_meta($post_id, '_airtable_last_sync', true);
        $now = time();
        if ($last_sync && ($now - $last_sync) < 5) {
            return; // Don't sync if updated within last 5 seconds
        }
        
        // Build CAR data
        $airtable_data = self::build_car_data($post_id, $post);
        
        // Get API configuration
        $api_token = self::get_api_token();
        $base_url = self::get_base_url();
        
        if (!$api_token || !$base_url) {
            error_log('[Airtable Sync] Configuration error: Missing API token or base URL');
            return;
        }
        
        // Check if Airtable record already exists for this post
        $airtable_record_id = get_post_meta($post_id, '_airtable_record_id', true);
        
        if ($airtable_record_id) {
            // Verify the record exists and we can access it
            $verify_url = $base_url . '/' . $airtable_record_id;
            $verify_response = wp_remote_get($verify_url, array(
                'headers' => array(
                    'Authorization' => $api_token,
                ),
                'timeout' => 15
            ));
            
            $verify_status = !is_wp_error($verify_response) ? wp_remote_retrieve_response_code($verify_response) : 0;
            
            if ($verify_status === 404 || $verify_status === 403) {
                // Record not found or no permission - clear stored ID and create new
                delete_post_meta($post_id, '_airtable_record_id');
                $airtable_record_id = false;
            }
        }
        
        if ($airtable_record_id) {
            // UPDATE existing record (PATCH)
            $patch_url = $base_url . '/' . $airtable_record_id;
            $response = wp_remote_request($patch_url, array(
                'method' => 'PATCH',
                'headers' => array(
                    'Authorization' => $api_token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array('fields' => $airtable_data), JSON_UNESCAPED_SLASHES),
                'timeout' => 15
            ));
            
            // If record not found (deleted in Airtable), create new one
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 404) {
                delete_post_meta($post_id, '_airtable_record_id');
                $airtable_record_id = false;
            }
        }
        
        if (!$airtable_record_id) {
            // CREATE new record (POST)
            $response = wp_remote_post($base_url, array(
                'headers' => array(
                    'Authorization' => $api_token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array('fields' => $airtable_data), JSON_UNESCAPED_SLASHES),
                'timeout' => 15
            ));
            
            // Store Airtable record ID for future updates
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($body['id'])) {
                    update_post_meta($post_id, '_airtable_record_id', $body['id']);
                }
            }
        }
        
        // Log errors only
        if (is_wp_error($response)) {
            error_log(sprintf('[Airtable Sync] ERROR for post %d (%s): %s', 
                $post_id, $post->post_type, $response->get_error_message()));
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code === 200 || $status_code === 201) {
                // Success - update last sync time
                update_post_meta($post_id, '_airtable_last_sync', time());
            } elseif ($status_code === 422) {
                // Validation error
                $body = wp_remote_retrieve_body($response);
                $error_data = json_decode($body, true);
                $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown validation error';
                error_log(sprintf('[Airtable Sync] VALIDATION ERROR for post %d (%s): %s', 
                    $post_id, $post->post_type, $error_message));
            } elseif ($status_code !== 200 && $status_code !== 201) {
                // Other errors
                $body = wp_remote_retrieve_body($response);
                error_log(sprintf('[Airtable Sync] API ERROR for post %d (%s): Status %d. Response: %s', 
                    $post_id, $post->post_type, $status_code, substr($body, 0, 200)));
            }
        }
    }
}
