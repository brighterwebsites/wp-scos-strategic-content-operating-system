<?php
/**
 * 
 * Brighter Tools: Content Strategy
 * File:  
 * Purpose:  
 *  
 * Version: 4.0.0
 *
 * Responsibilities:
 * - 
 * - 
 * - 
 * - 
 * -  
*
 * Notes:
 * - 
 * - 
 *
 */

if (!defined('ABSPATH')) exit;


// ==========================
// Register Meta
// ==========================
add_action( 'init', function() {
    $args = [
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => false,
    ];
    register_post_meta( '', 'bw_notes', $args );
    register_post_meta( '', 'bw_page_topic', $args );
    register_post_meta( '', 'bw_intent', $args );
    register_post_meta( '', 'bw_purpose', $args );
    register_post_meta( '', 'bw_pillar_page_id', ['type'=>'integer','single'=>true] );
});

// ==========================
// Admin Columns
// ==========================
function bw_add_admin_columns( $cols ) {
    $cols['bw_topic']   = 'Topic';
    $cols['bw_intent']  = 'Intent';
    $cols['bw_purpose'] = 'Purpose';
    $cols['bw_pillar']  = 'Pillar Page';
    $cols['bw_notes']   = 'Notes';
    return $cols;
}
add_filter( 'manage_pages_columns', 'bw_add_admin_columns' );
add_filter( 'manage_posts_columns', 'bw_add_admin_columns' );

function bw_fill_admin_columns( $col, $post_id ) {
    switch ( $col ) {
        case 'bw_topic':
            echo esc_html( get_post_meta( $post_id, 'bw_page_topic', true ) );
            break;
        case 'bw_intent':
            echo esc_html( get_post_meta( $post_id, 'bw_intent', true ) ?: 'NA' );
            break;
        case 'bw_purpose':
            echo esc_html( get_post_meta( $post_id, 'bw_purpose', true ) ?: 'NA' );
            break;
        case 'bw_pillar':
            $id = get_post_meta( $post_id, 'bw_pillar_page_id', true );
            echo $id ? esc_html( get_the_title( $id ) ) : '';
            break;
        case 'bw_notes':
            echo esc_html( get_post_meta( $post_id, 'bw_notes', true ) );
            break;
    }
}
add_action( 'manage_pages_custom_column', 'bw_fill_admin_columns', 10, 2 );
add_action( 'manage_posts_custom_column', 'bw_fill_admin_columns', 10, 2 );

// ==========================
// Sortable Columns (posts + pages + CPTs)
// ==========================
add_filter( 'manage_edit-page_sortable_columns', 'bw_sortable_cols' );
add_filter( 'manage_edit-post_sortable_columns', 'bw_sortable_cols' );
add_filter( 'manage_edit-{your_cpt}_sortable_columns', 'bw_sortable_cols' ); // repeat per CPT

function bw_sortable_cols( $cols ) {
    $cols['bw_topic']   = 'bw_page_topic';
    $cols['bw_intent']  = 'bw_intent';
    $cols['bw_purpose'] = 'bw_purpose';
    $cols['bw_pillar']  = 'bw_pillar_page_id';
    return $cols;
}

add_action( 'pre_get_posts', function( $q ) {
    if ( ! is_admin() || ! $q->is_main_query() ) return;
    $orderby = $q->get( 'orderby' );
    if ( in_array( $orderby, ['bw_page_topic','bw_intent','bw_purpose','bw_pillar_page_id'], true ) ) {
        $q->set( 'meta_key', $orderby );
        $q->set( 'orderby', 'meta_value' );
    }
});

// ==========================
// Quick + Bulk Edit: Topic, Notes, Intent, Purpose, Pillar Page
// ==========================
add_action( 'quick_edit_custom_box', function( $col, $post_type ) {
    if ( ! in_array( $col, ['bw_topic','bw_notes','bw_intent','bw_purpose','bw_pillar'] ) ) return;

    // Grab pillar pages for dropdown
    $pillar_pages = get_posts([
        'post_type'      => 'page',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_key'       => 'bw_purpose',
        'meta_value'     => 'pillar',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);
    ?>
    <fieldset class="inline-edit-col-left">
        <div class="inline-edit-col">
            <?php if ( $col === 'bw_topic' ) : ?>
                <label><span class="title">Topic</span>
                    <input type="text" name="bw_page_topic" value="">
                </label>
            <?php elseif ( $col === 'bw_notes' ) : ?>
                <label><span class="title">Notes</span>
                    <textarea name="bw_notes" rows="2"></textarea>
                </label>
            <?php elseif ( $col === 'bw_intent' ) : ?>
                <label><span class="title">Intent</span>
                    <select name="bw_intent">
                        <option value="">NA</option>
                        <option value="informational">Informational</option>
                        <option value="transactional">Transactional</option>
                        <option value="trust">Trust</option>
                    </select>
                </label>
            <?php elseif ( $col === 'bw_purpose' ) : ?>
                <label><span class="title">Purpose</span>
                    <select name="bw_purpose">
                        <option value="">NA</option>
                        <option value="pillar">Pillar</option>
                        <option value="supporting">Supporting</option>
                        <option value="case-study">Case Study</option>
                        <option value="conversion-hub">Conversion Hub</option>
                    </select>
                </label>
            <?php elseif ( $col === 'bw_pillar' ) : ?>
                <label><span class="title">Pillar Page</span>
                    <select name="bw_pillar_page_id">
                        <option value="">— Select Pillar Page —</option>
                        <?php foreach ( $pillar_pages as $p ) : ?>
                            <option value="<?php echo esc_attr( $p->ID ); ?>">
                                <?php echo esc_html( get_the_title( $p ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>
    </fieldset>
    <?php
}, 10, 2 );

add_action( 'bulk_edit_custom_box', function( $col, $post_type ) {
    if ( ! in_array( $col, ['bw_intent','bw_purpose','bw_pillar'] ) ) return;

    $pillar_pages = get_posts([
        'post_type'      => 'page',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_key'       => 'bw_purpose',
        'meta_value'     => 'pillar',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);
    ?>
    <fieldset class="inline-edit-col-left">
        <div class="inline-edit-col">
            <?php if ( $col === 'bw_intent' ) : ?>
                <label><span class="title">Intent</span>
                    <select name="bw_intent">
                        <option value="">— No Change —</option>
                        <option value="informational">Informational</option>
                        <option value="transactional">Transactional</option>
                        <option value="trust">Trust</option>
                    </select>
                </label>
            <?php elseif ( $col === 'bw_purpose' ) : ?>
                <label><span class="title">Purpose</span>
                    <select name="bw_purpose">
                        <option value="">— No Change —</option>
                        <option value="pillar">Pillar</option>
                        <option value="supporting">Supporting</option>
                        <option value="case-study">Case Study</option>
                        <option value="conversion-hub">Conversion Hub</option>
                    </select>
                </label>
            <?php elseif ( $col === 'bw_pillar' ) : ?>
                <label><span class="title">Pillar Page</span>
                    <select name="bw_pillar_page_id">
                        <option value="">— No Change —</option>
                        <?php foreach ( $pillar_pages as $p ) : ?>
                            <option value="<?php echo esc_attr( $p->ID ); ?>">
                                <?php echo esc_html( get_the_title( $p ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>
    </fieldset>
    <?php
}, 10, 2 );

// Preload for Quick Edit
add_action( 'admin_footer-edit.php', function() {
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->post_type, ['post','page'], true ) ) return; ?>
    <script>
    jQuery(function($){
        var $edit = inlineEditPost.edit;
        inlineEditPost.edit = function( id ){
            $edit.apply(this, arguments);
            var postId = (typeof id === 'object') ? this.getId(id) : id;
            var $row = $('#post-' + postId);

            $('input[name="bw_page_topic"]', '.inline-edit-row').val( $row.find('td.column-bw_topic').text().trim() );
            $('textarea[name="bw_notes"]', '.inline-edit-row').val( $row.find('td.column-bw_notes').text().trim() );
            $('select[name="bw_intent"]', '.inline-edit-row').val( $row.find('td.column-bw_intent').text().trim() );
            $('select[name="bw_purpose"]', '.inline-edit-row').val( $row.find('td.column-bw_purpose').text().trim() );
            $('input[name="bw_pillar_page_id"]', '.inline-edit-row').val( $row.find('td.column-bw_pillar').data('value') );
        };
    });
    </script>
<?php });

// Save Quick + Bulk
add_action( 'save_post', function( $post_id ) {
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $fields = [
        'bw_notes'          => FILTER_SANITIZE_STRING,
        'bw_page_topic'     => FILTER_SANITIZE_STRING,
        'bw_intent'         => FILTER_SANITIZE_STRING,
        'bw_purpose'        => FILTER_SANITIZE_STRING,
        'bw_pillar_page_id' => FILTER_SANITIZE_NUMBER_INT,
    ];
    foreach ( $fields as $key => $filter ) {
        if ( isset($_POST[$key]) ) {
            update_post_meta( $post_id, $key, filter_var($_POST[$key], $filter) );
        }
    }
});
