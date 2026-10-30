<?php
/**
 * Breadcrumb Shortcode
 *
 * Displays breadcrumb navigation. bw_get_breadcrumb_items() is the one trail
 * builder: scos-schema-output.php maps the same items to its BreadcrumbList,
 * so the visible trail and the schema cannot drift apart.
 *
 * Usage: [breadcrumbs] or [bw_breadcrumbs]
 *
 * Output:
 * <ul class="bw-breadcrumbs">
 *   <li><a href="...">Home</a></li>
 *   <li><a href="...">Parent</a></li>
 *   <li>Current Page</li>
 * </ul>
 *
 * v1.3 | 2026-10-30 — Single trail builder for shortcode and schema. Term archives get the
 *                      posts page / CPT archive, parent terms and the term (was "Home › Posts").
 *                      Author and date archives get their crumbs. No posts page → no posts crumb
 *                      (was a second Home). WooCommerce trail moved here from the schema file.
 *
 * @package    BrighterCore
 * @subpackage Breadcrumbs
 * @version    1.3 | 2026-10-30
 */

if (!defined('ABSPATH')) exit;

/**
 * Posts index crumb: Settings → Reading “Posts page”.
 *
 * Null when there is no posts page: the posts index is then the front page,
 * and a crumb for it would be a second Home.
 *
 * @return array{name:string,url:string,current:bool}|null
 */
function bw_get_posts_archive_crumb_item() {
    $posts_page_id = (int) get_option('page_for_posts');
    if ($posts_page_id <= 0 || 'page' !== get_option('show_on_front')) {
        return null;
    }
    $url = get_permalink($posts_page_id);
    if (!$url) {
        return null;
    }
    $title = get_the_title($posts_page_id);
    if ($title === '' || $title === false) {
        $title = __('Posts', 'brighterwebsites');
    }
    return [
        'name' => $title,
        'url' => $url,
        'current' => false,
    ];
}

/**
 * Crumb for the archive a post type's items sit under.
 *
 * Posts use the posts page; other post types their archive, when they have one.
 *
 * @param string $post_type Post type name.
 * @return array{name:string,url:string,current:bool}|null
 */
function bw_get_post_type_crumb_item($post_type) {
    if ($post_type === 'post') {
        return bw_get_posts_archive_crumb_item();
    }
    $post_type_obj = get_post_type_object($post_type);
    if (!$post_type_obj || !$post_type_obj->has_archive) {
        return null;
    }
    $url = get_post_type_archive_link($post_type);
    if (!$url) {
        return null;
    }
    return [
        'name' => $post_type_obj->labels->name,
        'url' => $url,
        'current' => false,
    ];
}

/**
 * Breadcrumb trail on WooCommerce pages, as WooCommerce's own breadcrumb builds it.
 *
 * Sites show WooCommerce's breadcrumb on shop pages (Home > category > product),
 * and WooCommerce's BreadcrumbList is suppressed while the schema graph is output,
 * so the graph has to carry the same trail. WC_Breadcrumb::generate() runs the
 * woocommerce_get_breadcrumb filter and leaves Home out.
 *
 * @param string $current_url URL for the last crumb (WooCommerce leaves it empty).
 * @param int    $post_id     Current post ID (0 off singulars).
 * @return array|null List of ['name' => string, 'url' => string], or null off WooCommerce pages.
 */
function bw_schema_wc_breadcrumb_trail($current_url, $post_id) {
    if (!function_exists('is_woocommerce') || !class_exists('WC_Breadcrumb') || !is_woocommerce()) {
        return null;
    }

    $crumbs = (new WC_Breadcrumb())->generate();
    if (empty($crumbs)) {
        return null;
    }

    $trail = [];
    $last  = count($crumbs) - 1;
    foreach (array_values($crumbs) as $i => $crumb) {
        $name = isset($crumb[0]) ? trim(wp_strip_all_tags((string) $crumb[0])) : '';
        $url  = isset($crumb[1]) ? (string) $crumb[1] : '';

        if ($i === $last) {
            // The current page: WooCommerce leaves its URL empty.
            $url = $current_url;
            $override = (is_singular() && $post_id) ? get_post_meta($post_id, 'scos_seo_breadcrumb_title', true) : '';
            if (!empty($override)) {
                $name = $override;
            }
        }

        if ('' === $name || '' === $url) {
            continue;
        }
        $trail[] = ['name' => $name, 'url' => $url];
    }

    return $trail ?: null;
}

/**
 * URL of the current request, without query string.
 *
 * @return string
 */
function bw_breadcrumb_current_url() {
    if (function_exists('bw_schema_current_url')) {
        return bw_schema_current_url();
    }
    if (is_singular()) {
        $link = get_permalink(get_queried_object_id());
        if ($link) {
            return $link;
        }
    }
    global $wp;
    $path = isset($wp->request) ? (string) $wp->request : '';
    return home_url(user_trailingslashit($path));
}

/**
 * Apply extensions (e.g. Site Essentials permalink segments).
 *
 * @param array $items Breadcrumb rows.
 * @return array
 */
function bw_apply_breadcrumb_items_filter($items) {
    return apply_filters('bw_breadcrumb_items', is_array($items) ? $items : []);
}

/**
 * Build the breadcrumb trail for the current request.
 *
 * Shared by the [breadcrumbs] shortcode and the schema BreadcrumbList.
 *
 * @param string $current_url Optional URL for the current crumb; defaults to the request URL.
 * @return array Array of breadcrumb items with 'name', 'url', 'current'
 */
function bw_get_breadcrumb_items($current_url = '') {
    $breadcrumbs = [];
    $post_id = is_singular() ? (int) get_queried_object_id() : 0;
    if ($current_url === '') {
        $current_url = bw_breadcrumb_current_url();
    }

    // Home - always first
    $breadcrumbs[] = [
        'name' => 'Home',
        'url' => home_url('/'),
        'current' => false
    ];

    // WOOCOMMERCE PAGES - the trail WooCommerce's own breadcrumb shows
    $wc_trail = bw_schema_wc_breadcrumb_trail($current_url, $post_id);
    if ($wc_trail) {
        foreach ($wc_trail as $crumb) {
            $breadcrumbs[] = [
                'name' => $crumb['name'],
                'url' => $crumb['url'],
                'current' => false
            ];
        }
        $breadcrumbs[count($breadcrumbs) - 1]['current'] = true;
        return bw_apply_breadcrumb_items_filter($breadcrumbs);
    }

    // FRONT PAGE - just Home
    if (is_front_page()) {
        $breadcrumbs[0]['current'] = true;
        return bw_apply_breadcrumb_items_filter($breadcrumbs);
    }

    // POSTS PAGE (is_home() off the front page means a posts page is set)
    if (is_home()) {
        $crumb = bw_get_posts_archive_crumb_item();
        if ($crumb) {
            $crumb['current'] = true;
            $breadcrumbs[] = $crumb;
        }
        return bw_apply_breadcrumb_items_filter($breadcrumbs);
    }

    // OTHER ARCHIVES (categories, tags, custom taxonomies, CPT archives, author, date)
    if (is_archive()) {
        $queried = get_queried_object();

        if (is_post_type_archive()) {
            $post_type = get_query_var('post_type');
            $post_type = is_array($post_type) ? reset($post_type) : $post_type;
            $post_type_obj = $post_type ? get_post_type_object($post_type) : null;
            if ($post_type_obj) {
                $breadcrumbs[] = [
                    'name' => $post_type_obj->labels->name,
                    'url' => $current_url,
                    'current' => true
                ];
            }
        } elseif ($queried instanceof WP_Term) {
            // Post type from the taxonomy, not the loop: an empty term has no posts to ask.
            $taxonomy = get_taxonomy($queried->taxonomy);
            $object_types = $taxonomy ? (array) $taxonomy->object_type : [];
            $post_type = in_array('post', $object_types, true) ? 'post' : reset($object_types);
            $parent_crumb = $post_type ? bw_get_post_type_crumb_item($post_type) : null;
            if ($parent_crumb) {
                $breadcrumbs[] = $parent_crumb;
            }

            $ancestors = array_reverse(get_ancestors($queried->term_id, $queried->taxonomy, 'taxonomy'));
            foreach ($ancestors as $ancestor_id) {
                $ancestor = get_term($ancestor_id, $queried->taxonomy);
                if (!($ancestor instanceof WP_Term)) {
                    continue;
                }
                $ancestor_link = get_term_link($ancestor);
                if (is_wp_error($ancestor_link)) {
                    continue;
                }
                $breadcrumbs[] = [
                    'name' => $ancestor->name,
                    'url' => $ancestor_link,
                    'current' => false
                ];
            }

            $breadcrumbs[] = [
                'name' => $queried->name,
                'url' => $current_url,
                'current' => true
            ];
        } else {
            $posts_crumb = bw_get_posts_archive_crumb_item();
            if ($posts_crumb) {
                $breadcrumbs[] = $posts_crumb;
            }

            if (is_author() && $queried instanceof WP_User) {
                $breadcrumbs[] = [
                    'name' => $queried->display_name,
                    'url' => $current_url,
                    'current' => true
                ];
            } elseif (is_date()) {
                $year = (int) get_query_var('year');
                $month = (int) get_query_var('monthnum');
                $day = (int) get_query_var('day');
                if ($year && ($month || $day)) {
                    $breadcrumbs[] = [
                        'name' => (string) $year,
                        'url' => get_year_link($year),
                        'current' => false
                    ];
                }
                if ($month && $day) {
                    $breadcrumbs[] = [
                        'name' => date_i18n('F', mktime(0, 0, 0, $month, 1, $year)),
                        'url' => get_month_link($year, $month),
                        'current' => false
                    ];
                }
                if ($day) {
                    $date_name = date_i18n('j', mktime(0, 0, 0, $month, $day, $year));
                } elseif ($month) {
                    $date_name = date_i18n('F', mktime(0, 0, 0, $month, 1, $year));
                } else {
                    $date_name = (string) $year;
                }
                $breadcrumbs[] = [
                    'name' => $date_name,
                    'url' => $current_url,
                    'current' => true
                ];
            }
        }
        return bw_apply_breadcrumb_items_filter($breadcrumbs);
    }

    // SINGLES (posts, pages, CPTs)
    if (is_singular() && $post_id) {
        $post_type = get_post_type($post_id);

        // Posts page for posts, archive for CPTs that have one (not pages)
        if ($post_type !== 'page') {
            $parent_crumb = bw_get_post_type_crumb_item($post_type);
            if ($parent_crumb) {
                $breadcrumbs[] = $parent_crumb;
            }
        }

        // Add parent pages for hierarchical post types (pages)
        if (is_page()) {
            $ancestors = get_post_ancestors($post_id);
            $ancestors = array_reverse($ancestors);

            foreach ($ancestors as $ancestor_id) {
                $breadcrumbs[] = [
                    'name' => get_the_title($ancestor_id),
                    'url' => get_permalink($ancestor_id),
                    'current' => false
                ];
            }
        }

        // Current page - check for breadcrumb override (SCOS SEO field)
        $breadcrumb_override = get_post_meta($post_id, 'scos_seo_breadcrumb_title', true);
        $current_name = !empty($breadcrumb_override) ? $breadcrumb_override : get_the_title($post_id);

        $breadcrumbs[] = [
            'name' => $current_name,
            'url' => get_permalink($post_id),
            'current' => true
        ];
    }

    return bw_apply_breadcrumb_items_filter($breadcrumbs);
}

/**
 * Render breadcrumb HTML
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function bw_render_breadcrumbs($atts = []) {
    $atts = shortcode_atts([
        'class' => '',           // Additional CSS classes
        'separator' => '',       // Separator between items (CSS handles this by default)
        'home_icon' => false,    // Use icon instead of "Home" text
    ], $atts);

    $breadcrumbs = bw_get_breadcrumb_items();

    if (empty($breadcrumbs)) {
        return '';
    }

    // Build class attribute
    $classes = ['bw-breadcrumbs'];
    if (!empty($atts['class'])) {
        $classes[] = sanitize_html_class($atts['class']);
    }
    $class_attr = implode(' ', $classes);

    // Build HTML with wrapper for overflow detection
    $output = '<div class="bw-breadcrumbs-wrap">';
    $output .= '<ul class="' . esc_attr($class_attr) . '">';

    $count = count($breadcrumbs);
    foreach ($breadcrumbs as $i => $crumb) {
        $is_last = ($i === $count - 1);

        $output .= '<li>';

        if ($is_last || $crumb['current']) {
            // Current page - no link
            $output .= esc_html($crumb['name']);
        } else {
            // Linked breadcrumb
            $output .= sprintf(
                '<a href="%s" target="_self" data-type="url">%s</a>',
                esc_url($crumb['url']),
                esc_html($crumb['name'])
            );
        }

        $output .= '</li>';
    }

    $output .= '</ul>';
    $output .= '</div>';

    return $output;
}

// Register shortcodes
add_shortcode('breadcrumbs', 'bw_render_breadcrumbs');
add_shortcode('bw_breadcrumbs', 'bw_render_breadcrumbs');
