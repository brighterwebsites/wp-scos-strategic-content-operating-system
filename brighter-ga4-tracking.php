<?php
/*
Plugin Name: Brighter GA4 Tracking Loader
Description: Enqueue the unified GA4 tracker on all pages and protect it from optimisation.
*/

// Enqueue the JS in <head> so listeners exist before clicks
add_action('wp_enqueue_scripts', function () {
  $handle = 'brighter-ga4-tracking';
  $src    = content_url('mu-plugins/brighter-core/js/brighter-ga4-tracking.js'); // adjust if your path/name differs
  wp_enqueue_script($handle, $src, [], '2.0.3', false); // false = in head
}, 1);

// Add attributes so LiteSpeed and others leave it alone
add_filter('script_loader_tag', function ($tag, $handle) {
  if ($handle === 'brighter-ga4-tracking') {
    $tag = str_replace('<script', '<script data-no-defer="1" data-no-optimize="1"', $tag);
  }
  return $tag;
}, 10, 2);

