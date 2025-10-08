<?php
/**
 * 
 * Brighter Tools: Image Optimisation Settings (Admin UI)
 * File: brighter-support-image-settings.php
 * Purpose: Registers admin settings for image optimisation features
 * including resizing, JPEG quality, and image size toggles.
 *  
 * Version: 4.0.0
 *
 * Responsibilities:
 * - Register options for image resizing and JPEG quality
 * - Provide UI toggles for custom and core image sizes
 * - Output overview of registered sizes in settings page
 * - Add an og:logo meta tag site 
 * - Use our 1200x630 "og-image" size for single posts
 *
 * Notes:
 * - Related runtime logic lives in image-optimisation.php
 * - Admin-only; does not handle upload resizing directly
 *
 */

if (!defined('ABSPATH')) exit;

/**
 * ================================================================
 * Image Optimisation Settings
 * Register options and fields for image resizing, custom sizes,
 * JPEG quality, and optimisation settings in admin.
 * Related: Actual resizing and cleanup logic is handled in other files.
 * -----------------------------------------------------------------
 */
add_action('admin_init', function () {
    // Register base options
    register_setting('brighter_optimisation_settings', 'enable_image_resize');
    register_setting('brighter_optimisation_settings', 'image_max_dimension');
    register_setting('brighter_optimisation_settings', 'jpeg_quality');

    // Define image sizes we want toggles for
    $image_sizes = [
        'thumbnail'       => 'Thumbnail (150x150)',
        'medium'          => 'Medium (300x300)',
      //  'medium_large'    => 'Medium Large (768w)',
      //  'large'           => 'Large (1200x?)',
        'custom_768w'     => 'Custom 768w',
        'custom_1200w'    => 'Custom 1200w',
         'og-image'        => 'Open Graph (1200x630)',
        '1536x1536'       => '1536x1536',
        '2048x2048'       => '2048x2048',
       
    ];

    // Register settings for each image size
    foreach ($image_sizes as $size => $label) {
        register_setting('brighter_optimisation_settings', "enable_size_$size");
    }

    // Section: Image Settings
    add_settings_section('image_optimisation_section', '🖼️ Image Settings', '__return_false', 'brighter_optimisation_page');

    // Register: Enable image resize toggle
    add_settings_field('enable_image_resize', 'Enable Image Resizing?', function () {
        $enabled = get_option('enable_image_resize', 'yes');
        echo '<label><input type="checkbox" name="enable_image_resize" value="yes" ' . checked('yes', $enabled, false) . '> Resize uploaded images</label>';
        echo '<p class="description">If unchecked, original images will be stored without resizing.</p>';
    }, 'brighter_optimisation_page', 'image_optimisation_section');

    // Register: Max upload dimension
    add_settings_field('image_max_dimension', 'Max Upload Dimension (px)', function () {
        echo '<input type="number" name="image_max_dimension" value="' . esc_attr(get_option('image_max_dimension', 2480)) . '" class="small-text" min="500" step="10">';
        echo '<p class="description">Maximum dimension for uploaded images (longest side).</p>';
    }, 'brighter_optimisation_page', 'image_optimisation_section');

    // Register: Checkboxes for each image size
    foreach ($image_sizes as $size => $label) {
        add_settings_field("enable_size_$size", "Enable $label", function () use ($size) {
            $enabled = get_option("enable_size_$size", 1);
            echo '<input type="checkbox" name="enable_size_' . esc_attr($size) . '" value="1" ' . checked(1, $enabled, false) . '> ' . ucfirst($size);
        }, 'brighter_optimisation_page', 'image_optimisation_section');
    }

    // Register: JPEG quality field
    add_settings_field('jpeg_quality', 'JPEG Compression Quality', function () {
        $quality = get_option('jpeg_quality', 75);
        echo '<input type="number" min="30" max="100" step="1" name="jpeg_quality" value="' . esc_attr($quality) . '" />';
    }, 'brighter_optimisation_page', 'image_optimisation_section');

    // Section: Registered sizes overview
    add_settings_section('registered_sizes_section', '📏 Registered Image Sizes', function () use ($image_sizes) {
        $all_sizes = wp_get_registered_image_subsizes();
        echo '<ul>';
        foreach ($all_sizes as $name => $size) {
            $enabled = get_option("enable_size_$name", false);
            $width = esc_html($size['width']);
            $height = esc_html($size['height']);
            $crop = !empty($size['crop']) ? ' (cropped)' : '';
            $status = $enabled ? '✅ Enabled' : '❌ Disabled';
            echo "<li><strong>{$name}</strong>: {$width}×{$height}{$crop} — {$status}</li>";
        }
        echo '</ul>';
    }, 'brighter_optimisation_page');

    add_settings_section('other_optimisation_section', '️Other Optimisations', function () {
        echo '<p>More performance tools coming soon...</p>';
    }, 'brighter_optimisation_page');
});

// Add an og:logo meta tag site‑wide
add_action( 'wp_head', function() {
    // Only run on the front end
    if ( !is_admin() ) {
        $logo = get_option( 'your_logo_option' ); // or pull from SEOPress settings
        if ( $logo ) {
            echo '<meta property="og:logo" content="' . esc_url( $logo ) . '">' . "\n";
        }
    }
} );


// Use our 1200x630 "og-image" size for single posts
add_filter( 'seopress_social_og_image', function( $url, $post_id ) {
    if ( has_post_thumbnail( $post_id ) ) {
        // get the 1200x630 version, fall back to full if not available
        $new_url = wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'og-image' );
        if ( $new_url ) {
            return $new_url;
        }
    }
    return $url;
}, 10, 2 );
