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
            'sanitize_callback' => function($value) use ($option, $label) {
                if (empty($value)) return '';
                $decoded = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    add_settings_error($option, 'invalid_json', sprintf('Invalid JSON in %s. Please check your schema.', $label));
                    return get_option($option, '');
                }
                return $value;
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

    // Handle form submission (all options in one form)
    if (isset($_POST['bw_schema_settings_nonce']) && wp_verify_nonce($_POST['bw_schema_settings_nonce'], 'bw_schema_settings')) {
        $errors = [];
        $saved = [];

        foreach (['bw_local_business_schema', 'bw_success_stories_schema', 'bw_product_schema', 'bw_service_schema'] as $key) {
            if (!isset($_POST[$key])) continue;
            $schema = wp_unslash($_POST[$key]);
            $schema = trim($schema);
            if (!empty($schema)) {
                $decoded = json_decode($schema, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = $key . ': Invalid JSON.';
                    continue;
                }
            }
            update_option($key, $schema);
            $saved[] = $key;
        }
        if (isset($_POST['bw_product_post_ids'])) {
            update_option('bw_product_post_ids', bw_schema_sanitize_post_ids(wp_unslash($_POST['bw_product_post_ids'])));
            $saved[] = 'bw_product_post_ids';
        }
        if (isset($_POST['bw_service_post_ids'])) {
            update_option('bw_service_post_ids', bw_schema_sanitize_post_ids(wp_unslash($_POST['bw_service_post_ids'])));
            $saved[] = 'bw_service_post_ids';
        }

        if (!empty($errors)) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(implode(' ', $errors)) . '</p></div>';
        }
        if (!empty($saved)) {
            echo '<div class="notice notice-success is-dismissible"><p>Schema settings saved.</p></div>';
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
                                <textarea id="bw_local_business_schema" name="bw_local_business_schema" rows="22" class="large-text code" style="font-family: monospace; font-size: 12px; width: 100%; max-width: 800px;"
                                          placeholder='{"@type": "LocalBusiness", "@id": "<?php echo esc_js(home_url('/#organization')); ?>", "name": "Your Business", "url": "<?php echo esc_js(home_url('/')); ?>"}'><?php echo esc_textarea($local_business_schema); ?></textarea>
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
                                <textarea id="bw_success_stories_schema" name="bw_success_stories_schema" rows="22" class="large-text code" style="font-family: monospace; font-size: 12px; width: 100%; max-width: 800px;"
                                          placeholder='{"@type": "CreativeWork", "name": "Example Success Story"}'><?php echo esc_textarea($success_stories_schema); ?></textarea>
                                <p class="description"><?php esc_html_e('Single block or array of blocks. Validated on save.', 'brighterwebsites'); ?></p>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>

                <?php if ($current_tab === 'product') : ?>
                    <h2><?php esc_html_e('Product Schema', 'brighterwebsites'); ?></h2>
                    <p><?php esc_html_e('This template is merged into the schema graph on Post single pages whose ID is in the list below.', 'brighterwebsites'); ?></p>
                    <div class="notice notice-info inline">
                        <p><strong><?php esc_html_e('Where it appears:', 'brighterwebsites'); ?></strong> <?php esc_html_e('Single post pages only, when the post ID is in the "Post IDs" list.', 'brighterwebsites'); ?></p>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="bw_product_post_ids"><?php esc_html_e('Post IDs', 'brighterwebsites'); ?></label></th>
                            <td>
                                <input type="text" id="bw_product_post_ids" name="bw_product_post_ids" value="<?php echo esc_attr($product_post_ids); ?>"
                                       class="regular-text" placeholder="123, 456, 789"/>
                                <p class="description"><?php esc_html_e('Comma-separated Post IDs that should output Product schema.', 'brighterwebsites'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bw_product_schema"><?php esc_html_e('Product Schema (JSON-LD)', 'brighterwebsites'); ?></label></th>
                            <td>
                                <textarea id="bw_product_schema" name="bw_product_schema" rows="18" class="large-text code" style="font-family: monospace; font-size: 12px; width: 100%; max-width: 800px;"
                                          placeholder='{"@type": "Product", "name": "Product Name"}'><?php echo esc_textarea($product_schema); ?></textarea>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>

                <?php if ($current_tab === 'service') : ?>
                    <h2><?php esc_html_e('Service Schema', 'brighterwebsites'); ?></h2>
                    <p><?php esc_html_e('This template is merged into the schema graph on Post single pages whose ID is in the list below.', 'brighterwebsites'); ?></p>
                    <div class="notice notice-info inline">
                        <p><strong><?php esc_html_e('Where it appears:', 'brighterwebsites'); ?></strong> <?php esc_html_e('Single post pages only, when the post ID is in the "Post IDs" list.', 'brighterwebsites'); ?></p>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="bw_service_post_ids"><?php esc_html_e('Post IDs', 'brighterwebsites'); ?></label></th>
                            <td>
                                <input type="text" id="bw_service_post_ids" name="bw_service_post_ids" value="<?php echo esc_attr($service_post_ids); ?>"
                                       class="regular-text" placeholder="123, 456, 789"/>
                                <p class="description"><?php esc_html_e('Comma-separated Post IDs that should output Service schema.', 'brighterwebsites'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bw_service_schema"><?php esc_html_e('Service Schema (JSON-LD)', 'brighterwebsites'); ?></label></th>
                            <td>
                                <textarea id="bw_service_schema" name="bw_service_schema" rows="18" class="large-text code" style="font-family: monospace; font-size: 12px; width: 100%; max-width: 800px;"
                                          placeholder='{"@type": "Service", "name": "Service Name"}'><?php echo esc_textarea($service_schema); ?></textarea>
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
        <h3><?php esc_html_e('Schema resources', 'brighterwebsites'); ?></h3>
        <ul style="line-height: 1.8;">
            <li><a href="https://schema.org/LocalBusiness" target="_blank" rel="noopener">Schema.org LocalBusiness</a></li>
            <li><a href="https://validator.schema.org/" target="_blank" rel="noopener">Schema.org Validator</a></li>
        </ul>
    </div>
    <?php
}
