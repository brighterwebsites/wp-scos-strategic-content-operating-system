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
     * Normalize URL by removing UTM parameters for comparison
     *
     * @param string $url The URL to normalize
     * @return string URL without UTM parameters
     */
    private static function normalize_url($url) {
        $parsed = parse_url($url);
        if (!isset($parsed['query'])) {
            return $url;
        }
        
        parse_str($parsed['query'], $params);
        
        // Remove UTM parameters
        $utm_params = array('utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term');
        foreach ($utm_params as $param) {
            unset($params[$param]);
        }
        
        // Rebuild URL
        $base_url = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port'])) {
            $base_url .= ':' . $parsed['port'];
        }
        if (isset($parsed['path'])) {
            $base_url .= $parsed['path'];
        }
        
        if (!empty($params)) {
            $base_url .= '?' . http_build_query($params);
        }
        
        if (isset($parsed['fragment'])) {
            $base_url .= '#' . $parsed['fragment'];
        }
        
        return $base_url;
    }

    /**
     * Check if a keyword exists in YOURLS and get its current URL
     *
     * @param string $keyword The keyword to check
     * @param string $api_url YOURLS API URL
     * @param array $auth_params Authentication parameters
     * @return array|false Array with 'url' and 'shorturl' if exists, false otherwise
     */
    private static function check_keyword_exists($keyword, $api_url, $auth_params) {
        $params = array_merge($auth_params, array(
            'action' => 'expand',
            'shorturl' => $keyword,
            'format' => 'json'
        ));

        $response = wp_remote_post($api_url, array(
            'body' => $params,
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (is_array($data) && isset($data['status']) && $data['status'] === 'success' && isset($data['longurl'])) {
            return array(
                'url' => $data['longurl'],
                'shorturl' => isset($data['shorturl']) ? $data['shorturl'] : ''
            );
        }

        return false;
    }

    /**
     * Update an existing YOURLS shortlink URL using save_by_keyword action
     * 
     * NOTE: This requires the 'yourls-api-save-by-keyword' plugin to be installed on YOURLS.
     * If the plugin is not available, this will return false.
     *
     * @param string $long_url The new URL to set
     * @param string $keyword The keyword to update
     * @param string $title Optional title
     * @param string $api_url YOURLS API URL
     * @param array $auth_params Authentication parameters
     * @return array|false Array with success data, or false on failure
     */
    private static function update_existing_keyword($long_url, $keyword, $title, $api_url, $auth_params) {
        $params = array_merge($auth_params, array(
            'action' => 'save_by_keyword',
            'url' => $long_url,
            'keyword' => $keyword,
            'format' => 'json'
        ));

        if (!empty($title)) {
            $params['title'] = $title;
        }

        $response = wp_remote_post($api_url, array(
            'body' => $params,
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (is_array($data) && isset($data['status']) && $data['status'] === 'success' && isset($data['shorturl'])) {
            return array(
                'success' => true,
                'shorturl' => $data['shorturl'],
                'keyword' => $keyword,
                'message' => 'Shortlink URL updated successfully'
            );
        }

        return false;
    }

    /**
     * Create a shortlink via YOURLS API
     * 
     * HANDLES EXISTING KEYWORDS:
     * - If keyword exists and base URL matches (ignoring UTM params), returns success with existing shortlink
     * - If keyword exists and URL differs, attempts to update using save_by_keyword action (if plugin available)
     * - Otherwise returns error
     * 
     * TODO/FUTURE REFACTOR: Shortlink keywords must be unique site-wide to prevent conflicts.
     * Currently managed manually during testing (low volume). Future implementation should:
     * - Validate keyword uniqueness before creation
     * - Provide admin UI for keyword management
     * - Add conflict detection and resolution
     *
     * @param string $long_url The full URL to shorten (with UTM parameters)
     * @param string $keyword The custom keyword (e.g., "seo-signals")
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
            
            // Handle keyword already exists error
            if (is_array($data) && 
                isset($data['code']) && 
                $data['code'] === 'error:keyword') {
                
                error_log('YOURLS: Keyword already exists: ' . $keyword);
                
                // Build auth params for helper methods
                $auth_params = array();
                if (!empty($signature)) {
                    $auth_params['signature'] = $signature;
                } elseif (!empty($username) && !empty($password)) {
                    $auth_params['username'] = $username;
                    $auth_params['password'] = $password;
                }
                
                // Check what URL the existing keyword points to
                $existing = self::check_keyword_exists($keyword, $api_url, $auth_params);
                
                if ($existing) {
                    // Normalize both URLs (remove UTM params) for comparison
                    $normalized_existing = self::normalize_url($existing['url']);
                    $normalized_requested = self::normalize_url($long_url);
                    
                    // If base URLs match (ignoring UTM params), return success
                    if ($normalized_existing === $normalized_requested) {
                        error_log('YOURLS: Keyword exists with matching base URL, returning existing shortlink');
                        
                        // Build shorturl if not provided
                        $shorturl = $existing['shorturl'];
                        if (empty($shorturl)) {
                            $base_url = preg_replace('/yourls-api\.php$/i', '', $api_url);
                            $shorturl = rtrim($base_url, '/') . '/' . $keyword;
                        }
                        
                        return array(
                            'success' => true,
                            'shorturl' => $shorturl,
                            'keyword' => $keyword,
                            'keyword_requested' => $keyword,
                            'keyword_modified' => false,
                            'message' => 'Shortlink already exists with matching URL (reused existing)'
                        );
                    }
                    
                    // URLs differ - try to update using save_by_keyword action
                    error_log('YOURLS: Keyword exists but URL differs, attempting to update');
                    $update_result = self::update_existing_keyword($long_url, $keyword, $title, $api_url, $auth_params);
                    
                    if ($update_result) {
                        error_log('YOURLS: Successfully updated existing keyword URL');
                        return $update_result;
                    }
                    
                    // Update failed (plugin not available or other error)
                    // Return error but log the situation
                    error_log('YOURLS: Keyword exists with different URL and update failed. Existing URL: ' . $existing['url'] . ', Requested URL: ' . $long_url);
                    return new WP_Error(
                        'keyword_exists_different_url', 
                        'Keyword already exists with a different URL. Existing: ' . $existing['url'] . '. To enable automatic updates, install the "yourls-api-save-by-keyword" plugin on your YOURLS instance.',
                        array(
                            'existing_url' => $existing['url'],
                            'requested_url' => $long_url,
                            'keyword' => $keyword
                        )
                    );
                }
                
                // Keyword exists but we couldn't retrieve its details
                // Build shorturl and return success (assume it's okay)
                error_log('YOURLS: Keyword exists but details unavailable, returning assumed shortlink');
                $base_url = preg_replace('/yourls-api\.php$/i', '', $api_url);
                $shorturl = rtrim($base_url, '/') . '/' . $keyword;
                
                return array(
                    'success' => true,
                    'shorturl' => $shorturl,
                    'keyword' => $keyword,
                    'keyword_requested' => $keyword,
                    'keyword_modified' => false,
                    'message' => 'Shortlink keyword already exists (assumed success)'
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

        // Handle error:keyword in success response (some YOURLS configs return 200 with error status)
        if (isset($data['code']) && $data['code'] === 'error:keyword') {
            error_log('YOURLS: Keyword already exists (in success response): ' . $keyword);
            
            // Build auth params for helper methods
            $auth_params = array();
            if (!empty($signature)) {
                $auth_params['signature'] = $signature;
            } elseif (!empty($username) && !empty($password)) {
                $auth_params['username'] = $username;
                $auth_params['password'] = $password;
            }
            
            // Check what URL the existing keyword points to
            $existing = self::check_keyword_exists($keyword, $api_url, $auth_params);
            
            if ($existing) {
                // Normalize both URLs (remove UTM params) for comparison
                $normalized_existing = self::normalize_url($existing['url']);
                $normalized_requested = self::normalize_url($long_url);
                
                // If base URLs match (ignoring UTM params), return success
                if ($normalized_existing === $normalized_requested) {
                    error_log('YOURLS: Keyword exists with matching base URL, returning existing shortlink');
                    
                    // Build shorturl if not provided
                    $shorturl = $existing['shorturl'];
                    if (empty($shorturl)) {
                        $base_url = preg_replace('/yourls-api\.php$/i', '', $api_url);
                        $shorturl = rtrim($base_url, '/') . '/' . $keyword;
                    }
                    
                    return array(
                        'success' => true,
                        'shorturl' => $shorturl,
                        'keyword' => $keyword,
                        'keyword_requested' => $keyword,
                        'keyword_modified' => false,
                        'message' => 'Shortlink already exists with matching URL (reused existing)'
                    );
                }
                
                // URLs differ - try to update using save_by_keyword action
                error_log('YOURLS: Keyword exists but URL differs, attempting to update');
                $update_result = self::update_existing_keyword($long_url, $keyword, $title, $api_url, $auth_params);
                
                if ($update_result) {
                    error_log('YOURLS: Successfully updated existing keyword URL');
                    return $update_result;
                }
                
                // Update failed - return error
                error_log('YOURLS: Keyword exists with different URL and update failed');
                return new WP_Error(
                    'keyword_exists_different_url', 
                    'Keyword already exists with a different URL. Existing: ' . $existing['url'] . '. To enable automatic updates, install the "yourls-api-save-by-keyword" plugin on your YOURLS instance.',
                    array(
                        'existing_url' => $existing['url'],
                        'requested_url' => $long_url,
                        'keyword' => $keyword
                    )
                );
            }
            
            // Keyword exists but we couldn't retrieve its details
            // Build shorturl and return success (assume it's okay)
            error_log('YOURLS: Keyword exists but details unavailable, returning assumed shortlink');
            $base_url = preg_replace('/yourls-api\.php$/i', '', $api_url);
            $shorturl = rtrim($base_url, '/') . '/' . $keyword;
            
            return array(
                'success' => true,
                'shorturl' => $shorturl,
                'keyword' => $keyword,
                'keyword_requested' => $keyword,
                'keyword_modified' => false,
                'message' => 'Shortlink keyword already exists (assumed success)'
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
