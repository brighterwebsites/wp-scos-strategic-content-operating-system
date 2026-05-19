<?php
/**
 * SEO Page Template
 *
 * v1.2 | 2026-05-19
 *
 * SCOS design system: scos__header, scos__tabs, scos-card layout.
 * No functional changes — tab slugs, module checks, and include paths unchanged.
 *
 * @package    SiteEssentials
 * @subpackage Views
 *
 * Variables available:
 * @var string      $active_tab Current active tab (set by Admin_UI::render_seo_page)
 * @var object|null $seo_module SEO module instance (if loaded)
 */

defined( 'ABSPATH' ) || exit;

$page_slug = \SiteEssentials\Core\Admin_UI::SEO_PAGE_SLUG;

$guide_base = 'https://brighterwebsites.com.au/software/seo-module/';
$guide_urls = [
	'sitemaps'     => $guide_base . 'sitemaps/',
	'meta'         => $guide_base . 'archive-seo/',
	'advanced'     => $guide_base . 'advanced-seo/',
	'redirections' => $guide_base . 'redirections/',
];
$current_guide = isset( $guide_urls[ $active_tab ] ) ? $guide_urls[ $active_tab ] : $guide_base;
?>

<div class="wrap scos">

	<?php if ( ! $seo_module ) : ?>

		<header class="scos__header">
			<div>
				<h1 class="scos__title"><?php esc_html_e( 'SEO', 'site-essentials' ); ?></h1>
				<p class="scos__subtitle"><?php esc_html_e( 'Site Essentials › SEO', 'site-essentials' ); ?></p>
			</div>
		</header>
		<div class="scos-notice scos-notice--warning">
			<p>
				<strong><?php esc_html_e( 'SEO Module not enabled', 'site-essentials' ); ?></strong><br>
				<?php esc_html_e( 'Please enable the SEO Module in', 'site-essentials' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \SiteEssentials\Core\Admin_UI::SETTINGS_PAGE_SLUG ) ); ?>">
					<?php esc_html_e( 'Settings', 'site-essentials' ); ?>
				</a>
				<?php esc_html_e( 'to access SEO features.', 'site-essentials' ); ?>
			</p>
		</div>

	<?php else : ?>

		<header class="scos__header">
			<div>
				<h1 class="scos__title"><?php esc_html_e( 'SEO', 'site-essentials' ); ?></h1>
				<p class="scos__subtitle"><?php esc_html_e( 'Site Essentials › SEO', 'site-essentials' ); ?></p>
			</div>
			<div class="scos__header-actions">
				<a href="<?php echo esc_url( $current_guide ); ?>"
				   class="scos-btn scos-btn--ghost"
				   target="_blank" rel="noopener">
					<?php esc_html_e( 'Guide', 'site-essentials' ); ?> ↗
				</a>
			</div>
		</header>

		<nav class="scos__tabs">
			<a href="?page=<?php echo esc_attr( $page_slug ); ?>&tab=sitemaps"
			   class="scos__tab<?php echo 'sitemaps' === $active_tab ? ' scos__tab--active' : ''; ?>">
				<?php esc_html_e( 'Sitemaps', 'site-essentials' ); ?>
			</a>
			<a href="?page=<?php echo esc_attr( $page_slug ); ?>&tab=meta"
			   class="scos__tab<?php echo 'meta' === $active_tab ? ' scos__tab--active' : ''; ?>">
				<?php esc_html_e( 'Archive SEO', 'site-essentials' ); ?>
			</a>
			<a href="?page=<?php echo esc_attr( $page_slug ); ?>&tab=advanced"
			   class="scos__tab<?php echo 'advanced' === $active_tab ? ' scos__tab--active' : ''; ?>">
				<?php esc_html_e( 'Advanced', 'site-essentials' ); ?>
			</a>
			<a href="?page=<?php echo esc_attr( $page_slug ); ?>&tab=redirections"
			   class="scos__tab<?php echo 'redirections' === $active_tab ? ' scos__tab--active' : ''; ?>">
				<?php esc_html_e( 'Redirections', 'site-essentials' ); ?>
			</a>
		</nav>

		<?php if ( 'sitemaps' === $active_tab ) : ?>
			<?php $seo_module->render_settings(); ?>

		<?php elseif ( 'meta' === $active_tab ) : ?>
			<?php
			if ( class_exists( '\SiteEssentials\Modules\SeoMeta\Archive_Settings' ) ) {
				include SITE_ESSENTIALS_PATH . 'Modules/SeoMeta/views/archive-meta.php';
			} else {
				echo '<div class="scos-notice scos-notice--warning"><p>' .
				     esc_html__( 'Archive SEO could not be loaded. Ensure the SEO Module is enabled and reload.', 'site-essentials' ) .
				     '</p></div>';
			}
			?>

		<?php elseif ( 'advanced' === $active_tab ) : ?>
			<?php include SITE_ESSENTIALS_PATH . 'Modules/SeoMeta/views/advanced.php'; ?>

		<?php elseif ( 'redirections' === $active_tab ) : ?>
			<?php include SITE_ESSENTIALS_PATH . 'Modules/SeoMeta/views/redirections.php'; ?>

		<?php endif; ?>

	<?php endif; ?>

</div>
