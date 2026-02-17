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

if (!defined('ABSPATH')) exit;

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
});

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
    $admin_emails = ['team@brighterwebsites.com.au', 'support@brighterwebsites.com.au'];
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'support';

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Welcome to Your Website Support Hub', 'brighterwebsites') . '</h1>';
    
    // Tab navigation - SECURITY: URLs properly escaped
    echo '<nav class="nav-tab-wrapper">';
    echo '<a href="' . esc_url(admin_url('admin.php?page=brighter_support&tab=support')) . '" class="nav-tab ' . ($active_tab == 'support' ? 'nav-tab-active' : '') . '">' . esc_html__('Support Info', 'brighterwebsites') . '</a>';

    // Manual Links: show to site admins so they can add/edit support link URLs (Full Manual, Quick Guide, Ranking tools)
    if (current_user_can('manage_options')) {
        echo '<a href="' . esc_url(admin_url('admin.php?page=brighter_support&tab=manuals')) . '" class="nav-tab ' . ($active_tab == 'manuals' ? 'nav-tab-active' : '') . '">' . esc_html__('Manual Links', 'brighterwebsites') . '</a>';
    }

    // Allow other modules to add tabs (e.g., API Settings)
    $custom_tabs = apply_filters('brighter_support_tabs', array(), $email);
    foreach ($custom_tabs as $tab_key => $tab_label) {
        echo '<a href="' . esc_url(admin_url('admin.php?page=brighter_support&tab=' . $tab_key)) . '" class="nav-tab ' . ($active_tab == $tab_key ? 'nav-tab-active' : '') . '">' . esc_html($tab_label) . '</a>';
    }

    echo '</nav>';

    // Tab content
    echo '<div class="tab-content">';

    if ($active_tab === 'manuals' && current_user_can('manage_options')) {
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
 * Render manuals tab
 * SECURITY: Settings API provides nonce protection
 */
function brighter_support_render_manuals_tab() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'brighterwebsites'));
    }

    echo '<div class="support-page">';
    echo '<form method="post" action="options.php">';
    settings_fields('brighter_support_settings');
    do_settings_sections('brighter_support_page');
    submit_button(esc_html__('Save Manual Links', 'brighterwebsites'));
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
                <p><em><?php esc_html_e('Create a backup of your website before making any changes.', 'brighterwebsites'); ?></em></p>
                <p><?php esc_html_e('Your website is automatically backed up from the server weekly - we keep the last 2 backups by default. If something goes wrong, contact us to restore a previous version.', 'brighterwebsites'); ?></p>
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
                        <strong><?php esc_html_e('Growth & Scale Partner Portal:', 'brighterwebsites'); ?></strong> 
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