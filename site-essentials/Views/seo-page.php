<?php
/**
 * SEO Page Template
 *
 * Main SEO page for Site Essentials with tabbed interface.
 *
 * @package    SiteEssentials
 * @subpackage Views
 * @version    1.1.0
 *
 * Variables available:
 * @var string      $active_tab Current active tab (set by Admin_UI::render_seo_page)
 * @var object|null $seo_module SEO module instance (if loaded)
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap site-essentials-wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php if ( ! $seo_module ) : ?>
        <div class="notice notice-warning">
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

        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=<?php echo esc_attr( \SiteEssentials\Core\Admin_UI::SEO_PAGE_SLUG ); ?>&tab=sitemaps"
               class="nav-tab <?php echo 'sitemaps' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Sitemaps', 'site-essentials' ); ?>
            </a>
            <a href="?page=<?php echo esc_attr( \SiteEssentials\Core\Admin_UI::SEO_PAGE_SLUG ); ?>&tab=meta"
               class="nav-tab <?php echo 'meta' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Archive SEO', 'site-essentials' ); ?>
            </a>
            <a href="?page=<?php echo esc_attr( \SiteEssentials\Core\Admin_UI::SEO_PAGE_SLUG ); ?>&tab=advanced"
               class="nav-tab <?php echo 'advanced' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Advanced', 'site-essentials' ); ?>
            </a>
            <a href="?page=<?php echo esc_attr( \SiteEssentials\Core\Admin_UI::SEO_PAGE_SLUG ); ?>&tab=redirections"
               class="nav-tab <?php echo 'redirections' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Redirections', 'site-essentials' ); ?>
            </a>
        </h2>

        <div class="site-essentials-content">

            <?php if ( 'sitemaps' === $active_tab ) : ?>
                <div class="card" style="margin-top: 20px; padding: 20px;">
                    <?php $seo_module->render_settings(); ?>
                </div>

            <?php elseif ( 'meta' === $active_tab ) : ?>
                <div style="margin-top: 20px;">
                    <?php
                    if ( class_exists( '\SiteEssentials\Modules\SeoMeta\Archive_Settings' ) ) {
                        include SITE_ESSENTIALS_PATH . 'Modules/SeoMeta/views/archive-meta.php';
                    } else {
                        echo '<div class="notice notice-warning"><p>' .
                             esc_html__( 'Archive SEO could not be loaded. Ensure the SEO Module is enabled and reload.', 'site-essentials' ) .
                             '</p></div>';
                    }
                    ?>
                </div>

            <?php elseif ( 'advanced' === $active_tab ) : ?>
                <?php include SITE_ESSENTIALS_PATH . 'Modules/SeoMeta/views/advanced.php'; ?>

            <?php elseif ( 'redirections' === $active_tab ) : ?>
                <?php include SITE_ESSENTIALS_PATH . 'Modules/SeoMeta/views/redirections.php'; ?>

            <?php endif; ?>

        </div>
    <?php endif; ?>
</div>
