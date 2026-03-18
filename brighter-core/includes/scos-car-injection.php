<?php
/**
 * SCOS Content Architecture Record (CAR) Injection
 * 
 * Version: 1.0.0
 * 
 * Purpose:
 * - Defines semantic intent and topical authority mapping
 * - Consolidates data from bw-content-strategy.php + class-altc-ga4-integration.php
 * - Creates machine-readable content architecture for AI agents
 * - Provides backwards-compatible data structure for GA4 tracking
 * 
 * Responsibilities:
 * - Inject SCOS CAR data into <head> as window.brighterSCOS
 * - Defines semantic intent and topical authority mapping
 * - Used by GA4 tracking scripts and AI agents
 * - Loads on all page types (singular, archive, home)
 * 
 * Data Structure:
 * - car: Content Architecture Record (ALTC, strategy, metrics)
 * - pillar: Pillar relationship data
 * - tracking: GA4 configuration
 * - meta: Post metadata
 * 
 * @package BrighterCore
 * @subpackage Analytics
 */

if (!defined('ABSPATH')) exit;

/**
 * Inject SCOS CAR into <head>
 * Priority 5 = loads before GA4 tracking scripts
 */
add_action('wp_head', function() {
    // Get post ID - works on singular pages, homepage (if static page), and archives
    $post_id = null;
    if (is_singular()) {
        $post_id = get_the_ID();
    } elseif (is_front_page()) {
        // Homepage - check if it's a static page or blog posts
        $front_page_id = get_option('page_on_front');
        if ($front_page_id) {
            // Static page set as homepage
            $post_id = $front_page_id;
        }
        // If no static page, homepage shows latest posts - will use minimal data below
    }
    
    // If no post ID available (archive pages, blog home showing latest posts, etc.), use minimal data
    if (!$post_id) {
        // Provide minimal SCOS data for non-singular pages
        $ga4_id = get_option('brighter_ga4_measurement_id', '');
        ?>
        <!-- SCOS CAR - Minimal for archive/home pages -->
        <script data-no-optimize="1" data-cfasync="false" data-litespeed-no-optimize="1">
        // SCOS Content Architecture Record (CAR) - Defines semantic intent and topical authority mapping.
        window.brighterSCOS = {
            car: {
                cluster: 'not_set',
                topic: 'not_set',
                maturity: 'not_set',
                intent: 'not_set',
                'search-intent': 'not_set',
                purpose: 'not_set',
                pillar: null,
                service_pathway: null
            },
            tracking: {
                ga4_id: <?php echo json_encode($ga4_id); ?>,
                consent_given: false
            },
            meta: {
                post_id: 0,
                post_type: '<?php echo get_post_type() ?: 'archive'; ?>',
                scos_version: '<?php echo defined('BRIGHTER_CORE_VERSION') ? BRIGHTER_CORE_VERSION : '1.0.0'; ?>',
                car_generated: '<?php echo current_time('c'); ?>'
            }
        };
        
        </script>
        <?php
        return;
    }
    
    // Continue with full SCOS data for singular pages
    
    // ============================================
    // GATHER ALTC FRAMEWORK DATA
    // ============================================
    
    $altc_id = get_post_meta($post_id, 'bw_primary_altc_id', true);
    $altc_name = 'not_set';
    
    if ($altc_id) {
        $altc_term = get_term($altc_id, 'altc_strategic_lens');
        if ($altc_term && !is_wp_error($altc_term)) {
            $altc_name = $altc_term->name;
        }
    }
    
    // Fallback: Try to get first assigned ALTC term if no primary set
    if ($altc_name === 'not_set') {
        $altc_terms = wp_get_post_terms($post_id, 'altc_strategic_lens', ['fields' => 'names']);
        if (!empty($altc_terms) && !is_wp_error($altc_terms)) {
            $altc_name = $altc_terms[0];
        }
    }
    
    $topic_id = get_post_meta($post_id, 'bw_primary_topic_id', true);
    $topic_name = 'not_set';
    
    if ($topic_id) {
        $topic_term = get_term($topic_id, 'altc_topic');
        if ($topic_term && !is_wp_error($topic_term)) {
            $topic_name = $topic_term->name;
        }
    }
    
    // Fallback: Try to get first assigned topic term if no primary set
    if ($topic_name === 'not_set') {
        $topic_terms = wp_get_post_terms($post_id, 'altc_topic', ['fields' => 'names']);
        if (!empty($topic_terms) && !is_wp_error($topic_terms)) {
            $topic_name = $topic_terms[0];
        }
    }
    
    // Fallback: Try old bw_page_topic meta field
    if ($topic_name === 'not_set') {
        $old_topic = get_post_meta($post_id, 'bw_page_topic', true);
        if (!empty($old_topic)) {
            $topic_name = $old_topic;
        }
    }
    
    // ============================================
    // GATHER CONTENT STRATEGY DATA
    // ============================================
    
    $intent = get_post_meta($post_id, 'bw_intent', true) ?: 'not_set';
    $purpose = get_post_meta($post_id, 'bw_purpose', true) ?: 'not_set';
    $maturity = get_post_meta($post_id, 'bw_cont_maturity', true) ?: 'not_set';
    $search_intent = get_post_meta($post_id, 'bw_search_intent', true) ?: 'not_set';
    
    // ============================================
    // GATHER PILLAR RELATIONSHIP
    // ============================================
    
    $pillar_id = get_post_meta($post_id, 'bw_pillar_page_id', true);
    $pillar = null;
    $pillar_name = 'not_set';
    $pillar_type = 'none';
    
    if ($pillar_id) {
        $pillar_purpose = get_post_meta($pillar_id, 'bw_purpose', true);
        $pillar_name = get_the_title($pillar_id);
        $pillar_type = ($pillar_purpose === 'service-page') ? 'service' : 'pillar';
        
        $pillar = [
            'id' => (int) $pillar_id,
            'title' => $pillar_name,
            'type' => $pillar_type
        ];
    }
    
    // Service Pathway (similar to Pillar but for service/product pathways)
    $service_pathway_id = get_post_meta($post_id, 'bw_service_pathway_id', true);
    $service_pathway = null;
    $service_pathway_name = 'none';
    
    if ($service_pathway_id) {
        $service_pathway_name = get_the_title($service_pathway_id);
        $service_pathway = [
            'id' => (int) $service_pathway_id,
            'title' => $service_pathway_name
        ];
    }
    
    
    // ============================================
    // GATHER CONTENT METRICS (Internal use only)
    // ============================================
    
    $metrics = [
        'word_count' => (int) get_post_meta($post_id, 'bw_word_count', true),
        'reading_time' => (int) get_post_meta($post_id, 'bw_reading_time', true),
        'internal_links' => (int) get_post_meta($post_id, 'bw_internal_link_count', true),
        'external_links' => (int) get_post_meta($post_id, 'bw_external_link_count', true),
        'last_updated' => get_the_modified_date('Y-m-d', $post_id)
    ];
    
    // ============================================
    // BUILD SCOS CAR STRUCTURE
    // ============================================
    
    $scos = [
        'car' => [
            // ALTC Framework
            'cluster' => $altc_name,
            'topic' => $topic_name,
            'maturity' => $maturity,
            
            // Content Strategy
            'intent' => $intent,
            'search-intent' => $search_intent,
            'purpose' => $purpose,
            
            // Relationships
            'pillar' => $pillar,
            'service_pathway' => $service_pathway,
            
            // Metrics (internal only - not sent to GA4)
            'metrics' => $metrics
        ],
        
        // GA4 tracking config
        'tracking' => [
            'ga4_id' => get_option('brighter_ga4_measurement_id', ''),
            'consent_given' => false  // Updated by consent handler JS
        ],
        
        // Metadata
        'meta' => [
            'post_id' => $post_id,
            'post_type' => get_post_type($post_id),
            'scos_version' => defined('BRIGHTER_CORE_VERSION') ? BRIGHTER_CORE_VERSION : '1.0.0',
            'car_generated' => current_time('c')  // ISO 8601 format
        ]
    ];
    
    // ============================================
    // OUTPUT JAVASCRIPT
    // SCOS Content Architecture Record (CAR) - Defines semantic intent and topical authority mapping.
    // Used by: GA4 tracking scripts, content strategy tools, AI agents
    // ============================================
    ?>
    <!-- SCOS CAR - Full data for singular pages -->
    <script data-no-optimize="1" data-cfasync="false" data-litespeed-no-optimize="1">
    // SCOS Content Architecture Record (CAR) - Defines semantic intent and topical authority mapping.
    window.brighterSCOS = <?php echo json_encode($scos, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>;
    
    // Note: window.brighterGA4 is created by brighter-ga4-tracking.php (runs on ALL pages)
    // We don't create it here to avoid conflicts and ensure skipTracking property is preserved
    </script>
    <?php
}, 5); // Priority 5 = loads before GA4 tracking (priority 99)

