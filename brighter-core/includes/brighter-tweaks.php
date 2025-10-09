<?php
/**
 * Brighter Tools: Tweaks
 *
 * File: brighter-tweaks.php
 * Version: 4.3.0
 *
 * Changelog:
 * 4.3.0 - FIXED: Preloads now working, pagination fixed, Google Fonts removed, cleaned duplicate code
 * 4.0.0 - Initial version
 *
 * Purpose: Site tweaks and performance optimizations
 * - Per-page image/asset preloads
 * - Auto-preload featured images on singles
 * - Remove Google Fonts
 * - LCP image optimization
 */

defined('ABSPATH') || exit;

class Brighter_Tweaks {
    const OPT = 'bw_preloads_map';
    const OPT_THEME = 'theme_colour';
    const OPT_POST_TYPES = 'brighter_preload_post_types';

    public static function boot() {
        // Admin
        add_action('admin_init', [__CLASS__, 'register_settings']);
        
        // Frontend output - HIGHER PRIORITY to run earlier
        add_action('wp_head', [__CLASS__, 'output_preloads'], 2); // Changed from 1 to 2
        add_action('wp_head', [__CLASS__, 'output_featured_image_preload'], 2); // Changed from 1 to 2
        add_action('wp_head', [__CLASS__, 'remove_google_fonts'], 1);
        
        // Meta box for individual pages
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post_page', [__CLASS__, 'save_meta_box']);
        
        // LCP optimization
        add_filter('wp_get_attachment_image_attributes', [__CLASS__, 'add_fetchpriority'], 99, 3);
        add_filter('the_content', [__CLASS__, 'add_fetchpriority_to_content'], 99);
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        // Per-page preloads map
        register_setting('brighter_tweaks', self::OPT, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitise_preloads_map'],
            'default' => [],
        ]);

        // Theme colour
        register_setting('brighter_tweaks', self::OPT_THEME, [
            'type' => 'string',
            'sanitize_callback' => [__CLASS__, 'sanitise_hex'],
            'default' => '',
        ]);

        // Post types for featured image preload
        register_setting('brighter_tweaks', self::OPT_POST_TYPES, [
            'type' => 'array',
            'sanitize_callback' => function($input) {
                return array_map('sanitize_text_field', (array)$input);
            },
            'default' => []
        ]);

        // Settings section for post types
        add_settings_section(
            'preload_on_singles',
            '??? Preload Featured Images on Singles',
            function () { 
                echo '<p>' . esc_html__('Select the post types where featured images should be preloaded on single pages.', 'brighterwebsites') . '</p>'; 
            },
            'brighter_tweaks'
        );

        add_settings_field('brighter_preload_post_types', 'Post Types', function () {
            $selected = (array) get_option(self::OPT_POST_TYPES, []);
            $post_types = get_post_types(['public' => true], 'objects');

            foreach ($post_types as $type => $obj) {
                $checked = in_array($type, $selected, true) ? 'checked' : '';
                echo '<label style="display:block;margin-bottom:4px">';
                echo '<input type="checkbox" name="' . esc_attr(self::OPT_POST_TYPES) . '[]" value="' . esc_attr($type) . '" ' . $checked . '> ';
                echo esc_html($obj->labels->singular_name . " ($type)");
                echo '</label>';
            }
        }, 'brighter_tweaks', 'preload_on_singles');

        // Register post meta for per-page preloads
        register_post_meta('page', '_bw_preloads', [
            'type' => 'array',
            'single' => true,
            'default' => [],
            'sanitize_callback' => [__CLASS__, 'sanitise_meta_array'],
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'auth_callback' => '__return_true',
        ]);
    }

    /**
     * Admin page render
     * SECURITY: Nonce verification, capability checks, output escaping
     */
    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'brighterwebsites'));
        }

        // Handle form submission
        if (!empty($_POST['bw_tweaks_nonce']) && wp_verify_nonce($_POST['bw_tweaks_nonce'], 'bw_tweaks_save')) {
            // Theme colour
            if (isset($_POST[self::OPT_THEME])) {
                update_option(self::OPT_THEME, self::sanitise_hex(wp_unslash($_POST[self::OPT_THEME])));
            }
            
            // Post types for featured image preload
            if (isset($_POST[self::OPT_POST_TYPES]) && is_array($_POST[self::OPT_POST_TYPES])) {
                $types = array_map('sanitize_text_field', $_POST[self::OPT_POST_TYPES]);
                update_option(self::OPT_POST_TYPES, $types);
            } else {
                update_option(self::OPT_POST_TYPES, []);
            }
            
            // Preloads map
            $map = [];
            if (!empty($_POST[self::OPT]) && is_array($_POST[self::OPT])) {
                foreach ($_POST[self::OPT] as $pid => $raw) {
                    $pid = (int)$pid;
                    $lines = array_filter(array_map('trim', explode("\n", wp_unslash($raw))));
                    if ($pid > 0 && $lines) {
                        $map[$pid] = array_values(array_unique(array_map([__CLASS__, 'sanitise_url'], $lines)));
                    }
                }
            }
            update_option(self::OPT, $map);
            
            echo '<div class="updated"><p>' . esc_html__('Brighter Tweaks saved.', 'brighterwebsites') . '</p></div>';
        }

        // Load data
        $theme = get_option(self::OPT_THEME, '');
        $map = get_option(self::OPT, []);

        // Pagination
        $paged = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $per_page = 20;

        $q = new WP_Query([
            'post_type' => 'page',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            's' => $search,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'fields' => 'ids',
        ]);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Brighter Tweaks', 'brighterwebsites'); ?></h1>

            <form method="get" style="margin-top:10px;">
                <input type="hidden" name="page" value="brighter_support">
                <input type="hidden" name="tab" value="tweaks">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search pages…', 'brighterwebsites'); ?>">
                <button class="button"><?php esc_html_e('Search', 'brighterwebsites'); ?></button>
            </form>

            <form method="post" style="margin-top:20px;">
                <?php wp_nonce_field('bw_tweaks_save', 'bw_tweaks_nonce'); ?>

                <h2 class="title"><?php esc_html_e('Theme Colour', 'brighterwebsites'); ?></h2>
                <p><?php esc_html_e('Used across Brighter tools where a brand colour is needed.', 'brighterwebsites'); ?></p>
                <input type="text" name="<?php echo esc_attr(self::OPT_THEME); ?>"
                       value="<?php echo esc_attr($theme); ?>" class="regular-text" placeholder="#193b2d"
                       pattern="^#?[0-9a-fA-F]{3,6}$" />
                <p class="description"><?php esc_html_e('Accepts 3 or 6-digit hex. The hash is optional.', 'brighterwebsites'); ?></p>

                <hr>

                <?php 
                // Output the featured image preload settings
                do_settings_sections('brighter_tweaks');
                ?>

                <hr>

                <h2 class="title"><?php esc_html_e('Per-Page Preloads', 'brighterwebsites'); ?></h2>
                <p><?php esc_html_e('Enter one asset URL per line. These will be preloaded only on that page. Supports images, fonts, CSS and JS.', 'brighterwebsites'); ?></p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width:35%"><?php esc_html_e('Page', 'brighterwebsites'); ?></th>
                            <th><?php esc_html_e('Assets to Preload (one per line)', 'brighterwebsites'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if ($q->have_posts()):
                        foreach ($q->posts as $pid):
                            $title = get_the_title($pid) ?: '(no title)';
                            $url = get_permalink($pid);
                            $val = isset($map[$pid]) ? implode("\n", $map[$pid]) : '';
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($title); ?></strong><br>
                                    <code><?php echo esc_html($url); ?></code><br>
                                    <small><?php echo esc_html(sprintf('ID: %d | Status: %s', $pid, get_post_status($pid))); ?></small>
                                </td>
                                <td>
                                    <textarea name="<?php echo esc_attr(self::OPT); ?>[<?php echo (int)$pid; ?>]"
                                              rows="4" style="width:100%;font-family:monospace;"><?php echo esc_textarea($val); ?></textarea>
                                </td>
                            </tr>
                            <?php
                        endforeach;
                    else:
                        echo '<tr><td colspan="2">' . esc_html__('No pages found.', 'brighterwebsites') . '</td></tr>';
                    endif;
                    ?>
                    </tbody>
                </table>

                <?php
                // FIXED: Pagination with correct tab parameter
                $total_pages = $q->max_num_pages ?: 1;
                if ($total_pages > 1) {
                    echo '<p>';
                    for ($i = 1; $i <= $total_pages; $i++) {
                        $link = add_query_arg([
                            'page' => 'brighter_support',
                            'tab' => 'tweaks',
                            's' => $search,
                            'paged' => $i,
                        ], admin_url('admin.php'));

                        if ($i === $paged) {
                            echo '<span class="button button-primary" style="margin-right:6px;">' . esc_html($i) . '</span>';
                        } else {
                            echo '<a class="button" style="margin-right:6px;" href="' . esc_url($link) . '">' . esc_html($i) . '</a>';
                        }
                    }
                    echo '</p>';
                }
                ?>

                <p><button class="button button-primary"><?php esc_html_e('Save Tweaks', 'brighterwebsites'); ?></button></p>
            </form>
        </div>
        <?php
        wp_reset_postdata();
    }

    /**
     * Output preloads for current page
     * FIXED: More aggressive checking and output
     */
    public static function output_preloads() {
        // Work on any singular page/post, not just is_page()
        if (!is_singular()) return;
        
        global $post;
        $pid = $post ? $post->ID : get_queried_object_id();
        if (!$pid) return;
        
        $urls = [];
        
        // Get preloads from settings (main tweaks table)
        $map = get_option(self::OPT, []);
        if (!empty($map[$pid]) && is_array($map[$pid])) {
            $urls = array_merge($urls, $map[$pid]);
        }
        
        // Also get preloads from post meta (sidebar box)
        $meta_urls = get_post_meta($pid, '_bw_preloads', true);
        if (is_array($meta_urls) && !empty($meta_urls)) {
            $urls = array_merge($urls, $meta_urls);
        }
        
        // Remove duplicates and empty values
        $urls = array_filter(array_unique($urls));
        
        if (empty($urls)) return;
        
        // Output preloads
        echo "\n<!-- Brighter Tweaks: Preloads -->\n";
        foreach ($urls as $u) {
            $u = self::normalise_url($u);
            if (empty($u)) continue;
            
            $attr = self::infer_preload_attrs($u);
            if (!$attr) continue;
            
            printf(
                "<link rel=\"preload\" href=\"%s\" as=\"%s\"%s%s fetchpriority=\"high\">\n",
                esc_url($u),
                esc_attr($attr['as']),
                !empty($attr['type']) ? ' type="' . esc_attr($attr['type']) . '"' : '',
                !empty($attr['crossorigin']) ? ' crossorigin' : ''
            );
        }
        echo "<!-- /Brighter Tweaks: Preloads -->\n";
    }

    /**
     * Output featured image preload for singles
     * FIXED: Now working with settings
     */
    public static function output_featured_image_preload() {
        if (!is_singular()) return;
        
        $enabled = (array) get_option(self::OPT_POST_TYPES, []);
        $post_type = get_post_type();
        
        if (!in_array($post_type, $enabled, true)) return;
        if (!has_post_thumbnail()) return;
        
        $id = get_post_thumbnail_id();
        $src = wp_get_attachment_image_url($id, 'full');
        $srcset = wp_get_attachment_image_srcset($id, 'full');
        $mime = wp_get_image_mime($id) ?: 'image/*';
        
        if ($src) {
            $src = self::normalise_url($src);
            printf(
                '<link rel="preload" as="image" href="%s"%s imagesizes="100vw" type="%s" fetchpriority="high">' . "\n",
                esc_url($src),
                $srcset ? ' imagesrcset="' . esc_attr($srcset) . '"' : '',
                esc_attr($mime)
            );
        }
    }

    /**
     * Remove Google Fonts
     * FIXED: More reliable removal that works with caching
     */
    public static function remove_google_fonts() {
        // Remove from wp_head
        remove_action('wp_head', 'wp_print_styles', 8);
        add_action('wp_head', function() {
            global $wp_styles;
            if (!is_object($wp_styles)) return;
            
            foreach ($wp_styles->registered as $handle => $style) {
                if (isset($style->src) && strpos($style->src, 'fonts.googleapis.com') !== false) {
                    wp_dequeue_style($handle);
                    wp_deregister_style($handle);
                }
            }
            
            wp_print_styles();
        }, 8);
        
        // Also strip from final HTML output
        add_action('wp_loaded', function() {
            ob_start(function($html) {
                // Remove any Google Fonts links
                $html = preg_replace(
                    '/<link[^>]*fonts\.googleapis\.com[^>]*>/i',
                    '',
                    $html
                );
                // Remove any @import statements with Google Fonts
                $html = preg_replace(
                    '/@import\s+url\([\'"]?https?:\/\/fonts\.googleapis\.com[^\)]+\)[\'"]?;?/i',
                    '',
                    $html
                );
                return $html;
            });
        });
    }

    /**
     * Add fetchpriority=high to preloaded images
     */
    public static function add_fetchpriority($attrs, $attachment, $size) {
        if (!is_singular()) return $attrs;
        
        $pid = get_queried_object_id();
        $urls = self::get_preload_urls($pid);
        
        if (empty($urls) || empty($attrs['src'])) return $attrs;
        
        foreach ($urls as $u) {
            if (strpos($attrs['src'], basename($u)) !== false) {
                $attrs['fetchpriority'] = 'high';
                $attrs['loading'] = 'eager';
                break;
            }
        }
        
        return $attrs;
    }

    /**
     * Add fetchpriority to images in content
     */
    public static function add_fetchpriority_to_content($html) {
        if (!is_singular()) return $html;
        
        $pid = get_queried_object_id();
        $urls = self::get_preload_urls($pid);
        
        if (empty($urls) || stripos($html, '<img') === false) return $html;
        
        return preg_replace_callback(
            '/<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i',
            function($m) use ($urls) {
                $src = $m[0];
                foreach ($urls as $u) {
                    if (strpos($m[1], basename($u)) !== false) {
                        if (stripos($src, 'fetchpriority=') === false) {
                            $src = str_replace('<img', '<img fetchpriority="high"', $src);
                        }
                        if (stripos($src, 'loading=') !== false) {
                            $src = preg_replace('/loading=["\']lazy["\']/i', 'loading="eager"', $src);
                        }
                        break;
                    }
                }
                return $src;
            },
            $html
        );
    }

    /**
     * Meta box for individual pages
     */
    public static function add_meta_box() {
        add_meta_box(
            'bw_preloads',
            __('Preload Assets', 'brighterwebsites'),
            [__CLASS__, 'render_meta_box'],
            'page',
            'side',
            'default'
        );
    }

    public static function render_meta_box($post) {
        $vals = get_post_meta($post->ID, '_bw_preloads', true);
        $text = is_array($vals) ? implode("\n", $vals) : '';
        
        echo '<p>' . esc_html__('Enter one asset URL per line. Supports CSS, JS, fonts, and images.', 'brighterwebsites') . '</p>';
        echo '<textarea style="width:100%;min-height:140px" name="bw_preloads_field">' . esc_textarea($text) . '</textarea>';
        wp_nonce_field('bw_preloads_save', 'bw_preloads_nonce');
    }

    public static function save_meta_box($post_id) {
        if (wp_is_post_revision($post_id)) return;
        if (!isset($_POST['bw_preloads_nonce']) || !wp_verify_nonce($_POST['bw_preloads_nonce'], 'bw_preloads_save')) return;
        if (!current_user_can('edit_page', $post_id)) return;
        
        $lines = isset($_POST['bw_preloads_field']) ? (string) wp_unslash($_POST['bw_preloads_field']) : '';
        $arr = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $lines)));
        
        $clean = [];
        foreach ($arr as $u) {
            $u = self::sanitise_url($u);
            if ($u) $clean[] = $u;
        }
        
        update_post_meta($post_id, '_bw_preloads', array_values(array_unique($clean)));
    }

    /**
     * Helper: Get all preload URLs for a page
     */
    private static function get_preload_urls($pid) {
        $map = get_option(self::OPT, []);
        $urls = isset($map[$pid]) ? (array)$map[$pid] : [];
        
        $meta = get_post_meta($pid, '_bw_preloads', true);
        if (is_array($meta)) {
            $urls = array_merge($urls, $meta);
        }
        
        return array_unique($urls);
    }

    /**
     * Normalise URL (fix /core/storage/ path)
     */
    private static function normalise_url($url) {
        return str_replace('/core/storage/', '/storage/', $url);
    }

    /**
     * Sanitisation helpers
     */
    public static function sanitise_preloads_map($map) {
        $out = [];
        if (!is_array($map)) return $out;
        
        foreach ($map as $pid => $lines) {
            $pid = (int)$pid;
            if ($pid <= 0) continue;
            
            if (is_string($lines)) $lines = explode("\n", $lines);
            $lines = array_filter(array_map('trim', $lines));
            
            $urls = [];
            foreach ($lines as $u) {
                $u = self::sanitise_url($u);
                if ($u) $urls[] = $u;
            }
            
            if ($urls) $out[$pid] = array_values(array_unique($urls));
        }
        return $out;
    }

    public static function sanitise_url($u) {
        if (strpos($u, '//') === 0) $u = 'https:' . $u;
        if (preg_match('#^/[^ ]#', $u)) return esc_url_raw($u);
        $ok = filter_var($u, FILTER_VALIDATE_URL);
        return $ok ? esc_url_raw($u) : '';
    }

    public static function sanitise_hex($hex) {
        $hex = ltrim(trim((string)$hex), '#');
        if (!preg_match('/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $hex)) return '';
        return '#' . strtolower($hex);
    }

    public static function sanitise_meta_array($value) {
        $out = [];
        foreach ((array)$value as $v) {
            if (is_scalar($v)) {
                $s = sanitize_text_field((string)$v);
                if ($s !== '') $out[] = $s;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Infer preload attributes
     */
    public static function infer_preload_attrs($url) {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        switch ($ext) {
            case 'css': return ['as' => 'style', 'type' => 'text/css'];
            case 'js': return ['as' => 'script', 'type' => 'application/javascript'];
            case 'woff2': return ['as' => 'font', 'type' => 'font/woff2', 'crossorigin' => true];
            case 'woff': return ['as' => 'font', 'type' => 'font/woff', 'crossorigin' => true];
            case 'ttf': return ['as' => 'font', 'type' => 'font/ttf', 'crossorigin' => true];
            case 'otf': return ['as' => 'font', 'type' => 'font/otf', 'crossorigin' => true];
            case 'jpg':
            case 'jpeg': return ['as' => 'image', 'type' => 'image/jpeg'];
            case 'png': return ['as' => 'image', 'type' => 'image/png'];
            case 'webp': return ['as' => 'image', 'type' => 'image/webp'];
            case 'gif': return ['as' => 'image', 'type' => 'image/gif'];
            case 'svg': return ['as' => 'image', 'type' => 'image/svg+xml'];
            case 'avif': return ['as' => 'image', 'type' => 'image/avif'];
            default: return ['as' => 'fetch', 'type' => ''];
        }
    }
}

Brighter_Tweaks::boot();