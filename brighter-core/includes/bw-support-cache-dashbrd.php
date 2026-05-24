<?php
/**
 * Brighter Cache Testing Dashboard
 * Version: 4.2.0
 * 
 * SECURITY HARDENED:
 * - Nonce verification on all actions
 * - Capability checks
 * - Rate limiting on cache operations
 * - Input validation and output escaping
 * - CSRF protection
 */

/**
 * Rate limiting helper
 * 
 * SECURITY: Prevents abuse of cache operations
 */
function brighter_check_rate_limit($action, $limit = 10, $period = MINUTE_IN_SECONDS) {
    $user_id = get_current_user_id();
    $transient_key = 'brighter_rate_limit_' . $action . '_' . $user_id;
    
    $attempts = get_transient($transient_key);
    
    if ($attempts === false) {
        set_transient($transient_key, 1, $period);
        return true;
    }
    
    if ($attempts >= $limit) {
        return false;
    }
    
    set_transient($transient_key, $attempts + 1, $period);
    return true;
}

/**
 * Add Cache Test submenu - SECURITY HARDENED
 * Only shown when site-essentials is not installed. When SE is active this
 * will eventually live under Site Essentials > Settings > Debug.
 */
add_action('admin_menu', function() {
    if ( defined( 'SITE_ESSENTIALS_VERSION' ) ) { return; }
    add_submenu_page(
        'brighter_support',
        'Cache Test',
        '? Cache Test',
        'manage_options', // SECURITY: Requires admin capability
        'brighter_cache_test',
        'brighter_render_cache_test_secure'
    );
}, 99);

/**
 * Render cache test page with full security
 */
function brighter_render_cache_test_secure() {
    // SECURITY: Double-check permissions
    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__('You do not have sufficient permissions to access this page.', 'brighterwebsites'),
            esc_html__('Permission Denied', 'brighterwebsites'),
            ['response' => 403]
        );
    }
    
    // SECURITY: Handle actions with nonce verification
    if (isset($_POST['clear_all_cache']) && check_admin_referer('brighter_cache_actions', 'brighter_cache_nonce')) {
        // SECURITY: Rate limiting
        if (!brighter_check_rate_limit('cache_clear', 5, MINUTE_IN_SECONDS)) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Too many requests. Please wait a minute.', 'brighterwebsites') . '</p></div>';
        } else {
            wp_cache_flush();
            if (class_exists('Brighter_Business_Cache')) {
                Brighter_Business_Cache::clear();
            }
            if (class_exists('Brighter_Image_Settings_Cache')) {
                Brighter_Image_Settings_Cache::clear();
            }
            delete_transient('brighter_registered_sizes_html');
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('? All caches cleared!', 'brighterwebsites') . '</p></div>';
        }
    }
    
    // SECURITY: Force load cache with rate limiting
    if (isset($_POST['force_load_cache']) && check_admin_referer('brighter_cache_actions', 'brighter_cache_nonce')) {
        if (!brighter_check_rate_limit('cache_load', 10, MINUTE_IN_SECONDS)) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Too many requests. Please wait a minute.', 'brighterwebsites') . '</p></div>';
        } elseif (class_exists('Brighter_Business_Cache')) {
            $loaded = Brighter_Business_Cache::get_all();
            $count = count($loaded);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(__('? Cache force-loaded! Loaded %d business info fields.', 'brighterwebsites'), $count)) . '</p></div>';
        }
    }
    
    // SECURITY: Performance test with rate limiting
    $show_performance_test = false;
    if (isset($_POST['test_performance']) && check_admin_referer('brighter_cache_actions', 'brighter_cache_nonce')) {
        if (!brighter_check_rate_limit('perf_test', 3, MINUTE_IN_SECONDS)) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Too many requests. Please wait a minute.', 'brighterwebsites') . '</p></div>';
        } else {
            $show_performance_test = true;
        }
    }
    
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('? Brighter Core Cache Test', 'brighterwebsites') . '</h1>';
    echo '<p>' . esc_html__('Test and monitor your caching performance', 'brighterwebsites') . '</p>';
    
    // Query count comparison
    echo '<div style="background:#fff;padding:20px;border:1px solid #ccc;border-radius:5px;margin:20px 0;">';
    echo '<h2>' . esc_html__('?? Current Page Performance', 'brighterwebsites') . '</h2>';
    echo '<table class="widefat">';
    echo '<tr><td><strong>' . esc_html__('Queries:', 'brighterwebsites') . '</strong></td><td>' . absint(get_num_queries()) . '</td></tr>';
    echo '<tr><td><strong>' . esc_html__('Load Time:', 'brighterwebsites') . '</strong></td><td>' . esc_html(timer_stop(0, 3)) . ' ' . esc_html__('seconds', 'brighterwebsites') . '</td></tr>';
    echo '<tr><td><strong>' . esc_html__('Memory:', 'brighterwebsites') . '</strong></td><td>' . esc_html(size_format(memory_get_peak_usage())) . '</td></tr>';
    echo '</table>';
    echo '</div>';
    
    // Test 1: Business Info Cache
    echo '<div style="background:#fff;padding:20px;border:1px solid #ccc;border-radius:5px;margin:20px 0;">';
    echo '<h2>' . esc_html__('Test 1: Business Info Cache', 'brighterwebsites') . '</h2>';
    
    if (class_exists('Brighter_Business_Cache')) {
        $start = microtime(true);
        $cached = wp_cache_get('all_business_info', 'brighter_business_info');
        $time = (microtime(true) - $start) * 1000;
        
        if ($cached !== false && is_array($cached)) {
            echo '<p style="color:green;font-size:18px;">? <strong>' . esc_html__('CACHED', 'brighterwebsites') . '</strong></p>';
            echo '<p>' . esc_html__('Retrieved in', 'brighterwebsites') . ' <strong>' . esc_html(number_format($time, 2)) . 'ms</strong></p>';
            echo '<p>' . esc_html__('Cached fields:', 'brighterwebsites') . ' <strong>' . absint(count($cached)) . '</strong></p>';
        } else {
            echo '<p style="color:orange;font-size:18px;">?? <strong>' . esc_html__('NOT CACHED YET', 'brighterwebsites') . '</strong></p>';
            echo '<p>' . esc_html__('Cache will be created on next frontend page load or when shortcode is used.', 'brighterwebsites') . '</p>';
        }
    } else {
        echo '<p style="color:red;">? ' . esc_html__('Brighter_Business_Cache class not found', 'brighterwebsites') . '</p>';
    }
    echo '</div>';
    
    // Test 2: Image Settings Cache
    echo '<div style="background:#fff;padding:20px;border:1px solid #ccc;border-radius:5px;margin:20px 0;">';
    echo '<h2>' . esc_html__('Test 2: Image Settings Cache', 'brighterwebsites') . '</h2>';
    
    if (class_exists('Brighter_Image_Settings_Cache')) {
        $start = microtime(true);
        $img_cache = Brighter_Image_Settings_Cache::get_all();
        $time = (microtime(true) - $start) * 1000;
        
        echo '<p style="color:green;font-size:18px;">? ' . esc_html__('Loaded successfully', 'brighterwebsites') . '</p>';
        echo '<p>' . esc_html__('Retrieved in', 'brighterwebsites') . ' <strong>' . esc_html(number_format($time, 2)) . 'ms</strong></p>';
        echo '<p>' . esc_html__('Settings count:', 'brighterwebsites') . ' <strong>' . absint(count($img_cache)) . '</strong></p>';
    } else {
        echo '<p style="color:red;">? ' . esc_html__('Brighter_Image_Settings_Cache class not found', 'brighterwebsites') . '</p>';
    }
    echo '</div>';
    
    // Test 3: Transient Cache
    echo '<div style="background:#fff;padding:20px;border:1px solid #ccc;border-radius:5px;margin:20px 0;">';
    echo '<h2>' . esc_html__('Test 3: Transient Cache (Registered Sizes)', 'brighterwebsites') . '</h2>';
    
    $transient = get_transient('brighter_registered_sizes_html');
    if ($transient !== false) {
        $timeout = get_option('_transient_timeout_brighter_registered_sizes_html');
        $remaining = $timeout ? max(0, round(($timeout - time()) / 60)) : 0;
        echo '<p style="color:green;font-size:18px;">? <strong>' . esc_html__('CACHED', 'brighterwebsites') . '</strong></p>';
        echo '<p>' . esc_html__('Expires in:', 'brighterwebsites') . ' <strong>' . absint($remaining) . ' ' . esc_html__('minutes', 'brighterwebsites') . '</strong></p>';
    } else {
        echo '<p style="color:orange;font-size:18px;">?? <strong>' . esc_html__('NOT CACHED', 'brighterwebsites') . '</strong></p>';
        echo '<p>' . esc_html__('Will cache on next visit to Support ? Optimisation tab.', 'brighterwebsites') . '</p>';
    }
    echo '</div>';
    
    // Performance Test Results
    if ($show_performance_test && class_exists('Brighter_Business_Cache')) {
        echo '<div style="background:#fffbcc;padding:20px;border:2px solid #e6db55;border-radius:5px;margin:20px 0;">';
        echo '<h2>?? ' . esc_html__('Performance Test Results', 'brighterwebsites') . '</h2>';
        
        Brighter_Business_Cache::clear();
        $start = microtime(true);
        $data = Brighter_Business_Cache::get_all();
        $uncached_time = (microtime(true) - $start) * 1000;
        
        $start = microtime(true);
        $data2 = Brighter_Business_Cache::get_all();
        $cached_time = (microtime(true) - $start) * 1000;
        
        $speedup = $uncached_time > 0 ? round($uncached_time / max($cached_time, 0.001), 1) : 0;
        
        echo '<table class="widefat" style="max-width:600px;">';
        echo '<tr><td><strong>?? ' . esc_html__('Uncached (DB query):', 'brighterwebsites') . '</strong></td><td><strong>' . esc_html(number_format($uncached_time, 2)) . 'ms</strong></td></tr>';
        echo '<tr><td><strong>? ' . esc_html__('Cached (memory):', 'brighterwebsites') . '</strong></td><td><strong>' . esc_html(number_format($cached_time, 2)) . 'ms</strong></td></tr>';
        echo '<tr style="background:#d4edda;"><td><strong>' . esc_html__('Speed increase:', 'brighterwebsites') . '</strong></td><td><strong style="color:green;font-size:20px;">' . esc_html($speedup) . 'x ' . esc_html__('faster', 'brighterwebsites') . '</strong></td></tr>';
        echo '</table>';
        echo '</div>';
    }
    
    // Cache Actions - SECURITY: Nonce field added
    echo '<div style="background:#f0f0f0;padding:20px;border:1px solid #ccc;border-radius:5px;margin:20px 0;">';
    echo '<h2>??? ' . esc_html__('Cache Actions', 'brighterwebsites') . '</h2>';
    echo '<p>' . esc_html__('Use these buttons to test and manage caches:', 'brighterwebsites') . '</p>';
    
    // SECURITY: Single nonce for all forms
    $nonce = wp_create_nonce('brighter_cache_actions');
    
    echo '<form method="post" style="display:inline-block;margin-right:10px;">';
    wp_nonce_field('brighter_cache_actions', 'brighter_cache_nonce');
    submit_button(esc_html__('??? Clear All Caches', 'brighterwebsites'), 'secondary', 'clear_all_cache', false);
    echo '</form>';
    
    echo '<form method="post" style="display:inline-block;margin-right:10px;">';
    wp_nonce_field('brighter_cache_actions', 'brighter_cache_nonce');
    submit_button(esc_html__('? Force Load Cache', 'brighterwebsites'), 'secondary', 'force_load_cache', false);
    echo '</form>';
    
    echo '<form method="post" style="display:inline-block;">';
    wp_nonce_field('brighter_cache_actions', 'brighter_cache_nonce');
    submit_button(esc_html__('?? Run Performance Test', 'brighterwebsites'), 'primary', 'test_performance', false);
    echo '</form>';
    
    echo '<p style="margin-top:15px;"><small><strong>' . esc_html__('Note:', 'brighterwebsites') . '</strong> ' . esc_html__('Caches clear automatically when you save settings. Manual clearing is rarely needed.', 'brighterwebsites') . '</small></p>';
    echo '<p><small><strong>' . esc_html__('Rate Limiting:', 'brighterwebsites') . '</strong> ' . esc_html__('Actions are rate-limited to prevent abuse.', 'brighterwebsites') . '</small></p>';
    echo '</div>';
    
    // Tips
    echo '<div style="background:#e7f5fe;padding:20px;border-left:4px solid #0073aa;margin:20px 0;">';
    echo '<h2>?? ' . esc_html__('Understanding Cache Status', 'brighterwebsites') . '</h2>';
    echo '<ul style="line-height:1.8;">';
    echo '<li><strong style="color:green;">? ' . esc_html__('Green (CACHED)', 'brighterwebsites') . '</strong> - ' . esc_html__('Working perfectly, data is cached in memory', 'brighterwebsites') . '</li>';
    echo '<li><strong style="color:orange;">?? ' . esc_html__('Orange (NOT CACHED)', 'brighterwebsites') . '</strong> - ' . esc_html__('Normal! Will cache on first use (lazy loading)', 'brighterwebsites') . '</li>';
    echo '<li><strong style="color:red;">? ' . esc_html__('Red (ERROR)', 'brighterwebsites') . '</strong> - ' . esc_html__('Problem detected, check if module is loaded', 'brighterwebsites') . '</li>';
    echo '</ul>';
    echo '</div>';
    
    echo '</div>'; // .wrap
}


// ADMIN ONLY - Won't interfere with frontend
add_action('admin_footer', function() {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'brighter') === false) return;
    
    $business_cached = wp_cache_get('all_business_info', 'brighter_business_info');
    $status = $business_cached !== false ? '? CACHED' : '? NOT CACHED';
    
    echo '<div style="background:#000;color:#0f0;padding:10px;position:fixed;bottom:10px;right:10px;z-index:9999;font-family:monospace;font-size:12px;border-radius:5px;">';
    echo '<strong>Cache Status:</strong><br>';
    echo 'Business Info: ' . $status . '<br>';
    echo 'Queries: ' . get_num_queries();
    echo '</div>';
});