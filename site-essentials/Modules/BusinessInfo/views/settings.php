<?php
/**
 * Business Info Module — settings view.
 *
 * Renders the full business info form (from brighter-business-info.php)
 * and a collapsible shortcodes reference.
 */
defined( 'ABSPATH' ) || exit;

if ( isset( $_GET['settings-updated'] ) ) {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Business information saved.', 'site-essentials' ) . '</p></div>';
}

if ( function_exists( 'brighterweb_render_business_info_form' ) ) {
	brighterweb_render_business_info_form();
} else {
	echo '<div class="notice notice-warning"><p>';
	esc_html_e( 'Business Info form is not available. Ensure brighter-core is active.', 'site-essentials' );
	echo '</p></div>';
}

include __DIR__ . '/shortcodes-reference.php';
