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

// 1) Privacy/Terms styles only on the Privacy Policy page
add_action('wp_enqueue_scripts', function () {
    if (!is_page('privacy-policy')) return;

    // Register a no-file handle so we can attach inline CSS reliably
    wp_register_style('brighter-privacy', false);
    wp_enqueue_style('brighter-privacy');

    $css = <<<CSS
/* MU Privacy Policy & Terms CSS */
.terms-tocs > li a {
  text-decoration: none;
  font-family: "Noto Sans", sans-serif;
  font-weight: 500;
}
p, .terms-text, ul, li {
  list-style-type: none;
  line-height: 1.5;
  font-family: "Noto Sans", sans-serif !important;
  color: #2b2b2b;
}
li { padding-bottom: 10px; }
h2, h3, h4 {
  font-size: 1.1em !important;
  margin: 10px 0;
  line-height: 1;
  font-weight: 500;
  font-family: "Noto Sans", sans-serif !important;
}
.c01 { padding-left: 20px; }
.c02 { padding-left: 40px; }
.c03 { padding-left: 60px; }
.c04 { padding-left: 90px; }
.c05 { padding-left: 100px; }
CSS;

    wp_add_inline_style('brighter-privacy', $css);
}, 20);

// Output the skip link
add_action('wp_body_open', function () {
    echo '<a class="skip-link screen-reader-text" href="#main-content">Skip to main content</a>';
});

// Inject #main-content target ID into the page
// This creates the anchor point for the skip link to jump to
add_action('wp_body_open', function () {
    echo '<div id="main-content"></div>';
}, 5);

// Styles (site-wide)
add_action('wp_enqueue_scripts', function () {
    wp_register_style('brighter-access', false);
    wp_enqueue_style('brighter-access');

    $css = <<<CSS
/* Base: keep it hidden but focusable */
a.skip-link.screen-reader-text {
  position: fixed;         /* sit above layout */
  top: 0; left: 0;
  transform: translateY(-120%);   /* visually hide, better than clip for overrides */
  background: var(--bde-button-primary-background-color);
  color: var(--bde-button-text-text-color);
  padding: 8px 16px;
  z-index: 999999;         /* above sticky header */
  text-decoration: none;
}
	

/* Reveal on keyboard focus */
a.skip-link.screen-reader-text:focus,
a.skip-link.screen-reader-text:focus-visible {
  transform: none;
  /* force-undo typical screen-reader-text rules */
  clip: auto !important;
  -webkit-clip-path: none !important;
  clip-path: none !important;
  width: auto !important;
  height: auto !important;
  margin: 0 !important;
  overflow: visible !important;
  outline: 2px solid #fff !important;
  outline-offset: 2px !important;
}

/* Logged-in admin bar offset (desktop) */
body.admin-bar a.skip-link.screen-reader-text {
  top: 32px;
}
/* Admin bar on mobile */
@media (max-width: 782px){
  body.admin-bar a.skip-link.screen-reader-text { top: 46px; }
}
CSS;
    wp_add_inline_style('brighter-access', $css);
}, 20);