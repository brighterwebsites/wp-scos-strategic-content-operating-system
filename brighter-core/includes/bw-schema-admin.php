<?php
/**
 * Schema Admin Interface
 *
 * Adds Schema submenu under Brighter Support with tabbed schema settings:
 * Local Business | Success Stories | Product | Service
 * Path: Support > Schema
 *
 * @see SCHEMA-ENHANCEMENT-PLAN.md
 */

if (!defined('ABSPATH')) exit;

// Register all schema options
add_action('admin_init', function() {
    $schema_options = [
        'bw_local_business_schema'   => 'Local Business',
        'bw_success_stories_schema'  => 'Success Stories',
        'bw_product_schema'          => 'Product',
        'bw_service_schema'          => 'Service',
    ];
    foreach ($schema_options as $option => $label) {
        register_setting('bw_schema_settings', $option, [
            'type' => 'string',
            'sanitize_callback' => function($value) {
                return is_string($value) ? $value : '';
            },
            'default' => ''
        ]);
    }
    register_setting('bw_schema_settings', 'bw_product_post_ids', [
        'type' => 'string',
        'sanitize_callback' => function($value) {
            return bw_schema_sanitize_post_ids($value);
        },
        'default' => ''
    ]);
    register_setting('bw_schema_settings', 'bw_service_post_ids', [
        'type' => 'string',
        'sanitize_callback' => function($value) {
            return bw_schema_sanitize_post_ids($value);
        },
        'default' => ''
    ]);
});

function bw_schema_sanitize_post_ids($value) {
    $value = wp_strip_all_tags($value);
    $ids = array_filter(array_map('absint', preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY)));
    return implode(',', $ids);
}

add_action('admin_menu', function() {
    if (!is_admin()) return;
    add_submenu_page(
        'brighter_support',
        'Schema',
        'Schema',
        'manage_options',
        'brighter-schema',
        'bw_schema_render_page',
        4
    );
});

function bw_schema_get_tabs() {
    return [
        'local-business'   => __('Local Business', 'brighterwebsites'),
        'success-stories'  => __('Success Stories', 'brighterwebsites'),
        'product'         => __('Product', 'brighterwebsites'),
        'service'          => __('Service', 'brighterwebsites'),
    ];
}

function bw_schema_render_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $tabs = bw_schema_get_tabs();
    $current_tab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $tabs) ? $_GET['tab'] : 'local-business';
    $base_url = add_query_arg('page', 'brighter-schema', admin_url('admin.php'));

    // Handle form submission (all options in one form) — always save raw value so user never loses input
    if (isset($_POST['bw_schema_settings_nonce']) && wp_verify_nonce($_POST['bw_schema_settings_nonce'], 'bw_schema_settings')) {
        $invalid = [];
        $saved = [];

        foreach (['bw_local_business_schema', 'bw_success_stories_schema', 'bw_product_schema', 'bw_service_schema'] as $key) {
            if (!isset($_POST[$key])) continue;
            $schema = wp_unslash($_POST[$key]);
            $schema = trim($schema);
            update_option($key, $schema);
            $saved[] = $key;
            if (!empty($schema)) {
                $decoded = json_decode($schema, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                    $invalid[] = $key;
                }
            }
        }
        if (isset($_POST['bw_product_post_ids'])) {
            update_option('bw_product_post_ids', bw_schema_sanitize_post_ids(wp_unslash($_POST['bw_product_post_ids'])));
            $saved[] = 'bw_product_post_ids';
        }
        if (isset($_POST['bw_service_post_ids'])) {
            update_option('bw_service_post_ids', bw_schema_sanitize_post_ids(wp_unslash($_POST['bw_service_post_ids'])));
            $saved[] = 'bw_service_post_ids';
        }

        if (!empty($saved)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Schema settings saved. You can edit until JSON is valid; invalid blocks are stored but not output on the site.', 'brighterwebsites') . '</p></div>';
        }
        if (!empty($invalid)) {
            $labels = [
                'bw_local_business_schema'  => __('Local Business', 'brighterwebsites'),
                'bw_success_stories_schema' => __('Success Stories', 'brighterwebsites'),
                'bw_product_schema'         => __('Product', 'brighterwebsites'),
                'bw_service_schema'         => __('Service', 'brighterwebsites'),
            ];
            $list = array_map(function($k) use ($labels) { return $labels[$k] ?? $k; }, $invalid);
            echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__('Invalid JSON (not output until fixed):', 'brighterwebsites') . '</strong> ' . esc_html(implode(', ', $list)) . '</p></div>';
        }
    }

    $local_business_schema = get_option('bw_local_business_schema', '');
    $success_stories_schema = get_option('bw_success_stories_schema', '');
    $product_schema = get_option('bw_product_schema', '');
    $product_post_ids = get_option('bw_product_post_ids', '');
    $service_schema = get_option('bw_service_schema', '');
    $service_post_ids = get_option('bw_service_post_ids', '');
    ?>
    <!-- Brighter Schema Admin v2 (tabbed: Local Business | Success Stories | Product | Service) -->
    <div class="wrap" id="bw-schema-admin-wrap">
        <h1><?php esc_html_e('Schema Settings', 'brighterwebsites'); ?></h1>
        <p class="description" style="margin-top: -8px;"><?php esc_html_e('Local Business | Success Stories | Product | Service', 'brighterwebsites'); ?></p>

        <nav class="nav-tab-wrapper wp-clearfix" style="margin-top: 16px;" aria-label="<?php esc_attr_e('Schema tabs', 'brighterwebsites'); ?>">
            <?php foreach ($tabs as $tab => $label) : ?>
                <a href="<?php echo esc_url(add_query_arg('tab', $tab, $base_url)); ?>"
                   class="nav-tab <?php echo $current_tab === $tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </nav>

        <form method="post" id="bw-schema-form">
            <?php wp_nonce_field('bw_schema_settings', 'bw_schema_settings_nonce'); ?>

            <div class="bw-schema-tab-content" style="margin-top: 20px;">
                <?php if ($current_tab === 'local-business') : ?>
                    <h2><?php esc_html_e('Local Business Schema', 'brighterwebsites'); ?></h2>
                    <p><?php esc_html_e('Enter your LocalBusiness JSON-LD schema. It will be included in your site\'s schema graph (site-wide).', 'brighterwebsites'); ?></p>
                    <div class="notice notice-info inline">
                        <p><strong><?php esc_html_e('How to use:', 'brighterwebsites'); ?></strong></p>
                        <ul style="list-style: disc; margin-left: 20px; line-height: 1.8;">
                            <li><?php esc_html_e('Enter valid JSON-LD for LocalBusiness. Include @type and @id.', 'brighterwebsites'); ?></li>
                            <li><?php esc_html_e('Leave empty to disable LocalBusiness schema.', 'brighterwebsites'); ?></li>
                        </ul>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="bw_local_business_schema"><?php esc_html_e('Local Business Schema (JSON-LD)', 'brighterwebsites'); ?></label></th>
                            <td>
                                <textarea id="bw_local_business_schema" name="bw_local_business_schema" rows="22" class="large-text code bw-schema-json" style="font-family: monospace; font-size: 12px; width: 100%; max-width: 800px;"
                                          placeholder='{"@type": "LocalBusiness", "@id": "<?php echo esc_js(home_url('/#organization')); ?>", "name": "Your Business", "url": "<?php echo esc_js(home_url('/')); ?>"}'><?php echo esc_textarea($local_business_schema); ?></textarea>
                                <div id="bw_local_business_schema-validation" class="bw-schema-validation" aria-live="polite" style="margin-top:6px;padding:8px 12px;border-radius:4px;display:none;"></div>
                                <p class="description"><?php esc_html_e('Single block: { }. Multiple blocks: [ { }, { } ].', 'brighterwebsites'); ?></p>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>

                <?php if ($current_tab === 'success-stories') : ?>
                    <h2><?php esc_html_e('Success Stories Schema', 'brighterwebsites'); ?></h2>
                    <p><?php esc_html_e('This template is merged into the schema graph on every single Project/Success Story (CPT) page.', 'brighterwebsites'); ?></p>
                    <div class="notice notice-info inline">
                        <p><strong><?php esc_html_e('Where it appears:', 'brighterwebsites'); ?></strong> <?php esc_html_e('Single project/success story posts only (post type: projects).', 'brighterwebsites'); ?></p>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="bw_success_stories_schema"><?php esc_html_e('Success Stories Schema (JSON-LD)', 'brighterwebsites'); ?></label></th>
                            <td>
                                <textarea id="bw_success_stories_schema" name="bw_success_stories_schema" rows="22" class="large-text code bw-schema-json" style="font-family: monospace; font-size: 12px; width: 100%; max-width: 800px;"
                                          placeholder='{"@type": "CreativeWork", "name": "Example Success Story"}'><?php echo esc_textarea($success_stories_schema); ?></textarea>
                                <div id="bw_success_stories_schema-validation" class="bw-schema-validation" aria-live="polite" style="margin-top:6px;padding:8px 12px;border-radius:4px;display:none;"></div>
                                <p class="description"><?php esc_html_e('Single block: one object { }. Multiple blocks: one array [ { }, { } ]. Edit until the box shows valid JSON; invalid blocks are saved but not output.', 'brighterwebsites'); ?></p>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>

                <?php if ($current_tab === 'product') : ?>
                    <h2><?php esc_html_e('Product Schema', 'brighterwebsites'); ?></h2>
                    <p><?php esc_html_e('This template is merged into the schema graph on single post or page when its ID is in the list below.', 'brighterwebsites'); ?></p>
                    <div class="notice notice-info inline">
                        <p><strong><?php esc_html_e('Where it appears:', 'brighterwebsites'); ?></strong> <?php esc_html_e('Single post or page only, when its ID is in the "Post/Page IDs" list.', 'brighterwebsites'); ?></p>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="bw_product_post_ids"><?php esc_html_e('Post/Page IDs', 'brighterwebsites'); ?></label></th>
                            <td>
                                <input type="text" id="bw_product_post_ids" name="bw_product_post_ids" value="<?php echo esc_attr($product_post_ids); ?>"
                                       class="regular-text" placeholder="123, 456, 789"/>
                                <p class="description"><?php esc_html_e('Comma-separated post or page IDs that should output Product schema.', 'brighterwebsites'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bw_product_schema"><?php esc_html_e('Product Schema (JSON-LD)', 'brighterwebsites'); ?></label></th>
                            <td>
                                <textarea id="bw_product_schema" name="bw_product_schema" rows="18" class="large-text code bw-schema-json" style="font-family: monospace; font-size: 12px; width: 100%; max-width: 800px;"
                                          placeholder='{"@type": "Product", "name": "Product Name"}'><?php echo esc_textarea($product_schema); ?></textarea>
                                <div id="bw_product_schema-validation" class="bw-schema-validation" aria-live="polite" style="margin-top:6px;padding:8px 12px;border-radius:4px;display:none;"></div>
                                <p class="description"><?php esc_html_e('Single block: { }. Multiple blocks: [ { }, { } ].', 'brighterwebsites'); ?></p>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>

                <?php if ($current_tab === 'service') : ?>
                    <h2><?php esc_html_e('Service Schema', 'brighterwebsites'); ?></h2>
                    <p><?php esc_html_e('This template is merged into the schema graph on single post or page when its ID is in the list below.', 'brighterwebsites'); ?></p>
                    <div class="notice notice-info inline">
                        <p><strong><?php esc_html_e('Where it appears:', 'brighterwebsites'); ?></strong> <?php esc_html_e('Single post or page only, when its ID is in the "Post/Page IDs" list.', 'brighterwebsites'); ?></p>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="bw_service_post_ids"><?php esc_html_e('Post/Page IDs', 'brighterwebsites'); ?></label></th>
                            <td>
                                <input type="text" id="bw_service_post_ids" name="bw_service_post_ids" value="<?php echo esc_attr($service_post_ids); ?>"
                                       class="regular-text" placeholder="123, 456, 789"/>
                                <p class="description"><?php esc_html_e('Comma-separated post or page IDs that should output Service schema.', 'brighterwebsites'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bw_service_schema"><?php esc_html_e('Service Schema (JSON-LD)', 'brighterwebsites'); ?></label></th>
                            <td>
                                <textarea id="bw_service_schema" name="bw_service_schema" rows="18" class="large-text code bw-schema-json" style="font-family: monospace; font-size: 12px; width: 100%; max-width: 800px;"
                                          placeholder='{"@type": "Service", "name": "Service Name"}'><?php echo esc_textarea($service_schema); ?></textarea>
                                <div id="bw_service_schema-validation" class="bw-schema-validation" aria-live="polite" style="margin-top:6px;padding:8px 12px;border-radius:4px;display:none;"></div>
                                <p class="description"><?php esc_html_e('Single block: { }. Multiple blocks: [ { }, { } ].', 'brighterwebsites'); ?></p>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>
            </div>

            <p class="submit">
                <?php submit_button(__('Save Schema', 'brighterwebsites'), 'primary', 'submit', false); ?>
            </p>
        </form>

        <hr style="margin: 30px 0;">
        <h3><?php esc_html_e('Schema variables', 'brighterwebsites'); ?></h3>
        <p><?php esc_html_e('Use these placeholders in your JSON; they are replaced when the schema is output.', 'brighterwebsites'); ?></p>
        <details style="margin-bottom: 1em;">
            <summary style="cursor: pointer; font-weight: 600;"><?php esc_html_e('Available variables (click to expand)', 'brighterwebsites'); ?></summary>
            <ul style="line-height: 1.9; margin-top: 8px; margin-left: 1em;">
                <li><code>%%post_title%%</code> <?php esc_html_e('– Post/page title', 'brighterwebsites'); ?></li>
                <li><code>%%post_excerpt%%</code> <?php esc_html_e('– Excerpt', 'brighterwebsites'); ?></li>
                <li><code>%%post_date%%</code>, <code>%%post_modified%%</code> <?php esc_html_e('– Date (ISO 8601)', 'brighterwebsites'); ?></li>
                <li><code>%%post_url%%</code>, <code>%%post_id%%</code>, <code>%%post_name%%</code></li>
                <li><code>%%post_author%%</code> <?php esc_html_e('– Author display name', 'brighterwebsites'); ?></li>
                <li><code>%%post_thumbnail_url%%</code> <?php esc_html_e('– Featured image URL', 'brighterwebsites'); ?></li>
                <li><code>%%post_thumbnail%%</code> <?php esc_html_e('– Featured image as ImageObject (use as whole value for "image")', 'brighterwebsites'); ?></li>
                <li><code>%%site_name%%</code>, <code>%%site_url%%</code> <?php esc_html_e('– Site info (any context)', 'brighterwebsites'); ?></li>
                <li><code>%%_cmeta_meta_key%%</code> <?php esc_html_e('– Custom meta (replace meta_key)', 'brighterwebsites'); ?></li>
                <li><code>%%_acf_field_name%%</code> <?php esc_html_e('– ACF field (replace field_name)', 'brighterwebsites'); ?></li>
                <li><code>%%_rating_json%%</code>, <code>%%_acf_offers_json%%</code> <?php esc_html_e('– JSON/repeater: use unquoted as value (e.g. "aggregateRating": %%_rating_json%%). Custom PHP supplies the array via filter bw_schema_resolve_variable.', 'brighterwebsites'); ?></li>
            </ul>
            <p class="description"><?php esc_html_e('Example: "name": "%%post_title%%". For object/array injection use unquoted: "aggregateRating": %%_rating_json%%. Per-post schema use the current post; Local Business uses site_name/site_url only.', 'brighterwebsites'); ?></p>
            <p class="description"><strong><?php esc_html_e('Multiple blocks:', 'brighterwebsites'); ?></strong> <?php esc_html_e('Use a single array with comma-separated objects: [ { "@type": "CreativeWork", ... }, { "@type": "Product", ... } ]. One object without brackets is valid; two objects need wrapping [ ].', 'brighterwebsites'); ?></p>
        </details>
        <h3><?php esc_html_e('Schema resources', 'brighterwebsites'); ?></h3>
        <ul style="line-height: 1.8;">
            <li><a href="https://schema.org/LocalBusiness" target="_blank" rel="noopener">Schema.org LocalBusiness</a></li>
            <li><a href="https://validator.schema.org/" target="_blank" rel="noopener">Schema.org Validator</a></li>
        </ul>
    </div>
    <style>
        .bw-schema-validation.valid { background: #d4edda; color: #155724; display: block; }
        .bw-schema-validation.invalid { background: #f8d7da; color: #721c24; display: block; }
    </style>
    <script>
    (function() {
        document.querySelectorAll('.bw-schema-json').forEach(function(textarea) {
            var id = textarea.id;
            var validation = document.getElementById(id + '-validation');
            if (!validation) return;
            function validate() {
                var value = textarea.value.trim();
                validation.className = 'bw-schema-validation';
                validation.textContent = '';
                validation.style.display = 'none';
                if (!value) return;
                var parsed = null;
                var usedPlaceholderNorm = false;
                try {
                    parsed = JSON.parse(value);
                } catch (e1) {
                    var normalized = value.replace(/:\s*%%[^%]+%%/g, ': null');
                    if (normalized !== value) {
                        try {
                            parsed = JSON.parse(normalized);
                            usedPlaceholderNorm = true;
                        } catch (e2) { }
                    }
                }
                if (parsed !== null) {
                    validation.style.display = 'block';
                    validation.className = 'bw-schema-validation valid';
                    if (Array.isArray(parsed)) {
                        validation.textContent = '✓ Valid JSON – ' + parsed.length + ' block(s)' + (usedPlaceholderNorm ? ' (unquoted %%…%% allowed)' : '');
                    } else if (parsed && parsed['@type']) {
                        validation.textContent = '✓ Valid JSON – @type: ' + parsed['@type'] + (usedPlaceholderNorm ? ' (unquoted %%…%% allowed)' : '');
                    } else {
                        validation.textContent = '✓ Valid JSON' + (usedPlaceholderNorm ? ' (unquoted %%…%% allowed)' : '');
                    }
                } else {
                    validation.style.display = 'block';
                    validation.className = 'bw-schema-validation invalid';
                    var msg = '✗ Invalid JSON';
                    try { JSON.parse(value); } catch (e) { msg = '✗ Invalid JSON: ' + e.message; }
                    if (/\}\s*,?\s*\{/.test(value)) {
                        msg += ' — For multiple blocks use [ { ... }, { ... } ]';
                    }
                    validation.textContent = msg;
                }
            }
            textarea.addEventListener('blur', validate);
            var timeout;
            textarea.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(validate, 400);
            });
            if (textarea.value.trim()) validate();
        });
    })();
    </script>
    <?php
}
