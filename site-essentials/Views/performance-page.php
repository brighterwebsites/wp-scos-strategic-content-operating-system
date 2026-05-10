<?php
/**
 * Performance Page Template
 *
 * Tabs: Performance (Image Optimisation + Asset Preloading + Monitoring) | WP Tweaks
 *
 * @package    SiteEssentials
 * @subpackage Views
 * @version    2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$performance_slug = \SiteEssentials\Core\Admin_UI::ESSENTIALS_PAGE_SLUG;
$base_url         = admin_url( 'admin.php?page=' . $performance_slug );
?>

<div class="wrap scos">

	<header class="scos__header">
		<div>
			<h1 class="scos__title"><?php esc_html_e( 'Performance', 'site-essentials' ); ?></h1>
			<p class="scos__subtitle">Site Essentials &rsaquo; Performance</p>
		</div>
		<div class="scos__header-actions"></div>
	</header>

	<nav class="scos__tabs">
		<a href="<?php echo esc_url( $base_url . '&tab=performance' ); ?>"
		   class="scos__tab <?php echo $active_tab === 'performance' ? 'scos__tab--active' : ''; ?>">
			<?php esc_html_e( 'Performance', 'site-essentials' ); ?>
		</a>
		<a href="<?php echo esc_url( $base_url . '&tab=tweaks' ); ?>"
		   class="scos__tab <?php echo $active_tab === 'tweaks' ? 'scos__tab--active' : ''; ?>">
			<?php esc_html_e( 'WP Tweaks', 'site-essentials' ); ?>
		</a>
	</nav>

	<?php if ( $active_tab === 'tweaks' ) : ?>

		<?php if ( ! $tweaks_module || ! is_object( $tweaks_module ) ) : ?>
			<div class="scos-notice scos-notice--warning">
				<p>
					<?php esc_html_e( 'WordPress Tweaks module is not enabled.', 'site-essentials' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \SiteEssentials\Core\Admin_UI::SETTINGS_PAGE_SLUG ) ); ?>">
						<?php esc_html_e( 'Enable it in Settings › Modules', 'site-essentials' ); ?>
					</a>
				</p>
			</div>
		<?php else : ?>
			<?php $tweaks_module->render_settings(); ?>
		<?php endif; ?>

	<?php else : /* Performance tab — Image Optimisation, Asset Preloading, Monitoring */ ?>

		<?php if ( isset( $_GET['tweaks_saved'] ) && $_GET['tweaks_saved'] === '1' ) : ?>
			<div class="scos-notice scos-notice--success">
				<p><?php esc_html_e( 'Settings saved.', 'site-essentials' ); ?></p>
			</div>
		<?php endif; ?>

		<!-- ═══════════════════════════════════════════════════════════
		     Section: Image Optimisation
		     ═══════════════════════════════════════════════════════════ -->
		<p class="scos__section-label"><?php esc_html_e( 'Image Optimisation', 'site-essentials' ); ?></p>

		<?php if ( ! $image_settings_available ) : ?>
			<div class="scos-notice scos-notice--warning">
				<p><?php esc_html_e( 'Image optimization settings require brighter-core (Brighter Tools) to be active.', 'site-essentials' ); ?></p>
			</div>
		<?php else : ?>

			<div class="scos-card">
				<div class="scos-card__header">
					<h2 class="scos-card__title"><?php esc_html_e( 'Image Settings', 'site-essentials' ); ?></h2>
					<p class="scos-card__desc"><?php esc_html_e( 'Max upload dimension, resize on upload, JPEG quality, and registered image sizes.', 'site-essentials' ); ?></p>
				</div>
				<div class="scos-card__body">
					<form method="post" action="options.php">
						<?php settings_fields( 'brighter_optimisation_settings' ); ?>
						<?php do_settings_sections( 'brighter_optimisation_page' ); ?>
						<?php submit_button( esc_html__( 'Save Image Settings', 'site-essentials' ) ); ?>
					</form>
				</div>
				<div class="scos-card__footer">
					<a href="https://brighterwebsites.com.au/software/image-optimisation/#image-settings"
					   target="_blank" rel="noopener" class="scos-btn scos-btn--ghost">
						<?php esc_html_e( 'Image Settings guide', 'site-essentials' ); ?>
					</a>
					<a href="https://brighterwebsites.com.au/software/image-optimisation/#image-thumbnails"
					   target="_blank" rel="noopener" class="scos-btn scos-btn--ghost">
						<?php esc_html_e( 'Thumbnails guide', 'site-essentials' ); ?>
					</a>
					<a href="https://brighterwebsites.com.au/software/image-optimisation/#registered-sizes"
					   target="_blank" rel="noopener" class="scos-btn scos-btn--ghost">
						<?php esc_html_e( 'Registered sizes guide', 'site-essentials' ); ?>
					</a>
				</div>
			</div>

		<?php endif; ?>

		<!-- ═══════════════════════════════════════════════════════════
		     Section: Asset Preloading
		     ═══════════════════════════════════════════════════════════ -->
		<p class="scos__section-label"><?php esc_html_e( 'Asset Preloading', 'site-essentials' ); ?></p>

		<?php if ( ! class_exists( 'Brighter_Tweaks' ) ) : ?>
			<div class="scos-notice scos-notice--warning">
				<p><?php esc_html_e( 'Asset preloading requires brighter-core (Brighter Tweaks) to be active.', 'site-essentials' ); ?></p>
			</div>
		<?php else : ?>

			<div class="scos-card">
				<div class="scos-card__header">
					<h2 class="scos-card__title"><?php esc_html_e( 'Google Fonts &amp; Asset Preloads', 'site-essentials' ); ?></h2>
					<p class="scos-card__desc"><?php esc_html_e( 'Preload Google Fonts, featured images on single pages, and define per-page preloads for critical assets.', 'site-essentials' ); ?></p>
				</div>
				<div class="scos-card__body">
					<?php
					$asset_preload_url = admin_url( 'admin.php?page=site-essentials-essentials&tab=performance' );
					?>
					<form method="get" class="scos-form" style="margin-bottom: var(--scos-s-4);">
						<input type="hidden" name="page" value="<?php echo esc_attr( $performance_slug ); ?>">
						<input type="hidden" name="tab" value="performance">
						<input type="search" name="s" class="scos-input"
						       value="<?php echo esc_attr( isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '' ); ?>"
						       placeholder="<?php esc_attr_e( 'Search pages…', 'site-essentials' ); ?>">
						<button type="submit" class="scos-btn scos-btn--ghost"><?php esc_html_e( 'Search', 'site-essentials' ); ?></button>
					</form>
					<?php Brighter_Tweaks::render_preload_form( true, $asset_preload_url ); ?>
				</div>
				<div class="scos-card__footer">
					<a href="https://brighterwebsites.com.au/software/performance/font-optimisation/google-fonts-preload"
					   target="_blank" rel="noopener" class="scos-btn scos-btn--ghost">
						<?php esc_html_e( 'Google Fonts guide', 'site-essentials' ); ?>
					</a>
					<a href="https://brighterwebsites.com.au/software/asset-preloading/#featured-images"
					   target="_blank" rel="noopener" class="scos-btn scos-btn--ghost">
						<?php esc_html_e( 'Featured images guide', 'site-essentials' ); ?>
					</a>
					<a href="https://brighterwebsites.com.au/software/asset-preloading/#per-page"
					   target="_blank" rel="noopener" class="scos-btn scos-btn--ghost">
						<?php esc_html_e( 'Per-page preloads guide', 'site-essentials' ); ?>
					</a>
				</div>
			</div>

		<?php endif; ?>

		<!-- ═══════════════════════════════════════════════════════════
		     Section: Monitoring
		     ═══════════════════════════════════════════════════════════ -->
		<p class="scos__section-label"><?php esc_html_e( 'Monitoring', 'site-essentials' ); ?></p>

		<?php include SITE_ESSENTIALS_PATH . 'Views/performance-monitoring.php'; ?>

	<?php endif; ?>

</div>
