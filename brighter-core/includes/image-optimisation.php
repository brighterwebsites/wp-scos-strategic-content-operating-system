<?php
/**
 * Brighter Tools: Image Optimisation (Runtime)
 * 
 * File: image-optimisation.php
 * Purpose: Core logic for resizing uploads, managing registered sizes,
 * thumbnail control, comment disable on attachments, and LiteSpeed fade-in CSS.
 *  
 * Version: 4.0.0
 *
 * Responsibilities:
 * - Resize uploaded images to a max dimension
 * - Register, enable, or disable custom image sizes
 * - Enforce thumbnail generation preferences
 * - Disable comments on attachment pages
 * - Add lazyload fade-in CSS for LiteSpeed
 *
 * Notes:
 * - Settings for these features are managed in brighter-support-image-settings.php
 * - This file runs the actual behaviour on upload and frontend
 *  
 */


if (!defined('ABSPATH')) exit;

// ==========================================
// ✅ Resize images on upload
// ==========================================
add_filter('wp_handle_upload', function ($upload) {
    $file_path = $upload['file'];
    $file_type = $upload['type'];

    if (!preg_match('/^image\/(jpe?g|png|gif)$/', $file_type)) return $upload;
    if (get_option('enable_image_resize', 'yes') !== 'yes') return $upload;

    list($width, $height) = getimagesize($file_path);
    $max_size = intval(get_option('image_max_dimension', 2480));

    if ($width <= $max_size && $height <= $max_size) return $upload;

    $aspect_ratio = $width / $height;
    $new_width = $width >= $height ? $max_size : intval($max_size * $aspect_ratio);
    $new_height = $width >= $height ? intval($max_size / $aspect_ratio) : $max_size;

    switch ($file_type) {
        case 'image/jpeg': $src = imagecreatefromjpeg($file_path); break;
        case 'image/png': $src = imagecreatefrompng($file_path); break;
        case 'image/gif': $src = imagecreatefromgif($file_path); break;
        default: return $upload;
    }

    $dst = imagecreatetruecolor($new_width, $new_height);
    if (in_array($file_type, ['image/png', 'image/gif'])) {
        imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    $jpeg_quality = intval(get_option('jpeg_quality', 75));
    switch ($file_type) {
        case 'image/jpeg': imagejpeg($dst, $file_path, $jpeg_quality); break;
        case 'image/png':  imagepng($dst, $file_path); break;
        case 'image/gif':  imagegif($dst, $file_path); break;
    }

    imagedestroy($src);
    imagedestroy($dst);

    return $upload;
});

// ==========================================
// ✅ Image Sizes - Register / Deregister
// ==========================================
add_action('init', function () {
    $sizes = [
        'thumbnail'     => [150, 150, true],
        'medium'        => [300, 0, false],
        'medium_large'  => [768, 0, false],
        'large'         => [1200, 0, false],
        'custom_768w'   => [768, 0, false],
        'custom_1200w'  => [1200, 0, false],
        'og-image'      => [1200, 630, true],
        '1536x1536'     => [1536, 1536, false],
        '2048x2048'     => [2048, 2048, false],
    ];

    global $_wp_additional_image_sizes;

    foreach ($sizes as $name => [$w, $h, $crop]) {
        if (get_option("enable_size_$name", 1)) {
            add_image_size($name, $w, $h, $crop);
        } else {
            remove_image_size($name);
            unset($_wp_additional_image_sizes[$name]); // hide from regenerators
        }
    }

    // Clean DB for disabled core sizes
    $option_map = [
        'thumbnail'     => 'thumbnail_size',
        'medium'        => 'medium_size',
        'large'         => 'large_size',
        'medium_large'  => 'medium_large_size',
        '1536x1536'     => '1536x1536_size',
        '2048x2048'     => '2048x2048_size',
    ];

    foreach ($option_map as $name => $prefix) {
        if (!get_option("enable_size_$name", 0)) {
            update_option("{$prefix}_w", 0);
            update_option("{$prefix}_h", 0);
            update_option("{$prefix}_crop", false);
        }
    }
}, 10);

// ==========================================
// ✅ Filter: remove disabled sizes during generation
// ==========================================
add_filter('intermediate_image_sizes', function ($sizes) {
    $core = ['thumbnail', 'medium', 'medium_large', 'large'];
    return array_filter($sizes, function ($s) {
        return get_option("enable_size_$s", 0);
    });
});

add_filter('intermediate_image_sizes_advanced', function ($sizes) {
    foreach ($sizes as $name => $settings) {
        if (!get_option("enable_size_$name", 1)) {
            unset($sizes[$name]);
        }
    }
    return $sizes;
});

// ==========================================
// ✅ Admin UI Dropdown - Media Editor
// ==========================================
add_filter('image_size_names_choose', function ($sizes) {
    $labels = [
        'custom_768w'   => 'Custom 768w',
        'custom_1200w'  => 'Custom 1200w',
        'og-image'      => 'Open Graph 1200x630',
    ];
    foreach ($labels as $key => $label) {
        if (get_option("enable_size_$key", 0)) {
            $sizes[$key] = $label;
        }
    }
    return $sizes;
});

// ==========================================
// ✅ Disable comments on media attachments
// ==========================================
add_filter('comments_open', function ($open, $post_id) {
    $post = get_post($post_id);
    return ($post && $post->post_type === 'attachment') ? false : $open;
}, 10, 2);
/**
 * Inline CSS for lazy-loaded image fade-in
 */
function brighterwebsites_lazyload_css() {
    ?>
    <style>
    /* PART 1 - Before Lazy Load */
    img[data-lazyloaded] {
        opacity: 0;
    }
    /* PART 2 - Upon Lazy Load */
    img.litespeed-loaded {
        -webkit-transition: opacity .5s linear 0.2s;
        -moz-transition: opacity .5s linear 0.2s;
        transition: opacity .5s linear 0.2s;
        opacity: 1;
    }
    </style>
    <?php
}
add_action('wp_head', 'brighterwebsites_lazyload_css'); // frontend
