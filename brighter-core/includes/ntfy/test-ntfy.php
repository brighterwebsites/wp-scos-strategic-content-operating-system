<?php
/**
 * Quick ntfy Connection Test
 *
 * USAGE: Visit yourdomain.com/?test_ntfy=1 (must be logged in as admin)
 * 
 * This will be removed once the UI is built.
 */

add_action('init', function() {
    if (!isset($_GET['test_ntfy']) || !current_user_can('manage_options')) {
        return;
    }
    
    // Load the client
    require_once __DIR__ . '/class-ntfy-client.php';
    
    header('Content-Type: text/plain');
    
    echo "=== ntfy Connection Test ===\n\n";
    
    // Check configuration
    echo "1. Checking wp-config constants:\n";
    echo "   NTFY_ENABLED: " . (defined('NTFY_ENABLED') && NTFY_ENABLED ? 'YES' : 'NO') . "\n";
    echo "   NTFY_SERVER_URL: " . (defined('NTFY_SERVER_URL') ? NTFY_SERVER_URL : 'NOT SET') . "\n";
    echo "   NTFY_USERNAME: " . (defined('NTFY_USERNAME') ? 'SET' : 'NOT SET') . "\n";
    echo "   NTFY_PASSWORD: " . (defined('NTFY_PASSWORD') ? 'SET' : 'NOT SET') . "\n\n";
    
    // Test connection
    echo "2. Testing connection...\n";
    $client = new Brighter_Ntfy_Client();
    
    if (!$client->is_configured()) {
        echo "   ❌ FAILED: ntfy is not configured in wp-config.php\n";
        exit;
    }
    
    echo "   ✓ Configuration looks good\n\n";
    
    echo "3. Sending test notification...\n";
    $result = $client->send('bw-test', 
        'Test from ' . get_bloginfo('name') . ' (' . home_url() . ')',
        [
            'title' => '✅ ntfy WordPress Test',
            'priority' => 'default',
            'tags' => ['test', 'white_check_mark'],
            'click' => admin_url('admin.php?page=brighter_support'),
        ]
    );
    
    if (is_wp_error($result)) {
        echo "   ❌ FAILED: " . $result->get_error_message() . "\n";
    } else {
        echo "   ✅ SUCCESS! Check your ntfy app for the notification.\n";
        echo "   Topic: bw-test\n";
    }
    
    exit;
});
