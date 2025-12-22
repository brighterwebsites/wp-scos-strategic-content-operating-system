<?php
/**
 * SEO Page Template
 *
 * Main SEO page for Site Essentials with tabbed interface.
 *
 * @package    SiteEssentials
 * @subpackage Views
 * @version    1.0.0
 *
 * Variables available:
 * @var string $active_tab Current active tab
 * @var object|null $seo_module SEO module instance (if loaded)
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap site-essentials-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (!$seo_module): ?>
        <!-- SEO Module Not Enabled -->
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e('SEO Module Not Enabled', 'site-essentials'); ?></strong><br>
                <?php esc_html_e('Please enable the SEO module in', 'site-essentials'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . \SiteEssentials\Core\Admin_UI::SETTINGS_PAGE_SLUG)); ?>">
                    <?php esc_html_e('Settings', 'site-essentials'); ?>
                </a>
                <?php esc_html_e('to access SEO features.', 'site-essentials'); ?>
            </p>
        </div>
    <?php else: ?>
        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=<?php echo esc_attr(\SiteEssentials\Core\Admin_UI::SEO_PAGE_SLUG); ?>&tab=sitemaps"
               class="nav-tab <?php echo $active_tab === 'sitemaps' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Sitemaps', 'site-essentials'); ?>
            </a>
            <a href="?page=<?php echo esc_attr(\SiteEssentials\Core\Admin_UI::SEO_PAGE_SLUG); ?>&tab=meta"
               class="nav-tab <?php echo $active_tab === 'meta' ? 'nav-tab-active' : ''; ?>"
               style="opacity: 0.5; cursor: not-allowed;">
                <?php esc_html_e('Meta Tags', 'site-essentials'); ?> <small>(Coming Soon)</small>
            </a>
            <a href="?page=<?php echo esc_attr(\SiteEssentials\Core\Admin_UI::SEO_PAGE_SLUG); ?>&tab=schema"
               class="nav-tab <?php echo $active_tab === 'schema' ? 'nav-tab-active' : ''; ?>"
               style="opacity: 0.5; cursor: not-allowed;">
                <?php esc_html_e('Schema', 'site-essentials'); ?> <small>(Coming Soon)</small>
            </a>
        </h2>

        <div class="site-essentials-content">
            <?php if ($active_tab === 'sitemaps'): ?>
                <!-- Sitemaps Tab Content -->
                <div class="card" style="margin-top: 20px; padding: 20px;">
                    <?php $seo_module->render_settings(); ?>
                </div>

            <?php elseif ($active_tab === 'meta'): ?>
                <!-- Meta Tags Tab (Future) -->
                <div class="card" style="margin-top: 20px; padding: 20px;">
                    <p><?php esc_html_e('Meta tags management coming soon...', 'site-essentials'); ?></p>
                </div>

            <?php elseif ($active_tab === 'schema'): ?>
                <!-- Schema Tab (Future) -->
                <div class="card" style="margin-top: 20px; padding: 20px;">
                    <p><?php esc_html_e('Schema markup management coming soon...', 'site-essentials'); ?></p>
                </div>

            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
