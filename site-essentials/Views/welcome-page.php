<?php
/**
 * Site Essentials Welcome Page
 *
 * @package    SiteEssentials
 * @subpackage Views
 * @version    1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$current_branch = 'Unknown';
if (file_exists(SITE_ESSENTIALS_PATH . '../.git/HEAD')) {
    $head_content = file_get_contents(SITE_ESSENTIALS_PATH . '../.git/HEAD');
    if (preg_match('#ref: refs/heads/(.+)#', $head_content, $matches)) {
        $current_branch = $matches[1];
    }
}
?>

<div class="wrap site-essentials-wrap site-essentials-welcome">
    <div class="se-welcome-header">
        <h1><?php esc_html_e('Site Essentials', 'site-essentials'); ?></h1>
        <p class="se-version-info">
            Version <?php echo esc_html(SITE_ESSENTIALS_VERSION); ?>
            <?php if ($current_branch !== 'Unknown'): ?>
                <span class="se-branch">Branch: <code><?php echo esc_html($current_branch); ?></code></span>
            <?php endif; ?>
        </p>
    </div>

    <div class="se-welcome-grid">
        <div class="se-welcome-card">
            <div class="dashicons dashicons-admin-site-alt3"></div>
            <h2><?php esc_html_e('SEO Module', 'site-essentials'); ?></h2>
            <p><?php esc_html_e('Sitemaps, on-page SEO, archive SEO, robots/LLMs.txt, image SEO, and redirections.', 'site-essentials'); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=site-essentials-seo')); ?>" class="button button-primary">
                <?php esc_html_e('Configure SEO', 'site-essentials'); ?>
            </a>
        </div>

        <div class="se-welcome-card">
            <div class="dashicons dashicons-performance"></div>
            <h2><?php esc_html_e('Performance', 'site-essentials'); ?></h2>
            <p><?php esc_html_e('WordPress Tweaks, Image Optimization, and Asset Preloading.', 'site-essentials'); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=site-essentials-essentials')); ?>" class="button button-primary">
                <?php esc_html_e('Performance', 'site-essentials'); ?>
            </a>
        </div>

        <div class="se-welcome-card">
            <div class="dashicons dashicons-portfolio"></div>
            <h2><?php esc_html_e('Custom Posts & Fields', 'site-essentials'); ?></h2>
            <p><?php esc_html_e('Manage recommended custom post types (FAQ, Projects/Success Stories) and extended field sets (Author Extension).', 'site-essentials'); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=site-essentials-cpt')); ?>" class="button button-primary">
                <?php esc_html_e('Manage Custom Posts & Fields', 'site-essentials'); ?>
            </a>
        </div>

        <div class="se-welcome-card">
            <div class="dashicons dashicons-building"></div>
            <h2><?php esc_html_e('Business Info', 'site-essentials'); ?></h2>
            <p><?php esc_html_e('Contact name, phone, email and business details for privacy policy and schema.', 'site-essentials'); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=site-essentials-business-info')); ?>" class="button button-primary">
                <?php esc_html_e('Business Info', 'site-essentials'); ?>
            </a>
        </div>

        <div class="se-welcome-card">
            <div class="dashicons dashicons-admin-settings"></div>
            <h2><?php esc_html_e('Settings', 'site-essentials'); ?></h2>
            <p><?php esc_html_e('Manage modules, import/export settings, and clear cache.', 'site-essentials'); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=site-essentials-settings')); ?>" class="button button-primary">
                <?php esc_html_e('Open Settings', 'site-essentials'); ?>
            </a>
        </div>
    </div>

    <div class="se-welcome-footer">
        <h3><?php esc_html_e('Quick Stats', 'site-essentials'); ?></h3>
        <?php
        $loaded_modules = \SiteEssentials\Core\Module_Loader::get_loaded_modules();
        $available_modules = \SiteEssentials\Core\Module_Loader::get_available_modules();
        ?>
        <ul class="se-stats-list">
            <li>
                <span class="dashicons dashicons-yes-alt"></span>
                <strong><?php echo count($loaded_modules); ?></strong>
                <?php esc_html_e('modules loaded', 'site-essentials'); ?>
            </li>
            <li>
                <span class="dashicons dashicons-admin-plugins"></span>
                <strong><?php echo count($available_modules); ?></strong>
                <?php esc_html_e('modules available', 'site-essentials'); ?>
            </li>
        </ul>
    </div>
</div>

<style>
.site-essentials-welcome {
    max-width: 1200px;
}

.se-welcome-header {
    text-align: center;
    margin: 40px 0;
}

.se-welcome-header h1 {
    font-size: 42px;
    font-weight: 300;
    margin-bottom: 10px;
}

.se-version-info {
    font-size: 14px;
    color: #666;
}

.se-branch {
    margin-left: 15px;
    padding: 3px 8px;
    background: #f0f0f1;
    border-radius: 3px;
    font-size: 12px;
}

.se-branch code {
    background: none;
    padding: 0;
    color: #2271b1;
}

.se-welcome-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 40px 0;
}

.se-welcome-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 30px;
    text-align: center;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.se-welcome-card .dashicons {
    font-size: 60px;
    width: 60px;
    height: 60px;
    color: #2271b1;
    margin-bottom: 15px;
}

.se-welcome-card h2 {
    font-size: 20px;
    margin: 15px 0;
}

.se-welcome-card p {
    color: #646970;
    margin-bottom: 20px;
}

.se-welcome-footer {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 25px;
    margin-top: 40px;
}

.se-welcome-footer h3 {
    margin-top: 0;
    font-size: 18px;
}

.se-stats-list {
    list-style: none;
    margin: 15px 0 0 0;
    padding: 0;
    display: flex;
    gap: 30px;
}

.se-stats-list li {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.se-stats-list .dashicons {
    color: #00a32a;
    font-size: 20px;
    width: 20px;
    height: 20px;
}
</style>
