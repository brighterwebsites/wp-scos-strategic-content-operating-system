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

        // Handle migration
        add_action('admin_post_bw_migrate_breadcrumbs', array($this, 'handle_migration'));
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_submenu_page(
            'brighter_support',
            __('Social Amplification', 'brighterwebsites'),
            __('Social Amplification', 'brighterwebsites'),
            'manage_options',
            'bw-social-amplification',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('bw_social_amplification', 'bw_social_webhook_url', array(
            'sanitize_callback' => 'esc_url_raw'
        ));

        register_setting('bw_social_amplification', 'bw_social_webhook_enabled', array(
            'sanitize_callback' => 'absint'
        ));

        // YOURLS settings
        register_setting('bw_social_amplification', 'bw_yourls_api_url', array(
            'sanitize_callback' => 'esc_url_raw'
        ));

        register_setting('bw_social_amplification', 'bw_yourls_signature', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting('bw_social_amplification', 'bw_yourls_username', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting('bw_social_amplification', 'bw_yourls_password', array(
            'sanitize_callback' => 'sanitize_text_field'
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

            <hr style="margin: 40px 0;" />

            <h2><?php _e('Migration Tools', 'brighterwebsites'); ?></h2>

            <?php if (isset($_GET['migrated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php printf(
                            __('Successfully migrated %d breadcrumbs from SEOPress!', 'brighterwebsites'),
                            intval($_GET['migrated'])
                        ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: 600px;">
                <h3><?php _e('Migrate from SEOPress Breadcrumbs', 'brighterwebsites'); ?></h3>
                <p><?php _e('If you have been using SEOPress breadcrumbs, you can migrate them to the new Breadcrumb field.', 'brighterwebsites'); ?></p>
                <p><?php _e('This will copy values from:', 'brighterwebsites'); ?>
                    <code>_seopress_robots_breadcrumbs</code> →
                    <code>_bw_breadcrumb</code>
                </p>
                <p class="description">
                    <?php _e('Only posts without existing breadcrumbs will be migrated. Existing values will not be overwritten.', 'brighterwebsites'); ?>
                </p>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" onsubmit="return confirm('<?php esc_attr_e('Migrate breadcrumbs from SEOPress? This will not overwrite existing values.', 'brighterwebsites'); ?>');">
                    <?php wp_nonce_field('bw_migrate_breadcrumbs', 'bw_migrate_nonce'); ?>
                    <input type="hidden" name="action" value="bw_migrate_breadcrumbs" />
                    <?php submit_button(__('Migrate SEOPress Breadcrumbs', 'brighterwebsites'), 'secondary', 'submit', false); ?>
                </form>
            </div>
        </div>
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

        // Save settings
        $webhook_url = isset($_POST['bw_social_webhook_url']) ? esc_url_raw($_POST['bw_social_webhook_url']) : '';
        $enabled = isset($_POST['bw_social_webhook_enabled']) ? 1 : 0;

        update_option('bw_social_webhook_url', $webhook_url);
        update_option('bw_social_webhook_enabled', $enabled);

        // Redirect back with success message
        wp_redirect(add_query_arg(array(
            'page' => 'bw-social-amplification',
            'updated' => 'true'
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Handle migration from SEOPress
     */
    public function handle_migration() {
        // Check nonce
        if (!isset($_POST['bw_migrate_nonce']) || !wp_verify_nonce($_POST['bw_migrate_nonce'], 'bw_migrate_breadcrumbs')) {
            wp_die(__('Security check failed', 'brighterwebsites'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions', 'brighterwebsites'));
        }

        // Run migration
        $migrated = BW_Breadcrumbs_Meta::migrate_from_seopress();

        // Redirect back with success message
        wp_redirect(add_query_arg(array(
            'page' => 'bw-social-amplification',
            'migrated' => $migrated
        ), admin_url('admin.php')));
        exit;
    }
}
