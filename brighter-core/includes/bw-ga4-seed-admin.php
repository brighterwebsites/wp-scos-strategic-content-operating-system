<?php
/**
 * GA4 Analytics Admin Interface
 *
 * Adds Analytics submenu under Brighter Support with 3 tabs:
 * - Configuration
 * - Content Strategy Tracking
 * - Seeding
 */

if (!defined('ABSPATH')) exit;

// Add Analytics submenu to Brighter Support — hidden when site-essentials Analytics module is active
add_action('admin_menu', function() {
    if ( defined( 'SCOS_ANALYTICS_ACTIVE' ) ) { return; }
    add_submenu_page(
        'brighter_support',           // Parent slug
        'Analytics',                  // Page title
        'Analytics',                  // Menu title
        'manage_options',             // Capability
        'brighter-analytics',         // Menu slug
        'brighter_analytics_render_page', // Callback
        2                             // Position (above cache test)
    );
});

// Render the Analytics page with tabs
function brighter_analytics_render_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Get current tab
    $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'configuration';

    // Handle form submission for Configuration tab
    if (isset($_POST['brighter_ga4_settings_nonce']) &&
        wp_verify_nonce($_POST['brighter_ga4_settings_nonce'], 'brighter_ga4_settings')) {

        if (isset($_POST['ga4_measurement_id'])) {
            update_option('brighter_ga4_measurement_id', sanitize_text_field($_POST['ga4_measurement_id']));
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved!</p></div>';
        }
    }

    $ga4_id = get_option('brighter_ga4_measurement_id', '');
    $seeded = get_transient('brighter_ga4_events_seeded');
    $seed_date = get_transient('brighter_ga4_seed_date');

    ?>
    <div class="wrap">
        <h1>📊 Analytics</h1>

        <!-- Tabs -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=brighter-analytics&tab=configuration"
               class="nav-tab <?php echo $current_tab === 'configuration' ? 'nav-tab-active' : ''; ?>">
                Configuration
            </a>
            <a href="?page=brighter-analytics&tab=content-strategy"
               class="nav-tab <?php echo $current_tab === 'content-strategy' ? 'nav-tab-active' : ''; ?>">
                Content Strategy Tracking
            </a>
            <a href="?page=brighter-analytics&tab=seeding"
               class="nav-tab <?php echo $current_tab === 'seeding' ? 'nav-tab-active' : ''; ?>">
                Seeding
            </a>
        </h2>

        <div style="margin-top: 20px;">
            <?php if ($current_tab === 'configuration'): ?>
                <!-- TAB 1: Configuration -->
                <h2>Google Analytics Settings</h2>
                <p>Configure your Google Analytics 4 (GA4) tracking settings.</p>

                <form method="post">
                    <?php wp_nonce_field('brighter_ga4_settings', 'brighter_ga4_settings_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ga4_measurement_id">GA4 Measurement ID</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="ga4_measurement_id"
                                       name="ga4_measurement_id"
                                       value="<?php echo esc_attr($ga4_id); ?>"
                                       class="regular-text"
                                       placeholder="G-XXXXXXXXXX">
                                <p class="description">
                                    Your GA4 Measurement ID (e.g., G-ABC123DEF4).
                                    Find this in GA4 → Admin → Data Streams → Web Stream Details.
                                </p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Save Settings'); ?>
                </form>

                <hr>

                <h3>🎯 Current Tracking Setup</h3>
                <p><strong>Status:</strong> <?php echo $ga4_id ? '✅ GA4 tracking configured' : '⚠️ No GA4 ID set'; ?></p>
                <p><strong>Content TrackingStatus:</strong> ✅ Australian Busienss GA4 Consent not rquired. </p>
                <p>'✅ Consent Settings Configures in GA4 - Admin / Data Collection and modifications / Consent settings</p>
                <p>'✅ Data Collection Settings Configures in GA4 - Admin / Data Collection and modifications / Data Collection</p>
                <p>'✅ GA4 DPA Administration Configured https://marketingplatform.google.com/gdpr </p>    
                <h4>What's Being Tracked:</h4>
                <ul style="line-height: 1.8;">
                    <li>📄 <strong>Page views</strong> with content strategy metadata</li>
                    <li>🎯 <strong>CTA clicks</strong> (main, micro, menu cta, assists)</li>
                    <li>🎯 <strong>Contact Clicks</strong> (phone, email)</li>
                    <li>📝 <strong>Form interactions</strong> (start, submit, lead generation)</li>
                    <li>📝 <strong>Form Lead Generation</strong> (cold, warm, hot)</li>
                    <li>🎓 <strong>Lead magnets</strong> (downloads, guides)</li>
                    <li>👁️ <strong>Trust signals</strong> (reviews, pricing, case studies, videos)</li>
                    <li>📊 <strong>Navigation</strong> (blog, portfolio, products, services)</li>
                    <li>📍 <strong>Page hierarchy</strong> (Above the fold, mid & final CTA sections)</li>
                   
                </ul>

                <h4>Tracking Files:</h4>
                <ul>
                    <li><code>/mu-plugins/brighter-ga4-tracking.php</code> - GA4 loader & consent handling</li>
                    <li><code>/mu-plugins/brighter-core/js/brighter-ga4-enhanced.js</code> - Event tracking logic</li>
                    <li><code>/mu-plugins/brighter-core/includes/scos-car-injection.php</code> - SCOS CAR metadata injection ⭐ NEW</li>
                </ul>

                <div class="notice notice-info inline" style="margin-top: 20px;">
                    <p><strong>🆕 Recent Update:</strong> Content metadata injection has been consolidated into a single
                    <code>scos-car-injection.php</code> file for better AI readability and maintainability. This creates
                    a machine-readable Content Architecture Record (CAR) that both GA4 and AI agents can parse.</p>
                </div>

            <?php elseif ($current_tab === 'content-strategy'): ?>
                <!-- TAB 2: Content Strategy Tracking -->
                <h2>Content Strategy Tracking</h2>
                <p>Your GA4 tracking automatically includes custom dimensions on every page view.</p>

                <div class="notice notice-info inline">
                    <p><strong>ℹ️ How It Works:</strong> Each page/post in WordPress has content strategy metadata
                    (set in the editor sidebar). This metadata is automatically sent to GA4 with every event,
                    allowing you to analyze performance by content type, intent, and topic.</p>
                </div>

                <h3>Custom Dimensions Sent to GA4</h3>
                <table class="wp-list-table widefat striped" style="max-width: 900px; margin-top: 20px;">
                    <thead>
                        <tr>
                            <th>Parameter Name</th>
                            <th>Description</th>
                            <th>Example Values</th>
                        </tr>
                    </thead>
                    <tbody>

            
               

                        <tr>
                            <td><code>post_type</code></td>
                            <td>WordPress post type</td>
                            <td>page, post, project, etc.</td>
                        </tr>
                  
                        <tr style="background-color: #e8f5e9;">
                            <td><code>altc_primary</code></td>
                            <td><strong>ALTC Strategic Lens</strong> (Authority cluster)</td>
                            <td>AI-First SEO, Conversion Optimization, etc.</td>
                        </tr>
                        <tr style="background-color: #e8f5e9;">
                            <td><code>altc_topic</code></td>
                            <td><strong>ALTC Topic</strong> (Specific focus area)</td>
                            <td>Content Strategy, Technical SEO, UX Design, etc.</td>
                        </tr>
                        <tr>
                            <td><code>content_intent</code></td>
                            <td>User search intent for this page</td>
                            <td>informational, commercial, transactional, navigational</td>
                        </tr>
                        <tr>
                            <td><code>content_purpose</code></td>
                            <td>Content role in your strategy</td>
                            <td>pillar, service-page, supporting, case-study, conversion-hub</td>
                        </tr>

                        <tr>
                            <td><code>pillar_page</code></td>
                            <td>Parent pillar/service page</td>
                            <td>Page title of linked pillar</td>
                        </tr>
                        <tr>
                            <td><code>pillar_type</code></td>
                            <td>Type of parent</td>
                            <td>pillar, service, none</td>
                        </tr>
                        <tr>
                            <td><code>service_pathway</code></td>
                            <td>Service/Product pathway page</td>
                            <td>Page title of linked service pathway</td>
                        </tr>
                        <tr>
                            <td><code>content_plan</code></td>
                            <td>Content workflow status</td>
                            <td>approve, testing, revise, merge, archive</td>
                        </tr>

                        <tr style="background-color: #e8f5e9;">
                            <td><code>content_maturity</code></td>
                            <td><strong>Content Maturity Level</strong></td>
                            <td>beginner, intermediate, expert, advanced</td>
                        </tr>
                        <tr style="background-color: #fff3cd;">
                            <td><code>lead_tier</code></td>
                            <td><strong>Lead Quality Tier</strong> (Form-based)</td>
                            <td>hot, warm, cold, unknown</td>
                        </tr>
                        <tr style="background-color: #fff3cd;">
                            <td><code>lead_type</code></td>
                            <td><strong>Lead Type</strong> (Form-based)</td>
                            <td>quote_request, contact_form, newsletter, etc.</td>
                        </tr>
                        <tr style="background-color: #fff3cd;">
                            <td><code>cta_label</code></td>
                            <td><strong>CTA Label</strong> (Form context)</td>
                            <td>Text of CTA clicked before form submission</td>
                        </tr>
                        <tr style="background-color: #fff3cd;">
                            <td><code>cta_location</code></td>
                            <td><strong>CTA Location</strong> (Form context)</td>
                            <td>Section where CTA was located (header, footer, atf, etc.)</td>
                        </tr>
                        <tr style="background-color: #fff3cd;">
                            <td><code>cta_type</code></td>
                            <td><strong>CTA Type</strong> (Form context)</td>
                            <td>main, micro, assist, unknown</td>
                        </tr>
                    </tbody>
                </table>

                <div class="notice notice-success inline" style="margin-top: 20px;">
                    <p><strong>🆕 ALTC Dimensions (Green Rows):</strong> Authority-Led Topic Clusters (ALTC) parameters 
                    help track how your strategic content clusters perform. These allow you to measure the effectiveness
                    of your authority positioning and topic targeting strategy.</p>
                </div>
                
                <div class="notice notice-info inline" style="margin-top: 15px;">
                    <p><strong>📝 Form Context Dimensions (Yellow Rows):</strong> Lead tier, lead type, and CTA context 
                    (cta_label, cta_location, cta_type) are automatically captured on form submissions. These help you 
                    understand which CTAs and content sections drive conversions. <strong>Note:</strong> CTA dimensions are 
                    only sent with <code>form_submit</code> and <code>generate_lead</code> events.</p>
                </div>

                <hr style="margin: 30px 0;">

                <h3>📈 How to Use These Dimensions</h3>

                <h4>1. Register Custom Dimensions in GA4</h4>
                <ol style="line-height: 1.8;">
                    <li>Go to GA4 → <strong>Admin → Custom Definitions → Create Custom Dimension</strong></li>
                    <li>For each parameter above, create a dimension:
                        <ul>
                            <li><strong>Dimension name:</strong> Content Intent (user-friendly name)</li>
                            <li><strong>Scope:</strong> Event</li>
                            <li><strong>Event parameter:</strong> content_intent (exact parameter name)</li>
                        </ul>
                    </li>
                    <li>Repeat for all parameters</li>
                </ol>

                <div class="notice notice-info inline" style="margin-top: 15px;">
                    <p><strong>💡 Pro Tip:</strong> Prioritize the ALTC dimensions (altc_primary, altc_topic, content_maturity)
                    if you're using the Authority-Led Topic Clusters strategy. These provide powerful insights into how your
                    strategic positioning affects conversions and engagement.</p>
                </div>

                <h4>2. Use in Reports & Explorations</h4>
                <ul style="line-height: 1.8;">
                    <li><strong>Analyze by intent:</strong> See which content types drive conversions</li>
                    <li><strong>Topic performance:</strong> Compare engagement across topics</li>
                    <li><strong>Pillar effectiveness:</strong> Measure supporting content impact</li>
                    <li><strong>Optimization ROI:</strong> Track before/after optimization changes</li>
                </ul>

                <h4>3. Example Reports You Can Build</h4>
                <ul style="line-height: 1.8;">
                    <li>Conversion rate by <code>content_purpose</code> (which page types convert best?)</li>
                    <li>Engagement by <code>content_topic</code> (which topics are most popular?)</li>
                    <li>Form submissions by <code>pillar_page</code> (which pillar drives leads?)</li>
                    <li>CTA clicks by <code>optimization_status</code> (do optimized pages perform better?)</li>
                    <li><strong>🆕 ALTC Cluster ROI:</strong> Conversion rate by <code>altc_primary</code> (which authority clusters convert?)</li>
                    <li><strong>🆕 Topic Performance:</strong> Engagement by <code>altc_topic</code> (which topics resonate most?)</li>
                    <li><strong>🆕 Maturity Targeting:</strong> Bounce rate by <code>content_maturity</code> (are expert articles engaging?)</li>
                </ul>

                <div class="notice notice-warning inline" style="margin-top: 20px;">
                    <p><strong>⚠️ Important:</strong> Custom dimensions can take up to 24 hours to appear in GA4
                    after registration. Historical data is NOT backfilled - only new events will include them.</p>
                </div>

            <?php elseif ($current_tab === 'seeding'): ?>
                <!-- TAB 3: Seeding -->
                <h2>🌱 GA4 Event Seeding</h2>

                <?php if ($seeded): ?>
                    <div class="notice notice-success inline">
                        <h3>✅ Events Already Seeded</h3>
                        <p>Your GA4 events were registered on <strong><?php echo esc_html($seed_date); ?></strong></p>

                        <p style="margin-top: 20px;">
                            <a href="<?php echo admin_url('admin.php?page=brighter-analytics&tab=seeding&reset=1'); ?>"
                               class="button"
                               onclick="return confirm('Are you sure? This will allow re-seeding.');">
                                Reset Seeding Flag
                            </a>
                        </p>
                    </div>

                    <hr>

                    <h3>📊 What's Tracked</h3>
                    <p>Your site is tracking these conversion events:</p>

                    <table class="wp-list-table widefat striped" style="max-width: 900px; margin-top: 20px;">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Category</th>
                                <th>Purpose</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="background-color: #fff4e5;">
                                <td><code>click_meeting</code></td>
                                <td>Meetings</td>
                                <td><strong>🔥 High-value conversion</strong></td>
                            </tr>
                            <tr style="background-color: #fff4e5;">
                                <td><code>generate_lead</code></td>
                                <td>Forms</td>
                                <td><strong>🔥 Primary conversion</strong> - GA4 standard event. Mark as conversion in GA4 Admin → Events.</td>
                            </tr>
                            <tr style="background-color: #fff4e5;">
                                <td><code>form_submit</code></td>
                                <td>Forms</td>
                                <td><strong>🔥 Primary conversion</strong></td>
                            </tr>
                            <tr style="background-color: #fff9e5;">
                                <td><code>click_main_cta</code></td>
                                <td>Quote</td>
                                <td><strong>⭐ Conversion intent</strong></td>
                            </tr>
                            <tr>
                                <td><code>get_lead_magnet</code></td>
                                <td>Lead Magnet</td>
                                <td>Lead generation</td>
                            </tr>
                            <tr>
                                <td><code>click_phone</code></td>
                                <td>Contact</td>
                                <td>Direct contact</td>
                            </tr>
                            <tr>
                                <td><code>click_email</code></td>
                                <td>Contact</td>
                                <td>Direct contact</td>
                            </tr>
                            <tr>
                                <td><code>view_pricing</code></td>
                                <td>Trust</td>
                                <td>Purchase consideration</td>
                            </tr>
                            <tr>
                                <td><code>subscribe</code></td>
                                <td>Subscribe</td>
                                <td>Lead nurture</td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: center; font-style: italic; color: #666;">
                                    + 15 more engagement events
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <hr>

                    <h3>🎯 Next Steps</h3>
                    <ol style="line-height: 2;">
                        <li>Go to GA4 → <strong>Admin → Events</strong></li>
                        <li>Find events listed above (they appear within 24 hours of seeding)</li>
                        <li>Click "Mark as conversion" for high-value events (highlighted in yellow/orange)</li>
                        <li>Set up <strong>attribution models</strong> in GA4 → Admin → Attribution Settings</li>
                        <li>Create <strong>conversion funnels</strong> in Explorations</li>
                    </ol>

                    <div class="notice notice-info inline">
                        <p><strong>💡 Pro Tip:</strong> With low traffic (3-7 visitors/week), use GA4's "Model Comparison"
                        in Attribution to understand which touchpoints matter most for your rare conversions.</p>
                    </div>

                <?php else: ?>
                    <div class="notice notice-warning inline">
                        <h3>⚠️ Events Not Yet Seeded</h3>
                        <p>Your GA4 event taxonomy hasn't been initialized yet.</p>

                        <p style="margin-top: 20px;">
                            <a href="<?php echo esc_url(home_url('/?seedEvents=true')); ?>"
                               class="button button-primary button-hero"
                               target="_blank">
                                🌱 Seed Events Now
                            </a>
                        </p>
                    </div>

                    <hr>

                    <h3>Why Seed Events?</h3>
                    <p>With <strong>low traffic</strong>, natural event registration could take months:</p>
                    <ul style="line-height: 2;">
                        <li>⏳ First form submission might not happen for 4-8 weeks</li>
                        <li>⏳ Meeting bookings could be 2-3 months apart</li>
                        <li>❌ Can't set up conversions or attribution until events fire</li>
                        <li>✅ Seeding registers all events <strong>immediately</strong></li>
                    </ul>

                    <h3>What Happens When You Seed?</h3>
                    <ol style="line-height: 2;">
                        <li>Script fires each event <strong>once</strong> with zero-value test data</li>
                        <li>GA4 registers event names within 30 seconds</li>
                        <li>Events appear in Admin → Events within 24 hours</li>
                        <li>You can mark conversions and build funnels immediately</li>
                        <li>All seed events are clearly labeled and filterable</li>
                    </ol>

                    <div class="notice notice-info inline">
                        <p><strong>ℹ️ Note:</strong> If your IP is excluded in GA4 filters, you won't see the seeding
                        in Realtime reports. But the events ARE being sent and will appear in Admin → Events.</p>
                    </div>
                <?php endif; ?>

                <hr>

                <h3>🔍 Filter Seed Events in Reports</h3>
                <p>To exclude seed data from reports, use this filter in GA4 Explorations:</p>
                <pre style="background: #f5f5f5; padding: 15px; border-left: 4px solid #4CAF50; font-family: monospace;">event_label does not contain "[SEED]"</pre>

            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Legacy reset flag handler (only runs when old page is active)
add_action('admin_init', function() {
    if ( defined( 'SCOS_ANALYTICS_ACTIVE' ) ) { return; }
    if (isset($_GET['page']) && $_GET['page'] === 'brighter-analytics' &&
        isset($_GET['tab']) && $_GET['tab'] === 'seeding' &&
        isset($_GET['reset']) && current_user_can('manage_options')) {

        delete_transient('brighter_ga4_events_seeded');
        delete_transient('brighter_ga4_seed_date');

        wp_redirect(admin_url('admin.php?page=brighter-analytics&tab=seeding'));
        exit;
    }
});

// Legacy AJAX handler — only active when site-essentials Analytics module is not loaded
add_action('wp_ajax_brighter_ga4_seed_complete', function() {
    if ( defined( 'SCOS_ANALYTICS_ACTIVE' ) ) { return; }
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    set_transient('brighter_ga4_events_seeded', true, 90 * DAY_IN_SECONDS);
    set_transient('brighter_ga4_seed_date', current_time('mysql'), 90 * DAY_IN_SECONDS);

    wp_send_json_success([
        'message' => 'GA4 events seeded successfully',
        'date' => current_time('mysql')
    ]);
});
