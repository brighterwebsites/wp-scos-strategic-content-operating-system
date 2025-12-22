<?php
/**
 * Reading Time Shortcode
 * 
 * Simple shortcode that reuses existing bw_word_count field
 * to calculate reading time without duplicate meta fields.
 * 
 * Usage: [reading_time] or [reading_time format="minutes"]
 */

if (!defined('ABSPATH')) exit;

add_shortcode('reading_time', function($atts) {
    $atts = shortcode_atts([
        'format' => 'full',
    ], $atts, 'reading_time');
    
    $post_id = get_the_ID();
    if (!$post_id) {
        return '';
    }
    
    // Use existing bw_word_count field (from Content Analysis module)
    $word_count = (int) get_post_meta($post_id, 'bw_word_count', true);
    
    // Fallback: compute if not yet analyzed
    if (!$word_count) {
        $content = get_post_field('post_content', $post_id);
        if ($content) {
            $content = strip_shortcodes($content);
            $content = wp_strip_all_tags($content);
            $word_count = str_word_count($content);
        }
    }
    
    // Calculate reading time (200 words per minute)
    $reading_time = max(1, ceil($word_count / 200));
    $reading_iso = 'PT' . $reading_time . 'M';
    
    // DEBUG (remove after testing)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf('Reading Time Debug - Post ID: %d, Word Count: %d, Reading Time: %d', $post_id, $word_count, $reading_time));
    }
    
    // Format output
    switch (strtolower($atts['format'])) {
        case 'minutes':
            return sprintf('%d min read', $reading_time);
        
        case 'words':
            return sprintf('%s words', number_format($word_count));
        
        case 'iso':
            return esc_html($reading_iso);
        
        case 'full':
        default:
            return sprintf(
                '%d min read (%s words)',
                $reading_time,
                number_format($word_count)
            );
    }
});

