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
 */
add_action('wp_head', function() {
    ?>
    <script>
    (function() {
        'use strict';
        
        // -------------------------------------------------------
        // UNIVERSAL CONSENT CHECK
        // -------------------------------------------------------
        
function hasConsent() {
    const cookie = document.cookie;
    
    // SEOPress - check for =1 OR =true
    if (cookie.indexOf('seopress-user-consent-accept=1') !== -1) return true;
    if (cookie.indexOf('seopress-user-consent-accept=true') !== -1) return true;
    
    // Cookie Notice
    if (cookie.indexOf('cookie_notice_accepted=true') !== -1) return true;
    
    // GDPR Cookie Consent
    if (cookie.indexOf('viewed_cookie_policy=yes') !== -1) return true;
    
    // Complianz
    if (cookie.indexOf('cmplz_consented_services') !== -1) return true;
    
    // CookieYes
    if (cookie.indexOf('cookieyes-consent=yes') !== -1) return true;
    
    return false;
}        
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
        if ($h === $handle && strpos($tag, 'defer') === false) {
            $tag = str_replace('<script', '<script defer', $tag);
        }
        return $tag;
    }, 10, 2);
}, 99);