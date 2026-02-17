<?php
/**
 * Module 11: Recommended Custom Posts & Fields - Settings View
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 * @version    2.0.0
 *
 * Variables available:
 * @var array $opts Current CPT options (customer_success_stories, include_categories, include_tags, archive_slug, enable_faq, enable_author_extension)
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$author_extension_enabled = get_option('bw_author_extension_enabled', false);
$faq_enabled = !empty($opts['enable_faq']);
$projects_enabled = !empty($opts['customer_success_stories']);
?>

<div class="se-module-settings-cpt">
    <p><?php esc_html_e('Enable or disable recommended custom post types, taxonomy support, and extended field sets. When disabled, the CPT is not registered and extended fields are not loaded.', 'site-essentials'); ?></p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:20px;">
        <?php wp_nonce_field('site_essentials_cpt', 'site_essentials_cpt_nonce'); ?>
        <input type="hidden" name="action" value="site_essentials_save_cpt">

        <!-- =========================================
             CARD 1: MASTER TOGGLES
             ========================================= -->
        <div class="card" style="margin-bottom:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Master Toggles', 'site-essentials'); ?></h2>
            <p><?php esc_html_e('Enable or disable custom post types and extended field sets.', 'site-essentials'); ?></p>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="cpt_enable_faq">
                                <?php esc_html_e('FAQ Custom Posts', 'site-essentials'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="cpt_enable_faq"
                                       name="cpt_options[enable_faq]"
                                       value="1"
                                       <?php checked($faq_enabled); ?>
                                       disabled>
                                <?php esc_html_e('Enable FAQ System (see Module 8)', 'site-essentials'); ?>
                                <em style="color:#646970;"> — <?php esc_html_e('Coming soon', 'site-essentials'); ?></em>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cpt_customer_success_stories">
                                <?php esc_html_e('Project/Success Stories', 'site-essentials'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="cpt_customer_success_stories"
                                       name="cpt_options[customer_success_stories]"
                                       value="1"
                                       <?php checked($projects_enabled); ?>>
                                <?php esc_html_e('Enable Project Custom Posts', 'site-essentials'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cpt_enable_author_extension">
                                <?php esc_html_e('Author Extension', 'site-essentials'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="cpt_enable_author_extension"
                                       name="cpt_options[enable_author_extension]"
                                       value="1"
                                       <?php checked($author_extension_enabled); ?>>
                                <?php esc_html_e('Enable extended author user meta fields (see Module 15)', 'site-essentials'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Adds E-E-A-T fields to user profiles for structured Person schema.', 'site-essentials'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- =========================================
             CARD 2: FAQ CUSTOM POSTS (Placeholder)
             ========================================= -->
        <?php if ($faq_enabled): ?>
        <div class="card" style="margin-bottom:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('FAQ Custom Posts', 'site-essentials'); ?></h2>
            <p><em><?php esc_html_e('Settings TBD — see Module 8 for current FAQ system details.', 'site-essentials'); ?></em></p>
        </div>
        <?php endif; ?>

        <!-- =========================================
             CARD 3: PROJECT/SUCCESS STORIES SETTINGS
             ========================================= -->
        <?php if ($projects_enabled): ?>
        <div class="card" style="margin-bottom:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Project/Success Stories', 'site-essentials'); ?></h2>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="cpt_include_categories">
                                <?php esc_html_e('WordPress Categories', 'site-essentials'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="cpt_include_categories"
                                       name="cpt_options[include_categories]"
                                       value="1"
                                       <?php checked(!empty($opts['include_categories'])); ?>>
                                <?php esc_html_e('Use WordPress Categories for Customer Success Stories (projects)', 'site-essentials'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cpt_include_tags">
                                <?php esc_html_e('WordPress Tags', 'site-essentials'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="cpt_include_tags"
                                       name="cpt_options[include_tags]"
                                       value="1"
                                       <?php checked(!empty($opts['include_tags'])); ?>>
                                <?php esc_html_e('Use WordPress Tags for Customer Success Stories (projects)', 'site-essentials'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cpt_archive_slug">
                                <?php esc_html_e('Archive Slug', 'site-essentials'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="cpt_archive_slug"
                                   name="cpt_options[archive_slug]"
                                   value="<?php echo esc_attr(!empty($opts['archive_slug']) ? $opts['archive_slug'] : 'projects'); ?>"
                                   class="regular-text"
                                   placeholder="projects">
                            <p class="description">
                                <strong><?php esc_html_e('⚠️ Note:', 'site-essentials'); ?></strong>
                                <?php esc_html_e('After changing, visit Settings → Permalinks and click Save to refresh rewrite rules. Changing the slug on an established site will break existing URLs — set up redirects first.', 'site-essentials'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- =========================================
             CARD 4: AUTHOR EXTENSION
             ========================================= -->
        <?php if ($author_extension_enabled): ?>
        <div class="card" style="margin-bottom:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Author Extension', 'site-essentials'); ?></h2>
            <p><?php esc_html_e('Extended author metadata fields for E-E-A-T signals and structured Person schema.', 'site-essentials'); ?></p>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Advanced Author E-E-A-T Signals', 'site-essentials'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" disabled checked="checked">
                                <?php esc_html_e('Enable Advanced Author E-E-A-T Signals', 'site-essentials'); ?>
                                <em style="color:#646970;"> — <?php esc_html_e('Future feature', 'site-essentials'); ?></em>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Additional fields: knowsAbout, alumniOf, hasCredential (repeaters)', 'site-essentials'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Manage Author Data', 'site-essentials'); ?>
                        </th>
                        <td>
                            <a href="<?php echo esc_url(admin_url('users.php')); ?>" class="button">
                                <?php esc_html_e('Manage Author Inputs in Users', 'site-essentials'); ?>
                            </a>
                            <p class="description">
                                <?php esc_html_e('Edit user profiles to add job title, organization, LinkedIn, etc.', 'site-essentials'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Schema Integration', 'site-essentials'); ?>
                        </th>
                        <td>
                            <button type="button" class="button" disabled>
                                <?php esc_html_e('Enable Advanced Author Schema Properties in SEO Suite → Schema', 'site-essentials'); ?>
                            </button>
                            <em style="color:#646970;"> — <?php esc_html_e('Available when Schema module (Module 7) is built', 'site-essentials'); ?></em>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Save Settings', 'site-essentials'); ?>
            </button>
        </p>
    </form>
</div>

<style>
    .se-module-settings-cpt .card {
        padding: 15px 20px;
    }
    .se-module-settings-cpt .card h2 {
        font-size: 18px;
        margin: 0 0 10px 0;
    }
    .se-module-settings-cpt .form-table th {
        width: 250px;
    }
</style>
