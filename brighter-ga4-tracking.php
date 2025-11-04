<?php
/**
 * Brighter GA4 Tracking Loader - OPTIMIZED
 * Version: 2.2.0 - Works with SEOPress GA4
 * 
 * Note: SEOPress loads gtag.js, we just add enhanced tracking
 */

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
            console.log('?? GA4 Enhanced: Waiting for cookie consent');
            return;
        }
        
        // Wait for gtag to be loaded by SEOPress
        function initTracking() {
            if (typeof window.gtag !== 'function') {
                // gtag not ready yet, try again in 100ms
                setTimeout(initTracking, 100);
                return;
            }
            
            console.log('? GA4 Enhanced: Consent granted, tracking active');
            
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
    wp_enqueue_script($handle, $src, [], '2.2.0', true);

    // Add defer attribute and cache plugin exclusions
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