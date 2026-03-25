<?php
/**
 * FAQ Module — Site Essentials
 *
 * Enhances the legacy brighter-core FAQ CPT with:
 *   - Hierarchical parent/child support
 *   - Topic-based permalink structure: /faq/{topic-slug}/{faq-slug}/
 *   - scos_topic taxonomy integration for Primary Topic
 *   - scos_faq_* meta key prefix (dual-writes to _faq_* for block/REST compat)
 *   - Schema answer pre-fill from TLDR field
 *   - Moved under Site Essentials menu (hidden from WP sidebar top-level)
 *   - Archive and topic-archive redirect settings
 *
 * Meta boxes for SEO and Content Architecture are provided automatically by
 * those modules — faq is a public CPT and is included in Taxonomies::get_post_types().
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

		// ── Suppress legacy bw-faq.php hooks ─────────────────────────────────
		// Module_Loader runs at init priority 10; brighter-core hooks are
		// registered at file-include time (before init), so remove_action()
		// calls here arrive before any of those priorities fire.

		remove_action( 'init', 'register_faq_cpt', 20 );                         // CPT: we re-register below
		remove_action( 'init', 'bw_faq_maybe_flush_rewrite_rules', 999 );        // Rewrite flush: we manage our own
		remove_filter( 'post_type_link', 'bw_faq_force_permalink_structure', 10 ); // Permalink: topic-folder takes over
		remove_action( 'save_post_faq', 'save_faq_meta_fields' );                // Save: our handler runs instead
		remove_filter( 'manage_faq_posts_columns', 'faq_admin_columns' );        // Columns: our set replaces legacy
		remove_action( 'manage_faq_posts_custom_column', 'faq_admin_column_content', 10 );
		// Keep: register_faq_selector_block, rest routes, shortcode (still used)
		// Keep: faq_dashboard_widget (removed below on wp_dashboard_setup)

		// ── Register enhanced CPT (priority 21 for rewrite tag, 22 for CPT) ──
		add_action( 'init', [ self::class, 'register_rewrite_tag' ], 21 );
		add_action( 'init', [ self::class, 'register_cpt' ],         22 );
		add_action( 'init', [ self::class, 'add_topic_support' ],    30 );
		add_action( 'init', [ self::class, 'maybe_flush_rewrites' ], 999 );

		// ── Permalink generation ───────────────────────────────────────────────
		add_filter( 'post_type_link', [ self::class, 'faq_permalink' ], 10, 2 );

		// ── Archive / topic-folder redirects ──────────────────────────────────
		add_action( 'template_redirect', [ self::class, 'handle_redirects' ] );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes',                 [ self::class, 'replace_meta_boxes' ], 20 );
			add_action( 'save_post_faq',                  [ self::class, 'save_meta' ],          20 );
			add_filter( 'manage_faq_posts_columns',       [ self::class, 'admin_columns' ] );
			add_action( 'manage_faq_posts_custom_column', [ self::class, 'admin_column_content' ], 10, 2 );
			add_action( 'wp_dashboard_setup',             [ self::class, 'remove_dashboard_widget' ], 20 );
		}
	}

	// =========================================================================
	// CPT Registration
	// =========================================================================

	/**
	 * Register %scos_topic% rewrite tag so it can be used in the CPT
	 * rewrite slug and substituted with the actual topic slug.
	 */
	public static function register_rewrite_tag(): void {
		add_rewrite_tag( '%scos_topic%', '([^/]+)', 'scos_topic=' );

		// Single-segment /faq/topic-slug/ rule → handled by template_redirect redirect
		add_rewrite_rule( '^faq/([^/]+)/?$', 'index.php?scos_faq_topic_browse=$matches[1]', 'top' );
		add_filter( 'query_vars', function ( array $vars ): array {
			$vars[] = 'scos_faq_topic_browse';
			return $vars;
		} );
	}

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

		// has_archive mirrors the scos_faq_archive_enabled option so the CPT's
		// built-in archive is only active when explicitly switched on.
		$archive_enabled = (bool) get_option( 'scos_faq_archive_enabled', false );

		register_post_type( 'faq', [
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // Hidden — added via Admin_UI under Site Essentials
			'query_var'           => true,
			'rewrite'             => [
				'slug'       => 'faq/%scos_topic%',
				'with_front' => false,
			],
			'exclude_from_search' => true,
			'capability_type'     => 'post',
			'has_archive'         => $archive_enabled ? 'faq' : false,
			'hierarchical'        => true,   // Enables parent/child FAQ grouping
			'menu_position'       => 25,
			'menu_icon'           => 'dashicons-editor-help',
			'show_in_rest'        => true,
			'supports'            => [ 'title', 'editor', 'revisions', 'page-attributes' ],
		] );
	}

	/**
	 * Attach scos_topic taxonomy to the faq CPT so FAQs share the
	 * site-wide topic vocabulary (and the CA meta box shows Primary Topic).
	 */
	public static function add_topic_support(): void {
		if ( taxonomy_exists( 'scos_topic' ) ) {
			register_taxonomy_for_object_type( 'scos_topic', 'faq' );
		}
	}

	// =========================================================================
	// Permalink — /faq/{topic-slug}/{faq-slug}/
	// =========================================================================

	/**
	 * Replace %scos_topic% placeholder with the FAQ's assigned topic slug.
	 * Falls back to 'general' when no topic is assigned.
	 *
	 * @param string   $link The permalink.
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	public static function faq_permalink( string $link, \WP_Post $post ): string {
		if ( 'faq' !== $post->post_type || false === strpos( $link, '%scos_topic%' ) ) {
			return $link;
		}

		$terms = get_the_terms( $post->ID, 'scos_topic' );
		$slug  = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->slug : 'general';

		return str_replace( '%scos_topic%', $slug, $link );
	}

	// =========================================================================
	// Rewrites flush
	// =========================================================================

	/**
	 * Flush rewrite rules when our registered structure version changes.
	 * Runs at init priority 999, after everything is registered.
	 */
	public static function maybe_flush_rewrites(): void {
		$version = '2.1'; // Bump when slug/tag structure changes
		if ( get_option( 'scos_faq_rewrite_version' ) !== $version ) {
			flush_rewrite_rules( false );
			update_option( 'scos_faq_rewrite_version', $version );
		}
	}

	// =========================================================================
	// Archive / topic-folder redirects
	// =========================================================================

	/**
	 * Handle redirects for /faq/ archive and /faq/topic-slug/ pages.
	 *
	 * Settings (stored as wp_options):
	 *   scos_faq_archive_enabled  (bool)   — allow the /faq/ archive; false = redirect/404
	 *   scos_faq_archive_redirect (string) — redirect /faq/ to this URL (empty = 404)
	 *   scos_faq_topic_redirect   (string) — redirect /faq/topic/ to this URL (empty = /faq/)
	 */
	public static function handle_redirects(): void {
		// /faq/ archive ───────────────────────────────────────────────────────
		if ( is_post_type_archive( 'faq' ) ) {
			$archive_enabled  = (bool) get_option( 'scos_faq_archive_enabled', false );
			$archive_redirect = (string) get_option( 'scos_faq_archive_redirect', '' );

			if ( ! $archive_enabled ) {
				if ( ! empty( $archive_redirect ) ) {
					wp_safe_redirect( esc_url_raw( $archive_redirect ), 301 );
					exit;
				}
				// No redirect configured — fall through to 404
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );
				return;
			}
		}

		// /faq/topic-slug/ (single segment, matched by our rewrite rule) ──────
		$topic_browse = get_query_var( 'scos_faq_topic_browse', '' );
		if ( '' !== $topic_browse ) {
			$topic_redirect = (string) get_option( 'scos_faq_topic_redirect', '' );
			$target         = ! empty( $topic_redirect ) ? $topic_redirect : home_url( '/faq/' );
			wp_safe_redirect( esc_url_raw( $target ), 301 );
			exit;
		}
	}

	// =========================================================================
	// Meta Boxes
	// =========================================================================

	/**
	 * Remove legacy brighter-core meta boxes and add our lean Schema Answer box.
	 * Primary Topic is intentionally omitted — the Content Architecture meta box
	 * handles scos_topic assignment and appears automatically on this CPT.
	 */
	public static function replace_meta_boxes(): void {
		remove_meta_box( 'faq_schema_answer', 'faq', 'normal' );
		remove_meta_box( 'faq_schema_toggle', 'faq', 'side' );

		add_meta_box(
			'scos_faq_schema',
			__( 'FAQ Schema Answer', 'site-essentials' ),
			[ self::class, 'render_meta_box' ],
			'faq',
			'normal',
			'high'
		);
	}

	public static function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'scos_faq_meta_save', 'scos_faq_nonce' );

		// Read: scos_faq_schema_answer primary, _faq_schema_answer legacy fallback
		$schema_answer = get_post_meta( $post->ID, 'scos_faq_schema_answer', true );
		if ( $schema_answer === '' ) {
			$schema_answer = (string) get_post_meta( $post->ID, '_faq_schema_answer', true );
		}

		// TLDR for pre-fill (SEO module field)
		$tldr = get_post_meta( $post->ID, 'scos_seo_tldr', true )
			?: get_post_meta( $post->ID, 'bw_tldr', true );
		?>

		<p class="description" style="margin-bottom:10px;">
			<?php esc_html_e( 'Optional concise answer used in FAQPage schema markup generated by the FAQ Selector block. If left empty, the full post content is trimmed and used instead.', 'site-essentials' ); ?>
			<?php esc_html_e( 'Recommended: 100–300 characters, plain text.', 'site-essentials' ); ?>
		</p>

		<?php if ( ! empty( $tldr ) ) : ?>
			<p class="description" style="margin-bottom:6px;">
				<?php esc_html_e( 'TLDR available:', 'site-essentials' ); ?>
				<em><?php echo esc_html( wp_trim_words( $tldr, 20 ) ); ?></em>
				&nbsp;<a href="#" id="scos-faq-use-tldr" style="font-size:12px;">
					<?php esc_html_e( '↑ Use as schema answer', 'site-essentials' ); ?>
				</a>
			</p>
			<script>
			( function() {
				var btn = document.getElementById( 'scos-faq-use-tldr' );
				if ( btn ) {
					btn.addEventListener( 'click', function( e ) {
						e.preventDefault();
						var ta = document.getElementById( 'scos_faq_schema_answer' );
						ta.value = <?php echo wp_json_encode( $tldr ); ?>;
						document.getElementById( 'scos-faq-cc' ).textContent = ta.value.length;
					} );
				}
			} )();
			</script>
		<?php endif; ?>

		<textarea
			name="scos_faq_schema_answer"
			id="scos_faq_schema_answer"
			rows="3"
			style="width:100%;"
			placeholder="<?php esc_attr_e( 'Concise answer for schema (plain text, no HTML)', 'site-essentials' ); ?>"
		><?php echo esc_textarea( $schema_answer ); ?></textarea>
		<p class="description">
			<span id="scos-faq-cc"><?php echo esc_html( (string) strlen( $schema_answer ) ); ?></span>
			<?php esc_html_e( ' chars', 'site-essentials' ); ?>
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

		// Schema answer — dual-write so the Gutenberg block/REST keep working
		$schema_answer = isset( $_POST['scos_faq_schema_answer'] )
			? sanitize_textarea_field( wp_unslash( $_POST['scos_faq_schema_answer'] ) )
			: '';
		update_post_meta( $post_id, 'scos_faq_schema_answer', $schema_answer );
		update_post_meta( $post_id, '_faq_schema_answer',     $schema_answer );

		// Always keep _faq_enable_schema = '1' (schema controlled at block level, not per-FAQ)
		update_post_meta( $post_id, '_faq_enable_schema',     '1' );
		update_post_meta( $post_id, 'scos_faq_enable_schema', '1' );
	}

	// =========================================================================
	// Admin Columns
	// =========================================================================

	public static function admin_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $value ) {
			// Drop legacy columns from bw-faq.php
			if ( in_array( $key, [ 'schema_enabled', 'char_count' ], true ) ) {
				continue;
			}
			$new[ $key ] = $value;
			if ( $key === 'title' ) {
				$new['scos_faq_topic']  = __( 'Primary Topic', 'site-essentials' );
				$new['scos_faq_parent'] = __( 'Parent', 'site-essentials' );
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

		if ( $column === 'scos_faq_parent' ) {
			$post = get_post( $post_id );
			if ( $post && $post->post_parent ) {
				$parent = get_post( $post->post_parent );
				if ( $parent ) {
					printf(
						'<a href="%s">%s</a>',
						esc_url( get_edit_post_link( $parent->ID ) ),
						esc_html( get_the_title( $parent->ID ) )
					);
				}
			} else {
				echo '<span style="color:#999">—</span>';
			}
		}
	}

	// =========================================================================
	// Misc
	// =========================================================================

	public static function remove_dashboard_widget(): void {
		remove_meta_box( 'faq_stats_widget', 'dashboard', 'normal' );
	}
}
