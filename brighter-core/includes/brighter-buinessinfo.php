<?php
/**
 * Brighter Tools: Business Info
 *
 * File: brighter-buinessinfo.php
 * Version: 4.1.0
 *
 * Changelog:
 * 4.1.0 - Performance optimization: Added caching, batch option loading, reduced queries
 * 4.0.0 - Added proper option prefixing, fixed encoding issues, optimized queries
 */

if (!defined('ABSPATH')) exit;

/**
 * Option prefix to prevent conflicts with other plugins
 */
define('BRIGHTER_OPTION_PREFIX', 'brighter_');

/**
 * Cache group for business info
 */
define('BRIGHTER_CACHE_GROUP', 'brighter_business_info');

/**
 * Cache duration (1 hour)
 */
define('BRIGHTER_CACHE_DURATION', HOUR_IN_SECONDS);

/**
 * Static cache for runtime
 */
class Brighter_Business_Cache {
    private static $runtime_cache = null;
    
    /**
     * Get all business info in one query (cached)
     */
    public static function get_all() {
        // Check runtime cache first
        if (self::$runtime_cache !== null) {
            return self::$runtime_cache;
        }
        
        // Check object cache
        $cached = wp_cache_get('all_business_info', BRIGHTER_CACHE_GROUP);
        if ($cached !== false) {
            self::$runtime_cache = $cached;
            return $cached;
        }
        
        // Load from database (single query)
        global $wpdb;
        $fields = brighter_get_business_info_fields();
        $option_names = array_map(function($field) {
            return BRIGHTER_OPTION_PREFIX . $field;
        }, $fields);
        
        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($option_names), '%s'));
        
        $query = $wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($placeholders)",
            $option_names
        );
        
        $results = $wpdb->get_results($query);
        
        // Build associative array
        $data = [];
        foreach ($results as $row) {
            // Remove prefix for cleaner keys
            $key = str_replace(BRIGHTER_OPTION_PREFIX, '', $row->option_name);
            $data[$key] = maybe_unserialize($row->option_value);
        }
        
        // Cache it
        wp_cache_set('all_business_info', $data, BRIGHTER_CACHE_GROUP, BRIGHTER_CACHE_DURATION);
        self::$runtime_cache = $data;
        
        return $data;
    }
    
    /**
     * Clear all caches
     */
    public static function clear() {
        wp_cache_delete('all_business_info', BRIGHTER_CACHE_GROUP);
        self::$runtime_cache = null;
    }
}

/**
 * Get all business info field keys
 */
function brighter_get_business_info_fields() {
    static $fields = null;
    
    if ($fields === null) {
        $fields = [
            // Info
            'business_name', 'contact_name', 'abn', 'organisation_type', 'service_description',
            // Contact
            'phone_number', 'email', 'address', 'city', 'state', 'postcode', 'country', 'lat', 'long',
            // Hours
            'business_hours',
            // Social
            'social_link_facebook', 'social_link_twitter', 'social_link_instagram',
            'social_link_youtube', 'social_link_linkedin', 'social_link_google_review',
            // Service
            'area_served', 'provider_mobility', 'price_tier'
        ];
    }
    
    return $fields;
}

/**
 * Get a business info option with caching
 */
function brighter_get_option($key, $default = '') {
    $all_data = Brighter_Business_Cache::get_all();
    return isset($all_data[$key]) ? $all_data[$key] : $default;
}

/**
 * Update a business info option and clear cache
 */
function brighter_update_option($key, $value) {
    $result = update_option(BRIGHTER_OPTION_PREFIX . $key, $value);
    if ($result) {
        Brighter_Business_Cache::clear();
    }
    return $result;
}

/**
 * Register all business info settings
 */
function brighterweb_register_business_info_settings() {
    $fields = brighter_get_business_info_fields();
    
    // Register all fields with proper prefix
    foreach ($fields as $field) {
        register_setting('brighterweb_business_info_group', BRIGHTER_OPTION_PREFIX . $field, [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
    }

    // Add UI sections
    add_settings_section(
        'brighterweb_info_section',
        'Business Information',
        'brighterweb_info_section_callback',
        'brighterweb_business_info_page'
    );
    
    add_settings_section(
        'brighterweb_contact_section',
        'Business Contact Info',
        'brighterweb_contact_section_callback',
        'brighterweb_business_info_page'
    );
    
    add_settings_section(
        'brighterweb_social_section',
        'Social Media Links',
        function() {
            echo '<p class="business-info-p">Paste full URLs to your social profiles.</p>';
        },
        'brighterweb_business_info_page'
    );
    
    add_settings_section(
        'brighterweb_service_section',
        'Service Details',
        function() {
            echo '<p class="business-info-p">Define your service area, mobility and pricing tier.</p>';
        },
        'brighterweb_business_info_page'
    );

    // Info fields
    $info_fields = ['business_name', 'contact_name', 'abn', 'business_hours'];
    foreach ($info_fields as $field) {
        add_settings_field(
            $field, 
            ucwords(str_replace('_', ' ', $field)), 
            'brighterweb_field_callback', 
            'brighterweb_business_info_page', 
            'brighterweb_info_section', 
            ['id' => BRIGHTER_OPTION_PREFIX . $field]
        );
    }

    // Contact fields
    $contact_fields = ['phone_number', 'email', 'address', 'city', 'state', 'postcode', 'country', 'lat', 'long'];
    foreach ($contact_fields as $field) {
        add_settings_field(
            $field, 
            ucwords(str_replace('_', ' ', $field)), 
            'brighterweb_field_callback', 
            'brighterweb_business_info_page', 
            'brighterweb_contact_section', 
            ['id' => BRIGHTER_OPTION_PREFIX . $field]
        );
    }

    // Social fields
    $social_fields = ['facebook', 'twitter', 'instagram', 'youtube', 'linkedin', 'google_review'];
    foreach ($social_fields as $social) {
        $id = BRIGHTER_OPTION_PREFIX . "social_link_{$social}";
        add_settings_field(
            $id, 
            ucfirst($social), 
            'brighterweb_field_callback', 
            'brighterweb_business_info_page', 
            'brighterweb_social_section', 
            ['id' => $id]
        );
    }

    // Dropdown/textarea fields
    add_settings_field(
        'organisation_type', 
        'Organisation Type', 
        'brighterweb_select_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_info_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'organisation_type',
            'options' => ['Local Business', 'Organization', 'Person']
        ]
    );
    
    add_settings_field(
        'service_description', 
        'Service Description', 
        'brighterweb_textarea_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_info_section', 
        ['id' => BRIGHTER_OPTION_PREFIX . 'service_description']
    );
    
    add_settings_field(
        'provider_mobility', 
        'Provider Mobility', 
        'brighterweb_dropdown_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_service_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'provider_mobility',
            'options' => ['static' => 'Static', 'dynamic' => 'Dynamic']
        ]
    );
    
    add_settings_field(
        'price_tier', 
        'Price Tier', 
        'brighterweb_dropdown_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_service_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'price_tier',
            'options' => ['$', '$$', '$$$', '$$$$']
        ]
    );
}
add_action('admin_init', 'brighterweb_register_business_info_settings');

/**
 * Clear cache when any business info option is saved
 */
add_action('update_option', function($option_name) {
    if (strpos($option_name, BRIGHTER_OPTION_PREFIX) === 0) {
        Brighter_Business_Cache::clear();
    }
}, 10, 1);

/**
 * Section callbacks
 */
function brighterweb_info_section_callback() {
    echo '<p class="business-info-p">Please enter your general business information below. This information is used to populate your website\'s privacy policy and to generate <a href="https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data" target="_blank" rel="noopener noreferrer">schema markup</a> to help search engines understand and display your business more effectively in search results.</p>';
}

function brighterweb_contact_section_callback() {
    echo '<p class="business-info-p">Enter your contact and location information below.</p>';
}

/**
 * Universal field callback renderer
 */
function brighterweb_field_callback($args) {
    $value = get_option($args['id']);
    echo "<input type='text' name='{$args['id']}' value='" . esc_attr($value) . "' />";
    if (!empty($args['note'])) {
        echo "<p class='description'>{$args['note']}</p>";
    }
}

/**
 * Dropdown callback
 */
function brighterweb_dropdown_callback($args) {
    $value = get_option($args['id']);
    echo "<select name='{$args['id']}'>";
    foreach ($args['options'] as $key => $label) {
        $selected = selected($value, $key, false);
        echo "<option value='{$key}' {$selected}>{$label}</option>";
    }
    echo "</select>";
}

/**
 * Select field callback
 */
function brighterweb_select_field_callback($args) {
    $id = $args['id'];
    $value = get_option($id);
    $options = $args['options'] ?? ['Local Business', 'Organization', 'Personal'];

    echo '<select name="' . esc_attr($id) . '" id="' . esc_attr($id) . '">';
    foreach ($options as $key => $label) {
        $val = is_int($key) ? $label : $key;
        $selected = selected($value, $val, false);
        echo '<option value="' . esc_attr($val) . '"' . $selected . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
}

/**
 * Textarea callback
 */
function brighterweb_textarea_callback($args) {
    $id = $args['id'];
    $value = get_option($id);
    echo '<textarea name="' . esc_attr($id) . '" id="' . esc_attr($id) . '" rows="4" cols="50">' . esc_textarea($value) . '</textarea>';
}

/**
 * Render the business information form
 */
function brighterweb_render_business_info_form() {
    echo '<form method="post" action="options.php" class="business_info_style">';
    settings_fields('brighterweb_business_info_group');
    do_settings_sections('brighterweb_business_info_page');
    submit_button('Save Business Information');
    echo '</form>';
}

/**
 * Shortcode to output business info (cached)
 * Usage: [business_info setting="phone_number"]
 */
function brighterweb_business_info_shortcode($atts) {
    static $valid_keys = null;
    
    if ($valid_keys === null) {
        $valid_keys = brighter_get_business_info_fields();
    }
    
    $atts = shortcode_atts(['setting' => ''], $atts);

    if (in_array($atts['setting'], $valid_keys)) {
        return esc_html(brighter_get_option($atts['setting']));
    } else {
        return 'Invalid or missing setting attribute.';
    }
}
add_shortcode('business_info', 'brighterweb_business_info_shortcode');

/**
 * Copyright notice shortcode (cached)
 * Usage: [site_copyright]
 */
function brighterwebsites_copyright_notice() {
    static $cached_output = null;
    
    if ($cached_output !== null) {
        return $cached_output;
    }
    
    $year = date('Y');
    $site_title = get_bloginfo('name');
    $abn = brighter_get_option('abn');

    $abn_string = $abn ? "ABN {$abn}. " : '';

    $cached_output = "&copy; {$year} {$site_title}. {$abn_string}All rights reserved.";
    return $cached_output;
}
add_shortcode('site_copyright', 'brighterwebsites_copyright_notice');