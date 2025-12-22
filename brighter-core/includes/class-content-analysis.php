<?php
/**
 * Content Analysis - Link Counting & Statistics
 *
 * File: class-content-analysis.php
 * Version: 1.0.0
 *
 * Responsibilities:
 * - Count internal/external links (excluding header/footer/nav)
 * - Calculate content statistics (word count, images, H2s)
 * - Scan post_content, ACF fields, Breakdance content
 * - Save analysis results on post save
 */

if (!defined('ABSPATH')) exit;

class BW_Content_Analysis {

    /**
     * Initialize content analysis
     */
    public static function init() {
        // Re-enabled: Only runs on individual post save, not on admin list
        // This analyzes one post at a time when saved, not all posts at once
        add_action('save_post', [__CLASS__, 'analyze_content'], 20, 3);
        
        // Track post views on frontend
        add_action('wp_head', [__CLASS__, 'track_post_views']);
        
        // Register shortcodes
        add_shortcode('reading_time', [__CLASS__, 'reading_time_shortcode']);
        add_shortcode('post_views', [__CLASS__, 'post_views_shortcode']);
    }

    /**
     * Main analysis function - runs on save_post
     */
    public static function analyze_content($post_id, $post, $update) {
        // Prevent autosave, revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;

        // Only analyze supported post types
        if (!in_array($post->post_type, bw_cs_post_types(), true)) return;

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) return;

        // Only recalculate if content changed
        $last_modified = get_post_meta($post_id, '_bw_last_analyzed', true);
        if ($last_modified === $post->post_modified) {
            return; // Skip - nothing changed
        }

        // 1. Aggregate content from all sources
        $raw_content = self::aggregate_content($post_id, $post);

        // 2. Clean content (remove header/footer/nav)
        $clean_content = self::clean_content($raw_content);

        // 3. Extract and categorize links
        $link_data = self::analyze_links($clean_content, $post_id);

        // 4. Calculate content statistics
        $stats = self::calculate_stats($clean_content);

        // 5. Calculate reading time (based on word count)
        $reading_time = self::calculate_reading_time($stats['word_count']);

        // 6. Save all data
        update_post_meta($post_id, 'bw_internal_link_count', $link_data['internal_count']);
        update_post_meta($post_id, 'bw_external_link_count', $link_data['external_count']);
        update_post_meta($post_id, 'bw_internal_links', $link_data['internal_links']);
        update_post_meta($post_id, 'bw_external_links', $link_data['external_links']);

        update_post_meta($post_id, 'bw_word_count', $stats['word_count']);
        update_post_meta($post_id, 'bw_image_count', $stats['image_count']);
        update_post_meta($post_id, 'bw_h2_count', $stats['h2_count']);
        
        // Reading time fields (NEW - consolidates post_reading_minutes, post_reading_iso)
        update_post_meta($post_id, 'bw_reading_time', $reading_time['minutes']);
        update_post_meta($post_id, 'bw_reading_time_iso', $reading_time['iso']);

        update_post_meta($post_id, '_bw_last_analyzed', $post->post_modified);
    }

    /**
     * Aggregate content from all sources
     */
    private static function aggregate_content($post_id, $post) {
        $content = '';

        // 1. Post content (always present)
        $content .= $post->post_content;

        // 2. ACF fields (if ACF is active)
        if (function_exists('get_fields')) {
            $fields = get_fields($post_id);
            if ($fields) {
                $content .= ' ' . self::extract_acf_content($fields);
            }
        }

        // 3. Breakdance content (if Breakdance is active)
        $bd_content = self::get_breakdance_content($post_id);
        if ($bd_content) {
            $content .= ' ' . $bd_content;
        }

        return $content;
    }

    /**
     * Clean content - remove header/footer/nav elements
     */
    private static function clean_content($html) {
        if (empty($html)) return '';

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // 1. Remove semantic HTML5 tags
        $exclude_tags = apply_filters('bw_content_analysis_exclude_tags', [
            'header',
            'footer',
            'nav'
        ]);

        foreach ($exclude_tags as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            $to_remove = [];
            foreach ($elements as $element) {
                $to_remove[] = $element;
            }
            foreach ($to_remove as $element) {
                if ($element->parentNode) {
                    $element->parentNode->removeChild($element);
                }
            }
        }

        // 2. Remove elements with exclusion classes
        $xpath = new DOMXPath($dom);
        $exclude_classes = apply_filters('bw_content_analysis_exclude_classes', [
            'ga-hrcy-header',
            'ga-hrcy-footer',
            'site-header',
            'site-footer',
            'main-navigation',
            'site-navigation'
        ]);

        foreach ($exclude_classes as $class) {
            $nodes = $xpath->query("//*[contains(@class, '$class')]");
            $to_remove = [];
            foreach ($nodes as $node) {
                $to_remove[] = $node;
            }
            foreach ($to_remove as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        return $dom->saveHTML();
    }

    /**
     * Extract and categorize links
     */
    private static function analyze_links($html, $post_id) {
        if (empty($html)) {
            return [
                'internal_count' => 0,
                'external_count' => 0,
                'internal_links' => [],
                'external_links' => []
            ];
        }

        // Extract all links
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $all_links = [];
        foreach ($dom->getElementsByTagName('a') as $link) {
            $href = $link->getAttribute('href');
            if ($href) {
                $all_links[] = $href;
            }
        }

        // Categorize links
        $internal = [];
        $external = [];
        $site_url = site_url();
        $home_url = home_url();

        $social_domains = apply_filters('bw_content_analysis_social_domains', [
            'facebook.com',
            'linkedin.com',
            'twitter.com',
            'x.com',
            'instagram.com',
            'youtube.com',
            'tiktok.com',
            'pinterest.com'
        ]);

        foreach ($all_links as $url) {
            // Skip empty, anchors, javascript
            if (empty($url) || $url === '#' || strpos($url, 'javascript:') === 0) continue;

            // Skip anchor links
            if (strpos($url, '#') === 0) continue;

            // Skip media files
            if (preg_match('/\.(jpg|jpeg|png|gif|pdf|doc|docx|xls|xlsx|zip)$/i', $url)) continue;

            // Check if social
            $is_social = false;
            foreach ($social_domains as $social) {
                if (strpos($url, $social) !== false) {
                    $is_social = true;
                    break;
                }
            }
            if ($is_social) continue;

            // Normalize URL for comparison
            $normalized_url = $url;

            // Handle protocol-relative URLs
            if (strpos($url, '//') === 0) {
                $normalized_url = 'https:' . $url;
            }

            // Handle relative URLs
            if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
                $normalized_url = $site_url . $url;
                $internal[] = $normalized_url;
                continue;
            }

            // Check if internal or external
            if (strpos($normalized_url, $site_url) === 0 || strpos($normalized_url, $home_url) === 0) {
                $internal[] = $normalized_url;
            } else {
                $external[] = $normalized_url;
            }
        }

        return [
            'internal_count' => count(array_unique($internal)),
            'external_count' => count(array_unique($external)),
            'internal_links' => array_unique($internal),
            'external_links' => array_unique($external)
        ];
    }

    /**
     * Calculate reading time based on word count
     * Average reading speed: 200 words per minute
     *
     * @param int $word_count Word count
     * @return array Reading time data
     */
    private static function calculate_reading_time($word_count) {
        $minutes = max(1, ceil($word_count / 200)); // 200 wpm average
        $iso = 'PT' . $minutes . 'M'; // ISO 8601 duration format
        
        return array(
            'minutes' => $minutes,
            'iso' => $iso,
            'formatted' => $minutes . ' min read'
        );
    }

    /**
     * Calculate content statistics
     */
    private static function calculate_stats($html) {
        if (empty($html)) {
            return [
                'word_count' => 0,
                'image_count' => 0,
                'h2_count' => 0
            ];
        }

        // Word count
        $text = strip_tags($html);
        $text = strip_shortcodes($text);
        $text = preg_replace('/\s+/', ' ', $text); // Normalize whitespace
        $word_count = str_word_count($text);

        // Image and H2 count
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $images = $dom->getElementsByTagName('img');
        $image_count = $images->length;

        $h2s = $dom->getElementsByTagName('h2');
        $h2_count = $h2s->length;

        return [
            'word_count' => $word_count,
            'image_count' => $image_count,
            'h2_count' => $h2_count
        ];
    }

    /**
     * Extract content from ACF fields recursively
     */
    private static function extract_acf_content($fields, $content = '') {
        if (!is_array($fields)) {
            if (is_string($fields)) {
                return $content . ' ' . $fields;
            }
            return $content;
        }

        foreach ($fields as $key => $value) {
            // Skip ACF internal fields starting with underscore
            if (is_string($key) && strpos($key, '_') === 0) {
                continue;
            }

            if (is_string($value)) {
                $content .= ' ' . $value;
            } elseif (is_array($value)) {
                // Recursively process arrays (repeaters, flexible content)
                $content = self::extract_acf_content($value, $content);
            }
        }

        return $content;
    }

    /**
     * Get Breakdance content (if Breakdance is active)
     */
    private static function get_breakdance_content($post_id) {
        // Check if Breakdance is active
        if (!class_exists('Breakdance\PluginBootstrap')) {
            return '';
        }

        // Breakdance typically stores in post meta
        // Common keys: _breakdance_data, breakdance_data, bd_post_content
        $possible_keys = ['_breakdance_data', 'breakdance_data', 'bd_post_content'];

        foreach ($possible_keys as $key) {
            $bd_data = get_post_meta($post_id, $key, true);
            if ($bd_data) {
                return self::parse_breakdance_structure($bd_data);
            }
        }

        // Also check options table
        $bd_data = get_option('breakdance_post_' . $post_id);
        if ($bd_data) {
            return self::parse_breakdance_structure($bd_data);
        }

        return '';
    }

    /**
     * Parse Breakdance structure (implement when Breakdance is installed)
     */
    private static function parse_breakdance_structure($data) {
        // If string, try to decode
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if ($decoded) {
                $data = $decoded;
            } else {
                $unserialized = maybe_unserialize($data);
                if ($unserialized) {
                    $data = $unserialized;
                }
            }
        }

        // If still string, return as is
        if (is_string($data)) {
            return $data;
        }

        // If array, recursively extract text
        if (is_array($data)) {
            $content = '';
            array_walk_recursive($data, function($value) use (&$content) {
                if (is_string($value) && strlen($value) > 0) {
                    $content .= ' ' . $value;
                }
            });
            return $content;
        }

        return '';
    }

    /**
     * Track post views (privacy-friendly, no cookies)
     * Runs on wp_head for singular posts
     * 
     * Replaces old ACF-based post_views_count (KB only)
     * Now works on all post types with consistent bw_ naming
     */
    public static function track_post_views() {
        // Only track on singular posts (not archives, home, admin)
        if (!is_singular() || is_admin()) {
            return;
        }
        
        // Skip AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        // Filter out bots
        if (isset($_SERVER['HTTP_USER_AGENT']) && 
            stripos($_SERVER['HTTP_USER_AGENT'], 'bot') !== false) {
            return;
        }
        
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        
        // Increment view count
        $views = (int) get_post_meta($post_id, 'bw_views_count', true);
        update_post_meta($post_id, 'bw_views_count', $views + 1);
        
        // Track last viewed timestamp
        update_post_meta($post_id, 'bw_last_viewed', current_time('mysql'));
    }
    
    /**
     * Reading time shortcode
     * Usage: [reading_time] or [reading_time format="minutes"]
     */
    public static function reading_time_shortcode($atts) {
        $atts = shortcode_atts([
            'format' => 'full',
        ], $atts, 'reading_time');
        
        $post_id = get_the_ID();
        if (!$post_id) {
            return '';
        }
        
        // Use module data (bw_ prefix)
        $word_count = (int) get_post_meta($post_id, 'bw_word_count', true);
        $reading_time = (int) get_post_meta($post_id, 'bw_reading_time', true);
        $reading_iso = get_post_meta($post_id, 'bw_reading_time_iso', true);
        
        // Fallback: compute if not yet analyzed
        if (!$word_count) {
            $content = get_post_field('post_content', $post_id);
            if ($content) {
                $content = strip_shortcodes($content);
                $content = wp_strip_all_tags($content);
                $word_count = str_word_count($content);
                $reading_time = max(1, ceil($word_count / 200));
                $reading_iso = 'PT' . $reading_time . 'M';
            }
        }
        
        switch (strtolower($atts['format'])) {
            case 'minutes':
                return sprintf('%d min read', $reading_time);
            case 'words':
                return sprintf('%d words', number_format($word_count));
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
    }
    
    /**
     * Post views shortcode
     * Usage: [post_views] or [post_views format="text"]
     */
    public static function post_views_shortcode($atts) {
        $atts = shortcode_atts([
            'format' => 'count',
        ], $atts, 'post_views');
        
        $post_id = get_the_ID();
        if (!$post_id) {
            return '';
        }
        
        $views = (int) get_post_meta($post_id, 'bw_views_count', true);
        
        switch (strtolower($atts['format'])) {
            case 'text':
                return sprintf('%s views', number_format($views));
            case 'count':
            default:
                return number_format($views);
        }
    }
}

// Initialize
BW_Content_Analysis::init();
