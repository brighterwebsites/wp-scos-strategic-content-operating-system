<?php
/**
 * Brighter Tools: Support
 *
 * File: brighter-support.php
 * Purpose: Core support features for client sites.
 *
 * Version: 4.0.0
 *
 * Responsibilities:
 * - Brighter Tools: Support Page & Login Styling
 * - Design Credit Hook  
 * - Footer branding: comment and visible credit link
 * - Brighter Support Admin Page with Tabs and Dynamic Manual Links
 *
 * Notes:
 * - Part of the Brighter Support Tools for Client Sites MU plugin 
 * - Loaded automatically by /mu-plugins/brighter-core.php
 */


if ( ! defined( 'ABSPATH' ) ) exit;

// Design Credit Hook  
// Head markup: JSON-LD only (no non-standard meta in body)
add_action( 'wp_head', function () {
    if ( is_admin() || is_feed() || ( defined('REST_REQUEST') && REST_REQUEST ) ) {
        return;
    }

    $site_name = get_bloginfo( 'name' );
    $site_url  = home_url( '/' );

    // If your SEO plugin already outputs WebSite schema, avoid duplication:
    // if ( function_exists('seopress') ) { return; }

    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'WebSite',
        'name'       => $site_name,
        'url'        => $site_url,
        'publisher'  => [
            '@type' => 'Organization',
            'name'  => 'Brighter Websites',
            'url'   => 'https://brighterwebsites.com.au',
        ],
    ];

    echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}, 20 );

// Footer branding: comment and visible credit link
add_action( 'wp_footer', function () {
    if ( is_admin() || is_feed() || ( defined('REST_REQUEST') && REST_REQUEST ) ) {
        return;
    }

    // Hidden attribution comment
    echo "\n<!-- Website built by Brighter Websites - https://brighterwebsites.com.au -->\n";
}, 99 );

// Shortcode: [brighter_credit hide_on_posts="yes"]
function brighter_credit_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'hide_on_posts' => 'yes',
    ], $atts, 'brighter_credit' );

    // Hide on single blog posts, if requested
    if ( 'yes' === strtolower( $atts['hide_on_posts'] ) && is_single() && get_post_type() === 'post' ) {
        return '';
    }

    $utm_source = sanitize_title( get_bloginfo( 'name' ) );
    $url = add_query_arg( [
        'utm_source'   => $utm_source,
        'utm_medium'   => 'footer',
        'utm_campaign' => 'site-credit',
    ], 'https://brighterwebsites.com.au/' );

    return sprintf(
        'Proudly Built by <a href="%s" target="_blank" rel="noopener"><strong>BRIGHTER WEBSITES</strong></a>',
        esc_url( $url )
    );
}
add_shortcode( 'brighter_credit', 'brighter_credit_shortcode' );



// Brighter Support Admin Page with Tabs and Dynamic Manual Links

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


// Render CSS
function brighter_enqueue_admin_support_styles($hook) {
    if (strpos($hook, 'brighter_support') !== false) {
        wp_enqueue_style('brighter-admin-support', plugin_dir_url(__FILE__) . '../css/admin-support.css');
    }
}
add_action('admin_enqueue_scripts', 'brighter_enqueue_admin_support_styles');



function brighter_support_render_page() {
    $current_user = wp_get_current_user();
    $email = $current_user->user_email;
    $admin_emails = ['team@brighterwebsites.com.au', 'support@brighterwebsites.com.au'];
    $active_tab = $_GET['tab'] ?? 'support';

    echo '<div class="wrap">';
    echo '<h1>Welcome to Your Website Support Hub</h1>';
    echo '<nav class="nav-tab-wrapper">';
    echo '<a href="?page=brighter_support&tab=support" class="nav-tab ' . ($active_tab == 'support' ? 'nav-tab-active' : '') . '">Support Info</a>';
    if (in_array($email, $admin_emails)) {
        echo '<a href="?page=brighter_support&tab=manuals" class="nav-tab ' . ($active_tab == 'manuals' ? 'nav-tab-active' : '') . '">Manual Links</a>';
    }
    echo '<a href="?page=brighter_support&tab=business_info" class="nav-tab ' . ($active_tab == 'business_info' ? 'nav-tab-active' : '') . '">Business Info</a>';
    
    echo '<a href="?page=brighter_support&tab=optimisation" class="nav-tab ' . ($active_tab == 'optimisation' ? 'nav-tab-active' : '') . '">Optimisation</a>';
    // Add Tweaks tab link after Optimisation but before closing the nav wrapper
    echo '<a href="?page=brighter_support&tab=tweaks" class="nav-tab ' . ($active_tab == 'tweaks' ? 'nav-tab-active' : '') . '">Brighter Tweaks</a>';
    echo '</nav>';


    // Render the selected tab's content
    if ($active_tab === 'manuals' && in_array($email, $admin_emails)) {
        echo '<div class="support-page">';
        echo '<form method="post" action="options.php">';
        settings_fields('brighter_support_settings');
        do_settings_sections('brighter_support_page');
        submit_button('Save Manual Links');
        echo '</form>';
        echo '</div>';
    } elseif ($active_tab === 'business_info' && current_user_can('manage_options')) {
        brighterweb_render_business_info_form();
    } elseif ($active_tab === 'optimisation' && current_user_can('manage_options')) {
        echo '<div class="support-page">';
        echo '<form method="post" action="options.php">';
        settings_fields('brighter_optimisation_settings');
        do_settings_sections('brighter_optimisation_page');
        submit_button('Save Optimisation Settings');
        echo '</form>';
        echo '</div>';
    } elseif ($active_tab === 'tweaks' && current_user_can('manage_options')) {
        // Render the Tweaks tab without using the Settings API. The Tweaks page handles its own saving.
        Brighter_Tweaks::render_page();
    } else {
        brighter_support_output_main();
    }


    echo '</div>';
}





function brighter_support_output_main() {
    $site_url = site_url();
    $manual_full_link = esc_url(get_option('manual_full_link', '#'));
    $manual_quick_link = esc_url(get_option('manual_quick_link', '#'));
    $website_ranking_link = esc_url(get_option('website_ranking_link', '#'));
    $map_ranking_link = esc_url(get_option('map_ranking_link', '#'));
	$logo_url = plugin_dir_url(__FILE__) . '../assets/brighter-logo.png';
	$site_url = get_site_url();


    echo '<div class="support-page">';
    
    echo '<div class="support-desc">';
        echo '<p>Weve created this page to help you confidently manage and maintain your website. Below are quick-access links and tips to get you started.</p>';
    echo '</div>';
    
    echo '<div class="support-container support-manual">';
        echo '<h2>ℹ️ Website Owners Manual</h2>';
        
         // Website Owners Manual
        if ($manual_full_link && $manual_full_link !== '#') {
         
            echo '<div class="bright-manual"><a href="' . $manual_full_link . '" target="_blank">Website Manual</a></div>';
            
        } else {
            echo '<div class="bright-manual">Full Manual:</strong> Coming Soon</div>';
            
        }
        if ($manual_quick_link && $manual_quick_link !== '#') {
          echo '<div class="bright-manual"><a href="' . $manual_quick_link . '" target="_blank">Quick Guide</a></div>';
        } else {
            echo '<div class="bright-manual"><strong>Quick Guide:</strong> Coming Soon</div>';
            
        }
       
    echo '</div>';


echo '<div class="support-container support-brand">';


        echo '<h2>📝 Manage Your Content</h2>';
       
        echo '<table class="compare-table">';
            echo '<thead><tr><th><a href="' . $site_url . '/wp-admin/edit.php" target="_blank">Posts</a></th><th><a href="' . $site_url . '/wp-admin/edit.php?post_type=page" target="_blank">Pages</a></th></tr></thead>';
            echo '<tbody>';
                echo '<tr><td>Appear in blog feed</td><td>Stand-alone content (like About or Contact)</td></tr>';
                echo '<tr><td>Organised by date, category, tags</td><td>Organised hierarchically (parent/child)</td></tr>';
                echo '<tr><td>Ideal for regular updates</td><td>Best for timeless content</td></tr>';
            echo '</tbody>';
        echo '</table>';



echo '</div>';
 
    
    
    echo '<div class="support-container support-brand">';
        
             
            
            echo '<div class="support-help">';
                echo '<div class="support-help-inner">';
                    echo '<img class="support-img" src="' . esc_url($logo_url) . '" alt="Support">';
                    echo '<h2>🧰 Need Help?</h2>';
                echo '</div>';
                echo '<p>We’re here to help with technical issues, updates, or questions. Email us directly at <a href="mailto:support@brighterwebsites.com.au">support@brighterwebsites.com.au</a</p>';
               
                echo '<div class="bright-button"><a href="https://brighterwebsites.com.au/kb/" target="_blank">Website Knowledge Base</a></div>';
            echo '</div>';
        
            echo '<div class="support-tips">';
               
   echo '<div class="support-container support-search">';
        echo '<h2>📈 Performance & Search</h2>';
      echo '<p>If we set these up as part of your website package, you’ll find your account details in your Website Manual.</p>';

        echo '<ul>';
        echo '<li><strong>Google Search Console:</strong> <a href="https://search.google.com/search-console" target="_blank">Manage Search Performance</a></li>';
         echo '<li><strong>AHREFS SEO Health:</strong> <a href="https://app.ahrefs.com/site-audit" target="_blank">Site Audit</a></li>';
        echo '<li><strong>Website Visitors:</strong> <a href="https://app.ahrefs.com/site-audit" target="_blank">Google Analytics</a></li>';
        echo '<li><strong>Check Speed:</strong> <a href="https://pagespeed.web.dev" target="_blank">PageSpeed Insights</a></li>';
        echo '</ul>';
        
        // Ranks Pro - Website Ranking
        if ($website_ranking_link && $website_ranking_link !== '#') {
            echo '<li><strong>SEO Website Ranking Report:</strong> <a href="' . $website_ranking_link . '" target="_blank">Open Tool</a></li>';
        } else {
            echo '<li><strong>SEO Website Ranking Report:</strong> Request <a href="mailto:support@brighterwebsites.com.au">support@brighterwebsites.com.au</a></li>';
        }
        // Ranks Pro - Map Ranking
        if ($map_ranking_link && $map_ranking_link !== '#') {
            echo '<li><strong>SEO Map Ranking Report:</strong> <a href="' . $map_ranking_link . '" target="_blank">Open Tool</a></li>';
        } else {
            echo '<li><strong>SEO Map Ranking Report:</strong> Request <a href="mailto:support@brighterwebsites.com.au">support@brighterwebsites.com.au</a></li>';
        }
    echo '</div>';
 
   
   
    echo '<div class="support-container support-tools">';
       
       echo '<p><strong>📣 Recommended Tools</strong> – If these tools have been set up for you, you’ll find the login details in your <strong>Website Owner Manual</strong>.</p>';

        echo '<ul>';
        echo '<li><strong>Email Campaigns:</strong> <a href="https://www.mailerlite.com/invite/e74a69700df56/" target="_blank">MailerLite</a></li>';
        echo '<li><strong>SMS Marketing:</strong> <a href="https://www.smsglobal.com/" target="_blank">SMSGlobal</a></li>';
        echo '<li><strong>Social Media Management:</strong> <a href="https://www.postly.ai/" target="_blank">Postly</a></li>';
        echo '<li><strong>Content copywriter:</strong> <a href="https://app.neuronwriter.com/ar/98d2833da3de4ac1cc524b8864cf1241/" target="_blank">Neuronwriter</a></li>';

        echo '</ul>';
    echo '</div>';
    
  
    
        echo '<div class="support-container support-health">';
  
        echo '<p>🔒 <strong>Admin Tools</strong> - Log in as admin to access website health.</p>';
        
        echo '<ul>';
        echo '<li><strong>Check Website Health:</strong> <a href="' . $site_url . '/wp-admin/site-health.php" target="_blank">Site Health Tool</a></li>';
       echo '<li><strong>Backups:</strong> <a href="' . $site_url . '/wp-admin/admin.php?page=WPvivid" target="_blank">Go to Backups</a></li>';
        echo '</ul>';
        echo '<p>*Your website is automatically backed up monthly. If something goes wrong, contact us to restore a previous version.</p>';
    echo '</div>';
         
     
   
}

