<?php
/**
 * Brighter Tools: Support
 *
 * File: brighter-support.php
 * Version: 4.2.0
 *
 * Changelog:
 * 4.2.0 - SECURITY: XSS protection, nonce verification, capability checks, output escaping
 * 4.0.0 - Fixed HTML structure, improved tab rendering, added proper closing tags
 *
 * Purpose: Core support features for client sites including support page,
 * login styling, design credit, and branding elements.
 */

/**
 * Custom save handler for Agency Settings (bypasses WordPress Settings API issues)
 */
add_action('admin_init', function() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!isset($_GET['page']) || $_GET['page'] !== 'brighter_support') {
        return;
    }
    if (!isset($_GET['tab']) || $_GET['tab'] !== 'manuals') {
        return;
    }
    if (!current_user_can('manage_options') || !brighter_support_is_agency_user()) {
        return;
    }
    if (empty($_POST['agency_settings_nonce']) || !wp_verify_nonce($_POST['agency_settings_nonce'], 'save_agency_settings')) {
        return;
    }
    
    error_log('[Agency Settings] Custom save handler triggered');
    
    // Save all the settings - use wp_kses with script tags allowed
    $allowed_tags = [
        'script' => [
            'src' => true,
            'type' => true,
            'async' => true,
            'defer' => true,
            'data-key' => true,
            'id' => true,
        ],
        'link' => [
            'rel' => true,
            'href' => true,
            'as' => true,
            'type' => true,
            'crossorigin' => true,
        ]
    ];
    
    if (isset($_POST['simple_commenter_script'])) {
        $value = wp_unslash($_POST['simple_commenter_script']);
        $sanitized = wp_kses($value, $allowed_tags);
        update_option('simple_commenter_script', $sanitized);
        error_log('[Agency Settings] simple_commenter_script - Raw length: ' . strlen($value) . ', Sanitized length: ' . strlen($sanitized));
    }
    if (isset($_POST['ahrefs_analytics_script'])) {
        $value = wp_unslash($_POST['ahrefs_analytics_script']);
        $sanitized = wp_kses($value, $allowed_tags);
        update_option('ahrefs_analytics_script', $sanitized);
        error_log('[Agency Settings] ahrefs_analytics_script - Raw length: ' . strlen($value) . ', Sanitized length: ' . strlen($sanitized));
    }
    
    // Save all other fields
    $fields = ['manual_full_link', 'manual_quick_link', 'website_ranking_link', 'map_ranking_link',
               'ai_content_writing', 'ai_research', 'ai_social_media', 'ai_competitor_research', 'management_portal'];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_option($field, esc_url_raw(wp_unslash($_POST[$field])));
        }
    }
    
    // Redirect back to the same tab
    wp_safe_redirect(add_query_arg(['page' => 'brighter_support', 'tab' => 'manuals', 'saved' => '1'], admin_url('admin.php')));
    exit;
}, 1);

if (!defined('ABSPATH')) exit;

/**
 * Fix Settings API redirect to preserve tab parameter
 * SECURITY: Only affects brighter_support pages to avoid conflicts with other plugins
 */
add_filter('wp_redirect', function($location) {
    // CRITICAL: Only modify redirects from our settings page
    // Check that we're actually on our page before modifying anything
    if (!isset($_GET['page']) || $_GET['page'] !== 'brighter_support') {
        return $location; // Early return if not our page
    }
    
    // Only modify if this is a settings-updated redirect
    if (strpos($location, 'page=brighter_support') !== false && strpos($location, 'settings-updated=true') !== false) {
        // If tab parameter is missing, add it back
        if (strpos($location, 'tab=') === false && isset($_POST['tab'])) {
            $location = add_query_arg('tab', sanitize_key($_POST['tab']), $location);
        }
    }
    return $location;
}, 10);

/**
 * Inject third-party scripts from Agency Settings into <head>
 */
add_action('wp_head', function() {
    if (is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }
    
    // Simple Commenter - data is already sanitized on save, just output it
    $simple_commenter = get_option('simple_commenter_script', '');
    if (!empty($simple_commenter)) {
        echo "\n<!-- Simple Commenter -->\n" . $simple_commenter . "\n";
    }
    
    // Ahrefs Analytics - data is already sanitized on save, just output it
    $ahrefs = get_option('ahrefs_analytics_script', '');
    if (!empty($ahrefs)) {
        echo "\n" . $ahrefs . "\n";
    }
}, 10);

/**
 * Add Support Hub menu page
 * SECURITY: Proper capability requirement
 */
add_action('admin_menu', 'brighter_support_add_menu');
function brighter_support_add_menu() {
    add_menu_page(
        'Support Hub',
        'Support',
        'read',
        'brighter_support',
        'brighter_support_render_page',
        'dashicons-sos',
        3
    );
}

/**
 * Register settings for manual links
 * SECURITY: Sanitization callbacks added
 */
add_action('admin_init', function () {
    register_setting('brighter_support_settings', 'manual_full_link', [
        'sanitize_callback' => 'esc_url_raw',
        'default' => ''
    ]);
    register_setting('brighter_support_settings', 'manual_quick_link', [
        'sanitize_callback' => 'esc_url_raw',
        'default' => ''
    ]);
    register_setting('brighter_support_settings', 'website_ranking_link', [
        'sanitize_callback' => 'esc_url_raw',
        'default' => ''
    ]);
    register_setting('brighter_support_settings', 'map_ranking_link', [
        'sanitize_callback' => 'esc_url_raw',
        'default' => ''
    ]);
    
    // AI Tools & Management Portal
    register_setting('brighter_support_settings', 'ai_content_writing', [
        'sanitize_callback' => 'esc_url_raw',
        'default' => ''
    ]);
    register_setting('brighter_support_settings', 'ai_research', [
        'sanitize_callback' => 'esc_url_raw',
        'default' => ''
    ]);
    register_setting('brighter_support_settings', 'ai_social_media', [
        'sanitize_callback' => 'esc_url_raw',
        'default' => ''
    ]);
    register_setting('brighter_support_settings', 'ai_competitor_research', [
        'sanitize_callback' => 'esc_url_raw',
        'default' => ''
    ]);
    register_setting('brighter_support_settings', 'management_portal', [
        'sanitize_callback' => 'esc_url_raw',
        'default' => ''
    ]);
    
    // Third-party Scripts
    register_setting('brighter_support_settings', 'simple_commenter_script', [
        'sanitize_callback' => function($value) {
            error_log('[Agency Settings] Saving simple_commenter_script: ' . substr($value, 0, 50));
            return wp_kses_post($value);
        },
        'default' => ''
    ]);
    register_setting('brighter_support_settings', 'ahrefs_analytics_script', [
        'sanitize_callback' => function($value) {
            error_log('[Agency Settings] Saving ahrefs_analytics_script: ' . substr($value, 0, 50));
            return wp_kses_post($value);
        },
        'default' => ''
    ]);

    add_settings_section(
        'brighter_manual_links_section',
        'Manual & Tool Links',
        function() {
            echo '<p>' . esc_html__('Enter the URLs for client manuals and ranking tools.', 'brighterwebsites') . '</p>';
        },
        'brighter_support_page'
    );

    $fields = [
        'manual_full_link'      => 'Full Manual URL',
        'manual_quick_link'     => 'Quick Guide URL',
        'website_ranking_link'  => 'Website Ranking Tool URL',
        'map_ranking_link'      => 'Map Ranking Tool URL'
    ];
    
    $ai_fields = [
        'ai_content_writing'     => 'Content Writing Assistant URL',
        'ai_research'            => 'Research Assistant URL',
        'ai_social_media'        => 'Social Media Assistant URL',
        'ai_competitor_research' => 'Competitor Market Research Tool URL',
        'management_portal'      => 'Growth & Scale Client Portal URL'
    ];

    foreach ($fields as $id => $label) {
        add_settings_field(
            $id,
            $label,
            function($args) {
                $value = get_option($args['id'], '');
                echo '<input type="url" name="' . esc_attr($args['id']) . '" value="' . esc_attr($value) . '" class="regular-text" />';
            },
            'brighter_support_page',
            'brighter_manual_links_section',
            ['id' => $id]
        );
    }
    
    // AI Tools & Management section
    add_settings_section(
        'brighter_ai_tools_section',
        'Custom AI Tools & Management',
        function() {
            echo '<p>' . esc_html__('Enter URLs for your custom AI assistants and management portals. These will appear on the Support Hub only when populated.', 'brighterwebsites') . '</p>';
        },
        'brighter_support_page'
    );
    
    foreach ($ai_fields as $id => $label) {
        add_settings_field(
            $id,
            $label,
            function($args) {
                $value = get_option($args['id'], '');
                echo '<input type="url" name="' . esc_attr($args['id']) . '" value="' . esc_attr($value) . '" class="regular-text" />';
            },
            'brighter_support_page',
            'brighter_ai_tools_section',
            ['id' => $id]
        );
    }
    
    // Third-party scripts section
    add_settings_section(
        'brighter_scripts_section',
        'Third-Party Scripts',
        function() {
            echo '<p>' . esc_html__('These scripts will be injected into the <head> section when populated.', 'brighterwebsites') . '</p>';
        },
        'brighter_support_page'
    );
    
    add_settings_field(
        'simple_commenter_script',
        'Simple Commenter Script',
        function() {
            wp_cache_delete('simple_commenter_script', 'options');
            $value = get_option('simple_commenter_script', '');
            echo '<textarea name="simple_commenter_script" rows="3" class="large-text code" style="width:100%;max-width:600px;">' . esc_textarea($value) . '</textarea>';
            echo '<p class="description">' . esc_html__('Paste the full <script> tag from Simple Commenter.', 'brighterwebsites') . '<br><strong>' . esc_html__('Important:', 'brighterwebsites') . '</strong> ' . esc_html__('Add /js/comments.min.js to WP Rocket/LiteSpeed Cache JS Excludes.', 'brighterwebsites') . '</p>';
        },
        'brighter_support_page',
        'brighter_scripts_section'
    );
    
    add_settings_field(
        'ahrefs_analytics_script',
        'Ahrefs Analytics Script',
        function() {
            wp_cache_delete('ahrefs_analytics_script', 'options');
            $value = get_option('ahrefs_analytics_script', '');
            echo '<textarea name="ahrefs_analytics_script" rows="3" class="large-text code" style="width:100%;max-width:600px;">' . esc_textarea($value) . '</textarea>';
            echo '<p class="description">' . esc_html__('Paste the full <script> tag from Ahrefs Analytics.', 'brighterwebsites') . '</p>';
        },
        'brighter_support_page',
        'brighter_scripts_section'
    );
});

/**
 * Check if user is a Brighter Websites team member
 * 
 * @return bool True if user has @brighterwebsites.com.au email
 */
function brighter_support_is_agency_user() {
    $current_user = wp_get_current_user();
    $email = $current_user->user_email;
    return (bool) preg_match('/@brighterwebsites\.com\.au$/i', $email);
}

/**
 * Main support page renderer with tabs
 * SECURITY: Capability checks, nonce verification, output escaping
 */
function brighter_support_render_page() {
    // SECURITY: Verify user can access
    if (!current_user_can('read')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'brighterwebsites'));
    }
    
    $current_user = wp_get_current_user();
    $email = $current_user->user_email;
    $is_agency_user = brighter_support_is_agency_user();
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'support';

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Welcome to Your Website Support Hub', 'brighterwebsites') . '</h1>';
    
    // Tab navigation - SECURITY: URLs properly escaped
    echo '<nav class="nav-tab-wrapper">';
    echo '<a href="' . esc_url(admin_url('admin.php?page=brighter_support&tab=support')) . '" class="nav-tab ' . ($active_tab == 'support' ? 'nav-tab-active' : '') . '">' . esc_html__('Support Info', 'brighterwebsites') . '</a>';

    // Agency Settings: Only show to @brighterwebsites.com.au users
    if ($is_agency_user) {
        echo '<a href="' . esc_url(admin_url('admin.php?page=brighter_support&tab=manuals')) . '" class="nav-tab ' . ($active_tab == 'manuals' ? 'nav-tab-active' : '') . '">' . esc_html__('Agency Settings', 'brighterwebsites') . '</a>';
    }

    // Allow other modules to add tabs (e.g., API Settings)
    $custom_tabs = apply_filters('brighter_support_tabs', array(), $email);
    foreach ($custom_tabs as $tab_key => $tab_label) {
        echo '<a href="' . esc_url(admin_url('admin.php?page=brighter_support&tab=' . $tab_key)) . '" class="nav-tab ' . ($active_tab == $tab_key ? 'nav-tab-active' : '') . '">' . esc_html($tab_label) . '</a>';
    }

    echo '</nav>';

    // Tab content
    echo '<div class="tab-content">';

    if ($active_tab === 'manuals' && $is_agency_user) {
        brighter_support_render_manuals_tab();
    } else {
        // Check if a custom tab handler wants to render content
        $custom_content = apply_filters('brighter_support_tab_content', '', $active_tab);

        if (!empty($custom_content)) {
            echo $custom_content;
        } else {
            // Default to main support page
            brighter_support_output_main();
        }
    }

    echo '</div>'; // .tab-content
    echo '</div>'; // .wrap
}

/**
 * Render agency settings tab (formerly manual links)
 * SECURITY: Settings API provides nonce protection
 * ACCESS: Only accessible to @brighterwebsites.com.au users
 */
function brighter_support_render_manuals_tab() {
    if (!brighter_support_is_agency_user()) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'brighterwebsites'));
    }

    // Show success message
    if (isset($_GET['saved']) && $_GET['saved'] === '1') {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Agency Settings saved successfully!', 'brighterwebsites') . '</p></div>';
    }

    echo '<div class="support-page">';
    echo '<style>.form-table th { width: 200px; vertical-align: top; padding-top: 20px; } .form-table td { padding-top: 15px; }</style>';
    
    // Custom form (NOT using Settings API)
    echo '<form method="post" action="">';
    wp_nonce_field('save_agency_settings', 'agency_settings_nonce');
    
    do_settings_sections('brighter_support_page');
    submit_button(esc_html__('Save Agency Settings', 'brighterwebsites'));
    echo '</form>';
    echo '</div>';
}

/**
 * Main support info tab content
 * SECURITY: All output properly escaped
 */
function brighter_support_output_main() {
    // Get all link options
    $manual_full_link = get_option('manual_full_link', '');
    $manual_quick_link = get_option('manual_quick_link', '');
    $website_ranking_link = get_option('website_ranking_link', '');
    $map_ranking_link = get_option('map_ranking_link', '');
    
    // AI Tools
    $ai_content = get_option('ai_content_writing', '');
    $ai_research = get_option('ai_research', '');
    $ai_social = get_option('ai_social_media', '');
    $ai_competitor = get_option('ai_competitor_research', '');
    $management_portal = get_option('management_portal', '');
    
    $has_ai_tools = !empty($ai_content) || !empty($ai_research) || !empty($ai_social) || !empty($ai_competitor);
    ?>
    
    <style>
        .support-hub-wrap {
            max-width: 1200px;
        }
        .support-hub-intro {
            margin-bottom: 30px;
        }
        .support-hub-intro p {
            font-size: 14px;
            color: #646970;
            margin: 5px 0;
        }
        .support-hub-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .support-hub-card h2 {
            margin-top: 0;
            font-size: 18px;
            border-bottom: 1px solid #dcdcde;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .support-compare-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .support-compare-table th {
            background: #f6f7f7;
            padding: 12px;
            text-align: left;
            border: 1px solid #c3c4c7;
            font-weight: 600;
        }
        .support-compare-table td {
            padding: 10px 12px;
            border: 1px solid #dcdcde;
        }
        .support-ai-tools {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
        }
        .support-ai-tools .button {
            flex: 1;
            min-width: 180px;
            text-align: center;
        }
        .support-hub-card ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .support-hub-card ul li {
            margin: 8px 0;
        }
        .support-backup-note {
            background: #f0f6fc;
            border-left: 4px solid #72aee6;
            padding: 12px 15px;
            margin: 15px 0;
        }
        .support-backup-note p {
            margin: 5px 0;
        }
    </style>
    
    <div class="wrap support-hub-wrap">
        <div class="support-hub-intro">
            <p><?php esc_html_e('We\'ve created this page to help you confidently manage and maintain your website.', 'brighterwebsites'); ?></p>
            <p><?php esc_html_e('Below are quick-access links and tips to get you started.', 'brighterwebsites'); ?></p>
        </div>

        <!-- ===================================
             CARD 1: MANAGE YOUR CONTENT
             =================================== -->
        <div class="support-hub-card">
            <h2>📝 <?php esc_html_e('Manage Your Content', 'brighterwebsites'); ?></h2>
            
            <table class="support-compare-table">
                <thead>
                    <tr>
                        <th><a href="<?php echo esc_url(admin_url('edit.php')); ?>"><?php esc_html_e('Posts', 'brighterwebsites'); ?></a></th>
                        <th><a href="<?php echo esc_url(admin_url('edit.php?post_type=page')); ?>"><?php esc_html_e('Pages', 'brighterwebsites'); ?></a></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Appear in blog feed', 'brighterwebsites'); ?></td>
                        <td><?php esc_html_e('Stand-alone content (like About or Contact)', 'brighterwebsites'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Organised by date, category, tags', 'brighterwebsites'); ?></td>
                        <td><?php esc_html_e('Organised hierarchically (parent/child)', 'brighterwebsites'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Ideal for regular updates', 'brighterwebsites'); ?></td>
                        <td><?php esc_html_e('Best for timeless content', 'brighterwebsites'); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <div class="support-backup-note">
                <p><strong><?php esc_html_e('Backups:', 'brighterwebsites'); ?></strong> 
                    <a href="<?php echo esc_url(admin_url('admin.php?page=WPvivid')); ?>"><?php esc_html_e('Go to Backups', 'brighterwebsites'); ?></a>
                </p>
                <p><em><?php esc_html_e('Create a backup of your website before making major changes so you can make restorations yourself if needed. (log in with your administrator account to access backups)', 'brighterwebsites'); ?></em></p>
                <p><?php esc_html_e('Your website is automatically backed up on your managed hosting server weekly - The last 2 backups are kept by default. If something goes wrong, contact us to restore a previous version. - Care Plans have at least 4 restore from server backups each year', 'brighterwebsites'); ?></p>
            </div>
            
            <?php if ($has_ai_tools): ?>
                <h3 style="margin-top:20px;margin-bottom:10px;"><?php esc_html_e('Your Custom AI Tools & Assistants', 'brighterwebsites'); ?></h3>
                <div class="support-ai-tools">
                    <?php if ($ai_research): ?>
                        <a href="<?php echo esc_url($ai_research); ?>" class="button" target="_blank" rel="noopener">
                            <?php esc_html_e('Research Assistant', 'brighterwebsites'); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($ai_content): ?>
                        <a href="<?php echo esc_url($ai_content); ?>" class="button" target="_blank" rel="noopener">
                            <?php esc_html_e('Content Writing Assistant', 'brighterwebsites'); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($ai_social): ?>
                        <a href="<?php echo esc_url($ai_social); ?>" class="button" target="_blank" rel="noopener">
                            <?php esc_html_e('Social Media Assistant', 'brighterwebsites'); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($ai_competitor): ?>
                        <a href="<?php echo esc_url($ai_competitor); ?>" class="button" target="_blank" rel="noopener">
                            <?php esc_html_e('Competitor Market Research', 'brighterwebsites'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===================================
             CARD 2: SUPPORT - NEED HELP?
             =================================== -->
        <div class="support-hub-card">
            <h2>💬 <?php esc_html_e('Need Help?', 'brighterwebsites'); ?></h2>
            <p><?php echo wp_kses_post(__('We\'re here to help with technical issues, updates, or questions. <strong>Always call or SMS if you need help!</strong>', 'brighterwebsites')); ?></p>
            <p><?php echo wp_kses_post(__('Email us directly at <a href="mailto:support@brighterwebsites.com.au">support@brighterwebsites.com.au</a> (longer reply time for email)', 'brighterwebsites')); ?></p>
            
            <div style="margin-top:15px;">
                <a href="https://brighterwebsites.com.au/kb/" class="button button-primary" target="_blank" rel="noopener">
                    <?php esc_html_e('Website Knowledge Base', 'brighterwebsites'); ?>
                </a>
            </div>
            
            <h3 style="margin-top:25px;margin-bottom:10px;"><?php esc_html_e('📖 Documentation', 'brighterwebsites'); ?></h3>
            <ul style="margin-left:0;padding-left:20px;">
                <?php if ($manual_full_link): ?>
                    <li>
                        <strong><?php esc_html_e('Website Owners Manual:', 'brighterwebsites'); ?></strong> 
                        <a href="<?php echo esc_url($manual_full_link); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open Manual', 'brighterwebsites'); ?></a>
                    </li>
                <?php endif; ?>
                <?php if ($management_portal): ?>
                    <li>
                        <strong><?php esc_html_e('Project Portal:', 'brighterwebsites'); ?></strong> 
                        <a href="<?php echo esc_url($management_portal); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open Portal', 'brighterwebsites'); ?></a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- ===================================
             CARD 3: PERFORMANCE & SEARCH
             =================================== -->
        <div class="support-hub-card">
            <h2>🔍 <?php esc_html_e('Performance & Search', 'brighterwebsites'); ?></h2>
            <p><?php esc_html_e('If we set these up as part of your website package, you\'ll find your account details in your Website Manual.', 'brighterwebsites'); ?></p>
            
            <ul>
                <?php if ($website_ranking_link): ?>
                    <li>
                        <strong><?php esc_html_e('SEO Website Ranking Report:', 'brighterwebsites'); ?></strong> 
                        <a href="<?php echo esc_url($website_ranking_link); ?>" target="_blank" rel="noopener"><?php esc_html_e('View Report', 'brighterwebsites'); ?></a>
                    </li>
                <?php endif; ?>
                <?php if ($map_ranking_link): ?>
                    <li>
                        <strong><?php esc_html_e('SEO Map Ranking Report:', 'brighterwebsites'); ?></strong> 
                        <a href="<?php echo esc_url($map_ranking_link); ?>" target="_blank" rel="noopener"><?php esc_html_e('View Report', 'brighterwebsites'); ?></a>
                    </li>
                <?php endif; ?>
                <li>
                    <strong><?php esc_html_e('Google Search Console:', 'brighterwebsites'); ?></strong> 
                    <a href="https://search.google.com/search-console" target="_blank" rel="noopener"><?php esc_html_e('Manage Search Performance', 'brighterwebsites'); ?></a>
                </li>
                <li>
                    <strong><?php esc_html_e('Website Visitors:', 'brighterwebsites'); ?></strong> 
                    <a href="https://analytics.google.com" target="_blank" rel="noopener"><?php esc_html_e('Google Analytics', 'brighterwebsites'); ?></a>
                </li>
                <li>
                    <strong><?php esc_html_e('AHREFS SEO Health:', 'brighterwebsites'); ?></strong> 
                    <a href="https://app.ahrefs.com/site-audit" target="_blank" rel="noopener"><?php esc_html_e('Site Audit', 'brighterwebsites'); ?></a>
                </li>
                <li>
                    <strong><?php esc_html_e('Check Speed:', 'brighterwebsites'); ?></strong> 
                    <a href="https://pagespeed.web.dev" target="_blank" rel="noopener"><?php esc_html_e('PageSpeed Insights', 'brighterwebsites'); ?></a>
                </li>
                <?php if (current_user_can('manage_options')): ?>
                    <li>
                        <strong><?php esc_html_e('Check Website Health:', 'brighterwebsites'); ?></strong> 
                        <a href="<?php echo esc_url(admin_url('site-health.php')); ?>"><?php esc_html_e('Site Health Tool', 'brighterwebsites'); ?></a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <?php
}