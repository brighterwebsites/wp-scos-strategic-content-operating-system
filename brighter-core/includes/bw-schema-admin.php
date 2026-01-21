<?php
/**
 * Schema Admin Interface
 *
 * Adds Schema submenu under Brighter Support with Local Business Schema settings
 * Path: Support > Schema
 */

if (!defined('ABSPATH')) exit;

// Debug: Verify file is loaded
error_log('BW Schema Admin: File loaded at ' . current_time('mysql'));

// Register settings
add_action('admin_init', function() {
    register_setting('bw_schema_settings', 'bw_local_business_schema', [
        'type' => 'string',
        'sanitize_callback' => function($value) {
            // Validate JSON
            if (!empty($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    add_settings_error('bw_local_business_schema', 'invalid_json', 'Invalid JSON format. Please check your schema.');
                    return '';
                }
            }
            return $value;
        },
        'default' => ''
    ]);
});

// Add Schema submenu to Brighter Support
// Priority 20 ensures brighter_support parent menu exists first
add_action('admin_menu', function() {
    error_log('BW Schema Admin: admin_menu hook fired');
    
    add_submenu_page(
        'brighter_support',           // Parent slug
        'Schema',                      // Page title
        'Schema',                      // Menu title
        'manage_options',             // Capability
        'brighter-schema',            // Menu slug
        'bw_schema_render_page',      // Callback
        4                             // Position (after Analytics which is at 2)
    );
    
    error_log('BW Schema Admin: add_submenu_page called');
}, 20);

// Render the Schema page
function bw_schema_render_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // Handle form submission
    if (isset($_POST['bw_schema_settings_nonce']) &&
        wp_verify_nonce($_POST['bw_schema_settings_nonce'], 'bw_schema_settings')) {
        
        if (isset($_POST['bw_local_business_schema'])) {
            update_option('bw_local_business_schema', wp_unslash($_POST['bw_local_business_schema']));
            echo '<div class="notice notice-success is-dismissible"><p>Local Business Schema saved!</p></div>';
        }
    }
    
    $local_business_schema = get_option('bw_local_business_schema', '');
    
    ?>
    <div class="wrap">
        <h1>📋 Schema Settings</h1>
        
        <div style="margin-top: 20px;">
            <h2>Local Business Schema</h2>
            <p>Enter your LocalBusiness JSON-LD schema here. This will be automatically included in your site's schema graph.</p>
            
            <div class="notice notice-info inline">
                <p><strong>ℹ️ How to use:</strong></p>
                <ul style="list-style: disc; margin-left: 20px; line-height: 1.8;">
                    <li>Enter valid JSON-LD schema for LocalBusiness type</li>
                    <li>The schema will be merged into your site's @graph structure</li>
                    <li>Make sure to include <code>"@type": "LocalBusiness"</code> and <code>"@id": "<?php echo esc_js(home_url('/#organization')); ?>"</code></li>
                    <li>Leave empty to disable LocalBusiness schema</li>
                </ul>
            </div>
            
            <form method="post">
                <?php wp_nonce_field('bw_schema_settings', 'bw_schema_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="bw_local_business_schema">Local Business Schema (JSON-LD)</label>
                        </th>
                        <td>
                            <textarea 
                                id="bw_local_business_schema"
                                name="bw_local_business_schema"
                                rows="30"
                                cols="80"
                                class="large-text code"
                                style="font-family: monospace; font-size: 12px;"
                                placeholder='{
  "@type": "LocalBusiness",
  "@id": "<?php echo esc_js(home_url('/#organization')); ?>",
  "name": "Your Business Name",
  "description": "Your business description",
  "url": "<?php echo esc_js(home_url('/')); ?>",
  "telephone": "+61XXXXXXXXX",
  "email": "contact@example.com",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "123 Main St",
    "addressLocality": "City",
    "addressRegion": "State",
    "postalCode": "1234",
    "addressCountry": "AU"
  }
}'><?php echo esc_textarea($local_business_schema); ?></textarea>
                            <p class="description">
                                Enter your LocalBusiness schema in JSON-LD format. The schema will be validated on save.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Schema'); ?>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <h3>📚 Schema Resources</h3>
            <ul style="line-height: 1.8;">
                <li><a href="https://schema.org/LocalBusiness" target="_blank">Schema.org LocalBusiness Documentation</a></li>
                <li><a href="https://developers.google.com/search/docs/appearance/structured-data/local-business" target="_blank">Google Local Business Structured Data</a></li>
                <li><a href="https://validator.schema.org/" target="_blank">Schema.org Validator</a></li>
            </ul>
            
            <?php if (!empty($local_business_schema)): ?>
                <hr style="margin: 30px 0;">
                <h3>✅ Current Schema Preview</h3>
                <details>
                    <summary style="cursor: pointer; font-weight: 600; margin-bottom: 10px;">Click to view current schema</summary>
                    <pre style="background: #f5f5f5; padding: 15px; border-left: 4px solid #2271b1; overflow-x: auto; max-height: 400px;"><code><?php echo esc_html(json_encode(json_decode($local_business_schema), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></code></pre>
                </details>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
