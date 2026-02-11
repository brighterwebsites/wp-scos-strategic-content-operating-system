<?php
/**
 * Custom Posts Module Settings View
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 * @version    1.0.0
 *
 * Variables available:
 * @var array $opts Current CPT options (customer_success_stories, include_categories, include_tags)
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="se-module-settings-cpt">
    <p><?php esc_html_e('Enable or disable recommended custom post types and taxonomy support. When disabled, the CPT is not registered.', 'site-essentials'); ?></p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('site_essentials_cpt', 'site_essentials_cpt_nonce'); ?>
        <input type="hidden" name="action" value="site_essentials_save_cpt">

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="cpt_customer_success_stories">
                            <?php esc_html_e('Customer Success Stories', 'site-essentials'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   id="cpt_customer_success_stories"
                                   name="cpt_options[customer_success_stories]"
                                   value="1"
                                   <?php checked(!empty($opts['customer_success_stories'])); ?>>
                            <?php esc_html_e('Register post type', 'site-essentials'); ?>
                            <code>projects</code>
                            <?php esc_html_e('(archive on, slug: projects)', 'site-essentials'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cpt_include_categories">
                            <?php esc_html_e('Inc WP Categories', 'site-essentials'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   id="cpt_include_categories"
                                   name="cpt_options[include_categories]"
                                   value="1"
                                   <?php checked(!empty($opts['include_categories'])); ?>>
                            <?php esc_html_e('Use WordPress Categories for Customer Success Stories (projects).', 'site-essentials'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cpt_include_tags">
                            <?php esc_html_e('Inc WP Tags', 'site-essentials'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   id="cpt_include_tags"
                                   name="cpt_options[include_tags]"
                                   value="1"
                                   <?php checked(!empty($opts['include_tags'])); ?>>
                            <?php esc_html_e('Use WordPress Tags for Customer Success Stories (projects).', 'site-essentials'); ?>
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Save CPT Settings', 'site-essentials'); ?>
            </button>
        </p>
    </form>

    <div class="notice notice-info inline">
        <p>
            <strong><?php esc_html_e('Note:', 'site-essentials'); ?></strong>
            <?php esc_html_e('After changing these settings, you may need to visit Settings → Permalinks and click Save to refresh rewrite rules.', 'site-essentials'); ?>
        </p>
    </div>
</div>
