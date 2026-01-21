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

// Wrap everything in template_redirect hook to ensure WordPress is fully loaded
add_action('template_redirect', function() {
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
        // Use breadcrumb schema as short title if available, fallback to document.title
        const pageTitle = (contentStrategy.breadcrumb_schema && contentStrategy.breadcrumb_schema.trim()) 
            ? contentStrategy.breadcrumb_schema.trim() + ' [SEED]'
            : document.title + ' [SEED]';
        
        return {
            // Page metadata
            page_title: pageTitle,
            // Note: page_path removed - GA4 automatically tracks this
            
            // Content strategy fields
            content_intent: contentStrategy.content_intent || 'not_set',
            content_purpose: contentStrategy.content_purpose || 'not_set',
            
            // ALTC Framework fields
            altc_primary: contentStrategy.altc_primary || 'not_set',
            altc_topic: contentStrategy.altc_topic || 'not_set', // Preferred field name
            content_topic: contentStrategy.content_topic || 'not_set', // Legacy - kept for backwards compatibility
            content_maturity: contentStrategy.content_maturity || 'not_set',
            
            // Content workflow fields
            content_plan: contentStrategy.content_plan || 'none',
            
            // Relationship fields
            pillar_page: contentStrategy.pillar_page || 'none',
            pillar_type: contentStrategy.pillar_type || 'none',
            service_pathway: contentStrategy.service_pathway || 'none',
            
            // Post metadata
            post_type: contentStrategy.post_type || 'page',
            
            // Seed event markers
            event_label: 'Seed Event - Ignore',
            value: 0.01 // Tiny value to identify as test
        };
    }
    
    // All events from your RULES
    const eventsToSeed = [
        // Conversion events
        { name: 'click_meeting', category: 'CTA' },
        { name: 'click_main_cta', category: 'CTA' },
        { name: 'click_micro_cta', category: 'CTA' },
        { name: 'click_menu_cta', category: 'CTA' },
        { name: 'click_assist_cta', category: 'CTA' },
        { name: 'click_end_cta', category: 'CTA' },

    // Contact events
        { name: 'click_phone', category: 'Contact' },
        { name: 'click_email', category: 'Contact' },
        
        // Forms
        { name: 'form_start', category: 'Forms' },
        { name: 'form_submit', category: 'Forms' },


        { name: 'form_enquiry', category: 'Forms' },
        { name: 'form_quote', category: 'Forms' },
       
        
        { name: 'form_lead_magnet', category: 'Forms' },
        { name: 'form_subscribe', category: 'Forms' },

        { name: 'generate_lead', category: 'Forms' },
          

        // Navigation
        { name: 'nav_blog', category: 'Navigation' },
        { name: 'nav_project', category: 'Navigation' },
        { name: 'nav_product', category: 'Navigation' },
        { name: 'nav_service', category: 'Navigation' },
        { name: 'nav_pricing', category: 'Navigation' },
      
        
        // Trust signals
        { name: 'view_reviews', category: 'Trust' },
        { name: 'view_pricing', category: 'Trust' },
        { name: 'view_specs', category: 'Trust' },
        { name: 'view_case', category: 'Trust' },
        { name: 'view_badge', category: 'Trust' },
        { name: 'click_video', category: 'Trust' },
        

        
        // Hierarchy
        { name: 'view_section', category: 'Hierarchy' }

        
        // System - DISABLED (Ad Tag Detection commented out in enhanced.js)
        // TODO: Re-enable when Agency Level ad tag detection is added as optional feature
        // { name: 'call_bw_seo_gal_we_shld_wrk_2gether', category: 'System Alert' }
    ];
    
    // Fire each event with tiny delay
    let count = 0;
    eventsToSeed.forEach((event, index) => {
        setTimeout(() => {
            const params = getBaseParams();
            params.event_category = event.category;

            // Add lead hierarchy parameters for form-related events
            if (event.name === 'generate_lead' || event.name === 'form_submit') {
                params.lead_tier = 'warm';
                params.lead_type = 'contact_form';
                params.form_type = 'contact_form';
                params.form_fields = 3;
                params.form_id = 'seed_test_form';
                params.cta_label = 'Seed Test CTA';
                params.cta_location = 'seed';
                params.cta_type = 'main';
                params.element_location = 'above_fold';
            }

            gtag('event', event.name, params);
            count++;

            console.log(`✓ Seeded: ${event.name} (${event.category})`);

            // Summary when done
            if (count === eventsToSeed.length) {
                console.log(`%c✅ Seeded ${count} events`, 'background: #4CAF50; color: white; padding: 6px 12px; font-weight: bold;');
                console.log('Check GA4 → Realtime → Events in ~30 seconds');
                console.log('Then go to Admin → Events to mark conversions');
            }
        }, index * 100); // 100ms between events
    });
})();
</script>
<?php
}, 999);

}); // End template_redirect wrapper