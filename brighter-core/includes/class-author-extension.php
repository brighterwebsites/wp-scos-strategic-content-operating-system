<?php
/**
 * Module 15: Author Extension
 *
 * File: class-author-extension.php
 * Version: 1.0.0
 *
 * Purpose: Extends WordPress user profiles with structured author metadata fields.
 * Data is stored as WordPress user meta with `scos_author_` prefix.
 * Designed for sites with 1–4 publishing authors.
 *
 * Activation: Enabled/disabled via Site Essentials > Content Strategy > Custom Posts
 * Schema Integration: Module 7 (Schema) consumes this data to generate Person schema
 *
 * Changelog:
 * 1.0.0 - Initial version (core fields: job_title, works_for, works_for_url, linkedin, facebook)
 */

if (!defined('ABSPATH')) exit;

class Brighter_Author_Extension {
    
    /**
     * Meta key prefix for author fields
     */
    const META_PREFIX = 'scos_author_';
    
    /**
     * Option key for enable/disable toggle
     */
    const ENABLED_OPTION = 'bw_author_extension_enabled';
    
    /**
     * Initialize the module
     */
    public static function init() {
        // Check if enabled via Custom Posts settings
        if (!self::is_enabled()) {
            return;
        }
        
        // Add fields to user profile pages
        add_action('show_user_profile', [__CLASS__, 'render_profile_fields']);
        add_action('edit_user_profile', [__CLASS__, 'render_profile_fields']);
        
        // Save fields
        add_action('personal_options_update', [__CLASS__, 'save_profile_fields']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_profile_fields']);
        
        // Register user meta (for REST API / block editor)
        add_action('init', [__CLASS__, 'register_user_meta']);
    }
    
    /**
     * Check if Author Extension is enabled
     */
    public static function is_enabled() {
        return get_option(self::ENABLED_OPTION, false);
    }
    
    /**
     * Enable Author Extension
     */
    public static function enable() {
        update_option(self::ENABLED_OPTION, true);
    }
    
    /**
     * Disable Author Extension
     */
    public static function disable() {
        update_option(self::ENABLED_OPTION, false);
    }
    
    /**
     * Get all author meta fields configuration
     */
    public static function get_fields() {
        return [
            'job_title' => [
                'label' => __('Job Title', 'brighterwebsites'),
                'type' => 'text',
                'description' => __('Your professional title or role.', 'brighterwebsites'),
                'schema' => 'jobTitle'
            ],
            'works_for' => [
                'label' => __('Works For', 'brighterwebsites'),
                'type' => 'text',
                'description' => __('Organization name (e.g., your company).', 'brighterwebsites'),
                'schema' => 'worksFor.name'
            ],
            'works_for_url' => [
                'label' => __('Works For URL', 'brighterwebsites'),
                'type' => 'url',
                'description' => __('<strong>Important:</strong> Repeat your website URL here if same as current site. The standard WordPress "Website" field should be your personal site, portfolio, or link-in-bio URL.', 'brighterwebsites'),
                'schema' => 'worksFor.url'
            ],
            'linkedin' => [
                'label' => __('Personal LinkedIn', 'brighterwebsites'),
                'type' => 'url',
                'description' => __('Your LinkedIn profile URL.', 'brighterwebsites'),
                'schema' => 'sameAs[]'
            ],
            'facebook' => [
                'label' => __('Personal Facebook', 'brighterwebsites'),
                'type' => 'url',
                'description' => __('Your Facebook profile URL.', 'brighterwebsites'),
                'schema' => 'sameAs[]'
            ],
        ];
    }
    
    /**
     * Register user meta for REST API / block editor
     */
    public static function register_user_meta() {
        $fields = self::get_fields();
        
        foreach ($fields as $key => $config) {
            register_meta('user', self::META_PREFIX . $key, [
                'type' => 'string',
                'description' => $config['description'],
                'single' => true,
                'show_in_rest' => false, // Keep private for now
                'sanitize_callback' => ($config['type'] === 'url') ? 'esc_url_raw' : 'sanitize_text_field',
            ]);
        }
    }
    
    /**
     * Render author fields on user profile page
     */
    public static function render_profile_fields($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        
        $fields = self::get_fields();
        ?>
        
        <h2><?php esc_html_e('Author Extension (E-E-A-T)', 'brighterwebsites'); ?></h2>
        <p class="description">
            <?php esc_html_e('These fields are used to generate structured Person schema markup and enhance author credibility signals.', 'brighterwebsites'); ?>
            <br><em><?php esc_html_e('Managed by Module 15: Author Extension', 'brighterwebsites'); ?></em>
        </p>
        
        <table class="form-table" role="presentation">
            <?php foreach ($fields as $key => $config): 
                $meta_key = self::META_PREFIX . $key;
                $value = get_user_meta($user->ID, $meta_key, true);
                ?>
                <tr>
                    <th><label for="<?php echo esc_attr($meta_key); ?>"><?php echo esc_html($config['label']); ?></label></th>
                    <td>
                        <input 
                            type="<?php echo esc_attr($config['type']); ?>" 
                            name="<?php echo esc_attr($meta_key); ?>" 
                            id="<?php echo esc_attr($meta_key); ?>" 
                            value="<?php echo esc_attr($value); ?>" 
                            class="regular-text"
                        />
                        <?php if (!empty($config['description'])): ?>
                            <p class="description"><?php echo wp_kses_post($config['description']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($config['schema'])): ?>
                            <p class="description" style="color:#646970;font-size:11px;">
                                <em>Schema: <code><?php echo esc_html($config['schema']); ?></code></em>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        
        <style>
            .form-table th[scope="row"] { width: 200px; }
        </style>
        <?php
    }
    
    /**
     * Save author fields
     */
    public static function save_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        $fields = self::get_fields();
        
        foreach ($fields as $key => $config) {
            $meta_key = self::META_PREFIX . $key;
            
            if (isset($_POST[$meta_key])) {
                $value = $_POST[$meta_key];
                
                // Sanitize based on field type
                if ($config['type'] === 'url') {
                    $value = esc_url_raw($value);
                } else {
                    $value = sanitize_text_field($value);
                }
                
                update_user_meta($user_id, $meta_key, $value);
            }
        }
    }
    
    /**
     * Get author data for a user (for schema generation)
     *
     * @param int $user_id User ID
     * @return array Author data
     */
    public static function get_author_data($user_id) {
        if (!self::is_enabled()) {
            return [];
        }
        
        $fields = self::get_fields();
        $data = [];
        
        foreach ($fields as $key => $config) {
            $meta_key = self::META_PREFIX . $key;
            $value = get_user_meta($user_id, $meta_key, true);
            
            if (!empty($value)) {
                $data[$key] = $value;
            }
        }
        
        // Also get standard WP fields
        $user = get_userdata($user_id);
        if ($user) {
            $data['first_name'] = get_user_meta($user_id, 'first_name', true);
            $data['last_name'] = get_user_meta($user_id, 'last_name', true);
            $data['bio'] = get_user_meta($user_id, 'description', true);
            $data['website'] = $user->user_url;
            $data['display_name'] = $user->display_name;
        }
        
        return $data;
    }
}

// Initialize if enabled
add_action('plugins_loaded', ['Brighter_Author_Extension', 'init'], 20);
