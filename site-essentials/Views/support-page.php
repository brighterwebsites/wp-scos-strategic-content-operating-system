<?php
/**
 * Support hub — top-level shell (placeholder only).
 *
 * @package SiteEssentials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap scos">
	<header class="scos__header">
		<div>
			<h1 class="scos__title"><?php esc_html_e( 'Support', 'site-essentials' ); ?></h1>
			<p class="scos__subtitle"><?php esc_html_e( 'Client support landing page', 'site-essentials' ); ?></p>
		</div>
	</header>

	<div class="scos-card">
		<div class="scos-card__body">
			<div class="scos-empty">
				<div class="scos-empty__icon">&#x1F6DF;</div>
				<p class="scos-empty__title"><?php esc_html_e( 'Support hub', 'site-essentials' ); ?></p>
				<p class="scos-empty__desc">
					<?php esc_html_e( 'This page will surface agency contact details, manuals, and support links pulled from Agency settings. Configuration coming in the next pass.', 'site-essentials' ); ?>
				</p>
			</div>
		</div>
	</div>
</div>
