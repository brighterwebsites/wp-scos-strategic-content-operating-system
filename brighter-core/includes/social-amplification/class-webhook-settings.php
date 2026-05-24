<?php
/**
 * Social Amplification Webhook Settings
 *
 * Admin interface for webhook configuration
 *
 * @package BrighterCore
 * @subpackage SocialAmplification
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class BW_Social_Webhook_Settings {

    /**
     * Initialize hooks
     */
    public function init() {
        // Add submenu under Support
        add_action('admin_menu', array($this, 'add_settings_page'), 25);

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Handle form submission
        add_action('admin_post_bw_save_webhook_settings', array($this, 'handle_save'));

        // Bulk sync AJAX
        add_action('wp_ajax_bw_airtable_seed_bulk', array($this, 'ajax_seed_airtable_bulk'));
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        // Suppressed when site-essentials Social Amplification module is active.
        if ( defined( 'SCOS_SA_ACTIVE' ) ) { return; }
        add_submenu_page(
            'brighter_support',
            __('Social Amplification', 'brighterwebsites'),
            __('Social Amplification', 'brighterwebsites'),
            'manage_options',
            'bw-social-amplification',
            array($this, 'render_settings_page')
        );
        add_submenu_page(
            'brighter_support',
            __('Integration CAR', 'brighterwebsites'),
            __('Integration CAR', 'brighterwebsites'),
            'manage_options',
            'bw-integration-car',
            array($this, 'render_car_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('bw_social_amplification', 'bw_social_webhook_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));

        register_setting('bw_social_amplification', 'bw_social_webhook_enabled', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0
        ));

        // YOURLS settings
        register_setting('bw_social_amplification', 'bw_yourls_api_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));

        register_setting('bw_social_amplification', 'bw_yourls_signature', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        register_setting('bw_social_amplification', 'bw_yourls_username', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        register_setting('bw_social_amplification', 'bw_yourls_password', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        // Airtable settings
        register_setting('bw_social_amplification', 'bw_airtable_api_token', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        register_setting('bw_social_amplification', 'bw_airtable_base_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        register_setting('bw_social_amplification', 'bw_airtable_table_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        register_setting('bw_social_amplification', 'bw_airtable_altc_table_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        register_setting('bw_social_amplification', 'bw_airtable_topics_table_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $webhook_url = get_option('bw_social_webhook_url', '');
        $enabled = get_option('bw_social_webhook_enabled', 0);

        ?>
        <div class="wrap">
            <h1><?php _e('Social Amplification Settings', 'brighterwebsites'); ?></h1>

            <?php if (isset($_GET['updated']) && $_GET['updated'] === 'true'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully!', 'brighterwebsites'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('bw_webhook_settings', 'bw_webhook_nonce'); ?>
                <input type="hidden" name="action" value="bw_save_webhook_settings" />

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="bw_social_webhook_enabled"><?php _e('Enable Webhook', 'brighterwebsites'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="bw_social_webhook_enabled"
                                       name="bw_social_webhook_enabled"
                                       value="1"
                                       <?php checked($enabled, 1); ?> />
                                <?php _e('Trigger Make.com when posts are published/updated', 'brighterwebsites'); ?>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, WordPress will automatically notify Make.com when content is published or updated.', 'brighterwebsites'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bw_social_webhook_url"><?php _e('Make.com Webhook URL', 'brighterwebsites'); ?></label>
                        </th>
                        <td>
                            <input type="url"
                                   id="bw_social_webhook_url"
                                   name="bw_social_webhook_url"
                                   value="<?php echo esc_attr($webhook_url); ?>"
                                   class="regular-text code"
                                   style="width: 100%; max-width: 600px;" />
                            <p class="description">
                                <?php _e('Your Make.com custom webhook URL (starts with https://hook.us2.make.com/...)', 'brighterwebsites'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- YOURLS Configuration -->
                    <tr>
                        <th colspan="2" style="background: #f5f5f5; padding: 10px;">
                            <h3 style="margin: 0;"><?php _e('YOURLS Shortlink Configuration', 'brighterwebsites'); ?></h3>
                            <p style="margin: 5px 0 0 0; font-weight: normal;">
                                <?php _e('Configure YOURLS integration for social media shortlinks', 'brighterwebsites'); ?>
                            </p>
                        </th>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bw_yourls_api_url"><?php _e('YOURLS API URL', 'brighterwebsites'); ?></label>
                        </th>
                        <td>
                            <input type="url"
                                   id="bw_yourls_api_url"
                                   name="bw_yourls_api_url"
                                   value="<?php echo esc_attr(get_option('bw_yourls_api_url', '')); ?>"
                                   class="regular-text code"
                                   style="width: 100%; max-width: 600px;"
                                   placeholder="https://bweb1.com.au/yourls-api.php" />
                            <p class="description">
                                <?php _e('Your YOURLS installation API URL (e.g., https://bweb1.com.au/yourls-api.php)', 'brighterwebsites'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bw_yourls_signature"><?php _e('YOURLS Signature', 'brighterwebsites'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="bw_yourls_signature"
                                   name="bw_yourls_signature"
                                   value="<?php echo esc_attr(get_option('bw_yourls_signature', '')); ?>"
                                   class="regular-text code"
                                   style="width: 100%; max-width: 600px;" />
                            <p class="description">
                                <?php _e('Recommended: Get this from YOURLS Admin → Tools → Signature Token', 'brighterwebsites'); ?>
                                <br>
                                <strong><?php _e('OR', 'brighterwebsites'); ?></strong> <?php _e('use username and password below (less secure)', 'brighterwebsites'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bw_yourls_username"><?php _e('YOURLS Username', 'brighterwebsites'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="bw_yourls_username"
                                   name="bw_yourls_username"
                                   value="<?php echo esc_attr(get_option('bw_yourls_username', '')); ?>"
                                   class="regular-text"
                                   autocomplete="off" />
                            <p class="description">
                                <?php _e('Only needed if not using signature token above', 'brighterwebsites'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bw_yourls_password"><?php _e('YOURLS Password', 'brighterwebsites'); ?></label>
                        </th>
                        <td>
                            <input type="password"
                                   id="bw_yourls_password"
                                   name="bw_yourls_password"
                                   value="<?php echo esc_attr(get_option('bw_yourls_password', '')); ?>"
                                   class="regular-text"
                                   autocomplete="off" />
                            <p class="description">
                                <?php _e('Only needed if not using signature token above', 'brighterwebsites'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Webhook Payload', 'brighterwebsites'); ?></th>
                        <td>
                            <p><?php _e('When triggered, WordPress sends this data to Make.com:', 'brighterwebsites'); ?></p>
                            <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto; max-width: 600px;">
{
  "post_id": 123,
  "post_url": "https://example.com/blog/post-title/",
  "post_title": "Post Title",
  "post_type": "post",
  "post_excerpt": "Brief excerpt...",
  "post_date": "2025-12-02T10:30:00+00:00",
  "post_modified": "2025-12-02T11:00:00+00:00",
  "breadcrumb": "seo-signals",
  "content_type": "Article",
  "featured_image_url": "https://.../image.jpg",
  "featured_image_caption": "Image caption",
  "site_url": "https://example.com",
  "trigger_time": "2025-12-02 11:00:00",
  "trigger_type": "manual"
}</pre>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('API Documentation', 'brighterwebsites'); ?></th>
                        <td>
                            <p><strong><?php _e('Generate Prompt Endpoint:', 'brighterwebsites'); ?></strong></p>
                            <code style="background: #f5f5f5; padding: 5px; display: block; margin: 10px 0;">
                                <?php echo esc_html(get_site_url()); ?>/wp-json/brighter-core/v1/social-amplification/generate-prompt
                            </code>
                            <p class="description">
                                <?php _e('Use this endpoint in Make.com to generate AI prompts. Requires X-Brighter-Token header.', 'brighterwebsites'); ?>
                            </p>
                            <p><?php _e('Airtable CAR Sync is configured on', 'brighterwebsites'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=bw-integration-car')); ?>"><?php _e('Integration CAR', 'brighterwebsites'); ?></a>.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'brighterwebsites')); ?>
            </form>

            <hr style="margin: 40px 0;" />

            <h2><?php _e('Testing', 'brighterwebsites'); ?></h2>
            <p><?php _e('To test the webhook:', 'brighterwebsites'); ?></p>
            <ol>
                <li><?php _e('Enable the webhook above and save', 'brighterwebsites'); ?></li>
                <li><?php _e('Publish or update any blog post', 'brighterwebsites'); ?></li>
                <li><?php _e('Check Make.com to see if the webhook was received', 'brighterwebsites'); ?></li>
                <li><?php _e('Check WordPress error logs for webhook activity', 'brighterwebsites'); ?></li>
            </ol>

        </div>
        <?php
    }

    /**
     * Render Integration CAR page (Airtable CAR Sync Configuration only)
     */
    public function render_car_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Airtable CAR Sync Configuration', 'brighterwebsites'); ?></h1>

            <?php if (isset($_GET['updated']) && $_GET['updated'] === 'true'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully!', 'brighterwebsites'); ?></p>
                </div>
            <?php endif; ?>

            <p><?php _e('Configure Airtable integration for Content Architecture Record (CAR) synchronization.', 'brighterwebsites'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('bw_webhook_settings', 'bw_webhook_nonce'); ?>
                <input type="hidden" name="action" value="bw_save_webhook_settings" />
                <input type="hidden" name="return_page" value="bw-integration-car" />

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="bw_airtable_api_token"><?php _e('Airtable API Token', 'brighterwebsites'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="bw_airtable_api_token"
                                   name="bw_airtable_api_token"
                                   value="<?php echo esc_attr(get_option('bw_airtable_api_token', '')); ?>"
                                   class="regular-text code"
                                   style="width: 100%; max-width: 600px;"
                                   placeholder="Bearer pat..." />
                            <p class="description">
                                <?php _e('Your Airtable Personal Access Token (starts with "Bearer pat...").', 'brighterwebsites'); ?>
                                <br>
                                <?php _e('Get this from:', 'brighterwebsites'); ?>
                                <a href="https://airtable.com/create/tokens" target="_blank"><?php _e('Airtable Account → Developer → Personal Access Tokens', 'brighterwebsites'); ?></a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="bw_airtable_base_id"><?php _e('Airtable Base ID', 'brighterwebsites'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="bw_airtable_base_id"
                                   name="bw_airtable_base_id"
                                   value="<?php echo esc_attr(get_option('bw_airtable_base_id', '')); ?>"
                                   class="regular-text code"
                                   style="width: 100%; max-width: 600px;"
                                   placeholder="appOqcQR79umbJJGP" />
                            <p class="description">
                                <?php _e('Your Airtable Base ID (found in the API docs URL).', 'brighterwebsites'); ?>
                                <br>
                                <?php _e('Get this from:', 'brighterwebsites'); ?>
                                <a href="https://airtable.com/api" target="_blank"><?php _e('Airtable → Help → API documentation', 'brighterwebsites'); ?></a>
                                <?php _e(' - The Base ID is in the URL:', 'brighterwebsites'); ?>
                                <code>https://airtable.com/appOqcQR79umbJJGP/api/docs</code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="bw_airtable_table_id"><?php _e('Airtable Content Table ID', 'brighterwebsites'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="bw_airtable_table_id"
                                   name="bw_airtable_table_id"
                                   value="<?php echo esc_attr(get_option('bw_airtable_table_id', '')); ?>"
                                   class="regular-text code"
                                   style="width: 100%; max-width: 600px;"
                                   placeholder="tblXXXXXXXXXXXXXX" />
                            <p class="description">
                                <?php _e('Content table (stronger than name - use Table ID from API docs).', 'brighterwebsites'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="bw_airtable_altc_table_id"><?php _e('Airtable ALTC Table ID', 'brighterwebsites'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="bw_airtable_altc_table_id"
                                   name="bw_airtable_altc_table_id"
                                   value="<?php echo esc_attr(get_option('bw_airtable_altc_table_id', '')); ?>"
                                   class="regular-text code"
                                   style="width: 100%; max-width: 600px;"
                                   placeholder="tblXXXXXXXXXXXXXX" />
                            <p class="description">
                                <?php _e('ALTC Strategic Lenses table. Terms sync on save; stores record ID in term meta for linked records in Content.', 'brighterwebsites'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="bw_airtable_topics_table_id"><?php _e('Airtable Topics Table ID', 'brighterwebsites'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="bw_airtable_topics_table_id"
                                   name="bw_airtable_topics_table_id"
                                   value="<?php echo esc_attr(get_option('bw_airtable_topics_table_id', '')); ?>"
                                   class="regular-text code"
                                   style="width: 100%; max-width: 600px;"
                                   placeholder="tblXXXXXXXXXXXXXX" />
                            <p class="description">
                                <?php _e('Topics table. Terms sync on save; stores record ID in term meta for linked records in Content.', 'brighterwebsites'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Airtable Table Structure', 'brighterwebsites'); ?></th>
                        <td>
                            <p><?php _e('Use this Google Sheets template to set up your Airtable table with the correct structure:', 'brighterwebsites'); ?></p>
                            <p>
                                <a href="https://docs.google.com/spreadsheets/d/1hNYVYF31T7S2CA0J8Jv01eMH5s3tnACaZg6Il9IBn9s/edit?usp=sharing"
                                   target="_blank"
                                   class="button button-secondary">
                                    <?php _e('View Airtable Structure Template', 'brighterwebsites'); ?>
                                </a>
                            </p>
                            <p class="description">
                                <?php _e('This template can be easily imported into Airtable to set up the table correctly with all required fields.', 'brighterwebsites'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Seed Airtable (Bulk Sync)', 'brighterwebsites'); ?></th>
                        <td>
                            <p><?php _e('Sync all ALTC Clusters, Topics, and Content to Airtable. Recommended order: 1) ALTC Clusters, 2) Topics, 3) Content. Content runs in 2 phases: static data first, then Internal Links.', 'brighterwebsites'); ?></p>
                            <p>
                                <button type="button" id="bw-airtable-seed-bulk" class="button button-secondary">
                                    <?php _e('Seed Airtable — Sync All', 'brighterwebsites'); ?>
                                </button>
                            </p>
                            <p id="bw-airtable-seed-status" style="margin-top: 8px; font-family: monospace; font-size: 12px;"></p>
                            <?php wp_nonce_field('bw_airtable_seed_bulk', 'bw_airtable_seed_nonce', false); ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'brighterwebsites')); ?>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#bw-airtable-seed-bulk').on('click', function() {
                var $btn = $(this), $status = $('#bw-airtable-seed-status');
                $btn.prop('disabled', true);
                $status.text('Syncing... ALTC Clusters, Topics, Content (phase 1), Content (phase 2)');
                $.post(ajaxurl, {
                    action: 'bw_airtable_seed_bulk',
                    nonce: $('#bw_airtable_seed_nonce').val()
                }).done(function(r) {
                    if (r.success) {
                        var d = r.data, lines = [];
                        lines.push('ALTC: ' + (d.altc ? d.altc.synced : 0) + ' synced, ' + (d.altc ? d.altc.errors : 0) + ' errors');
                        lines.push('Topics: ' + (d.topics ? d.topics.synced : 0) + ' synced, ' + (d.topics ? d.topics.errors : 0) + ' errors');
                        lines.push('Content P1: ' + (d.content_phase1 ? d.content_phase1.synced : 0) + ' synced, ' + (d.content_phase1 ? d.content_phase1.errors : 0) + ' errors');
                        lines.push('Content P2 (links): ' + (d.content_phase2 ? d.content_phase2.synced : 0) + ' synced, ' + (d.content_phase2 ? d.content_phase2.errors : 0) + ' errors');
                        $status.text(lines.join(' | '));
                    } else {
                        $status.text('Error: ' + (r.data && r.data.message ? r.data.message : 'Unknown'));
                    }
                }).fail(function() {
                    $status.text('Request failed. Check console/network.');
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Handle form submission
     */
    public function handle_save() {
        // Check nonce
        if (!isset($_POST['bw_webhook_nonce']) || !wp_verify_nonce($_POST['bw_webhook_nonce'], 'bw_webhook_settings')) {
            wp_die(__('Security check failed', 'brighterwebsites'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions', 'brighterwebsites'));
        }

        $return_page = isset($_POST['return_page']) ? sanitize_text_field($_POST['return_page']) : 'bw-social-amplification';

        // When saving from Integration CAR page, only update Airtable options (do not overwrite webhook/YOURLS)
        if ($return_page === 'bw-integration-car') {
            $airtable_api_token = isset($_POST['bw_airtable_api_token']) ? sanitize_text_field($_POST['bw_airtable_api_token']) : '';
            $airtable_base_id = isset($_POST['bw_airtable_base_id']) ? sanitize_text_field($_POST['bw_airtable_base_id']) : '';
            $airtable_table_id = isset($_POST['bw_airtable_table_id']) ? sanitize_text_field($_POST['bw_airtable_table_id']) : '';
            $airtable_altc_table_id = isset($_POST['bw_airtable_altc_table_id']) ? sanitize_text_field($_POST['bw_airtable_altc_table_id']) : '';
            $airtable_topics_table_id = isset($_POST['bw_airtable_topics_table_id']) ? sanitize_text_field($_POST['bw_airtable_topics_table_id']) : '';

            update_option('bw_airtable_api_token', $airtable_api_token);
            update_option('bw_airtable_base_id', $airtable_base_id);
            update_option('bw_airtable_table_id', $airtable_table_id);
            update_option('bw_airtable_altc_table_id', $airtable_altc_table_id);
            update_option('bw_airtable_topics_table_id', $airtable_topics_table_id);

            wp_redirect(add_query_arg(array(
                'page' => 'bw-integration-car',
                'updated' => 'true'
            ), admin_url('admin.php')));
            exit;
        }

        // Save webhook settings (Social Amplification page)
        $webhook_url = isset($_POST['bw_social_webhook_url']) ? esc_url_raw($_POST['bw_social_webhook_url']) : '';
        $enabled = isset($_POST['bw_social_webhook_enabled']) ? 1 : 0;

        update_option('bw_social_webhook_url', $webhook_url);
        update_option('bw_social_webhook_enabled', $enabled);

        // Save YOURLS settings
        $yourls_api_url = isset($_POST['bw_yourls_api_url']) ? esc_url_raw($_POST['bw_yourls_api_url']) : '';
        $yourls_signature = isset($_POST['bw_yourls_signature']) ? sanitize_text_field($_POST['bw_yourls_signature']) : '';
        $yourls_username = isset($_POST['bw_yourls_username']) ? sanitize_text_field($_POST['bw_yourls_username']) : '';
        $yourls_password = isset($_POST['bw_yourls_password']) ? sanitize_text_field($_POST['bw_yourls_password']) : '';

        update_option('bw_yourls_api_url', $yourls_api_url);
        update_option('bw_yourls_signature', $yourls_signature);
        update_option('bw_yourls_username', $yourls_username);
        update_option('bw_yourls_password', $yourls_password);

        // Save Airtable settings (when on Social Amplification page)
        $airtable_api_token = isset($_POST['bw_airtable_api_token']) ? sanitize_text_field($_POST['bw_airtable_api_token']) : '';
        $airtable_base_id = isset($_POST['bw_airtable_base_id']) ? sanitize_text_field($_POST['bw_airtable_base_id']) : '';
        $airtable_table_id = isset($_POST['bw_airtable_table_id']) ? sanitize_text_field($_POST['bw_airtable_table_id']) : '';
        $airtable_altc_table_id = isset($_POST['bw_airtable_altc_table_id']) ? sanitize_text_field($_POST['bw_airtable_altc_table_id']) : '';
        $airtable_topics_table_id = isset($_POST['bw_airtable_topics_table_id']) ? sanitize_text_field($_POST['bw_airtable_topics_table_id']) : '';

        update_option('bw_airtable_api_token', $airtable_api_token);
        update_option('bw_airtable_base_id', $airtable_base_id);
        update_option('bw_airtable_table_id', $airtable_table_id);
        update_option('bw_airtable_altc_table_id', $airtable_altc_table_id);
        update_option('bw_airtable_topics_table_id', $airtable_topics_table_id);

        wp_redirect(add_query_arg(array(
            'page' => 'bw-social-amplification',
            'updated' => 'true'
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * AJAX: Seed Airtable bulk sync (ALTC → Topics → Content phase 1 → Content phase 2)
     */
    public function ajax_seed_airtable_bulk() {
        check_ajax_referer('bw_airtable_seed_bulk', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        if (!class_exists('BW_Airtable_Helper')) {
            wp_send_json_error(array('message' => 'Airtable helper not loaded'));
        }
        set_time_limit(300);
        $results = array();
        $results['altc'] = BW_Airtable_Helper::bulk_sync_altc_terms();
        $results['topics'] = BW_Airtable_Helper::bulk_sync_topics();
        $results['content_phase1'] = BW_Airtable_Helper::bulk_sync_content(1);
        $results['content_phase2'] = BW_Airtable_Helper::bulk_sync_content(2);
        wp_send_json_success($results);
    }
}
