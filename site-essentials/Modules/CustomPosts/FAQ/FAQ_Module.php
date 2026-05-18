<?php
/**
 * FAQ Module — Site Essentials
 *
 * Owns the entire FAQ submodule (CPT, meta boxes, admin columns, shortcode,
 * Gutenberg block, REST endpoint for the editor, and schema-graph injection).
 *
 * Naming:
 *   Post type:    faq
 *   Meta:         scos_faq_schema_answer, scos_faq_enable_schema
 *                 (legacy _faq_schema_answer / _faq_enable_schema dual-written for
 *                  back-compat with existing data; reads prefer scos_faq_* and
 *                  fall back to _faq_*)
 *   Options:      scos_faq_archive_enabled, scos_faq_archive_redirect,
 *                 scos_faq_topic_redirect, scos_faq_rewrite_version
 *   Block:        brighter/faq-selector (name retained for backward
 *                 compatibility with existing post_content references)
 *   REST routes:  site-essentials/v1/faqs       (editor only — current_user_can edit_posts)
 *                 brighter-core/v1/faqs         (token-auth, owned by brighter-core API
 *                                                — still used by external GPT/MCP/Postly)
 *
 * v1.0 | 2026-05-19
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

	/** Post type slug. */
	const POST_TYPE = 'faq';

	/** Primary meta key for the concise schema answer (scos_faq_*). */
	const META_SCHEMA_ANSWER = 'scos_faq_schema_answer';

	/** Primary meta key for the per-FAQ schema toggle (scos_faq_*). */
	const META_ENABLE_SCHEMA = 'scos_faq_enable_schema';

	/** Legacy meta keys — still dual-written so any existing reader keeps working. */
	const LEGACY_META_SCHEMA_ANSWER = '_faq_schema_answer';
	const LEGACY_META_ENABLE_SCHEMA = '_faq_enable_schema';

	/** Block name — retained for backward compatibility with existing post_content. */
	const BLOCK_NAME = 'brighter/faq-selector';

	// =========================================================================
	// Bootstrap
	// =========================================================================

	/**
	 * Initialise the FAQ submodule.
	 *
	 * Called by Cpt_Module::init() only when the enable_faq option is true.
	 * Registers the CPT, permalinks, redirects, admin UI, block, REST and
	 * schema-graph injection.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init(): void {
		if ( ! defined( 'SCOS_FAQ_ACTIVE' ) ) {
			define( 'SCOS_FAQ_ACTIVE', true );
		}

		// ── Register enhanced CPT ─────────────────────────────────────────────
		// IMPORTANT: Taxonomies::associate_post_types() fires at init priority 20
		// and populates a static cache of supported post types used by the CA and
		// SEO meta boxes. We must register the faq CPT BEFORE priority 20 so it
		// is included in that cache (and therefore gets the CA + SEO meta boxes).
		add_action( 'init', [ self::class, 'register_rewrite_tag' ], 15 );
		add_action( 'init', [ self::class, 'register_cpt' ],         16 );
		add_action( 'init', [ self::class, 'add_topic_support' ],    25 ); // after associate_post_types (20)
		add_action( 'init', [ self::class, 'maybe_flush_rewrites' ], 999 );

		// ── Permalink generation ──────────────────────────────────────────────
		add_filter( 'post_type_link', [ self::class, 'faq_permalink' ], 10, 2 );

		// ── Archive / topic-folder redirects ──────────────────────────────────
		add_action( 'template_redirect', [ self::class, 'handle_redirects' ] );

		// ── Sitemap exclusion (Yoast) ─────────────────────────────────────────
		add_filter( 'wpseo_sitemap_exclude_post_type', [ self::class, 'exclude_from_yoast_sitemap' ], 10, 2 );

		// ── Shortcode ─────────────────────────────────────────────────────────
		add_shortcode( 'faqs', [ self::class, 'shortcode' ] );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes',                 [ self::class, 'register_meta_boxes' ], 20 );
			add_action( 'save_post_faq',                  [ self::class, 'save_meta' ],          20 );
			add_filter( 'manage_faq_posts_columns',       [ self::class, 'admin_columns' ] );
			add_action( 'manage_faq_posts_custom_column', [ self::class, 'admin_column_content' ], 10, 2 );
		}

		// ── Sub-components ────────────────────────────────────────────────────
		require_once __DIR__ . '/FAQ_Block.php';
		FAQ_Block::register();

		require_once __DIR__ . '/FAQ_REST.php';
		FAQ_REST::register();

		require_once __DIR__ . '/FAQ_Schema_Graph.php';
		FAQ_Schema_Graph::register();
	}

	// =========================================================================
	// CPT Registration
	// =========================================================================

	/**
	 * Register %scos_topic% rewrite tag so it can be used in the CPT
	 * rewrite slug and substituted with the actual topic slug.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_rewrite_tag(): void {
		add_rewrite_tag( '%scos_topic%', '([^/]+)', 'scos_topic=' );

		// Single-segment /faq/topic-slug/ rule → handled by template_redirect redirect
		add_rewrite_rule( '^faq/([^/]+)/?$', 'index.php?scos_faq_topic_browse=$matches[1]', 'top' );
		add_filter( 'query_vars', static function ( array $vars ): array {
			$vars[] = 'scos_faq_topic_browse';
			return $vars;
		} );
	}

	/**
	 * Register the faq CPT.
	 *
	 * @since 1.0.0
	 * @return void
	 */
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

		register_post_type( self::POST_TYPE, [
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true, // Top-level menu — FAQs stay as a first-class admin menu item
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
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_topic_support(): void {
		if ( taxonomy_exists( 'scos_topic' ) ) {
			register_taxonomy_for_object_type( 'scos_topic', self::POST_TYPE );
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
		if ( self::POST_TYPE !== $post->post_type || false === strpos( $link, '%scos_topic%' ) ) {
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
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function maybe_flush_rewrites(): void {
		$version = '2.3'; // Bump when slug/tag structure changes
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
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_redirects(): void {
		// /faq/ archive ───────────────────────────────────────────────────────
		if ( is_post_type_archive( self::POST_TYPE ) ) {
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
	// Sitemap exclusion (Yoast)
	// =========================================================================

	/**
	 * Exclude FAQ CPT from the Yoast XML sitemap.
	 *
	 * The CPT is also `exclude_from_search => true`, so WordPress core
	 * sitemap excludes it automatically. This filter handles Yoast.
	 * Returns the existing $excluded flag for non-faq post types so we
	 * don't accidentally exclude everything else.
	 *
	 * @since 1.0.0
	 * @param bool   $excluded  Existing exclusion flag from Yoast.
	 * @param string $post_type Post type being evaluated.
	 * @return bool
	 */
	public static function exclude_from_yoast_sitemap( $excluded, $post_type = '' ): bool {
		if ( self::POST_TYPE === (string) $post_type ) {
			return true;
		}
		return (bool) $excluded;
	}

	// =========================================================================
	// Meta Boxes
	// =========================================================================

	/**
	 * Register the FAQ Schema Answer meta box.
	 *
	 * Primary Topic is intentionally omitted — the Content Architecture meta box
	 * handles scos_topic assignment and appears automatically on this CPT.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_meta_boxes(): void {
		add_meta_box(
			'scos_faq_schema',
			__( 'FAQ Schema Answer', 'site-essentials' ),
			[ self::class, 'render_meta_box' ],
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the FAQ Schema Answer meta box.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public static function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'scos_faq_meta_save', 'scos_faq_nonce' );

		// Read: scos_faq_schema_answer primary, _faq_schema_answer legacy fallback
		$schema_answer = (string) get_post_meta( $post->ID, self::META_SCHEMA_ANSWER, true );
		if ( '' === $schema_answer ) {
			$schema_answer = (string) get_post_meta( $post->ID, self::LEGACY_META_SCHEMA_ANSWER, true );
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

	/**
	 * Save the FAQ Schema Answer meta. Dual-writes scos_faq_* and _faq_*
	 * for backward compatibility with any reader still on the legacy keys.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function save_meta( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['scos_faq_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scos_faq_nonce'] ) ), 'scos_faq_meta_save' )
		) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$schema_answer = isset( $_POST['scos_faq_schema_answer'] )
			? sanitize_textarea_field( wp_unslash( $_POST['scos_faq_schema_answer'] ) )
			: '';

		update_post_meta( $post_id, self::META_SCHEMA_ANSWER,        $schema_answer );
		update_post_meta( $post_id, self::LEGACY_META_SCHEMA_ANSWER, $schema_answer );

		// Schema is controlled at block level, not per-FAQ — keep '1' for back-compat.
		update_post_meta( $post_id, self::META_ENABLE_SCHEMA,        '1' );
		update_post_meta( $post_id, self::LEGACY_META_ENABLE_SCHEMA, '1' );
	}

	// =========================================================================
	// Admin Columns
	// =========================================================================

	/**
	 * Replace the default columns with Primary Topic + Parent.
	 *
	 * @since 1.0.0
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function admin_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $value ) {
			$new[ $key ] = $value;
			if ( 'title' === $key ) {
				$new['scos_faq_topic']  = __( 'Primary Topic', 'site-essentials' );
				$new['scos_faq_parent'] = __( 'Parent', 'site-essentials' );
			}
		}
		return $new;
	}

	/**
	 * Populate custom admin column content.
	 *
	 * @since 1.0.0
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function admin_column_content( string $column, int $post_id ): void {
		if ( 'scos_faq_topic' === $column ) {
			$terms = get_the_terms( $post_id, 'scos_topic' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				echo esc_html( $terms[0]->name );
			} else {
				echo '<span style="color:#999">—</span>';
			}
			return;
		}

		if ( 'scos_faq_parent' === $column ) {
			$post = get_post( $post_id );
			if ( $post && $post->post_parent ) {
				$parent = get_post( $post->post_parent );
				if ( $parent ) {
					printf(
						'<a href="%s">%s</a>',
						esc_url( (string) get_edit_post_link( $parent->ID ) ),
						esc_html( get_the_title( $parent->ID ) )
					);
				}
			} else {
				echo '<span style="color:#999">—</span>';
			}
		}
	}

	// =========================================================================
	// Shortcode
	// =========================================================================

	/**
	 * [faqs ids="123,456,789" format="accordion" heading="h3" schema="true"]
	 *
	 * Delegates to the block render so output and schema behaviour are identical.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts(
			[
				'ids'     => '',
				'format'  => 'accordion',
				'heading' => 'h3',
				'schema'  => 'true',
			],
			(array) $atts,
			'faqs'
		);

		$faq_ids = array_filter( array_map( 'intval', explode( ',', (string) $atts['ids'] ) ) );

		return FAQ_Block::render( [
			'selectedFaqs'  => array_values( $faq_ids ),
			'displayFormat' => sanitize_key( (string) $atts['format'] ),
			'headingLevel'  => sanitize_key( (string) $atts['heading'] ),
			'enableSchema'  => 'true' === strtolower( (string) $atts['schema'] ),
		] );
	}

	// =========================================================================
	// Helpers — used by FAQ_Block, FAQ_REST, FAQ_Schema_Graph
	// =========================================================================

	/**
	 * Get the schema answer for an FAQ (stripped of HTML), or false if
	 * schema is disabled for this FAQ.
	 *
	 * @since 1.0.0
	 * @param int $faq_id FAQ post ID.
	 * @return string|false
	 */
	public static function get_schema_answer( int $faq_id ) {
		// Check enable_schema (prefer scos_faq_*, fall back to legacy).
		$enabled = get_post_meta( $faq_id, self::META_ENABLE_SCHEMA, true );
		if ( '' === $enabled ) {
			$enabled = get_post_meta( $faq_id, self::LEGACY_META_ENABLE_SCHEMA, true );
		}
		if ( '0' === (string) $enabled ) {
			return false;
		}

		// Prefer scos_faq_schema_answer, fall back to legacy.
		$schema_answer = (string) get_post_meta( $faq_id, self::META_SCHEMA_ANSWER, true );
		if ( '' === $schema_answer ) {
			$schema_answer = (string) get_post_meta( $faq_id, self::LEGACY_META_SCHEMA_ANSWER, true );
		}

		if ( '' !== $schema_answer ) {
			$answer = $schema_answer;
		} else {
			$post = get_post( $faq_id );
			if ( ! $post ) {
				return false;
			}
			$answer = wp_trim_words( wp_strip_all_tags( $post->post_content ), 50, '...' );
		}

		$answer = wp_strip_all_tags( $answer );
		$answer = strip_shortcodes( $answer );
		$answer = preg_replace( '/\s+/', ' ', $answer );

		return trim( (string) $answer );
	}

	/**
	 * Get multiple published FAQ posts by IDs, preserving order.
	 *
	 * @since 1.0.0
	 * @param int[] $faq_ids FAQ post IDs.
	 * @return \WP_Post[]
	 */
	public static function get_by_ids( array $faq_ids ): array {
		$faq_ids = array_filter( array_map( 'intval', $faq_ids ) );
		if ( empty( $faq_ids ) ) {
			return [];
		}

		return get_posts( [
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post__in'       => $faq_ids,
			'orderby'        => 'post__in',
			'no_found_rows'  => true,
		] );
	}

	/**
	 * Search published FAQs by keyword.
	 *
	 * @since 1.0.0
	 * @param string $keyword Search term.
	 * @param int    $limit   Posts per page (-1 for all).
	 * @return \WP_Post[]
	 */
	public static function search( string $keyword, int $limit = -1 ): array {
		return get_posts( [
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			's'              => $keyword,
			'orderby'        => 'relevance',
			'no_found_rows'  => true,
		] );
	}
}
