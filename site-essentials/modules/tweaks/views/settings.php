<?php
/**
 * Tweaks Module Settings View
 *
 * @package    SiteEssentials
 * @subpackage Modules\Tweaks
 * @version    1.0.0
 *
 * Variables available:
 * @var array $tweaks Current tweak settings
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$tweak_definitions = [
    'disable_emojis' => [
        'label' => __('Disable Emojis', 'site-essentials'),
        'description' => __('Removes WordPress emoji support (saves HTTP requests)', 'site-essentials'),
    ],
    'remove_jquery_migrate' => [
        'label' => __('Remove jQuery Migrate', 'site-essentials'),
        'description' => __('Removes jQuery Migrate (only if you don\'t need it for old themes/plugins)', 'site-essentials'),
    ],
    'disable_xmlrpc' => [
        'label' => __('Disable XML-RPC', 'site-essentials'),
        'description' => __('Disables XML-RPC (improves security if you don\'t use it)', 'site-essentials'),
    ],
    'remove_rsd_link' => [
        'label' => __('Remove RSD Link', 'site-essentials'),
        'description' => __('Removes Really Simple Discovery link from head', 'site-essentials'),
    ],
    'remove_wlw_link' => [
        'label' => __('Remove Windows Live Writer Link', 'site-essentials'),
        'description' => __('Removes Windows Live Writer manifest link', 'site-essentials'),
    ],
    'remove_wp_version' => [
        'label' => __('Remove WordPress Version', 'site-essentials'),
        'description' => __('Removes WordPress version meta tag (security)', 'site-essentials'),
    ],
    'optimize_heartbeat' => [
        'label' => __('Optimize Heartbeat', 'site-essentials'),
        'description' => __('Slows Heartbeat API from 15s to 60s, disables on front-end', 'site-essentials'),
    ],
    'remove_query_strings' => [
        'label' => __('Remove Query Strings', 'site-essentials'),
        'description' => __('Removes version query strings from CSS/JS (better caching)', 'site-essentials'),
    ],
    'disable_embeds' => [
        'label' => __('Disable Embeds', 'site-essentials'),
        'description' => __('Disables WordPress embed functionality if not needed', 'site-essentials'),
    ],
    'disable_rest_api' => [
        'label' => __('Disable REST API for Non-Logged Users', 'site-essentials'),
        'description' => __('Restricts REST API access to logged-in users only', 'site-essentials'),
    ],
];
?>

<div class="se-module-settings-tweaks">
    <p><?php esc_html_e('Enable or disable individual WordPress tweaks. Disabled tweaks don\'t load any code.', 'site-essentials'); ?></p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('site_essentials_tweaks', 'site_essentials_tweaks_nonce'); ?>
        <input type="hidden" name="action" value="site_essentials_save_tweaks">

        <table class="form-table" role="presentation">
            <tbody>
                <?php foreach ($tweak_definitions as $tweak_id => $tweak_data): ?>
                    <tr>
                        <th scope="row">
                            <label for="tweak_<?php echo esc_attr($tweak_id); ?>">
                                <?php echo esc_html($tweak_data['label']); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="tweak_<?php echo esc_attr($tweak_id); ?>"
                                       name="enabled_tweaks[<?php echo esc_attr($tweak_id); ?>]"
                                       value="1"
                                       <?php checked(!empty($tweaks[$tweak_id])); ?>>
                                <?php echo esc_html($tweak_data['description']); ?>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Save Tweaks Settings', 'site-essentials'); ?>
            </button>
        </p>
    </form>

    <div class="notice notice-info inline">
        <p>
            <strong><?php esc_html_e('Note:', 'site-essentials'); ?></strong>
            <?php esc_html_e('Changes take effect immediately. If something breaks, simply uncheck the problematic tweak.', 'site-essentials'); ?>
        </p>
    </div>
</div>
