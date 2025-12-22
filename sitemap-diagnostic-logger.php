<?php
/**
 * Plugin Name: Sitemap Diagnostic Logger
 * Description: Logs all sitemap requests with headers, user-agents, and response codes for debugging CDN/cache issues
 * Version: 1.0.0
 * Author: Brighter Websites
 *
 * Deploy to: wp-content/mu-plugins/
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sitemap_Diagnostic_Logger {

    private $table_name;
    private $db_version = '1.0';

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sitemap_diagnostic_log';

        // Hooks
        add_action('plugins_loaded', [$this, 'maybe_create_table']);
        add_action('init', [$this, 'log_sitemap_request'], 1); // Early hook
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_export_sitemap_logs', [$this, 'export_logs_csv']);

        // Cleanup old logs daily
        add_action('wp_scheduled_delete', [$this, 'cleanup_old_logs']);
        if (!wp_next_scheduled('wp_scheduled_delete')) {
            wp_schedule_event(time(), 'daily', 'wp_scheduled_delete');
        }
    }

    /**
     * Create database table if it doesn't exist
     */
    public function maybe_create_table() {
        global $wpdb;

        $installed_version = get_option('sitemap_diagnostic_db_version', '0');

        if (version_compare($installed_version, $this->db_version, '<')) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$this->table_name} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                request_time datetime NOT NULL,
                request_uri varchar(500) NOT NULL,
                user_agent text,
                ip_address varchar(100),
                http_referer text,
                request_headers longtext,
                response_status int(3),
                is_bot tinyint(1) DEFAULT 0,
                bot_type varchar(100),
                domain varchar(255),
                PRIMARY KEY  (id),
                KEY request_time (request_time),
                KEY is_bot (is_bot),
                KEY domain (domain),
                KEY response_status (response_status)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            update_option('sitemap_diagnostic_db_version', $this->db_version);
        }
    }

    /**
     * Log sitemap requests
     */
    public function log_sitemap_request() {
        // Only log XML file requests
        if (!isset($_SERVER['REQUEST_URI']) || !preg_match('/\.xml$/i', $_SERVER['REQUEST_URI'])) {
            return;
        }

        // Only log sitemap-related requests
        if (!preg_match('/sitemap/i', $_SERVER['REQUEST_URI'])) {
            return;
        }

        global $wpdb;

        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $ip_address = $this->get_client_ip();
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $request_uri = $_SERVER['REQUEST_URI'];

        // Detect bot type
        $bot_info = $this->detect_bot($user_agent);

        // Capture all request headers
        $headers = $this->get_all_headers();

        // Get response status (will be updated via output buffering if needed)
        $response_status = http_response_code() ?: 200;

        // Get current domain
        $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

        $wpdb->insert(
            $this->table_name,
            [
                'request_time' => current_time('mysql'),
                'request_uri' => $request_uri,
                'user_agent' => $user_agent,
                'ip_address' => $ip_address,
                'http_referer' => $referer,
                'request_headers' => json_encode($headers),
                'response_status' => $response_status,
                'is_bot' => $bot_info['is_bot'],
                'bot_type' => $bot_info['bot_type'],
                'domain' => $domain,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );
    }

    /**
     * Get client IP address (handles proxies)
     */
    private function get_client_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Detect if request is from a bot
     */
    private function detect_bot($user_agent) {
        $bots = [
            'Googlebot' => 'Googlebot',
            'bingbot' => 'Bingbot',
            'Slurp' => 'Yahoo',
            'DuckDuckBot' => 'DuckDuckGo',
            'Baiduspider' => 'Baidu',
            'YandexBot' => 'Yandex',
            'Sogou' => 'Sogou',
            'Exabot' => 'Exalead',
            'facebookexternalhit' => 'Facebook',
            'ia_archiver' => 'Alexa',
            'bot' => 'Generic Bot',
            'crawl' => 'Generic Crawler',
            'spider' => 'Generic Spider',
        ];

        foreach ($bots as $bot_string => $bot_name) {
            if (stripos($user_agent, $bot_string) !== false) {
                return ['is_bot' => 1, 'bot_type' => $bot_name];
            }
        }

        return ['is_bot' => 0, 'bot_type' => null];
    }

    /**
     * Get all request headers
     */
    private function get_all_headers() {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        // Fallback for nginx
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    /**
     * Cleanup logs older than 7 days
     */
    public function cleanup_old_logs() {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE request_time < %s",
                date('Y-m-d H:i:s', strtotime('-7 days'))
            )
        );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            'Sitemap Diagnostic Logs',
            'Sitemap Logs',
            'manage_options',
            'sitemap-diagnostic-logs',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'tools_page_sitemap-diagnostic-logs') {
            return;
        }

        wp_enqueue_style('sitemap-diagnostic-admin', false);
        wp_add_inline_style('sitemap-diagnostic-admin', $this->get_admin_css());
    }

    /**
     * Get admin CSS
     */
    private function get_admin_css() {
        return '
            .sdl-wrap { max-width: 100%; margin: 20px; }
            .sdl-filters { background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; }
            .sdl-filter-group { display: inline-block; margin-right: 20px; }
            .sdl-table { width: 100%; border-collapse: collapse; background: #fff; }
            .sdl-table th { background: #f0f0f1; padding: 10px; text-align: left; border-bottom: 2px solid #ccd0d4; }
            .sdl-table td { padding: 10px; border-bottom: 1px solid #e0e0e0; }
            .sdl-table tr:hover { background: #f9f9f9; }
            .sdl-bot { color: #d63638; font-weight: bold; }
            .sdl-googlebot { color: #4285f4; font-weight: bold; }
            .sdl-status-200 { color: #00a32a; }
            .sdl-status-403 { color: #d63638; }
            .sdl-status-404 { color: #dba617; }
            .sdl-headers { font-size: 11px; color: #646970; max-width: 300px; overflow: auto; }
            .sdl-export-btn { margin-left: 10px; }
            .sdl-stats { display: flex; gap: 20px; margin-bottom: 20px; }
            .sdl-stat-box { background: #fff; padding: 15px; border: 1px solid #ccd0d4; flex: 1; text-align: center; }
            .sdl-stat-number { font-size: 32px; font-weight: bold; color: #2271b1; }
            .sdl-stat-label { color: #646970; margin-top: 5px; }
        ';
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        global $wpdb;

        // Get filter params
        $filter_domain = isset($_GET['filter_domain']) ? sanitize_text_field($_GET['filter_domain']) : '';
        $filter_bot = isset($_GET['filter_bot']) ? sanitize_text_field($_GET['filter_bot']) : '';
        $filter_status = isset($_GET['filter_status']) ? intval($_GET['filter_status']) : 0;

        // Build query
        $where = ['1=1'];
        if ($filter_domain) {
            $where[] = $wpdb->prepare('domain = %s', $filter_domain);
        }
        if ($filter_bot === 'googlebot') {
            $where[] = "bot_type = 'Googlebot'";
        } elseif ($filter_bot === 'bots') {
            $where[] = "is_bot = 1";
        } elseif ($filter_bot === 'humans') {
            $where[] = "is_bot = 0";
        }
        if ($filter_status) {
            $where[] = $wpdb->prepare('response_status = %d', $filter_status);
        }

        $where_clause = implode(' AND ', $where);

        // Get stats
        $total_requests = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE $where_clause");
        $bot_requests = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE is_bot = 1 AND $where_clause");
        $googlebot_requests = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE bot_type = 'Googlebot' AND $where_clause");
        $error_requests = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE response_status >= 400 AND $where_clause");

        // Get logs
        $logs = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE $where_clause ORDER BY request_time DESC LIMIT 100"
        );

        // Get unique domains
        $domains = $wpdb->get_col("SELECT DISTINCT domain FROM {$this->table_name} ORDER BY domain");

        ?>
        <div class="sdl-wrap wrap">
            <h1>Sitemap Diagnostic Logs</h1>

            <!-- Stats -->
            <div class="sdl-stats">
                <div class="sdl-stat-box">
                    <div class="sdl-stat-number"><?php echo number_format($total_requests); ?></div>
                    <div class="sdl-stat-label">Total Requests</div>
                </div>
                <div class="sdl-stat-box">
                    <div class="sdl-stat-number"><?php echo number_format($googlebot_requests); ?></div>
                    <div class="sdl-stat-label">Googlebot</div>
                </div>
                <div class="sdl-stat-box">
                    <div class="sdl-stat-number"><?php echo number_format($bot_requests); ?></div>
                    <div class="sdl-stat-label">All Bots</div>
                </div>
                <div class="sdl-stat-box">
                    <div class="sdl-stat-number"><?php echo number_format($error_requests); ?></div>
                    <div class="sdl-stat-label">Errors (4xx/5xx)</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="sdl-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="sitemap-diagnostic-logs">

                    <div class="sdl-filter-group">
                        <label>Domain:</label>
                        <select name="filter_domain">
                            <option value="">All Domains</option>
                            <?php foreach ($domains as $domain): ?>
                                <option value="<?php echo esc_attr($domain); ?>" <?php selected($filter_domain, $domain); ?>>
                                    <?php echo esc_html($domain); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sdl-filter-group">
                        <label>Type:</label>
                        <select name="filter_bot">
                            <option value="">All Requests</option>
                            <option value="googlebot" <?php selected($filter_bot, 'googlebot'); ?>>Googlebot Only</option>
                            <option value="bots" <?php selected($filter_bot, 'bots'); ?>>All Bots</option>
                            <option value="humans" <?php selected($filter_bot, 'humans'); ?>>Humans Only</option>
                        </select>
                    </div>

                    <div class="sdl-filter-group">
                        <label>Status:</label>
                        <select name="filter_status">
                            <option value="0">All Status Codes</option>
                            <option value="200" <?php selected($filter_status, 200); ?>>200 OK</option>
                            <option value="403" <?php selected($filter_status, 403); ?>>403 Forbidden</option>
                            <option value="404" <?php selected($filter_status, 404); ?>>404 Not Found</option>
                            <option value="500" <?php selected($filter_status, 500); ?>>500 Error</option>
                        </select>
                    </div>

                    <button type="submit" class="button button-primary">Apply Filters</button>
                    <a href="?page=sitemap-diagnostic-logs" class="button">Clear Filters</a>
                    <button type="button" class="button sdl-export-btn" onclick="exportLogs()">Export CSV</button>
                </form>
            </div>

            <!-- Logs Table -->
            <table class="sdl-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Domain</th>
                        <th>URI</th>
                        <th>User Agent</th>
                        <th>IP</th>
                        <th>Status</th>
                        <th>Bot Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                No sitemap requests logged yet. Requests will appear here automatically.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log->request_time))); ?></td>
                                <td><?php echo esc_html($log->domain); ?></td>
                                <td><code><?php echo esc_html($log->request_uri); ?></code></td>
                                <td title="<?php echo esc_attr($log->user_agent); ?>">
                                    <?php echo esc_html(substr($log->user_agent, 0, 50)) . (strlen($log->user_agent) > 50 ? '...' : ''); ?>
                                </td>
                                <td><?php echo esc_html($log->ip_address); ?></td>
                                <td class="sdl-status-<?php echo intval($log->response_status); ?>">
                                    <?php echo intval($log->response_status); ?>
                                </td>
                                <td>
                                    <?php if ($log->is_bot): ?>
                                        <span class="<?php echo $log->bot_type === 'Googlebot' ? 'sdl-googlebot' : 'sdl-bot'; ?>">
                                            <?php echo esc_html($log->bot_type); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #646970;">Human</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <p style="margin-top: 20px; color: #646970;">
                Showing last 100 requests. Logs auto-delete after 7 days.
            </p>
        </div>

        <script>
        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            params.set('action', 'export_sitemap_logs');
            window.location.href = ajaxurl + '?' + params.toString();
        }
        </script>
        <?php
    }

    /**
     * Export logs as CSV
     */
    public function export_logs_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        global $wpdb;

        // Get filter params
        $filter_domain = isset($_GET['filter_domain']) ? sanitize_text_field($_GET['filter_domain']) : '';
        $filter_bot = isset($_GET['filter_bot']) ? sanitize_text_field($_GET['filter_bot']) : '';
        $filter_status = isset($_GET['filter_status']) ? intval($_GET['filter_status']) : 0;

        // Build query
        $where = ['1=1'];
        if ($filter_domain) {
            $where[] = $wpdb->prepare('domain = %s', $filter_domain);
        }
        if ($filter_bot === 'googlebot') {
            $where[] = "bot_type = 'Googlebot'";
        } elseif ($filter_bot === 'bots') {
            $where[] = "is_bot = 1";
        } elseif ($filter_bot === 'humans') {
            $where[] = "is_bot = 0";
        }
        if ($filter_status) {
            $where[] = $wpdb->prepare('response_status = %d', $filter_status);
        }

        $where_clause = implode(' AND ', $where);

        $logs = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE $where_clause ORDER BY request_time DESC",
            ARRAY_A
        );

        // Set headers for download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sitemap-logs-' . date('Y-m-d-His') . '.csv"');

        $output = fopen('php://output', 'w');

        // Header row
        if (!empty($logs)) {
            fputcsv($output, array_keys($logs[0]));
        }

        // Data rows
        foreach ($logs as $log) {
            fputcsv($output, $log);
        }

        fclose($output);
        exit;
    }
}

// Initialize
new Sitemap_Diagnostic_Logger();
