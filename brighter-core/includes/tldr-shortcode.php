<?php
/**
 * TLDR Shortcode
 * 
 * Displays article summary/TLDR with semantic markup for:
 * - Screen readers
 * - Voice search (Google speakable schema)
 * - SEO
 * 
 * Usage: [tldr] or [tldr wrapper="div"]
 * 
 * Output includes:
 * - id="tldr-summary" for targeting
 * - class="speakthis" for Google speakable schema
 * - Proper paragraph wrapping
 */

if (!defined('ABSPATH')) exit;

add_shortcode('tldr', function($atts) {
    $atts = shortcode_atts([
        'wrapper' => 'div',  // div or section
        'class' => '',       // Additional CSS classes
    ], $atts, 'tldr');
    
    $post_id = get_the_ID();
    if (!$post_id) {
        return '';
    }
    
    // Get TLDR content from new standardized field
    $tldr = get_post_meta($post_id, 'bw_tldr', true);
    
    // Fallback to old ACF field if new field is empty
    if (empty($tldr)) {
        $tldr = get_post_meta($post_id, 'tldr', true);
    }
    
    // Return empty if no TLDR content
    if (empty($tldr)) {
        return '';
    }
    
    // Sanitize and prepare content
    $tldr = wp_kses_post($tldr); // Allow basic HTML tags
    $tldr = wpautop($tldr);      // Auto-paragraph
    
    // Build wrapper element
    $wrapper = in_array($atts['wrapper'], ['div', 'section']) ? $atts['wrapper'] : 'div';
    
    // Build class attribute
    $classes = ['tldr-summary', 'speakthis'];
    if (!empty($atts['class'])) {
        $classes[] = sanitize_html_class($atts['class']);
    }
    $class_attr = implode(' ', $classes);
    
    // Output with semantic markup
    $output = sprintf(
        '<%1$s id="tldr-summary" class="%2$s" itemscope itemtype="https://schema.org/SpeakableSpecification">
            <h3 class="tldr-heading">Summary</h3>
            <div class="tldr-content">%3$s</div>
        </%1$s>',
        $wrapper,
        esc_attr($class_attr),
        $tldr
    );
    
    return $output;
});

/**
 * Add basic TLDR styles (can be overridden by theme)
 */
add_action('wp_head', function() {
    ?>
    <style>
        .tldr-summary {
            background: #f8f9fa;
            border-left: 4px solid #0073aa;
            padding: 1.5rem;
            margin: 2rem 0;
            border-radius: 4px;
        }
        
        .tldr-summary .tldr-heading {
            margin-top: 0;
            margin-bottom: 1rem;
            font-size: 1.2em;
            color: #0073aa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .tldr-summary .tldr-content {
            line-height: 1.6;
        }
        
        .tldr-summary .tldr-content p:last-child {
            margin-bottom: 0;
        }
    </style>
    <?php
}, 100);

