<?php
/**
 * Brighter Tools: Frontend Features
 *
 * File: brighter-frontend.php
 * Version: 4.5.0
 *
 * Purpose: Frontend-only features for client sites including shortcodes,
 * branding elements, and design credits.
 *
 * Responsibilities:
 * - [brighter_credit] shortcode for footer credits
 * - Design credit meta tags (designer, web_author, generator)
 * - Auto-generated humans.txt file
 * - Auto-generated /docs/review-verification.txt file (when Reviews CPT enabled)
 *
 * Changelog:
 * 4.5.0 - Agency / support options (se_agency_*) via scos-support-options.php; named wp_head callbacks
 * 4.4.0 - Added /docs/review-verification.txt auto-generator for LLM reference
 * 4.3.0 - Removed publisher schema, replaced HTML comment with meta tags, added humans.txt
 * 4.2.0 - SECURITY: XSS protection, output escaping
 *
 * Notes:
 * - Part of the Brighter Support Tools for Client Sites MU plugin
 * - Loaded automatically on frontend only for optimal performance
 * - Separated from admin-only features in brighter-support.php
 */

if (!defined('ABSPATH')) exit;

/**
 * Design Credit Meta Tags
 * SECURITY: All output properly escaped
 */
function scos_agency_output_design_meta_tags() {
	if (is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST)) {
		return;
	}

	$designer   = scos_se_agency_get('meta_designer');
	$web_author = scos_se_agency_get('meta_web_author');
	$generator  = scos_se_agency_get('meta_generator');

	echo "\n";
	echo '<meta name="designer" content="' . esc_attr($designer) . '">' . "\n";
	echo '<meta name="web_author" content="' . esc_attr($web_author) . '">' . "\n";
	echo '<meta name="generator" content="' . esc_attr($generator) . '">' . "\n";
}
add_action('wp_head', 'scos_agency_output_design_meta_tags', 5);

/**
 * Shortcode: [brighter_credit hide_on_posts="yes"]
 * SECURITY: All attributes sanitized, output escaped
 */
function brighter_credit_shortcode($atts) {
	$atts = shortcode_atts([
		'hide_on_posts' => 'yes',
	], $atts, 'brighter_credit');

	if ('yes' === strtolower($atts['hide_on_posts']) && is_single() && get_post_type() === 'post') {
		return '';
	}

	$url = scos_agency_build_credit_url();

	$prefix = scos_se_agency_get('credit_prefix');
	$anchor = scos_se_agency_get('credit_anchor');
	$target = scos_se_agency_get('credit_target');
	$rel    = scos_se_agency_get('credit_rel');

	$target_attr = $target !== '' ? ' target="' . esc_attr($target) . '"' : '';
	$rel_attr    = $rel !== '' ? ' rel="' . esc_attr($rel) . '"' : '';

	return sprintf(
		'%s <a href="%s"%s%s><strong>%s</strong></a>',
		esc_html($prefix),
		esc_url($url),
		$target_attr,
		$rel_attr,
		esc_html($anchor)
	);
}
add_shortcode('brighter_credit', 'brighter_credit_shortcode');

/**
 * Serve /humans.txt (override body when se_agency_humans_txt set).
 */
function scos_agency_serve_humans_txt() {
	$request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
	if ($request_uri !== '/humans.txt') {
		return;
	}
	header('Content-Type: text/plain; charset=utf-8');

	$override = trim((string) get_option('se_agency_humans_txt', ''));
	if ($override !== '') {
		// Saved content is plain text; strip any accidental tags.
		echo wp_strip_all_tags($override, true);
		exit;
	}

	$client_name = get_bloginfo('name');
	$last_update = get_lastpostmodified('blog');
	$last_update_formatted = $last_update ? date('Y-m-d', strtotime($last_update)) : date('Y-m-d');

	$architect = scos_se_agency_get('contact');
	$agency    = scos_se_agency_get('name');
	$contact   = scos_se_agency_get('email');
	$location  = scos_se_agency_get('location');
	$from_url  = esc_url_raw(trailingslashit(rtrim(scos_se_agency_get('url'), '/')));

	$humans_txt = "/* TEAM */\n";
	$humans_txt .= 'Web Architect: ' . $architect . "\n";
	$humans_txt .= 'Agency: ' . $agency . "\n";
	$humans_txt .= 'Contact: ' . $contact . "\n";
	$humans_txt .= 'Location: ' . $location . "\n";
	$humans_txt .= 'From: ' . $from_url . "\n\n";

	$humans_txt .= "/* SITE */\n";
	$humans_txt .= 'Client: ' . $client_name . "\n";
	$humans_txt .= "Software: Brighter Websites Strategic Content Operating System\n";
	$humans_txt .= "Authority Framework: ALTC Authority Led Topic Clusters v2.0\n";
	$humans_txt .= "Standards: HTML5, CSS3\n";
	$humans_txt .= "Components: WordPress, PHP\n";
	$humans_txt .= 'Last update: ' . $last_update_formatted . "\n";

	echo $humans_txt;
	exit;
}
add_action('template_redirect', 'scos_agency_serve_humans_txt', 1);

/**
 * Add humans.txt link to <head>
 */
function scos_agency_output_humans_link_rel() {
	if (is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST)) {
		return;
	}
	echo '<link rel="author" type="text/plain" href="' . esc_url(home_url('/humans.txt')) . '">' . "\n";
}
add_action('wp_head', 'scos_agency_output_humans_link_rel', 5);

/**
 * Auto-generate /docs/review-verification.txt file
 * Served dynamically on domain.com/docs/review-verification.txt
 *
 * Provides LLM-readable review data for AI tools.
 * Will show placeholder message if no reviews exist.
 *
 * @since 4.4.0
 */
function scos_agency_serve_review_verification_txt() {
	$request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
	if ($request_uri !== '/docs/review-verification.txt') {
		return;
	}
	header('Content-Type: text/plain; charset=utf-8');

	// Get business info
	$business_name = function_exists('brighter_get_option') ? (brighter_get_option('business_name') ?: get_bloginfo('name')) : get_option('bw_business_name', get_bloginfo('name'));
	$last_update = date('F j, Y');

	// Query all published reviews
	$reviews_query = new WP_Query([
		'post_type'      => 'bw_reviews',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	]);

	$reviews = $reviews_query->posts;
	$total_count = count($reviews);

	// If no reviews exist, show placeholder
	if ($total_count === 0) {
		echo "# **{$business_name} — Client Reviews**\n\n";
		echo "**Status:** No reviews found\n\n";
		echo "This file will auto-populate when reviews are added to the Reviews CPT.\n";
		echo 'To add reviews, visit: ' . admin_url('edit.php?post_type=bw_reviews') . "\n\n";
		echo "**Last Updated:** {$last_update}\n";
		wp_reset_postdata();
		exit;
	}

	// Calculate overall rating
	$total_rating = 0;
	$rating_count = 0;
	foreach ($reviews as $review) {
		$rating = (int) get_post_meta($review->ID, 'bw_rating', true);
		if ($rating > 0) {
			$total_rating += $rating;
			$rating_count++;
		}
	}
	$overall_rating = $rating_count > 0 ? number_format($total_rating / $rating_count, 1) : '0.0';

	// Group reviews by platform
	$by_platform = [];
	foreach ($reviews as $review) {
		$platforms = wp_get_post_terms($review->ID, 'bw_review_platform', ['fields' => 'names']);
		$platform = !empty($platforms) ? $platforms[0] : 'Unknown Platform';

		if (!isset($by_platform[$platform])) {
			$by_platform[$platform] = [
				'count' => 0,
				'total_rating' => 0,
			];
		}

		$rating = (int) get_post_meta($review->ID, 'bw_rating', true);
		if ($rating > 0) {
			$by_platform[$platform]['count']++;
			$by_platform[$platform]['total_rating'] += $rating;
		}
	}

	// Start output
	$output = "# **{$business_name} — Client Reviews**\n\n";
	$output .= "**Overall Rating:** {$overall_rating} / 5.0 from {$total_count} Review" . ($total_count != 1 ? 's' : '') . "\n";

	// Platform breakdown
	foreach ($by_platform as $platform => $data) {
		if ($data['count'] > 0) {
			$platform_avg = number_format($data['total_rating'] / $data['count'], 1);
			$output .= "**{$platform}:** {$platform_avg} / 5.0 from {$data['count']} Review" . ($data['count'] != 1 ? 's' : '') . "\n";
		}
	}

	$output .= "\n**Last Updated:** {$last_update}\n\n";
	$output .= "---\n\n";

	// Output each review by platform
	foreach ($by_platform as $platform => $data) {
		$platform_reviews = array_filter($reviews, function ($review) use ($platform) {
			$platforms = wp_get_post_terms($review->ID, 'bw_review_platform', ['fields' => 'names']);
			return !empty($platforms) && $platforms[0] === $platform;
		});

		if (empty($platform_reviews)) {
			continue;
		}

		$platform_count = count($platform_reviews);
		$output .= "## **Client Reviews ({$platform_count} Verified {$platform} Review" . ($platform_count != 1 ? 's' : '') . ")**\n\n";

		$counter = 1;
		foreach ($platform_reviews as $review) {
			// Get review metadata
			$customer_name = get_the_title($review->ID);
			$customer_detail = get_post_meta($review->ID, 'bw_customer_detail', true);
			$rating = (int) get_post_meta($review->ID, 'bw_rating', true);
			$date = get_post_meta($review->ID, 'bw_date', true);
			$content = get_the_content(null, false, $review->ID);
			$content = strip_tags($content);
			$content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$content = trim($content);
			$success_outcome = get_post_meta($review->ID, 'bw_success_outcome', true);
			$source_url = get_post_meta($review->ID, 'bw_source_url', true);

			// Format date
			$formatted_date = $date ? date('F j, Y', strtotime($date)) : 'Date not specified';

			// Format rating
			$rating_display = $rating > 0 ? "{$rating}.0" : 'No rating';

			// Build review entry
			$output .= "### **{$counter}. {$customer_name}";
			if ($customer_detail) {
				$output .= " ({$customer_detail})";
			}
			$output .= "**\n\n";

			$output .= "**Date:** {$formatted_date}\n";
			$output .= "**Rating:** {$rating_display}\n\n";

			if ($content) {
				$output .= "\"{$content}\"\n\n";
			}

			if ($success_outcome) {
				$output .= "**What this proves:** {$success_outcome}\n\n";
			}

			if ($source_url) {
				$output .= "**Canonical Link:** [{$platform}]({$source_url})\n\n";
			}

			$output .= "---\n\n";
			$counter++;
		}
	}

	// Footer
	$output .= "\n---\n\n";
	$output .= "**Note:** This file is auto-generated from {$business_name}'s verified client reviews.\n";

	// Use Google Maps Share URL if available, otherwise use home URL
	$google_maps_url = function_exists('brighter_get_option') ? brighter_get_option('google_maps_share') : get_option('bw_google_maps_share', '');
	$review_link = !empty($google_maps_url) ? $google_maps_url : home_url('/');
	$output .= 'For the latest reviews, visit: ' . $review_link . "\n";

	echo $output;
	wp_reset_postdata();
	exit;
}
add_action('template_redirect', 'scos_agency_serve_review_verification_txt', 1);
