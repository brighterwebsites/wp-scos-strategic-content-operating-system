<?php
/**
 * Brighter Tools: Business Info
 *
 * File: brighter-buinessinfo.php
 * Version: 4.2.0
 *
 * Changelog:
 * 4.2.0 - SECURITY: Input sanitization, SQL injection prevention, XSS protection, capability checks
 * 4.1.0 - Performance optimization: Added caching, batch option loading, reduced queries
 * 4.0.0 - Added proper option prefixing, fixed encoding issues, optimized queries
 */

if (!defined('ABSPATH')) exit;

/**
 * Option prefix for business info options (e.g. bw_business_name, bw_phone_number, bw_email).
 * Used for privacy policy and schema; only a small number of sites use this.
 */
define('BRIGHTER_OPTION_PREFIX', 'bw_');

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
 * 
 * SECURITY: Cache validation added
 */
class Brighter_Business_Cache {
    private static $runtime_cache = null;
    
    /**
     * Validate cached data structure
     * 
     * SECURITY: Ensures cache hasn't been corrupted
     */
    private static function validate_cache_data($data) {
        if (!is_array($data)) {
            return false;
        }
        
        // Check if it looks like business info data
        $expected_keys = ['business_name', 'phone_number', 'email'];
        $has_expected = false;
        
        foreach ($expected_keys as $key) {
            if (array_key_exists($key, $data)) {
                $has_expected = true;
                break;
            }
        }
        
        return $has_expected;
    }
    
    /**
     * Get all business info in one query (cached)
     * 
     * SECURITY: SQL injection prevention with whitelist validation
     */
    public static function get_all() {
        // Check runtime cache first
        if (self::$runtime_cache !== null) {
            return self::$runtime_cache;
        }
        
        // Check object cache
        $cached = wp_cache_get('all_business_info', BRIGHTER_CACHE_GROUP);
        if ($cached !== false && self::validate_cache_data($cached)) {
            self::$runtime_cache = $cached;
            return $cached;
        }
        
        // Load from database (single query)
        global $wpdb;
        $fields = brighter_get_business_info_fields();
        
        // SECURITY: Validate all field names before building query
        $validated_fields = array_filter($fields, function($field) {
            return preg_match('/^[a-z_]+$/', $field);
        });
        
        if (empty($validated_fields)) {
            return [];
        }
        
        $option_names = array_map(function($field) {
            return BRIGHTER_OPTION_PREFIX . sanitize_key($field);
        }, $validated_fields);
        
        // SECURITY: Build safe placeholders
        $placeholders = implode(',', array_fill(0, count($option_names), '%s'));
        
        // SECURITY: Use prepared statement
        $query = $wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($placeholders)",
            $option_names
        );
        
        $results = $wpdb->get_results($query);
        
        if (!$results) {
            return [];
        }
        
        // Build associative array
        $data = [];
        foreach ($results as $row) {
            // Remove prefix for cleaner keys
            $key = str_replace(BRIGHTER_OPTION_PREFIX, '', $row->option_name);
            $data[$key] = maybe_unserialize($row->option_value);
        }
        
        // SECURITY: Validate before caching
        if (self::validate_cache_data($data)) {
            wp_cache_set('all_business_info', $data, BRIGHTER_CACHE_GROUP, BRIGHTER_CACHE_DURATION);
            self::$runtime_cache = $data;
        }
        
        return $data;
    }
    
    /**
     * Clear all caches
     * 
     * SECURITY: Only admins can clear
     */
    public static function clear() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        wp_cache_delete('all_business_info', BRIGHTER_CACHE_GROUP);
        self::$runtime_cache = null;
        return true;
    }
}

/**
 * Get all business info field keys
 * 
 * SECURITY: Whitelist of allowed fields
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
 * 
 * SECURITY: Key validation before retrieval
 */
function brighter_get_option($key, $default = '') {
    // SECURITY: Validate key is in whitelist
    $valid_keys = brighter_get_business_info_fields();
    if (!in_array($key, $valid_keys, true)) {
        return $default;
    }
    
    $all_data = Brighter_Business_Cache::get_all();
    return isset($all_data[$key]) ? $all_data[$key] : $default;
}

/**
 * Update a business info option and clear cache
 * 
 * SECURITY: Capability check and input validation
 */
function brighter_update_option($key, $value) {
    // SECURITY: Only admins can update
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    // SECURITY: Validate key is in whitelist
    $valid_keys = brighter_get_business_info_fields();
    if (!in_array($key, $valid_keys, true)) {
        return false;
    }
    
    // SECURITY: Sanitize based on field type
    $value = brighter_sanitize_business_field($key, $value);
    
    $result = update_option(BRIGHTER_OPTION_PREFIX . $key, $value);
    if ($result) {
        Brighter_Business_Cache::clear();
    }
    return $result;
}

/**
 * Sanitize business field based on type
 * 
 * SECURITY: Field-specific sanitization
 */
function brighter_sanitize_business_field($key, $value) {
    // Email fields
    if ($key === 'email') {
        return sanitize_email($value);
    }
    
    // URL fields
    if (strpos($key, 'social_link_') === 0) {
        return esc_url_raw($value);
    }
    
    // Textarea fields
    if (in_array($key, ['service_description', 'business_hours'], true)) {
        return sanitize_textarea_field($value);
    }
    
    // Numeric fields
    if (in_array($key, ['lat', 'long'], true)) {
        return sanitize_text_field($value); // Keep as text to preserve decimals
    }
    
    // Default: text field
    return sanitize_text_field($value);
}

/**
 * Register all business info settings
 * 
 * SECURITY: Enhanced sanitization callbacks
 */
function brighterweb_register_business_info_settings() {
    $fields = brighter_get_business_info_fields();
    
    // Register all fields with proper prefix and sanitization
    foreach ($fields as $field) {
        register_setting('brighterweb_business_info_group', BRIGHTER_OPTION_PREFIX . $field, [
            'sanitize_callback' => function($value) use ($field) {
                return brighter_sanitize_business_field($field, $value);
            },
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
            ['id' => BRIGHTER_OPTION_PREFIX . $field, 'type' => 'text']
        );
    }

    // Contact fields
    $contact_fields = ['phone_number', 'email', 'address', 'city', 'state', 'postcode', 'country', 'lat', 'long'];
    foreach ($contact_fields as $field) {
        $type = ($field === 'email') ? 'email' : 'text';
        add_settings_field(
            $field, 
            ucwords(str_replace('_', ' ', $field)), 
            'brighterweb_field_callback', 
            'brighterweb_business_info_page', 
            'brighterweb_contact_section', 
            ['id' => BRIGHTER_OPTION_PREFIX . $field, 'type' => $type]
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
            ['id' => $id, 'type' => 'url']
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
 * 
 * SECURITY: All output properly escaped
 */
function brighterweb_info_section_callback() {
    echo '<p class="business-info-p">Please enter your general business information below. This information is used to populate your website\'s privacy policy and to generate <a href="https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data" target="_blank" rel="noopener noreferrer">schema markup</a> to help search engines understand and display your business more effectively in search results.</p>';
}

function brighterweb_contact_section_callback() {
    echo '<p class="business-info-p">Enter your contact and location information below.</p>';
}

/**
 * Universal field callback renderer
 * 
 * SECURITY: Enhanced with input type validation and escaping
 */
function brighterweb_field_callback($args) {
    $value = get_option($args['id'], '');
    $type = isset($args['type']) ? $args['type'] : 'text';
    
    // SECURITY: Validate type
    $allowed_types = ['text', 'email', 'url', 'tel', 'number'];
    if (!in_array($type, $allowed_types, true)) {
        $type = 'text';
    }
    
    echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($args['id']) . '" value="' . esc_attr($value) . '" class="regular-text" />';
    
    if (!empty($args['note'])) {
        echo '<p class="description">' . esc_html($args['note']) . '</p>';
    }
}

/**
 * Dropdown callback
 * 
 * SECURITY: All output escaped
 */
function brighterweb_dropdown_callback($args) {
    $value = get_option($args['id'], '');
    echo '<select name="' . esc_attr($args['id']) . '">';
    
    foreach ($args['options'] as $key => $label) {
        $selected = selected($value, $key, false);
        echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
}

/**
 * Select field callback
 * 
 * SECURITY: All output escaped
 */
function brighterweb_select_field_callback($args) {
    $id = $args['id'];
    $value = get_option($id, '');
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
 * 
 * SECURITY: Proper textarea escaping
 */
function brighterweb_textarea_callback($args) {
    $id = $args['id'];
    $value = get_option($id, '');
    echo '<textarea name="' . esc_attr($id) . '" id="' . esc_attr($id) . '" rows="4" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
}

/**
 * Render the business information form
 * 
 * SECURITY: Nonce and capability check implicit via Settings API
 */
function brighterweb_render_business_info_form() {
    // SECURITY: Additional capability check
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'brighterwebsites'));
    }
    
    echo '<form method="post" action="options.php" class="business_info_style">';
    settings_fields('brighterweb_business_info_group');
    do_settings_sections('brighterweb_business_info_page');
    submit_button('Save Business Information');
    echo '</form>';
}

/**
 * Shortcode to output business info (cached)
 * Usage: [business_info setting="phone_number"]
 * 
 * SECURITY: Input validation and output escaping
 */
function brighterweb_business_info_shortcode($atts) {
    static $valid_keys = null;
    
    if ($valid_keys === null) {
        $valid_keys = brighter_get_business_info_fields();
    }
    
    // SECURITY: Sanitize shortcode attributes
    $atts = shortcode_atts(['setting' => ''], $atts);
    $setting = sanitize_key($atts['setting']);
    
    // SECURITY: Validate against whitelist
    if (!in_array($setting, $valid_keys, true)) {
        return ''; // Return empty string instead of error message (don't expose info)
    }
    
    $value = brighter_get_option($setting);
    
    // SECURITY: Context-aware escaping
    if (strpos($setting, 'social_link_') === 0) {
        return esc_url($value);
    } elseif ($setting === 'email') {
        return is_email($value) ? sanitize_email($value) : '';
    } else {
        return esc_html($value);
    }
}
add_shortcode('business_info', 'brighterweb_business_info_shortcode');

/**
 * Copyright notice shortcode (cached)
 * Usage: [site_copyright]
 * 
 * SECURITY: All output escaped
 */
function brighterwebsites_copyright_notice() {
    static $cached_output = null;
    
    if ($cached_output !== null) {
        return $cached_output;
    }
    
    $year = absint(date('Y'));
    $site_title = get_bloginfo('name');
    $abn = brighter_get_option('abn');

    // SECURITY: Sanitize ABN (should be numeric)
    $abn = preg_replace('/[^0-9\s]/', '', $abn);
    $abn_string = $abn ? 'ABN ' . esc_html($abn) . '. ' : '';

    $cached_output = '&copy; ' . esc_html($year) . ' ' . esc_html($site_title) . '. ' . $abn_string . 'All rights reserved.';
    return $cached_output;
}
add_shortcode('site_copyright', 'brighterwebsites_copyright_notice');

/**
 * SEOPress Schema Mapping
 * Maps Brighter Business Info fields to SEOPress schema placeholders
 *
 * This allows SEOPress to use our custom business info fields in its schema markup
 * instead of requiring duplicate data entry in SEOPress settings.
 */
function sp_schemas_mapping_select($mapping) {
    // Add Brighter business info fields to SEOPress schema dropdown
    $mapping['brighter_fields'] = [
        'label' => __('Brighter Business Info', 'brighterwebsites'),
        'values' => [
            // Basic Info
            'brighter_business_name' => __('Business Name', 'brighterwebsites'),
            'brighter_contact_name' => __('Contact Name', 'brighterwebsites'),
            'brighter_abn' => __('ABN', 'brighterwebsites'),
            'brighter_business_hours' => __('Business Hours', 'brighterwebsites'),
            'brighter_organisation_type' => __('Organisation Type', 'brighterwebsites'),
            'brighter_service_description' => __('Service Description', 'brighterwebsites'),

            // Contact Details
            'brighter_phone_number' => __('Phone Number', 'brighterwebsites'),
            'brighter_email' => __('Email', 'brighterwebsites'),
            'brighter_address' => __('Address', 'brighterwebsites'),
            'brighter_city' => __('City', 'brighterwebsites'),
            'brighter_state' => __('State', 'brighterwebsites'),
            'brighter_postcode' => __('Postcode', 'brighterwebsites'),
            'brighter_country' => __('Country', 'brighterwebsites'),
            'brighter_lat' => __('Latitude', 'brighterwebsites'),
            'brighter_long' => __('Longitude', 'brighterwebsites'),

            // Social Links
            'brighter_social_link_facebook' => __('Facebook URL', 'brighterwebsites'),
            'brighter_social_link_twitter' => __('Twitter URL', 'brighterwebsites'),
            'brighter_social_link_instagram' => __('Instagram URL', 'brighterwebsites'),
            'brighter_social_link_youtube' => __('YouTube URL', 'brighterwebsites'),
            'brighter_social_link_linkedin' => __('LinkedIn URL', 'brighterwebsites'),
            'brighter_social_link_google_review' => __('Google Review URL', 'brighterwebsites'),

            // Service Details
            'brighter_provider_mobility' => __('Provider Mobility', 'brighterwebsites'),
            'brighter_price_tier' => __('Price Tier', 'brighterwebsites'),
        ]
    ];

    return $mapping;
}
add_filter('seopress_schemas_mapping_select', 'sp_schemas_mapping_select');