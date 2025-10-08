<?php
/**
 * Brighter Tools: Business Info
 *
 * File: brighter-buinessinfo.php
 * Purpose: Provides a custom admin settings page for managing key business 
 * information (name, contact, social, hours, etc.), and exposes this data 
 * via shortcodes for consistent reuse across the site.
 *
 * Version: 4.0.0
 *
 * Responsibilities:
 * - Register and manage settings for business details, contact info, 
 *   service information, and social links.
 * - Render an admin interface with grouped sections (Info, Contact, Social, Service).
 * - Provide shortcodes for outputting business info values and a dynamic 
 *   copyright notice.
 *
 * Notes:
 * - Shortcode `[business_info setting="field_name"]` outputs stored values for 
 *   valid keys (e.g. `phone_number`, `email`, `social_link_facebook`).
 * - Shortcode `[site_copyright]` generates a copyright line using site name 
 *   and ABN if present.
 * - This file also registers dropdown and textarea callbacks for structured 
 *   fields such as organisation type, provider mobility, and price tier.
 * - Encoding issue: `your website’s privacy policy` should be corrected 
 *   to a proper apostrophe or `&rsquo;`.
 * - Option naming is raw (`get_option('email')` etc.), which may conflict 
 *   with other plugins. Prefixing options (e.g. `brighter_email`) would be safer.
 * 
 */
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Brighter Websites Business Info Manager
 * Description: Adds a custom admin page to manage business details and output them via shortcodes.
 * Author: Brighter Websites
 */
//add_theme_support('custom-logo');

// Enqueue custom admin styles  <--------------------------------------------------------------------------------MOVED
//function brighterweb_enqueue_admin_styles() {
//   wp_enqueue_style('brighterweb-options-styles', plugins_url('css/admin-support.css', __FILE__));
//}

//add_action('admin_enqueue_scripts', 'brighterweb_enqueue_admin_styles');

// Register custom admin menu page
function brighterweb_render_business_info_form() {
    echo '<form method="post" action="options.php" class="business_info_style">';
    settings_fields('brighterweb_business_info_group');
    do_settings_sections('brighterweb_business_info_page');
    submit_button('Save Business Information');
    echo '</form>';
}


// Render the business information admin page
function brighterweb_render_info_page() {
    ?>
    <div class="business_info_page_style">
        <h2 class="business-info-h">Business Information</h2>
        <form method="post" action="options.php" class="business_info_style">
            <?php
            settings_fields('brighterweb_business_info_group');
            do_settings_sections('brighterweb_business_info_page');
            submit_button('Save Business Information');
            ?>
        </form>
    </div>
    <?php
}

// 1. Register all field keys once:
function brighterweb_register_business_info_settings() {
$fields = [
    // Info
    'business_name', 'contact_name', 'abn',
    // Contact
    'phone_number', 'email', 'address', 'city', 'state', 'postcode', 'country', 'lat', 'long',
    // Hours
    'business_hours',
    // Social
    'social_link_facebook', 'social_link_twitter', 'social_link_instagram',
    'social_link_youtube', 'social_link_linkedin', 'social_link_google_review',
    // Service
    'area_served', 'provider_mobility', 'price_tier',
    // Extended
    'organisation_type', 'service_description'
];
foreach ($fields as $field) {
    register_setting('brighterweb_business_info_group', $field);
}

//2. Add UI sections and fields logically:
//Use grouped arrays for consistency:
$info_fields = ['business_name', 'contact_name', 'abn', 'business_hours'];
$contact_fields = ['phone_number', 'email', 'address', 'city', 'state', 'postcode', 'country', 'lat', 'long'];
$social_fields = ['facebook', 'twitter', 'instagram', 'youtube', 'linkedin', 'google_review'];

//Loop through each with their appropriate callback:
foreach ($info_fields as $field) {
    add_settings_field($field, ucwords(str_replace('_', ' ', $field)), 'brighterweb_field_callback', 'brighterweb_business_info_page', 'brighterweb_info_section', ['id' => $field]);
}
foreach ($contact_fields as $field) {
    add_settings_field($field, ucwords(str_replace('_', ' ', $field)), 'brighterweb_field_callback', 'brighterweb_business_info_page', 'brighterweb_contact_section', ['id' => $field]);
}
foreach ($social_fields as $social) {
    $id = "social_link_{$social}";
    add_settings_field($id, ucfirst($social), 'brighterweb_field_callback', 'brighterweb_business_info_page', 'brighterweb_social_section', ['id' => $id]);
}
    
    
//And use these for dropdowns and textareas:
add_settings_field('organisation_type', 'Organisation Type', 'brighterweb_select_field_callback', 'brighterweb_business_info_page', 'brighterweb_info_section', [
    'id' => 'organisation_type',
    'options' => ['Local Business', 'Organization', 'Person']
]);
add_settings_field('service_description', 'Service Description', 'brighterweb_textarea_callback', 'brighterweb_business_info_page', 'brighterweb_info_section', ['id' => 'service_description']);
add_settings_field('provider_mobility', 'Provider Mobility', 'brighterweb_dropdown_callback', 'brighterweb_business_info_page', 'brighterweb_service_section', [
    'id' => 'provider_mobility',
    'options' => ['static' => 'Static', 'dynamic' => 'Dynamic']
]);
add_settings_field('price_tier', 'Price Tier', 'brighterweb_dropdown_callback', 'brighterweb_business_info_page', 'brighterweb_service_section', [
    'id' => 'price_tier',
    'options' => ['$', '$$', '$$$', '$$$$']
]);
   
//Section Definitions
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
   
   
   
   
   
}
add_action('admin_init', 'brighterweb_register_business_info_settings');







// Section callbacks
function brighterweb_info_section_callback() {
    echo '<p class="business-info-p">Please enter your general business information below. This information is used to populate your website’s privacy policy and to generate 
<a href="https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data" target="_blank" rel="noopener noreferrer">schema markup</a> to help search engines understand and display your business more effectively in search results.
</p>';
}

function brighterweb_contact_section_callback() {
    echo '<p class="business-info-p">Enter your contact and location information below.</p>';
}

// Universal field callback renderer
function brighterweb_field_callback($args) {
    $value = get_option($args['id']);
    echo "<input type='text' name='{$args['id']}' value='" . esc_attr($value) . "' />";
    if (!empty($args['note'])) {
        echo "<p class='description'>{$args['note']}</p>";
    }
}

function brighterweb_dropdown_callback($args) {
    $value = get_option($args['id']);
    echo "<select name='{$args['id']}'>";
    foreach ($args['options'] as $key => $label) {
        $selected = selected($value, $key, false);
        echo "<option value='{$key}' {$selected}>{$label}</option>";
    }
    echo "</select>";
}


function brighterweb_select_field_callback($args) {
    $id = $args['id'];
    $value = get_option($id);
    $options = $args['options'] ?? ['Local Business', 'Organization', 'Personal'];

    echo '<select name="' . esc_attr($id) . '" id="' . esc_attr($id) . '">';
    foreach ($options as $key => $label) {
        // Allow associative or indexed arrays
        $val = is_int($key) ? $label : $key;
        $selected = selected($value, $val, false);
        echo '<option value="' . esc_attr($val) . '"' . $selected . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
}

function brighterweb_textarea_callback($args) {
    $id = $args['id'];
    $value = get_option($id);
    echo '<textarea name="' . esc_attr($id) . '" id="' . esc_attr($id) . '" rows="4" cols="50">' . esc_textarea($value) . '</textarea>';
}






// Shortcode to output business info
function brighterweb_business_info_shortcode($atts) {
$valid_keys = [
    // Basic Info
    'business_name', 'contact_name', 'abn',
    // Contact
    'phone_number', 'email', 'address', 'city', 'state', 'postcode', 'country', 'lat', 'long',
    // Additional
    'business_hours', 'area_served', 'provider_mobility', 'price_tier',
    // Social Links
    'social_link_facebook', 'social_link_twitter', 'social_link_instagram',
    'social_link_youtube', 'social_link_linkedin', 'social_link_google_review'
];


    $atts = shortcode_atts(['setting' => ''], $atts);

    if (in_array($atts['setting'], $valid_keys)) {
        return esc_html(get_option($atts['setting']));
    } else {
        return 'Invalid or missing setting attribute.';
    }
}
add_shortcode('business_info', 'brighterweb_business_info_shortcode');


function brighterwebsites_copyright_notice() {
    $year       = date('Y');
    $site_title = get_bloginfo('name');
    $abn        = get_option('abn');

    $abn_string = $abn ? "ABN {$abn}. " : '';

    return "&copy; {$year} {$site_title}. {$abn_string}All rights reserved.";
}
add_shortcode('site_copyright', 'brighterwebsites_copyright_notice');


