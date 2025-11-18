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

        <!-- Sitemap Stats Widget -->
        <?php
        // Calculate sitemap stats
        $total_posts = 0;
        $post_type_counts = [];

        foreach ($sitemap_settings['post_types'] as $post_type) {
            $count = wp_count_posts($post_type);
            if (isset($count->publish)) {
                $post_type_obj = get_post_type_object($post_type);
                $post_type_counts[$post_type] = [
                    'label' => $post_type_obj ? $post_type_obj->labels->name : $post_type,
                    'count' => $count->publish,
                ];
                $total_posts += $count->publish;
            }
        }

        $cache_time = get_transient('se_sitemap_cache_time');
        $cache_age = $cache_time ? human_time_diff($cache_time, current_time('timestamp')) : 'Never';
        ?>
        <div class="card" style="margin: 0 0 20px 0; max-width: 400px;">
            <h3 style="margin-top: 0;">📊 Sitemap Stats</h3>
            <table class="widefat" style="border: none;">
                <tbody>
                    <tr>
                        <td style="border: none; padding: 5px 0;"><strong>Total URLs:</strong></td>
                        <td style="border: none; padding: 5px 0;"><?php echo number_format($total_posts); ?></td>
                    </tr>
                    <?php foreach ($post_type_counts as $data): ?>
                    <tr>
                        <td style="border: none; padding: 5px 0; padding-left: 20px;">↳ <?php echo esc_html($data['label']); ?>:</td>
                        <td style="border: none; padding: 5px 0;"><?php echo number_format($data['count']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td style="border: none; padding: 5px 0;"><strong>Last Generated:</strong></td>
                        <td style="border: none; padding: 5px 0;"><?php echo esc_html($cache_age); ?> ago</td>
                    </tr>
                </tbody>
            </table>

            <?php if (!empty($sitemap_settings['html_sitemap_enabled'])): ?>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                <p style="margin: 0 0 10px;"><strong>HTML Sitemap Shortcode:</strong></p>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="text"
                           id="se-html-sitemap-shortcode"
                           value="[site_essentials_sitemap]"
                           readonly
                           style="flex: 1; font-family: monospace; font-size: 13px;"
                           onclick="this.select()">
                    <button type="button"
                            class="button button-secondary"
                            id="se-copy-shortcode"
                            style="white-space: nowrap;">
                        Copy
                    </button>
                </div>
                <p class="description" style="margin-top: 5px;">
                    Paste this shortcode on any page to display an HTML sitemap.
                </p>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#se-copy-shortcode').on('click', function() {
                var $input = $('#se-html-sitemap-shortcode');
                $input.select();
                document.execCommand('copy');

                var $button = $(this);
                var originalText = $button.text();
                $button.text('Copied!');

                setTimeout(function() {
                    $button.text(originalText);
                }, 2000);
            });
        });
        </script>

        <table class="form-table" role="presentation">
            <tbody>
                <!-- Enable XML Sitemaps -->
                <tr>
                    <th scope="row">
                        <label for="sitemap_enabled">
                            <?php esc_html_e('Enable XML Sitemaps', 'site-essentials'); ?>
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

                <!-- Enable HTML Sitemap -->
                <tr>
                    <th scope="row">
                        <label for="html_sitemap_enabled">
                            <?php esc_html_e('Enable HTML Sitemap', 'site-essentials'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   id="html_sitemap_enabled"
                                   name="sitemap[html_sitemap_enabled]"
                                   value="1"
                                   <?php checked(!empty($sitemap_settings['html_sitemap_enabled'])); ?>>
                            <?php esc_html_e('Enable HTML sitemap with shortcode', 'site-essentials'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Use shortcode:', 'site-essentials'); ?>
                            <code>[site_essentials_sitemap]</code>
                            <br>
                            <?php esc_html_e('Displays a user-friendly sitemap grouped by post type with published and updated dates.', 'site-essentials'); ?>
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
                            <?php esc_html_e('Select which post types to include in sitemaps. Posts & Pages are ON by default.', 'site-essentials'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Taxonomies -->
                <tr>
                    <th scope="row">
                        <?php esc_html_e('Include Taxonomies', 'site-essentials'); ?>
                    </th>
                    <td>
                        <?php foreach ($all_taxonomies as $taxonomy => $taxonomy_obj): ?>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox"
                                       name="sitemap[taxonomies][]"
                                       value="<?php echo esc_attr($taxonomy); ?>"
                                       <?php checked(in_array($taxonomy, !empty($sitemap_settings['taxonomies']) ? $sitemap_settings['taxonomies'] : [], true)); ?>>
                                <?php echo esc_html($taxonomy_obj->labels->name); ?>
                                <small>(<?php echo esc_html($taxonomy); ?>)</small>
                            </label>
                        <?php endforeach; ?>
                        <p class="description">
                            <?php esc_html_e('Select which taxonomies to include in sitemaps. Categories are ON by default. Empty terms (0 posts) are automatically excluded to prevent 404 errors.', 'site-essentials'); ?>
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
