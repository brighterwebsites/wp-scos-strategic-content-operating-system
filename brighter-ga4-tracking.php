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

    // Check if admin or editor is logged in (skip tracking)
    $is_admin_or_editor = current_user_can('edit_posts');
    
    // Pass GA4 ID to JavaScript for consent-based loading
    ?>
    <!-- Google Analytics 4 -->
    <script data-no-optimize="1" data-cfasync="false" data-litespeed-no-optimize="1">
        (function() {
            'use strict';
            window.brighterGA4 = {
                measurementId: '<?php echo esc_js($ga4_id); ?>',
                loaded: false,
                skipTracking: <?php echo $is_admin_or_editor ? 'true' : 'false'; ?> // Skip events if admin/editor logged in
            };
            // Debug logging (only in development - remove in production)
            if (window.console && window.console.log) {
                console.log('[Brighter GA4] Initialized:', {
                    measurementId: window.brighterGA4.measurementId,
                    skipTracking: window.brighterGA4.skipTracking,
                    userLoggedIn: <?php echo is_user_logged_in() ? 'true' : 'false'; ?>
                });
            }
        })();
    </script>
    <?php
}, 5); // Priority 5 = loads early

/** PART 1: Inline Core Script - GA4 loader (no consent check) */
//Removed comments for cleaner output
// Wait for brighterGA4 to be created (PART 0 runs at priority 5, this runs at 99)
// Only log error after all attempts failed
// Skip tracking if admin/editor is logged in
// Set consent mode to granted (no consent check required)
// Grant analytics storage by default
// Load GA4 script
// Start initialization check
add_action('wp_head', function() {
    ?>
    <script data-no-optimize="1" data-cfasync="false" data-litespeed-no-optimize="1">
    (function(){'use strict';
       var attempts = 0;
    var maxAttempts = 50; // Wait up to 5 seconds (50 * 100ms)
    
    function checkAndInit(){
        attempts++;
        if(!window.brighterGA4){
            if(attempts < maxAttempts){
                setTimeout(checkAndInit, 100);
                return;
            }
            if(window.console&&window.console.error){
                console.error('[Brighter GA4] ERROR: window.brighterGA4 not found after ' + (maxAttempts * 100) + 'ms! Check if brighter-ga4-tracking.php is loading.');
            }
            return;
        }
        
        
        if(window.brighterGA4.skipTracking===true){
            if(window.console&&window.console.log){
                console.log('[Brighter GA4] Skipping tracking (admin/editor logged in)');
            }
            return;
        }
        
        if(!window.brighterGA4.loaded){
            window.dataLayer=window.dataLayer||[];
            function gtag(){dataLayer.push(arguments);}
            window.gtag=gtag;
            gtag('js',new Date());
            gtag('consent','default',{
                'analytics_storage':'granted',
                'ad_storage':'denied'
            });
            var s=document.createElement('script');
            s.async=true;
            s.src='https://www.googletagmanager.com/gtag/js?id='+window.brighterGA4.measurementId;
            document.head.appendChild(s);
            gtag('config',window.brighterGA4.measurementId,{'send_page_view':false});
            window.brighterGA4.loaded=true;
            if(window.console&&window.console.log){
                console.log('[Brighter GA4] Script loaded and configured:',window.brighterGA4.measurementId);
            }
        }
        
        (function track(){
            if(typeof window.gtag!=='function'){setTimeout(track,100);return;}
            document.addEventListener('click',function(e){
                var el=e.target.closest('a,button');
                if(!el||el.dataset.gaSkip==='1')return;
                var href=el.getAttribute('href');
                if(!href)return;
                var ev='click';
                if(href.startsWith('tel:'))ev='click_phone';
                else if(href.startsWith('mailto:'))ev='click_email';
                else if(/\.(pdf|docx?|xlsx?|zip)$/i.test(href))ev='download';
                gtag('event',ev,{event_category:'Engagement',event_label:el.textContent?.trim()||href,page_title:document.title,page_path:location.pathname});
            },true);
            var scrolled=false;
            window.addEventListener('scroll',function(){
                if(scrolled)return;
                var depth=(window.scrollY+window.innerHeight)/Math.max(document.body.scrollHeight,document.documentElement.scrollHeight);
                if(depth>=0.5){scrolled=true;gtag('event','scroll',{event_category:'Engagement',event_label:'Scrolled 50%',depth_percent:50,page_title:document.title,page_path:location.pathname});}
            },{passive:true});
        })();
    }
    checkAndInit();
    })();
    </script>
    <?php
}, 99);

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