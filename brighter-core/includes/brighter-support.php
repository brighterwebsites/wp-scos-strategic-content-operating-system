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

    if (current_user_can('manage_options')) {
        echo '<a href="' . esc_url(admin_url('admin.php?page=brighter_support&tab=business_info')) . '" class="nav-tab ' . ($active_tab == 'business_info' ? 'nav-tab-active' : '') . '">' . esc_html__('Business Info', 'brighterwebsites') . '</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=brighter_support&tab=optimisation')) . '" class="nav-tab ' . ($active_tab == 'optimisation' ? 'nav-tab-active' : '') . '">' . esc_html__('Optimisation', 'brighterwebsites') . '</a>';

        if (class_exists('Brighter_Tweaks')) {
            echo '<a href="' . esc_url(admin_url('admin.php?page=brighter_support&tab=tweaks')) . '" class="nav-tab ' . ($active_tab == 'tweaks' ? 'nav-tab-active' : '') . '">' . esc_html__('Brighter Tweaks', 'brighterwebsites') . '</a>';
        }
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
    } elseif ($active_tab === 'business_info' && current_user_can('manage_options')) {
        // SECURITY: Check if function exists before calling
        if (function_exists('brighterweb_render_business_info_form')) {
            brighterweb_render_business_info_form();
        } else {
            echo '<div class="support-page">';
            echo '<div class="notice notice-error"><p><strong>' . esc_html__('Error:', 'brighterwebsites') . '</strong> ' . esc_html__('Business Info module not loaded.', 'brighterwebsites') . '</p></div>';
            echo '<p>' . esc_html__('Debug info:', 'brighterwebsites') . '</p>';
            echo '<ul>';
            echo '<li>' . esc_html__('File exists:', 'brighterwebsites') . ' ' . (file_exists(BRIGHTER_CORE_PATH . 'includes/brighter-buinessinfo.php') ? '? YES' : '? NO') . '</li>';
            echo '<li>' . esc_html__('Function defined:', 'brighterwebsites') . ' ' . (function_exists('brighterweb_render_business_info_form') ? '? YES' : '? NO') . '</li>';
            echo '<li>' . esc_html__('Class exists:', 'brighterwebsites') . ' ' . (class_exists('Brighter_Business_Cache') ? '? YES' : '? NO') . '</li>';
            echo '</ul>';
            echo '</div>';
        }
    } elseif ($active_tab === 'optimisation' && current_user_can('manage_options')) {
        brighter_support_render_optimisation_tab();
    } elseif ($active_tab === 'tweaks' && current_user_can('manage_options')) {
        if (class_exists('Brighter_Tweaks')) {
            Brighter_Tweaks::render_page();
        } else {
            echo '<div class="support-page"><p>' . esc_html__('Tweaks module not available.', 'brighterwebsites') . '</p></div>';
        }
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
 * Render optimisation tab
 * SECURITY: Settings API provides nonce protection
 */
function brighter_support_render_optimisation_tab() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'brighterwebsites'));
    }
    
    echo '<div class="support-page">';
    echo '<form method="post" action="options.php">';
    settings_fields('brighter_optimisation_settings');
    do_settings_sections('brighter_optimisation_page');
    submit_button(esc_html__('Save Optimisation Settings', 'brighterwebsites'));
    echo '</form>';
    echo '</div>';
}

/**
 * Main support info tab content
 * SECURITY: All output properly escaped
 */
function brighter_support_output_main() {
    $site_url = get_site_url();
    $manual_full_link = esc_url(get_option('manual_full_link', '#'));
    $manual_quick_link = esc_url(get_option('manual_quick_link', '#'));
    $website_ranking_link = esc_url(get_option('website_ranking_link', '#'));
    $map_ranking_link = esc_url(get_option('map_ranking_link', '#'));
    $logo_url = BRIGHTER_CORE_URL . 'assets/brighter-logo.png';

    echo '<div class="support-page">';
    
    echo '<div class="support-desc">';
    echo '<p>' . esc_html__('We\'ve created this page to help you confidently manage and maintain your website. Below are quick-access links and tips to get you started.', 'brighterwebsites') . '</p>';
    echo '</div>';
    
    // Website Owners Manual
    echo '<div class="support-container support-manual">';
    echo '<h2>📖 ' . esc_html__('Website Owners Manual', 'brighterwebsites') . '</h2>';
    
    if ($manual_full_link && $manual_full_link !== '#') {
        echo '<div class="bright-manual"><a href="' . esc_url($manual_full_link) . '" target="_blank" rel="noopener">' . esc_html__('Website Manual', 'brighterwebsites') . '</a></div>';
    } else {
        echo '<div class="bright-manual"><strong>' . esc_html__('Full Manual:', 'brighterwebsites') . '</strong> ' . esc_html__('Coming Soon', 'brighterwebsites') . '</div>';
    }
    
    if ($manual_quick_link && $manual_quick_link !== '#') {
        echo '<div class="bright-manual"><a href="' . esc_url($manual_quick_link) . '" target="_blank" rel="noopener">' . esc_html__('Quick Guide', 'brighterwebsites') . '</a></div>';
    } else {
        echo '<div class="bright-manual"><strong>' . esc_html__('Quick Guide:', 'brighterwebsites') . '</strong> ' . esc_html__('Coming Soon', 'brighterwebsites') . '</div>';
    }
    echo '</div>'; // .support-container.support-manual

    // Manage Content
    echo '<div class="support-container support-brand">';
    echo '<h2>📝 ' . esc_html__('Manage Your Content', 'brighterwebsites') . '</h2>';
    echo '<table class="compare-table">';
    echo '<thead><tr><th><a href="' . esc_url(admin_url('edit.php')) . '">' . esc_html__('Posts', 'brighterwebsites') . '</a></th><th><a href="' . esc_url(admin_url('edit.php?post_type=page')) . '">' . esc_html__('Pages', 'brighterwebsites') . '</a></th></tr></thead>';
    echo '<tbody>';
    echo '<tr><td>' . esc_html__('Appear in blog feed', 'brighterwebsites') . '</td><td>' . esc_html__('Stand-alone content (like About or Contact)', 'brighterwebsites') . '</td></tr>';
    echo '<tr><td>' . esc_html__('Organised by date, category, tags', 'brighterwebsites') . '</td><td>' . esc_html__('Organised hierarchically (parent/child)', 'brighterwebsites') . '</td></tr>';
    echo '<tr><td>' . esc_html__('Ideal for regular updates', 'brighterwebsites') . '</td><td>' . esc_html__('Best for timeless content', 'brighterwebsites') . '</td></tr>';
    echo '</tbody>';
    echo '</table>';
    echo '</div>'; // .support-container.support-brand

    // Need Help
    echo '<div class="support-container support-brand">';
    echo '<div class="support-help">';
    echo '<div class="support-help-inner">';
    echo '<img class="support-img" src="' . esc_url($logo_url) . '" alt="' . esc_attr__('Support', 'brighterwebsites') . '">';
    echo '<h2>💬 ' . esc_html__('Need Help?', 'brighterwebsites') . '</h2>';
    echo '</div>'; // .support-help-inner
    echo '<p>' . wp_kses_post(__('We\'re here to help with technical issues, updates, or questions. Email us directly at <a href="mailto:support@brighterwebsites.com.au">support@brighterwebsites.com.au</a>', 'brighterwebsites')) . '</p>';
    echo '<div class="bright-button"><a href="https://brighterwebsites.com.au/kb/" target="_blank" rel="noopener">' . esc_html__('Website Knowledge Base', 'brighterwebsites') . '</a></div>';
    echo '</div>'; // .support-help
    echo '</div>'; // .support-container.support-brand

    // Performance & Search
    echo '<div class="support-tips">';
    echo '<div class="support-container support-search">';
    echo '<h2>🔍 ' . esc_html__('Performance & Search', 'brighterwebsites') . '</h2>';
    echo '<p>' . esc_html__('If we set these up as part of your website package, you\'ll find your account details in your Website Manual.', 'brighterwebsites') . '</p>';
    echo '<ul>';
    echo '<li><strong>' . esc_html__('Google Search Console:', 'brighterwebsites') . '</strong> <a href="https://search.google.com/search-console" target="_blank" rel="noopener">' . esc_html__('Manage Search Performance', 'brighterwebsites') . '</a></li>';
    echo '<li><strong>' . esc_html__('AHREFS SEO Health:', 'brighterwebsites') . '</strong> <a href="https://app.ahrefs.com/site-audit" target="_blank" rel="noopener">' . esc_html__('Site Audit', 'brighterwebsites') . '</a></li>';
    echo '<li><strong>' . esc_html__('Website Visitors:', 'brighterwebsites') . '</strong> <a href="https://analytics.google.com" target="_blank" rel="noopener">' . esc_html__('Google Analytics', 'brighterwebsites') . '</a></li>';
    echo '<li><strong>' . esc_html__('Check Speed:', 'brighterwebsites') . '</strong> <a href="https://pagespeed.web.dev" target="_blank" rel="noopener">' . esc_html__('PageSpeed Insights', 'brighterwebsites') . '</a></li>';
    
    if ($website_ranking_link && $website_ranking_link !== '#') {
        echo '<li><strong>' . esc_html__('SEO Website Ranking Report:', 'brighterwebsites') . '</strong> <a href="' . esc_url($website_ranking_link) . '" target="_blank" rel="noopener">' . esc_html__('Open Tool', 'brighterwebsites') . '</a></li>';
    }
    
    if ($map_ranking_link && $map_ranking_link !== '#') {
        echo '<li><strong>' . esc_html__('SEO Map Ranking Report:', 'brighterwebsites') . '</strong> <a href="' . esc_url($map_ranking_link) . '" target="_blank" rel="noopener">' . esc_html__('Open Tool', 'brighterwebsites') . '</a></li>';
    }
    echo '</ul>';
    echo '</div>'; // .support-container.support-search

    // Recommended Tools
    echo '<div class="support-container support-tools">';
    echo '<p><strong>🛠️ ' . esc_html__('Recommended Tools', 'brighterwebsites') . '</strong> – ' . esc_html__('If these tools have been set up for you, you\'ll find the login details in your Website Owner Manual.', 'brighterwebsites') . '</p>';
    echo '<ul>';
    echo '<li><strong>' . esc_html__('Email Campaigns:', 'brighterwebsites') . '</strong> <a href="https://www.mailerlite.com/invite/e74a69700df56/" target="_blank" rel="noopener">MailerLite</a></li>';
    echo '<li><strong>' . esc_html__('SMS Marketing:', 'brighterwebsites') . '</strong> <a href="https://www.smsglobal.com/" target="_blank" rel="noopener">SMSGlobal</a></li>';
    echo '<li><strong>' . esc_html__('Social Media Management:', 'brighterwebsites') . '</strong> <a href="https://www.postly.ai/" target="_blank" rel="noopener">Postly</a></li>';
    echo '<li><strong>' . esc_html__('Content copywriter:', 'brighterwebsites') . '</strong> <a href="https://app.neuronwriter.com/ar/98d2833da3de4ac1cc524b8864cf1241/" target="_blank" rel="noopener">Neuronwriter</a></li>';
    echo '</ul>';
    echo '</div>'; // .support-container.support-tools

    // Website Health (admin only)
    if (current_user_can('manage_options')) {
        echo '<div class="support-container support-health">';
        echo '<p>⚙️ <strong>' . esc_html__('Admin Tools', 'brighterwebsites') . '</strong> - ' . esc_html__('Log in as admin to access website health.', 'brighterwebsites') . '</p>';
        echo '<ul>';
        echo '<li><strong>' . esc_html__('Check Website Health:', 'brighterwebsites') . '</strong> <a href="' . esc_url(admin_url('site-health.php')) . '">' . esc_html__('Site Health Tool', 'brighterwebsites') . '</a></li>';
        echo '<li><strong>' . esc_html__('Backups:', 'brighterwebsites') . '</strong> <a href="' . esc_url(admin_url('admin.php?page=WPvivid')) . '">' . esc_html__('Go to Backups', 'brighterwebsites') . '</a></li>';
        echo '</ul>';
        echo '<p>*' . esc_html__('Your website is automatically backed up monthly. If something goes wrong, contact us to restore a previous version.', 'brighterwebsites') . '</p>';
        echo '</div>'; // .support-container.support-health
    }
    
    echo '</div>'; // .support-tips
    echo '</div>'; // .support-page
}