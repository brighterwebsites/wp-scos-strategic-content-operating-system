<?php
/**
 * SEO Module Settings View
 *
 * @package    SiteEssentials
 * @subpackage Modules\Seo
 * @version    1.0.0
 *
 * Variables available:
 * @var array $sitemap_settings  Current sitemap settings
 * @var array $all_post_types    All public post types
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="se-module-settings-seo">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('site_essentials_seo', 'site_essentials_seo_nonce'); ?>
        <input type="hidden" name="action" value="site_essentials_save_seo">

        <!-- Sitemap Info Box -->
        <div class="notice notice-info inline" style="margin: 0 0 20px 0;">
            <p>
                <strong>📍 Your Sitemap URL:</strong>
                <a href="<?php echo esc_url(home_url('/sitemap.xml')); ?>" target="_blank">
                    <?php echo esc_url(home_url('/sitemap.xml')); ?>
                </a>
                <br>
                <small>Submit this URL to Google Search Console for indexing.</small>
            </p>
        </div>

        <table class="form-table" role="presentation">
            <tbody>
                <!-- Enable Sitemaps -->
                <tr>
                    <th scope="row">
                        <label for="sitemap_enabled">
                            <?php esc_html_e('Enable Sitemaps', 'site-essentials'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   id="sitemap_enabled"
                                   name="sitemap[enabled]"
                                   value="1"
                                   <?php checked(!empty($sitemap_settings['enabled'])); ?>>
                            <?php esc_html_e('Generate XML sitemaps', 'site-essentials'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Automatically generates XML sitemaps for your site content.', 'site-essentials'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Post Types -->
                <tr>
                    <th scope="row">
                        <?php esc_html_e('Include Post Types', 'site-essentials'); ?>
                    </th>
                    <td>
                        <?php foreach ($all_post_types as $post_type => $post_type_obj): ?>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox"
                                       name="sitemap[post_types][]"
                                       value="<?php echo esc_attr($post_type); ?>"
                                       <?php checked(in_array($post_type, $sitemap_settings['post_types'], true)); ?>>
                                <?php echo esc_html($post_type_obj->labels->name); ?>
                                <small>(<?php echo esc_html($post_type); ?>)</small>
                            </label>
                        <?php endforeach; ?>
                        <p class="description">
                            <?php esc_html_e('Select which post types to include in sitemaps.', 'site-essentials'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Include Images -->
                <tr>
                    <th scope="row">
                        <label for="sitemap_include_images">
                            <?php esc_html_e('Include Images', 'site-essentials'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   id="sitemap_include_images"
                                   name="sitemap[include_images]"
                                   value="1"
                                   <?php checked(!empty($sitemap_settings['include_images'])); ?>>
                            <?php esc_html_e('Include images in sitemaps (image sitemap)', 'site-essentials'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Adds featured images and content images to sitemap for better image SEO.', 'site-essentials'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Entries Per Sitemap -->
                <tr>
                    <th scope="row">
                        <label for="sitemap_entries">
                            <?php esc_html_e('Entries Per Sitemap', 'site-essentials'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number"
                               id="sitemap_entries"
                               name="sitemap[entries_per_sitemap]"
                               value="<?php echo esc_attr($sitemap_settings['entries_per_sitemap']); ?>"
                               min="100"
                               max="50000"
                               step="100"
                               class="small-text">
                        <p class="description">
                            <?php esc_html_e('Maximum number of URLs per sitemap file. Google recommends 50,000 max. Default: 2000', 'site-essentials'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Exclude Posts -->
                <tr>
                    <th scope="row">
                        <label for="sitemap_exclude_ids">
                            <?php esc_html_e('Exclude Post IDs', 'site-essentials'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text"
                               id="sitemap_exclude_ids"
                               name="sitemap[exclude_ids]"
                               value="<?php echo esc_attr(implode(',', $sitemap_settings['exclude_ids'])); ?>"
                               class="regular-text"
                               placeholder="123,456,789">
                        <p class="description">
                            <?php esc_html_e('Comma-separated list of post IDs to exclude from sitemaps.', 'site-essentials'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Save SEO Settings', 'site-essentials'); ?>
            </button>
            <button type="button" class="button" id="se-clear-sitemap-cache">
                <?php esc_html_e('Clear Sitemap Cache', 'site-essentials'); ?>
            </button>
        </p>
    </form>

    <!-- Cache Clear JavaScript -->
    <script>
    jQuery(document).ready(function($) {
        $('#se-clear-sitemap-cache').on('click', function() {
            if (!confirm('Clear all sitemap caches? Sitemaps will be regenerated on next request.')) {
                return;
            }

            const $button = $(this);
            $button.prop('disabled', true).text('Clearing...');

            $.post(ajaxurl, {
                action: 'site_essentials_clear_sitemap_cache',
                nonce: '<?php echo wp_create_nonce('site_essentials_seo'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Sitemap cache cleared successfully!');
                } else {
                    alert('Failed to clear cache: ' + (response.data || 'Unknown error'));
                }
                $button.prop('disabled', false).text('Clear Sitemap Cache');
            });
        });
    });
    </script>

    <!-- Help Section -->
    <div class="card" style="margin-top: 20px;">
        <h3><?php esc_html_e('How to Use', 'site-essentials'); ?></h3>
        <ol>
            <li><strong><?php esc_html_e('Enable Sitemaps', 'site-essentials'); ?></strong> - <?php esc_html_e('Check the box above', 'site-essentials'); ?></li>
            <li><strong><?php esc_html_e('Select Post Types', 'site-essentials'); ?></strong> - <?php esc_html_e('Choose which content to include', 'site-essentials'); ?></li>
            <li><strong><?php esc_html_e('Save Settings', 'site-essentials'); ?></strong></li>
            <li><strong><?php esc_html_e('Visit Sitemap', 'site-essentials'); ?></strong> - <a href="<?php echo esc_url(home_url('/sitemap.xml')); ?>" target="_blank"><?php echo esc_url(home_url('/sitemap.xml')); ?></a></li>
            <li><strong><?php esc_html_e('Submit to Google', 'site-essentials'); ?></strong> - <?php esc_html_e('Add to Google Search Console', 'site-essentials'); ?></li>
        </ol>

        <h4><?php esc_html_e('Conflict Detection', 'site-essentials'); ?></h4>
        <p>
            <?php esc_html_e('If you have SEOPress, Yoast, or Rank Math installed, you may want to disable their sitemap features to avoid conflicts.', 'site-essentials'); ?>
        </p>
    </div>
</div>
