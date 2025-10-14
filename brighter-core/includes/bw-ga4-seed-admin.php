<?php
/**
 * Add to your theme's functions.php
 * 
 * Handles seeding completion flag and provides admin interface
 */

// Include the seeder file
require_once get_template_directory() . '/includes/analytics-seeder.php';

// AJAX handler to mark seeding as complete
add_action('wp_ajax_brighter_ga4_seed_complete', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // Store flag for 90 days (prevents accidental re-seeding)
    set_transient('brighter_ga4_events_seeded', true, 90 * DAY_IN_SECONDS);
    set_transient('brighter_ga4_seed_date', current_time('mysql'), 90 * DAY_IN_SECONDS);
    
    wp_send_json_success([
        'message' => 'GA4 events seeded successfully',
        'date' => current_time('mysql')
    ]);
});

// Add admin notice when seeding is needed
add_action('admin_notices', function() {
    // Only show on main dashboard
    $screen = get_current_screen();
    if ($screen->id !== 'dashboard') return;
    
    // Don't show if already seeded
    if (get_transient('brighter_ga4_events_seeded')) return;
    
    // Only show to admins
    if (!current_user_can('manage_options')) return;
    
    ?>
    <div class="notice notice-warning is-dismissible">
        <h3>?? GA4 Event Setup Required</h3>
        <p>
            <strong>Your GA4 tracking needs initial event registration.</strong><br>
            This is a <strong>one-time setup</strong> for low-traffic sites to enable immediate conversion tracking.
        </p>
        <p>
            <a href="<?php echo home_url('/?seedEvents=true'); ?>" 
               class="button button-primary" 
               target="_blank">
                Seed GA4 Events Now
            </a>
            <a href="https://support.google.com/analytics/answer/12229021" 
               class="button button-secondary" 
               target="_blank">
                Learn About GA4 Events
            </a>
        </p>
        <p style="font-size: 12px; color: #666;">
            <em>This will register 25+ event names in GA4 so you can mark conversions immediately. 
            Safe for production - creates zero-value test events that are clearly labeled.</em>
        </p>
    </div>
    <?php
});

// Optional: Add to admin menu for easy re-access
add_action('admin_menu', function() {
    add_submenu_page(
        'options-general.php',
        'GA4 Event Seeder',
        'GA4 Events',
        'manage_options',
        'brighter-ga4-seeder',
        function() {
            $seeded = get_transient('brighter_ga4_events_seeded');
            $date = get_transient('brighter_ga4_seed_date');
            ?>
            <div class="wrap">
                <h1>?? GA4 Event Seeder</h1>
                
                <?php if ($seeded): ?>
                    <div class="notice notice-success inline">
                        <h2>? Events Already Seeded</h2>
                        <p>Your GA4 events were registered on <strong><?php echo $date; ?></strong></p>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=brighter-ga4-seeder&reset=1'); ?>" 
                               class="button" 
                               onclick="return confirm('Are you sure? This will allow re-seeding.');">
                                Reset Seeding Flag
                            </a>
                        </p>
                    </div>
                    
                    <hr>
                    
                    <h2>?? What's Tracked</h2>
                    <p>Your site is tracking these conversion events:</p>
                    
                    <table class="widefat" style="max-width: 800px;">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Category</th>
                                <th>Purpose</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><code>click_meeting</code></td><td>Meetings</td><td><strong>High-value conversion</strong></td></tr>
                            <tr><td><code>generate_lead</code></td><td>Forms</td><td><strong>Primary conversion</strong></td></tr>
                            <tr><td><code>form_submit</code></td><td>Forms</td><td><strong>Primary conversion</strong></td></tr>
                            <tr><td><code>click_main_cta</code></td><td>Quote</td><td><strong>Conversion intent</strong></td></tr>
                            <tr><td><code>get_lead_magnet</code></td><td>Lead Magnet</td><td>Lead generation</td></tr>
                            <tr><td><code>click_phone</code></td><td>Contact</td><td>Direct contact</td></tr>
                            <tr><td><code>click_email</code></td><td>Contact</td><td>Direct contact</td></tr>
                            <tr><td><code>view_pricing</code></td><td>Trust</td><td>Purchase consideration</td></tr>
                            <tr><td><code>subscribe</code></td><td>Subscribe</td><td>Lead nurture</td></tr>
                            <tr><td colspan="3" style="text-align: center;"><em>+ 15 more engagement events</em></td></tr>
                        </tbody>
                    </table>
                    
                    <hr>
                    
                    <h2>?? Next Steps</h2>
                    <ol style="line-height: 2;">
                        <li>Go to GA4 ? <strong>Admin ? Events</strong></li>
                        <li>Find events listed above (they should appear within 24 hours)</li>
                        <li>Click "Mark as conversion" for high-value events</li>
                        <li>Set up <strong>attribution models</strong> in GA4 ? Admin ? Attribution Settings</li>
                        <li>Create <strong>conversion funnels</strong> in Explorations</li>
                    </ol>
                    
                    <div class="notice notice-info inline">
                        <p><strong>?? Pro Tip:</strong> With only 3-7 visitors/week, use GA4's "Model Comparison" 
                        in Attribution to understand which touchpoints matter most for your rare conversions.</p>
                    </div>
                    
                <?php else: ?>
                    <div class="notice notice-warning inline">
                        <h2>?? Events Not Yet Seeded</h2>
                        <p>Your GA4 event taxonomy hasn't been initialized yet.</p>
                        <p>
                            <a href="<?php echo home_url('/?seedEvents=true'); ?>" 
                               class="button button-primary button-hero" 
                               target="_blank">
                                ?? Seed Events Now
                            </a>
                        </p>
                    </div>
                    
                    <hr>
                    
                    <h2>Why Seed Events?</h2>
                    <p>With <strong>3-7 visitors per week</strong>, natural event registration could take months:</p>
                    <ul style="line-height: 2;">
                        <li>? First form submission might not happen for 4-8 weeks</li>
                        <li>? Meeting bookings could be 2-3 months apart</li>
                        <li>? Can't set up conversions or attribution until events fire</li>
                        <li>? Seeding registers all events <strong>immediately</strong></li>
                    </ul>
                    
                    <h3>What Happens When You Seed?</h3>
                    <ol style="line-height: 2;">
                        <li>Script fires each event <strong>once</strong> with zero-value test data</li>
                        <li>GA4 registers event names within 30 seconds</li>
                        <li>Events appear in Admin ? Events within 24 hours</li>
                        <li>You can mark conversions and build funnels immediately</li>
                        <li>All seed events are clearly labeled and filterable</li>
                    </ol>
                <?php endif; ?>
                
                <hr>
                
                <h2>?? Filter Seed Events in Reports</h2>
                <p>To exclude seed data from reports, use this filter:</p>
                <pre style="background: #f5f5f5; padding: 10px; border-left: 4px solid #4CAF50;">
page_path does not contain "/admin/seed-events"
OR
event_label does not contain "[SEED]"</pre>
            </div>
            <?php
        }
    );
});

// Handle reset flag
add_action('admin_init', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'brighter-ga4-seeder' && isset($_GET['reset'])) {
        if (!current_user_can('manage_options')) return;
        
        delete_transient('brighter_ga4_events_seeded');
        delete_transient('brighter_ga4_seed_date');
        
        wp_redirect(admin_url('admin.php?page=brighter-ga4-seeder'));
        exit;
    }
});