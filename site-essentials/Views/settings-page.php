<?php
/**
 * Settings Page Template
 *
 * Main settings page for Site Essentials.
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
        <a href="?page=<?php echo esc_attr(\SiteEssentials\Core\Admin_UI::PAGE_SLUG); ?>&tab=modules"
           class="nav-tab <?php echo $active_tab === 'modules' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Modules', 'site-essentials'); ?>
        </a>
        <a href="?page=<?php echo esc_attr(\SiteEssentials\Core\Admin_UI::PAGE_SLUG); ?>&tab=import-export"
           class="nav-tab <?php echo $active_tab === 'import-export' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Import/Export', 'site-essentials'); ?>
        </a>
        <a href="?page=<?php echo esc_attr(\SiteEssentials\Core\Admin_UI::PAGE_SLUG); ?>&tab=cache"
           class="nav-tab <?php echo $active_tab === 'cache' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Cache', 'site-essentials'); ?>
        </a>
        <a href="?page=<?php echo esc_attr(\SiteEssentials\Core\Admin_UI::PAGE_SLUG); ?>&tab=debug"
           class="nav-tab <?php echo $active_tab === 'debug' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Debug', 'site-essentials'); ?>
        </a>
    </h2>

    <div class="site-essentials-content">
        <?php if ($active_tab === 'modules'): ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('site_essentials');
                do_settings_sections(\SiteEssentials\Core\Admin_UI::PAGE_SLUG);
                ?>
            </form>

        <?php elseif ($active_tab === 'import-export'): ?>
            <div class="site-essentials-import-export">
                <div class="card">
                    <h2><?php esc_html_e('Export Settings', 'site-essentials'); ?></h2>
                    <p><?php esc_html_e('Export your Site Essentials settings as JSON.', 'site-essentials'); ?></p>
                    <button type="button" class="button button-primary" id="se-export-settings">
                        <?php esc_html_e('Export Settings', 'site-essentials'); ?>
                    </button>
                </div>

                <div class="card">
                    <h2><?php esc_html_e('Import Settings', 'site-essentials'); ?></h2>
                    <p><?php esc_html_e('Import settings from a JSON file.', 'site-essentials'); ?></p>
                    <textarea id="se-import-json" rows="10" style="width: 100%; font-family: monospace;"></textarea>
                    <p>
                        <label>
                            <input type="checkbox" id="se-import-merge" checked>
                            <?php esc_html_e('Merge with existing settings (unchecked = replace)', 'site-essentials'); ?>
                        </label>
                    </p>
                    <button type="button" class="button button-primary" id="se-import-settings">
                        <?php esc_html_e('Import Settings', 'site-essentials'); ?>
                    </button>
                </div>
            </div>

        <?php elseif ($active_tab === 'cache'): ?>
            <div class="site-essentials-cache">
                <div class="card">
                    <h2><?php esc_html_e('Cache Statistics', 'site-essentials'); ?></h2>
                    <?php
                    $stats = \SiteEssentials\Core\Cache_Helper::get_stats();
                    ?>
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <th><?php esc_html_e('Object Cache Enabled', 'site-essentials'); ?></th>
                                <td><?php echo $stats['object_cache_enabled'] ? '✓ Yes' : '✗ No (using transients)'; ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Cache Group', 'site-essentials'); ?></th>
                                <td><?php echo esc_html($stats['cache_group']); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Default Duration', 'site-essentials'); ?></th>
                                <td><?php echo esc_html($stats['default_duration']); ?> <?php esc_html_e('seconds', 'site-essentials'); ?></td>
                            </tr>
                            <?php if (isset($stats['transient_count'])): ?>
                            <tr>
                                <th><?php esc_html_e('Transient Count', 'site-essentials'); ?></th>
                                <td><?php echo esc_html($stats['transient_count']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <h2><?php esc_html_e('Clear Cache', 'site-essentials'); ?></h2>
                    <p><?php esc_html_e('Clear all Site Essentials cache.', 'site-essentials'); ?></p>
                    <button type="button" class="button button-secondary" id="se-clear-cache">
                        <?php esc_html_e('Clear All Cache', 'site-essentials'); ?>
                    </button>
                </div>
            </div>

        <?php elseif ($active_tab === 'debug'): ?>
            <div class="site-essentials-debug">
                <div class="card">
                    <h2><?php esc_html_e('Loaded Modules', 'site-essentials'); ?></h2>
                    <?php
                    $loaded_modules = \SiteEssentials\Core\Module_Loader::get_loaded_modules();
                    if (empty($loaded_modules)): ?>
                        <p><?php esc_html_e('No modules loaded.', 'site-essentials'); ?></p>
                    <?php else: ?>
                        <ul>
                        <?php foreach ($loaded_modules as $module_id => $module): ?>
                            <li>
                                <strong><?php echo esc_html($module::get_name()); ?></strong>
                                (<?php echo esc_html($module_id); ?>) -
                                Version <?php echo esc_html($module::get_version()); ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2><?php esc_html_e('Failed Modules', 'site-essentials'); ?></h2>
                    <?php
                    $failed_modules = \SiteEssentials\Core\Module_Loader::get_failed_modules();
                    if (empty($failed_modules)): ?>
                        <p><?php esc_html_e('No module failures.', 'site-essentials'); ?></p>
                    <?php else: ?>
                        <ul>
                        <?php foreach ($failed_modules as $module_id => $reason): ?>
                            <li>
                                <strong><?php echo esc_html($module_id); ?></strong>:
                                <?php echo esc_html($reason); ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2><?php esc_html_e('System Info', 'site-essentials'); ?></h2>
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <th><?php esc_html_e('Site Essentials Version', 'site-essentials'); ?></th>
                                <td><?php echo esc_html(SITE_ESSENTIALS_VERSION); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('WordPress Version', 'site-essentials'); ?></th>
                                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('PHP Version', 'site-essentials'); ?></th>
                                <td><?php echo esc_html(PHP_VERSION); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Object Cache', 'site-essentials'); ?></th>
                                <td><?php echo wp_using_ext_object_cache() ? '✓ Enabled' : '✗ Disabled'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
