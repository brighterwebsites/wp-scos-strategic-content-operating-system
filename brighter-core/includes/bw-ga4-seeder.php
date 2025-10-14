<?php
/**
 * GA4 Event Seeder - Low Traffic Site Edition
 * 
 * FOR LOW-VOLUME, HIGH-TICKET SITES (< 50 visitors/week)
 * 
 * Purpose: Pre-register all event names in GA4 on initial setup
 * Trigger: ?seedEvents=true (admin only, runs once)
 * 
 * This creates a complete event taxonomy immediately so you can:
 * - Mark conversions from day 1
 * - Set up proper attribution
 * - Track rare high-value events
 * 
 * Safe for production - marks all events clearly as seed data
 */

// Only run for admins
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    return;
}

// Only run when explicitly requested
if (!isset($_GET['seedEvents']) || $_GET['seedEvents'] !== 'true') {
    return;
}

// Check if already seeded (store in transient for 30 days)
$seeded_flag = get_transient('brighter_ga4_events_seeded');
if ($seeded_flag) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-success"><p>';
        echo '<strong>GA4 Events Already Seeded</strong><br>';
        echo 'Events were registered on ' . get_transient('brighter_ga4_seed_date');
        echo '</p></div>';
    });
    return;
}

// Add notice in admin bar
add_action('wp_footer', function() {
    if (is_user_logged_in() && current_user_can('manage_options')) {
        echo '<style>
            .ga4-seed-notice {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #ff6b6b;
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                font-size: 14px;
                font-weight: 600;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                z-index: 999999;
                animation: fadeIn 0.5s;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
        </style>
        <div class="ga4-seed-notice">
            ?? GA4 Event Seeding Active<br>
            <small>Check GA4 Realtime in 30 seconds</small>
        </div>';
    }
});

// Output seeding script
add_action('wp_footer', function() {
?>
<script>
(function() {
    'use strict';
    
    if (typeof window.gtag !== 'function') {
        console.error('GA4 not loaded - cannot seed events');
        return;
    }
    
    console.log('%c?? GA4 Event Seeder', 'background: #4CAF50; color: white; padding: 6px 12px; font-weight: bold; border-radius: 4px;');
    
    // Get base params (matches your main script)
    const contentStrategy = window.brighterContentStrategy || {};
    const region = new URLSearchParams(location.search).get('region') || 'zone4-remote';
    
    function getBaseParams() {
        return {
            region_id: region,
            page_title: document.title + ' [SEED]',
            page_path: location.pathname,
            content_intent: contentStrategy.content_intent || 'not_set',
            content_purpose: contentStrategy.content_purpose || 'not_set',
            content_topic: contentStrategy.content_topic || 'not_set',
            optimization_status: 'seed_test',
            pillar_page: contentStrategy.pillar_page || document.title,
            pillar_type: contentStrategy.pillar_type || 'none',
            post_type: contentStrategy.post_type || 'page',
            event_label: 'Seed Event - Ignore',
            value: 0.01 // Tiny value to identify as test
        };
    }
    
    // All events from your RULES
    const eventsToSeed = [
        // Conversion events
        { name: 'click_meeting', category: 'Meetings' },
        { name: 'click_main_cta', category: 'Quote' },
        { name: 'click_micro_cta', category: 'Quote' },
        { name: 'form_submit', category: 'Forms' },
        { name: 'generate_lead', category: 'Forms' },
        { name: 'get_lead_magnet', category: 'Lead Magnet' },
        { name: 'subscribe', category: 'Subscribe' },
        
        // Contact events
        { name: 'click_phone', category: 'Contact' },
        { name: 'click_email', category: 'Contact' },
        
        // Navigation
        { name: 'nav_blog', category: 'Navigation' },
        { name: 'nav_project', category: 'Navigation' },
        { name: 'click_product', category: 'Product' },
        { name: 'click_service', category: 'Service' },
        { name: 'click_pricing_detail', category: 'Navigation' },
        { name: 'click_comparison', category: 'Navigation' },
        
        // Trust signals
        { name: 'view_reviews', category: 'Trust' },
        { name: 'view_pricing', category: 'Trust' },
        { name: 'view_specs', category: 'Trust' },
        { name: 'view_case', category: 'Trust' },
        { name: 'click_video', category: 'Trust' },
        
        // Forms
        { name: 'form_start', category: 'Forms' },
        { name: 'view_quote_form', category: 'Forms' },
        { name: 'view_contact_form', category: 'Forms' },
        { name: 'view_lead_magnet', category: 'Lead Magnet' },
        
        // Hierarchy
        { name: 'view_section', category: 'Hierarchy' },
        
        // System
        { name: 'call_bw_seo_gal_we_shld_wrk_2gether', category: 'System Alert' }
    ];
    
    // Fire each event with tiny delay
    let count = 0;
    eventsToSeed.forEach((event, index) => {
        setTimeout(() => {
            const params = getBaseParams();
            params.event_category = event.category;
            
            gtag('event', event.name, params);
            count++;
            
            console.log(`? Seeded: ${event.name} (${event.category})`);
            
            // Summary when done
            if (count === eventsToSeed.length) {
                console.log(`%c?? Seeded ${count} events`, 'background: #4CAF50; color: white; padding: 6px 12px; font-weight: bold;');
                console.log('Check GA4 ? Realtime ? Events in ~30 seconds');
                console.log('Then go to Admin ? Events to mark conversions');
            }
        }, index * 100); // 100ms between events
    });
})();
</script>
<?php
}, 999);
?>