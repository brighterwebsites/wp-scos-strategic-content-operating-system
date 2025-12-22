<?php
/**
 * Essentials Page Template
 *
 * Page for WordPress Tweaks and other essential modules.
 *
 * @package    SiteEssentials
 * @subpackage Views
 * @version    1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap site-essentials-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <h2 class="nav-tab-wrapper">
        <a href="?page=<?php echo esc_attr(\SiteEssentials\Core\Admin_UI::ESSENTIALS_PAGE_SLUG); ?>&tab=tweaks"
           class="nav-tab <?php echo $active_tab === 'tweaks' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('WordPress Tweaks', 'site-essentials'); ?>
        </a>
        <a href="#" class="nav-tab" style="opacity: 0.5; cursor: not-allowed;" onclick="return false;">
            <?php esc_html_e('Tab 2', 'site-essentials'); ?> <small>(Coming Soon)</small>
        </a>
        <a href="#" class="nav-tab" style="opacity: 0.5; cursor: not-allowed;" onclick="return false;">
            <?php esc_html_e('Tab 3', 'site-essentials'); ?> <small>(Coming Soon)</small>
        </a>
    </h2>

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
        <?php endif; ?>
    </div>
</div>
