<?php
/**
 * ALTC Admin Pages
 *
 * File: class-altc-admin-pages.php
 * Version: 1.0.0
 *
 * Responsibilities:
 * - ALTC Overview Dashboard page
 * - Topic Breakdown View page
 * - Cannibalization risk calculations
 * - Admin menu integration
 */

if (!defined('ABSPATH')) exit;

class BW_ALTC_Admin_Pages {

    /**
     * Initialize admin pages
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_pages']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Add admin pages to menu
     */
    public static function add_admin_pages() {
        // Add submenu under Support > Optimisation
        add_submenu_page(
            'brighter_support',
            __('ALTC Overview', 'brighterwebsites'),
            __('ALTC Overview', 'brighterwebsites'),
            'edit_posts',
            'bw-altc-overview',
            [__CLASS__, 'render_overview_page']
        );

        add_submenu_page(
            'brighter_support',
            __('Topic Breakdown', 'brighterwebsites'),
            __('Topic Breakdown', 'brighterwebsites'),
            'edit_posts',
            'bw-altc-topics',
            [__CLASS__, 'render_topics_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_assets($hook) {
        // Only load on our admin pages
        if (!in_array($hook, ['support_page_bw-altc-overview', 'support_page_bw-altc-topics'], true)) {
            return;
        }

        // Add inline CSS
        wp_add_inline_style('common', self::get_admin_css());
    }

    /**
     * Get admin CSS
     */
    private static function get_admin_css() {
        return '
            .bw-altc-wrap { max-width: 1400px; margin: 20px 0; }
            .bw-altc-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-top: 20px; }
            .bw-altc-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,0.04); }
            .bw-altc-card-header { padding: 16px 20px; border-bottom: 1px solid #eee; background: linear-gradient(to bottom, #fafafa, #f5f5f5); }
            .bw-altc-card-header h3 { margin: 0; font-size: 16px; font-weight: 600; }
            .bw-altc-card-header .meta { font-size: 13px; color: #666; margin-top: 4px; }
            .bw-altc-card-body { padding: 20px; }
            .bw-altc-topics-list { list-style: none; margin: 0; padding: 0; }
            .bw-altc-topics-list li { padding: 6px 0; border-bottom: 1px solid #f0f0f0; }
            .bw-altc-topics-list li:last-child { border-bottom: none; }
            .bw-altc-maturity-bars { margin-top: 16px; }
            .bw-altc-maturity-bar { margin-bottom: 10px; }
            .bw-altc-maturity-bar-label { font-size: 12px; color: #555; margin-bottom: 4px; display: flex; justify-content: space-between; }
            .bw-altc-maturity-bar-track { background: #f0f0f0; height: 20px; border-radius: 3px; overflow: hidden; }
            .bw-altc-maturity-bar-fill { background: #0073aa; height: 100%; transition: width 0.3s; }
            .bw-altc-ratio { display: flex; justify-content: space-between; padding: 12px 0; border-top: 1px solid #eee; margin-top: 12px; }
            .bw-altc-ratio-item { text-align: center; }
            .bw-altc-ratio-value { font-size: 24px; font-weight: 600; color: #0073aa; }
            .bw-altc-ratio-label { font-size: 11px; color: #666; text-transform: uppercase; }
            .bw-altc-card-footer { padding: 12px 20px; border-top: 1px solid #eee; background: #fafafa; }
            .bw-altc-topic-section { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 20px; overflow: hidden; }
            .bw-altc-topic-header { padding: 16px 20px; background: #f7f7f7; border-bottom: 1px solid #ddd; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
            .bw-altc-topic-header:hover { background: #f0f0f0; }
            .bw-altc-topic-title { font-size: 16px; font-weight: 600; margin: 0; }
            .bw-altc-topic-meta { font-size: 13px; color: #666; margin-top: 4px; }
            .bw-altc-risk-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
            .bw-altc-risk-green { background: #dcfce7; color: #16a34a; }
            .bw-altc-risk-yellow { background: #fef9c3; color: #ca8a04; }
            .bw-altc-risk-orange { background: #ffedd5; color: #ea580c; }
            .bw-altc-risk-red { background: #fee2e2; color: #dc2626; }
            .bw-altc-topic-body { display: none; padding: 20px; }
            .bw-altc-topic-body.open { display: block; }
            .bw-altc-purpose-breakdown { background: #f9fafb; padding: 12px; border-radius: 4px; margin-bottom: 16px; }
            .bw-altc-purpose-breakdown h4 { margin: 0 0 8px 0; font-size: 13px; font-weight: 600; color: #374151; }
            .bw-altc-purpose-list { display: flex; flex-wrap: wrap; gap: 8px; margin: 0; padding: 0; list-style: none; }
            .bw-altc-purpose-item { background: #fff; border: 1px solid #e5e7eb; padding: 4px 10px; border-radius: 4px; font-size: 12px; }
            .bw-altc-content-list { list-style: none; margin: 0; padding: 0; }
            .bw-altc-content-item { padding: 12px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
            .bw-altc-content-item:last-child { border-bottom: none; }
            .bw-altc-content-item:hover { background: #fafafa; }
            .bw-altc-content-title { font-weight: 500; }
            .bw-altc-content-title a { text-decoration: none; }
            .bw-altc-content-meta { display: flex; gap: 12px; margin-top: 4px; font-size: 12px; color: #666; }
            .bw-altc-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
            .bw-altc-toggle { font-size: 18px; color: #666; transition: transform 0.2s; }
            .bw-altc-toggle.open { transform: rotate(180deg); }
            .bw-altc-sort-controls { margin-bottom: 20px; }
            .bw-altc-sort-controls select { margin-left: 10px; }
            .bw-altc-altc-group { margin-bottom: 30px; }
            .bw-altc-altc-group-header { background: #0073aa; color: #fff; padding: 12px 20px; border-radius: 4px; margin-bottom: 16px; }
            .bw-altc-altc-group-header h2 { margin: 0; font-size: 18px; }
        ';
    }

    /**
     * Render ALTC Overview page
     */
    public static function render_overview_page() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'brighterwebsites'));
        }

        $altc_data = self::get_altc_overview_data();

        ?>
        <div class="wrap bw-altc-wrap">
            <h1><?php esc_html_e('ALTC Content Strategy Overview', 'brighterwebsites'); ?></h1>
            <p><?php esc_html_e('Overview of your Authority-Led Topic Clusters and content organization.', 'brighterwebsites'); ?></p>

            <div class="bw-altc-cards">
                <?php if (empty($altc_data)): ?>
                    <p><?php esc_html_e('No ALTC lenses found. Create your first ALTC strategic lens to get started.', 'brighterwebsites'); ?></p>
                <?php else: ?>
                    <?php foreach ($altc_data as $altc): ?>
                        <div class="bw-altc-card">
                            <div class="bw-altc-card-header">
                                <h3><?php echo esc_html($altc['name']); ?></h3>
                                <div class="meta">
                                    <?php
                                    printf(
                                        esc_html__('%d content items', 'brighterwebsites'),
                                        $altc['content_count']
                                    );
                                    ?>
                                </div>
                            </div>

                            <div class="bw-altc-card-body">
                                <h4><?php esc_html_e('Topics in this ALTC:', 'brighterwebsites'); ?></h4>
                                <?php if (!empty($altc['topics'])): ?>
                                    <ul class="bw-altc-topics-list">
                                        <?php foreach ($altc['topics'] as $topic): ?>
                                            <li>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=bw-altc-topics&altc=' . $altc['term_id'] . '&topic=' . $topic['term_id'])); ?>">
                                                    <?php echo esc_html($topic['name']); ?>
                                                </a>
                                                <span style="color: #999; font-size: 12px;">(<?php echo absint($topic['count']); ?>)</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p style="color: #999; font-style: italic;"><?php esc_html_e('No topics assigned yet', 'brighterwebsites'); ?></p>
                                <?php endif; ?>

                                <div class="bw-altc-maturity-bars">
                                    <h4><?php esc_html_e('Content Maturity Distribution:', 'brighterwebsites'); ?></h4>
                                    <?php foreach ($altc['maturity_distribution'] as $level => $count): ?>
                                        <?php
                                        $percentage = $altc['content_count'] > 0 ? ($count / $altc['content_count']) * 100 : 0;
                                        $label = BW_ALTC_Taxonomies::get_maturity_options()[$level] ?? $level;
                                        ?>
                                        <div class="bw-altc-maturity-bar">
                                            <div class="bw-altc-maturity-bar-label">
                                                <span><?php echo esc_html($label); ?></span>
                                                <span><?php echo absint($count); ?> (<?php echo number_format($percentage, 1); ?>%)</span>
                                            </div>
                                            <div class="bw-altc-maturity-bar-track">
                                                <div class="bw-altc-maturity-bar-fill" style="width: <?php echo esc_attr($percentage); ?>%;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="bw-altc-ratio">
                                    <div class="bw-altc-ratio-item">
                                        <div class="bw-altc-ratio-value"><?php echo absint($altc['pillar_count']); ?></div>
                                        <div class="bw-altc-ratio-label"><?php esc_html_e('Pillars', 'brighterwebsites'); ?></div>
                                    </div>
                                    <div class="bw-altc-ratio-item">
                                        <div class="bw-altc-ratio-value"><?php echo absint($altc['supporting_count']); ?></div>
                                        <div class="bw-altc-ratio-label"><?php esc_html_e('Supporting', 'brighterwebsites'); ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="bw-altc-card-footer">
                                <a href="<?php echo esc_url(admin_url('edit.php?bw_filter_altc=' . $altc['term_id'])); ?>" class="button button-primary">
                                    <?php esc_html_e('View Content', 'brighterwebsites'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Topic Breakdown page
     */
    public static function render_topics_page() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'brighterwebsites'));
        }

        $sort_by = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'risk';
        $topic_data = self::get_topic_breakdown_data($sort_by);

        ?>
        <div class="wrap bw-altc-wrap">
            <h1><?php esc_html_e('Topic Breakdown & Cannibalization Risk', 'brighterwebsites'); ?></h1>
            <p><?php esc_html_e('Analyze topic saturation and content diversity to identify cannibalization risks.', 'brighterwebsites'); ?></p>

            <div class="bw-altc-sort-controls">
                <label for="bw-altc-sort"><?php esc_html_e('Sort by:', 'brighterwebsites'); ?></label>
                <select id="bw-altc-sort" onchange="window.location.href='<?php echo esc_js(admin_url('admin.php?page=bw-altc-topics&sort=')); ?>' + this.value;">
                    <option value="risk" <?php selected($sort_by, 'risk'); ?>><?php esc_html_e('Risk Level (Highest First)', 'brighterwebsites'); ?></option>
                    <option value="count" <?php selected($sort_by, 'count'); ?>><?php esc_html_e('Article Count (Most First)', 'brighterwebsites'); ?></option>
                    <option value="altc" <?php selected($sort_by, 'altc'); ?>><?php esc_html_e('ALTC + Alphabetical', 'brighterwebsites'); ?></option>
                    <option value="diversity" <?php selected($sort_by, 'diversity'); ?>><?php esc_html_e('Purpose Diversity (Least First)', 'brighterwebsites'); ?></option>
                </select>
            </div>

            <?php if (empty($topic_data)): ?>
                <p><?php esc_html_e('No topics with content found.', 'brighterwebsites'); ?></p>
            <?php else: ?>
                <?php
                // Group by ALTC
                $grouped_data = [];
                foreach ($topic_data as $topic) {
                    foreach ($topic['altc_names'] as $altc_id => $altc_name) {
                        if (!isset($grouped_data[$altc_id])) {
                            $grouped_data[$altc_id] = [
                                'name' => $altc_name,
                                'topics' => [],
                            ];
                        }
                        $grouped_data[$altc_id]['topics'][] = $topic;
                    }
                }
                ?>

                <?php foreach ($grouped_data as $altc_id => $altc): ?>
                    <div class="bw-altc-altc-group">
                        <div class="bw-altc-altc-group-header">
                            <h2><?php echo esc_html($altc['name']); ?></h2>
                        </div>

                        <?php foreach ($altc['topics'] as $topic): ?>
                            <?php self::render_topic_section($topic); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.bw-altc-topic-header').on('click', function() {
                const $body = $(this).next('.bw-altc-topic-body');
                const $toggle = $(this).find('.bw-altc-toggle');

                $body.slideToggle(200);
                $body.toggleClass('open');
                $toggle.toggleClass('open');
            });
        });
        </script>
        <?php
    }

    /**
     * Render individual topic section
     */
    private static function render_topic_section($topic) {
        $risk_class = self::get_risk_class($topic['final_risk']);
        ?>
        <div class="bw-altc-topic-section">
            <div class="bw-altc-topic-header">
                <div>
                    <h3 class="bw-altc-topic-title"><?php echo esc_html($topic['name']); ?></h3>
                    <div class="bw-altc-topic-meta">
                        <?php
                        printf(
                            esc_html__('Base Risk: %s%% | Purpose Diversity: %d types | Final Risk: %s%%', 'brighterwebsites'),
                            number_format($topic['base_risk'], 1),
                            $topic['purpose_diversity_count'],
                            number_format($topic['final_risk'], 1)
                        );
                        ?>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span class="bw-altc-risk-badge bw-altc-risk-<?php echo esc_attr($risk_class); ?>">
                        <?php echo number_format($topic['final_risk'], 1); ?>%
                    </span>
                    <span class="bw-altc-toggle">▼</span>
                </div>
            </div>

            <div class="bw-altc-topic-body">
                <div class="bw-altc-purpose-breakdown">
                    <h4><?php esc_html_e('Purpose Breakdown:', 'brighterwebsites'); ?></h4>
                    <ul class="bw-altc-purpose-list">
                        <?php foreach ($topic['purpose_breakdown'] as $purpose => $count): ?>
                            <?php
                            $purpose_options = bw_cs_purpose_options();
                            $purpose_label = isset($purpose_options[$purpose]) ? $purpose_options[$purpose] : $purpose;
                            ?>
                            <li class="bw-altc-purpose-item">
                                <strong><?php echo esc_html($purpose_label); ?>:</strong> <?php echo absint($count); ?> <?php echo absint($count) === 1 ? esc_html__('article', 'brighterwebsites') : esc_html__('articles', 'brighterwebsites'); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <h4><?php esc_html_e('Content:', 'brighterwebsites'); ?></h4>
                <ul class="bw-altc-content-list">
                    <?php foreach ($topic['content'] as $content): ?>
                        <li class="bw-altc-content-item">
                            <div>
                                <div class="bw-altc-content-title">
                                    <a href="<?php echo esc_url(get_edit_post_link($content['ID'])); ?>">
                                        <?php echo esc_html($content['post_title']); ?>
                                    </a>
                                </div>
                                <div class="bw-altc-content-meta">
                                    <span>
                                        <strong><?php esc_html_e('Purpose:', 'brighterwebsites'); ?></strong>
                                        <?php
                                        $purpose_options = bw_cs_purpose_options();
                                        $purpose = $content['purpose'];
                                        echo esc_html($purpose_options[$purpose] ?? $purpose);
                                        ?>
                                    </span>
                                    <?php if (!empty($content['intent'])): ?>
                                        <span>
                                            <strong><?php esc_html_e('Intent:', 'brighterwebsites'); ?></strong>
                                            <?php
                                            $intent_options = bw_cs_intent_options();
                                            echo esc_html($intent_options[$content['intent']] ?? $content['intent']);
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($content['pillar_title']): ?>
                                        <span>
                                            <strong><?php esc_html_e('Pillar:', 'brighterwebsites'); ?></strong>
                                            <?php echo esc_html($content['pillar_title']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Get ALTC overview data
     */
    private static function get_altc_overview_data() {
        global $wpdb;

        $altc_terms = get_terms([
            'taxonomy' => 'altc_strategic_lens',
            'hide_empty' => false,
            'parent' => 0, // Only top-level terms
        ]);

        if (is_wp_error($altc_terms) || empty($altc_terms)) {
            return [];
        }

        $data = [];

        foreach ($altc_terms as $term) {
            // Get all posts with this ALTC
            $posts = get_posts([
                'post_type' => BW_ALTC_Taxonomies::get_supported_post_types(),
                'posts_per_page' => -1,
                'post_status' => 'any',
                'meta_query' => [
                    [
                        'key' => 'bw_primary_altc_id',
                        'value' => $term->term_id,
                        'compare' => '=',
                    ],
                ],
                'fields' => 'ids',
            ]);

            $content_count = count($posts);

            // Get maturity distribution
            $maturity_distribution = [
                'entry' => 0,
                'learner' => 0,
                'professional' => 0,
                'expert' => 0,
                'thought_leader' => 0,
                'industry_authority' => 0,
            ];

            $pillar_count = 0;
            $supporting_count = 0;

            foreach ($posts as $post_id) {
                $maturity = get_post_meta($post_id, 'bw_cont_maturity', true);
                if (isset($maturity_distribution[$maturity])) {
                    $maturity_distribution[$maturity]++;
                }

                $purpose = get_post_meta($post_id, 'bw_purpose', true);
                if ($purpose === 'pillar') {
                    $pillar_count++;
                } else {
                    $supporting_count++;
                }
            }

            // Get topics for this ALTC
            $topics = [];
            $topic_terms = get_terms([
                'taxonomy' => 'altc_topic',
                'hide_empty' => false,
            ]);

            foreach ($topic_terms as $topic_term) {
                $serves_altc = get_term_meta($topic_term->term_id, 'topic_serves_altc', true);
                if (is_array($serves_altc) && in_array($term->term_id, $serves_altc)) {
                    // Count posts with this topic
                    $topic_posts = get_posts([
                        'post_type' => BW_ALTC_Taxonomies::get_supported_post_types(),
                        'posts_per_page' => -1,
                        'post_status' => 'any',
                        'meta_query' => [
                            [
                                'key' => 'bw_primary_topic_id',
                                'value' => $topic_term->term_id,
                                'compare' => '=',
                            ],
                            [
                                'key' => 'bw_primary_altc_id',
                                'value' => $term->term_id,
                                'compare' => '=',
                            ],
                        ],
                        'fields' => 'ids',
                    ]);

                    $topics[] = [
                        'term_id' => $topic_term->term_id,
                        'name' => $topic_term->name,
                        'count' => count($topic_posts),
                    ];
                }
            }

            $data[] = [
                'term_id' => $term->term_id,
                'name' => $term->name,
                'content_count' => $content_count,
                'topics' => $topics,
                'maturity_distribution' => $maturity_distribution,
                'pillar_count' => $pillar_count,
                'supporting_count' => $supporting_count,
            ];
        }

        return $data;
    }

    /**
     * Get topic breakdown data with cannibalization risk
     */
    private static function get_topic_breakdown_data($sort_by = 'risk') {
        // Get all topics
        $topic_terms = get_terms([
            'taxonomy' => 'altc_topic',
            'hide_empty' => false,
        ]);

        if (is_wp_error($topic_terms) || empty($topic_terms)) {
            return [];
        }

        // Get total content count
        $total_content = wp_count_posts('post');
        $total_count = $total_content->publish + $total_content->draft;

        // Get total count for all post types
        foreach (BW_ALTC_Taxonomies::get_supported_post_types() as $pt) {
            if ($pt !== 'post') {
                $pt_count = wp_count_posts($pt);
                $total_count += $pt_count->publish + $pt_count->draft;
            }
        }

        $data = [];

        foreach ($topic_terms as $term) {
            // Get all posts with this topic
            $posts = get_posts([
                'post_type' => BW_ALTC_Taxonomies::get_supported_post_types(),
                'posts_per_page' => -1,
                'post_status' => ['publish', 'draft'],
                'meta_query' => [
                    [
                        'key' => 'bw_primary_topic_id',
                        'value' => $term->term_id,
                        'compare' => '=',
                    ],
                ],
            ]);

            if (empty($posts)) {
                continue;
            }

            $post_count = count($posts);

            // Calculate base risk
            $base_risk = $total_count > 0 ? ($post_count / $total_count) * 100 : 0;

            // Get purpose breakdown
            $purpose_breakdown = [];
            $content_data = [];

            foreach ($posts as $post) {
                $purpose = get_post_meta($post->ID, 'bw_purpose', true);
                if (empty($purpose)) {
                    $purpose = 'not_set';
                }

                if (!isset($purpose_breakdown[$purpose])) {
                    $purpose_breakdown[$purpose] = 0;
                }
                $purpose_breakdown[$purpose]++;

                // Get pillar info
                $pillar_id = get_post_meta($post->ID, 'bw_pillar_page_id', true);
                $pillar_title = '';
                if ($pillar_id) {
                    $pillar_title = get_the_title($pillar_id);
                }

                $content_data[] = [
                    'ID' => $post->ID,
                    'post_title' => $post->post_title,
                    'purpose' => $purpose,
                    'intent' => get_post_meta($post->ID, 'bw_intent', true),
                    'pillar_title' => $pillar_title,
                ];
            }

            // Calculate purpose diversity
            $purpose_count = count($purpose_breakdown);
            $diversity_modifier = 1.0;

            if ($purpose_count >= 4) {
                $diversity_modifier = 0.6;
            } elseif ($purpose_count >= 2) {
                $diversity_modifier = 0.8;
            }

            // Calculate final risk
            $final_risk = $base_risk * $diversity_modifier;

            // Get ALTC names
            $altc_names = [];
            foreach ($posts as $post) {
                $altc_id = get_post_meta($post->ID, 'bw_primary_altc_id', true);
                if ($altc_id && !isset($altc_names[$altc_id])) {
                    $altc_term = get_term($altc_id, 'altc_strategic_lens');
                    if ($altc_term && !is_wp_error($altc_term)) {
                        $altc_names[$altc_id] = $altc_term->name;
                    }
                }
            }

            $data[] = [
                'term_id' => $term->term_id,
                'name' => $term->name,
                'post_count' => $post_count,
                'base_risk' => $base_risk,
                'purpose_diversity_count' => $purpose_count,
                'diversity_modifier' => $diversity_modifier,
                'final_risk' => $final_risk,
                'purpose_breakdown' => $purpose_breakdown,
                'content' => $content_data,
                'altc_names' => $altc_names,
            ];
        }

        // Sort data
        usort($data, function($a, $b) use ($sort_by) {
            switch ($sort_by) {
                case 'count':
                    return $b['post_count'] - $a['post_count'];
                case 'altc':
                    return strcmp(reset($a['altc_names']), reset($b['altc_names']));
                case 'diversity':
                    return $a['purpose_diversity_count'] - $b['purpose_diversity_count'];
                case 'risk':
                default:
                    return $b['final_risk'] - $a['final_risk'];
            }
        });

        return $data;
    }

    /**
     * Get risk class for color coding
     */
    private static function get_risk_class($risk) {
        if ($risk >= 30) {
            return 'red';
        } elseif ($risk >= 21) {
            return 'orange';
        } elseif ($risk >= 11) {
            return 'yellow';
        } else {
            return 'green';
        }
    }
}

// Initialize
BW_ALTC_Admin_Pages::init();
