<?php
/**
 * Post Framing — Social Framing CPT (scos_social_framing)
 *
 * Registers the Post Framing CPT and bw_content_type taxonomy when the Social
 * Amplification module is active. One-time DB migration renames bw_talking_point
 * posts to scos_social_framing. Legacy brighter-core BW_Talking_Points skips
 * registration when SCOS_SA_ACTIVE is defined.
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

	/** CPT slug (B1) — replaces legacy bw_talking_point. */
	public const POST_TYPE = 'scos_social_framing';

	/** Option set after one-time post_type migration. */
	private const MIGRATE_OPTION = 'scos_pf_post_type_migrated_v1';

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
		if ( ! defined( 'SCOS_SA_ACTIVE' ) ) {
			return;
		}

		add_action( 'init', [ static::class, 'maybe_migrate_post_type' ], 9 );
		add_action( 'init', [ static::class, 'register_cpt_and_taxonomy' ], 11 );
		add_action( 'init', [ static::class, 'register_post_meta' ], 12 );

		add_action( 'add_meta_boxes', [ static::class, 'replace_meta_box' ], 20 );
		add_action( 'save_post_' . self::POST_TYPE, [ static::class, 'save_meta' ], 20, 2 );

		add_action( 'admin_menu', [ static::class, 'remove_old_menus' ], 99 );
	}

	/**
	 * One-time migration: bw_talking_point → scos_social_framing in wp_posts.
	 */
	public static function maybe_migrate_post_type(): void {
		if ( get_option( self::MIGRATE_OPTION ) ) {
			return;
		}

		global $wpdb;

		$updated = 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1) FROM {$wpdb->posts} WHERE post_type = %s",
				'bw_talking_point'
			)
		);

		if ( $count > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
					self::POST_TYPE,
					'bw_talking_point'
				)
			);
		}

		update_option( self::MIGRATE_OPTION, time(), false );

		if ( $updated > 0 ) {
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Register CPT + taxonomy (bw_content_type kept for Make.com / REST compat).
	 */
	public static function register_cpt_and_taxonomy(): void {
		$labels = [
			'name'               => __( 'Post Framing', 'site-essentials' ),
			'singular_name'      => __( 'Post Frame', 'site-essentials' ),
			'menu_name'          => __( 'Post Framing', 'site-essentials' ),
			'add_new'            => __( 'Add New', 'site-essentials' ),
			'add_new_item'       => __( 'Add New Post Frame', 'site-essentials' ),
			'edit_item'          => __( 'Edit Post Frame', 'site-essentials' ),
			'new_item'           => __( 'New Post Frame', 'site-essentials' ),
			'view_item'          => __( 'View Post Frame', 'site-essentials' ),
			'search_items'       => __( 'Search Post Framing', 'site-essentials' ),
			'not_found'          => __( 'No post frames found', 'site-essentials' ),
			'not_found_in_trash' => __( 'No post frames found in trash', 'site-essentials' ),
			'all_items'          => __( 'All Post Frames', 'site-essentials' ),
		];

		register_post_type(
			self::POST_TYPE,
			[
				'labels'              => $labels,
				'description'         => __( 'AI framing templates for social post creation.', 'site-essentials' ),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => true,
				'capability_type'     => 'post',
				'hierarchical'        => false,
				'supports'            => [ 'title', 'editor' ],
				'menu_icon'           => 'dashicons-megaphone',
				'menu_position'       => 26,
				'has_archive'         => false,
				'rewrite'             => false,
			]
		);

		$tax_labels = [
			'name'          => __( 'Content Types', 'site-essentials' ),
			'singular_name' => __( 'Content Type', 'site-essentials' ),
			'search_items'  => __( 'Search Content Types', 'site-essentials' ),
			'all_items'     => __( 'All Content Types', 'site-essentials' ),
			'edit_item'     => __( 'Edit Content Type', 'site-essentials' ),
			'update_item'   => __( 'Update Content Type', 'site-essentials' ),
			'add_new_item'  => __( 'Add New Content Type', 'site-essentials' ),
			'new_item_name' => __( 'New Content Type Name', 'site-essentials' ),
			'menu_name'     => __( 'Content Types', 'site-essentials' ),
		];

		register_taxonomy(
			'bw_content_type',
			[ self::POST_TYPE ],
			[
				'labels'            => $tax_labels,
				'hierarchical'      => false,
				'public'            => false,
				'show_ui'           => false,
				'show_in_menu'      => false,
				'show_admin_column' => false,
				'show_in_quick_edit'=> false,
				'show_in_nav_menus' => false,
				'show_in_rest'      => true,
				'query_var'         => true,
				'rewrite'           => false,
				'meta_box_cb'       => false,
			]
		);
	}

	public static function register_post_meta(): void {
		$string_args = [
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => false,
			'auth_callback' => static function () { return current_user_can( 'edit_posts' ); },
		];
		$int_args = array_merge( $string_args, [ 'type' => 'integer', 'sanitize_callback' => 'absint' ] );

		register_post_meta( self::POST_TYPE, 'scos_sma_pf_content_type', array_merge( $string_args, [ 'sanitize_callback' => 'sanitize_key' ] ) );
		register_post_meta( self::POST_TYPE, 'scos_sma_pf_context', array_merge( $string_args, [ 'sanitize_callback' => 'sanitize_textarea_field' ] ) );
		register_post_meta( self::POST_TYPE, 'scos_sma_pf_example', array_merge( $string_args, [ 'sanitize_callback' => 'sanitize_textarea_field' ] ) );
		register_post_meta( self::POST_TYPE, 'scos_sma_pf_cta', array_merge( $string_args, [ 'sanitize_callback' => 'sanitize_textarea_field' ] ) );
		register_post_meta( self::POST_TYPE, 'scos_sma_pf_words_min', $int_args );
		register_post_meta( self::POST_TYPE, 'scos_sma_pf_words_max', $int_args );
	}

	public static function replace_meta_box(): void {
		remove_meta_box( 'tagsdiv-bw_content_type', self::POST_TYPE, 'side' );

		add_meta_box(
			'scos_pf_details',
			__( 'Post Frame Details', 'site-essentials' ),
			[ static::class, 'render_meta_box' ],
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'scos_pf_meta', 'scos_pf_nonce' );

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
			<p class="description"><?php esc_html_e( 'Structured type — matches what Make.com receives in the webhook payload.', 'site-essentials' ); ?></p>
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

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['scos_pf_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scos_pf_nonce'] ) ), 'scos_pf_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['scos_sma_pf_content_type'] ) ) {
			$ct = sanitize_key( wp_unslash( $_POST['scos_sma_pf_content_type'] ) );
			update_post_meta( $post_id, 'scos_sma_pf_content_type', $ct );
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
			'scos_sma_pf_context' => 'sanitize_textarea_field',
			'scos_sma_pf_example' => 'sanitize_textarea_field',
			'scos_sma_pf_cta'     => 'sanitize_textarea_field',
		];
		foreach ( $textarea_fields as $key => $cb ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, $cb( wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		if ( isset( $_POST['scos_sma_pf_words_min'] ) ) {
			update_post_meta( $post_id, 'scos_sma_pf_words_min', absint( $_POST['scos_sma_pf_words_min'] ) );
		}
		if ( isset( $_POST['scos_sma_pf_words_max'] ) ) {
			update_post_meta( $post_id, 'scos_sma_pf_words_max', absint( $_POST['scos_sma_pf_words_max'] ) );
		}
	}

	/**
	 * Remove legacy Support submenu URLs (old CPT slug + taxonomy screen).
	 */
	public static function remove_old_menus(): void {
		remove_submenu_page( 'brighter_support', 'edit.php?post_type=bw_talking_point' );
		remove_submenu_page( 'brighter_support', 'edit-tags.php?taxonomy=bw_content_type&post_type=bw_talking_point' );
		remove_submenu_page( 'brighter_support', 'edit-tags.php?taxonomy=bw_content_type&post_type=' . self::POST_TYPE );
	}
}
