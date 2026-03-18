<?php
/**
 * Quick diagnostic to check if options are actually in the database
 * 
 * Upload this to wp-content/mu-plugins/ and visit: yourdomain.com/?check_options=1
 */

if (isset($_GET['check_options']) && current_user_can('manage_options')) {
    header('Content-Type: text/plain');
    
    echo "=== CHECKING OPTIONS IN DATABASE ===\n\n";
    
    // Check third-party scripts
    echo "1. Simple Commenter Script:\n";
    $simple = get_option('simple_commenter_script', false);
    echo "   Exists: " . ($simple === false ? 'NO' : 'YES') . "\n";
    echo "   Value: " . ($simple ? substr($simple, 0, 100) . '...' : 'EMPTY') . "\n\n";
    
    echo "2. Ahrefs Analytics Script:\n";
    $ahrefs = get_option('ahrefs_analytics_script', false);
    echo "   Exists: " . ($ahrefs === false ? 'NO' : 'YES') . "\n";
    echo "   Value: " . ($ahrefs ? substr($ahrefs, 0, 100) . '...' : 'EMPTY') . "\n\n";
    
    echo "3. Google Fonts Preload:\n";
    $fonts = get_option('bw_google_fonts_preload', false);
    echo "   Exists: " . ($fonts === false ? 'NO' : 'YES') . "\n";
    echo "   Value: " . ($fonts ? substr($fonts, 0, 100) . '...' : 'EMPTY') . "\n\n";
    
    // Check if LiteSpeed Cache is active
    echo "=== LITESPEED CACHE STATUS ===\n";
    if (defined('LSCWP_V')) {
        echo "LiteSpeed Cache: ACTIVE (version " . LSCWP_V . ")\n";
        echo "WARNING: Admin pages might be cached!\n";
    } else {
        echo "LiteSpeed Cache: Not active\n";
    }
    
    exit;
}
