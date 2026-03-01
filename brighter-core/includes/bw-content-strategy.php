<?php
/**
 * Brighter Tools: Content Strategy
 * 
 * File: bw-content-strategy.php
 * Version: 4.1.0
 *
 * Changelog:
 * 4.1.0 - MERGED: Added inline click-to-edit for all fields, fixed nonce issues, combined with optimization status
 * 4.0.1 - Added more intent/purpose options
 * 4.0.0 - Initial version
 *
 * Responsibilities:
 * - Content strategy metadata (Topic, Intent, Purpose, Pillar Page, Notes)
 * - Optimization status tracking
 * - Admin columns with inline editing
 * - Quick/Bulk edit support
 * - Editor sidebar meta boxes
 */

if (!defined('ABSPATH')) exit;

// ==========================
// Register Meta
// ==========================
add_action('init', function() {
    $string_args = [
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => false,
        'auth_callback'     => function() { return current_user_can('edit_posts'); },
    ];
    
    // ALTC Notes (Primary Search Intent) - replaces deprecated bw_notes
    register_post_meta('', 'bw_altc_notes', $string_args);  //in major refactor this should be renamed to bw_intent_goal and migrated.
    register_post_meta('', 'bw_page_topic', $string_args);
    register_post_meta('', 'bw_intent', $string_args);
    register_post_meta('', 'bw_purpose', $string_args);
    register_post_meta('', 'bw_pillar_page_id', [
        'type'              => 'integer',
        'single'            => true,
        'sanitize_callback' => 'absint',
        'show_in_rest'      => false,
        'auth_callback'     => function() { return current_user_can('edit_posts'); },
    ]);
    
    // Service Pathway (functions like Pillar Page for services/products)
    register_post_meta('', 'bw_service_pathway_id', [
        'type'              => 'integer',
        'single'            => true,
        'sanitize_callback' => 'absint',
        'show_in_rest'      => false,
        'auth_callback'     => function() { return current_user_can('edit_posts'); },
    ]);
    
    // Index status
    register_post_meta('', 'bw_index_status', [
        'type'          => 'string',
        'single'        => true,
        'show_in_rest'  => false,
        'auth_callback' => function() { return current_user_can('edit_posts'); },
    ]);
    
    // Workflow Progress (multi-select tags)
    register_post_meta('', 'workflow_progress', [
        'type'              => 'array',
        'single'            => true,
        'show_in_rest'      => false,
        'auth_callback'     => function() { return current_user_can('edit_posts'); },
        'sanitize_callback' => function($value) {
            if (!is_array($value)) {
                return [];
            }
            return array_map('sanitize_text_field', $value);
        },
    ]);
    
    // Next Step / Content Plan (single select)
    register_post_meta('', 'content_plan', [
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => false,
        'auth_callback'     => function() { return current_user_can('edit_posts'); },
    ]);
});


// ==========================
// Configuration
// ==========================
function bw_cs_post_types() {
    // Get all public post types including custom post types
    $post_types = get_post_types([
        'public' => true,
        'show_ui' => true
    ], 'names');
    
    // Exclude post types that shouldn't have content strategy
    $exclude = [
        'attachment', 
        'nav_menu_item', 
        'wp_block', 
        'wp_template', 
        'wp_template_part', 
        'wp_navigation',
        // WooCommerce post types
        'product',
        'product_variation',
        'shop_order',
        'shop_coupon',
        'shop_webhook',
    ];
    
    return array_values(array_diff($post_types, $exclude));
}


//Page Intent - add or modify your own - also maps to GA4 Automatically at the page/Post level
function bw_cs_intent_options() {
    return [
       /* **important note for "major refactor"
         do not yet changes these meta field names until explicitily advised.  
       many related uses in 
       content statistics, ga4 analytics,  API/external tools like airtable, make.com, yourls, social amplification loop (likely others
       so many areas will need updating so for now, i will leave these fields in tact and only change the front facing ones. 
       When imported to other tools informational_p is imported - it would be better if they were more recognisable and standardised 
       Field meta names to be changed as this is sent to ga4 so they should be easier to read and understand(use standard/correct - or _ between ie if conversion-hub or conversion_hub)
              - informational_p - info_problem_aware
              - informational_s   - info_solution_aware
              - commercial_ds  - comm_decision_support


       */

        ''              => 'NA',
        'informational' => 'Informational', //General (Learn / Solve / Understand)
        'informational_p' => 'Info Problem', //Problem Aware (Learn / Solve / Understand)
        'informational_s' => 'Info Solution', //Solution Aware (Learn / Solve / Understand)
        'commercial' => 'Commercial', // 
        'commercial_ds' => 'Decision Support', // Compare / Pricing Pages Commercial Investigation
        'transactional' => 'Transactional Payment', //Payment Intent Conversion Goal   
        'transact_lead' => 'Transactional Lead', //Warm or Hot Lead Generated Conversion Goal Offline Sales Conversion End-of-funnel Quote, Contact, Thank You  

        'trust'         => 'Authority Trust', //Trust / Validation (Prove / Reassure)

        'navigational'  => 'Navigate Hub', // Navigational (Find a Brand / Page / Login)

        'functional'    => 'Policy Functional',  //Sitemap, Maintenance, Privacy, Terms, Access




    ];
}

function bw_cs_purpose_options() {
    return [
       /* **important note for "major refactor"
       do not yet changes these meta field names until explicitily advised.  
       many related uses in 
       content statistics, ga4 analytics,  API/external tools like airtable, make.com, yourls, social amplification loop (likely others
       so many areas will need updating so for now, i will leave these fields in tact and only change the front facing ones. 
      When imported to other tools informational_p is imported - it would be better if they were more recognisable and standardised 
       Field meta names to be changed
       - service-page to service         
       - product-page to product         
       - case-study to success-story         
       - authority-page to brand-authority  
       - supporting to supporting-topic
       - terms to policy      
       - changing these will have impact on all areas that rely on purpose like utm params in social loop
       */
 	''              => 'NA',
        'pillar'        => 'Pillar',             // High-level topic hub (e.g., "The Complete Stable Buyer's Guide")
        'service-page'  => 'Service',       // Page detailing a service (e.g., "Shed-to-Stable Retrofit Conversion")
        'product-page'  => 'Product',       // Page detailing a specific model (e.g., "4x4m Modular Stable Kit")
        'conversion-hub'=> 'Conversion Hub',     // conversion page with multiple pathways (eg service/product collection pages, find your perfect "service" )
        'conversion-event'=> 'Conversion Event',     // Dedicated Conversion event - (eg Lead magnet delivery, Quote, Pricing Calculator, Service Finder Quiz)
        'conversion-endpoint'=> 'Conversion End-Point',     // Post Conversion Event - Thank you page, Post Sale Conversion redirect
        'case-study'    => 'Success Story',         // Specific client success story (e.g., "4-Bay Build in Tamworth")
        'authority-page'=> 'Brand Authority',     // E-E-A-T pages (e.g., "About Us," "Our Welding Process")
        'supporting'    => 'Supporting Topic',         // Standard blog article (e.g., The article we just wrote)
        'content-collection'=> 'Content Collection',     // Topic/Cluster Curated/Collection Hub/Archive pages
        'resource-guide'=> 'Resource Guide',     // Keep for how to guides.
      
        'terms'         => 'Policy',        // Legal Terms Pages, privacy, terms, warranty, returns)
        'functional'         => 'Functional',        // Functional pages - maintenance, access, front end editing.

    ];
}

function bw_cs_index_status_options() {
    return [
        ''            => 'Not Set',
        'crawled'     => 'Crawled',      // Amber - Issue
        'discovered'  => 'Discovered',   // Blue - Waiting
        'indexed'     => 'Indexed',      // Dark Green
        'requested'   => 'Requested',    // Teal - Waiting
        'issue'       => 'Issue',        // Dark Red - Critical
        'no_index'    => 'Do Not Index', // Red - Excluded
    ];
}

/**
 * Workflow Progress options (multi-select tags)
 */
function bw_cs_workflow_progress_options() {
    return [
        'idea'               => ['label' => 'Idea 💡', 'color' => '#7c3aed', 'bg' => '#ede9fe'],              // Purple - Planning
        'content'            => ['label' => 'Content ✏️', 'color' => '#2563eb', 'bg' => '#dbeafe'],           // Blue - Writing
        'entities-semantics' => ['label' => 'Entity Semantic Coverage 📑', 'color' => '#0891b2', 'bg' => '#cffafe'], // Cyan - Research
        'conversion'         => ['label' => 'Conversion 💲', 'color' => '#16a34a', 'bg' => '#dcfce7'],         // Green - CRO
        'seo-basic'          => ['label' => 'Basic SEO 🥈', 'color' => '#ca8a04', 'bg' => '#fef9c3'],          // Yellow - Basic
        'seo-advanced'       => ['label' => 'Advanced SEO 🥇', 'color' => '#ea580c', 'bg' => '#ffedd5'],       // Orange - Advanced
        'authority-outreach' => ['label' => 'Authority Outreach 🔗', 'color' => '#be185d', 'bg' => '#fce7f3'], // Pink - Outreach
        'amplification'      => ['label' => 'Social Amplification 📢', 'color' => '#4f46e5', 'bg' => '#e0e7ff'], // Indigo - Promotion
    ];
}

/**
 * Next Step / Content Plan options (single select)
 */
function bw_cs_content_plan_options() {
    return [
        ''        => ['label' => '— Not Set —', 'color' => '#6b7280', 'bg' => '#f3f4f6'],
        'approve' => ['label' => '✅ Approved', 'color' => '#15803d', 'bg' => '#dcfce7'],
        'testing' => ['label' => '🚥 Testing', 'color' => '#ca8a04', 'bg' => '#fef9c3'],
        'revise'  => ['label' => '🔄 Revise', 'color' => '#2563eb', 'bg' => '#dbeafe'],
        'merge'   => ['label' => '🔀 Merge', 'color' => '#7c3aed', 'bg' => '#ede9fe'],
        'archive' => ['label' => '🗑️ Archive', 'color' => '#dc2626', 'bg' => '#fee2e2'],
    ];
}
//this is ready to be depreciated fully please also search for other instances and uses of this and remove it, flag if we need to rework any other sections. 
// ==========================
// Admin Columns
// ==========================
add_action('admin_init', function() {
    foreach (bw_cs_post_types() as $pt) {
        add_filter("manage_edit-{$pt}_columns", function($cols) {
            $new = [];
            foreach ($cols as $k => $v) {
                $new[$k] = $v;
                if ($k === 'title') {
                    $new['bw_pillar']          = 'Pillar';
                    $new['bw_service_pathway'] = 'Service Pathway';
                    $new['bw_intent']          = 'Intent';
                    $new['bw_purpose']         = 'Purpose';
                    $new['bw_index']           = 'Index Status';
                    $new['bw_progress']        = 'Progress';
                    $new['bw_next_step']       = 'Next Step';
                    $new['bw_altc_notes']      = 'Primary Intent';
                    // Stats columns - TEMPORARILY DISABLED for performance testing
                    // Each column makes get_post_meta() calls which causes query explosion
                    // TODO: Add proper meta cache priming before re-enabling
                    // $new['bw_word_count']     = 'Words';
                    // $new['bw_images']         = 'Images';
                    // $new['bw_h2s']            = 'H2s';
                    // $new['bw_internal_links'] = 'Int Links';
                    // $new['bw_external_links'] = 'Ext Links';
                }
            }
            return $new;
        });
        
        add_action("manage_{$pt}_posts_custom_column", function($col, $post_id) {
            switch ($col) {
                // DEPRECATED: bw_topic column removed - use ALTC Topic taxonomy instead

                case 'bw_intent':
                    $val = get_post_meta($post_id, 'bw_intent', true);
                    $opts = bw_cs_intent_options();
                    echo '<span class="bw-cs-select" data-post="' . esc_attr($post_id) . '" data-field="bw_intent" style="cursor:pointer;text-decoration:underline dotted;">' 
                         . esc_html($opts[$val] ?? 'NA') . '</span>';
                    break;
                    
                case 'bw_purpose':
                    $val = get_post_meta($post_id, 'bw_purpose', true);
                    $opts = bw_cs_purpose_options();
                    echo '<span class="bw-cs-select" data-post="' . esc_attr($post_id) . '" data-field="bw_purpose" style="cursor:pointer;text-decoration:underline dotted;">' 
                         . esc_html($opts[$val] ?? 'NA') . '</span>';
                    break;
                    
                case 'bw_index':
                    $val = get_post_meta($post_id, 'bw_index_status', true);
                    $opts = bw_cs_index_status_options();
                    $label = $opts[$val] ?? 'Not Set';
                    $colors = [
                        'crawled' => ['color' => '#b45309', 'bg' => '#fef3c7'],      // Amber - Issue
                        'discovered' => ['color' => '#1d4ed8', 'bg' => '#dbeafe'],   // Blue - Waiting
                        'indexed' => ['color' => '#065f46', 'bg' => '#d1fae5'],      // Dark Green
                        'requested' => ['color' => '#0f766e', 'bg' => '#ccfbf1'],    // Teal - Waiting
                        'issue' => ['color' => '#991b1b', 'bg' => '#fee2e2'],        // Dark Red - Critical
                        'no_index' => ['color' => '#dc2626', 'bg' => '#fee2e2'],     // Red - Excluded
                        '' => ['color' => '#6b7280', 'bg' => '#f3f4f6']               // Grey - Not Set
                    ];
                    $color = $colors[$val] ?? $colors[''];
                    echo '<span class="bw-cs-select" data-post="' . esc_attr($post_id) . '" data-field="bw_index_status" style="display:inline-block;border-radius:3px;padding:3px 8px;font-size:11px;font-weight:600;color:' . esc_attr($color['color']) . ';background:' . esc_attr($color['bg']) . ';cursor:pointer;">'
                         . esc_html($label) . '</span>';
                    break;

                case 'bw_pillar':
                    $id = get_post_meta($post_id, 'bw_pillar_page_id', true);
                    if ($id) {
                        $title = get_the_title($id);
                        $purpose = get_post_meta($id, 'bw_purpose', true);
                        $type_labels = [
                            'service-page' => ' [Service]',
                            'product-page' => ' [Product]',
                            'pillar' => ' [Pillar]'
                        ];
                        $type_label = isset($type_labels[$purpose]) ? $type_labels[$purpose] : ' [Pillar]';
                        echo '<span class="bw-cs-pillar" data-post="' . esc_attr($post_id) . '" style="cursor:pointer;text-decoration:underline dotted;">'
                             . esc_html($title . $type_label) . '</span>';
                    } else {
                        echo '<span class="bw-cs-pillar" data-post="' . esc_attr($post_id) . '" style="cursor:pointer;color:#999;">Not Set</span>';
                    }
                    break;
                    
                case 'bw_service_pathway':
                    $id = get_post_meta($post_id, 'bw_service_pathway_id', true);
                    if ($id) {
                        $title = get_the_title($id);
                        $purpose = get_post_meta($id, 'bw_purpose', true);
                        $type_labels = [
                            'service-page' => ' [Service]',
                            'product-page' => ' [Product]',
                            'conversion-hub' => ' [Hub]'
                        ];
                        $type_label = isset($type_labels[$purpose]) ? $type_labels[$purpose] : '';
                        echo '<span class="bw-cs-service-pathway" data-post="' . esc_attr($post_id) . '" style="cursor:pointer;text-decoration:underline dotted;">'
                             . esc_html($title . $type_label) . '</span>';
                    } else {
                        echo '<span class="bw-cs-service-pathway" data-post="' . esc_attr($post_id) . '" style="cursor:pointer;color:#999;">Not Set</span>';
                    }
                    break;
                    
                case 'bw_progress':
                    $values = get_post_meta($post_id, 'workflow_progress', true);
                    $opts = bw_cs_workflow_progress_options();
                    if (!empty($values) && is_array($values)) {
                        // Store values as JSON in data attribute for inline edit
                        echo '<span class="bw-cs-progress" data-post="' . esc_attr($post_id) . '" data-progress-values="' . esc_attr(wp_json_encode($values)) . '" style="cursor:pointer;">';
                        $tags = [];
                        foreach ($values as $val) {
                            if (isset($opts[$val])) {
                                $cfg = $opts[$val];
                                $tags[] = '<span style="display:inline-block;border-radius:3px;padding:2px 6px;font-size:10px;font-weight:600;color:' . esc_attr($cfg['color']) . ';background:' . esc_attr($cfg['bg']) . ';margin:1px;">' . esc_html($cfg['label']) . '</span>';
                            }
                        }
                        echo implode('', $tags);
                        echo '</span>';
                    } else {
                        echo '<span class="bw-cs-progress" data-post="' . esc_attr($post_id) . '" style="cursor:pointer;color:#999;">Not Set</span>';
                    }
                    break;
                    
                case 'bw_next_step':
                    $val = get_post_meta($post_id, 'content_plan', true);
                    $opts = bw_cs_content_plan_options();
                    if (!isset($opts[$val])) $val = '';
                    $cfg = $opts[$val];
                    printf(
                        '<span class="bw-cs-next-step" data-post="%d" data-value="%s" title="Click to edit" style="display:inline-block;border-radius:3px;padding:3px 8px;font-size:11px;font-weight:600;color:%s;background:%s;cursor:pointer;">%s</span>',
                        $post_id,
                        esc_attr($val),
                        esc_attr($cfg['color']),
                        esc_attr($cfg['bg']),
                        esc_html($cfg['label'])
                    );
                    break;
                    
                case 'bw_altc_notes':
                    $notes = get_post_meta($post_id, 'bw_altc_notes', true);
                    // Manual truncation that's more reliable with special characters
                    $display_notes = mb_strlen($notes) > 60 
                        ? mb_substr($notes, 0, 60) . '…' 
                        : $notes;
                    echo '<span class="bw-cs-text" data-post="' . esc_attr($post_id) . '" data-field="bw_altc_notes" style="cursor:pointer;">'
                         . esc_html($display_notes ?: '—') . '</span>';
                    break;

                // Stats columns - TEMPORARILY DISABLED for performance testing
                /*
                case 'bw_word_count':
                    $count = get_post_meta($post_id, 'bw_word_count', true);
                    echo $count ? '<span style="color:#2271b1;font-weight:600;">' . number_format($count) . '</span>' : '<span style="color:#999;">—</span>';
                    break;

                case 'bw_images':
                    $count = get_post_meta($post_id, 'bw_image_count', true);
                    echo $count ? '<span style="color:#2271b1;font-weight:600;">' . absint($count) . '</span>' : '<span style="color:#999;">—</span>';
                    break;

                case 'bw_h2s':
                    $count = get_post_meta($post_id, 'bw_h2_count', true);
                    echo $count ? '<span style="color:#2271b1;font-weight:600;">' . absint($count) . '</span>' : '<span style="color:#999;">—</span>';
                    break;

                case 'bw_internal_links':
                    $count = get_post_meta($post_id, 'bw_internal_link_count', true);
                    $color = $count > 5 ? '#16a34a' : ($count > 2 ? '#ca8a04' : '#dc2626');
                    echo $count ? '<span style="color:' . esc_attr($color) . ';font-weight:600;">' . absint($count) . '</span>' : '<span style="color:#dc2626;font-weight:600;">0</span>';
                    break;

                case 'bw_external_links':
                    $count = get_post_meta($post_id, 'bw_external_link_count', true);
                    echo $count ? '<span style="color:#2271b1;font-weight:600;">' . absint($count) . '</span>' : '<span style="color:#999;">0</span>';
                    break;
                */
            }
        }, 10, 2);
    }
});

// ==========================
// Sortable Columns
// ==========================
foreach (['post', 'page'] as $pt) {
    add_filter("manage_edit-{$pt}_sortable_columns", function($cols) {
        // DEPRECATED: bw_topic removed - use ALTC Topic taxonomy instead
        $cols['bw_intent']          = 'bw_intent';
        $cols['bw_purpose']         = 'bw_purpose';
        $cols['bw_index']           = 'bw_index_status';
        $cols['bw_pillar']          = 'bw_pillar_page_id';
        $cols['bw_service_pathway'] = 'bw_service_pathway_id';
        $cols['bw_next_step']       = 'content_plan';
        // Stats columns - TEMPORARILY DISABLED
        // $cols['bw_word_count']     = 'bw_word_count';
        // $cols['bw_images']         = 'bw_image_count';
        // $cols['bw_h2s']            = 'bw_h2_count';
        // $cols['bw_internal_links'] = 'bw_internal_link_count';
        // $cols['bw_external_links'] = 'bw_external_link_count';
        return $cols;
    });
}

add_action('pre_get_posts', function($q) {
    if (!is_admin() || !$q->is_main_query()) return;
    $orderby = $q->get('orderby');

    // Text-based meta fields
    // DEPRECATED: bw_page_topic removed - use ALTC Topic taxonomy instead
    if (in_array($orderby, ['bw_intent', 'bw_purpose', 'bw_index_status', 'bw_pillar_page_id', 'bw_service_pathway_id', 'content_plan'], true)) {
        $q->set('meta_key', $orderby);
        $q->set('orderby', 'meta_value');
    }

    // Numeric meta fields (stats) - TEMPORARILY DISABLED
    /*
    if (in_array($orderby, ['bw_word_count', 'bw_image_count', 'bw_h2_count', 'bw_internal_link_count', 'bw_external_link_count'], true)) {
        $q->set('meta_key', $orderby);
        $q->set('orderby', 'meta_value_num');
    }
    */
});

// ==========================
// Inline Editing - JavaScript
// ==========================
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'edit.php') return;
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, bw_cs_post_types(), true)) return;
    
    // Get pillar pages for dropdown (all post types with qualifying purposes)
    $pillar_pages = get_posts([
        'post_type'      => bw_cs_post_types(),
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => 'bw_purpose',
                'value'   => 'pillar',
                'compare' => '='
            ],
            [
                'key'     => 'bw_purpose',
                'value'   => 'service-page',
                'compare' => '='
            ],
            [
                'key'     => 'bw_purpose',
                'value'   => 'product-page',
                'compare' => '='
            ]
        ]
    ]);

    $pillar_opts = ['0' => 'Not Set'];
    foreach ($pillar_pages as $p) {
        $purpose = get_post_meta($p->ID, 'bw_purpose', true);
        $type_labels = [
            'service-page' => ' [Service]',
            'product-page' => ' [Product]',
            'pillar' => ' [Pillar]'
        ];
        $type = isset($type_labels[$purpose]) ? $type_labels[$purpose] : ' [Pillar]';
        $pillar_opts[$p->ID] = get_the_title($p) . $type;
    }
    
    // Get service pathway pages for dropdown (service-page, product-page, conversion-hub)
    $service_pathway_pages = get_posts([
        'post_type'      => bw_cs_post_types(),
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => 'bw_purpose',
                'value'   => 'service-page',
                'compare' => '='
            ],
            [
                'key'     => 'bw_purpose',
                'value'   => 'product-page',
                'compare' => '='
            ],
            [
                'key'     => 'bw_purpose',
                'value'   => 'conversion-hub',
                'compare' => '='
            ]
        ]
    ]);

    $service_pathway_opts = ['0' => 'Not Set'];
    foreach ($service_pathway_pages as $p) {
        $purpose = get_post_meta($p->ID, 'bw_purpose', true);
        $type_labels = [
            'service-page' => ' [Service]',
            'product-page' => ' [Product]',
            'conversion-hub' => ' [Hub]'
        ];
        $type = isset($type_labels[$purpose]) ? $type_labels[$purpose] : '';
        $service_pathway_opts[$p->ID] = get_the_title($p) . $type;
    }
    
    wp_register_script('bw-cs-inline', false, ['jquery'], '1.3', true);
    wp_add_inline_script('bw-cs-inline', '(function($){
        const nonce = "' . esc_js(wp_create_nonce('bw_cs_inline')) . '";
        const intentOpts = ' . wp_json_encode(bw_cs_intent_options()) . ';
        const purposeOpts = ' . wp_json_encode(bw_cs_purpose_options()) . ';
        const indexOpts = ' . wp_json_encode(bw_cs_index_status_options()) . ';
        const pillarOpts = ' . wp_json_encode($pillar_opts) . ';
        const servicePathwayOpts = ' . wp_json_encode($service_pathway_opts) . ';
        const progressOpts = ' . wp_json_encode(bw_cs_workflow_progress_options()) . ';
        const nextStepOpts = ' . wp_json_encode(bw_cs_content_plan_options()) . ';
        
        let activeRequest = null; // Track active AJAX request
        
        function saveField(postId, field, value) {
            // Cancel previous request if still pending
            if (activeRequest) {
                activeRequest.abort();
            }
            
            activeRequest = $.post(ajaxurl, {
                action: "bw_cs_save_field",
                post_id: postId,
                field: field,
                value: value,
                _ajax_nonce: nonce
            });
            
            return activeRequest;
        }
        
        // Text fields (topic, notes)
        $(document).on("click", ".bw-cs-text", function(e) {
            e.preventDefault();
            const $span = $(this);
            const postId = $span.data("post");
            const field = $span.data("field");
            const current = $span.text();
            
            const $input = $("<input>", {
                type: "text",
                class: "bw-cs-input",
                value: current,
                "data-post": postId,
                "data-field": field,
                css: { width: "100%" }
            });
            
            $span.replaceWith($input);
            $input.focus().select();
        });
        
        $(document).on("blur keydown", ".bw-cs-input", function(e) {
            if (e.type === "keydown" && e.keyCode !== 13) return;
            if (e.type === "keydown") e.preventDefault();
            
            const $input = $(this);
            const postId = $input.data("post");
            const field = $input.data("field");
            const value = $input.val();
            
            $input.prop("disabled", true);
            saveField(postId, field, value).done(function(resp) {
                if (resp && resp.success) {
                    const $span = $("<span>", {
                        class: "bw-cs-text",
                        "data-post": postId,
                        "data-field": field,
                        text: value
                    });
                    $input.replaceWith($span);
                } else {
                    alert("Save failed");
                    $input.prop("disabled", false).focus();
                }
            });
        });
        
        // Select fields (intent, purpose, index)
        $(document).on("click", ".bw-cs-select", function(e) {
            e.preventDefault();
            const $span = $(this);
            const postId = $span.data("post");
            const field = $span.data("field");
            let opts = purposeOpts; // default
            if (field === "bw_intent") opts = intentOpts;
            else if (field === "bw_index_status") opts = indexOpts;
            
            const $select = $("<select>", {
                class: "bw-cs-dropdown",
                "data-post": postId,
                "data-field": field
            });
            
            $.each(opts, function(key, label) {
                $select.append($("<option>", { value: key, text: label }));
            });
            
            $span.replaceWith($select);
            $select.focus();
        });
        
        $(document).on("change blur", ".bw-cs-dropdown", function(e) {
            const $select = $(this);
            const postId = $select.data("post");
            const field = $select.data("field");
            const value = $select.val();
            // Select correct options object based on field
            let opts = purposeOpts;
            if (field === "bw_intent") opts = intentOpts;
            else if (field === "bw_index_status") opts = indexOpts;

            $select.prop("disabled", true);
            saveField(postId, field, value).done(function(resp) {
                if (resp && resp.success) {
                    // Apply special styling for index status
                    const spanStyle = { cursor: "pointer" };
                    if (field === "bw_index_status") {
                        const indexColors = {
                            "crawled": { color: "#b45309", bg: "#fef3c7" },
                            "discovered": { color: "#1d4ed8", bg: "#dbeafe" },
                            "indexed": { color: "#065f46", bg: "#d1fae5" },
                            "requested": { color: "#0f766e", bg: "#ccfbf1" },
                            "issue": { color: "#991b1b", bg: "#fee2e2" },
                            "": { color: "#6b7280", bg: "#f3f4f6" }
                        };
                        const colors = indexColors[value] || indexColors[""];
                        spanStyle.display = "inline-block";
                        spanStyle.borderRadius = "3px";
                        spanStyle.padding = "3px 8px";
                        spanStyle.fontSize = "11px";
                        spanStyle.fontWeight = "600";
                        spanStyle.color = colors.color;
                        spanStyle.background = colors.bg;
                    } else {
                        spanStyle.textDecoration = "underline dotted";
                    }

                    const $span = $("<span>", {
                        class: "bw-cs-select",
                        "data-post": postId,
                        "data-field": field,
                        text: opts[value] || "NA",
                        css: spanStyle
                    });
                    $select.replaceWith($span);
                } else {
                    alert("Save failed");
                    $select.prop("disabled", false).focus();
                }
            });
        });
        
        // Pillar page
        $(document).on("click", ".bw-cs-pillar", function(e) {
            e.preventDefault();
            const $span = $(this);
            const postId = $span.data("post");
            
            const $select = $("<select>", {
                class: "bw-pillar-dropdown",
                "data-post": postId
            });
            
            $.each(pillarOpts, function(key, label) {
                $select.append($("<option>", { value: key, text: label }));
            });
            
            $span.replaceWith($select);
            $select.focus();
        });
        
        $(document).on("change blur", ".bw-pillar-dropdown", function(e) {
            const $select = $(this);
            const postId = $select.data("post");
            const value = $select.val();
            
            $select.prop("disabled", true);
            saveField(postId, "bw_pillar_page_id", value).done(function(resp) {
                if (resp && resp.success) {
                    const label = pillarOpts[value] || "—";
                    const $span = $("<span>", {
                        class: "bw-cs-pillar",
                        "data-post": postId,
                        text: label,
                        css: {
                            cursor: "pointer",
                            textDecoration: value > 0 ? "underline dotted" : "none",
                            color: value > 0 ? "#000" : "#999"
                        }
                    });
                    $select.replaceWith($span);
                } else {
                    alert("Save failed");
                    $select.prop("disabled", false).focus();
                }
            });
        });
        
        // Service Pathway
        $(document).on("click", ".bw-cs-service-pathway", function(e) {
            e.preventDefault();
            const $span = $(this);
            const postId = $span.data("post");
            
            const $select = $("<select>", {
                class: "bw-service-pathway-dropdown",
                "data-post": postId
            });
            
            $.each(servicePathwayOpts, function(key, label) {
                $select.append($("<option>", { value: key, text: label }));
            });
            
            $span.replaceWith($select);
            $select.focus();
        });
        
        $(document).on("change blur", ".bw-service-pathway-dropdown", function(e) {
            const $select = $(this);
            const postId = $select.data("post");
            const value = $select.val();
            
            $select.prop("disabled", true);
            saveField(postId, "bw_service_pathway_id", value).done(function(resp) {
                if (resp && resp.success) {
                    const label = servicePathwayOpts[value] || "—";
                    const $span = $("<span>", {
                        class: "bw-cs-service-pathway",
                        "data-post": postId,
                        text: label,
                        css: {
                            cursor: "pointer",
                            textDecoration: value > 0 ? "underline dotted" : "none",
                            color: value > 0 ? "#000" : "#999"
                        }
                    });
                    $select.replaceWith($span);
                } else {
                    alert("Save failed");
                    $select.prop("disabled", false).focus();
                }
            });
        });
        
        // Progress (multi-select tags)
        $(document).on("click", ".bw-cs-progress", function(e) {
            e.preventDefault();
            const $span = $(this);
            const postId = $span.data("post");
            
            const $wrapper = $("<div>", {
                class: "bw-progress-checkboxes",
                "data-post": postId,
                css: { padding: "6px", background: "#f9f9f9", borderRadius: "3px" }
            });
            
            $.each(progressOpts, function(key, cfg) {
                const $label = $("<label>", {
                    css: { display: "block", fontSize: "11px", marginBottom: "3px", cursor: "pointer" }
                });
                const $cb = $("<input>", {
                    type: "checkbox",
                    value: key,
                    css: { marginRight: "4px" }
                });
                $label.append($cb).append(cfg.label);
                $wrapper.append($label);
            });
            
            const $saveBtn = $("<button>", {
                type: "button",
                class: "button button-small bw-progress-save",
                text: "Save",
                css: { marginTop: "6px" }
            });
            $wrapper.append($saveBtn);
            
            $span.replaceWith($wrapper);
        });
        
        $(document).on("click", ".bw-progress-save", function(e) {
            e.preventDefault();
            const $wrapper = $(this).closest(".bw-progress-checkboxes");
            const postId = $wrapper.data("post");
            const values = [];
            
            $wrapper.find("input:checked").each(function() {
                values.push($(this).val());
            });
            
            $(this).prop("disabled", true).text("Saving...");
            saveField(postId, "workflow_progress", JSON.stringify(values)).done(function(resp) {
                if (resp && resp.success) {
                    let html = "";
                    if (values.length > 0) {
                        values.forEach(function(val) {
                            if (progressOpts[val]) {
                                const cfg = progressOpts[val];
                                html += "<span style=\"display:inline-block;border-radius:3px;padding:2px 6px;font-size:10px;font-weight:600;color:" + cfg.color + ";background:" + cfg.bg + ";margin:1px;\">" + cfg.label + "</span>";
                            }
                        });
                    } else {
                        html = "Not Set";
                    }
                    const $span = $("<span>", {
                        class: "bw-cs-progress",
                        "data-post": postId,
                        html: html,
                        css: {
                            cursor: "pointer",
                            color: values.length > 0 ? "#000" : "#999"
                        }
                    });
                    $wrapper.replaceWith($span);
                } else {
                    alert("Save failed");
                    $wrapper.find(".bw-progress-save").prop("disabled", false).text("Save");
                }
            });
        });
        
        // Next Step dropdown
        $(document).on("click", ".bw-cs-next-step", function(e) {
            e.preventDefault();
            const $span = $(this);
            const postId = $span.data("post");
            const current = $span.data("value") || "";
            
            const $select = $("<select>", {
                class: "bw-next-step-dropdown",
                "data-post": postId,
                css: { fontSize: "12px" }
            });
            
            $.each(nextStepOpts, function(key, cfg) {
                $select.append($("<option>", {
                    value: key,
                    text: cfg.label,
                    selected: key === current
                }));
            });
            
            $span.replaceWith($select);
            $select.focus();
        });
        
        $(document).on("change blur", ".bw-next-step-dropdown", function(e) {
            const $select = $(this);
            const postId = $select.data("post");
            const value = $select.val();
            const cfg = nextStepOpts[value] || nextStepOpts[""];
            
            $select.prop("disabled", true);
            saveField(postId, "content_plan", value).done(function(resp) {
                if (resp && resp.success) {
                    const $span = $("<span>", {
                        class: "bw-cs-next-step",
                        "data-post": postId,
                        "data-value": value,
                        title: "Click to edit",
                        text: cfg.label,
                        css: {
                            display: "inline-block",
                            borderRadius: "3px",
                            padding: "3px 8px",
                            fontSize: "11px",
                            fontWeight: "600",
                            color: cfg.color,
                            background: cfg.bg,
                            cursor: "pointer"
                        }
                    });
                    $select.replaceWith($span);
                } else {
                    alert("Save failed");
                    $select.prop("disabled", false).focus();
                }
            });
        });
        
    })(jQuery);');
    wp_enqueue_script('bw-cs-inline');
    
    // Column width CSS
    wp_add_inline_style('common', '
        .fixed .column-bw_topic { width: 140px; }
        .fixed .column-bw_intent { width: 110px; }
        .fixed .column-bw_purpose { width: 120px; }
        .fixed .column-bw_opt { width: 130px; }
        .fixed .column-bw_pillar { width: 140px; }
        .fixed .column-bw_service_pathway { width: 140px; }
        .fixed .column-bw_progress { width: 180px; }
        .fixed .column-bw_next_step { width: 100px; }
        .fixed .column-bw_altc_notes { width: 180px; }
    ');
});

// ==========================
// AJAX Handler
// ==========================
add_action('wp_ajax_bw_cs_save_field', function() {
    check_ajax_referer('bw_cs_inline');

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $field = isset($_POST['field']) ? sanitize_key($_POST['field']) : '';
    $value = isset($_POST['value']) ? wp_unslash($_POST['value']) : '';

    // Verify post exists and has a valid registered post type
    $post_type = get_post_type($post_id);
    if (!$post_id || !$post_type || !post_type_exists($post_type) || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error('No permission');
    }
    
    // Allowed fields for inline editing
    $allowed = ['bw_altc_notes', 'bw_intent', 'bw_purpose', 'bw_pillar_page_id', 'bw_service_pathway_id', 'bw_index_status', 'workflow_progress', 'content_plan'];
    if (!in_array($field, $allowed, true)) {
        wp_send_json_error('Invalid field');
    }
    
    // Handle different field types
    if (in_array($field, ['bw_pillar_page_id', 'bw_service_pathway_id'], true)) {
        $value = absint($value);
    } elseif ($field === 'workflow_progress') {
        // Handle array field (multi-select tags)
        $value = json_decode($value, true);
        if (!is_array($value)) {
            $value = [];
        }
        $value = array_map('sanitize_text_field', $value);
    } elseif ($field === 'bw_altc_notes') {
        // Textarea field - preserve newlines
        $value = sanitize_textarea_field($value);
    } else {
        $value = sanitize_text_field($value);
    }
    
    update_post_meta($post_id, $field, $value);
    wp_send_json_success(true);
});

// ==========================
// Quick Edit + Bulk Edit
// ==========================
add_action('quick_edit_custom_box', 'bw_cs_quick_bulk_box', 10, 2);
add_action('bulk_edit_custom_box', 'bw_cs_quick_bulk_box', 10, 2);

function bw_cs_quick_bulk_box($col, $post_type) {
    // Allowed columns for quick/bulk edit
    $allowed_cols = ['bw_altc_notes', 'bw_intent', 'bw_purpose', 'bw_pillar', 'bw_service_pathway', 'bw_opt', 'bw_index', 'bw_progress', 'bw_next_step'];
    if (!in_array($col, $allowed_cols, true)) return;
    if (!in_array($post_type, bw_cs_post_types(), true)) return;

    // Detect if this is bulk edit (hide notes field for bulk edit)
    $is_bulk = (current_filter() === 'bulk_edit_custom_box');

    // Hide notes field in bulk edit
    if ($is_bulk && $col === 'bw_altc_notes') return;

    // Get pillar pages for dropdown
    $pillar_pages = get_posts([
        'post_type' => bw_cs_post_types(),
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_query' => [
            'relation' => 'OR',
            ['key' => 'bw_purpose', 'value' => 'pillar', 'compare' => '='],
            ['key' => 'bw_purpose', 'value' => 'service-page', 'compare' => '='],
            ['key' => 'bw_purpose', 'value' => 'product-page', 'compare' => '=']
        ]
    ]);
    
    // Get service pathway pages for dropdown
    $service_pathway_pages = get_posts([
        'post_type' => bw_cs_post_types(),
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_query' => [
            'relation' => 'OR',
            ['key' => 'bw_purpose', 'value' => 'service-page', 'compare' => '='],
            ['key' => 'bw_purpose', 'value' => 'product-page', 'compare' => '='],
            ['key' => 'bw_purpose', 'value' => 'conversion-hub', 'compare' => '=']
        ]
    ]);
    ?>
    <fieldset class="inline-edit-col-left">
        <div class="inline-edit-col">
            <?php
            if ($col === 'bw_altc_notes'): ?>
                <label><span class="title">Primary Intent</span>
                    <textarea name="bw_altc_notes" rows="2"></textarea>
                </label>
            <?php elseif ($col === 'bw_intent'): ?>
                <label><span class="title">Intent</span>
                    <select name="bw_intent">
                        <?php if ($is_bulk): ?>
                            <option value="">-- No Change --</option>
                        <?php endif; ?>
                        <?php foreach (bw_cs_intent_options() as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php elseif ($col === 'bw_purpose'): ?>
                <label><span class="title">Purpose</span>
                    <select name="bw_purpose">
                        <?php if ($is_bulk): ?>
                            <option value="">-- No Change --</option>
                        <?php endif; ?>
                        <?php foreach (bw_cs_purpose_options() as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php elseif ($col === 'bw_pillar'): ?>
                <label><span class="title">Pillar Page</span>
                    <select name="bw_pillar_page_id">
                        <?php if ($is_bulk): ?>
                            <option value="">-- No Change --</option>
                        <?php else: ?>
                            <option value="">Not Set</option>
                        <?php endif; ?>
                        <?php foreach ($pillar_pages as $p):
                            $purpose = get_post_meta($p->ID, 'bw_purpose', true);
                            $type_labels = [
                                'service-page' => ' [Service]',
                                'product-page' => ' [Product]',
                                'pillar' => ' [Pillar]'
                            ];
                            $type = isset($type_labels[$purpose]) ? $type_labels[$purpose] : ' [Pillar]';
                        ?>
                            <option value="<?php echo esc_attr($p->ID); ?>">
                                <?php echo esc_html(get_the_title($p) . $type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php elseif ($col === 'bw_service_pathway'): ?>
                <label><span class="title">Service Pathway</span>
                    <select name="bw_service_pathway_id">
                        <?php if ($is_bulk): ?>
                            <option value="">-- No Change --</option>
                        <?php else: ?>
                            <option value="">Not Set</option>
                        <?php endif; ?>
                        <?php foreach ($service_pathway_pages as $p):
                            $purpose = get_post_meta($p->ID, 'bw_purpose', true);
                            $type_labels = [
                                'service-page' => ' [Service]',
                                'product-page' => ' [Product]',
                                'conversion-hub' => ' [Hub]'
                            ];
                            $type = isset($type_labels[$purpose]) ? $type_labels[$purpose] : '';
                        ?>
                            <option value="<?php echo esc_attr($p->ID); ?>">
                                <?php echo esc_html(get_the_title($p) . $type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php elseif ($col === 'bw_progress'): ?>
                <label><span class="title">Progress</span>
                    <input type="hidden" name="workflow_progress_touched" value="1">
                    <div class="bw-progress-checkboxes-edit" style="margin-top:4px;">
                        <?php foreach (bw_cs_workflow_progress_options() as $key => $cfg): ?>
                            <label style="display:block;font-size:11px;margin-bottom:2px;">
                                <input type="checkbox" name="workflow_progress[]" value="<?php echo esc_attr($key); ?>">
                                <?php echo esc_html($cfg['label']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </label>
            <?php elseif ($col === 'bw_next_step'): ?>
                <label><span class="title">Next Step</span>
                    <select name="content_plan">
                        <?php if ($is_bulk): ?>
                            <option value="">-- No Change --</option>
                        <?php endif; ?>
                        <?php foreach (bw_cs_content_plan_options() as $key => $cfg): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($cfg['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php elseif ($col === 'bw_index'): ?>
                <label><span class="title">Index Status</span>
                    <select name="bw_index_status">
                        <?php if ($is_bulk): ?>
                            <option value="">-- No Change --</option>
                        <?php endif; ?>
                        <?php foreach (bw_cs_index_status_options() as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>
    </fieldset>
    <?php
}

// Preload Quick Edit with current values
add_action('admin_footer-edit.php', function() {
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, bw_cs_post_types(), true)) return;
    ?>
    <script>
    jQuery(function($) {
        // Hook into WordPress's inlineEditPost.edit function (the official way)
        if (typeof inlineEditPost !== 'undefined') {
            var $wp_inline_edit = inlineEditPost.edit;
            inlineEditPost.edit = function(id) {
                // Call original function
                $wp_inline_edit.apply(this, arguments);
                
                // Get post ID
                var postId = 0;
                if (typeof(id) === 'object') {
                    postId = parseInt(this.getId(id));
                }
                
                if (postId > 0) {
                    // Get saved progress values from the table row
                    var $row = $('#post-' + postId);
                    var progressData = $row.find('.bw-cs-progress').attr('data-progress-values');
                    
                    if (progressData) {
                        try {
                            var progressValues = JSON.parse(progressData);
                            
                            // Wait a moment for inline edit form to render
                            setTimeout(function() {
                                $('.bw-progress-checkboxes-edit input[type="checkbox"]').prop('checked', false);
                                
                                if (Array.isArray(progressValues) && progressValues.length > 0) {
                                    progressValues.forEach(function(val) {
                                        $('.bw-progress-checkboxes-edit input[value="' + val + '"]').prop('checked', true);
                                    });
                                }
                            }, 50);
                        } catch(e) {
                            // Silent fail
                        }
                    }
                }
            };
        }
    });
    </script>
    <?php
});

// Save from Quick Edit / Bulk Edit
add_action('save_post', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // Check if this is a quick/bulk edit request
    if (!isset($_REQUEST['_inline_edit']) && !isset($_REQUEST['bulk_edit'])) {
        return;
    }

    // Verify post type is registered before checking capabilities
    $post_type = get_post_type($post_id);
    if (!$post_type || !post_type_exists($post_type)) return;

    if (!current_user_can('edit_post', $post_id)) return;

    // Check if this is bulk edit (skip empty values for bulk)
    $is_bulk_edit = isset($_REQUEST['bulk_edit']);

    // Text/select fields
    $fields = [
        'bw_altc_notes'         => 'sanitize_textarea_field',
        'bw_intent'             => 'sanitize_text_field',
        'bw_purpose'            => 'sanitize_text_field',
        'bw_index_status'       => 'sanitize_text_field',
        'content_plan'          => 'sanitize_text_field',
    ];

    foreach ($fields as $key => $sanitizer) {
        if (isset($_REQUEST[$key])) {
            $value = call_user_func($sanitizer, $_REQUEST[$key]);

            // For bulk edit: skip empty values (= "No Change")
            if ($is_bulk_edit && $value === '') {
                continue;
            }

            update_post_meta($post_id, $key, $value);
        }
    }
    
    // FIX: Pillar Page ID and Service Pathway ID - Handle separately to prevent clearing on bulk edit
    // These are integer fields, so we need special handling for empty strings
    if (isset($_REQUEST['bw_pillar_page_id'])) {
        $value = $_REQUEST['bw_pillar_page_id'];
        // For bulk edit: empty string means "No Change", don't update
        if ($is_bulk_edit && $value === '') {
            // Skip - don't clear existing value
        } else {
            // For quick edit or bulk edit with actual value
            $value = absint($value);
            update_post_meta($post_id, 'bw_pillar_page_id', $value);
        }
    }
    
    if (isset($_REQUEST['bw_service_pathway_id'])) {
        $value = $_REQUEST['bw_service_pathway_id'];
        // For bulk edit: empty string means "No Change", don't update
        if ($is_bulk_edit && $value === '') {
            // Skip - don't clear existing value
        } else {
            // For quick edit or bulk edit with actual value
            $value = absint($value);
            update_post_meta($post_id, 'bw_service_pathway_id', $value);
        }
    }
    
    // Handle workflow_progress (multi-select checkboxes)
    // Only update if the progress field was actually rendered in the form (marker field present)
    if (isset($_REQUEST['workflow_progress'])) {
        $progress = $_REQUEST['workflow_progress'];
        if (is_array($progress)) {
            $progress = array_map('sanitize_text_field', $progress);
            update_post_meta($post_id, 'workflow_progress', $progress);
        }
    } elseif (!$is_bulk_edit && isset($_REQUEST['workflow_progress_touched'])) {
        // Only clear if the progress section was rendered but no boxes checked
        // AND this is NOT a bulk edit
        update_post_meta($post_id, 'workflow_progress', []);
    }
    // If neither condition met, leave existing workflow_progress unchanged
});

// ==========================
// Editor Sidebar Meta Boxes
// ==========================
add_action('add_meta_boxes', function() {
    foreach (bw_cs_post_types() as $pt) {
        add_meta_box(
            'bw_content_strategy',
            'Content Management',
            'bw_cs_render_metabox',
            $pt,
            'side',
            'high'
        );
    }
});

function bw_cs_render_metabox($post) {
    wp_nonce_field('bw_cs_metabox', 'bw_cs_nonce');

    // Get current values
    $intent = get_post_meta($post->ID, 'bw_intent', true);
    $purpose = get_post_meta($post->ID, 'bw_purpose', true);
    $pillar = get_post_meta($post->ID, 'bw_pillar_page_id', true);
    $index_status = get_post_meta($post->ID, 'bw_index_status', true);
    $workflow_progress = get_post_meta($post->ID, 'workflow_progress', true);
    $content_plan = get_post_meta($post->ID, 'content_plan', true);
    
    if (!is_array($workflow_progress)) {
        $workflow_progress = [];
    }
    
    // Get pillar pages for dropdown
    $pillar_pages = get_posts([
        'post_type' => bw_cs_post_types(),
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_query' => [
            'relation' => 'OR',
            ['key' => 'bw_purpose', 'value' => 'pillar', 'compare' => '='],
            ['key' => 'bw_purpose', 'value' => 'service-page', 'compare' => '='],
            ['key' => 'bw_purpose', 'value' => 'product-page', 'compare' => '=']
        ]
    ]);
?>
    <style>
        .bw-cs-field { margin-bottom: 12px; }
        .bw-cs-field label { display: block; font-weight: 600; margin-bottom: 4px; }
        .bw-cs-field input,
        .bw-cs-field select,
        .bw-cs-field textarea { width: 100%; }
        .bw-cs-field textarea { min-height: 60px; }
        .bw-cs-help { font-size: 11px; color: #666; margin-top: 2px; }
        .bw-progress-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
        .bw-progress-tags label { 
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 8px; border-radius: 4px; 
            font-size: 11px; cursor: pointer; 
            background: #f5f5f5; color: #999;
            border: 1px solid #ddd;
            transition: all 0.15s ease;
            opacity: 0.6;
        }
        .bw-progress-tags label:hover { 
            opacity: 0.85;
            border-color: #bbb;
        }
        .bw-progress-tags label.is-selected {
            opacity: 1;
            font-weight: 600;
            border-color: currentColor;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .bw-progress-tags input[type="checkbox"] { 
            width: 14px; height: 14px; 
            margin: 0; cursor: pointer;
            accent-color: currentColor;
        }
    </style>

    <div class="bw-cs-field">
        <label for="bw_intent">Intent</label>
        <select id="bw_intent" name="bw_intent" style="width:100%;">
            <?php foreach (bw_cs_intent_options() as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($intent, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="bw-cs-help">User search intent</p>
    </div>

    <div class="bw-cs-field">
        <label for="bw_purpose">Purpose</label>
        <select id="bw_purpose" name="bw_purpose" style="width:100%;">
            <?php foreach (bw_cs_purpose_options() as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($purpose, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="bw-cs-help">Content purpose in strategy</p>
    </div>
    
    <div class="bw-cs-field">
        <label for="bw_pillar_page_id">Pillar Page</label>
        <select id="bw_pillar_page_id" name="bw_pillar_page_id">
            <option value="">Not Set</option>
            <?php foreach ($pillar_pages as $p):
                $p_purpose = get_post_meta($p->ID, 'bw_purpose', true);
                $type_labels = [
                    'service-page' => ' [Service]',
                    'product-page' => ' [Product]',
                    'pillar' => ' [Pillar]'
                ];
                $type = isset($type_labels[$p_purpose]) ? $type_labels[$p_purpose] : ' [Pillar]';
            ?>
                <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($pillar, $p->ID); ?>>
                    <?php echo esc_html(get_the_title($p) . $type); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="bw-cs-help">Link to parent pillar content</p>
    </div>
    
    <div class="bw-cs-field">
        <label>Progress</label>
        <div class="bw-progress-tags">
            <?php foreach (bw_cs_workflow_progress_options() as $key => $cfg): 
                $is_checked = in_array($key, $workflow_progress, true);
            ?>
                <label class="<?php echo $is_checked ? 'is-selected' : ''; ?>" style="<?php echo $is_checked ? 'color:' . esc_attr($cfg['color']) . ';background:' . esc_attr($cfg['bg']) . ';' : ''; ?>" data-color="<?php echo esc_attr($cfg['color']); ?>" data-bg="<?php echo esc_attr($cfg['bg']); ?>">
                    <input type="checkbox" name="workflow_progress[]" value="<?php echo esc_attr($key); ?>" <?php checked($is_checked); ?>>
                    <span><?php echo esc_html($cfg['label']); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="bw-cs-help">Track content creation stages</p>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('.bw-progress-tags input[type="checkbox"]').on('change', function() {
            var $label = $(this).closest('label');
            if (this.checked) {
                $label.addClass('is-selected')
                      .css({
                          'color': $label.data('color'),
                          'background': $label.data('bg')
                      });
            } else {
                $label.removeClass('is-selected')
                      .css({
                          'color': '#999',
                          'background': '#f5f5f5'
                      });
            }
        });
    });
    </script>
    
    <div class="bw-cs-field">
        <label for="content_plan">Next Step</label>
        <select id="content_plan" name="content_plan" style="width:100%;">
            <?php foreach (bw_cs_content_plan_options() as $key => $cfg): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($content_plan, $key); ?>>
                    <?php echo esc_html($cfg['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="bw-cs-help">Current content action status</p>
    </div>

    <div class="bw-cs-field">
        <label for="bw_index_status">Index Status</label>
        <select id="bw_index_status" name="bw_index_status" style="width:100%;">
            <?php foreach (bw_cs_index_status_options() as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($index_status, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="bw-cs-help">Search engine indexing status</p>
    </div>
    <?php
}

// Save meta box
add_action('save_post', function($post_id) {
    if (!isset($_POST['bw_cs_nonce']) || !wp_verify_nonce($_POST['bw_cs_nonce'], 'bw_cs_metabox')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    // Verify post type is registered before checking capabilities
    $post_type = get_post_type($post_id);
    if (!$post_type || !post_type_exists($post_type)) return;

    if (!current_user_can('edit_post', $post_id)) return;

    // Text/select fields
    $fields = [
        'bw_intent'             => 'sanitize_text_field',
        'bw_purpose'            => 'sanitize_text_field',
        'bw_pillar_page_id'     => 'absint',
        'bw_index_status'       => 'sanitize_text_field',
        'content_plan'          => 'sanitize_text_field',
    ];
    
    foreach ($fields as $key => $sanitizer) {
        if (isset($_POST[$key])) {
            update_post_meta($post_id, $key, call_user_func($sanitizer, $_POST[$key]));
        }
    }
    
    // Handle workflow_progress (multi-select checkboxes)
    if (isset($_POST['workflow_progress']) && is_array($_POST['workflow_progress'])) {
        $progress = array_map('sanitize_text_field', $_POST['workflow_progress']);
        update_post_meta($post_id, 'workflow_progress', $progress);
    } else {
        // If no checkboxes selected, save empty array
        update_post_meta($post_id, 'workflow_progress', []);
    }
}, 10);

/**
 * Injects content strategy metadata into GA4 tracking
 *
 * VERIFICATION: These custom dimensions ARE being sent to GA4
 * ================================================================
 * WordPress Meta Field → GA4 Parameter Name → Example Value
 * ----------------------------------------------------------------
 * bw_page_topic       → content_topic         → "SEO Services" [DEPRECATED - kept for GA4 legacy data]
 * bw_intent           → content_intent        → "informational"
 * bw_purpose          → content_purpose       → "pillar"
 * bw_pillar_page_id   → pillar_page           → "About Us"
 *
 * These dimensions are automatically included in ALL GA4 events:
 * - page_view events (brighter-ga4-enhanced.js:277)
 * - click events (brighter-ga4-enhanced.js:204)
 * - impression tracking (brighter-ga4-enhanced.js:229)
 * - form interactions (brighter-ga4-enhanced.js:252, 262)
 *
 * The data flows: WordPress → window.brighterSCOS → getBaseParams() → gtag()
 */

/**
 * DATA INJECTION HANDLED BY scos-car-injection.php
 * 
 * Content strategy metadata is injected by brighter-core/includes/scos-car-injection.php
 * as part of the SCOS CAR (Content Architecture Record) structure.
 * 
 * The SCOS CAR provides:
 * - Single source of truth for content metadata (window.brighterSCOS)
 * - Machine-readable structure for AI agents
 * - All GA4 tracking scripts use window.brighterSCOS directly
 * - Reduced code complexity and better maintainability
 * 
 * The data flows: WordPress → window.brighterSCOS → getBaseParams() → gtag()
 * 
 * See: brighter-core/includes/scos-car-injection.php
 */
