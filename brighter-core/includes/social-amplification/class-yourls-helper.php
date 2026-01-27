<?php
/**
 * YOURLS Helper
 *
 * Handles communication with YOURLS API for shortlink creation
 *
 * @package BrighterCore
 * @subpackage SocialAmplification
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class BW_YOURLS_Helper {

    /**
     * Create a shortlink via YOURLS API
     *
     * @param string $long_url The full URL to shorten (with UTM parameters)
     * @param string $keyword The custom keyword (e.g., "seo-signals-fb")
     * @param string $title Optional title for the shortlink
     * @return array|WP_Error Array with 'shorturl' on success, WP_Error on failure
     */
    public static function create_shortlink($long_url, $keyword, $title = '') {
        // Get YOURLS settings
        $api_url = get_option('bw_yourls_api_url');
        $signature = get_option('bw_yourls_signature');
        $username = get_option('bw_yourls_username');
        $password = get_option('bw_yourls_password');

        // Validate settings
        if (empty($api_url)) {
            return new WP_Error('missing_config', 'YOURLS API URL is not configured');
        }

        // Build API request parameters
        $params = array(
            'action' => 'shorturl',
            'url' => $long_url,
            'keyword' => $keyword,
            'format' => 'json'
        );

        // Add title if provided
        if (!empty($title)) {
            $params['title'] = $title;
        }

        // Add authentication (prefer signature over username/password)
        if (!empty($signature)) {
            $params['signature'] = $signature;
        } elseif (!empty($username) && !empty($password)) {
            $params['username'] = $username;
            $params['password'] = $password;
        } else {
            return new WP_Error('missing_auth', 'YOURLS authentication credentials are not configured');
        }

        // Make API request
        $response = wp_remote_post($api_url, array(
            'body' => $params,
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));

        // Check for network errors
        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Log raw response for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('YOURLS Raw Response Body: ' . $body);
        }

        // Parse JSON response
        $data = json_decode($body, true);

        // Log the parsed response for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('YOURLS Parsed Response: ' . print_r($data, true));
            error_log('YOURLS Requested Keyword: ' . $keyword);
        }

        // Check for API errors
        if ($response_code !== 200) {
            // Special case: YOURLS returns 400 when URL already exists but still provides the shorturl
            if (is_array($data) && 
                isset($data['code']) && 
                $data['code'] === 'error:url' && 
                isset($data['shorturl'])) {
                
                error_log('YOURLS: URL already exists, returning existing shortlink: ' . $data['shorturl']);
                
                // Extract keyword from existing shortlink
                $actual_keyword = isset($data['url']['keyword']) ? $data['url']['keyword'] : $keyword;
                
                return array(
                    'success' => true,
                    'shorturl' => $data['shorturl'],
                    'keyword' => $actual_keyword,
                    'keyword_requested' => $keyword,
                    'keyword_modified' => ($actual_keyword !== $keyword),
                    'message' => 'Shortlink already exists (reused existing)'
                );
            }
            
            // For other errors, log and return error
            error_log('YOURLS API Error - Status: ' . $response_code);
            error_log('YOURLS API Error - Response: ' . $body);
            error_log('YOURLS API Error - URL: ' . $long_url);
            error_log('YOURLS API Error - Keyword: ' . $keyword);
            
            return new WP_Error('api_error', 'YOURLS API returned status ' . $response_code . '. Response: ' . substr($body, 0, 200));
        }

        if (!is_array($data)) {
            error_log('YOURLS Invalid JSON Response: ' . $body);
            return new WP_Error('invalid_response', 'YOURLS API returned invalid JSON. Raw body: ' . substr($body, 0, 200));
        }

        // YOURLS returns status in different ways
        // Success: status = "success" or statusCode = 200
        // Error: status = "fail" with message
        $status = isset($data['status']) ? $data['status'] : '';
        $status_code = isset($data['statusCode']) ? intval($data['statusCode']) : 0;

        if ($status === 'success' || $status_code === 200) {
            // Success - return the shorturl
            if (isset($data['shorturl'])) {
                // Get the actual keyword YOURLS used (might differ from requested if YOURLS modified it)
                $actual_keyword = isset($data['url']['keyword']) ? $data['url']['keyword'] : $keyword;

                return array(
                    'success' => true,
                    'shorturl' => $data['shorturl'],
                    'keyword' => $actual_keyword,
                    'keyword_requested' => $keyword,
                    'keyword_modified' => ($actual_keyword !== $keyword),
                    'message' => isset($data['message']) ? $data['message'] : 'Shortlink created'
                );
            }
        }

        // If keyword already exists, YOURLS might return it anyway
        if (isset($data['url']) && !empty($data['url']['keyword'])) {
            // Extract short URL from keyword that YOURLS actually used
            $actual_keyword = $data['url']['keyword'];
            $base_url = preg_replace('/yourls-api\.php$/i', '', $api_url);
            $shorturl = rtrim($base_url, '/') . '/' . $actual_keyword;

            return array(
                'success' => true,
                'shorturl' => $shorturl,
                'keyword' => $actual_keyword,
                'keyword_requested' => $keyword,
                'keyword_modified' => ($actual_keyword !== $keyword),
                'message' => 'Shortlink already exists'
            );
        }

        // Error case
        $error_message = isset($data['message']) ? $data['message'] : 'Unknown error';
        return new WP_Error('yourls_error', $error_message, $data);
    }

    /**
     * Build shortlink keyword from breadcrumb and platform
     *
     * @param string $breadcrumb The breadcrumb slug (e.g., "seo-signals")
     * @param string $platform The platform (facebook, linkedin, twitter, instagram, gmb)
     * @return string The keyword (e.g., "seo-signals") - platform suffix removed
     */
    public static function build_keyword($breadcrumb, $platform) {
        // Platform suffixes (commented out - might want to add back later)
        $platform_codes = array(
            'facebook' => 'fb',
            'linkedin' => 'li',
            'twitter' => 'tw',
            'instagram' => 'ig',
            'gmb' => 'gmb'
        );

        // Platform suffix logic (commented out - might want to add back later)
        // $suffix = isset($platform_codes[$platform]) ? $platform_codes[$platform] : $platform;
        // return sanitize_title($breadcrumb . '-' . $suffix);
        
        // Return breadcrumb without platform suffix
        return sanitize_title($breadcrumb);
    }

    /**
     * Build full destination URL with UTM parameters
     *
     * @param string $base_url The post URL
     * @param string $platform The platform
     * @param string $content_type Content type for utm_content
     * @param string $format Format (link, img, reel, video)
     * @return string URL with UTM parameters
     */
    public static function build_destination_url($base_url, $platform, $content_type, $format = 'link') {
        // Use the Content_Type_Helper to build UTM string
        $utm_string = BW_Content_Type_Helper::build_utm_string($platform, $content_type, $format);

        // Append UTM to URL
        $separator = (strpos($base_url, '?') !== false) ? '&' : '?';

        return $base_url . $separator . $utm_string;
    }

    /**
     * Test YOURLS connection
     *
     * @return array|WP_Error Test result
     */
    public static function test_connection() {
        $api_url = get_option('bw_yourls_api_url');
        $signature = get_option('bw_yourls_signature');
        $username = get_option('bw_yourls_username');
        $password = get_option('bw_yourls_password');

        if (empty($api_url)) {
            return new WP_Error('missing_config', 'YOURLS API URL is not configured');
        }

        // Build test request (db-stats is a safe endpoint to test)
        $params = array(
            'action' => 'db-stats',
            'format' => 'json'
        );

        if (!empty($signature)) {
            $params['signature'] = $signature;
        } elseif (!empty($username) && !empty($password)) {
            $params['username'] = $username;
            $params['password'] = $password;
        } else {
            return new WP_Error('missing_auth', 'YOURLS authentication credentials are not configured');
        }

        $response = wp_remote_post($api_url, array(
            'body' => $params,
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($response_code === 200 && is_array($data)) {
            return array(
                'success' => true,
                'message' => 'YOURLS connection successful',
                'data' => $data
            );
        }

        return new WP_Error('connection_failed', 'Failed to connect to YOURLS API');
    }
}
