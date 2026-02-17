<?php
/**
 * Brighter Tools: Business Info
 *
 * File: brighter-business-info.php
 * Version: 4.2.0
 *
 * IN USE (do not remove):
 * - Caching: Brighter_Business_Cache (get_all, clear). brighter_update_option() clears cache on save.
 * - Options: bw_* (BRIGHTER_OPTION_PREFIX = 'bw_') for privacy policy, schema, contact.
 * - Shortcodes: [business_info setting="..."], [site_copyright].
 * - Admin form: brighterweb_render_business_info_form() (used on Site Essentials > Business Info).
 * - SEOPress schema mapping: sp_schemas_mapping_select (bw_* keys).
 *
 * Changelog:
 * 4.2.0 - SECURITY: Input sanitization, XSS protection, capability checks. Option prefix bw_.
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
            // Entity Identity
            'organisation_type', 'business_name', 'business_category', 'service_description', 'abn',
            
            // Key Media
            'site_icon', 'business_logo', 'publisher_logo', 'business_image', 'mobile_theme_color',
            
            // Contact Information
            'phone_number', 'email', 'contact_type', 'contact_option',
            'address', 'city', 'state', 'postcode', 'country', 'country_code',
            'lat', 'long', 'place_id',
            
            // Social Media & Web Presence
            'social_link_facebook', 'social_link_twitter', 'social_link_instagram',
            'social_link_youtube', 'social_link_linkedin', 'social_link_pinterest',
            'google_maps_share', 'knowledge_panel_share', 'additional_account_urls',
            
            // Operational Details
            'business_hours', 'price_tier', 'provider_mobility', 'service_area',
            
            // Legacy/deprecated (keep for backwards compatibility)
            'contact_name', 'social_link_google_review', 'area_served'
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
    
    // URL/image fields
    if (strpos($key, 'social_link_') === 0 || 
        in_array($key, ['site_icon', 'business_logo', 'publisher_logo', 'business_image', 
                        'google_maps_share', 'knowledge_panel_share'], true)) {
        return esc_url_raw($value);
    }
    
    // Color fields (hex)
    if ($key === 'mobile_theme_color') {
        $value = sanitize_text_field($value);
        // Ensure it's a valid hex color
        if (preg_match('/^#?[0-9a-fA-F]{3,6}$/', $value)) {
            return $value;
        }
        return '';
    }
    
    // Textarea fields
    if (in_array($key, ['service_description', 'business_hours', 'service_area', 'additional_account_urls'], true)) {
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

    // ========================================
    // ENTITY IDENTITY SECTION
    // ========================================
    add_settings_section(
        'brighterweb_entity_section',
        'Entity Identity',
        function() {
            echo '<p class="business-info-p">Define your organization type, business name, category, and core description for schema markup.</p>';
        },
        'brighterweb_business_info_page'
    );
    
    add_settings_field(
        'organisation_type', 
        'Organisation Type', 
        'brighterweb_select_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_entity_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'organisation_type',
            'options' => ['Local Business', 'Organization', 'Person'],
            'note' => 'Person vs. Organization / Local Business'
        ]
    );
    
    add_settings_field(
        'business_name', 
        'Business Name', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_entity_section', 
        ['id' => BRIGHTER_OPTION_PREFIX . 'business_name', 'type' => 'text']
    );
    
    add_settings_field(
        'business_category', 
        'Business Category', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_entity_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'business_category', 
            'type' => 'text',
            'note' => 'Same as Primary Category on your Google Business Profile'
        ]
    );
    
    add_settings_field(
        'service_description', 
        'Description', 
        'brighterweb_textarea_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_entity_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'service_description',
            'note' => 'Service Description or Organization Bio'
        ]
    );
    
    add_settings_field(
        'abn', 
        'Business Identification', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_entity_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'abn', 
            'type' => 'text',
            'note' => 'VAT ID or ABN. Search: https://abr.business.gov.au/ABN/'
        ]
    );

    // ========================================
    // KEY MEDIA SECTION
    // ========================================
    add_settings_section(
        'brighterweb_media_section',
        'Key Media',
        function() {
            echo '<p class="business-info-p">Upload or link to your business logos and images for schema, social sharing, and search results.</p>';
            echo '<p class="business-info-p"><strong>Note:</strong> Site Icon syncs with WordPress Customizer (Appearance → Customize → Site Identity). You can set it here and in the Customizer.</p>';
        },
        'brighterweb_business_info_page'
    );
    
    add_settings_field(
        'site_icon', 
        'Site Icon (Favicon)', 
        'brighterweb_image_upload_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_media_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'site_icon',
            'note' => 'Browser tabs & Google Mobile Search. 512×512px min (1:1 square). Symbol only, bold, simple.'
        ]
    );
    
    add_settings_field(
        'business_logo', 
        'Business / Org Logo', 
        'brighterweb_image_upload_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_media_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'business_logo',
            'note' => 'Google Knowledge Panel & Maps. 1200×1200px min (1:1). Full logo, centered with 20% padding.'
        ]
    );
    
    add_settings_field(
        'publisher_logo', 
        'Publisher Logo (Optional)', 
        'brighterweb_image_upload_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_media_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'publisher_logo',
            'note' => 'Google News & Article thumbnails. 600×60px (wide) or 600×600px. Use if main logo is too complex.'
        ]
    );
    
    add_settings_field(
        'business_image', 
        'Business Image', 
        'brighterweb_image_upload_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_media_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'business_image',
            'note' => 'Local Business verification. 1200×675px min (16:9 or 4:3). Shop front, interior, or team photo.'
        ]
    );
    
    add_settings_field(
        'mobile_theme_color', 
        'Mobile Theme Colour', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_media_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'mobile_theme_color', 
            'type' => 'text',
            'note' => 'Hex color (e.g. #193b2d) for mobile browser theme'
        ]
    );

    // ========================================
    // CONTACT INFORMATION SECTION
    // ========================================
    add_settings_section(
        'brighterweb_contact_section',
        'Contact Information',
        function() {
            echo '<p class="business-info-p">Enter your contact and location information below.</p>';
        },
        'brighterweb_business_info_page'
    );
    
    add_settings_field(
        'phone_number', 
        'Primary Phone Number', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_contact_section', 
        ['id' => BRIGHTER_OPTION_PREFIX . 'phone_number', 'type' => 'tel']
    );
    
    add_settings_field(
        'email', 
        'Email Address', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_contact_section', 
        ['id' => BRIGHTER_OPTION_PREFIX . 'email', 'type' => 'email']
    );
    
    add_settings_field(
        'contact_type', 
        'Contact Type', 
        'brighterweb_select_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_contact_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'contact_type',
            'options' => [
                '' => '-- Select --',
                'customer support' => 'Customer Support',
                'technical support' => 'Technical Support',
                'billing support' => 'Billing Support',
                'sales' => 'Sales',
                'emergency' => 'Emergency'
            ],
            'note' => 'Only for Organizations'
        ]
    );
    
    add_settings_field(
        'contact_option', 
        'Contact Option', 
        'brighterweb_select_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_contact_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'contact_option',
            'options' => [
                '' => 'None',
                'TollFree' => 'Toll Free',
                'HearingImpairedSupported' => 'Hearing Impaired Supported'
            ],
            'note' => 'Only for Organizations'
        ]
    );
    
    add_settings_field(
        'address', 
        'Address', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_contact_section', 
        ['id' => BRIGHTER_OPTION_PREFIX . 'address', 'type' => 'text']
    );
    
    add_settings_field(
        'city', 
        'City', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_contact_section', 
        ['id' => BRIGHTER_OPTION_PREFIX . 'city', 'type' => 'text']
    );
    
    add_settings_field(
        'state', 
        'State', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_contact_section', 
        ['id' => BRIGHTER_OPTION_PREFIX . 'state', 'type' => 'text']
    );
    
    add_settings_field(
        'postcode', 
        'Postcode', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_contact_section', 
        ['id' => BRIGHTER_OPTION_PREFIX . 'postcode', 'type' => 'text']
    );
    
    add_settings_field(
        'country', 
        'Country', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_contact_section', 
        ['id' => BRIGHTER_OPTION_PREFIX . 'country', 'type' => 'text']
    );
    
    add_settings_field(
        'country_code', 
        'Country Code', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_contact_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'country_code', 
            'type' => 'text',
            'note' => 'e.g. AU'
        ]
    );
    
    add_settings_field(
        'lat', 
        'Latitude', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_contact_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'lat', 
            'type' => 'text',
            'note' => 'Use numerical coordinate like -37.00000. Find: https://latitude.to/'
        ]
    );
    
    add_settings_field(
        'long', 
        'Longitude', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_contact_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'long', 
            'type' => 'text',
            'note' => 'Use numerical coordinate like 145.00000'
        ]
    );
    
    add_settings_field(
        'place_id', 
        'Google Place ID', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_contact_section', 
        [
            'id'   => BRIGHTER_OPTION_PREFIX . 'place_id', 
            'type' => 'text',
            // Update the note line below:
            'note' => '<a href="https://developers.google.com/places/web-service/place-id" target="_blank">Find your Local Verified Location Business</a> (no place ID for service area businesses)'
        ]
    );

    // ========================================
    // SOCIAL MEDIA & WEB PRESENCE SECTION
    // ========================================
    add_settings_section(
        'brighterweb_social_section',
        'Social Media & Web Presence',
        function() {
            echo '<p class="business-info-p">Paste full URLs to your social profiles and sharing links.</p>';
        },
        'brighterweb_business_info_page'
    );
    
    $social_fields = [
        'facebook' => 'Facebook',
        'twitter' => 'X / Twitter',
        'instagram' => 'Instagram',
        'youtube' => 'YouTube',
        'linkedin' => 'LinkedIn',
        'pinterest' => 'Pinterest'
    ];
    
    foreach ($social_fields as $key => $label) {
        $note = '';
        if ($key === 'twitter') {
            $note = 'X needs to be Username not full URL';
        }
        add_settings_field(
            "social_link_{$key}", 
            $label, 
            'brighterweb_field_callback', 
            'brighterweb_business_info_page', 
            'brighterweb_social_section', 
            [
                'id' => BRIGHTER_OPTION_PREFIX . "social_link_{$key}", 
                'type' => 'url',
                'note' => $note
            ]
        );
    }
    
    add_settings_field(
        'google_maps_share', 
        'Google Maps Share', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_social_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'google_maps_share', 
            'type' => 'url',
            'note' => 'Search your business, click "Share" next to Save under your business name, copy link'
        ]
    );
    
    add_settings_field(
        'knowledge_panel_share', 
        'Knowledge Panel Share', 
        'brighterweb_field_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_social_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'knowledge_panel_share', 
            'type' => 'url',
            'note' => 'If you have a knowledge panel, get the share link and add it here'
        ]
    );
    
    add_settings_field(
        'additional_account_urls', 
        'Additional Account URLs', 
        'brighterweb_textarea_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_social_section', 
        [
            'id' => BRIGHTER_OPTION_PREFIX . 'additional_account_urls',
            'note' => 'One URL per line'
        ]
    );

    // ========================================
    // OPERATIONAL DETAILS SECTION
    // ========================================
    add_settings_section(
        'brighterweb_service_section',
        'Operational Details',
        function() {
            echo '<p class="business-info-p">Define your service area, mobility, hours and pricing tier.</p>';
        },
        'brighterweb_business_info_page'
    );
    
    add_settings_field(
        'business_hours', 
        'Business Hours', 
        'brighterweb_textarea_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_service_section', 
        ['id' => BRIGHTER_OPTION_PREFIX . 'business_hours']
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
        'service_area', 
        'Service Area / Details', 
        'brighterweb_textarea_callback', 
        'brighterweb_business_info_page', 
        'brighterweb_service_section', 
        ['id' => BRIGHTER_OPTION_PREFIX . 'service_area']
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
 * Section callbacks (legacy - kept for backwards compatibility if referenced elsewhere)
 * 
 * SECURITY: All output properly escaped
 */
function brighterweb_info_section_callback() {
    echo '<p class="business-info-p">Please enter your general business information below.</p>';
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
        // Allow safe HTML (links, em, strong) in notes
        echo '<p class="description">' . wp_kses_post($args['note']) . '</p>';
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
    
    if (!empty($args['note'])) {
        echo '<p class="description">' . wp_kses_post($args['note']) . '</p>';
    }
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
    
    if (!empty($args['note'])) {
        echo '<p class="description">' . wp_kses_post($args['note']) . '</p>';
    }
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
    if (!empty($args['note'])) {
        echo '<p class="description">' . wp_kses_post($args['note']) . '</p>';
    }
}

/**
 * Image upload callback (uses WordPress Media Library)
 * 
 * SECURITY: Proper URL escaping and validation
 */
function brighterweb_image_upload_callback($args) {
    $id = $args['id'];
    $value = get_option($id, '');
    $button_id = $id . '_button';
    $preview_id = $id . '_preview';
    
    echo '<div class="brighter-image-upload-wrapper">';
    echo '<input type="url" name="' . esc_attr($id) . '" id="' . esc_attr($id) . '" value="' . esc_url($value) . '" class="regular-text brighter-image-url" />';
    echo '<button type="button" class="button brighter-image-upload-btn" id="' . esc_attr($button_id) . '">Select Image</button>';
    
    if ($value) {
        echo '<div id="' . esc_attr($preview_id) . '" class="brighter-image-preview" style="margin-top:10px;">';
        echo '<img src="' . esc_url($value) . '" style="max-width:200px;height:auto;display:block;" />';
        echo '</div>';
    } else {
        echo '<div id="' . esc_attr($preview_id) . '" class="brighter-image-preview" style="margin-top:10px;display:none;"></div>';
    }
    
    if (!empty($args['note'])) {
        echo '<p class="description">' . wp_kses_post($args['note']) . '</p>';
    }
    
    // Special note for site icon
    if ($id === BRIGHTER_OPTION_PREFIX . 'site_icon') {
        echo '<p class="description"><em>To sync with WordPress Customizer site icon, go to Appearance → Customize → Site Identity.</em></p>';
    }
    
    echo '</div>';
    
    // Inline script for media uploader (only output once)
    static $script_output = false;
    if (!$script_output) {
        $script_output = true;
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.brighter-image-upload-btn').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var inputField = button.prev('.brighter-image-url');
                var previewDiv = button.next('.brighter-image-preview');
                
                var frame = wp.media({
                    title: 'Select or Upload Image',
                    button: { text: 'Use this image' },
                    multiple: false
                });
                
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    inputField.val(attachment.url);
                    previewDiv.html('<img src="' + attachment.url + '" style="max-width:200px;height:auto;display:block;" />').show();
                });
                
                frame.open();
            });
        });
        </script>
        <?php
    }
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
    
    // Enqueue media uploader scripts
    wp_enqueue_media();
    
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
            // Basic Info (option prefix bw_)
            'bw_business_name' => __('Business Name', 'brighterwebsites'),
            'bw_contact_name' => __('Contact Name', 'brighterwebsites'),
            'bw_abn' => __('ABN', 'brighterwebsites'),
            'bw_business_hours' => __('Business Hours', 'brighterwebsites'),
            'bw_organisation_type' => __('Organisation Type', 'brighterwebsites'),
            'bw_service_description' => __('Service Description', 'brighterwebsites'),

            // Contact Details
            'bw_phone_number' => __('Phone Number', 'brighterwebsites'),
            'bw_email' => __('Email', 'brighterwebsites'),
            'bw_address' => __('Address', 'brighterwebsites'),
            'bw_city' => __('City', 'brighterwebsites'),
            'bw_state' => __('State', 'brighterwebsites'),
            'bw_postcode' => __('Postcode', 'brighterwebsites'),
            'bw_country' => __('Country', 'brighterwebsites'),
            'bw_lat' => __('Latitude', 'brighterwebsites'),
            'bw_long' => __('Longitude', 'brighterwebsites'),

            // Social Links
            'bw_social_link_facebook' => __('Facebook URL', 'brighterwebsites'),
            'bw_social_link_twitter' => __('Twitter URL', 'brighterwebsites'),
            'bw_social_link_instagram' => __('Instagram URL', 'brighterwebsites'),
            'bw_social_link_youtube' => __('YouTube URL', 'brighterwebsites'),
            'bw_social_link_linkedin' => __('LinkedIn URL', 'brighterwebsites'),
            'bw_social_link_google_review' => __('Google Review URL', 'brighterwebsites'),

            // Service Details
            'bw_provider_mobility' => __('Provider Mobility', 'brighterwebsites'),
            'bw_price_tier' => __('Price Tier', 'brighterwebsites'),
        ]
    ];

    return $mapping;
}
add_filter('seopress_schemas_mapping_select', 'sp_schemas_mapping_select');