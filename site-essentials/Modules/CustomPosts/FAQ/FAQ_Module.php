<?php
/**
 * FAQ Module — Site Essentials
 *
 * Enhances the legacy brighter-core FAQ CPT with:
 *   - Hierarchical parent/child support
 *   - scos_topic taxonomy integration (Primary Topic)
 *   - scos_faq_* meta key prefix (dual-writes to _faq_* for block/REST compat)
 *   - Schema answer pre-fill from TLDR field
 *   - Moved under Site Essentials menu (hidden from WP sidebar top-level)
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts\FAQ
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\CustomPosts\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FAQ_Module {

	// =========================================================================
	// Bootstrap
	// =========================================================================

	public static function init(): void {
		if ( ! defined( 'SCOS_FAQ_ACTIVE' ) ) {
			define( 'SCOS_FAQ_ACTIVE', true );
		}

		// Remove brighter-core's plain CPT registration (priority 20).
		// This is safe because Module_Loader runs at init priority 10,
		// so we remove the action before priority 20 fires.
		remove_action( 'init', 'register_faq_cpt', 20 );

		// Register our enhanced CPT
		add_action( 'init', [ self::class, 'register_cpt' ], 22 );

		// Attach scos_topic taxonomy to the faq CPT once both are registered
		add_action( 'init', [ self::class, 'add_topic_support' ], 30 );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes',                   [ self::class, 'replace_meta_boxes' ], 20 );
			add_action( 'save_post_faq',                    [ self::class, 'save_meta' ],          20 );
			add_filter( 'manage_faq_posts_columns',         [ self::class, 'admin_columns' ] );
			add_action( 'manage_faq_posts_custom_column',   [ self::class, 'admin_column_content' ], 10, 2 );
			add_action( 'wp_dashboard_setup',               [ self::class, 'remove_dashboard_widget' ], 20 );
		}
	}

	// =========================================================================
	// CPT Registration
	// =========================================================================

	public static function register_cpt(): void {
		$labels = [
			'name'               => __( 'FAQs',                   'site-essentials' ),
			'singular_name'      => __( 'FAQ',                    'site-essentials' ),
			'menu_name'          => __( 'FAQs',                   'site-essentials' ),
			'add_new'            => __( 'Add New FAQ',             'site-essentials' ),
			'add_new_item'       => __( 'Add New FAQ',             'site-essentials' ),
			'edit_item'          => __( 'Edit FAQ',                'site-essentials' ),
			'new_item'           => __( 'New FAQ',                 'site-essentials' ),
			'view_item'          => __( 'View FAQ',                'site-essentials' ),
			'search_items'       => __( 'Search FAQs',             'site-essentials' ),
			'not_found'          => __( 'No FAQs found',           'site-essentials' ),
			'not_found_in_trash' => __( 'No FAQs found in trash',  'site-essentials' ),
			'parent_item_colon'  => __( 'Parent FAQ:',             'site-essentials' ),
			'all_items'          => __( 'All FAQs',                'site-essentials' ),
		];

		register_post_type( 'faq', [
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // Suppressed — added via Admin_UI under Site Essentials
			'query_var'           => true,
			'rewrite'             => [ 'slug' => 'faq', 'with_front' => false ],
			'exclude_from_search' => true,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => true,  // Enables parent/child FAQ grouping
			'menu_position'       => 25,
			'menu_icon'           => 'dashicons-editor-help',
			'show_in_rest'        => true,
			'supports'            => [ 'title', 'editor', 'revisions', 'page-attributes' ],
		] );
	}

	/**
	 * Register scos_topic taxonomy on the faq CPT so FAQs can be
	 * tagged with the same topic vocabulary used site-wide.
	 */
	public static function add_topic_support(): void {
		if ( taxonomy_exists( 'scos_topic' ) ) {
			register_taxonomy_for_object_type( 'scos_topic', 'faq' );
		}
	}

	// =========================================================================
	// Meta Boxes
	// =========================================================================

	public static function replace_meta_boxes(): void {
		// Remove legacy brighter-core meta boxes
		remove_meta_box( 'faq_schema_answer', 'faq', 'normal' );
		remove_meta_box( 'faq_schema_toggle', 'faq', 'side' );

		add_meta_box(
			'scos_faq_settings',
			__( 'FAQ Settings', 'site-essentials' ),
			[ self::class, 'render_meta_box' ],
			'faq',
			'side',
			'high'
		);
	}

	public static function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'scos_faq_meta_save', 'scos_faq_nonce' );

		// Schema enabled — scos_faq_* primary, _faq_* legacy fallback
		$enable_schema = get_post_meta( $post->ID, 'scos_faq_enable_schema', true );
		if ( $enable_schema === '' ) {
			$legacy_val    = get_post_meta( $post->ID, '_faq_enable_schema', true );
			$enable_schema = ( $legacy_val !== '0' ) ? '1' : '0';
		}

		// Schema answer — scos_faq_* primary, _faq_* legacy fallback
		$schema_answer = get_post_meta( $post->ID, 'scos_faq_schema_answer', true );
		if ( $schema_answer === '' ) {
			$schema_answer = (string) get_post_meta( $post->ID, '_faq_schema_answer', true );
		}

		// TLDR for pre-fill suggestion
		$tldr = get_post_meta( $post->ID, 'scos_seo_tldr', true )
			?: get_post_meta( $post->ID, 'bw_tldr', true );

		// Primary topic (taxonomy)
		$topic_terms      = get_the_terms( $post->ID, 'scos_topic' );
		$current_topic_id = ( $topic_terms && ! is_wp_error( $topic_terms ) ) ? $topic_terms[0]->term_id : 0;
		$all_topics       = get_terms( [ 'taxonomy' => 'scos_topic', 'hide_empty' => false ] );
		?>

		<p style="margin:8px 0 4px;"><strong><?php esc_html_e( 'Primary Topic', 'site-essentials' ); ?></strong></p>
		<select name="scos_faq_topic" id="scos_faq_topic" style="width:100%;">
			<option value="0"><?php esc_html_e( '— None —', 'site-essentials' ); ?></option>
			<?php if ( ! is_wp_error( $all_topics ) && ! empty( $all_topics ) ) : ?>
				<?php foreach ( $all_topics as $topic ) : ?>
					<option value="<?php echo esc_attr( $topic->term_id ); ?>"
						<?php selected( $current_topic_id, $topic->term_id ); ?>>
						<?php echo esc_html( $topic->name ); ?>
					</option>
				<?php endforeach; ?>
			<?php endif; ?>
		</select>
		<p class="description" style="margin-bottom:10px;">
			<?php esc_html_e( 'Used for topical coverage and link suggestions.', 'site-essentials' ); ?>
		</p>

		<hr style="margin:10px -12px;">

		<p style="margin-top:10px;"><label>
			<input type="checkbox" name="scos_faq_enable_schema" value="1"
				<?php checked( $enable_schema, '1' ); ?> />
			<strong><?php esc_html_e( 'Include in FAQ Schema', 'site-essentials' ); ?></strong>
		</label></p>
		<p class="description">
			<?php esc_html_e( 'Uncheck to exclude this FAQ from schema markup. Useful when avoiding schema dilution.', 'site-essentials' ); ?>
		</p>

		<hr style="margin:10px -12px;">

		<p style="margin-top:10px;"><strong><?php esc_html_e( 'Schema Answer', 'site-essentials' ); ?></strong></p>
		<?php if ( ! empty( $tldr ) ) : ?>
			<p class="description" style="margin-bottom:6px;">
				<?php esc_html_e( 'TLDR:', 'site-essentials' ); ?>
				<em><?php echo esc_html( wp_trim_words( $tldr, 20 ) ); ?></em><br>
				<a href="#" id="scos-faq-use-tldr" style="font-size:11px;">
					<?php esc_html_e( '↑ Use TLDR as schema answer', 'site-essentials' ); ?>
				</a>
			</p>
			<script>
			( function() {
				var btn = document.getElementById( 'scos-faq-use-tldr' );
				if ( btn ) {
					btn.addEventListener( 'click', function( e ) {
						e.preventDefault();
						document.getElementById( 'scos_faq_schema_answer' ).value = <?php echo wp_json_encode( $tldr ); ?>;
						document.getElementById( 'scos-faq-cc' ).textContent = <?php echo (int) strlen( $tldr ); ?>;
					} );
				}
			} )();
			</script>
		<?php endif; ?>

		<textarea
			name="scos_faq_schema_answer"
			id="scos_faq_schema_answer"
			rows="4"
			style="width:100%;"
			placeholder="<?php esc_attr_e( 'Concise answer for schema (100–300 chars, plain text)', 'site-essentials' ); ?>"
		><?php echo esc_textarea( $schema_answer ); ?></textarea>
		<p class="description">
			<span id="scos-faq-cc"><?php echo esc_html( (string) strlen( $schema_answer ) ); ?></span>
			<?php esc_html_e( 'chars — recommended 100–300', 'site-essentials' ); ?>
		</p>
		<script>
		( function() {
			var ta = document.getElementById( 'scos_faq_schema_answer' );
			var cc = document.getElementById( 'scos-faq-cc' );
			if ( ta && cc ) {
				ta.addEventListener( 'input', function() { cc.textContent = ta.value.length; } );
			}
		} )();
		</script>
		<?php
	}

	// =========================================================================
	// Save Meta
	// =========================================================================

	public static function save_meta( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['scos_faq_nonce'] ) || ! wp_verify_nonce( $_POST['scos_faq_nonce'], 'scos_faq_meta_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Schema enable toggle — dual-write for block/REST backward compat
		$enable_schema = isset( $_POST['scos_faq_enable_schema'] ) ? '1' : '0';
		update_post_meta( $post_id, 'scos_faq_enable_schema', $enable_schema );
		update_post_meta( $post_id, '_faq_enable_schema',     $enable_schema );

		// Schema answer — dual-write
		$schema_answer = isset( $_POST['scos_faq_schema_answer'] )
			? sanitize_textarea_field( wp_unslash( $_POST['scos_faq_schema_answer'] ) )
			: '';
		update_post_meta( $post_id, 'scos_faq_schema_answer', $schema_answer );
		update_post_meta( $post_id, '_faq_schema_answer',     $schema_answer );

		// Primary topic taxonomy
		$topic_id = isset( $_POST['scos_faq_topic'] ) ? (int) $_POST['scos_faq_topic'] : 0;
		if ( $topic_id > 0 ) {
			wp_set_object_terms( $post_id, [ $topic_id ], 'scos_topic' );
		} else {
			wp_set_object_terms( $post_id, [], 'scos_topic' );
		}
	}

	// =========================================================================
	// Admin Columns
	// =========================================================================

	public static function admin_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $value ) {
			// Skip legacy columns added by bw-faq.php
			if ( in_array( $key, [ 'schema_enabled', 'char_count' ], true ) ) {
				continue;
			}
			$new[ $key ] = $value;
			if ( $key === 'title' ) {
				$new['scos_faq_topic']  = __( 'Primary Topic', 'site-essentials' );
				$new['scos_faq_schema'] = __( 'Schema', 'site-essentials' );
			}
		}
		return $new;
	}

	public static function admin_column_content( string $column, int $post_id ): void {
		if ( $column === 'scos_faq_topic' ) {
			$terms = get_the_terms( $post_id, 'scos_topic' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				echo esc_html( $terms[0]->name );
			} else {
				echo '<span style="color:#999">—</span>';
			}
		}

		if ( $column === 'scos_faq_schema' ) {
			$enabled = get_post_meta( $post_id, 'scos_faq_enable_schema', true );
			if ( $enabled === '' ) {
				$legacy  = get_post_meta( $post_id, '_faq_enable_schema', true );
				$enabled = ( $legacy !== '0' ) ? '1' : '0';
			}
			echo $enabled === '1'
				? '<span style="color:#46b450;" title="Enabled">✓</span>'
				: '<span style="color:#dc3232;" title="Disabled">✗</span>';
		}
	}

	// =========================================================================
	// Misc
	// =========================================================================

	/**
	 * Remove the legacy brighter-core dashboard widget; FAQ stats are now
	 * part of the Site Essentials overview.
	 */
	public static function remove_dashboard_widget(): void {
		remove_meta_box( 'faq_stats_widget', 'dashboard', 'normal' );
	}
}
