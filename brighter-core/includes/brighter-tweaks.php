<?php
/**
 * Brighter Tools: Tweaks
 *
 * File: brighter-tweaks.php
 * Purpose: General site tweaks and adjustments.
 *
 * Version: 4.0.0
 *
 * Responsibilities:
 * - Preload Images - Individual pages 
 * - Preload Images on Single Posts Select Post type
 * - Add Fetch Pri high to image input boxes
 *
 * Notes:
 * - Part of the Brighter Support Tools for Client Sites MU plugin 
 * - Loaded automatically by /mu-plugins/brighter-core.php
 */

defined('ABSPATH') || exit;

class Brighter_Tweaks {
  const OPT = 'bw_preloads_map';     // array: [ page_id => [urls...] ]
  const OPT_THEME = 'theme_colour';  // hex code
  

  public static function boot() {
    // Admin UI
    add_action('admin_init', [__CLASS__, 'register_settings']);
    add_action('admin_menu', [__CLASS__, 'hook_tab_into_support'], 20);

    // Front end output
    add_action('wp_head', [__CLASS__, 'output_preloads'], 1);
  }

  /** Register settings */
  public static function register_settings() {
    register_setting('brighter_tweaks', self::OPT, [
      'type' => 'array',
      'sanitize_callback' => [__CLASS__, 'sanitise_preloads_map'],
      'default' => [],
    ]);

    register_setting('brighter_tweaks', self::OPT_THEME, [
      'type' => 'string',
      'sanitize_callback' => [__CLASS__, 'sanitise_hex'],
      'default' => '',
    ]);
  }

  /** Add a “Brighter Tweaks” tab/section to your existing page */
  public static function hook_tab_into_support() {
     // No submenu creation needed; Tweaks will be rendered as a tab via brighter_support_render_page().

  }





  /** Admin page render */
  public static function render_page() {
    if (!current_user_can('manage_options')) return;

    // Persist saves
    if (!empty($_POST['bw_tweaks_nonce']) && wp_verify_nonce($_POST['bw_tweaks_nonce'], 'bw_tweaks_save')) {
      // Theme colour
      if (isset($_POST[self::OPT_THEME])) {
        update_option(self::OPT_THEME, self::sanitise_hex(wp_unslash($_POST[self::OPT_THEME])));
      }
      // Preloads map (textarea matrix)
      $map = [];
      if (!empty($_POST[self::OPT]) && is_array($_POST[self::OPT])) {
        foreach ($_POST[self::OPT] as $pid => $raw) {
          $pid = (int)$pid;
          $lines = array_filter(array_map('trim', explode("\n", wp_unslash($raw))));
          if ($pid > 0 && $lines) $map[$pid] = array_values(array_unique(array_map([__CLASS__,'sanitise_url'],$lines)));
        }
      }
      update_option(self::OPT, $map);
      echo '<div class="updated"><p>Brighter Tweaks saved.</p></div>';
    }

    // Load data
    $theme = get_option(self::OPT_THEME, '');
    $map   = get_option(self::OPT, []);

    // Basic page query with search and pagination
    $paged     = max(1, intval($_GET['paged'] ?? 1));
    $search    = sanitize_text_field($_GET['s'] ?? '');
    $per_page  = 20;

    $q = new WP_Query([
      'post_type'      => 'page',
      'posts_per_page' => $per_page,
      'paged'          => $paged,
      's'              => $search,
      'orderby'        => 'title',
      'order'          => 'ASC',
      'post_status'    => ['publish','draft','pending','private'],
      'fields'         => 'ids',
    ]);

    ?>
    <div class="wrap">
      <h1>Brighter Tweaks</h1>

      <form method="get" style="margin-top:10px;">
        <input type="hidden" name="page" value="brighter_tweaks">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search pages…">
        <button class="button">Search</button>
      </form>

      <form method="post" style="margin-top:20px;">
        <?php wp_nonce_field('bw_tweaks_save', 'bw_tweaks_nonce'); ?>

        <h2 class="title">Theme Colour</h2>
        <p>Used across Brighter tools where a brand colour is needed.</p>
        <input type="text" name="<?php echo esc_attr(self::OPT_THEME); ?>"
               value="<?php echo esc_attr($theme); ?>" class="regular-text" placeholder="#193b2d"
               pattern="^#?[0-9a-fA-F]{3,6}$" />
        <p class="description">Accepts 3 or 6-digit hex. The hash is optional.</p>

        <hr>

        <h2 class="title">Per-Page Preloads</h2>
        <p>Enter one asset URL per line. These will be preloaded only on that page. Supports images, fonts, CSS and JS.</p>

        <table class="widefat striped">
          <thead>
            <tr>
              <th style="width:35%">Page</th>
              <th>Assets to Preload (one per line)</th>
            </tr>
          </thead>
          <tbody>
          <?php
          if ($q->have_posts()):
            foreach ($q->posts as $pid):
              $title = get_the_title($pid) ?: '(no title)';
              $url   = get_permalink($pid);
              $val   = isset($map[$pid]) ? implode("\n", $map[$pid]) : '';
              ?>
              <tr>
                <td>
                  <strong><?php echo esc_html($title); ?></strong><br>
                  <code><?php echo esc_html($url); ?></code><br>
                  <small>ID: <?php echo (int)$pid; ?> | Status: <?php echo esc_html(get_post_status($pid)); ?></small>
                </td>
                <td>
                  <textarea name="<?php echo esc_attr(self::OPT); ?>[<?php echo (int)$pid; ?>]"
                            rows="4" style="width:100%;font-family:monospace;"><?php echo esc_textarea($val); ?></textarea>
                </td>
              </tr>
              <?php
            endforeach;
          else:
            echo '<tr><td colspan="2">No pages found.</td></tr>';
          endif;
          ?>
          </tbody>
        </table>

        <?php
        // pagination
// pagination (force parent page + tab param)
$total_pages = $q->max_num_pages ?: 1;
if ($total_pages > 1){
  echo '<p>';
  for ($i=1;$i<=$total_pages;$i++){
    $link = add_query_arg([
      'page' => 'brighter_support',
      'tab'  => 'brighter_tweaks',
      's'    => $search,
      'paged'=> $i,
    ], admin_url('admin.php'));

    echo ($i===$paged)
      ? "<span class='button button-primary' style='margin-right:6px;'>$i</span>"
      : "<a class='button' style='margin-right:6px;' href='".esc_url($link)."'>$i</a>";
  }
  echo '</p>';
}
        ?>

        <p><button class="button button-primary">Save Tweaks</button></p>
      </form>
    </div>
    <?php
    wp_reset_postdata();
  }

  /** Front end: print rel=preload for current page */
  public static function output_preloads() {
    if (!is_page()) return;
    $map = get_option(self::OPT, []);
    if (empty($map)) return;

    $pid = get_queried_object_id();
    if (empty($map[$pid])) return;

    foreach ($map[$pid] as $u) {
      $attr = self::infer_preload_attrs($u);
      if (!$attr) continue;
      printf(
        "<link rel=\"preload\" href=\"%s\" as=\"%s\"%s%s>\n",
        esc_url($u),
        esc_attr($attr['as']),
        !empty($attr['type']) ? ' type="'.esc_attr($attr['type']).'"' : '',
        !empty($attr['crossorigin']) ? ' crossorigin' : ''
      );
    }
  }

  /** Sanitisation helpers */
  public static function sanitise_preloads_map($map) {
    $out = [];
    if (!is_array($map)) return $out;
    foreach ($map as $pid => $lines) {
      $pid = (int)$pid;
      if ($pid <= 0) continue;
      if (is_string($lines)) $lines = explode("\n", $lines);
      $lines = array_filter(array_map('trim', $lines));
      $urls  = [];
      foreach ($lines as $u) {
        $u = self::sanitise_url($u);
        if ($u) $urls[] = $u;
      }
      if ($urls) $out[$pid] = array_values(array_unique($urls));
    }
    return $out;
  }
  public static function sanitise_url($u) {
    // allow absolute or site-relative
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

  /** Guess proper as= and type= attributes */
  public static function infer_preload_attrs($url) {
    $u = parse_url($url);
    $path = $u['path'] ?? '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    switch ($ext) {
      case 'css': return ['as'=>'style','type'=>'text/css'];
      case 'js':  return ['as'=>'script','type'=>'application/javascript'];
      case 'woff2': return ['as'=>'font','type'=>'font/woff2','crossorigin'=>true];
      case 'woff':  return ['as'=>'font','type'=>'font/woff','crossorigin'=>true];
      case 'ttf':   return ['as'=>'font','type'=>'font/ttf','crossorigin'=>true];
      case 'otf':   return ['as'=>'font','type'=>'font/otf','crossorigin'=>true];
      case 'jpg':
      case 'jpeg': return ['as'=>'image','type'=>'image/jpeg'];
      case 'png':  return ['as'=>'image','type'=>'image/png'];
      case 'webp': return ['as'=>'image','type'=>'image/webp'];
      case 'gif':  return ['as'=>'image','type'=>'image/gif'];
      case 'svg':  return ['as'=>'image','type'=>'image/svg+xml'];
      default:
        // Unknown, still allow as "fetch" for generic assets
        return ['as'=>'fetch','type'=>''];
    }
  }
}

Brighter_Tweaks::boot();
add_action( 'init', function () {

  // very safe sanitizer: strings only, trimmed
  $sanitize_array_of_strings = function( $value ) {
    $out = [];
    foreach ( (array) $value as $v ) {
      if ( is_scalar( $v ) ) {
        $s = sanitize_text_field( (string) $v );
        if ( $s !== '' ) {
          $out[] = $s;
        }
      }
    }
    // unique + reindex
    return array_values( array_unique( $out ) );
  };

  register_post_meta(
    'page',
    '_bw_preloads',
    [
      'type'              => 'array',
      'single'            => true,
      'default'           => [],
      'sanitize_callback' => $sanitize_array_of_strings,
      'show_in_rest'      => [
        'schema' => [
          'type'  => 'array',
          'items' => [ 'type' => 'string' ],
        ],
      ],
      'auth_callback'     => '__return_true',
    ]
  );

}, 10 );



// Add meta box
add_action('add_meta_boxes', function () {
  add_meta_box('bw_preloads', 'Preload Assets', function ($post) {
    $vals = get_post_meta($post->ID, '_bw_preloads', true);
    $text = is_array($vals) ? implode("\n", $vals) : '';
    echo '<p>Enter one asset URL per line. Supports CSS, JS, fonts, and images.</p>';
    echo '<textarea style="width:100%;min-height:140px" name="bw_preloads_field">' . esc_textarea($text) . '</textarea>';
    wp_nonce_field('bw_preloads_save', 'bw_preloads_nonce');
  }, 'page', 'side', 'default');
});

// Save meta box
add_action('save_post_page', function ($post_id) {
  if (wp_is_post_revision($post_id)) return;
  if (!isset($_POST['bw_preloads_nonce']) || !wp_verify_nonce($_POST['bw_preloads_nonce'], 'bw_preloads_save')) return;
  if (!current_user_can('edit_page', $post_id)) return;
  $lines = isset($_POST['bw_preloads_field']) ? (string) wp_unslash($_POST['bw_preloads_field']) : '';
  // Reuse the sanitizer above by writing a string; register_post_meta will run sanitize when updating via update_post_meta?
  // We’ll sanitize here to be explicit:
  $arr = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $lines)));
  // Minimal URL sanitise
  $clean = [];
  foreach ($arr as $u) {
    if (strpos($u, '//') === 0) $u = 'https:' . $u;
    if ($u !== '' && ($u[0] === '/' || filter_var($u, FILTER_VALIDATE_URL))) $clean[] = esc_url_raw($u);
  }
  update_post_meta($post_id, '_bw_preloads', array_values(array_unique($clean)));
});

add_action('wp_head', function () {
  if (!is_page()) return;
  $assets = get_post_meta(get_queried_object_id(), '_bw_preloads', true);
  if (empty($assets) || !is_array($assets)) return;

  foreach ($assets as $u) {
    $attr = bw_infer_preload_attrs($u);
    if (!$attr) continue;
    printf(
      "<link rel=\"preload\" href=\"%s\" as=\"%s\"%s%s>\n",
      esc_url($u),
      esc_attr($attr['as']),
      !empty($attr['type']) ? ' type="'.esc_attr($attr['type']).'"' : '',
      !empty($attr['crossorigin']) ? ' crossorigin' : ''
    );
  }
}, 1);



// Helper: guess proper as= and type=
function bw_infer_preload_attrs($url) {
  $path = parse_url($url, PHP_URL_PATH) ?: '';
  $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  switch ($ext) {
    case 'css':   return ['as'=>'style','type'=>'text/css'];
    case 'js':    return ['as'=>'script','type'=>'application/javascript'];
    case 'woff2': return ['as'=>'font','type'=>'font/woff2','crossorigin'=>true];
    case 'woff':  return ['as'=>'font','type'=>'font/woff','crossorigin'=>true];
    case 'ttf':   return ['as'=>'font','type'=>'font/ttf','crossorigin'=>true];
    case 'otf':   return ['as'=>'font','type'=>'font/otf','crossorigin'=>true];
    case 'jpg': case 'jpeg': return ['as'=>'image','type'=>'image/jpeg'];
    case 'png':   return ['as'=>'image','type'=>'image/png'];
    case 'webp':  return ['as'=>'image','type'=>'image/webp'];
    case 'gif':   return ['as'=>'image','type'=>'image/gif'];
    case 'svg':   return ['as'=>'image','type'=>'image/svg+xml'];
    default:      return ['as'=>'fetch','type'=>''];
  }
}


/**
 * LCP helpers:
 * - Keep the textarea as "one URL per line".
 * - For matching images on that page, force eager + high priority.
 * - Works for both attachment images and hard-coded <img> in content.
 */

/** Get the configured URL list for the current singular page */
function bw_get_preload_urls_for_current_page() {
    if ( ! is_singular() ) return [];
    $pid = get_queried_object_id();

    // Adjust this option name if your plugin stores it differently.
    // Many of your snippets referenced Brighter_Tweaks::OPT, so use that if available.
    $opt_name = defined('Brighter_Tweaks::OPT') ? Brighter_Tweaks::OPT : 'brighter_preloads_map';

    $map  = (array) get_option($opt_name, []);
    $list = isset($map[$pid]) ? (array) $map[$pid] : [];

    // The DB may store as newline-separated text. Normalise to array.
    if (count($list) === 1 && is_string($list[0]) && strpos($list[0], "\n") !== false) {
        $list = preg_split('/\r\n|\r|\n/', $list[0]);
    }
    $list = array_values(array_filter(array_map('trim', $list)));
    return $list;
}

/** True if $src matches any configured preload URL exactly or by prefix */
function bw_src_matches_lcp_list($src, $list) {
    if ( ! $src || ! $list ) return false;
    foreach ($list as $u) {
        // accept exact, prefix, or prefix without query
        if ($src === $u) return true;
        if (strpos($src, $u) === 0) return true;
        $u_base = strtok($u, '?');
        if ($u_base && strpos($src, $u_base) === 0) return true;
    }
    return false;
}

/**
 * 1) WordPress attachment images <img> generated via wp_get_attachment_image()
 * Force eager + fetchpriority=high for matching LCP candidates.
 */
add_filter('wp_get_attachment_image_attributes', function ($attr, $attachment, $size) {
    // High priority so we override theme or lazy-load plugins.
    $list = bw_get_preload_urls_for_current_page();
    if ( ! $list ) return $attr;

    $src = wp_get_attachment_image_url($attachment->ID, 'full');
    if ( ! $src ) return $attr;

    if (bw_src_matches_lcp_list($src, $list)) {
        // Remove lazy if present and set eager.
        $attr['loading'] = 'eager';
        // Help the preload get top scheduling.
        $attr['fetchpriority'] = 'high';
        // Optional, improves first paint for hero images.
        if (empty($attr['decoding'])) {
            $attr['decoding'] = 'sync';
        }
    }
    return $attr;
}, 99, 3);

/**
 * 2) Hard-coded <img> in post content.
 * Inject attributes on matching src.
 */
add_filter('the_content', function ($html) {
    $list = bw_get_preload_urls_for_current_page();
    if ( ! $list || ! $html ) return $html;

    foreach ($list as $u) {
        $u_quoted = preg_quote($u, '~');
        // Match: <img ... src="THE_URL[maybe more]" ... >
        $pattern = '~(<img\b[^>]*\bsrc=(["\'])' . $u_quoted . '[^"\']*\2[^>]*)(?=>)~i';

        $html = preg_replace_callback($pattern, function ($m) {
            $tag = $m[1];

            // Remove loading="lazy" if present.
            $tag = preg_replace('/\sloading=(["\'])(lazy)\1/i', '', $tag);

            // Add attributes if missing.
            if (!preg_match('/\bloading=/', $tag))       $tag .= ' loading="eager"';
            if (!preg_match('/\bfetchpriority=/', $tag)) $tag .= ' fetchpriority="high"';
            if (!preg_match('/\bdecoding=/', $tag))      $tag .= ' decoding="sync"';

            return $tag;
        }, $html);
    }
    return $html;
}, 99);


//Register Settings for Preload on Single Post Types
add_action('admin_init', function () {

    register_setting('brighter_tweaks', 'brighter_preload_post_types', [
        'type' => 'array',
        'sanitize_callback' => function($input) {
            return array_map('sanitize_text_field', (array)$input);
        },
        'default' => []
    ]);

    add_settings_section(
        'preload_on_singles',
        '?Preload Featured Images on Singles',
        function () { 
            echo '<p>Select the post types where featured images should be preloaded on single pages.</p>'; 
        },
        'brighter_tweaks'
    );

    add_settings_field('brighter_preload_post_types', 'Post Types', function () {
        $selected = (array) get_option('brighter_preload_post_types', []);
        $post_types = get_post_types(['public' => true], 'objects');

        foreach ($post_types as $type => $obj) {
            $checked = in_array($type, $selected) ? 'checked' : '';
            echo '<label style="display:block;margin-bottom:4px">';
            echo '<input type="checkbox" name="brighter_preload_post_types[]" value="' . esc_attr($type) . '" ' . $checked . '> ';
            echo esc_html($obj->labels->singular_name . " ($type)");
            echo '</label>';
        }
    }, 'brighter_tweaks', 'preload_on_singles');
});


// Update the preload function to use setting
function brighterweb_preload_featured_image() {
    if (is_singular()) {
        $enabled   = (array) get_option('brighter_preload_post_types', []);
        $post_type = get_post_type();

        if (in_array($post_type, $enabled, true) && has_post_thumbnail()) {
            $id     = get_post_thumbnail_id();
            $src    = wp_get_attachment_image_url( $id, 'full' );
            $srcset = wp_get_attachment_image_srcset( $id, 'full' );
            $mime   = wp_get_image_mime( $id ) ?: 'image/*';

            if ( $src ) {
                // normalise hidden path to the public one
                $src = str_replace('/core/storage/', '/storage/', $src);
                printf(
                    '<link rel="preload" as="image" href="%s"%s imagesizes="100vw" type="%s" fetchpriority="high" />' . "\n",
                    esc_url( $src ),
                    $srcset ? ' imagesrcset="' . esc_attr( $srcset ) . '"' : '',
                    esc_attr( $mime )
                );
            }
        }
    }
}
add_action('wp_head', 'brighterweb_preload_featured_image', 1);




/**
 * Preload + fetchpriority=high for all image URLs listed in _bw_preloads
 * – prints <link rel="preload" as="image" ... fetchpriority="high">
 * – also adds fetchpriority="high" to matching <img> tags at render time
 */

if ( ! function_exists( 'bw_preload_get_targets' ) ) {
    function bw_preload_get_targets(): array {
        if ( ! is_singular() ) return [];

        $urls = (array) get_post_meta( get_the_ID(), '_bw_preloads', true );
        if ( empty( $urls ) ) return [];

        // normalise: dedupe, drop empties, fix /core/storage -> /storage
        $seen = [];
        foreach ( $urls as $u ) {
            if ( ! is_scalar( $u ) ) continue;
            $u = trim( (string) $u );
            if ( $u === '' ) continue;
            $u = str_replace( '/core/storage/', '/storage/', $u );
            $seen[ $u ] = true;
        }
        $urls = array_keys( $seen );

        // also keep basenames for easy <img> matching (handles resized variants)
        $basenames = [];
        foreach ( $urls as $u ) {
            $p = parse_url( $u, PHP_URL_PATH );
            if ( $p ) $basenames[ strtolower( basename( $p ) ) ] = true;
        }

        return [
            'urls'      => $urls,          // full URLs for <link rel=preload>
            'basenames' => $basenames,     // filename matching for <img>
        ];
    }
}

/** Print preloads for ALL listed images with fetchpriority=high */
add_action( 'wp_head', function () {
    $t = bw_preload_get_targets();
    if ( empty( $t['urls'] ) ) return;

    foreach ( $t['urls'] as $u ) {
        $path = parse_url( $u, PHP_URL_PATH ) ?: '';
        $ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

        if ( in_array( $ext, ['jpg','jpeg','png','gif','webp','avif','svg'], true ) ) {
            // choose MIME
            $mime = [
                'jpg' => 'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif',
                'webp'=>'image/webp','avif'=>'image/avif','svg'=>'image/svg+xml'
            ][$ext] ?? 'image/*';

            printf(
                '<link rel="preload" as="image" href="%s" type="%s" fetchpriority="high" imagesizes="100vw" />' . "\n",
                esc_url( $u ),
                esc_attr( $mime )
            );
        } elseif ( $ext === 'css' ) {
            printf( '<link rel="preload" as="style" href="%s" />' . "\n", esc_url( $u ) );
        } elseif ( in_array( $ext, ['js','mjs'], true ) ) {
            printf( '<link rel="preload" as="script" href="%s" />' . "\n", esc_url( $u ) );
        } elseif ( in_array( $ext, ['woff2','woff','ttf','otf'], true ) ) {
            $mime = $ext === 'woff2' ? 'font/woff2' : ($ext === 'woff' ? 'font/woff' : ($ext === 'ttf' ? 'font/ttf' : 'font/otf'));
            printf(
                '<link rel="preload" as="font" href="%s" type="%s" crossorigin />' . "\n",
                esc_url( $u ), esc_attr( $mime )
            );
        }
    }
}, 1 );

/** Add fetchpriority=high to matching <img> elements (for builders/themes output) */
add_filter( 'wp_get_attachment_image_attributes', function ( $attrs ) {
    $t = bw_preload_get_targets();
    if ( empty( $t['basenames'] ) || empty( $attrs['src'] ) ) return $attrs;

    $name = strtolower( basename( parse_url( $attrs['src'], PHP_URL_PATH ) ) );
    if ( isset( $t['basenames'][ $name ] ) ) {
        $attrs['fetchpriority'] = 'high';
        // optional: force eager load for true hero images
        // $attrs['loading'] = 'eager';
    }
    return $attrs;
}, 10 );

/** Backup: inject fetchpriority=high into first match in post content HTML (non-attachment imgs) */
add_filter( 'the_content', function ( $html ) {
    $t = bw_preload_get_targets();
    if ( empty( $t['basenames'] ) || stripos( $html, '<img' ) === false ) return $html;

    // Add to every matching <img ...> in the content (not just first)
    return preg_replace_callback(
        '/<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i',
        function ( $m ) use ( $t ) {
            $src  = $m[1];
            $name = strtolower( basename( parse_url( $src, PHP_URL_PATH ) ) );
            if ( isset( $t['basenames'][ $name ] ) && stripos( $m[0], 'fetchpriority=' ) === false ) {
                return preg_replace('/^<img/i', '<img fetchpriority="high"', $m[0]);
            }
            return $m[0];
        },
        $html
    );
}, 20 );


/**
 * Normalise image preloads and add fetchpriority=high
 * - Converts /core/storage/ to /storage/
 * - Adds fetchpriority="high" to <link rel="preload" as="image"> tags that lack it
 */
add_action('template_redirect', function () {
    // Only buffer full HTML responses
    if ( is_feed() || is_admin() || wp_doing_ajax() ) { return; }
    if ( defined('REST_REQUEST') && REST_REQUEST ) { return; }

    ob_start(function ($html) {
        // Normalise hidden path to public one
        $html = str_replace('/core/storage/', '/storage/', $html);

        // Add fetchpriority="high" to image preloads that don't have it
        $html = preg_replace_callback(
            '#<link\s+([^>]*\brel=["\']preload["\'][^>]*\bas=["\']image["\'][^>]*)>#i',
            function ($m) {
                $tag = $m[0];
                // Already has fetchpriority?
                if ( stripos($tag, 'fetchpriority=') !== false ) {
                    return $tag;
                }
                // Inject before closing ">"
                return rtrim($tag, '>') . ' fetchpriority="high">';
            },
            $html
        );

        return $html;
    });
});


