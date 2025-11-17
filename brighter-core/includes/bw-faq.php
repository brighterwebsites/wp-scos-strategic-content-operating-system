<?php
/**
 * FAQ Custom Post Type System
 * 
 * Creates a flexible FAQ system with:
 * - Custom Post Type for FAQs
 * - Relationship to parent pages/posts
 * - Custom URL structure: site.com/parent-slug/faq-slug
 * - Automatic schema markup
 * - Speakable schema for voice search
 * - FAQ aggregation blocks for parent pages
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
        'public'                => true,
        'publicly_queryable'    => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'query_var'             => true,
        'rewrite'               => array(
            'slug'       => 'faq', // This will be overridden by custom rewrite
            'with_front' => false,
        ),
        'capability_type'       => 'post',
        'has_archive'           => false, // No archive page needed
        'hierarchical'          => false,
        'menu_position'         => 25,
        'menu_icon'             => 'dashicons-editor-help',
        'show_in_rest'          => true, // Enable Gutenberg
        'supports'              => array(
            'title',    // Question
            'editor',   // Extended answer
            'excerpt',  // Short answer (100-300 words)
            'thumbnail',
            'revisions',
        ),
    );

    register_post_type('faq', $args);
}
add_action('init', 'register_faq_cpt');

// ============================================
// 2. ADD RELATIONSHIP META BOX (Parent Page)
// ============================================

function add_faq_relationship_meta_box() {
    add_meta_box(
        'faq_parent_page',
        'Related Page/Product',
        'render_faq_relationship_meta_box',
        'faq',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'add_faq_relationship_meta_box');

function render_faq_relationship_meta_box($post) {
    // Get current parent page ID
    $parent_page_id = get_post_meta($post->ID, '_faq_parent_page', true);
    
    // Get all pages and posts that can have FAQs
    $pages = get_posts(array(
        'post_type'      => array('page', 'post', 'product'), // Add custom post types as needed
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));
    
    wp_nonce_field('faq_parent_page_nonce', 'faq_parent_page_nonce');
    
    echo '<select name="faq_parent_page" id="faq_parent_page" style="width: 100%;">';
    echo '<option value="">-- Select Related Page --</option>';
    
    foreach ($pages as $page) {
        $selected = ($parent_page_id == $page->ID) ? 'selected' : '';
        echo sprintf(
            '<option value="%d" %s>%s (%s)</option>',
            $page->ID,
            $selected,
            esc_html($page->post_title),
            get_post_type($page->ID)
        );
    }
    
    echo '</select>';
    echo '<p class="description">This FAQ will be associated with the selected page and its URL will reflect that relationship.</p>';
}

function save_faq_relationship_meta($post_id) {
    // Check nonce
    if (!isset($_POST['faq_parent_page_nonce']) || !wp_verify_nonce($_POST['faq_parent_page_nonce'], 'faq_parent_page_nonce')) {
        return;
    }
    
    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Save parent page ID
    if (isset($_POST['faq_parent_page'])) {
        update_post_meta($post_id, '_faq_parent_page', intval($_POST['faq_parent_page']));
    } else {
        delete_post_meta($post_id, '_faq_parent_page');
    }
}
add_action('save_post_faq', 'save_faq_relationship_meta');

// ============================================
// 3. CUSTOM URL REWRITE RULES
// Rewrite: site.com/parent-slug/faq/faq-slug
// IMPORTANT: Using /faq/ in the middle to avoid conflicts with other post types
// ============================================

function faq_custom_rewrite_rules() {
    // Match: any-parent-slug/faq/any-faq-slug
    // This pattern is specific enough to not conflict with other post types
    add_rewrite_rule(
        '^([^/]+)/faq/([^/]+)/?$',
        'index.php?faq=$matches[2]&parent_slug=$matches[1]',
        'top'
    );
}
add_action('init', 'faq_custom_rewrite_rules');

// Add parent_slug as query var
function faq_query_vars($vars) {
    $vars[] = 'parent_slug';
    return $vars;
}
add_filter('query_vars', 'faq_query_vars');

// Filter FAQ permalink to use parent slug
function faq_custom_permalink($permalink, $post) {
    if ($post->post_type !== 'faq') {
        return $permalink;
    }

    $parent_page_id = get_post_meta($post->ID, '_faq_parent_page', true);

    if (!$parent_page_id) {
        // No parent set, use default /faq/slug structure
        return home_url('/faq/' . $post->post_name . '/');
    }

    $parent_post = get_post($parent_page_id);

    if (!$parent_post) {
        return $permalink;
    }

    // Build URL: site.com/parent-slug/faq/faq-slug
    // Note: /faq/ in the middle to avoid conflicts with other post types
    return home_url('/' . $parent_post->post_name . '/faq/' . $post->post_name . '/');
}
add_filter('post_type_link', 'faq_custom_permalink', 10, 2);

// Verify FAQ belongs to parent in URL
function faq_validate_parent_slug($wp_query) {
    if (!is_singular('faq')) {
        return;
    }
    
    $parent_slug = get_query_var('parent_slug');
    $faq = $wp_query->get_queried_object();
    
    if (!$faq) {
        return;
    }
    
    $parent_page_id = get_post_meta($faq->ID, '_faq_parent_page', true);
    
    if (!$parent_page_id) {
        return; // No parent set, allow default URL
    }
    
    $parent_post = get_post($parent_page_id);
    
    // If parent slug in URL doesn't match actual parent, redirect to correct URL
    if ($parent_post && $parent_slug !== $parent_post->post_name) {
        wp_redirect(get_permalink($faq->ID), 301);
        exit;
    }
}
add_action('template_redirect', 'faq_validate_parent_slug');

// Flush rewrite rules on activation (add this to plugin activation or theme setup)
function faq_flush_rewrite_rules() {
    register_faq_cpt();
    faq_custom_rewrite_rules();
    flush_rewrite_rules();
}
// Uncomment and run once, then comment out again:
// add_action('init', 'faq_flush_rewrite_rules');

// ============================================
// 4. FAQ SCHEMA MARKUP (Single FAQ Page)
// ============================================

function faq_single_schema_markup() {
    if (!is_singular('faq')) {
        return;
    }
    
    global $post;
    
    $question = get_the_title();
    $short_answer = get_the_excerpt(); // Excerpt = short answer
    $long_answer = get_the_content(); // Content = extended answer
    $parent_page_id = get_post_meta($post->ID, '_faq_parent_page', true);
    
    // Build FAQPage schema
    $schema = array(
        '@context'  => 'https://schema.org',
        '@type'     => 'FAQPage',
        'mainEntity' => array(
            array(
                '@type'          => 'Question',
                'name'           => $question,
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags($short_answer), // Use excerpt for schema
                ),
            ),
        ),
    );
    
    // Add speakable schema for voice search
    $speakable_schema = array(
        '@context' => 'https://schema.org',
        '@type'    => 'WebPage',
        'name'     => $question,
        'speakable' => array(
            '@type' => 'SpeakableSpecification',
            'cssSelector' => array('.faq-excerpt', '.entry-excerpt'), // Target excerpt
        ),
    );
    
    // Add breadcrumb schema if parent page exists
    if ($parent_page_id) {
        $parent_post = get_post($parent_page_id);
        $breadcrumb_schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'BreadcrumbList',
            'itemListElement' => array(
                array(
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'name'     => 'Home',
                    'item'     => home_url('/'),
                ),
                array(
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'name'     => $parent_post->post_title,
                    'item'     => get_permalink($parent_post->ID),
                ),
                array(
                    '@type'    => 'ListItem',
                    'position' => 3,
                    'name'     => $question,
                    'item'     => get_permalink($post->ID),
                ),
            ),
        );
        
        echo '<script type="application/ld+json">' . json_encode($breadcrumb_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
    }
    
    // Output FAQ schema
    echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
    
    // Output speakable schema
    echo '<script type="application/ld+json">' . json_encode($speakable_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
}
add_action('wp_head', 'faq_single_schema_markup');

// ============================================
// 5. FAQ AGGREGATION SCHEMA (Parent Page)
// ============================================

function get_related_faqs_schema($post_id) {
    $faqs = get_posts(array(
        'post_type'      => 'faq',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_faq_parent_page',
                'value'   => $post_id,
                'compare' => '=',
            ),
        ),
        'orderby' => 'date',
        'order'   => 'ASC',
    ));
    
    if (empty($faqs)) {
        return false;
    }
    
    $faq_items = array();
    
    foreach ($faqs as $faq) {
        $faq_items[] = array(
            '@type'          => 'Question',
            'name'           => get_the_title($faq->ID),
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text'  => wp_strip_all_tags(get_the_excerpt($faq->ID)),
            ),
        );
    }
    
    $schema = array(
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $faq_items,
    );
    
    return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

// Auto-inject FAQ schema on pages with related FAQs
function inject_faq_schema_on_parent_pages() {
    if (!is_singular(array('page', 'post', 'product'))) {
        return;
    }
    
    global $post;
    $schema = get_related_faqs_schema($post->ID);
    
    if ($schema) {
        echo '<script type="application/ld+json">' . $schema . '</script>';
    }
}
add_action('wp_head', 'inject_faq_schema_on_parent_pages');

// ============================================
// 6. GUTENBERG BLOCK: FAQ LOOP
// ============================================

function register_faq_loop_block() {
    if (!function_exists('register_block_type')) {
        return;
    }
    
    register_block_type('custom/faq-loop', array(
        'render_callback' => 'render_faq_loop_block',
        'attributes'      => array(
            'postId' => array(
                'type'    => 'number',
                'default' => 0,
            ),
            'showExcerpt' => array(
                'type'    => 'boolean',
                'default' => true,
            ),
            'showContent' => array(
                'type'    => 'boolean',
                'default' => false,
            ),
        ),
    ));
}
add_action('init', 'register_faq_loop_block');

function render_faq_loop_block($attributes) {
    $post_id = isset($attributes['postId']) && $attributes['postId'] > 0 
        ? $attributes['postId'] 
        : get_the_ID();
    
    $show_excerpt = isset($attributes['showExcerpt']) ? $attributes['showExcerpt'] : true;
    $show_content = isset($attributes['showContent']) ? $attributes['showContent'] : false;
    
    $faqs = get_posts(array(
        'post_type'      => 'faq',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_faq_parent_page',
                'value'   => $post_id,
                'compare' => '=',
            ),
        ),
        'orderby' => 'date',
        'order'   => 'ASC',
    ));
    
    if (empty($faqs)) {
        return '<p>No FAQs found for this page.</p>';
    }
    
    ob_start();
    ?>
    <div class="faq-section" itemscope itemtype="https://schema.org/FAQPage">
        <?php foreach ($faqs as $faq) : ?>
            <div class="faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                <h3 class="faq-question" itemprop="name">
                    <a href="<?php echo get_permalink($faq->ID); ?>">
                        <?php echo esc_html(get_the_title($faq->ID)); ?>
                    </a>
                </h3>
                
                <div class="faq-answer" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                    <div itemprop="text" class="faq-excerpt">
                        <?php if ($show_excerpt) : ?>
                            <?php echo wpautop(get_the_excerpt($faq->ID)); ?>
                        <?php endif; ?>
                        
                        <?php if ($show_content) : ?>
                            <?php echo apply_filters('the_content', get_the_content(null, false, $faq->ID)); ?>
                        <?php endif; ?>
                    </div>
                    
                    <p class="faq-read-more">
                        <a href="<?php echo get_permalink($faq->ID); ?>">
                            Read full answer ?
                        </a>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ============================================
// 7. SHORTCODE FOR FAQ LOOP (Alternative to Block)
// ============================================

function faq_loop_shortcode($atts) {
    $atts = shortcode_atts(array(
        'post_id'      => get_the_ID(),
        'show_excerpt' => 'true',
        'show_content' => 'false',
    ), $atts);
    
    return render_faq_loop_block(array(
        'postId'      => intval($atts['post_id']),
        'showExcerpt' => ($atts['show_excerpt'] === 'true'),
        'showContent' => ($atts['show_content'] === 'true'),
    ));
}
add_shortcode('faq_loop', 'faq_loop_shortcode');

// Usage: [faq_loop] or [faq_loop post_id="123" show_content="true"]

// ============================================
// 8. ADMIN COLUMN: Show Parent Page
// ============================================

function faq_admin_columns($columns) {
    $columns['parent_page'] = 'Related Page';
    return $columns;
}
add_filter('manage_faq_posts_columns', 'faq_admin_columns');

function faq_admin_column_content($column, $post_id) {
    if ($column === 'parent_page') {
        $parent_page_id = get_post_meta($post_id, '_faq_parent_page', true);
        
        if ($parent_page_id) {
            $parent_post = get_post($parent_page_id);
            if ($parent_post) {
                echo '<a href="' . get_edit_post_link($parent_page_id) . '">' . esc_html($parent_post->post_title) . '</a>';
            }
        } else {
            echo '�';
        }
    }
}
add_action('manage_faq_posts_custom_column', 'faq_admin_column_content', 10, 2);

// ============================================
// 9. HELPER FUNCTION: Get FAQs for a Page
// ============================================

function get_page_faqs($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    return get_posts(array(
        'post_type'      => 'faq',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_faq_parent_page',
                'value'   => $post_id,
                'compare' => '=',
            ),
        ),
        'orderby' => 'date',
        'order'   => 'ASC',
    ));
}

// ============================================
// 10. TL;DR / SPEAKABLE META (Optional Enhancement)
// ============================================

function add_faq_tldr_meta_box() {
    add_meta_box(
        'faq_tldr',
        'TL;DR / Speakable Summary',
        'render_faq_tldr_meta_box',
        'faq',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'add_faq_tldr_meta_box');

function render_faq_tldr_meta_box($post) {
    $tldr = get_post_meta($post->ID, '_faq_tldr', true);
    
    wp_nonce_field('faq_tldr_nonce', 'faq_tldr_nonce');
    ?>
    <p>
        <label for="faq_tldr">Optional: Custom TL;DR (defaults to excerpt if empty)</label>
        <textarea 
            name="faq_tldr" 
            id="faq_tldr" 
            rows="3" 
            style="width: 100%;"
            placeholder="Ultra-concise answer for voice search..."
        ><?php echo esc_textarea($tldr); ?></textarea>
    </p>
    <p class="description">Used for speakable schema and AI summary optimization. 50-100 characters ideal.</p>
    <?php
}

function save_faq_tldr_meta($post_id) {
    if (!isset($_POST['faq_tldr_nonce']) || !wp_verify_nonce($_POST['faq_tldr_nonce'], 'faq_tldr_nonce')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    if (isset($_POST['faq_tldr'])) {
        update_post_meta($post_id, '_faq_tldr', sanitize_textarea_field($_POST['faq_tldr']));
    }
}
add_action('save_post_faq', 'save_faq_tldr_meta');

// ============================================
// 11. REST API ENDPOINT (for AI/external access)
// ============================================

function register_faq_rest_route() {
    register_rest_route('custom/v1', '/faqs/(?P<page_id>\d+)', array(
        'methods'  => 'GET',
        'callback' => 'get_page_faqs_api',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'register_faq_rest_route');

function get_page_faqs_api($request) {
    $page_id = $request['page_id'];
    $faqs = get_page_faqs($page_id);
    
    $response = array();
    
    foreach ($faqs as $faq) {
        $response[] = array(
            'id'            => $faq->ID,
            'question'      => get_the_title($faq->ID),
            'short_answer'  => get_the_excerpt($faq->ID),
            'full_answer'   => get_the_content(null, false, $faq->ID),
            'tldr'          => get_post_meta($faq->ID, '_faq_tldr', true),
            'permalink'     => get_permalink($faq->ID),
        );
    }
    
    return new WP_REST_Response($response, 200);
}

// Usage: GET /wp-json/custom/v1/faqs/123

// ============================================
// 12. AI OPTIMIZATION: Structured Data for LLMs
// ============================================

function add_faq_meta_tags() {
    if (!is_singular('faq')) {
        return;
    }
    
    global $post;
    $question = get_the_title();
    $short_answer = wp_strip_all_tags(get_the_excerpt());
    $tldr = get_post_meta($post->ID, '_faq_tldr', true);
    $speakable_text = $tldr ? $tldr : $short_answer;
    
    ?>
    <!-- AI-Optimized Meta Tags -->
    <meta property="og:type" content="article" />
    <meta property="og:title" content="<?php echo esc_attr($question); ?>" />
    <meta property="og:description" content="<?php echo esc_attr($short_answer); ?>" />
    <meta name="description" content="<?php echo esc_attr($short_answer); ?>" />
    
    <!-- Twitter Card for better social sharing -->
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:title" content="<?php echo esc_attr($question); ?>" />
    <meta name="twitter:description" content="<?php echo esc_attr($short_answer); ?>" />
    
    <!-- Custom meta for AI crawlers -->
    <meta name="ai:question" content="<?php echo esc_attr($question); ?>" />
    <meta name="ai:answer" content="<?php echo esc_attr($speakable_text); ?>" />
    <?php
}
add_action('wp_head', 'add_faq_meta_tags', 5);

// ============================================
// 13. AUTOMATIC INTERNAL LINKING
// ============================================

function auto_link_faqs_in_content($content) {
    // Only run on parent pages, not on FAQ singles
    if (is_singular('faq')) {
        return $content;
    }
    
    global $post;
    
    // Get FAQs related to current page
    $faqs = get_page_faqs($post->ID);
    
    if (empty($faqs)) {
        return $content;
    }
    
    // Build array of questions to link
    $links_to_add = array();
    
    foreach ($faqs as $faq) {
        $question = get_the_title($faq->ID);
        $permalink = get_permalink($faq->ID);
        
        // Only link if question appears naturally in content
        if (stripos($content, $question) !== false) {
            $links_to_add[$question] = $permalink;
        }
    }
    
    // Replace first occurrence of each question with link
    foreach ($links_to_add as $question => $link) {
        $content = preg_replace(
            '/\b(' . preg_quote($question, '/') . ')\b/i',
            '<a href="' . esc_url($link) . '" class="auto-faq-link">$1</a>',
            $content,
            1 // Only replace first occurrence
        );
    }
    
    return $content;
}
add_filter('the_content', 'auto_link_faqs_in_content', 20);

// ============================================
// 14. FAQ ANALYTICS TRACKING
// ============================================

function add_faq_analytics_tracking() {
    if (!is_singular('faq')) {
        return;
    }
    
    global $post;
    $parent_page_id = get_post_meta($post->ID, '_faq_parent_page', true);
    $parent_title = $parent_page_id ? get_the_title($parent_page_id) : 'No Parent';
    ?>
    <script>
    // GA4 Event Tracking for FAQ Views
    if (typeof gtag !== 'undefined') {
        gtag('event', 'faq_view', {
            'faq_question': '<?php echo esc_js(get_the_title()); ?>',
            'parent_page': '<?php echo esc_js($parent_title); ?>',
            'faq_id': <?php echo $post->ID; ?>
        });
    }
    
    // Track FAQ link clicks back to parent page
    document.addEventListener('DOMContentLoaded', function() {
        var parentLinks = document.querySelectorAll('a[href*="<?php echo esc_js(get_permalink($parent_page_id)); ?>"]');
        parentLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'faq_parent_click', {
                        'faq_question': '<?php echo esc_js(get_the_title()); ?>',
                        'destination': link.href
                    });
                }
            });
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'add_faq_analytics_tracking');

// ============================================
// 15. SITEMAP INTEGRATION (Yoast/RankMath Compatible)
// ============================================

function faq_sitemap_priority($priority, $post_type) {
    if ($post_type === 'faq') {
        return 0.7; // Higher than default (0.6) but lower than main pages (0.8-1.0)
    }
    return $priority;
}
add_filter('wpseo_sitemap_entry', 'faq_sitemap_priority', 10, 2);

// Ensure FAQs are in sitemap
function add_faq_to_sitemap($post_types) {
    $post_types[] = 'faq';
    return $post_types;
}
add_filter('wpseo_sitemap_exclude_post_type', 'add_faq_to_sitemap', 10, 1);

// ============================================
// 16. BREADCRUMB INTEGRATION
// ============================================

function faq_breadcrumbs() {
    if (!is_singular('faq')) {
        return;
    }
    
    global $post;
    $parent_page_id = get_post_meta($post->ID, '_faq_parent_page', true);
    
    if (!$parent_page_id) {
        return;
    }
    
    $parent_post = get_post($parent_page_id);
    
    ?>
    <nav class="faq-breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?php echo home_url('/'); ?>">Home</a></li>
            <li><a href="<?php echo get_permalink($parent_post->ID); ?>"><?php echo esc_html($parent_post->post_title); ?></a></li>
            <li aria-current="page"><?php echo esc_html(get_the_title()); ?></li>
        </ol>
    </nav>
    <?php
}

// Hook into Yoast breadcrumbs
function faq_yoast_breadcrumb($links) {
    if (is_singular('faq')) {
        global $post;
        $parent_page_id = get_post_meta($post->ID, '_faq_parent_page', true);
        
        if ($parent_page_id) {
            $parent_post = get_post($parent_page_id);
            
            // Insert parent page before current FAQ
            $faq_link = array_pop($links); // Remove current FAQ
            
            $links[] = array(
                'url' => get_permalink($parent_post->ID),
                'text' => $parent_post->post_title,
            );
            
            $links[] = $faq_link; // Add FAQ back
        }
    }
    
    return $links;
}
add_filter('wpseo_breadcrumb_links', 'faq_yoast_breadcrumb');

// ============================================
// 17. SEARCH ENHANCEMENT: Boost FAQ Results
// ============================================

function boost_faq_search_results($orderby, $query) {
    if (!$query->is_search() || is_admin()) {
        return $orderby;
    }
    
    global $wpdb;
    
    // Boost FAQs in search results (questions are naturally search-friendly)
    $orderby = "
        CASE 
            WHEN {$wpdb->posts}.post_type = 'faq' THEN 1
            ELSE 2
        END ASC,
        {$wpdb->posts}.post_date DESC
    ";
    
    return $orderby;
}
add_filter('posts_orderby', 'boost_faq_search_results', 10, 2);

// ============================================
// 18. TEMPLATE SUGGESTION SYSTEM
// ============================================

function faq_template_hierarchy($template) {
    if (is_singular('faq')) {
        $custom_templates = array(
            'single-faq.php',
            'single.php',
            'singular.php',
            'index.php'
        );
        
        foreach ($custom_templates as $custom_template) {
            if (file_exists(get_stylesheet_directory() . '/' . $custom_template)) {
                return get_stylesheet_directory() . '/' . $custom_template;
            }
        }
    }
    
    return $template;
}
add_filter('template_include', 'faq_template_hierarchy');

// ============================================
// 19. RELATED FAQs WIDGET/FUNCTION
// ============================================

function display_related_faqs($current_faq_id = null) {
    if (!$current_faq_id) {
        global $post;
        $current_faq_id = $post->ID;
    }
    
    // Get parent page of current FAQ
    $parent_page_id = get_post_meta($current_faq_id, '_faq_parent_page', true);
    
    if (!$parent_page_id) {
        return;
    }
    
    // Get other FAQs from same parent
    $related_faqs = get_posts(array(
        'post_type'      => 'faq',
        'posts_per_page' => 5,
        'post__not_in'   => array($current_faq_id),
        'meta_query'     => array(
            array(
                'key'     => '_faq_parent_page',
                'value'   => $parent_page_id,
                'compare' => '=',
            ),
        ),
        'orderby' => 'rand',
    ));
    
    if (empty($related_faqs)) {
        return;
    }
    
    ?>
    <aside class="related-faqs">
        <h3>Related Questions</h3>
        <ul>
            <?php foreach ($related_faqs as $faq) : ?>
                <li>
                    <a href="<?php echo get_permalink($faq->ID); ?>">
                        <?php echo esc_html(get_the_title($faq->ID)); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>
    <?php
}

// ============================================
// 20. BULK ACTIONS: Assign Parent Page
// ============================================

function add_faq_bulk_actions($bulk_actions) {
    $bulk_actions['assign_parent_page'] = 'Assign Parent Page';
    return $bulk_actions;
}
add_filter('bulk_actions-edit-faq', 'add_faq_bulk_actions');

function handle_faq_bulk_actions($redirect_to, $action, $post_ids) {
    if ($action !== 'assign_parent_page') {
        return $redirect_to;
    }
    
    // Show custom admin page for bulk parent assignment
    $redirect_to = add_query_arg('bulk_assign_parent', implode(',', $post_ids), admin_url('edit.php?post_type=faq'));
    return $redirect_to;
}
add_filter('handle_bulk_actions-edit-faq', 'handle_faq_bulk_actions', 10, 3);

// Display bulk assignment form
function faq_bulk_assign_admin_notice() {
    if (!isset($_GET['bulk_assign_parent'])) {
        return;
    }
    
    $post_ids = explode(',', $_GET['bulk_assign_parent']);
    
    ?>
    <div class="notice notice-info">
        <h3>Bulk Assign Parent Page</h3>
        <form method="post" action="">
            <?php wp_nonce_field('faq_bulk_assign', 'faq_bulk_assign_nonce'); ?>
            <input type="hidden" name="faq_post_ids" value="<?php echo esc_attr($_GET['bulk_assign_parent']); ?>" />
            
            <p>
                <label for="bulk_parent_page">Select Parent Page for <?php echo count($post_ids); ?> FAQs:</label>
                <select name="bulk_parent_page" id="bulk_parent_page" required>
                    <option value="">-- Select Page --</option>
                    <?php
                    $pages = get_posts(array(
                        'post_type'      => array('page', 'post', 'product'),
                        'posts_per_page' => -1,
                        'orderby'        => 'title',
                        'order'          => 'ASC',
                    ));
                    
                    foreach ($pages as $page) {
                        echo sprintf(
                            '<option value="%d">%s (%s)</option>',
                            $page->ID,
                            esc_html($page->post_title),
                            get_post_type($page->ID)
                        );
                    }
                    ?>
                </select>
            </p>
            
            <p>
                <button type="submit" name="faq_bulk_assign_submit" class="button button-primary">
                    Assign Parent Page
                </button>
            </p>
        </form>
    </div>
    <?php
}
add_action('admin_notices', 'faq_bulk_assign_admin_notice');

// Process bulk assignment
function process_faq_bulk_assign() {
    if (!isset($_POST['faq_bulk_assign_submit'])) {
        return;
    }
    
    if (!wp_verify_nonce($_POST['faq_bulk_assign_nonce'], 'faq_bulk_assign')) {
        return;
    }
    
    if (!current_user_can('edit_posts')) {
        return;
    }
    
    $post_ids = explode(',', $_POST['faq_post_ids']);
    $parent_page_id = intval($_POST['bulk_parent_page']);
    
    foreach ($post_ids as $post_id) {
        update_post_meta(intval($post_id), '_faq_parent_page', $parent_page_id);
    }
    
    // Redirect back to FAQ list
    wp_redirect(admin_url('edit.php?post_type=faq&bulk_assigned=' . count($post_ids)));
    exit;
}
add_action('admin_init', 'process_faq_bulk_assign');

// ============================================
// 21. FAQ EXPORT FOR AI TRAINING
// ============================================

function faq_export_json_endpoint() {
    register_rest_route('custom/v1', '/faqs/export', array(
        'methods'  => 'GET',
        'callback' => 'export_all_faqs_json',
        'permission_callback' => function() {
            // Add your own authentication here if needed
            return current_user_can('manage_options');
        },
    ));
}
add_action('rest_api_init', 'faq_export_json_endpoint');

function export_all_faqs_json($request) {
    $all_faqs = get_posts(array(
        'post_type'      => 'faq',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'ASC',
    ));
    
    $export_data = array();
    
    foreach ($all_faqs as $faq) {
        $parent_page_id = get_post_meta($faq->ID, '_faq_parent_page', true);
        $parent_title = $parent_page_id ? get_the_title($parent_page_id) : null;
        $parent_url = $parent_page_id ? get_permalink($parent_page_id) : null;
        
        $export_data[] = array(
            'id'            => $faq->ID,
            'question'      => get_the_title($faq->ID),
            'short_answer'  => wp_strip_all_tags(get_the_excerpt($faq->ID)),
            'full_answer'   => wp_strip_all_tags(get_the_content(null, false, $faq->ID)),
            'tldr'          => get_post_meta($faq->ID, '_faq_tldr', true),
            'permalink'     => get_permalink($faq->ID),
            'parent_page'   => array(
                'id'    => $parent_page_id,
                'title' => $parent_title,
                'url'   => $parent_url,
            ),
            'date_created'  => get_the_date('c', $faq->ID),
            'date_modified' => get_the_modified_date('c', $faq->ID),
        );
    }
    
    return new WP_REST_Response(array(
        'total_faqs' => count($export_data),
        'faqs'       => $export_data,
        'exported_at' => current_time('c'),
    ), 200);
}

// Usage: GET /wp-json/custom/v1/faqs/export
// Perfect for feeding into AI training systems or chatbots

// ============================================
// 22. ADMIN DASHBOARD WIDGET
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
    
    $faqs_with_parents = get_posts(array(
        'post_type'      => 'faq',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_faq_parent_page',
                'compare' => 'EXISTS',
            ),
        ),
        'fields' => 'ids',
    ));
    
    $faqs_without_parents = $total_faqs - count($faqs_with_parents);
    
    ?>
    <div class="faq-dashboard-stats">
        <p><strong>Total FAQs:</strong> <?php echo $total_faqs; ?></p>
        <p><strong>With Parent Pages:</strong> <?php echo count($faqs_with_parents); ?></p>
        <p><strong>Without Parent Pages:</strong> 
            <span style="color: <?php echo $faqs_without_parents > 0 ? '#dc3232' : '#46b450'; ?>">
                <?php echo $faqs_without_parents; ?>
            </span>
        </p>
        
        <?php if ($faqs_without_parents > 0) : ?>
            <p>
                <a href="<?php echo admin_url('edit.php?post_type=faq&meta_key=_faq_parent_page&meta_compare=NOT EXISTS'); ?>" class="button button-secondary">
                    View Orphaned FAQs
                </a>
            </p>
        <?php endif; ?>
        
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

// ============================================
// 23. AUTOMATIC EXCERPT GENERATION
// ============================================

function generate_faq_excerpt($post_id) {
    $post = get_post($post_id);
    
    if ($post->post_type !== 'faq') {
        return;
    }
    
    // If excerpt already exists, don't override
    if (!empty($post->post_excerpt)) {
        return;
    }
    
    // Get content
    $content = $post->post_content;
    
    if (empty($content)) {
        return;
    }
    
    // Generate excerpt: first 200 words of content
    $excerpt = wp_trim_words(wp_strip_all_tags($content), 200, '...');
    
    // Update post excerpt
    wp_update_post(array(
        'ID'           => $post_id,
        'post_excerpt' => $excerpt,
    ));
}
add_action('save_post_faq', 'generate_faq_excerpt', 20);

// ============================================
// 24. CSS FOR FAQ DISPLAY (Optional)
// ============================================

function faq_frontend_styles() {
    if (!is_singular('faq') && !has_block('custom/faq-loop')) {
        return;
    }
    ?>
    <style>
    .faq-section {
        margin: 2rem 0;
    }
    
    .faq-item {
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: #f9f9f9;
        border-left: 4px solid #0073aa;
        border-radius: 4px;
    }
    
    .faq-question {
        margin: 0 0 1rem 0;
        font-size: 1.3em;
        color: #0073aa;
    }
    
    .faq-question a {
        color: inherit;
        text-decoration: none;
    }
    
    .faq-question a:hover {
        text-decoration: underline;
    }
    
    .faq-answer {
        color: #333;
        line-height: 1.6;
    }
    
    .faq-excerpt {
        margin-bottom: 1rem;
    }
    
    .faq-read-more {
        margin: 1rem 0 0 0;
    }
    
    .faq-read-more a {
        color: #0073aa;
        text-decoration: none;
        font-weight: 600;
    }
    
    .faq-read-more a:hover {
        text-decoration: underline;
    }
    
    .related-faqs {
        margin: 2rem 0;
        padding: 1.5rem;
        background: #f0f0f0;
        border-radius: 4px;
    }
    
    .related-faqs h3 {
        margin-top: 0;
    }
    
    .related-faqs ul {
        list-style: none;
        padding: 0;
    }
    
    .related-faqs li {
        margin-bottom: 0.5rem;
        padding-left: 1.5rem;
        position: relative;
    }
    
    .related-faqs li:before {
        content: "?";
        position: absolute;
        left: 0;
        color: #0073aa;
    }
    
    .faq-breadcrumb ol {
        list-style: none;
        padding: 0;
        display: flex;
        gap: 0.5rem;
        margin: 1rem 0;
    }
    
    .faq-breadcrumb li:not(:last-child):after {
        content: "�";
        margin-left: 0.5rem;
        color: #999;
    }
    </style>
    <?php
}
add_action('wp_head', 'faq_frontend_styles');

?>