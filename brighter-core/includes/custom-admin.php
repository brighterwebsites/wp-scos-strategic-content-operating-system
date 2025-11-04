<?php
/**
 * Brighter Tools: Custom Admin
 *
 * File: custom-admin.php
 * Purpose: Enhancements and modifications to the WordPress admin UI.
 *
 * Version: 4.0.0
 *
 * Responsibilities:
 * - 
 * - 
 * - 
 *
 * Notes:
 * - Part of the Brighter Support Tools for Client Sites MU plugin 
 * - Loaded automatically by /mu-plugins/brighter-core.php
 */

// Brighter Tools: Admin UI Enhancements
// Registers Page Taxonomy
// Adds Excerpts for pages
// Optimisation Status – editor sidebar + admin column + inline edit + quick/bulk edit

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Ensure our args win even if another plugin registers the same taxonomy first.
 * Runs before register_taxonomy is processed.
 */
add_filter('register_taxonomy_args', function ($args, $taxonomy){
  if ($taxonomy !== 'pagetype') return $args;

  // Admin-only taxonomy with REST for editors like Breakdance
  $args['public']              = false;
  $args['publicly_queryable']  = false;
  $args['rewrite']             = false;
  $args['show_ui']             = true;
  $args['show_in_menu']        = true;
  $args['show_in_nav_menus']   = false;
  $args['show_admin_column']   = true;
  $args['show_in_quick_edit']  = true;
  $args['show_tagcloud']       = false;
  $args['show_in_rest']        = true;     // for Breakdance/Gutenberg visibility
  $args['hierarchical']        = true;
  $args['default_term']        = array('name' => 'General');
  // Capabilities: only admins manage, editors can assign
  $args['capabilities'] = array(
    'manage_terms' => 'manage_options',    // Admins
    'edit_terms'   => 'manage_options',
    'delete_terms' => 'manage_options',
    'assign_terms' => 'edit_pages',        // Editors and above
  );

  // Labels and textdomain
  $args['labels'] = array(
    'name'          => esc_html__('Page Types', 'brighterwebsites'),
    'singular_name' => esc_html__('Page Type', 'brighterwebsites'),
    'menu_name'     => esc_html__('Page Types', 'brighterwebsites'),
    'all_items'     => esc_html__('All Page Types', 'brighterwebsites'),
    'edit_item'     => esc_html__('Edit Page Type', 'brighterwebsites'),
    'view_item'     => esc_html__('View Page Type', 'brighterwebsites'),
    'add_new_item'  => esc_html__('Add new Page Type', 'brighterwebsites'),
    'new_item_name' => esc_html__('New Page Type name', 'brighterwebsites'),
    'search_items'  => esc_html__('Search Page Types', 'brighterwebsites'),
    'not_found'     => esc_html__('No Page Types found', 'brighterwebsites'),
  );

  return $args;
}, 10, 2);

/**
 * Register the taxonomy on init with a later priority so it runs after CPT UI.
 */
add_action('init', function () {
  if (!taxonomy_exists('pagetype')) {
    register_taxonomy('pagetype', array('page'), array()); // args are overridden by the filter above
  } else {
    // Make sure Pages are attached even if someone else registered it
    register_taxonomy_for_object_type('pagetype', 'page');
  }
}, 40);

/**
 * Admin guards: everything below only runs in wp-admin.
 */
if (is_admin()) {

  // Pages list: filter dropdown
  add_action('restrict_manage_posts', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (empty($screen) || $screen->post_type !== 'page' || !taxonomy_exists('pagetype')) return;

    $selected      = isset($_GET['pagetype']) ? sanitize_text_field($_GET['pagetype']) : '';
    $info_taxonomy = get_taxonomy('pagetype');

    wp_dropdown_categories(array(
      'show_option_all' => sprintf(esc_html__('Show all %s', 'brighterwebsites'), $info_taxonomy->label),
      'taxonomy'        => 'pagetype',
      'name'            => 'pagetype',
      'orderby'         => 'name',
      'selected'        => $selected,
      'show_count'      => true,
      'hide_empty'      => false,
      'hierarchical'    => true,
      'value_field'     => 'term_id',
    ));
  });

  // Convert term_id to slug for the list table query
  add_filter('parse_query', function ($query) {
    if (!is_admin() || !function_exists('get_current_screen')) return;
    $screen = get_current_screen();
    if (empty($screen) || $screen->base !== 'edit' || $screen->post_type !== 'page') return;

    if (isset($query->query_vars['pagetype']) && is_numeric($query->query_vars['pagetype']) && intval($query->query_vars['pagetype']) > 0) {
      $term = get_term_by('id', intval($query->query_vars['pagetype']), 'pagetype');
      if ($term && !is_wp_error($term)) {
        $query->query_vars['pagetype'] = $term->slug;
      }
    }
  });

  // Pages list: column display
  add_filter('manage_pages_columns', function ($cols) {
    $cols['pagetype'] = esc_html__('Page Type', 'brighterwebsites');
    return $cols;
  });
  add_action('manage_pages_custom_column', function ($col, $post_id) {
    if ($col !== 'pagetype') return;
    $terms = get_the_terms($post_id, 'pagetype');
    if (is_wp_error($terms) || empty($terms)) { echo '�'; return; }
    echo esc_html(join(', ', wp_list_pluck($terms, 'name')));
  }, 10, 2);

  // Make default term stick on new pages
  add_action('save_post_page', function ($post_id, $post, $update) {
    if ($update || wp_is_post_revision($post_id)) return;
    if (!has_term('', 'pagetype', $post_id)) {
      $term = get_term_by('name', 'General', 'pagetype');
      if ($term && !is_wp_error($term)) {
        wp_set_object_terms($post_id, array((int) $term->term_id), 'pagetype', false);
      }
    }
  }, 10, 3);
}

/**
 * Belt and braces. Block accidental front-end taxonomy archive.
 */
add_action('template_redirect', function () {
  if (is_tax('pagetype')) {
    wp_redirect(home_url(), 301);
    exit;
  }
});

// show_in_rest is true. That lets Breakdance conditions, queries, or template rules see and target pagetype terms. If helpful, you can also add classes to the <body> for easy CSS targeting:

add_filter('body_class', function($classes){
  if (is_page()) {
    $terms = get_the_terms(get_the_ID(), 'pagetype');
    if ($terms && !is_wp_error($terms)) {
      foreach ($terms as $t) $classes[] = 'pagetype-' . sanitize_html_class($t->slug);
    }
  }
  return $classes;
});


/**
 * Excerpts for pages
 */
add_action('init', function () {
  add_post_type_support('page', 'excerpt');
});




/**
 * Brighter: Optimisation Status – editor sidebar + admin column + inline edit + quick/bulk edit
 */

if (!defined('ABSPATH')) exit;

/* ---------------------------
   Meta registration
---------------------------- */
add_action('init', function () {
	register_post_meta('', '_brt_opt_status', [
		'type'          => 'string',
		'single'        => true,
		'show_in_rest'  => false,
		'auth_callback' => function() { return current_user_can('edit_posts'); },
	]);
});

function brt_opt_status_post_types() { return ['post', 'page']; }

function brt_opt_status_options() {
	return [
		''           => ['label' => '— No status —',   'color' => '#6b7280', 'bg' => '#f3f4f6'],
		'done'       => ['label' => 'Draft',            'color' => '#166534', 'bg' => '#dcfce7'],
		'op90'       => ['label' => 'Optimised 90+',   'color' => '#065f46', 'bg' => '#ccfbf1'],
		'op80'       => ['label' => 'Optimised 80+',   'color' => '#1e40af', 'bg' => '#dbeafe'],
		'op70'       => ['label' => 'Optimised 70+',   'color' => '#7c2d12', 'bg' => '#ffedd5'],
		'improve'    => ['label' => 'Improve',         'color' => '#92400e', 'bg' => '#fef3c7'],
		'leave'      => ['label' => 'Leave',           'color' => '#374151', 'bg' => '#e5e7eb'],
		'consolidate'=> ['label' => 'Consolidate',     'color' => '#6b21a8', 'bg' => '#f3e8ff'],
		'repurpose'  => ['label' => 'Repurpose',       'color' => '#9a3412', 'bg' => '#ffedd5'],
	];
}

/* ---------------------------
   Editor sidebar meta box
---------------------------- */
add_action('add_meta_boxes', function () {
	foreach (brt_opt_status_post_types() as $pt) {
		add_meta_box('brt_opt_status','Optimisation Status','brt_opt_status_metabox_cb',$pt,'side','high');
	}
});
function brt_opt_status_metabox_cb($post) {
	wp_nonce_field('brt_opt_status_save','brt_opt_status_nonce');
	$current = get_post_meta($post->ID,'_brt_opt_status',true);
	echo '<select name="brt_opt_status_field" id="brt_opt_status_field" style="width:100%;">';
	foreach (brt_opt_status_options() as $val=>$cfg) {
		printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($current,$val,false), esc_html($cfg['label']));
	}
	echo '</select><p style="margin:.5em 0 0;color:#6b7280;">Internal only.</p>';
}
add_action('save_post', function ($post_id) {
	if (!isset($_POST['brt_opt_status_nonce']) || !wp_verify_nonce($_POST['brt_opt_status_nonce'],'brt_opt_status_save')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!current_user_can('edit_post',$post_id)) return;
	$val = isset($_POST['brt_opt_status_field']) ? sanitize_text_field($_POST['brt_opt_status_field']) : '';
	$opts = brt_opt_status_options();
	update_post_meta($post_id,'_brt_opt_status', array_key_exists($val,$opts) ? $val : '');
});

/* ---------------------------
   Admin column (click-to-edit)
---------------------------- */
add_action('admin_init', function (){
	foreach (brt_opt_status_post_types() as $pt) {
		add_filter("manage_edit-{$pt}_columns", function ($cols) {
			$new = [];
			foreach ($cols as $k=>$v) { $new[$k]=$v; if ($k==='title') $new['brt_opt_status']='Optimisation'; }
			return $new;
		});
		add_action("manage_{$pt}_posts_custom_column", function ($col,$post_id) {
			if ($col!=='brt_opt_status') return;
			$opts = brt_opt_status_options();
			$val  = get_post_meta($post_id,'_brt_opt_status',true);
			if (!isset($opts[$val])) $val='';
			$cfg  = $opts[$val];
			printf(
				'<span class="brt-badge brt-badge-edit" data-post="%d" data-value="%s" title="Click to edit" style="display:inline-block;border-radius:999px;padding:.2em .6em;font-size:12px;font-weight:600;color:%s;background:%s;cursor:pointer;">%s</span>',
				$post_id, esc_attr($val), esc_attr($cfg['color']), esc_attr($cfg['bg']), esc_html($cfg['label'])
			);
		},10,2);
	}
});

/* ---------------------------
   Sortable column
---------------------------- */
add_filter('manage_edit-post_sortable_columns', fn($c)=> ($c['brt_opt_status']='brt_opt_status') ? $c : $c);
add_filter('manage_edit-page_sortable_columns', fn($c)=> ($c['brt_opt_status']='brt_opt_status') ? $c : $c);
add_action('pre_get_posts', function ($q){
	if (!is_admin() || !$q->is_main_query()) return;
	if ($q->get('orderby')==='brt_opt_status') { $q->set('meta_key','_brt_opt_status'); $q->set('orderby','meta_value'); }
});

/* ---------------------------
   Inline click-to-edit (AJAX)
---------------------------- */
add_action('admin_enqueue_scripts', function ($hook){
	if ($hook!=='edit.php') return;
	$screen = get_current_screen();
	if (!$screen || !in_array($screen->post_type, brt_opt_status_post_types(), true)) return;

	$opts = brt_opt_status_options();
	wp_register_script('brt-opt-inline', false, ['jquery'], '1.0', true);
	wp_add_inline_script('brt-opt-inline', '(function($){
		const optMap = '.wp_json_encode($opts).';
		const nonce  = "'.esc_js(wp_create_nonce('brt_opt_status_inline')).'";

		function renderBadge(val){
			if(!optMap[val]) val="";
			const cfg = optMap[val];
			return `<span class="brt-badge brt-badge-edit" data-value="${val}" title="Click to edit"
				style="display:inline-block;border-radius:999px;padding:.2em .6em;font-size:12px;font-weight:600;color:${cfg.color};background:${cfg.bg};cursor:pointer;">${cfg.label}</span>`;
		}

		$(document).on("click",".brt-badge-edit",function(e){
			e.preventDefault();
			const span   = $(this);
			const postId = span.closest("td").find(".brt-badge-edit").data("post") || span.data("post");
			const current= span.data("value") || "";
			let html = `<select class="brt-opt-select" data-post="${postId}" style="max-width:100%;font-size:12px;">`;
			for (const key in optMap) {
				const sel = key===current ? "selected" : "";
				html += `<option value="${key}" ${sel}>${optMap[key].label}</option>`;
			}
			html += `</select>`;
			span.replaceWith(html);
			span.closest("td").find("select.brt-opt-select").focus();
		});

		$(document).on("change blur","select.brt-opt-select",function(){
			const sel = $(this);
			const postId = sel.data("post");
			const value  = sel.val();
			sel.prop("disabled", true);
			$.post(ajaxurl, { action:"brt_update_opt_status", post_id:postId, value:value, _ajax_nonce:nonce }, function(resp){
				if(resp && resp.success){
					sel.replaceWith(renderBadge(value)).closest("td").find(".brt-badge-edit").attr("data-post", postId);
				}else{
					alert(resp && resp.data ? resp.data : "Save failed");
					sel.prop("disabled", false).focus();
				}
			});
		});
	})(jQuery);');
	wp_enqueue_script('brt-opt-inline');

	// small CSS width
	wp_add_inline_style('common', '.fixed .column-brt_opt_status{width:170px}');
});

add_action('wp_ajax_brt_update_opt_status', function (){
	check_ajax_referer('brt_opt_status_inline');
	$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
	$val     = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';
	if (!$post_id || !current_user_can('edit_post',$post_id)) wp_send_json_error('No permission');
	$opts = brt_opt_status_options();
	if (!array_key_exists($val,$opts)) $val = '';
	update_post_meta($post_id,'_brt_opt_status',$val);
	wp_send_json_success(true);
});

/* ---------------------------
   Quick Edit + Bulk Edit controls
---------------------------- */
add_action('quick_edit_custom_box', 'brt_opt_status_quick_bulk_box', 10, 2);
add_action('bulk_edit_custom_box',  'brt_opt_status_quick_bulk_box', 10, 2);
function brt_opt_status_quick_bulk_box($column_name, $post_type){
	if ($column_name!=='brt_opt_status' || !in_array($post_type, brt_opt_status_post_types(), true)) return;
	echo '<fieldset class="inline-edit-col-left"><div class="inline-edit-col"><label class="alignleft">';
	echo '<span class="title">Optimisation Status</span>';
	echo '<select name="brt_opt_status_field">';
	foreach (brt_opt_status_options() as $val=>$cfg) {
		printf('<option value="%s">%s</option>', esc_attr($val), esc_html($cfg['label']));
	}
	echo '</select></label></div></fieldset>';
}

// Fill Quick Edit with current value
add_action('admin_footer-edit.php', function (){
	$screen = get_current_screen();
	if (!$screen || !in_array($screen->post_type, brt_opt_status_post_types(), true)) return;
	?>
	<script>
	jQuery(function($){
		var $qe = inlineEditPost.edit;
		inlineEditPost.edit = function( id ){
			$qe.apply(this, arguments);
			var postId = 0;
			if ( typeof(id) === 'object' ) { postId = parseInt(this.getId(id)); }
			if (!postId) return;

			var $row = $('#post-' + postId),
				val = ($row.find('.brt-badge-edit').data('value') || '');
			$('select[name="brt_opt_status_field"]', '.inline-edit-row').val(val);
		});
	});
	</script>
	<?php
});

// Save from Quick Edit / Bulk Edit
add_action('load-edit.php', function (){
	if (!isset($_POST['brt_opt_status_field'])) return;
	if (!current_user_can('edit_posts')) return;
	$val  = sanitize_text_field($_POST['brt_opt_status_field']);
	$opts = brt_opt_status_options();
	if (!array_key_exists($val,$opts)) $val = '';

	$post_ids = [];
	if (!empty($_POST['post'])) { $post_ids = array_map('absint', (array) $_POST['post']); }
	elseif (!empty($_POST['post_ID'])) { $post_ids = [absint($_POST['post_ID'])]; }

	foreach ($post_ids as $pid) {
		if (current_user_can('edit_post',$pid)) update_post_meta($pid,'_brt_opt_status',$val);
	}
});




