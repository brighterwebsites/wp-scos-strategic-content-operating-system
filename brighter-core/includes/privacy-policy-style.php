<?php
/**
 * Brighter Tools: Privacy Policy Style
 *
 * File: privacy-policy-style.php
 * Purpose: Adds custom styles to Privacy Policy and Terms pages, and improves
 * accessibility by adding a skip link for keyboard/screen reader users.
 *
 * Version: 4.0.0
 *
 * Responsibilities:
 * - Enqueue inline CSS only on the Privacy Policy page for consistent formatting.
 * - Inject a �Skip to main content� accessibility link at the top of every page.
 * - Apply site-wide accessibility CSS for the skip link, ensuring proper visibility
 *   when focused and accounting for the WordPress admin bar offset.
 *
 * Notes:
 * - Styles are scoped: Privacy/Terms CSS loads only on `privacy-policy` page,
 *   while accessibility skip-link CSS is loaded globally.
 * - Uses `wp_register_style` with a no-file handle so inline CSS attaches reliably.
 * - Accessibility skip link uses `wp_body_open` hook, which is supported in modern themes.
 * - This file is purely for presentation and accessibility, no LS quota logic here.
 */

defined('ABSPATH') || exit;

// Privacy Policy styles are now in frontend.css (scoped to .page-slug-privacy-policy)
// No inline styles needed - CSS is cached and loaded via brighter-frontend.css

// Add body class for privacy policy page to scope CSS
add_filter('body_class', function($classes) {
    if (is_page('privacy-policy')) {
        $classes[] = 'page-slug-privacy-policy';
    }
    return $classes;
});

// Output the skip link
add_action('wp_body_open', function () {
    echo '<a class="skip-link screen-reader-text" href="#main-content">Skip to main content</a>';
});

// Inject #main-content target ID into the page
// This creates the anchor point for the skip link to jump to
add_action('wp_body_open', function () {
    echo '<div id="main-content"></div>';
}, 5);

// Skip link styles are now in frontend.css
// No inline styles needed - CSS is cached and loaded via brighter-frontend.css