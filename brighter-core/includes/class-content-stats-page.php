<?php
/**
 * Content Statistics Dashboard Page
 *
 * File: class-content-stats-page.php
 * Version: 1.0.0
 *
 * Responsibilities:
 * - Display content analysis statistics in dedicated admin page
 * - Show word count, links, images, H2s for all posts
 * - Sortable, filterable table with export capability
 * - Identifies posts needing analysis
 */

if (!defined('ABSPATH')) exit;

class BW_Content_Stats_Page {

    /**
     * Initialize stats page
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_page'], 99);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Register admin page under each post type
     */
    public static function register_page() {
        $post_types = bw_cs_post_types();

        foreach ($post_types as $post_type) {
            $post_type_obj = get_post_type_object($post_type);

            // Skip if post type doesn't exist
            if (!$post_type_obj) continue;

            // Determine parent slug
            $parent_slug = 'edit.php';
            if ($post_type !== 'post') {
                $parent_slug = 'edit.php?post_type=' . $post_type;
            }

            add_submenu_page(
                $parent_slug,
                __('Content Statistics', 'brighterwebsites'),
                __('Content Stats', 'brighterwebsites'),
                'edit_posts',
                'bw-content-stats-' . $post_type,
                [__CLASS__, 'render_page']
            );
        }
    }

    /**
     * Enqueue page assets
     */
    public static function enqueue_assets($hook) {
        // Check if this is any of our content stats pages
        if (strpos($hook, '_page_bw-content-stats-') === false) return;

        wp_enqueue_style('bw-content-stats', BRIGHTER_CORE_URL . 'css/content-stats.css', [], BRIGHTER_CORE_VERSION);
    }

    /**
     * Render the stats page
     */
    public static function render_page() {
        // Detect current post type from page slug
        $current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        $default_post_type = 'post';

        // Extract post type from page slug (bw-content-stats-{post_type})
        if (strpos($current_page, 'bw-content-stats-') === 0) {
            $default_post_type = str_replace('bw-content-stats-', '', $current_page);
        }

        // Get filter parameters
        $post_type = isset($_GET['post_type_filter']) ? sanitize_key($_GET['post_type_filter']) : $default_post_type;
        $post_status = isset($_GET['status_filter']) ? sanitize_key($_GET['status_filter']) : 'publish';
        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'updated';
        $order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'asc' : 'desc';
        if (!in_array($orderby, ['title', 'modified', 'updated'], true)) {
            $orderby = 'updated';
        }
        $per_page = 50;
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

        // Build query
        $query_args = [
            'post_type' => bw_cs_post_types(),
            'post_status' => $post_status,
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => $orderby === 'title' ? 'title' : 'modified',
            'order' => strtoupper($order),
        ];

        // Filter by specific post type if requested
        if ($post_type !== 'all') {
            $query_args['post_type'] = $post_type;
        }

        $query = new WP_Query($query_args);

        // Prime meta cache for performance
        if ($query->posts) {
            $post_ids = wp_list_pluck($query->posts, 'ID');
            update_meta_cache('post', $post_ids);
        }

        // Get stats for summary
        $total_analyzed = self::get_analyzed_count();
        $total_posts = wp_count_posts($post_type === 'all' ? 'post' : $post_type);
        $pending_analysis = self::get_pending_count();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Content Statistics', 'brighterwebsites'); ?></h1>

            <!-- Summary Stats -->
            <div class="bw-stats-summary" style="display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap;">
                <div class="bw-stat-card" style="flex: 1; min-width: 200px; padding: 20px; background: #fff; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: 600; color: #2271b1;"><?php echo number_format($total_analyzed); ?></div>
                    <div style="color: #666; margin-top: 5px;">Posts Analyzed</div>
                </div>
                <div class="bw-stat-card" style="flex: 1; min-width: 200px; padding: 20px; background: #fff; border-left: 4px solid #ca8a04; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: 600; color: #ca8a04;"><?php echo number_format($pending_analysis); ?></div>
                    <div style="color: #666; margin-top: 5px;">Pending Analysis</div>
                </div>
                <?php if ($pending_analysis > 0): ?>
                <div class="bw-stat-card" style="flex: 1; min-width: 200px; padding: 20px; background: #fff; border-left: 4px solid #16a34a; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('edit.php?page=bw-content-stats&bw_analyze_now=1'), 'bw_analyze_now')); ?>"
                       class="button button-primary" style="margin-top: 5px;">
                        Analyze 5 Posts Now
                    </a>
                    <div style="color: #666; margin-top: 10px; font-size: 12px;">Background: 5/hour automatic</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" style="display: inline-flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($current_page); ?>">

                    <select name="post_type_filter">
                        <option value="all" <?php selected($post_type, 'all'); ?>>All Post Types</option>
                        <?php foreach (bw_cs_post_types() as $pt):
                            $pt_obj = get_post_type_object($pt);
                            if ($pt_obj):
                        ?>
                            <option value="<?php echo esc_attr($pt); ?>" <?php selected($post_type, $pt); ?>>
                                <?php echo esc_html($pt_obj->labels->name); ?>
                            </option>
                        <?php endif; endforeach; ?>
                    </select>

                    <select name="status_filter">
                        <option value="publish" <?php selected($post_status, 'publish'); ?>>Published</option>
                        <option value="draft" <?php selected($post_status, 'draft'); ?>>Draft</option>
                        <option value="any" <?php selected($post_status, 'any'); ?>>Any Status</option>
                    </select>

                    <button type="submit" class="button">Filter</button>
                </form>
            </div>

            <!-- Stats Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 30%;">
                            <a href="<?php echo esc_url(add_query_arg(['orderby' => 'title', 'order' => $order === 'desc' ? 'asc' : 'desc'])); ?>">
                                Title <?php if ($orderby === 'title') echo $order === 'desc' ? '▼' : '▲'; ?>
                            </a>
                        </th>
                        <th style="width: 8%;">Type</th>
                        <th style="width: 8%; text-align: center;">Words</th>
                        <th style="width: 6%; text-align: center;">Images</th>
                        <th style="width: 6%; text-align: center;">H2s</th>
                        <th style="width: 8%; text-align: center;">Int Links</th>
                        <th style="width: 8%; text-align: center;">Ext Links</th>
                        <th style="width: 10%;">
                            <a href="<?php echo esc_url(add_query_arg(['orderby' => 'updated', 'order' => $order === 'desc' ? 'asc' : 'desc'])); ?>">
                                Updated <?php if ($orderby === 'updated' || $orderby === 'modified') echo $order === 'desc' ? '▼' : '▲'; ?>
                            </a>
                            <br><small style="font-weight: normal; color: #666;">Analyzed / Modified</small>
                        </th>
                        <th style="width: 12%; font-size: 11px;">Last Analysed</th>
                        <th style="width: 12%; font-size: 11px;">Post Modified</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($query->have_posts()): ?>
                        <?php while ($query->have_posts()): $query->the_post();
                            $post_id = get_the_ID();
                            $word_count = get_post_meta($post_id, 'bw_word_count', true);
                            $images = get_post_meta($post_id, 'bw_image_count', true);
                            $h2s = get_post_meta($post_id, 'bw_h2_count', true);
                            $int_links = get_post_meta($post_id, 'bw_internal_link_count', true);
                            $ext_links = get_post_meta($post_id, 'bw_external_link_count', true);
                            $last_analyzed = get_post_meta($post_id, '_bw_last_analyzed', true);
                            $needs_analysis = empty($last_analyzed);
                        ?>
                        <tr <?php if ($needs_analysis) echo 'style="background: #fff8e1;"'; ?>>
                            <td>
                                <strong>
                                    <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>">
                                        <?php echo esc_html(get_the_title() ?: '(no title)'); ?>
                                    </a>
                                </strong>
                                <?php if ($needs_analysis): ?>
                                    <span style="color: #ca8a04; font-size: 11px; margin-left: 8px;">⏳ Pending Analysis</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(get_post_type_object(get_post_type())->labels->singular_name); ?></td>
                            <td style="text-align: center; color: #2271b1; font-weight: 600;">
                                <?php echo $word_count ? number_format($word_count) : '—'; ?>
                            </td>
                            <td style="text-align: center; color: #2271b1; font-weight: 600;">
                                <?php echo $images ? absint($images) : '—'; ?>
                            </td>
                            <td style="text-align: center; color: #2271b1; font-weight: 600;">
                                <?php echo $h2s ? absint($h2s) : '—'; ?>
                            </td>
                            <td style="text-align: center; font-weight: 600;">
                                <?php
                                if ($int_links) {
                                    $color = $int_links > 5 ? '#16a34a' : ($int_links > 2 ? '#ca8a04' : '#dc2626');
                                    echo '<span style="color: ' . esc_attr($color) . ';">' . absint($int_links) . '</span>';
                                } else {
                                    echo '<span style="color: #dc2626;">0</span>';
                                }
                                ?>
                            </td>
                            <td style="text-align: center; color: #2271b1; font-weight: 600;">
                                <?php echo $ext_links ? absint($ext_links) : '0'; ?>
                            </td>
                            <td style="font-size: 11px; color: #666;">
                                <?php
                                $modified_ts = get_the_modified_time('U');
                                $analyzed_ts = $last_analyzed ? strtotime($last_analyzed) : 0;
                                $updated_ts = max($modified_ts, $analyzed_ts);
                                $updated_label = ($analyzed_ts >= $modified_ts && $analyzed_ts > 0) ? 'Analyzed' : 'Modified';
                                echo esc_html(human_time_diff($updated_ts, current_time('timestamp')) . ' ago');
                                ?>
                                <br><small style="color: #888;"><?php echo esc_html($updated_label); ?></small>
                            </td>
                            <td style="font-size: 11px; color: #666;">
                                <?php echo $last_analyzed ? esc_html(gmdate('Y-m-d H:i', strtotime($last_analyzed))) : '—'; ?>
                            </td>
                            <td style="font-size: 11px; color: #666;">
                                <?php echo esc_html(get_the_modified_date('Y-m-d H:i')); ?>
                            </td>
                        </tr>
                        <?php endwhile; wp_reset_postdata(); ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 40px; color: #666;">
                                No posts found matching your filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($query->max_num_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $query->max_num_pages,
                        'current' => $paged,
                    ]);
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Legend -->
            <div style="margin-top: 30px; padding: 15px; background: #f9f9f9; border-left: 4px solid #2271b1;">
                <strong>Internal Links Color Guide:</strong>
                <span style="color: #16a34a; margin-left: 10px;">● 6+ (Great)</span>
                <span style="color: #ca8a04; margin-left: 10px;">● 3-5 (Good)</span>
                <span style="color: #dc2626; margin-left: 10px;">● 0-2 (Needs Work)</span>
            </div>
        </div>
        <?php
    }

    /**
     * Get count of analyzed posts
     */
    private static function get_analyzed_count() {
        global $wpdb;
        $post_types = bw_cs_post_types();
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $sql = "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type IN ($placeholders)
                AND p.post_status IN ('publish', 'draft', 'pending', 'private')
                AND pm.meta_key = '_bw_last_analyzed'
                AND pm.meta_value != ''";

        return absint($wpdb->get_var($wpdb->prepare($sql, ...$post_types)));
    }

    /**
     * Get count of posts pending analysis
     */
    private static function get_pending_count() {
        global $wpdb;
        $post_types = bw_cs_post_types();
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $sql = "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_bw_last_analyzed'
                WHERE p.post_type IN ($placeholders)
                AND p.post_status IN ('publish', 'draft', 'pending', 'private')
                AND (pm.post_id IS NULL OR pm.meta_value = '')";

        return absint($wpdb->get_var($wpdb->prepare($sql, ...$post_types)));
    }
}

// Initialize
BW_Content_Stats_Page::init();
