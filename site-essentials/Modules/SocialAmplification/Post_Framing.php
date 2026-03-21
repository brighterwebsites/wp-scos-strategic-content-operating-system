<?php
/**
 * Post Framing — overrides the legacy bw_talking_point CPT
 *
 * Renames "Talking Points" → "Post Framing" in every admin label, hides the
 * bw_content_type editable taxonomy (content type is now a structured dropdown),
 * removes old brighter_support menu items, and replaces the legacy meta box with
 * a new one that saves to scos_sma_pf_* keys (dual-reads legacy _bw_tp_* as fallback).
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\SocialAmplification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Post_Framing {

	// ─────────────────────────────────────────────────────────────────────────
	// Content-type options (matches BW_Content_Type_Helper::get_content_type() output)
	// ─────────────────────────────────────────────────────────────────────────

	public static function content_type_options(): array {
		return [
			''           => __( '— Select type —', 'site-essentials' ),
			'article'    => __( 'Article / Blog Post', 'site-essentials' ),
			'service'    => __( 'Service Page', 'site-essentials' ),
			'product'    => __( 'Product', 'site-essentials' ),
			'case-study' => __( 'Case Study / Project', 'site-essentials' ),
			'guide'      => __( 'Guide / Knowledge Base', 'site-essentials' ),
			'news'       => __( 'News', 'site-essentials' ),
			'brand'      => __( 'Brand & Trust', 'site-essentials' ),
			'archive'    => __( 'Content Collection / Archive', 'site-essentials' ),
			'conversion' => __( 'Conversion / Landing Page', 'site-essentials' ),
			'policy'     => __( 'Policy / Legal', 'site-essentials' ),
			'functional' => __( 'Functional Page', 'site-essentials' ),
			'page'       => __( 'General Page', 'site-essentials' ),
			'home'       => __( 'Homepage', 'site-essentials' ),
		];
	}

	// ─────────────────────────────────────────────────────────────────────────

	public static function init(): void {
		// Override labels and taxonomy AFTER BW_Talking_Points registers (priority 20)
		add_action( 'init', [ static::class, 'override_cpt_labels' ], 20 );
		add_action( 'init', [ static::class, 'override_taxonomy_visibility' ], 20 );

		// Register scos_sma_pf_* post meta
		add_action( 'init', [ static::class, 'register_post_meta' ], 20 );

		// Replace the old meta box with the new one
		add_action( 'add_meta_boxes', [ static::class, 'replace_meta_box' ], 20 );

		// Save new meta fields
		add_action( 'save_post_bw_talking_point', [ static::class, 'save_meta' ], 20, 2 );

		// Remove old brighter_support menu items (fires after BW_Talking_Points at priority 20)
		add_action( 'admin_menu', [ static::class, 'remove_old_menus' ], 99 );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// CPT Labels
	// ─────────────────────────────────────────────────────────────────────────

	public static function override_cpt_labels(): void {
		global $wp_post_types;

		if ( ! isset( $wp_post_types['bw_talking_point'] ) ) {
			return;
		}

		$l = $wp_post_types['bw_talking_point']->labels;

		$l->name                  = __( 'Post Framing', 'site-essentials' );
		$l->singular_name         = __( 'Post Frame', 'site-essentials' );
		$l->menu_name             = __( 'Post Framing', 'site-essentials' );
		$l->add_new_item          = __( 'Add New Post Frame', 'site-essentials' );
		$l->edit_item             = __( 'Edit Post Frame', 'site-essentials' );
		$l->new_item              = __( 'New Post Frame', 'site-essentials' );
		$l->view_item             = __( 'View Post Frame', 'site-essentials' );
		$l->search_items          = __( 'Search Post Framing', 'site-essentials' );
		$l->not_found             = __( 'No post frames found', 'site-essentials' );
		$l->not_found_in_trash    = __( 'No post frames found in trash', 'site-essentials' );
		$l->all_items             = __( 'All Post Frames', 'site-essentials' );
		$wp_post_types['bw_talking_point']->description = __( 'AI framing templates for social post creation.', 'site-essentials' );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Taxonomy visibility — keep registered for REST/query compat, hide admin UI
	// ─────────────────────────────────────────────────────────────────────────

	public static function override_taxonomy_visibility(): void {
		global $wp_taxonomies;

		if ( ! isset( $wp_taxonomies['bw_content_type'] ) ) {
			return;
		}

		$t                       = $wp_taxonomies['bw_content_type'];
		$t->show_ui              = false;
		$t->show_in_menu         = false;
		$t->show_admin_column    = false;
		$t->show_in_quick_edit   = false;
		$t->show_in_nav_menus    = false;
		$t->meta_box_cb          = false; // remove meta box from edit screen
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Post Meta Registration
	// ─────────────────────────────────────────────────────────────────────────

	public static function register_post_meta(): void {
		$string_args = [
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => false,
			'auth_callback' => static function () { return current_user_can( 'edit_posts' ); },
		];
		$int_args = array_merge( $string_args, [ 'type' => 'integer', 'sanitize_callback' => 'absint' ] );

		register_post_meta( 'bw_talking_point', 'scos_sma_pf_content_type', array_merge( $string_args, [ 'sanitize_callback' => 'sanitize_key' ] ) );
		register_post_meta( 'bw_talking_point', 'scos_sma_pf_context',      array_merge( $string_args, [ 'sanitize_callback' => 'sanitize_textarea_field' ] ) );
		register_post_meta( 'bw_talking_point', 'scos_sma_pf_example',      array_merge( $string_args, [ 'sanitize_callback' => 'sanitize_textarea_field' ] ) );
		register_post_meta( 'bw_talking_point', 'scos_sma_pf_cta',          array_merge( $string_args, [ 'sanitize_callback' => 'sanitize_textarea_field' ] ) );
		register_post_meta( 'bw_talking_point', 'scos_sma_pf_words_min',    $int_args );
		register_post_meta( 'bw_talking_point', 'scos_sma_pf_words_max',    $int_args );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Meta Box
	// ─────────────────────────────────────────────────────────────────────────

	public static function replace_meta_box(): void {
		remove_meta_box( 'bw_talking_point_details', 'bw_talking_point', 'normal' );
		// Also remove the taxonomy meta box that WordPress auto-adds
		remove_meta_box( 'tagsdiv-bw_content_type', 'bw_talking_point', 'side' );

		add_meta_box(
			'scos_pf_details',
			__( 'Post Frame Details', 'site-essentials' ),
			[ static::class, 'render_meta_box' ],
			'bw_talking_point',
			'normal',
			'high'
		);
	}

	public static function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'scos_pf_meta', 'scos_pf_nonce' );

		// Read from new keys; fall back to legacy _bw_tp_* for existing data
		$content_type = get_post_meta( $post->ID, 'scos_sma_pf_content_type', true );
		$context      = get_post_meta( $post->ID, 'scos_sma_pf_context', true )
			?: get_post_meta( $post->ID, '_bw_tp_context', true );
		$example      = get_post_meta( $post->ID, 'scos_sma_pf_example', true )
			?: get_post_meta( $post->ID, '_bw_tp_example', true );
		$cta          = get_post_meta( $post->ID, 'scos_sma_pf_cta', true )
			?: get_post_meta( $post->ID, '_bw_tp_cta_example', true );
		$words_min    = get_post_meta( $post->ID, 'scos_sma_pf_words_min', true )
			?: get_post_meta( $post->ID, '_bw_tp_word_count_min', true )
			?: 50;
		$words_max    = get_post_meta( $post->ID, 'scos_sma_pf_words_max', true )
			?: get_post_meta( $post->ID, '_bw_tp_word_count_max', true )
			?: 130;

		// Also check bw_content_type taxonomy for existing content type
		if ( empty( $content_type ) ) {
			$terms = wp_get_post_terms( $post->ID, 'bw_content_type', [ 'fields' => 'slugs' ] );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$content_type = $terms[0];
			}
		}
		?>
		<style>
			.scos-pf-field { margin-bottom: 18px; }
			.scos-pf-field label { display: block; font-weight: 600; margin-bottom: 5px; }
			.scos-pf-field textarea, .scos-pf-field select { width: 100%; }
			.scos-pf-field input[type="number"] { width: 90px; }
			.scos-pf-field p.description { margin: 4px 0 0; color: #757575; font-style: italic; }
			.scos-pf-word-count { display: flex; align-items: center; gap: 8px; }
		</style>

		<div class="scos-pf-field">
			<label for="scos_sma_pf_content_type"><?php esc_html_e( 'Content Type', 'site-essentials' ); ?></label>
			<select id="scos_sma_pf_content_type" name="scos_sma_pf_content_type">
				<?php foreach ( static::content_type_options() as $val => $label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $content_type, $val ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Structured type — matches what Make.com receives in the webhook payload. Not directly editable by users.', 'site-essentials' ); ?></p>
		</div>

		<div class="scos-pf-field">
			<label for="scos_sma_pf_context"><?php esc_html_e( 'Context / Guidance', 'site-essentials' ); ?></label>
			<textarea id="scos_sma_pf_context" name="scos_sma_pf_context" rows="4"><?php echo esc_textarea( $context ); ?></textarea>
			<p class="description"><?php esc_html_e( 'What this post frame should cover — passed to AI as framing context.', 'site-essentials' ); ?></p>
		</div>

		<div class="scos-pf-field">
			<label for="scos_sma_pf_example"><?php esc_html_e( 'Example Hooks / Opening Lines', 'site-essentials' ); ?></label>
			<textarea id="scos_sma_pf_example" name="scos_sma_pf_example" rows="4"><?php echo esc_textarea( $example ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Example angles or opening sentences to guide the AI.', 'site-essentials' ); ?></p>
		</div>

		<div class="scos-pf-field">
			<label for="scos_sma_pf_cta"><?php esc_html_e( 'CTA Examples', 'site-essentials' ); ?></label>
			<textarea id="scos_sma_pf_cta" name="scos_sma_pf_cta" rows="3"><?php echo esc_textarea( $cta ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Example call-to-action phrases for this content type.', 'site-essentials' ); ?></p>
		</div>

		<div class="scos-pf-field">
			<label><?php esc_html_e( 'Target Word Count Range', 'site-essentials' ); ?></label>
			<div class="scos-pf-word-count">
				<input type="number" name="scos_sma_pf_words_min" id="scos_sma_pf_words_min"
					value="<?php echo esc_attr( $words_min ); ?>" min="20" max="500" />
				<span><?php esc_html_e( 'to', 'site-essentials' ); ?></span>
				<input type="number" name="scos_sma_pf_words_max" id="scos_sma_pf_words_max"
					value="<?php echo esc_attr( $words_max ); ?>" min="20" max="500" />
				<span><?php esc_html_e( 'words', 'site-essentials' ); ?></span>
			</div>
			<p class="description"><?php esc_html_e( 'Suggested word count range for social posts using this frame.', 'site-essentials' ); ?></p>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Save
	// ─────────────────────────────────────────────────────────────────────────

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['scos_pf_nonce'] )
			|| ! wp_verify_nonce( $_POST['scos_pf_nonce'], 'scos_pf_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		// Content type — save new key + sync to legacy taxonomy for REST compat
		if ( isset( $_POST['scos_sma_pf_content_type'] ) ) {
			$ct = sanitize_key( $_POST['scos_sma_pf_content_type'] );
			update_post_meta( $post_id, 'scos_sma_pf_content_type', $ct );
			// Keep taxonomy in sync so existing REST queries still work
			if ( $ct ) {
				$term = get_term_by( 'slug', $ct, 'bw_content_type' );
				if ( ! $term ) {
					$labels = static::content_type_options();
					$name   = $labels[ $ct ] ?? $ct;
					wp_insert_term( $name, 'bw_content_type', [ 'slug' => $ct ] );
				}
				wp_set_post_terms( $post_id, [ $ct ], 'bw_content_type' );
			} else {
				wp_set_post_terms( $post_id, [], 'bw_content_type' );
			}
		}

		$textarea_fields = [
			'scos_sma_pf_context'  => 'sanitize_textarea_field',
			'scos_sma_pf_example'  => 'sanitize_textarea_field',
			'scos_sma_pf_cta'      => 'sanitize_textarea_field',
		];
		foreach ( $textarea_fields as $key => $cb ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, $cb( $_POST[ $key ] ) );
			}
		}

		if ( isset( $_POST['scos_sma_pf_words_min'] ) ) {
			update_post_meta( $post_id, 'scos_sma_pf_words_min', absint( $_POST['scos_sma_pf_words_min'] ) );
		}
		if ( isset( $_POST['scos_sma_pf_words_max'] ) ) {
			update_post_meta( $post_id, 'scos_sma_pf_words_max', absint( $_POST['scos_sma_pf_words_max'] ) );
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Old Menus
	// ─────────────────────────────────────────────────────────────────────────

	public static function remove_old_menus(): void {
		remove_submenu_page( 'brighter_support', 'edit.php?post_type=bw_talking_point' );
		remove_submenu_page( 'brighter_support', 'edit-tags.php?taxonomy=bw_content_type&post_type=bw_talking_point' );
	}
}
