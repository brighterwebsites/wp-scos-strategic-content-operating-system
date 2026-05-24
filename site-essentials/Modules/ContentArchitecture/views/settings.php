<?php
/**
 * Content Architecture — Site Essentials settings panel.
 * Placeholder for future per-site configuration options.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="scos-ca-settings-info">
	<p>
		<?php esc_html_e( 'Content Architecture applies to all public post types. Manage your clusters and topics under the Content Architecture admin menu.', 'site-essentials' ); ?>
	</p>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=scos-content-architecture' ) ); ?>" class="button">
		<?php esc_html_e( 'Go to Content Architecture', 'site-essentials' ); ?>
	</a>
</div>
