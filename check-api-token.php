<?php
/**
 * Diagnostic: Check Brighter API Token
 * 
 * Upload to site root and visit: https://yourdomain.com/check-api-token.php
 * DELETE AFTER USE - SECURITY RISK
 */

// Load WordPress
require_once __DIR__ . '/wp-load.php';

// Security: Only allow admin
if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo '<h1>Brighter API Token Diagnostic</h1>';

$token = get_option('brighter_api_token');

echo '<h2>Database Value:</h2>';
if (empty($token)) {
    echo '<p style="color:red;"><strong>❌ NO TOKEN FOUND</strong> - Generate one in admin settings</p>';
} else {
    echo '<p style="color:green;"><strong>✓ Token exists</strong></p>';
    echo '<p>Token: <code>' . esc_html($token) . '</code></p>';
    echo '<p>Length: ' . strlen($token) . ' characters</p>';
}

echo '<hr>';
echo '<h2>Test Request Headers:</h2>';
echo '<p>If you send a request with this header:</p>';
echo '<pre>X-Brighter-Token: ' . esc_html($token) . '</pre>';
echo '<p>It should work.</p>';

echo '<hr>';
echo '<p><strong>⚠️ DELETE THIS FILE AFTER USE</strong></p>';
