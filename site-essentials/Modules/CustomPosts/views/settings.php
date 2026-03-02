<?php
/**
 * Module 11: Recommended Custom Posts & Fields - Settings View
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 * @version    2.1.0
 *
 * Variables available:
 * @var array $opts Current CPT options (customer_success_stories, include_categories,
 *                  include_tags, archive_slug, enable_faq, enable_author_extension,
 *                  enable_reviews)
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$author_extension_enabled = get_option('bw_author_extension_enabled', false);
$faq_enabled              = !empty($opts['enable_faq']);
$projects_enabled         = !empty($opts['customer_success_stories']);
$reviews_enabled          = !empty($opts['enable_reviews']);

// Import result notices (from CSV import redirect)
$import_status = isset($_GET['reviews_import']) ? sanitize_text_field($_GET['reviews_import']) : '';
$import_msg    = '';
if ($import_status === 'success') {
    $imported   = isset($_GET['imported']) ? intval($_GET['imported']) : 0;
    $skipped    = isset($_GET['skipped'])  ? intval($_GET['skipped'])  : 0;
    $import_msg = sprintf(
        /* translators: 1: number imported, 2: number skipped */
        esc_html__('%1$d reviews imported, %2$d skipped.', 'site-essentials'),
        $imported,
        $skipped
    );
} elseif ($import_status === 'error') {
    $import_msg = isset($_GET['error_msg']) ? rawurldecode(sanitize_text_field($_GET['error_msg'])) : __('Import failed.', 'site-essentials');
}
?>

<?php if ($import_status === 'success') : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html($import_msg); ?></p></div>
<?php elseif ($import_status === 'error') : ?>
    <div class="notice notice-error is-dismissible"><p><strong><?php esc_html_e('Import error:', 'site-essentials'); ?></strong> <?php echo esc_html($import_msg); ?></p></div>
<?php endif; ?>

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
                            <label for="cpt_enable_reviews">
                                <?php esc_html_e('Reviews', 'site-essentials'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="cpt_enable_reviews"
                                       name="cpt_options[enable_reviews]"
                                       value="1"
                                       <?php checked($reviews_enabled); ?>>
                                <?php esc_html_e('Enable Reviews (queryable SSOT — no archive/URLs)', 'site-essentials'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Registers bw_reviews CPT and bw_review_platform taxonomy. Reviews have no front-end archive or single URLs, but are queryable via WP_Query and Breakdance loops. Recommendation: Exclude from sitemap, add /reviews/ to robots.txt.', 'site-essentials'); ?>
                            </p>
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
                                <?php esc_html_e('Use WordPress Categories for Project posts', 'site-essentials'); ?>
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
                                <?php esc_html_e('Use WordPress Tags for Project posts', 'site-essentials'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cpt_archive_slug">
                                <?php esc_html_e('Rename Projects Archive', 'site-essentials'); ?>
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
                                <?php esc_html_e('Sets the archive URL slug and derives the admin menu label and breadcrumb label (e.g. "customer-success-stories" → "Customer Success Stories"). Rewrite rules are flushed on save.', 'site-essentials'); ?>
                            </p>
                            <p class="description">
                                <strong><?php esc_html_e('⚠️ Note:', 'site-essentials'); ?></strong>
                                <?php esc_html_e('Changing the slug on an established site will break existing URLs — set up redirects first.', 'site-essentials'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- =========================================
             CARD 4: REVIEWS SETTINGS + CSV IMPORT
             ========================================= -->
        <?php if ($reviews_enabled): ?>
        <div class="card" style="margin-bottom:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Reviews', 'site-essentials'); ?></h2>
            <p>
                <?php esc_html_e('Reviews are a SSOT data source. No public pages, no archive. Query via WP_Query using post_type=bw_reviews, combined with tax_query (bw_review_platform) and meta_query.', 'site-essentials'); ?>
            </p>
            <p>
                <?php
                printf(
                    /* translators: 1: opening link tag, 2: closing link tag */
                    esc_html__('Manage reviews in %1$sReviews → All Reviews%2$s.', 'site-essentials'),
                    '<a href="' . esc_url(admin_url('edit.php?post_type=bw_reviews')) . '">',
                    '</a>'
                );
                ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- =========================================
             CARD 5: AUTHOR EXTENSION
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

    <!-- =========================================
         CSV IMPORT FORM (separate from settings form — needs enctype multipart)
         Only shown when Reviews is enabled.
         ========================================= -->
    <?php if ($reviews_enabled): ?>
    <div class="card" style="margin-top:20px;margin-bottom:20px;">
        <h2 style="margin-top:0;"><?php esc_html_e('Import Reviews from CSV', 'site-essentials'); ?></h2>
        <p>
            <?php esc_html_e('Upload a CSV to bulk-import reviews. Rows where customer_name is empty are skipped. Platform is matched by name (case-insensitive) — new terms are created automatically if no match found.', 'site-essentials'); ?>
        </p>
        <p>
            <a href="#reviews-csv-template">
                <?php esc_html_e('Download CSV template', 'site-essentials'); ?>
            </a>
            <em style="color:#646970;"> — <?php esc_html_e('(Link will be replaced with Google Sheets template)', 'site-essentials'); ?></em>
        </p>
        <p><strong><?php esc_html_e('Required CSV headers (case-sensitive):', 'site-essentials'); ?></strong></p>
        <code style="display:block;padding:8px 12px;background:#f6f7f7;border:1px solid #ddd;border-radius:3px;margin-bottom:16px;font-size:12px;">
            customer_name, review_text, rating, platform, date, date_precision, verify_url, success_outcome, customer_detail, is_featured, review_excerpt
        </code>

        <form method="post"
              action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              enctype="multipart/form-data">
            <?php wp_nonce_field('bw_reviews_csv_import', 'bw_reviews_csv_nonce'); ?>
            <input type="hidden" name="action" value="site_essentials_reviews_csv_import">

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="bw_reviews_csv"><?php esc_html_e('CSV File', 'site-essentials'); ?></label>
                        </th>
                        <td>
                            <input type="file"
                                   id="bw_reviews_csv"
                                   name="bw_reviews_csv"
                                   accept=".csv,text/csv">
                            <p class="description"><?php esc_html_e('Upload a .csv file with the headers listed above.', 'site-essentials'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p>
                <button type="submit" class="button button-secondary">
                    <?php esc_html_e('Import Reviews from CSV', 'site-essentials'); ?>
                </button>
            </p>
        </form>
    </div>
    <?php endif; ?>

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
