<?php
/**
 * Brighter API Authentication Handler
 *
 * Handles API token verification and generation for Custom GPT access
 *
 * @package BrighterCore
 * @subpackage API
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class Brighter_API_Auth {

    /**
     * Option name for storing API token
     */
    const TOKEN_OPTION = 'brighter_api_token';

    /**
     * Verify API token from request header
     *
     * @param WP_REST_Request $request Request object
     * @return true|WP_Error True if valid, WP_Error if invalid
     */
    public function verify_token($request) {
        // Get token from X-Brighter-Token header
        $provided_token = $request->get_header('X-Brighter-Token');

        // Check if token was provided
        if (empty($provided_token)) {
            return new WP_Error(
                'unauthorized',
                'Missing API token. Provide X-Brighter-Token header.',
                array('status' => 401)
            );
        }

        // Get stored token
        $stored_token = get_option(self::TOKEN_OPTION);

        // Check if token exists in database
        if (empty($stored_token)) {
            return new WP_Error(
                'unauthorized',
                'API token not configured. Generate token in admin settings.',
                array('status' => 401)
            );
        }

        // Verify token matches (timing-safe comparison)
        if (!hash_equals($stored_token, $provided_token)) {
            return new WP_Error(
                'unauthorized',
                'Invalid API token.',
                array('status' => 401)
            );
        }

        // Token is valid
        return true;
    }

    /**
     * Generate a new API token
     *
     * @return string New cryptographically secure token
     */
    public function generate_token() {
        // Generate 32-character alphanumeric token
        // Using wp_generate_password for cryptographic security
        $token = wp_generate_password(32, false, false);

        // Store in options
        update_option(self::TOKEN_OPTION, $token, false);

        return $token;
    }

    /**
     * Get current API token
     *
     * @return string|false Current token or false if not set
     */
    public function get_token() {
        return get_option(self::TOKEN_OPTION);
    }

    /**
     * Delete API token
     *
     * @return bool True on success
     */
    public function delete_token() {
        return delete_option(self::TOKEN_OPTION);
    }

    /**
     * Check if API is configured (token exists)
     *
     * @return bool True if token exists
     */
    public function is_configured() {
        return !empty($this->get_token());
    }

    /**
     * Placeholder for future rate limiting
     *
     * Structure allows easy addition of rate limiting in future:
     * - Check IP/token request count
     * - Use transients for rate limit tracking
     * - Return WP_Error if rate exceeded
     *
     * @param WP_REST_Request $request Request object
     * @return true|WP_Error True if allowed, WP_Error if rate exceeded
     */
    public function check_rate_limit($request) {
        // Future implementation:
        // $ip = $request->get_header('X-Forwarded-For') ?: $_SERVER['REMOTE_ADDR'];
        // $token = $request->get_header('X-Brighter-Token');
        // $key = 'brighter_api_rate_' . md5($ip . $token);
        // $requests = get_transient($key) ?: 0;
        //
        // if ($requests >= 100) { // 100 requests per 5 minutes
        //     return new WP_Error('rate_limit_exceeded', 'Too many requests', ['status' => 429]);
        // }
        //
        // set_transient($key, $requests + 1, 300);

        return true;
    }
}
