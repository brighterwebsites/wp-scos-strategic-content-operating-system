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
    
    register_post_meta('', 'bw_notes', $string_args);
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
    
    // Optimization status
    register_post_meta('', '_brt_opt_status', [
        'type'          => 'string',
        'single'        => true,
        'show_in_rest'  => false,
        'auth_callback' => function() { return current_user_can('edit_posts'); },
    ]);

    // Index status
    register_post_meta('', 'bw_index_status', [
        'type'          => 'string',
        'single'        => true,
        'show_in_rest'  => false,
        'auth_callback' => function() { return current_user_can('edit_posts'); },
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
    $exclude = ['attachment', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation'];
    
    return array_values(array_diff($post_types, $exclude));
}


//Page Intent - add or modify your own - also maps to GA4 Automatically at the page/Post level
function bw_cs_intent_options() {
    return [
        ''              => 'NA',
        'informational' => 'Informational',
        'commercial' => 'Commercial',
        'transactional' => 'Transactional',
        'navigational'  => 'Navigational',
        'retention'     => 'Retention',
        'support'    => 'Support',  //Content designed to guide a new customer (post-sale) or a user of a tool.
        'trust'         => 'E-E-A-T/Trust', //measures content focused on building authority 
        'functional'    => 'System/Functional',

        'informational_p' => 'Informational (Problem)', 
        'informational_s' => 'Informational (Solution)',
        'commercial_ds' => 'Commercial (Decision Support)', // Replaces commercial_c and commercial_r
        


    ];
}

function bw_cs_purpose_options() {
    return [
 	''              => 'NA',
        'pillar'        => 'Pillar',             // High-level topic hub (e.g., "The Complete Stable Buyer's Guide")
        'service-page'  => 'Service Page',       // Page detailing a service (e.g., "Shed-to-Stable Retrofit Conversion")
        'product-page'  => 'Product Page',       // Page detailing a specific model (e.g., "4x4m Modular Stable Kit")
        'supporting'    => 'Supporting',         // Standard blog article (e.g., The article we just wrote)
        'case-study'    => 'Case Study',         // Specific client success story (e.g., "4-Bay Build in Tamworth")
        'conversion-hub'=> 'Conversion Hub',     // High-friction conversion page (e.g., "Get a Quote" form, Pricing Tool)
        'resource-guide'=> 'Resource/Guide',     // Lead magnet delivery (e.g., "Download Stable Buyer's Guide")
        'authority-page'=> 'Authority Page',     // E-E-A-T pages (e.g., "About Us," "Our Welding Process")
        'location-page' => 'Location Page',      // Geo-targeted pages (e.g., "Horse Stables Brisbane")
        'industry-page' => 'Industry Page',      // Persona-targeted content (e.g., "Stables for Breeding Facilities")
        'landing-page'  => 'Landing Page',       // Generic landing page for paid campaigns
        'terms'         => 'Legal/Terms',        // Standard functional page
    
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
    ];
}

function bw_cs_opt_status_options() {
    return [
        ''              => ['label' => '� No status �', 'color' => '#6b7280', 'bg' => '#f3f4f6'],  // Grey: Default / Unassigned

        // --- Workflow Stages (In Progress) ---
        'idea'          => ['label' => 'ℹ️ Idea', 'color' => '#4338ca', 'bg' => '#e0e7ff'],  // Indigo-700: Early planning / concept
        'draft'         => ['label' => '🆕 Drafted', 'color' => '#4f46e5', 'bg' => '#eef2ff'],  // Indigo-600: In progress content, not yet ready for publishing

        // --- Testing & Strategic Action ---
        'cont'          => ['label' => '🧾 Content Only', 'color' => '#be185d', 'bg' => '#fce7f3'],  // Rose-700: Content done is published
        'seo_basic'     => ['label' => '🚀 SEO Basic', 'color' => '#b45309', 'bg' => '#fef3c7'],  // Amber-700: Basic Onpage SEO is done
        'cro'           => ['label' => '📲 Conversion', 'color' => '#1d4ed8', 'bg' => '#dbeafe'],  // Blue-700: Analytics/UX optimisation is done

        // --- SEO Optimised Status (Green Zone) ---
        'op60'          => ['label' => '⭐ Optimised 60+', 'color' => '#15803d', 'bg' => '#dcfce7'],  // Green-700: At or Above target NeuronWriter keyword optimised
        'op70'          => ['label' => '🎯 Optimised 70+', 'color' => '#065f46', 'bg' => '#a7f3d0'],  // Emerald-800: Tech SEO Implemented
        'op80'          => ['label' => '💎 Optimised 80+', 'color' => '#7e22ce', 'bg' => '#f3e8ff'],  // Purple-700: Tech SEO + Conversion

        // --- Content Inventory Decisions (Archival) ---
        'attention'     => ['label' => '📉 Low Perf', 'color' => '#9d174d', 'bg' => '#fbcfe8'],  // Rose-800: Low Performing + Needs investigation
        'urgent'        => ['label' => '📌 Urgent Attention', 'color' => '#b91c1c', 'bg' => '#fee2e2'],  // Red-700: High Pri Low Performing any status
        'ctr'           => ['label' => '🚩 Visibility', 'color' => '#0f766e', 'bg' => '#ccfbf1'],  // Teal-700: Pri for Meta and or Tech SEO Needs improvement + is planned
        'repurpose'     => ['label' => '🔄 Repurpose', 'color' => '#b45309', 'bg' => '#fcefd5'],  // Pri for Content improvement + is planned
    ];
}

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
                    $new['bw_pillar']  = 'Pillar';
                    $new['bw_intent']  = 'Intent';
                    $new['bw_purpose'] = 'Purpose';
                    $new['bw_index']   = 'Index Status';
                    $new['bw_opt']     = 'Optimization';
                    $new['bw_notes']   = 'Content Notes';
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
                case 'bw_topic':
                    echo '<span class="bw-cs-text" data-post="' . esc_attr($post_id) . '" data-field="bw_page_topic">' 
                         . esc_html(get_post_meta($post_id, 'bw_page_topic', true)) . '</span>';
                    break;
                    
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
                    
                case 'bw_opt':
                    $val = get_post_meta($post_id, '_brt_opt_status', true);
                    $opts = bw_cs_opt_status_options();
                    if (!isset($opts[$val])) $val = '';
                    $cfg = $opts[$val];
                    printf(
                        '<span class="bw-opt-badge" data-post="%d" data-value="%s" title="Click to edit" style="display:inline-block;border-radius:999px;padding:.2em .6em;font-size:12px;font-weight:600;color:%s;background:%s;cursor:pointer;">%s</span>',
                        $post_id,
                        esc_attr($val),
                        esc_attr($cfg['color']),
                        esc_attr($cfg['bg']),
                        esc_html($cfg['label'])
                    );
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
                        '' => ['color' => '#6b7280', 'bg' => '#f3f4f6']               // Grey - Not Set
                    ];
                    $color = $colors[$val] ?? $colors[''];
                    echo '<span class="bw-cs-select" data-post="' . esc_attr($post_id) . '" data-field="bw_index_status" style="display:inline-block;border-radius:3px;padding:3px 8px;font-size:11px;font-weight:600;color:' . esc_attr($color['color']) . ';background:' . esc_attr($color['bg']) . ';cursor:pointer;">'
                         . esc_html($label) . '</span>';
                    break;

		// LOCATION 1: In the admin column display (around line 160)
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
                case 'bw_notes':
                    $notes = get_post_meta($post_id, 'bw_notes', true);
    // Manual truncation that's more reliable with special characters
    $display_notes = mb_strlen($notes) > 60 
        ? mb_substr($notes, 0, 60) . '�' 
        : $notes;
    echo '<span class="bw-cs-text" data-post="' . esc_attr($post_id) . '" data-field="bw_notes">'
         . esc_html($display_notes) . '</span>';
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
        $cols['bw_topic']   = 'bw_page_topic';
        $cols['bw_intent']  = 'bw_intent';
        $cols['bw_purpose'] = 'bw_purpose';
        $cols['bw_opt']     = '_brt_opt_status';
        $cols['bw_index']   = 'bw_index_status';
        $cols['bw_pillar']  = 'bw_pillar_page_id';
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
    if (in_array($orderby, ['bw_page_topic', 'bw_intent', 'bw_purpose', '_brt_opt_status', 'bw_index_status', 'bw_pillar_page_id'], true)) {
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
    
    wp_register_script('bw-cs-inline', false, ['jquery'], '1.2', true);
    wp_add_inline_script('bw-cs-inline', '(function($){
        const nonce = "' . esc_js(wp_create_nonce('bw_cs_inline')) . '";
        const intentOpts = ' . wp_json_encode(bw_cs_intent_options()) . ';
        const purposeOpts = ' . wp_json_encode(bw_cs_purpose_options()) . ';
        const optOpts = ' . wp_json_encode(bw_cs_opt_status_options()) . ';
        const indexOpts = ' . wp_json_encode(bw_cs_index_status_options()) . ';
        const pillarOpts = ' . wp_json_encode($pillar_opts) . ';
        
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
        
        // Optimization status
        $(document).on("click", ".bw-opt-badge", function(e) {
            e.preventDefault();
            const $span = $(this);
            const postId = $span.data("post");
            const current = $span.data("value") || "";
            
            const $select = $("<select>", {
                class: "bw-opt-dropdown",
                "data-post": postId,
                css: { fontSize: "12px" }
            });
            
            $.each(optOpts, function(key, cfg) {
                $select.append($("<option>", {
                    value: key,
                    text: cfg.label,
                    selected: key === current
                }));
            });
            
            $span.replaceWith($select);
            $select.focus();
        });
        
        $(document).on("change blur", ".bw-opt-dropdown", function(e) {
            const $select = $(this);
            const postId = $select.data("post");
            const value = $select.val();
            const cfg = optOpts[value] || optOpts[""];
            
            $select.prop("disabled", true);
            saveField(postId, "_brt_opt_status", value).done(function(resp) {
                if (resp && resp.success) {
                    const $span = $("<span>", {
                        class: "bw-opt-badge",
                        "data-post": postId,
                        "data-value": value,
                        title: "Click to edit",
                        text: cfg.label,
                        css: {
                            display: "inline-block",
                            borderRadius: "999px",
                            padding: ".2em .6em",
                            fontSize: "12px",
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
                    const label = pillarOpts[value] || "�";
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
        
    })(jQuery);');
    wp_enqueue_script('bw-cs-inline');
    
    // Column width CSS
    wp_add_inline_style('common', '
        .fixed .column-bw_topic { width: 140px; }
        .fixed .column-bw_intent { width: 110px; }
        .fixed .column-bw_purpose { width: 120px; }
        .fixed .column-bw_opt { width: 130px; }
        .fixed .column-bw_pillar { width: 140px; }
        .fixed .column-bw_notes { width: 160px; }
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
    
    $allowed = ['bw_notes', 'bw_page_topic', 'bw_intent', 'bw_purpose', 'bw_pillar_page_id', '_brt_opt_status', 'bw_index_status'];
    if (!in_array($field, $allowed, true)) {
        wp_send_json_error('Invalid field');
    }
    
    if ($field === 'bw_pillar_page_id') {
        $value = absint($value);
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
    if (!in_array($col, ['bw_topic', 'bw_notes', 'bw_intent', 'bw_purpose', 'bw_pillar', 'bw_opt', 'bw_index'], true)) return;
    if (!in_array($post_type, bw_cs_post_types(), true)) return;

    // Detect if this is bulk edit (hide notes field for bulk edit)
    $is_bulk = (current_filter() === 'bulk_edit_custom_box');

    // Hide notes field in bulk edit
    if ($is_bulk && $col === 'bw_notes') return;

    $pillar_pages = get_posts([
    'post_type' => bw_cs_post_types(),
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'orderby' => 'title',
    'order' => 'ASC',
    'meta_query' => [
        'relation' => 'OR',
        [
            'key' => 'bw_purpose',
            'value' => 'pillar',
            'compare' => '='
        ],
        [
            'key' => 'bw_purpose',
            'value' => 'service-page',
            'compare' => '='
        ],
        [
            'key' => 'bw_purpose',
            'value' => 'product-page',
            'compare' => '='
        ]
    ]
]);    ?>
    <fieldset class="inline-edit-col-left">
        <div class="inline-edit-col">
            <?php if ($col === 'bw_topic'): ?>
                <label><span class="title">Topic</span>
                    <input type="text" name="bw_page_topic" value="" placeholder="<?php echo $is_bulk ? 'Leave blank = No change' : ''; ?>">
                </label>
            <?php elseif ($col === 'bw_notes'): ?>
                <label><span class="title">Content Notes</span>
                    <textarea name="bw_notes" rows="2"></textarea>
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
            <?php elseif ($col === 'bw_opt'): ?>
                <label><span class="title">Optimization</span>
                    <select name="_brt_opt_status">
                        <?php if ($is_bulk): ?>
                            <option value="">-- No Change --</option>
                        <?php endif; ?>
                        <?php foreach (bw_cs_opt_status_options() as $key => $cfg): ?>
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
        var $qe = inlineEditPost.edit;
        inlineEditPost.edit = function(id) {
            $qe.apply(this, arguments);
            var postId = (typeof id === 'object') ? this.getId(id) : id;
            var $row = $('#post-' + postId);
            
            $('input[name="bw_page_topic"]', '.inline-edit-row').val($row.find('.bw-cs-text[data-field="bw_page_topic"]').text().trim());
            $('textarea[name="bw_notes"]', '.inline-edit-row').val($row.find('.bw-cs-text[data-field="bw_notes"]').text().trim());
            
            var intent = $row.find('.bw-cs-select[data-field="bw_intent"]').text().trim();
            $('select[name="bw_intent"] option', '.inline-edit-row').filter(function() {
                return $(this).text() === intent;
            }).prop('selected', true);
            
            var purpose = $row.find('.bw-cs-select[data-field="bw_purpose"]').text().trim();
            $('select[name="bw_purpose"] option', '.inline-edit-row').filter(function() {
                return $(this).text() === purpose;
            }).prop('selected', true);
            
            var optVal = $row.find('.bw-opt-badge').data('value') || '';
            $('select[name="_brt_opt_status"]', '.inline-edit-row').val(optVal);

            var indexStatus = $row.find('.bw-cs-select[data-field="bw_index_status"]').text().trim();
            $('select[name="bw_index_status"] option', '.inline-edit-row').filter(function() {
                return $(this).text() === indexStatus;
            }).prop('selected', true);
        };
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

    $fields = [
        'bw_notes'          => 'sanitize_textarea_field',
        'bw_page_topic'     => 'sanitize_text_field',
        'bw_intent'         => 'sanitize_text_field',
        'bw_purpose'        => 'sanitize_text_field',
        'bw_pillar_page_id' => 'absint',
        '_brt_opt_status'   => 'sanitize_text_field',
        'bw_index_status'   => 'sanitize_text_field',
    ];

    foreach ($fields as $key => $sanitizer) {
        if (isset($_REQUEST[$key])) {
            $value = call_user_func($sanitizer, $_REQUEST[$key]);

            // For bulk edit: skip empty values (= "No Change")
            // Exception: bw_page_topic can be intentionally cleared
            if ($is_bulk_edit && $value === '' && $key !== 'bw_page_topic') {
                continue;
            }

            update_post_meta($post_id, $key, $value);
        }
    }
});

// ==========================
// Editor Sidebar Meta Boxes
// ==========================
add_action('add_meta_boxes', function() {
    foreach (bw_cs_post_types() as $pt) {
        add_meta_box(
            'bw_content_strategy',
            'Content Strategy',
            'bw_cs_render_metabox',
            $pt,
            'side',
            'high'
        );
    }
});

function bw_cs_render_metabox($post) {
    wp_nonce_field('bw_cs_metabox', 'bw_cs_nonce');
    
    $topic = get_post_meta($post->ID, 'bw_page_topic', true);
    $intent = get_post_meta($post->ID, 'bw_intent', true);
    $purpose = get_post_meta($post->ID, 'bw_purpose', true);
    $pillar = get_post_meta($post->ID, 'bw_pillar_page_id', true);
    $notes = get_post_meta($post->ID, 'bw_notes', true);
    $opt = get_post_meta($post->ID, '_brt_opt_status', true);
    
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
    </style>
    
    <div class="bw-cs-field">
        <label for="bw_page_topic">Topic</label>
        <input type="text" id="bw_page_topic" name="bw_page_topic" value="<?php echo esc_attr($topic); ?>" placeholder="e.g., Web Design, SEO">
        <p class="bw-cs-help">Main topic/theme of this content</p>
    </div>

    <div class="bw-cs-field">
        <label for="bw_intent">Intent</label>
        <select id="bw_intent" name="bw_intent" style="width:100%;">
            <?php foreach (bw_cs_intent_options() as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($intent, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <p class="bw-cs-help">User search intent</p>

    <div class="bw-cs-field">
        <label for="bw_purpose">Purpose</label>
        <select id="bw_purpose" name="bw_purpose" style="width:100%;">
            <?php foreach (bw_cs_purpose_options() as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($purpose, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <p class="bw-cs-help">Content purpose in strategy</p>
    <p class="bw-cs-help" style="margin-top: 6px; padding: 8px; background: #e7f5fe; border-left: 3px solid #00a0d2;">
        <strong>💡 Tip:</strong> Diversifying content types (case studies, resource guides, etc.) within a topic reduces cannibalization risk.
    </p>
    
    <div class="bw-cs-field">
        <label for="bw_pillar_page_id">Pillar Page</label>
	<select id="bw_pillar_page_id" name="bw_pillar_page_id">
    		<option value="">Not Set</option>
    		<?php foreach ($pillar_pages as $p):
        		$purpose = get_post_meta($p->ID, 'bw_purpose', true);
        		$type_labels = [
        		    'service-page' => ' [Service]',
        		    'product-page' => ' [Product]',
        		    'pillar' => ' [Pillar]'
        		];
        		$type = isset($type_labels[$purpose]) ? $type_labels[$purpose] : ' [Pillar]';
    		?>
        	<option value="<?php echo esc_attr($p->ID); ?>" <?php selected($pillar, $p->ID); ?>>
            <?php echo esc_html(get_the_title($p) . $type); ?>
        </option>
    <?php endforeach; ?>
</select>        <p class="bw-cs-help">Link to parent pillar content</p>
    </div>
    
    <div class="bw-cs-field">
        <label for="_brt_opt_status">Optimization Status</label>
        <select id="_brt_opt_status" name="_brt_opt_status" style="width:100%;">
            <?php foreach (bw_cs_opt_status_options() as $key => $cfg): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($opt, $key); ?>>
                    <?php echo esc_html($cfg['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="bw-cs-help">Internal tracking only</p>
    </div>

    <div class="bw-cs-field">
        <label for="bw_index_status">Index Status</label>
        <select id="bw_index_status" name="bw_index_status" style="width:100%;">
            <?php
            $index_status = get_post_meta($post->ID, 'bw_index_status', true);
            foreach (bw_cs_index_status_options() as $key => $label):
            ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($index_status, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="bw-cs-help">Search engine indexing status</p>
    </div>

    <div class="bw-cs-field">
        <label for="bw_notes">Content Notes</label>
        <textarea id="bw_notes" name="bw_notes" rows="3" placeholder="Internal notes..."><?php echo esc_textarea($notes); ?></textarea>
        <p class="bw-cs-help">Optional internal note about content strategy</p>
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
    
    $fields = [
        'bw_notes'          => 'sanitize_textarea_field',
        'bw_page_topic'     => 'sanitize_text_field',
        'bw_intent'         => 'sanitize_text_field',
        'bw_purpose'        => 'sanitize_text_field',
        'bw_pillar_page_id' => 'absint',
        '_brt_opt_status'   => 'sanitize_text_field',
        'bw_index_status'   => 'sanitize_text_field',
    ];
    
    foreach ($fields as $key => $sanitizer) {
        if (isset($_POST[$key])) {
            update_post_meta($post_id, $key, call_user_func($sanitizer, $_POST[$key]));
        }
    }
}, 10);

/**
 * Injects content strategy metadata into GA4 tracking
 *
 * VERIFICATION: These custom dimensions ARE being sent to GA4
 * ================================================================
 * WordPress Meta Field → GA4 Parameter Name → Example Value
 * ----------------------------------------------------------------
 * bw_page_topic       → content_topic         → "SEO Services"
 * bw_intent           → content_intent        → "informational"
 * bw_purpose          → content_purpose       → "pillar"
 * _brt_opt_status     → optimization_status   → "cro"
 * bw_pillar_page_id   → pillar_page           → "About Us"
 *
 * These dimensions are automatically included in ALL GA4 events:
 * - page_view events (brighter-ga4-enhanced.js:277)
 * - click events (brighter-ga4-enhanced.js:204)
 * - impression tracking (brighter-ga4-enhanced.js:229)
 * - form interactions (brighter-ga4-enhanced.js:252, 262)
 *
 * The data flows: WordPress → window.brighterContentStrategy → getBaseParams() → gtag()
 */

add_action('wp_head', function() {
    // Only run on singular posts/pages
    if (!is_singular()) return;
    
    $post_id = get_the_ID();
    
    // Get content strategy metadata
    $intent = get_post_meta($post_id, 'bw_intent', true);
    $purpose = get_post_meta($post_id, 'bw_purpose', true);
    $topic = get_post_meta($post_id, 'bw_page_topic', true);
    $opt_status = get_post_meta($post_id, '_brt_opt_status', true);
    $pillar_id = get_post_meta($post_id, 'bw_pillar_page_id', true);
    
    // Get pillar info
    $pillar_name = '';
    $pillar_type = 'none';
    if ($pillar_id) {
        $pillar_name = get_the_title($pillar_id);
        $pillar_purpose = get_post_meta($pillar_id, 'bw_purpose', true);
        $pillar_type = ($pillar_purpose === 'service-page') ? 'service' : 'pillar';
    }
    
    // RECOMMENDED: Option 1 - "not_set" for everything
    $defaults = [
        'intent'     => $intent ?: 'not_set',
        'purpose'    => $purpose ?: 'not_set',
        'topic'      => $topic ?: 'not_set',
        'opt_status' => $opt_status ?: 'not_set',
        'pillar'     => $pillar_name ?: 'not_set', // Could use site name instead
        'pillar_type' => $pillar_type
    ];
    
    // Inject into window object for GA4 to pick up
    ?>
    <script>
    window.brighterContentStrategy = {
        content_intent: <?php echo json_encode($intent); ?>,
        content_purpose: <?php echo json_encode($purpose); ?>,
        content_topic: <?php echo json_encode($topic); ?>,
        optimization_status: <?php echo json_encode($opt_status); ?>,
        pillar_page: <?php echo json_encode($pillar_name); ?>,
        pillar_type: <?php echo json_encode($pillar_type); ?>,
        post_type: <?php echo json_encode(get_post_type()); ?>
    };
    </script>
    <?php
}, 5); // Priority 5 to load before GA4 script
