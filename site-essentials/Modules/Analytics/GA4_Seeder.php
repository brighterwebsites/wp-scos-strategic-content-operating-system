<?php
/**
 * GA4 Event Seeder — low-traffic site edition
 *
 * Pre-registers every tracked event name in GA4 so key events and custom
 * dimensions can be set up from day 1 on low-volume, high-ticket sites.
 * Trigger: /?seedEvents=true as an admin. Seed hits carry event_label
 * "Seed Event - Ignore", a [SEED] page title and value 0.01.
 *
 * v2.0 | 2026-10-23 — moved from brighter-core/includes/bw-ga4-seeder.php v1.4
 *                     into the Analytics module as a class (#93). GA4_Tracker now
 *                     loads gtag on the seed request; before, the seeder ran only
 *                     for admins while the loader skipped gtag for them, so it
 *                     logged "GA4 not loaded" and sent nothing. It now also
 *                     records the seed (brighter_ga4_seed_complete), which it never
 *                     called, so the settings page always showed "not seeded".
 * v1.4 | 2026-10-21 — altc_primary → cluster, altc_topic → topic; content_topic and pillar_* dropped (#98)
 * v1.3 | 2026-10-21 — seed only the events still tracked (#159); content_plan dropped (#97)
 * v1.2 | 2026-10-21 — form_start → lead_form_start; form_submit dropped (#158)
 * v1.1 | 2026-10-20 — read CAR v1.2 keys (known-for-goal, topic-maturity, commercial-end-target)
 *
 * @package    SiteEssentials
 * @subpackage Modules\Analytics
 */

namespace SiteEssentials\Modules\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GA4_Seeder {

	const NONCE_ACTION = 'scos_ga4_seed_complete';

	public static function init() {
		add_action( 'template_redirect', [ __CLASS__, 'maybe_seed' ] );
	}

	/** An admin asked for seeding with ?seedEvents=true. */
	public static function is_seed_request() {
		return isset( $_GET['seedEvents'] ) && 'true' === $_GET['seedEvents'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only trigger, admin-gated
			&& is_user_logged_in() && current_user_can( 'manage_options' );
	}

	public static function maybe_seed() {
		if ( ! self::is_seed_request() || get_transient( 'brighter_ga4_events_seeded' ) ) {
			return;
		}
		add_action( 'wp_footer', [ __CLASS__, 'render_notice' ] );
		add_action( 'wp_footer', [ __CLASS__, 'render_script' ], 999 ); // after the enhanced script's footer enqueue
	}

	public static function render_notice() {
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

	public static function render_script() {
		?>
<script>
(function() {
    'use strict';
    
    if (typeof window.gtag !== 'function') {
        console.error('GA4 not loaded - cannot seed events');
        return;
    }
    
    console.log('%c?? GA4 Event Seeder', 'background: #4CAF50; color: white; padding: 6px 12px; font-weight: bold; border-radius: 4px;');
    
    // Use SCOS CAR directly (single source of truth)
    const scos = window.scosCAR || {};
    const car = scos.car || {};
    const meta = scos.meta || {};
    // CAR v1.2 keys (scos-car-injection.php)
    const knownFor = car['known-for-goal'] || {};
    const endTarget = car['commercial-end-target'] || null;
    
    function getBaseParams() {
        // Use breadcrumb schema as short title if available, fallback to document.title
        const breadcrumb = scos.breadcrumb_schema || '';
        const pageTitle = (breadcrumb && breadcrumb.trim()) 
            ? breadcrumb.trim() + ' [SEED]'
            : document.title + ' [SEED]';
        
        return {
            // Page metadata
            page_title: pageTitle,
            // Note: page_path removed - GA4 automatically tracks this
            
            // Content strategy fields (from SCOS CAR)
            content_intent: car.intent || 'not_set',
            content_purpose: car.purpose || 'not_set',
            
            // ALTC Framework fields (from SCOS CAR)
            cluster: knownFor.title || 'not_set',
            topic: car.topic || 'not_set',
            content_maturity: car['topic-maturity'] || 'not_set',

            // Relationship fields (from SCOS CAR)
            service_pathway: (endTarget && endTarget.title) ? endTarget.title : 'none',
            
            // Post metadata (from SCOS CAR)
            post_type: meta.post_type || 'page',
            
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
        { name: 'lead_form_start', category: 'Forms' },


        { name: 'form_enquiry', category: 'Forms' },
        { name: 'form_quote', category: 'Forms' },
       
        
        { name: 'form_lead_magnet', category: 'Forms' },
        { name: 'form_subscribe', category: 'Forms' },

        { name: 'generate_lead', category: 'Forms' },

        // Hierarchy: CTA milestones (mid, final, assist, end)
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
            if (event.name === 'generate_lead') {
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
                // Record the seed so the Analytics settings page shows it and a reload doesn't re-seed.
                const body = new URLSearchParams({ action: 'brighter_ga4_seed_complete', nonce: <?php echo wp_json_encode( wp_create_nonce( GA4_Seeder::NONCE_ACTION ) ); ?> });
                fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, { method: 'POST', credentials: 'same-origin', body: body });
            }
        }, index * 100); // 100ms between events
    });
})();
</script>
		<?php
	}
}
