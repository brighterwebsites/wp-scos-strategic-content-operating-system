<?php
/**
 * Performance Page Template
 *
 * Tabs: WordPress Tweaks | Image Optimization | Asset Preloading
 *
 * @package    SiteEssentials
 * @subpackage Views
 * @version    1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$performance_slug = \SiteEssentials\Core\Admin_UI::ESSENTIALS_PAGE_SLUG;
$base_url = admin_url('admin.php?page=' . $performance_slug);
?>

<div class="wrap site-essentials-wrap">
    <h1><?php esc_html_e('Performance', 'site-essentials'); ?></h1>

    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url($base_url . '&tab=tweaks'); ?>" class="nav-tab <?php echo $active_tab === 'tweaks' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('WordPress Tweaks', 'site-essentials'); ?>
        </a>
        <a href="<?php echo esc_url($base_url . '&tab=image-optimization'); ?>" class="nav-tab <?php echo $active_tab === 'image-optimization' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Image Optimization', 'site-essentials'); ?>
        </a>
        <a href="<?php echo esc_url($base_url . '&tab=asset-preloading'); ?>" class="nav-tab <?php echo $active_tab === 'asset-preloading' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Asset Preloading', 'site-essentials'); ?>
        </a>
        <a href="<?php echo esc_url($base_url . '&tab=monitoring'); ?>" class="nav-tab <?php echo $active_tab === 'monitoring' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Monitoring', 'site-essentials'); ?>
        </a>
    </nav>

    <div class="site-essentials-content">
        <?php if ($active_tab === 'tweaks'): ?>
            <?php if (!$tweaks_module || !is_object($tweaks_module)): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e('WordPress Tweaks module is not enabled.', 'site-essentials'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . \SiteEssentials\Core\Admin_UI::SETTINGS_PAGE_SLUG)); ?>">
                            <?php esc_html_e('Enable it in Settings → Modules', 'site-essentials'); ?>
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <div class="card se-module-settings-card" data-module-id="tweaks">
                    <?php $tweaks_module->render_settings(); ?>
                </div>
            <?php endif; ?>

        <?php elseif ($active_tab === 'image-optimization'): ?>
            <?php if (!$image_settings_available): ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('Image optimization settings require brighter-core (Brighter Tools) to be active.', 'site-essentials'); ?></p>
                </div>
            <?php else: ?>
                <div class="card se-module-settings-card">
                    <h2><?php esc_html_e('Image Optimization', 'site-essentials'); ?></h2>
                    <p><?php esc_html_e('Max upload dimension, resize on upload, JPEG quality, and registered image sizes (including OG 1200×630).', 'site-essentials'); ?></p>
                    <form method="post" action="options.php">
                        <?php settings_fields('brighter_optimisation_settings'); ?>
                        <?php do_settings_sections('brighter_optimisation_page'); ?>
                        <?php submit_button(esc_html__('Save Image Settings', 'site-essentials')); ?>
                    </form>
                </div>
            <?php endif; ?>

        <?php elseif ($active_tab === 'asset-preloading'): ?>
            <?php if (!class_exists('Brighter_Tweaks')): ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('Asset preloading requires brighter-core (Brighter Tweaks) to be active.', 'site-essentials'); ?></p>
                </div>
            <?php else: ?>
                <?php if (isset($_GET['tweaks_saved']) && $_GET['tweaks_saved'] === '1'): ?>
                    <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'site-essentials'); ?></p></div>
                <?php endif; ?>
                <div class="card se-module-settings-card">
                    <h2><?php esc_html_e('Asset Preloading', 'site-essentials'); ?></h2>
                    <p><?php esc_html_e('Preload featured images on single pages and define per-page preloads for critical assets.', 'site-essentials'); ?></p>
                    <form method="get" style="margin-bottom:1em;">
                        <input type="hidden" name="page" value="<?php echo esc_attr($performance_slug); ?>">
                        <input type="hidden" name="tab" value="asset-preloading">
                        <input type="search" name="s" value="<?php echo esc_attr(isset($_GET['s']) ? sanitize_text_field($_GET['s']) : ''); ?>" placeholder="<?php esc_attr_e('Search pages…', 'site-essentials'); ?>">
                        <button class="button"><?php esc_html_e('Search', 'site-essentials'); ?></button>
                    </form>
                    <?php
                    $asset_preload_url = admin_url('admin.php?page=site-essentials-essentials&tab=asset-preloading');
                    Brighter_Tweaks::render_preload_form(true, $asset_preload_url);
                    ?>
                </div>
            <?php endif; ?>

        <?php elseif ($active_tab === 'monitoring'): ?>
            <?php include SITE_ESSENTIALS_PATH . 'Views/performance-monitoring.php'; ?>

        <?php endif; ?>
    </div>
</div>
