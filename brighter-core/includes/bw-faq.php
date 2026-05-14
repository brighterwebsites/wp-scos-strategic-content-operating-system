<?php
/**
 * FAQ System - Content Library
 *
 * Treats FAQs as reusable content snippets that can be used across multiple pages
 * - No parent page relationships
 * - Manual FAQ selection in blocks
 * - Schema control per FAQ
 * - Separate schema_answer field for concise schema output
 */

// ============================================
// 1. REGISTER FAQ CUSTOM POST TYPE
// ============================================

function register_faq_cpt() {
    $labels = array(
        'name'                  => 'FAQs',
        'singular_name'         => 'FAQ',
        'menu_name'             => 'FAQs',
        'add_new'               => 'Add New FAQ',
        'add_new_item'          => 'Add New FAQ',
        'edit_item'             => 'Edit FAQ',
        'new_item'              => 'New FAQ',
        'view_item'             => 'View FAQ',
        'search_items'          => 'Search FAQs',
        'not_found'             => 'No FAQs found',
        'not_found_in_trash'    => 'No FAQs found in trash',
    );

    $args = array(
        'labels'                => $labels,
        'public'                => true,               // Needed for admin editing
        'publicly_queryable'    => true,               // Allow front-end single pages
        'show_ui'               => true,               // Show in admin
        'show_in_menu'          => true,
        'query_var'             => true,               // Needed for admin edit screen
        'rewrite'               => array(
            'slug'       => 'faq',  // Front-end URLs: /faq/slug/
            'with_front' => false,  // Force root-level, don't inherit blog prefix
        ),
        'exclude_from_search'   => true,               // Exclude from search results
        'capability_type'       => 'post',
        'has_archive'           => false,
        'hierarchical'          => false,
        'menu_position'         => 25,
        'menu_icon'             => 'dashicons-editor-help',
        'show_in_rest'          => true,               // Enable Gutenberg
        'supports'              => array(
            'title',    // Question
            'editor',   // Full answer
            'revisions',
        ),
    );

    register_post_type('faq', $args);
}
add_action('init', 'register_faq_cpt', 20); // Priority 20 to ensure it runs after other init hooks

/**
 * Force FAQ permalinks to /faq/slug (override any blog prefix)
 * This filter runs after CPT registration and forcefully removes blog prefix
 */
function bw_faq_force_permalink_structure($post_link, $post) {
    if ($post->post_type === 'faq') {
        // Remove any blog prefix that might have been added
        $post_link = str_replace('/blog/faq/', '/faq/', $post_link);
        // Ensure it's exactly /faq/slug
        if (strpos($post_link, '/faq/') === false) {
            $home_url = home_url('/');
            $post_link = $home_url . 'faq/' . $post->post_name . '/';
        }
    }
    return $post_link;
}
add_filter('post_type_link', 'bw_faq_force_permalink_structure', 10, 2);

/**
 * Flush rewrite rules when FAQ CPT rewrite structure changes
 * This ensures /faq/slug URLs work correctly without blog prefix
 * 
 * Note: If FAQ pages still return 404 after this update, manually flush permalinks:
 * Go to Settings → Permalinks → Click "Save Changes" (no changes needed, just save)
 */
function bw_faq_maybe_flush_rewrite_rules() {
    $rewrite_version = '1.2'; // Increment when rewrite structure changes
    $flushed_version = get_option('bw_faq_rewrite_version', '0');
    
    if ($flushed_version !== $rewrite_version) {
        flush_rewrite_rules(false); // false = soft flush (faster)
        update_option('bw_faq_rewrite_version', $rewrite_version);
        update_option('bw_faq_rewrite_flushed_at', current_time('mysql')); // Track when flushed
    }
}
add_action('init', 'bw_faq_maybe_flush_rewrite_rules', 999);

/**
 * Diagnostic function: Check if rewrite rules were flushed
 * Can be called via: wp-cli eval "bw_faq_check_rewrite_status();"
 * Or add to admin notice if needed
 */
function bw_faq_check_rewrite_status() {
    $version = get_option('bw_faq_rewrite_version', '0');
    $flushed_at = get_option('bw_faq_rewrite_flushed_at', 'Never');
    
    $status = array(
        'rewrite_version' => $version,
        'flushed_at' => $flushed_at,
        'expected_version' => '1.2',
        'needs_flush' => ($version !== '1.2'),
    );
    
    return $status;
}

// ============================================
// 2. FAQ META FIELDS
// ============================================

function add_faq_meta_boxes() {
    // Schema Answer field
    add_meta_box(
        'faq_schema_answer',
        'Schema Answer (Optional)',
        'render_faq_schema_answer_meta_box',
        'faq',
        'normal',
        'high'
    );

    // Schema Toggle
    add_meta_box(
        'faq_schema_toggle',
        'Schema Settings',
        'render_faq_schema_toggle_meta_box',
        'faq',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'add_faq_meta_boxes');

function render_faq_schema_answer_meta_box($post) {
    $schema_answer = get_post_meta($post->ID, '_faq_schema_answer', true);
    $char_count = strlen($schema_answer);
    $max_chars = 300;

    wp_nonce_field('faq_schema_answer_nonce', 'faq_schema_answer_nonce');
    ?>
    <p class="description">
        Optional: Provide a concise answer specifically for schema markup. If empty, the full answer will be truncated and used instead.
        <strong>Recommended: 100-300 characters.</strong>
    </p>

    <textarea
        name="faq_schema_answer"
        id="faq_schema_answer"
        rows="4"
        style="width: 100%;"
        placeholder="Concise answer for schema (plain text only, no HTML)"
    ><?php echo esc_textarea($schema_answer); ?></textarea>

    <p class="description" id="faq-char-count">
        <span id="char-count"><?php echo $char_count; ?></span> characters
        <?php if ($char_count > $max_chars): ?>
            <span style="color: #dc3232; font-weight: bold;">
                ⚠ Warning: Exceeds recommended <?php echo $max_chars; ?> characters
            </span>
        <?php endif; ?>
    </p>

    <script>
    jQuery(document).ready(function($) {
        $('#faq_schema_answer').on('input', function() {
            var count = $(this).val().length;
            var max = <?php echo $max_chars; ?>;
            var countDisplay = $('#char-count');
            var countContainer = $('#faq-char-count');

            countDisplay.text(count);

            // Remove existing warning
            countContainer.find('span[style*="color"]').remove();

            // Add warning if over limit
            if (count > max) {
                countContainer.append(
                    '<span style="color: #dc3232; font-weight: bold;"> ⚠ Warning: Exceeds recommended ' + max + ' characters</span>'
                );
            }
        });
    });
    </script>
    <?php
}

function render_faq_schema_toggle_meta_box($post) {
    $enable_schema = get_post_meta($post->ID, '_faq_enable_schema', true);
    $enable_schema = $enable_schema !== '0' ? '1' : '0'; // Default to enabled

    wp_nonce_field('faq_schema_toggle_nonce', 'faq_schema_toggle_nonce');
    ?>
    <p>
        <label>
            <input
                type="checkbox"
                name="faq_enable_schema"
                value="1"
                <?php checked($enable_schema, '1'); ?>
            />
            Enable FAQ schema for this question
        </label>
    </p>
    <p class="description">
        Uncheck to exclude this FAQ from schema markup. Useful for long FAQs or when avoiding schema dilution.
    </p>
    <?php
}

function save_faq_meta_fields($post_id) {
    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save schema answer
    if (isset($_POST['faq_schema_answer_nonce']) && wp_verify_nonce($_POST['faq_schema_answer_nonce'], 'faq_schema_answer_nonce')) {
        if (isset($_POST['faq_schema_answer'])) {
            $schema_answer = sanitize_textarea_field($_POST['faq_schema_answer']);
            update_post_meta($post_id, '_faq_schema_answer', $schema_answer);
        } else {
            delete_post_meta($post_id, '_faq_schema_answer');
        }
    }

    // Save schema toggle
    if (isset($_POST['faq_schema_toggle_nonce']) && wp_verify_nonce($_POST['faq_schema_toggle_nonce'], 'faq_schema_toggle_nonce')) {
        $enable_schema = isset($_POST['faq_enable_schema']) ? '1' : '0';
        update_post_meta($post_id, '_faq_enable_schema', $enable_schema);
    }
}
add_action('save_post_faq', 'save_faq_meta_fields');

// ============================================
// 3. ADMIN COLUMNS
// ============================================

function faq_admin_columns($columns) {
    $new_columns = array();

    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;

        if ($key === 'title') {
            $new_columns['schema_enabled'] = 'Schema';
            $new_columns['char_count'] = 'Answer Length';
        }
    }

    return $new_columns;
}
add_filter('manage_faq_posts_columns', 'faq_admin_columns');

function faq_admin_column_content($column, $post_id) {
    if ($column === 'schema_enabled') {
        $enable_schema = get_post_meta($post_id, '_faq_enable_schema', true);
        $enable_schema = $enable_schema !== '0' ? '1' : '0';

        if ($enable_schema === '1') {
            echo '<span style="color: #46b450;">✓ Enabled</span>';
        } else {
            echo '<span style="color: #dc3232;">✗ Disabled</span>';
        }
    }

    if ($column === 'char_count') {
        $post = get_post($post_id);
        $content = wp_strip_all_tags($post->post_content);
        $schema_answer = get_post_meta($post_id, '_faq_schema_answer', true);

        if ($schema_answer) {
            echo '<strong>Schema:</strong> ' . strlen($schema_answer) . ' chars<br>';
        }
        echo '<strong>Full:</strong> ' . strlen($content) . ' chars';
    }
}
add_action('manage_faq_posts_custom_column', 'faq_admin_column_content', 10, 2);

// ============================================
// 4. HELPER FUNCTIONS
// ============================================

/**
 * Get FAQ answer for schema (stripped of HTML)
 */
function get_faq_schema_answer($faq_id) {
    // Check if schema is enabled
    $enable_schema = get_post_meta($faq_id, '_faq_enable_schema', true);
    if ($enable_schema === '0') {
        return false;
    }

    // Get schema answer or fall back to truncated content
    $schema_answer = get_post_meta($faq_id, '_faq_schema_answer', true);

    if (!empty($schema_answer)) {
        // Use custom schema answer
        $answer = $schema_answer;
    } else {
        // Use truncated full answer
        $post = get_post($faq_id);
        $answer = wp_trim_words(wp_strip_all_tags($post->post_content), 50, '...');
    }

    // Strip all HTML tags and links
    $answer = wp_strip_all_tags($answer);
    $answer = strip_shortcodes($answer);

    // Remove multiple spaces
    $answer = preg_replace('/\s+/', ' ', $answer);

    return trim($answer);
}

/**
 * Get multiple FAQs by IDs
 */
function get_faqs_by_ids($faq_ids) {
    if (empty($faq_ids)) {
        return array();
    }

    return get_posts(array(
        'post_type'      => 'faq',
        'posts_per_page' => -1,
        'post__in'       => $faq_ids,
        'orderby'        => 'post__in',
    ));
}

/**
 * Search FAQs by keyword
 */
function search_faqs($keyword, $limit = -1) {
    return get_posts(array(
        'post_type'      => 'faq',
        'posts_per_page' => $limit,
        's'              => $keyword,
        'orderby'        => 'relevance',
    ));
}

// ============================================
// 5. GUTENBERG BLOCK: FAQ SELECTOR
// ============================================

function register_faq_selector_block() {
    if (!function_exists('register_block_type')) {
        return;
    }

    // Enqueue block editor script
    wp_register_script(
        'faq-selector-block',
        BRIGHTER_CORE_URL . 'js/faq-selector-block.js',
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch'),
        BRIGHTER_CORE_VERSION,
        true
    );

    register_block_type('brighter/faq-selector', array(
        'editor_script'   => 'faq-selector-block',
        'render_callback' => 'render_faq_selector_block',
        'attributes'      => array(
            'selectedFaqs' => array(
                'type'    => 'array',
                'default' => array(),
                'items'   => array(
                    'type' => 'number',
                ),
            ),
            'displayFormat' => array(
                'type'    => 'string',
                'default' => 'accordion', // accordion, plain
            ),
            'headingLevel' => array(
                'type'    => 'string',
                'default' => 'h3', // h2, h3, h4, p
            ),
            'enableSchema' => array(
                'type'    => 'boolean',
                'default' => true,
            ),
        ),
    ));
}
add_action('init', 'register_faq_selector_block');

function render_faq_selector_block($attributes) {
    $selected_faqs = isset($attributes['selectedFaqs']) ? $attributes['selectedFaqs'] : array();
    $display_format = isset($attributes['displayFormat']) ? $attributes['displayFormat'] : 'accordion';
    $heading_level = isset($attributes['headingLevel']) ? $attributes['headingLevel'] : 'h3';
    $enable_schema = isset($attributes['enableSchema']) ? $attributes['enableSchema'] : true;

    if (empty($selected_faqs)) {
        return '<p>No FAQs selected.</p>';
    }

    $faqs = get_faqs_by_ids($selected_faqs);

    if (empty($faqs)) {
        return '<p>No FAQs found.</p>';
    }

    // Build schema if enabled
    $schema_items = array();
    if ($enable_schema) {
        foreach ($faqs as $faq) {
            $schema_answer = get_faq_schema_answer($faq->ID);
            if ($schema_answer) {
                $schema_items[] = array(
                    '@type'          => 'Question',
                    'name'           => get_the_title($faq->ID),
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text'  => $schema_answer,
                    ),
                );
            }
        }
    }

    ob_start();

    // Output schema
    if (!empty($schema_items)) {
        $schema = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $schema_items,
        );
        ?>
        <script type="application/ld+json">
        <?php echo json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
        </script>
        <?php
    }

    // Output FAQs
    ?>
    <div class="bw-faq-section" data-format="<?php echo esc_attr($display_format); ?>">
        <?php foreach ($faqs as $faq): ?>
            <div class="bw-faq-item">
                <?php if ($display_format === 'accordion'): ?>
                    <!-- Accordion format -->
                    <details class="bw-faq-accordion">
                        <summary class="bw-faq-question">
                            <?php echo esc_html(get_the_title($faq->ID)); ?>
                        </summary>
                        <div class="bw-faq-answer">
                            <?php echo apply_filters('the_content', $faq->post_content); ?>
                        </div>
                    </details>
                <?php else: ?>
                    <!-- Plain format -->
                    <?php
                    $heading_tag = in_array($heading_level, array('h2', 'h3', 'h4', 'p')) ? $heading_level : 'h3';
                    ?>
                    <<?php echo $heading_tag; ?> class="bw-faq-question">
                        <?php echo esc_html(get_the_title($faq->ID)); ?>
                    </<?php echo $heading_tag; ?>>
                    <div class="bw-faq-answer">
                        <?php echo apply_filters('the_content', $faq->post_content); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php

    return ob_get_clean();
}

// ============================================
// 6. SHORTCODE (Alternative to Block)
// ============================================

function faq_selector_shortcode($atts) {
    $atts = shortcode_atts(array(
        'ids'           => '',
        'format'        => 'accordion',
        'heading'       => 'h3',
        'schema'        => 'true',
    ), $atts);

    $faq_ids = array_map('intval', explode(',', $atts['ids']));

    return render_faq_selector_block(array(
        'selectedFaqs'  => $faq_ids,
        'displayFormat' => $atts['format'],
        'headingLevel'  => $atts['heading'],
        'enableSchema'  => ($atts['schema'] === 'true'),
    ));
}
add_shortcode('faqs', 'faq_selector_shortcode');

// Usage: [faqs ids="123,456,789" format="plain" heading="h2" schema="true"]

// ============================================
// 7. REST API ENDPOINTS
// ============================================

// brighter-core/v1/faqs is registered with token auth in class-brighter-api-endpoints.php.
// The legacy public registrations below were removed to avoid duplicate route conflicts
// where WordPress merges both handlers and produces unpredictable permission behaviour.
function register_faq_rest_routes() {
    // Intentionally empty — route is owned by Brighter_API_Endpoints (class-brighter-api-endpoints.php).
}
add_action('rest_api_init', 'register_faq_rest_routes');

function get_all_faqs_api($request) {
    $faqs = get_posts(array(
        'post_type'      => 'faq',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));

    $response = array();

    foreach ($faqs as $faq) {
        $response[] = array(
            'id'            => $faq->ID,
            'question'      => get_the_title($faq->ID),
            'answer'        => $faq->post_content,
            'schema_answer' => get_post_meta($faq->ID, '_faq_schema_answer', true),
            'schema_enabled' => get_post_meta($faq->ID, '_faq_enable_schema', true) !== '0',
        );
    }

    return new WP_REST_Response($response, 200);
}

function search_faqs_api($request) {
    $keyword = $request->get_param('q');
    $faqs = search_faqs($keyword);

    $response = array();

    foreach ($faqs as $faq) {
        $response[] = array(
            'id'            => $faq->ID,
            'question'      => get_the_title($faq->ID),
            'answer'        => wp_trim_words(wp_strip_all_tags($faq->post_content), 50),
            'full_answer'   => $faq->post_content,
        );
    }

    return new WP_REST_Response($response, 200);
}

function export_faqs_api($request) {
    $faqs = get_posts(array(
        'post_type'      => 'faq',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'ASC',
    ));

    $export_data = array();

    foreach ($faqs as $faq) {
        $export_data[] = array(
            'id'            => $faq->ID,
            'question'      => get_the_title($faq->ID),
            'answer'        => wp_strip_all_tags($faq->post_content),
            'schema_answer' => get_post_meta($faq->ID, '_faq_schema_answer', true),
            'date_created'  => get_the_date('c', $faq->ID),
            'date_modified' => get_the_modified_date('c', $faq->ID),
        );
    }

    return new WP_REST_Response(array(
        'total_faqs'  => count($export_data),
        'faqs'        => $export_data,
        'exported_at' => current_time('c'),
    ), 200);
}

// ============================================
// 8. EXCLUDE FROM SITEMAP
// ============================================

function exclude_faq_from_sitemap($post_types) {
    unset($post_types['faq']);
    return $post_types;
}
add_filter('wpseo_sitemap_exclude_post_type', 'exclude_faq_from_sitemap');

// ============================================
// 9. FRONTEND STYLES
// ============================================

/**
 * FAQ styles are now in frontend.css
 * No inline styles needed - CSS is cached and loaded via brighter-frontend.css
 * 
 * Note: Conditional loading logic removed as CSS file size is minimal (~4KB total)
 * and browser caching provides better performance than conditional checks.
 */

// ============================================
// 10. ADMIN DASHBOARD WIDGET
// ============================================

function faq_dashboard_widget() {
    wp_add_dashboard_widget(
        'faq_stats_widget',
        'FAQ Statistics',
        'render_faq_dashboard_widget'
    );
}
add_action('wp_dashboard_setup', 'faq_dashboard_widget');

function render_faq_dashboard_widget() {
    $total_faqs = wp_count_posts('faq')->publish;

    $faqs_with_schema = get_posts(array(
        'post_type'      => 'faq',
        'posts_per_page' => -1,
        'meta_query'     => array(
            'relation' => 'OR',
            array(
                'key'     => '_faq_enable_schema',
                'value'   => '1',
                'compare' => '=',
            ),
            array(
                'key'     => '_faq_enable_schema',
                'compare' => 'NOT EXISTS',
            ),
        ),
        'fields' => 'ids',
    ));

    ?>
    <div class="faq-dashboard-stats">
        <p><strong>Total FAQs:</strong> <?php echo $total_faqs; ?></p>
        <p><strong>Schema Enabled:</strong> <?php echo count($faqs_with_schema); ?></p>
        <p><strong>Schema Disabled:</strong> <?php echo $total_faqs - count($faqs_with_schema); ?></p>

        <hr>

        <p><strong>Recent FAQs:</strong></p>
        <ul>
            <?php
            $recent_faqs = get_posts(array(
                'post_type'      => 'faq',
                'posts_per_page' => 5,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ));

            foreach ($recent_faqs as $faq) {
                echo '<li><a href="' . get_edit_post_link($faq->ID) . '">' . esc_html(get_the_title($faq->ID)) . '</a></li>';
            }
            ?>
        </ul>
    </div>
    <?php
}

?>
