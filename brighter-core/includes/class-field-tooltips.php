<?php
/**
 * Field Tooltips - Inline Help
 *
 * File: class-field-tooltips.php
 * Version: 1.0.0
 *
 * Responsibilities:
 * - Add tooltip help icons to dropdown fields
 * - Display tooltips on hover
 * - Tooltip definitions for Intent, Purpose, Maturity
 */

if (!defined('ABSPATH')) exit;

class BW_Field_Tooltips {

    /**
     * Initialize tooltips
     */
    public static function init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Enqueue tooltip assets
     */
    public static function enqueue_assets($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;

        // Enqueue CSS
        wp_add_inline_style('common', self::get_tooltip_css());

        // Enqueue JavaScript
        wp_enqueue_script(
            'bw-field-tooltips',
            BRIGHTER_CORE_URL . 'js/field-tooltips.js',
            ['jquery'],
            BRIGHTER_CORE_VERSION,
            true
        );

        // Localize script with tooltip data
        wp_localize_script('bw-field-tooltips', 'bwTooltips', [
            'intent' => self::get_intent_tooltips(),
            'purpose' => self::get_purpose_tooltips(),
            'maturity' => self::get_maturity_tooltips()
        ]);
    }

    /**
     * Get tooltip CSS
     */
    private static function get_tooltip_css() {
        return '
            .bw-tooltip-icon {
                cursor: help;
                color: #50575e;
                margin-left: 5px;
                font-size: 16px;
                vertical-align: middle;
            }

            .bw-tooltip-icon:hover {
                color: #2271b1;
            }

            .bw-tooltip-popup {
                position: absolute;
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 12px;
                max-width: 350px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                z-index: 999999;
                font-size: 13px;
                line-height: 1.5;
                display: none;
            }

            .bw-tooltip-popup::before {
                content: "";
                position: absolute;
                top: -6px;
                left: 15px;
                width: 0;
                height: 0;
                border-left: 6px solid transparent;
                border-right: 6px solid transparent;
                border-bottom: 6px solid #c3c4c7;
            }

            .bw-tooltip-popup::after {
                content: "";
                position: absolute;
                top: -5px;
                left: 16px;
                width: 0;
                height: 0;
                border-left: 5px solid transparent;
                border-right: 5px solid transparent;
                border-bottom: 5px solid #fff;
            }

            .bw-tooltip-popup strong {
                display: block;
                margin-bottom: 6px;
                color: #1d2327;
                font-size: 14px;
            }
        ';
    }

    /**
     * Get Intent tooltips
     */
    private static function get_intent_tooltips() {
        return [
            '' => 'Not applicable or not yet determined',
            'informational' => 'User seeking knowledge or answers to questions',
            'commercial' => 'User researching products/services before making a purchase decision',
            'transactional' => 'User ready to take action (buy, book, sign up, contact)',
            'navigational' => 'User looking for a specific page, brand, or website',
            'retention' => 'Content for existing customers/clients to keep them engaged',
            'support' => 'Help documentation, troubleshooting, FAQs',
            'eeat-trust' => 'Authority and credibility building content (Experience, Expertise, Authoritativeness, Trust)',
            'system-functional' => 'Internal system pages (login, dashboard, account management)',
            'informational-problem' => 'User identifying or understanding a problem they\'re experiencing',
            'informational-solution' => 'User looking for solutions to a known problem',
            'commercial-decision-support' => 'Content helping users make informed purchase decisions (comparisons, reviews, guides)'
        ];
    }

    /**
     * Get Purpose tooltips
     */
    private static function get_purpose_tooltips() {
        return [
            '' => 'Not applicable or not yet determined',
            'pillar' => 'High-level topic hub that links to supporting content and serves as the main authority page for a subject',
            'service-page' => 'Page detailing a specific service your business offers',
            'product-page' => 'Page detailing a specific product, model, or package',
            'supporting' => 'Standard blog article or content piece that supports a pillar topic',
            'case-study' => 'Real-world example demonstrating results, process, or client success story',
            'conversion-hub' => 'Page specifically designed to drive a conversion action (quote request, booking, purchase)',
            'resource-guide' => 'Comprehensive how-to content, downloadable resource, checklist, or toolkit',
            'authority-page' => 'Deep expertise content establishing thought leadership and industry authority',
            'location-page' => 'Geographic or location-specific content (city pages, service areas)',
            'industry-page' => 'Sector or industry-specific content targeting a particular vertical',
            'landing-page' => 'Dedicated page for paid traffic (PPC, ads, campaigns) with focused conversion goal',
            'legal-terms' => 'Terms of service, privacy policy, disclaimers, and other legal documentation'
        ];
    }

    /**
     * Get Maturity tooltips
     */
    private static function get_maturity_tooltips() {
        return [
            '' => 'Maturity level not yet determined',
            'entry' => 'Beginner-friendly content that assumes no prior knowledge. Explains basics and foundational concepts.',
            'learner' => 'For those with basic understanding who are building their skills and knowledge in the subject.',
            'professional' => 'For practitioners with working knowledge. Goes beyond basics with practical applications.',
            'expert' => 'Advanced content for specialists in the field. Assumes deep existing knowledge.',
            'thought-leader' => 'Original insights and industry-shaping perspectives. Challenges conventions or introduces new frameworks.',
            'industry-authority' => 'Definitive, comprehensive, reference-level content. The go-to resource others cite and link to.'
        ];
    }

    /**
     * Render field with tooltip icon
     */
    public static function render_field_with_tooltip($field_id, $field_name, $label, $current_value, $options, $tooltip_type) {
        ?>
        <div class="bw-cs-field">
            <label for="<?php echo esc_attr($field_id); ?>">
                <?php echo esc_html($label); ?>
                <span class="dashicons dashicons-editor-help bw-tooltip-icon"
                      data-tooltip-type="<?php echo esc_attr($tooltip_type); ?>"
                      data-current-value="<?php echo esc_attr($current_value); ?>"></span>
            </label>
            <select id="<?php echo esc_attr($field_id); ?>"
                    name="<?php echo esc_attr($field_name); ?>"
                    class="bw-field-with-tooltip"
                    data-tooltip-type="<?php echo esc_attr($tooltip_type); ?>"
                    style="width:100%;">
                <?php foreach ($options as $value => $option_label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($current_value, $value); ?>>
                        <?php echo esc_html($option_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }
}

// Initialize
BW_Field_Tooltips::init();
