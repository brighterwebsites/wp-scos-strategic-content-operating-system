<?php
/**
 * Brighter GA4 Tracking Loader
 * Version: 3.0.0 - Standalone GA4 implementation
 *
 * Loads GA4 tracking with our configured measurement ID
 * Falls back to SEOPress if present
 */

/**
 * PART 0: Load gtag.js with our GA4 measurement ID (GDPR-compliant)
 */
add_action('wp_head', function() {
    // Get GA4 measurement ID from our settings
    $ga4_id = get_option('brighter_ga4_measurement_id', '');

    // Skip if no ID configured
    if (empty($ga4_id)) {
        echo "<!-- Brighter GA4: No measurement ID configured. Visit Support → Analytics to configure. -->\n";
        return;
    }

    // Pass GA4 ID to JavaScript for consent-based loading
    ?>
    <!-- Google Analytics 4 - Brighter Core -->
    <script>
        window.brighterGA4 = {
            measurementId: '<?php echo esc_js($ga4_id); ?>',
            loaded: false
        };
    </script>
    <?php
}, 5); // Priority 5 = loads early

/**
 * PART 1: Inline Core Script (Essential tracking)
 * Only runs if consent given
 *
 * PERFORMANCE NOTES:
 * - Inline = No HTTP request (FAST)
 * - Not minified = Readable for debugging (comments add ~1KB, negligible)
 * - Excluded from optimization plugins for reliability
 */
add_action('wp_head', function() {
    ?>
    <script data-no-optimize="1" data-cfasync="false">
    (function() {
        'use strict';

        // -------------------------------------------------------
        // UNIVERSAL CONSENT CHECK
        // -------------------------------------------------------

function hasConsent() {
    // Check for SEOPress consent cookie with multiple possible values
    const cookies = document.cookie.split(';');
    for (let cookie of cookies) {
        cookie = cookie.trim();
        // SEOPress - check for various formats
        if (cookie.startsWith('seopress-user-consent-accept=')) {
            const value = cookie.split('=')[1];
            // Accept: "1", "true", or just the presence of the cookie
            if (value === '1' || value === 'true' || value === '\'1\'' || value === '"1"') {
                return true;
            }
        }
        // Other consent plugins
        if (cookie.startsWith('cookie_notice_accepted=true')) return true;
        if (cookie.startsWith('viewed_cookie_policy=yes')) return true;
        if (cookie.startsWith('cmplz_consented_services=')) return true;
        if (cookie.startsWith('cookieyes-consent=yes')) return true;
    }

    return false;
}

        function initializeTracking() {
            // Exit if no consent
            if (!hasConsent()) {
                console.log('🛑 GA4 Enhanced: Waiting for cookie consent');
                return;
            }

            // Load gtag.js if we have a measurement ID
            if (window.brighterGA4 && !window.brighterGA4.loaded) {
                const script = document.createElement('script');
                script.async = true;
                script.src = 'https://www.googletagmanager.com/gtag/js?id=' + window.brighterGA4.measurementId;
                document.head.appendChild(script);

                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                window.gtag = gtag;
                gtag('js', new Date());
                gtag('config', window.brighterGA4.measurementId, {
                    'send_page_view': false  // We'll send it ourselves with custom params
                });

                window.brighterGA4.loaded = true;
                console.log('✅ GA4 Loaded: ' + window.brighterGA4.measurementId);
            }

            // Wait for gtag to be ready
            function initTracking() {
                if (typeof window.gtag !== 'function') {
                    // gtag not ready yet, try again in 100ms
                    setTimeout(initTracking, 100);
                    return;
                }

                console.log('✅ GA4 Enhanced: Consent granted, tracking active');
            
            // -------------------------------------------------------
            // CORE TRACKING (Inline)
            // -------------------------------------------------------
            
            const region = new URLSearchParams(location.search).get('region') || 'zone4-remote';
            
            // Set region as user property
            gtag('set', 'user_properties', { region_id: region });
            
            // Basic click tracking
            document.addEventListener('click', function(e) {
                const el = e.target.closest('a, button');
                if (!el || el.dataset.gaSkip === '1') return;
                
                const href = el.getAttribute('href');
                if (!href) return;
                
                let eventName = 'click';
                if (href.startsWith('tel:')) eventName = 'click_phone';
                else if (href.startsWith('mailto:')) eventName = 'click_email';
                else if (/\.(pdf|docx?|xlsx?|zip)$/i.test(href)) eventName = 'download';
                
                gtag('event', eventName, {
                    event_category: 'Engagement',
                    event_label: el.textContent?.trim() || href,
                    page_title: document.title,
                    page_path: location.pathname,
                    region_id: region
                });
            }, true);
            
            // Basic scroll tracking (50%)
            let scrolled = false;
            window.addEventListener('scroll', function() {
                if (scrolled) return;
                const depth = (window.scrollY + window.innerHeight) / Math.max(
                    document.body.scrollHeight, 
                    document.documentElement.scrollHeight
                );
                if (depth >= 0.5) {
                    scrolled = true;
                    gtag('event', 'scroll', {
                        event_category: 'Engagement',
                        event_label: 'Scrolled 50%',
                        depth_percent: 50,
                        page_title: document.title,
                        page_path: location.pathname,
                        region_id: region
                    });
                }
            }, { passive: true });
        }

        // Start initialization
        initTracking();
    }

    // Try to initialize immediately (if consent cookie already exists)
    initializeTracking();

    // Listen for SEOPress consent event (fires when user clicks "accept")
    document.addEventListener('seopress_analytics_cookies_accepted', function() {
        console.log('🍪 SEOPress consent granted - initializing GA4');
        initializeTracking();
    });

    // Also listen for generic consent events from other plugins
    document.addEventListener('cookie_consent_accepted', function() {
        console.log('🍪 Cookie consent granted - initializing GA4');
        initializeTracking();
    });

    })();
    </script>
    <?php
}, 99); // Priority 99 = after SEOPress loads

/**
 * PART 2: Enhanced Script (Selector attribution, deferred)
 */
add_action('wp_enqueue_scripts', function() {
    $handle = 'brighter-ga4-enhanced';
    $src = content_url('mu-plugins/brighter-core/js/brighter-ga4-enhanced.js');

    // Load in footer with defer
    wp_enqueue_script($handle, $src, [], '5.0.0', true);
    
    // Add defer attribute
    add_filter('script_loader_tag', function($tag, $h) use ($handle) {
        if ($h === $handle) {
            if (strpos($tag, 'defer') === false) {
                $tag = str_replace('<script', '<script defer', $tag);
            }
            // Exclude from optimization plugins (LiteSpeed, Autoptimize, etc.)
            if (strpos($tag, 'data-no-optimize') === false) {
                $tag = str_replace('<script', '<script data-no-optimize="1" data-cfasync="false"', $tag);
            }
        }
        return $tag;
    }, 10, 2);
}, 99);

/**
 * CACHE PLUGIN CONFIGURATION
 *
 * For LiteSpeed Cache:
 * - Go to LiteSpeed Cache → Page Optimization → JS Settings
 * - Add to "JS Excludes": brighter-ga4
 * - This ensures tracking scripts are not combined/minified
 *
 * For Autoptimize:
 * - The data-no-optimize="1" attribute automatically excludes these scripts
 *
 * For WP Rocket:
 * - Go to Settings → File Optimization → JavaScript
 * - Add to "Excluded JavaScript Files": brighter-ga4
 */