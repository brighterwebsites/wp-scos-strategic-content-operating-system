<?php
/**
 * ALTC Meta Boxes
 *
 * File: class-altc-meta-boxes.php
 * Version: 1.0.0
 *
 * Responsibilities:
 * - ALTC Content Strategy meta box (Primary ALTC, Primary Topic, Content Maturity, Topic Notes)
 * - Quick-add topic functionality
 * - Update Content Optimization meta box with help text
 * - Save handlers
 */

if (!defined('ABSPATH')) exit;

class BW_ALTC_Meta_Boxes {

    /**
     * Initialize meta boxes
     */
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);
        add_action('save_post', [__CLASS__, 'save_altc_meta'], 10, 1);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_bw_altc_add_topic', [__CLASS__, 'ajax_add_topic']);
    }

    /**
     * Register meta boxes
     */
    public static function register_meta_boxes() {
        $post_types = BW_ALTC_Taxonomies::get_supported_post_types();

        foreach ($post_types as $post_type) {
            // ALTC Content Strategy meta box
            add_meta_box(
                'bw_altc_strategy',
                __('ALTC Content Strategy', 'brighterwebsites'),
                [__CLASS__, 'render_altc_strategy_metabox'],
                $post_type,
                'side',
                'high'
            );
        }
    }

    /**
     * Render ALTC Content Strategy meta box
     */
    public static function render_altc_strategy_metabox($post) {
        wp_nonce_field('bw_altc_metabox', 'bw_altc_nonce');

        $primary_altc_id = get_post_meta($post->ID, 'bw_primary_altc_id', true);
        $primary_topic_id = get_post_meta($post->ID, 'bw_primary_topic_id', true);
        $content_maturity = get_post_meta($post->ID, 'bw_cont_maturity', true);
        $notes = get_post_meta($post->ID, 'bw_notes', true);

        // Get ALTC terms
        $altc_terms = get_terms([
            'taxonomy' => 'altc_strategic_lens',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        // Get topic terms
        $topic_terms = get_terms([
            'taxonomy' => 'altc_topic',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        $maturity_options = BW_ALTC_Taxonomies::get_maturity_options();
        ?>

        <style>
            .bw-altc-field { margin-bottom: 14px; }
            .bw-altc-field label { display: block; font-weight: 600; margin-bottom: 4px; }
            .bw-altc-field label .required { color: #d63638; }
            .bw-altc-field select,
            .bw-altc-field textarea { width: 100%; }
            .bw-altc-field textarea { min-height: 60px; resize: vertical; }
            .bw-altc-help { font-size: 11px; color: #666; margin-top: 3px; line-height: 1.4; }
            .bw-altc-topic-wrapper { position: relative; }
            .bw-altc-add-topic {
                display: inline-block;
                margin-top: 4px;
                font-size: 11px;
                text-decoration: none;
            }
            .bw-altc-quick-add {
                display: none;
                margin-top: 6px;
                padding: 8px;
                background: #f0f0f1;
                border-radius: 3px;
            }
            .bw-altc-quick-add input {
                width: 100%;
                margin-bottom: 4px;
            }
            .bw-altc-quick-add-actions {
                display: flex;
                gap: 6px;
            }
            .bw-altc-quick-add-actions button {
                flex: 1;
                padding: 4px 8px;
                font-size: 11px;
            }
        </style>

        <div class="bw-altc-field">
            <label for="bw_primary_altc_id">
                <?php esc_html_e('Primary ALTC Strategic Lens', 'brighterwebsites'); ?>
                <span class="required">*</span>
            </label>
            <select id="bw_primary_altc_id" name="bw_primary_altc_id" required>
                <option value=""><?php esc_html_e('-- Select ALTC --', 'brighterwebsites'); ?></option>
                <?php if (!is_wp_error($altc_terms) && !empty($altc_terms)): ?>
                    <?php foreach ($altc_terms as $term): ?>
                        <?php if ($term->parent == 0): // Only show top-level terms ?>
                            <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($primary_altc_id, $term->term_id); ?>>
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="" disabled><?php esc_html_e('No ALTC lenses found', 'brighterwebsites'); ?></option>
                <?php endif; ?>
            </select>
            <p class="bw-altc-help"><?php esc_html_e('Select the primary strategic lens for this content', 'brighterwebsites'); ?></p>
        </div>

        <div class="bw-altc-field">
            <label for="bw_primary_topic_id">
                <?php esc_html_e('Primary Topic', 'brighterwebsites'); ?>
                <span class="required">*</span>
            </label>
            <div class="bw-altc-topic-wrapper">
                <select id="bw_primary_topic_id" name="bw_primary_topic_id" required>
                    <option value=""><?php esc_html_e('-- Select Topic --', 'brighterwebsites'); ?></option>
                    <?php if (!is_wp_error($topic_terms) && !empty($topic_terms)): ?>
                        <?php foreach ($topic_terms as $term): ?>
                            <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($primary_topic_id, $term->term_id); ?>>
                                <?php echo esc_html($term->name); ?>
                                <?php if ($term->parent > 0): ?>
                                    <?php
                                    $parent = get_term($term->parent, 'altc_topic');
                                    if ($parent && !is_wp_error($parent)) {
                                        echo ' (' . esc_html($parent->name) . ')';
                                    }
                                    ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <a href="#" class="bw-altc-add-topic"><?php esc_html_e('+ Add New Topic', 'brighterwebsites'); ?></a>
                <div class="bw-altc-quick-add">
                    <input type="text" id="bw_new_topic_name" placeholder="<?php esc_attr_e('Enter topic name...', 'brighterwebsites'); ?>">
                    <div class="bw-altc-quick-add-actions">
                        <button type="button" class="button button-primary bw-altc-save-topic"><?php esc_html_e('Add', 'brighterwebsites'); ?></button>
                        <button type="button" class="button bw-altc-cancel-topic"><?php esc_html_e('Cancel', 'brighterwebsites'); ?></button>
                    </div>
                </div>
            </div>
            <p class="bw-altc-help"><?php esc_html_e('Select or create the primary topic for this content', 'brighterwebsites'); ?></p>
        </div>

        <div class="bw-altc-field">
            <label for="bw_cont_maturity">
                <?php esc_html_e('Content Maturity', 'brighterwebsites'); ?>
                <span class="required">*</span>
            </label>
            <select id="bw_cont_maturity"
                    name="bw_cont_maturity"
                    required>
                <?php foreach ($maturity_options as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($content_maturity, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="bw-altc-help"><?php esc_html_e('Content maturity level: Entry → Industry Authority', 'brighterwebsites'); ?></p>
        </div>

        <div class="bw-altc-field">
            <label for="bw_altc_notes">
                <?php esc_html_e('Topic Notes', 'brighterwebsites'); ?>
            </label>
            <textarea id="bw_altc_notes" name="bw_notes" rows="3" placeholder="<?php esc_attr_e('Note any secondary topics or strategic considerations...', 'brighterwebsites'); ?>"><?php echo esc_textarea($notes); ?></textarea>
            <p class="bw-altc-help"><?php esc_html_e('Optional notes about secondary topics or strategy', 'brighterwebsites'); ?></p>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Show/hide quick add form
            $('.bw-altc-add-topic').on('click', function(e) {
                e.preventDefault();
                $('.bw-altc-quick-add').slideToggle(200);
                $('#bw_new_topic_name').focus();
            });

            // Cancel quick add
            $('.bw-altc-cancel-topic').on('click', function() {
                $('.bw-altc-quick-add').slideUp(200);
                $('#bw_new_topic_name').val('');
            });

            // Save new topic
            $('.bw-altc-save-topic').on('click', function() {
                const topicName = $('#bw_new_topic_name').val().trim();

                if (!topicName) {
                    alert('<?php esc_html_e('Please enter a topic name', 'brighterwebsites'); ?>');
                    return;
                }

                const $button = $(this);
                $button.prop('disabled', true).text('<?php esc_html_e('Adding...', 'brighterwebsites'); ?>');

                $.post(ajaxurl, {
                    action: 'bw_altc_add_topic',
                    name: topicName,
                    _ajax_nonce: '<?php echo esc_js(wp_create_nonce('bw_altc_add_topic')); ?>'
                }, function(response) {
                    if (response.success && response.data.term_id) {
                        // Add new option to dropdown
                        const $option = $('<option>', {
                            value: response.data.term_id,
                            text: topicName,
                            selected: true
                        });
                        $('#bw_primary_topic_id').append($option);

                        // Close quick add
                        $('.bw-altc-quick-add').slideUp(200);
                        $('#bw_new_topic_name').val('');
                    } else {
                        alert(response.data || '<?php esc_html_e('Failed to add topic', 'brighterwebsites'); ?>');
                    }
                }).fail(function() {
                    alert('<?php esc_html_e('Failed to add topic', 'brighterwebsites'); ?>');
                }).always(function() {
                    $button.prop('disabled', false).text('<?php esc_html_e('Add', 'brighterwebsites'); ?>');
                });
            });

            // Enter key to save
            $('#bw_new_topic_name').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('.bw-altc-save-topic').click();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Save ALTC meta box data
     */
    public static function save_altc_meta($post_id) {
        // Check nonce
        if (!isset($_POST['bw_altc_nonce']) || !wp_verify_nonce($_POST['bw_altc_nonce'], 'bw_altc_metabox')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check if it's a revision
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Save primary ALTC ID
        if (isset($_POST['bw_primary_altc_id'])) {
            $altc_id = absint($_POST['bw_primary_altc_id']);
            if ($altc_id > 0) {
                update_post_meta($post_id, 'bw_primary_altc_id', $altc_id);
            } else {
                delete_post_meta($post_id, 'bw_primary_altc_id');
            }
        }

        // Save primary topic ID
        if (isset($_POST['bw_primary_topic_id'])) {
            $topic_id = absint($_POST['bw_primary_topic_id']);
            if ($topic_id > 0) {
                update_post_meta($post_id, 'bw_primary_topic_id', $topic_id);
            } else {
                delete_post_meta($post_id, 'bw_primary_topic_id');
            }
        }

        // Save content maturity
        if (isset($_POST['bw_cont_maturity'])) {
            $maturity = sanitize_text_field($_POST['bw_cont_maturity']);
            update_post_meta($post_id, 'bw_cont_maturity', $maturity);
        }

        // Notes are saved by the existing bw-content-strategy.php handler
    }

    /**
     * AJAX handler to add new topic
     */
    public static function ajax_add_topic() {
        check_ajax_referer('bw_altc_add_topic');

        if (!current_user_can('manage_categories')) {
            wp_send_json_error(__('You do not have permission to add topics', 'brighterwebsites'));
        }

        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';

        if (empty($name)) {
            wp_send_json_error(__('Topic name is required', 'brighterwebsites'));
        }

        // Check if topic already exists
        $existing = term_exists($name, 'altc_topic');
        if ($existing) {
            wp_send_json_success([
                'term_id' => $existing['term_id'],
                'message' => __('Topic already exists', 'brighterwebsites'),
            ]);
            return;
        }

        // Create new topic
        $result = wp_insert_term($name, 'altc_topic');

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'term_id' => $result['term_id'],
            'message' => __('Topic created successfully', 'brighterwebsites'),
        ]);
    }

    /**
     * Enqueue scripts for meta box
     */
    public static function enqueue_scripts($hook) {
        // Only load on post edit screens
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        // Check if current post type is supported
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, BW_ALTC_Taxonomies::get_supported_post_types(), true)) {
            return;
        }

        // jQuery is already enqueued by WordPress
    }
}

// Initialize
BW_ALTC_Meta_Boxes::init();

/**
 * Add help text to existing Content Optimization meta box
 */
add_action('add_meta_boxes', function() {
    // This will add a filter to inject help text into the existing meta box render function
    add_filter('bw_cs_purpose_help_text', function() {
        return '<p class="bw-cs-help" style="margin-top: 6px; padding: 8px; background: #e7f5fe; border-left: 3px solid #00a0d2;">
            <strong>💡 Tip:</strong> Diversifying content types (case studies, resource guides, etc.) within a topic reduces cannibalization risk.
        </p>';
    });
}, 9); // Priority 9 to run before meta boxes are registered
