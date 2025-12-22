<?php
/**
 * ALTC GA4 Integration
 *
 * File: class-altc-ga4-integration.php
 * Version: 1.0.0
 *
 * Responsibilities:
 * - Add ALTC parameters to existing GA4 tracking
 * - Inject altc_primary, altc_topic, content_maturity to page view events
 * - Ensure content_purpose is sent (from existing bw_purpose field)
 */

if (!defined('ABSPATH')) exit;

class BW_ALTC_GA4_Integration {

    /**
     * Initialize GA4 integration
     */
    public static function init() {
        // Hook into wp_head to add ALTC parameters
        // Priority 6 to run after the existing content strategy injection (priority 5)
        add_action('wp_head', [__CLASS__, 'inject_altc_params'], 6);
    }

    /**
     * Inject ALTC parameters into the brighterContentStrategy object
     */
    public static function inject_altc_params() {
        // Only run on singular posts/pages
        if (!is_singular()) {
            return;
        }

        $post_id = get_the_ID();

        // Get ALTC metadata
        $altc_id = get_post_meta($post_id, 'bw_primary_altc_id', true);
        $topic_id = get_post_meta($post_id, 'bw_primary_topic_id', true);
        $maturity = get_post_meta($post_id, 'bw_cont_maturity', true);

        // Get ALTC name
        $altc_name = '';
        if ($altc_id) {
            $altc_term = get_term($altc_id, 'altc_strategic_lens');
            if ($altc_term && !is_wp_error($altc_term)) {
                $altc_name = $altc_term->name;
            }
        }

        // Get topic name
        $topic_name = '';
        if ($topic_id) {
            $topic_term = get_term($topic_id, 'altc_topic');
            if ($topic_term && !is_wp_error($topic_term)) {
                $topic_name = $topic_term->name;
            }
        }

        // Fallback to old bw_page_topic if topic_name is empty
        if (empty($topic_name)) {
            $topic_name = get_post_meta($post_id, 'bw_page_topic', true);
        }

        // Set defaults for missing values
        $altc_name = $altc_name ?: 'not_set';
        $topic_name = $topic_name ?: 'not_set';
        $maturity = $maturity ?: 'not_set';

        // Output JavaScript to extend brighterContentStrategy object
        ?>
        <script>
        // Extend existing brighterContentStrategy object with ALTC parameters
        if (typeof window.brighterContentStrategy === 'undefined') {
            window.brighterContentStrategy = {};
        }

        // Add ALTC parameters
        window.brighterContentStrategy.altc_primary = <?php echo json_encode($altc_name); ?>;
        window.brighterContentStrategy.altc_topic = <?php echo json_encode($topic_name); ?>;
        window.brighterContentStrategy.content_maturity = <?php echo json_encode($maturity); ?>;

        // Ensure content_purpose is set (should already be set by existing script, but ensure it's there)
        if (!window.brighterContentStrategy.content_purpose) {
            window.brighterContentStrategy.content_purpose = <?php echo json_encode(get_post_meta($post_id, 'bw_purpose', true) ?: 'not_set'); ?>;
        }
        </script>
        <?php
    }
}

// Initialize
BW_ALTC_GA4_Integration::init();
