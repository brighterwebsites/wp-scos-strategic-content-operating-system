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
    <!-- Google Analytics 4 - Brighter Core -->
    <script>
        window.brighterGA4 = {
            measurementId: '<?php echo esc_js($ga4_id); ?>',
            loaded: false,
            skipTracking: <?php echo $is_admin_or_editor ? 'true' : 'false'; ?> // Skip events if admin/editor logged in
        };
    </script>
    <?php
}, 5); // Priority 5 = loads early

/** PART 1: Inline Core Script - Consent-gated GA4 loader */
add_action('wp_head', function() {
    ?>
    <script data-no-optimize="1" data-cfasync="false">
    (function(){'use strict';
    function hasConsent(){
        var c=document.cookie.split(';');
        for(var i=0;i<c.length;i++){
            var ck=c[i].trim();
            if(ck.startsWith('seopress-user-consent-accept=')){
                var v=ck.split('=')[1];
                if(v==='1'||v==='true'||v==="'1'"||v==='"1"')return true;
            }
        }
        return false;
    }
    function init(){
        if(!hasConsent())return;
        // Skip tracking if admin/editor is logged in
        if(window.brighterGA4&&window.brighterGA4.skipTracking===true)return;
        if(window.brighterGA4&&!window.brighterGA4.loaded){
            var s=document.createElement('script');
            s.async=true;
            s.src='https://www.googletagmanager.com/gtag/js?id='+window.brighterGA4.measurementId;
            document.head.appendChild(s);
            window.dataLayer=window.dataLayer||[];
            function gtag(){dataLayer.push(arguments);}
            window.gtag=gtag;
            gtag('js',new Date());
            gtag('config',window.brighterGA4.measurementId,{'send_page_view':false});
            window.brighterGA4.loaded=true;
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
    init();
    document.addEventListener('seopress_analytics_cookies_accepted',init);
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