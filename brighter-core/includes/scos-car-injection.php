<?php
/**
 * SCOS Content Architecture Record (CAR) Injection
 * 
 * Version: 1.0.0
 * 
 * Purpose:
 * - Single source of truth for content metadata injection
 * - Consolidates data from bw-content-strategy.php + class-altc-ga4-integration.php
 * - Creates machine-readable content architecture for AI agents
 * - Provides backwards-compatible data structure for GA4 tracking
 * 
 * Responsibilities:
 * - Inject SCOS CAR data into <head> as window.brighterSCOS
 * - Maintain backwards compatibility with window.brighterContentStrategy
 * - Maintain backwards compatibility with window.brighterGA4
 * - Only load on singular posts/pages
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
    // Only run on singular posts/pages
    if (!is_singular()) {
        return;
    }
    
    $post_id = get_the_ID();
    
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
    $opt_status = get_post_meta($post_id, '_brt_opt_status', true) ?: 'not_set';
    
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
    $service_pathway_name = 'none';
    
    if ($service_pathway_id) {
        $service_pathway_name = get_the_title($service_pathway_id);
    }
    
    // Content Plan (replaces deprecated optimization_status)
    $content_plan = get_post_meta($post_id, 'content_plan', true) ?: 'none';
    
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
            'purpose' => $purpose,
            'optimization_status' => $opt_status,
            
            // Metrics (internal only - not sent to GA4)
            'metrics' => $metrics
        ],
        
        // Pillar relationship
        'pillar' => $pillar,
        
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
    // ============================================
    ?>
    <script>
    // SCOS Content Architecture Record (CAR)
    // Single source of truth for content metadata
    window.brighterSCOS = <?php echo json_encode($scos, JSON_UNESCAPED_SLASHES); ?>;
    
    // ============================================
    // BACKWARDS COMPATIBILITY
    // ============================================
    // Maintain existing window objects for GA4 tracking scripts
    
    // Legacy GA4 config object (used by brighter-ga4-tracking.php)
    window.brighterGA4 = {
        measurementId: window.brighterSCOS.tracking.ga4_id,
        loaded: false
    };
    
    // Legacy content strategy object (used by GA4 enhanced tracking)
    window.brighterContentStrategy = {
        // Original fields from bw-content-strategy.php
        content_intent: window.brighterSCOS.car.intent,
        content_purpose: window.brighterSCOS.car.purpose,
        content_topic: window.brighterSCOS.car.topic, // Legacy - kept for backwards compatibility
        optimization_status: window.brighterSCOS.car.optimization_status, // Legacy - deprecated
        pillar_page: <?php echo json_encode($pillar_name); ?>,
        pillar_type: <?php echo json_encode($pillar_type); ?>,
        post_type: window.brighterSCOS.meta.post_type,
        
        // ALTC fields from class-altc-ga4-integration.php
        altc_primary: window.brighterSCOS.car.cluster,
        altc_topic: window.brighterSCOS.car.topic, // Preferred over content_topic
        content_maturity: window.brighterSCOS.car.maturity,
        
        // New fields
        service_pathway: <?php echo json_encode($service_pathway_name); ?>,
        content_plan: <?php echo json_encode($content_plan); ?>,
        
        // Breadcrumb schema for short page title
        breadcrumb_schema: <?php echo json_encode(get_post_meta($post_id, 'bw_breadcrumb_schema', true) ?: ''); ?>
    };
    </script>
    <?php
}, 5); // Priority 5 = loads before GA4 tracking (priority 99)

