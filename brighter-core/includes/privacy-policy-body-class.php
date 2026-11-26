<?php
/**
 * Brighter Core: privacy policy body class
 *
 * v4.2 | 2026-11-26 - renamed from privacy-policy-style.php; matches the Settings > Privacy
 *                     page, not only the privacy-policy slug; named callback (#137)
 *
 * Adds the page-slug-privacy-policy body class that brighter-core/css/frontend.css scopes the
 * privacy policy styles to (headings, lists, .terms-tocs, .c01-.c05). It loads no CSS itself.
 *
 * With the Business Info module on, SiteEssentials\Modules\BusinessInfo\Privacy_Policy::body_class()
 * adds the class instead (#230), so this is the fallback for sites without that module.
 *
 * The skip link that used to live here was removed: Breakdance outputs its own.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'body_class', 'brighter_privacy_policy_body_class' );

/**
 * @param string[] $classes
 * @return string[]
 */
function brighter_privacy_policy_body_class( $classes ) {
	if ( defined( 'SCOS_BIZ_ACTIVE' ) ) {
		return $classes;
	}
	$policy_id = (int) get_option( 'wp_page_for_privacy_policy' );
	if ( ( $policy_id && is_page( $policy_id ) ) || is_page( 'privacy-policy' ) ) {
		$classes[] = 'page-slug-privacy-policy';
	}
	return $classes;
}
