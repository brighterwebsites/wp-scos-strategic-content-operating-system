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
    <h1>
        <?php echo esc_html(get_admin_page_title()); ?>
        <?php
        $deploy_info = \SiteEssentials\Core\Admin_UI::get_deployment_info();
        ?>
        <span style="font-size: 14px; font-weight: normal; color: #666; margin-left: 15px;">
            v<?php echo esc_html($deploy_info['version']); ?> |
            <code><?php echo esc_html($deploy_info['commit']); ?></code> |
            Deployed: <?php echo esc_html($deploy_info['deployed_at']); ?>
        </span>
    </h1>

    <h2 class="nav-tab-wrapper">
        <a href="?page=<?php echo esc_attr(\SiteEssentials\Core\Admin_UI::SETTINGS_PAGE_SLUG); ?>&tab=modules"
           class="nav-tab <?php echo $active_tab === 'modules' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Modules', 'site-essentials'); ?>
        </a>
        <a href="?page=<?php echo esc_attr(\SiteEssentials\Core\Admin_UI::SETTINGS_PAGE_SLUG); ?>&tab=import-export"
           class="nav-tab <?php echo $active_tab === 'import-export' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Import/Export', 'site-essentials'); ?>
        </a>
        <a href="?page=<?php echo esc_attr(\SiteEssentials\Core\Admin_UI::SETTINGS_PAGE_SLUG); ?>&tab=api"
           class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('API Settings', 'site-essentials'); ?>
        </a>
        <a href="?page=<?php echo esc_attr(\SiteEssentials\Core\Admin_UI::SETTINGS_PAGE_SLUG); ?>&tab=ai-keys"
           class="nav-tab <?php echo $active_tab === 'ai-keys' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('AI API Keys', 'site-essentials'); ?>
        </a>
        <a href="?page=<?php echo esc_attr(\SiteEssentials\Core\Admin_UI::SETTINGS_PAGE_SLUG); ?>&tab=email"
           class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Email', 'site-essentials'); ?>
        </a>
        <a href="?page=<?php echo esc_attr(\SiteEssentials\Core\Admin_UI::SETTINGS_PAGE_SLUG); ?>&tab=cache"
           class="nav-tab <?php echo $active_tab === 'cache' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Cache', 'site-essentials'); ?>
        </a>
        <a href="?page=<?php echo esc_attr(\SiteEssentials\Core\Admin_UI::SETTINGS_PAGE_SLUG); ?>&tab=debug"
           class="nav-tab <?php echo $active_tab === 'debug' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Debug', 'site-essentials'); ?>
        </a>
    </h2>

    <div class="site-essentials-content">
        <?php if ($active_tab === 'modules'): ?>
            <p><?php esc_html_e('Enable or disable modules below. Module settings are available on their respective pages (SEO, Essentials, etc.).', 'site-essentials'); ?></p>

            <!-- Module Toggle Cards -->
            <?php
            settings_fields('site_essentials');
            do_settings_sections(\SiteEssentials\Core\Admin_UI::PAGE_SLUG);
            ?>

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

        <?php elseif ($active_tab === 'api'): ?>
            <?php include SITE_ESSENTIALS_PATH . 'Views/settings-api.php'; ?>

        <?php elseif ($active_tab === 'ai-keys'): ?>
            <?php
            $anthropic_key   = get_option( 'bw_anthropic_api_key', '' );
            $anthropic_model = get_option( 'bw_anthropic_model', '' );
            ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'scos_save_ai_keys', 'scos_ai_keys_nonce' ); ?>
                <input type="hidden" name="action" value="scos_save_ai_keys">

                <h2><?php esc_html_e( 'AI API Keys', 'site-essentials' ); ?></h2>
                <p class="description" style="margin-bottom:20px;">
                    <?php esc_html_e( 'Third-party AI provider credentials used across Site Essentials modules. These keys are stored as WordPress options and never exposed to the front end.', 'site-essentials' ); ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="bw_anthropic_api_key"><?php esc_html_e( 'Anthropic API Key', 'site-essentials' ); ?></label>
                        </th>
                        <td>
                            <input type="password" id="bw_anthropic_api_key" name="bw_anthropic_api_key"
                                value="<?php echo esc_attr( $anthropic_key ); ?>"
                                class="regular-text code" autocomplete="new-password"
                                style="width:100%;max-width:560px;" />
                            <p class="description">
                                <?php esc_html_e( 'Used by Social Amplification (Postly.ai caption generation via Claude) and future AI integrations. Obtain from ', 'site-essentials' ); ?>
                                <a href="https://console.anthropic.com/" target="_blank" rel="noopener">console.anthropic.com</a>.
                            </p>
                            <?php if ( $anthropic_key ) : ?>
                                <p style="color:#16a34a;font-size:13px;margin-top:6px;">
                                    &#10003; <?php esc_html_e( 'Key is saved. Enter a new value to replace it.', 'site-essentials' ); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="bw_anthropic_model"><?php esc_html_e( 'Claude Model', 'site-essentials' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="bw_anthropic_model" name="bw_anthropic_model"
                                value="<?php echo esc_attr( $anthropic_model ); ?>"
                                class="regular-text code"
                                style="width:100%;max-width:560px;"
                                placeholder="claude-haiku-4-5-20251001" />
                            <p class="description">
                                <?php esc_html_e( 'Default: ', 'site-essentials' ); ?><code>claude-haiku-4-5-20251001</code>
                                <?php esc_html_e( '(Claude Haiku 4.5). Legacy Haiku 3 IDs are retired. Enter the exact API model string for your plan, e.g.', 'site-essentials' ); ?>
                                <code>claude-3-5-sonnet-20241022</code>,
                                <code>claude-3-7-sonnet-20250219</code>.
                                <?php esc_html_e( 'This integration does not send temperature or top_p (Claude 4 disallows setting both). ', 'site-essentials' ); ?>
                                <?php esc_html_e( 'If you get a 404 error the model name is wrong or not on your plan — check ', 'site-essentials' ); ?>
                                <a href="https://docs.anthropic.com/en/docs/about-claude/models" target="_blank" rel="noopener">docs.anthropic.com/models</a>.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save AI API Keys', 'site-essentials' ) ); ?>
            </form>

        <?php elseif ($active_tab === 'email'): ?>
            <?php include SITE_ESSENTIALS_PATH . 'Modules/EmailDelivery/views/settings.php'; ?>

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
