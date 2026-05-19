<?php
/**
 * Content Analysis - Link Counting & Statistics
 *
 * File: class-content-analysis.php
 * Version: 1.2.0 | 2026-05-19
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
        add_action('save_post', [__CLASS__, 'analyze_content'], 20, 3);

        // Breakdance Builder writes _breakdance_data via its own REST API, which may
        // run after save_post fires or may not trigger save_post at all. Hook onto the
        // meta write so analysis always runs with the freshly committed BD content.
        add_action('updated_post_meta', [__CLASS__, 'on_breakdance_data_saved'], 10, 3);
        add_action('added_post_meta',   [__CLASS__, 'on_breakdance_data_saved'], 10, 3);

        // Track post views on frontend
        add_action('wp_head', [__CLASS__, 'track_post_views']);
        
        // Register post_views shortcode (reading_time is in reading-time-shortcode.php)
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
     * Get aggregated content for a post (post_content + ACF + Breakdance).
     *
     * Same source as Content Analysis uses for word count and stats. Use this when you need
     * "full" content including builder/meta content (e.g. Make prompt-data, exports).
     * Refactor: move to central helper (Option 2) when content is refactored into new modules.
     *
     * @param int $post_id Post ID
     * @return string Raw aggregated HTML/text (not sanitized for display)
     */
    public static function get_aggregated_content($post_id) {
        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, bw_cs_post_types(), true)) {
            return '';
        }
        return self::aggregate_content($post_id, $post);
    }

    /**
     * Aggregate content from all sources.
     * When _breakdance_data is present and non-empty, use it as the primary content (Breakdance overwrites editor content).
     * When empty, use post_content. ACF is always appended when available.
     */
    private static function aggregate_content($post_id, $post) {
        $bd_content = self::get_breakdance_content($post_id);

        if ($bd_content !== '') {
            // Breakdance data exists: use it as primary content (do not double-count with post_content)
            $content = $bd_content;
        } else {
            $content = $post->post_content;
        }

        // ACF fields (if ACF is active)
        if (function_exists('get_fields')) {
            $fields = get_fields($post_id);
            if ($fields) {
                $content .= ' ' . self::extract_acf_content($fields);
            }
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
     * Get Breakdance content from post meta _breakdance_data.
     * When present and non-empty, returns HTML extracted from the tree_json_string (headings, text, rich text, etc.).
     * Does not require Breakdance plugin class so it works in all contexts (save_post, REST, cron).
     */
    private static function get_breakdance_content($post_id) {
        $bd_data = get_post_meta($post_id, '_breakdance_data', true);
        if (empty($bd_data) || !is_string($bd_data)) {
            $bd_data = get_post_meta($post_id, 'breakdance_data', true);
        }
        if (empty($bd_data) || !is_string($bd_data)) {
            return '';
        }
        return self::parse_breakdance_structure($bd_data);
    }

    /**
     * Parse Breakdance _breakdance_data: outer JSON has tree_json_string containing inner JSON tree.
     * Walks the tree and extracts readable text/HTML from Heading, Text, RichText, Button, Badge, FancyTestimonial, etc.
     * Returns HTML so calculate_stats (word count, h2 count) and link analysis work.
     */
    private static function parse_breakdance_structure($data) {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (!is_array($decoded)) {
                return '';
            }
            $data = $decoded;
        }

        // Breakdance stores tree in tree_json_string (inner JSON as string)
        $tree_json = isset($data['tree_json_string']) ? $data['tree_json_string'] : null;
        if (empty($tree_json) || !is_string($tree_json)) {
            return '';
        }

        $tree = json_decode($tree_json, true);
        if (!is_array($tree) || empty($tree['root'])) {
            return '';
        }

        return self::extract_breakdance_tree_to_html($tree['root']);
    }

    /**
     * Recursively walk Breakdance tree and build HTML from node content.
     *
     * Handles:
     *  - Heading    → content.content.tags + text           → <h1>…<h6>
     *  - RichText   → content.content.text (HTML)           → raw HTML
     *  - TextLink   → content.content.link.url + text       → <a href>
     *  - Button     → content.content.link.url              → <a href>
     *  - Text/Badge → content.content.text (plain)          → <p>
     *  - Image/Image2 → content.image (no URL at parse time) → <img> placeholder
     *  - FancyTestimonial → content.content.testimonial/title/name/occupation → <p>
     */
    private static function extract_breakdance_tree_to_html($node) {
        if (!is_array($node)) {
            return '';
        }

        $html    = '';
        $data    = isset($node['data']) ? $node['data'] : [];
        $props   = isset($data['properties']) ? $data['properties'] : [];
        $content = isset($props['content']) ? $props['content'] : [];
        $inner   = isset($content['content']) ? $content['content'] : [];

        // ── Image / Image2 ──────────────────────────────────────────────────
        // Breakdance stores image config under content.image (URL is resolved at
        // render time from the media library — not available in raw meta). Output
        // a placeholder <img> so image_count is correctly incremented.
        if (!empty($content['image']) && is_array($content['image'])) {
            $alt  = isset($content['image']['alt']) && is_string($content['image']['alt'])
                ? $content['image']['alt'] : '';
            $html .= '<img src="" alt="' . esc_attr($alt) . '">';
        }

        // ── Text-based content from content.content ─────────────────────────
        if (!empty($inner) && is_array($inner)) {

            // Heading: content.content.tags (h1–h6) + text
            if (isset($inner['tags']) && is_string($inner['tags'])
                && isset($inner['text']) && is_string($inner['text'])) {
                $tag  = preg_match('/^h[1-6]$/i', $inner['tags']) ? strtolower($inner['tags']) : 'h2';
                $text = trim($inner['text']);
                if ($text !== '') {
                    $html .= '<' . $tag . '>' . esc_html($text) . '</' . $tag . '>';
                }

            // RichText: text contains HTML markup
            } elseif (isset($inner['text']) && is_string($inner['text'])
                      && strpos($inner['text'], '<') !== false) {
                $html .= wp_kses_post($inner['text']);

            // TextLink / Button: has a link URL (with or without display text)
            } elseif (isset($inner['link']['url']) && is_string($inner['link']['url'])) {
                $link_text = isset($inner['text']) && is_string($inner['text'])
                    ? trim(strip_tags($inner['text'])) : '';
                $anchor = $link_text !== '' ? $link_text : $inner['link']['url'];
                $html  .= '<a href="' . esc_url($inner['link']['url']) . '">'
                    . esc_html($anchor) . '</a>';

            // Plain text: Text, Badge, etc.
            } elseif (isset($inner['text']) && is_string($inner['text'])) {
                $text = trim(strip_tags($inner['text']));
                if ($text !== '') {
                    $html .= '<p>' . esc_html($text) . '</p>';
                }
            }

            // FancyTestimonial fields (run after main paths, can coexist)
            foreach (['testimonial', 'title', 'name', 'occupation'] as $key) {
                if (isset($inner[$key]) && is_string($inner[$key]) && trim($inner[$key]) !== '') {
                    $html .= '<p>' . esc_html(trim($inner[$key])) . '</p>';
                }
            }
        }

        // Recurse into children
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                $html .= self::extract_breakdance_tree_to_html($child);
            }
        }

        return $html;
    }

    /**
     * Re-analyze a post whenever Breakdance writes new builder data.
     *
     * Mirrors the same hook in Content_Analysis.php (CA module). Both must be updated
     * together so bw_* and scos_ca_* keys stay in sync after a Breakdance builder save.
     *
     * @param int    $meta_id  Unused.
     * @param int    $post_id  Post being saved.
     * @param string $meta_key Meta key just written.
     */
    public static function on_breakdance_data_saved($meta_id, $post_id, $meta_key) {
        if ('_breakdance_data' !== $meta_key) {
            return;
        }
        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, bw_cs_post_types(), true)) {
            return;
        }
        // Clear the timestamp so the skip-condition doesn't block this run.
        delete_post_meta($post_id, '_bw_last_analyzed');
        self::analyze_content($post_id, $post, true);
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
