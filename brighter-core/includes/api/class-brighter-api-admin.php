<?php
/**
 * Brighter API Admin Interface
 *
 * Handles admin interface for API settings and token management
 *
 * @package BrighterCore
 * @subpackage API
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class Brighter_API_Admin {

    /**
     * @var Brighter_API_Auth Authentication handler
     */
    private $auth;

    /**
     * Constructor
     *
     * @param Brighter_API_Auth $auth Authentication handler
     */
    public function __construct($auth) {
        $this->auth = $auth;
    }

    /**
     * Initialize admin hooks
     */
    public function init() {
        // Add API tab to Support menu
        add_filter('brighter_support_tabs', array($this, 'add_api_tab'), 10, 2);
        add_filter('brighter_support_tab_content', array($this, 'render_api_tab_content'), 10, 2);

        // Handle AJAX requests
        add_action('wp_ajax_brighter_api_regenerate_token', array($this, 'ajax_regenerate_token'));
        add_action('wp_ajax_brighter_api_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_brighter_api_download_openapi', array($this, 'ajax_download_openapi'));

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add API tab to Support menu (filter hook)
     *
     * @param array $tabs Existing tabs
     * @param string $email Current user email
     * @return array Modified tabs
     */
    public function add_api_tab($tabs, $email) {
        // Only show to @brighterwebsites.com.au emails
        if ($this->is_brighter_email($email)) {
            $tabs['api'] = __('API Settings', 'brighterwebsites');
        }
        return $tabs;
    }

    /**
     * Render API tab content (filter hook)
     *
     * @param string $content Existing content
     * @param string $tab Current tab
     * @return string Content to display
     */
    public function render_api_tab_content($content, $tab) {
        if ($tab === 'api') {
            ob_start();
            $this->render_api_settings();
            return ob_get_clean();
        }
        return $content;
    }

    /**
     * Check if email is from Brighter Websites
     *
     * @param string $email Email address
     * @return bool True if Brighter email
     */
    private function is_brighter_email($email) {
        return (bool) preg_match('/@brighterwebsites\.com\.au$/i', $email);
    }

    /**
     * Render API settings page
     */
    public function render_api_settings() {
        $current_user = wp_get_current_user();
        $email = $current_user->user_email;

        // Double-check email permission
        if (!$this->is_brighter_email($email)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Access denied. This section is only available to Brighter Websites staff.', 'brighterwebsites') . '</p></div>';
            return;
        }

        $token = $this->auth->get_token();
        $has_token = !empty($token);

        ?>
        <div class="support-page">
            <h2><?php esc_html_e('Custom GPT API Settings', 'brighterwebsites'); ?></h2>
            <p><?php esc_html_e('Configure API access for custom ChatGPT instances to retrieve structured content from this website.', 'brighterwebsites'); ?></p>

            <!-- API Token Section -->
            <div class="card" style="max-width: 800px; margin: 20px 0;">
                <h3><?php esc_html_e('API Token', 'brighterwebsites'); ?></h3>

                <?php if ($has_token): ?>
                    <div style="margin: 15px 0;">
                        <label for="brighter-api-token" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <?php esc_html_e('Current Token:', 'brighterwebsites'); ?>
                        </label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input
                                type="password"
                                id="brighter-api-token"
                                value="<?php echo esc_attr($token); ?>"
                                readonly
                                style="flex: 1; max-width: 500px; font-family: monospace;"
                            />
                            <button type="button" id="brighter-api-toggle-token" class="button">
                                <?php esc_html_e('Show', 'brighterwebsites'); ?>
                            </button>
                            <button type="button" id="brighter-api-copy-token" class="button">
                                <?php esc_html_e('Copy to Clipboard', 'brighterwebsites'); ?>
                            </button>
                        </div>
                        <p class="description" style="margin-top: 10px;">
                            <?php esc_html_e('Use this token in the X-Brighter-Token header when making API requests.', 'brighterwebsites'); ?>
                        </p>
                    </div>

                    <div style="margin: 20px 0;">
                        <button type="button" id="brighter-api-regenerate" class="button button-secondary">
                            <?php esc_html_e('Regenerate Token', 'brighterwebsites'); ?>
                        </button>
                        <p class="description" style="margin-top: 5px;">
                            ⚠️ <?php esc_html_e('Warning: Regenerating will break existing GPT connections using the old token.', 'brighterwebsites'); ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div style="margin: 15px 0;">
                        <p><?php esc_html_e('No API token has been generated yet.', 'brighterwebsites'); ?></p>
                        <button type="button" id="brighter-api-generate" class="button button-primary">
                            <?php esc_html_e('Generate API Token', 'brighterwebsites'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- API Endpoints Section -->
            <div class="card" style="max-width: 800px; margin: 20px 0;">
                <h3><?php esc_html_e('API Endpoints', 'brighterwebsites'); ?></h3>
                <p><strong><?php esc_html_e('Base URL:', 'brighterwebsites'); ?></strong></p>
                <p style="font-family: monospace; background: #f5f5f5; padding: 10px; border-radius: 3px;">
                    <?php echo esc_html(rest_url('brighter-core/v1')); ?>
                </p>

                <h4 style="margin-top: 20px;"><?php esc_html_e('Available Endpoints:', 'brighterwebsites'); ?></h4>
                <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th style="width: 30%;"><?php esc_html_e('Endpoint', 'brighterwebsites'); ?></th>
                            <th style="width: 40%;"><?php esc_html_e('Description', 'brighterwebsites'); ?></th>
                            <th style="width: 30%;"><?php esc_html_e('Status', 'brighterwebsites'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $endpoints = array(
                            'posts' => array('name' => 'Blog Posts', 'post_type' => 'post'),
                            'our-work' => array('name' => 'Portfolio', 'post_type' => 'folio'),
                            'kb' => array('name' => 'Knowledge Base', 'post_type' => 'kb'),
                            'news' => array('name' => 'News Articles', 'post_type' => 'news'),
                            'faqs' => array('name' => 'FAQs', 'post_type' => 'faq'),
                            'pages' => array('name' => 'Service Pages', 'post_type' => 'page')
                        );

                        foreach ($endpoints as $route => $data):
                            $exists = post_type_exists($data['post_type']);
                            $status_class = $exists ? 'success' : 'error';
                            $status_text = $exists ? __('Active', 'brighterwebsites') : __('Post Type Not Found', 'brighterwebsites');
                        ?>
                            <tr>
                                <td><code>GET /<?php echo esc_html($route); ?></code></td>
                                <td><?php echo esc_html($data['name']); ?></td>
                                <td>
                                    <span class="notice notice-<?php echo esc_attr($status_class); ?> inline" style="margin: 0; padding: 2px 8px;">
                                        <?php echo esc_html($status_text); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 20px;">
                    <button type="button" id="brighter-api-test" class="button button-secondary" <?php echo $has_token ? '' : 'disabled'; ?>>
                        <?php esc_html_e('Test API Connection', 'brighterwebsites'); ?>
                    </button>
                    <button type="button" id="brighter-api-download-openapi" class="button button-secondary" <?php echo $has_token ? '' : 'disabled'; ?>>
                        <?php esc_html_e('Download OpenAPI Spec', 'brighterwebsites'); ?>
                    </button>
                </div>

                <div id="brighter-api-test-result" style="margin-top: 15px; display: none;"></div>
            </div>

            <!-- Query Parameters Documentation -->
            <div class="card" style="max-width: 800px; margin: 20px 0;">
                <h3><?php esc_html_e('Query Parameters', 'brighterwebsites'); ?></h3>
                <p><?php esc_html_e('Content endpoints (posts, our-work, kb, news, faqs) support these query parameters:', 'brighterwebsites'); ?></p>
                <ul style="margin-left: 20px; line-height: 1.8;">
                    <li><code>page</code> - <?php esc_html_e('Page number (default: 1)', 'brighterwebsites'); ?></li>
                    <li><code>per_page</code> - <?php esc_html_e('Items per page (default: 15, max: 50)', 'brighterwebsites'); ?></li>
                    <li><code>status</code> - <?php esc_html_e('Post status (default: publish, options: publish, draft, any)', 'brighterwebsites'); ?></li>
                </ul>

                <p style="margin-top: 15px;"><strong><?php esc_html_e('Example Request:', 'brighterwebsites'); ?></strong></p>
                <pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto;">GET <?php echo esc_html(rest_url('brighter-core/v1/posts?page=1&per_page=15')); ?>
Headers: X-Brighter-Token: your_token_here</pre>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on Support page, API tab
        if ($hook !== 'toplevel_page_brighter_support') {
            return;
        }

        if (!isset($_GET['tab']) || $_GET['tab'] !== 'api') {
            return;
        }

        wp_add_inline_script('jquery', $this->get_inline_script());
    }

    /**
     * Get inline JavaScript for admin page
     *
     * @return string JavaScript code
     */
    private function get_inline_script() {
        ob_start();
        ?>
        jQuery(document).ready(function($) {
            // Toggle token visibility
            $('#brighter-api-toggle-token').on('click', function() {
                var $input = $('#brighter-api-token');
                var $button = $(this);

                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $button.text('<?php echo esc_js(__('Hide', 'brighterwebsites')); ?>');
                } else {
                    $input.attr('type', 'password');
                    $button.text('<?php echo esc_js(__('Show', 'brighterwebsites')); ?>');
                }
            });

            // Copy token to clipboard
            $('#brighter-api-copy-token').on('click', function() {
                var $input = $('#brighter-api-token');
                $input.attr('type', 'text').select();
                document.execCommand('copy');
                $input.attr('type', 'password');

                var $button = $(this);
                var originalText = $button.text();
                $button.text('<?php echo esc_js(__('Copied!', 'brighterwebsites')); ?>');
                setTimeout(function() {
                    $button.text(originalText);
                }, 2000);
            });

            // Generate/Regenerate token
            $('#brighter-api-generate, #brighter-api-regenerate').on('click', function() {
                var isRegenerate = $(this).attr('id') === 'brighter-api-regenerate';

                if (isRegenerate) {
                    if (!confirm('<?php echo esc_js(__('Are you sure? This will break existing GPT connections.', 'brighterwebsites')); ?>')) {
                        return;
                    }
                }

                $.post(ajaxurl, {
                    action: 'brighter_api_regenerate_token',
                    _wpnonce: '<?php echo esc_js(wp_create_nonce('brighter_api_regenerate')); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('<?php echo esc_js(__('Error generating token. Please try again.', 'brighterwebsites')); ?>');
                    }
                });
            });

            // Test API connection
            $('#brighter-api-test').on('click', function() {
                var $button = $(this);
                var $result = $('#brighter-api-test-result');

                $button.prop('disabled', true).text('<?php echo esc_js(__('Testing...', 'brighterwebsites')); ?>');

                $.post(ajaxurl, {
                    action: 'brighter_api_test_connection',
                    _wpnonce: '<?php echo esc_js(wp_create_nonce('brighter_api_test')); ?>'
                }, function(response) {
                    $button.prop('disabled', false).text('<?php echo esc_js(__('Test API Connection', 'brighterwebsites')); ?>');

                    if (response.success) {
                        $result.html('<div class="notice notice-success inline"><p><strong>✓ Success!</strong> API is working correctly. Response time: ' + response.data.time + 'ms</p></div>').show();
                    } else {
                        $result.html('<div class="notice notice-error inline"><p><strong>✗ Error:</strong> ' + response.data.message + '</p></div>').show();
                    }
                });
            });

            // Download OpenAPI spec
            $('#brighter-api-download-openapi').on('click', function() {
                window.location.href = ajaxurl + '?action=brighter_api_download_openapi&_wpnonce=' + '<?php echo esc_js(wp_create_nonce('brighter_api_openapi')); ?>';
            });
        });
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Regenerate API token
     */
    public function ajax_regenerate_token() {
        check_ajax_referer('brighter_api_regenerate');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $current_user = wp_get_current_user();
        if (!$this->is_brighter_email($current_user->user_email)) {
            wp_send_json_error(array('message' => 'Access denied'));
        }

        $token = $this->auth->generate_token();

        // Clear API cache when token changes
        Brighter_API_Endpoints::clear_cache();

        wp_send_json_success(array('token' => $token));
    }

    /**
     * AJAX: Test API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('brighter_api_test');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $token = $this->auth->get_token();

        if (empty($token)) {
            wp_send_json_error(array('message' => 'No API token configured'));
        }

        // Make test request to /posts endpoint
        $start_time = microtime(true);

        $response = wp_remote_get(rest_url('brighter-core/v1/posts?per_page=1'), array(
            'headers' => array(
                'X-Brighter-Token' => $token
            )
        ));

        $end_time = microtime(true);
        $response_time = round(($end_time - $start_time) * 1000, 2);

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 200) {
            wp_send_json_success(array('time' => $response_time));
        } else {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $message = isset($body['message']) ? $body['message'] : 'HTTP ' . $code;
            wp_send_json_error(array('message' => $message));
        }
    }

    /**
     * AJAX: Download OpenAPI specification
     */
    public function ajax_download_openapi() {
        check_ajax_referer('brighter_api_openapi', '_wpnonce');

        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        $current_user = wp_get_current_user();
        if (!$this->is_brighter_email($current_user->user_email)) {
            wp_die('Access denied');
        }

        $spec = $this->generate_openapi_spec();

        header('Content-Type: application/x-yaml');
        header('Content-Disposition: attachment; filename="brighter-api-openapi.yaml"');
        echo $spec;
        exit;
    }

    /**
     * Generate OpenAPI 3.1.0 specification
     *
     * @return string YAML specification
     */
    private function generate_openapi_spec() {
        $base_url = rest_url('brighter-core/v1');

        ob_start();
        ?>
openapi: 3.1.0
info:
  title: Brighter Websites Content API
  version: 1.0.0
  description: Access structured content from Brighter Websites for Custom GPT integration
  contact:
    name: Brighter Websites Support
    email: support@brighterwebsites.com.au

servers:
  - url: <?php echo esc_html($base_url); ?>

    description: Production API

security:
  - ApiKeyAuth: []

components:
  securitySchemes:
    ApiKeyAuth:
      type: apiKey
      in: header
      name: X-Brighter-Token
      description: API token for authentication

  schemas:
    ContentItem:
      type: object
      properties:
        id:
          type: integer
        title:
          type: string
        excerpt:
          type: string
        content:
          type: string
        slug:
          type: string
        url:
          type: string
        date:
          type: string
          format: date-time
        modified:
          type: string
          format: date-time
        featured_image:
          type: object
          nullable: true
        categories:
          type: array
          items:
            type: string
        tags:
          type: array
          items:
            type: string
        meta_description:
          type: string
        altc_content_data:
          type: object
        custom_fields:
          type: object

    Pagination:
      type: object
      properties:
        total:
          type: integer
        total_pages:
          type: integer
        current_page:
          type: integer
        per_page:
          type: integer
        has_more:
          type: boolean

    Error:
      type: object
      properties:
        code:
          type: string
        message:
          type: string
        data:
          type: object

paths:
  /posts:
    get:
      operationId: getBlogPosts
      summary: Get blog posts
      description: Retrieve paginated blog posts with full content and metadata
      parameters:
        - name: page
          in: query
          schema:
            type: integer
            default: 1
            minimum: 1
        - name: per_page
          in: query
          schema:
            type: integer
            default: 15
            minimum: 1
            maximum: 50
        - name: status
          in: query
          schema:
            type: string
            enum: [publish, draft, any]
            default: publish
      responses:
        '200':
          description: Successful response
          content:
            application/json:
              schema:
                type: object
                properties:
                  items:
                    type: array
                    items:
                      $ref: '#/components/schemas/ContentItem'
                  pagination:
                    $ref: '#/components/schemas/Pagination'
        '401':
          description: Unauthorized
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'

  /our-work:
    get:
      operationId: getPortfolio
      summary: Get portfolio items
      description: Retrieve paginated portfolio/work items
      parameters:
        - name: page
          in: query
          schema:
            type: integer
            default: 1
        - name: per_page
          in: query
          schema:
            type: integer
            default: 15
            maximum: 50
        - name: status
          in: query
          schema:
            type: string
            enum: [publish, draft, any]
            default: publish
      responses:
        '200':
          description: Successful response
        '401':
          description: Unauthorized

  /kb:
    get:
      operationId: getKnowledgeBase
      summary: Get knowledge base articles
      description: Retrieve paginated knowledge base articles
      parameters:
        - name: page
          in: query
          schema:
            type: integer
            default: 1
        - name: per_page
          in: query
          schema:
            type: integer
            default: 15
            maximum: 50
        - name: status
          in: query
          schema:
            type: string
            enum: [publish, draft, any]
            default: publish
      responses:
        '200':
          description: Successful response
        '401':
          description: Unauthorized

  /news:
    get:
      operationId: getNews
      summary: Get news articles
      description: Retrieve paginated news articles
      parameters:
        - name: page
          in: query
          schema:
            type: integer
            default: 1
        - name: per_page
          in: query
          schema:
            type: integer
            default: 15
            maximum: 50
        - name: status
          in: query
          schema:
            type: string
            enum: [publish, draft, any]
            default: publish
      responses:
        '200':
          description: Successful response
        '401':
          description: Unauthorized

  /faqs:
    get:
      operationId: getFAQs
      summary: Get FAQ items
      description: Retrieve paginated FAQ items
      parameters:
        - name: page
          in: query
          schema:
            type: integer
            default: 1
        - name: per_page
          in: query
          schema:
            type: integer
            default: 15
            maximum: 50
        - name: status
          in: query
          schema:
            type: string
            enum: [publish, draft, any]
            default: publish
      responses:
        '200':
          description: Successful response
        '401':
          description: Unauthorized

  /pages:
    get:
      operationId: getPages
      summary: Get configured service pages
      description: Retrieve pages configured in API settings
      responses:
        '200':
          description: Successful response
          content:
            application/json:
              schema:
                type: object
                properties:
                  pages:
                    type: array
                    items:
                      type: object
                      properties:
                        id:
                          type: integer
                        title:
                          type: string
                        content:
                          type: string
                        url:
                          type: string
                        excerpt:
                          type: string
                        meta_description:
                          type: string
        '401':
          description: Unauthorized

  /post:
    get:
      operationId: getBlogPost
      summary: Get blog post (singular)
      description: Retrieve single or paginated blog posts - GS format
      parameters:
        - name: page
          in: query
          schema:
            type: integer
            default: 1
        - name: per_page
          in: query
          schema:
            type: integer
            default: 1
            maximum: 50
        - name: status
          in: query
          schema:
            type: string
            enum: [publish, draft, any]
            default: publish
      responses:
        '200':
          description: Successful response
        '401':
          description: Unauthorized

  /page:
    get:
      operationId: getPage
      summary: Get page (singular)
      description: Retrieve single or paginated pages - GS format
      parameters:
        - name: page
          in: query
          schema:
            type: integer
            default: 1
        - name: per_page
          in: query
          schema:
            type: integer
            default: 1
            maximum: 50
        - name: status
          in: query
          schema:
            type: string
            enum: [publish, draft, any]
            default: publish
      responses:
        '200':
          description: Successful response
        '401':
          description: Unauthorized

  /project:
    get:
      operationId: getProject
      summary: Get project (singular)
      description: Retrieve single or paginated projects - GS format
      parameters:
        - name: page
          in: query
          schema:
            type: integer
            default: 1
        - name: per_page
          in: query
          schema:
            type: integer
            default: 1
            maximum: 50
        - name: status
          in: query
          schema:
            type: string
            enum: [publish, draft, any]
            default: publish
      responses:
        '200':
          description: Successful response
        '401':
          description: Unauthorized

  /faq:
    get:
      operationId: getFAQ
      summary: Get FAQ (singular)
      description: Retrieve single or paginated FAQs - GS format
      parameters:
        - name: page
          in: query
          schema:
            type: integer
            default: 1
        - name: per_page
          in: query
          schema:
            type: integer
            default: 1
            maximum: 50
        - name: status
          in: query
          schema:
            type: string
            enum: [publish, draft, any]
            default: publish
      responses:
        '200':
          description: Successful response
        '401':
          description: Unauthorized
        <?php
        return ob_get_clean();
    }
}
