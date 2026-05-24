<?php
/**
 * Schema Meta Box
 *
 * Adds custom schema meta fields to posts/pages for:
 * - Custom JSON-LD schema injection (FAQPage, HowTo, etc.)
 * - Breadcrumb override for schema
 *
 * @package    SiteEssentials
 * @subpackage Modules\Seo
 * @version    1.0.0
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\Seo;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Schema Meta Box Class
 *
 * Registers meta box and fields for custom schema on single posts/pages.
 *
 * @since 1.0.0
 */
class Schema_Meta_Box {

    /**
     * Post types to add meta box to
     * Populated dynamically in register_meta_box() when CPTs are available
     *
     * @since 1.0.0
     * @var array
     */
    private $post_types = [];

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
        add_action('save_post', [$this, 'save_meta'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Initialize the class
     *
     * @since  1.0.0
     * @return void
     */
    public static function init() {
        new self();
    }

    /**
     * Register meta box
     *
     * @since 1.0.0
     * @return void
     */
	public function register_meta_box() {
		// Suppressed when new SeoSchema module is active
		if ( defined( 'SCOS_SCHEMA_ACTIVE' ) ) { return; }

		// Get all public post types NOW (when CPTs are registered)
		$this->post_types = get_post_types(['public' => true], 'names');
        
        // Remove attachment (media) - doesn't need schema
        unset($this->post_types['attachment']);
        
        foreach ($this->post_types as $post_type) {
            add_meta_box(
                'bw_schema_settings',
                __('Schema Settings', 'site-essentials'),
                [$this, 'render_meta_box'],
                $post_type,
                'normal',
                'default'
            );
        }
    }

    /**
     * Render meta box content
     *
     * @since 1.0.0
     * @param WP_Post $post Current post object
     * @return void
     */
    public function render_meta_box($post) {
        // Security nonce
        wp_nonce_field('bw_schema_meta_nonce', 'bw_schema_meta_nonce');

        // Get existing values
        $custom_schema = get_post_meta($post->ID, 'bw_custom_schema', true);
        $breadcrumb_override = get_post_meta($post->ID, 'bw_breadcrumb_schema', true);

        // Include view file if exists, otherwise inline
        $view_file = __DIR__ . '/views/schema-meta-box.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            $this->render_inline_form($post, $custom_schema, $breadcrumb_override);
        }
    }

    /**
     * Render inline form (fallback if view file doesn't exist)
     *
     * @since 1.0.0
     * @param WP_Post $post Current post
     * @param string  $custom_schema Custom schema JSON
     * @param string  $breadcrumb_override Breadcrumb override text
     * @return void
     */
    private function render_inline_form($post, $custom_schema, $breadcrumb_override) {
        ?>
        <style>
            .bw-schema-field { margin-bottom: 20px; }
            .bw-schema-field label { display: block; font-weight: 600; margin-bottom: 5px; }
            .bw-schema-field textarea { width: 100%; font-family: monospace; }
            .bw-schema-field input[type="text"] { width: 100%; }
            .bw-schema-field .description { color: #666; font-size: 12px; margin-top: 4px; }
            .bw-schema-validation { padding: 8px 12px; margin-top: 8px; border-radius: 4px; display: none; }
            .bw-schema-validation.valid { background: #d4edda; color: #155724; display: block; }
            .bw-schema-validation.invalid { background: #f8d7da; color: #721c24; display: block; }
        </style>

        <div class="bw-schema-fields">
            
            <!-- Breadcrumb Override -->
            <div class="bw-schema-field">
                <label for="bw_breadcrumb_schema">
                    <?php esc_html_e('Breadcrumb Label Override', 'site-essentials'); ?>
                </label>
                <input 
                    type="text" 
                    id="bw_breadcrumb_schema" 
                    name="bw_breadcrumb_schema" 
                    value="<?php echo esc_attr($breadcrumb_override); ?>"
                    placeholder="<?php echo esc_attr(get_the_title($post->ID)); ?>"
                />
                <p class="description">
                    <?php esc_html_e('Override the breadcrumb name in schema. Leave empty to use page title.', 'site-essentials'); ?>
                </p>
            </div>

            <!-- Custom Schema JSON -->
            <div class="bw-schema-field">
                <label for="bw_custom_schema">
                    <?php esc_html_e('Custom Schema (JSON-LD)', 'site-essentials'); ?>
                </label>
                <textarea 
                    id="bw_custom_schema" 
                    name="bw_custom_schema" 
                    rows="12"
                    placeholder='{"@type": "FAQPage", "mainEntity": [...]}'
                ><?php echo esc_textarea($custom_schema); ?></textarea>
                <p class="description">
                    <?php esc_html_e('Enter raw JSON-LD without <script> tags. This will be merged into the @graph. Supports single block or array of blocks. Mulitple blocks can be added like [{"@type": "FAQPage"}, {"@type": "HowTo"}] ', 'site-essentials'); ?>
                </p>
                <div id="bw-schema-validation" class="bw-schema-validation"></div>
            </div>

        </div>

        <script>
        (function() {
            const textarea = document.getElementById('bw_custom_schema');
            const validation = document.getElementById('bw-schema-validation');
            
            if (!textarea || !validation) return;
            
            function validateJSON() {
                const value = textarea.value.trim();
                
                if (!value) {
                    validation.className = 'bw-schema-validation';
                    validation.textContent = '';
                    return;
                }
                
                try {
                    const parsed = JSON.parse(value);
                    validation.className = 'bw-schema-validation valid';
                    
                    // Show type info
                    if (Array.isArray(parsed)) {
                        validation.textContent = '✓ Valid JSON - Array with ' + parsed.length + ' block(s)';
                    } else if (parsed['@type']) {
                        validation.textContent = '✓ Valid JSON - @type: ' + parsed['@type'];
                    } else {
                        validation.textContent = '✓ Valid JSON';
                    }
                } catch (e) {
                    validation.className = 'bw-schema-validation invalid';
                    validation.textContent = '✗ Invalid JSON: ' + e.message;
                }
            }
            
            textarea.addEventListener('blur', validateJSON);
            textarea.addEventListener('input', function() {
                // Debounce validation on input
                clearTimeout(this._timeout);
                this._timeout = setTimeout(validateJSON, 500);
            });
            
            // Validate on load if has content
            if (textarea.value.trim()) {
                validateJSON();
            }
        })();
        </script>
        <?php
    }

    /**
     * Save meta field values
     *
     * @since 1.0.0
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     * @return void
     */
    public function save_meta($post_id, $post) {
        // Security checks
        if (!isset($_POST['bw_schema_meta_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['bw_schema_meta_nonce'], 'bw_schema_meta_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save breadcrumb override
        if (isset($_POST['bw_breadcrumb_schema'])) {
            $breadcrumb = sanitize_text_field($_POST['bw_breadcrumb_schema']);
            if (!empty($breadcrumb)) {
                update_post_meta($post_id, 'bw_breadcrumb_schema', $breadcrumb);
            } else {
                delete_post_meta($post_id, 'bw_breadcrumb_schema');
            }
        }

        // Save custom schema (validate JSON first)
        if (isset($_POST['bw_custom_schema'])) {
            $custom_schema = wp_unslash($_POST['bw_custom_schema']);
            $custom_schema = trim($custom_schema);

            if (!empty($custom_schema)) {
                // Validate JSON
                $decoded = json_decode($custom_schema);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Store the original (valid) JSON
                    update_post_meta($post_id, 'bw_custom_schema', $custom_schema);
                }
                // If invalid JSON, don't save (keeps old value)
            } else {
                delete_post_meta($post_id, 'bw_custom_schema');
            }
        }
    }

    /**
     * Enqueue admin assets
     *
     * @since 1.0.0
     * @param string $hook Current admin page
     * @return void
     */
    public function enqueue_assets($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, $this->post_types, true)) {
            return;
        }

        // Future: enqueue dedicated CSS/JS files if needed
        // wp_enqueue_style('bw-schema-meta-box', ...);
        // wp_enqueue_script('bw-schema-meta-box', ...);
    }

    /**
     * Get custom schema for a post
     *
     * @since  1.0.0
     * @param  int $post_id Post ID
     * @return array|null Parsed schema or null
     */
    public static function get_custom_schema($post_id) {
        $custom_schema = get_post_meta($post_id, 'bw_custom_schema', true);

        if (empty($custom_schema)) {
            return null;
        }

        $decoded = json_decode($custom_schema, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return null;
    }

    /**
     * Get breadcrumb override for a post
     *
     * @since  1.0.0
     * @param  int $post_id Post ID
     * @return string|null Breadcrumb override or null
     */
    public static function get_breadcrumb_override($post_id) {
        $override = get_post_meta($post_id, 'bw_breadcrumb_schema', true);
        return !empty($override) ? $override : null;
    }
}

