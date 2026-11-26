<?php
/**
 * SEO Meta — Meta Box Controller
 *
 * Registers the "SEO" meta box on all supported post types.
 * Writes to scos_seo_* keys only. No SEOPress dual-writes.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 * @since      1.0.0
 *
 * v1.1 | 2026-06-24 — Register scos-seo-meta ability category; load Suggest_Seo_Meta
 *                      and Suggest_Tldr abilities; extend enqueue_assets() with
 *                      scos-seo-suggest.js and ScosSeoSuggest localization data.
 * v1.2 | 2026-06-29 — Remove bw_tldr dual-write; consumers now read scos_seo_tldr first.
 * v1.3 | 2026-07-01 — Register scos-media ability category; load Fill_Image_Meta ability.
 * v1.4 | 2026-07-15 — Remove bw_tldr fallback read; migration confirmed complete on all sites.
 * v1.5 | 2026-07-18 — Remove SEOPress dual-writes and fallback reads; scos_seo_* is sole contract.
 * v1.6 | 2026-09-30 — Load the Get_Seo_Meta_Instructions ability.
 * v1.7 | 2026-09-30 — Load the Get_Image_Meta_Instructions ability.
 * v1.8 | 2026-09-30 — Tell the suggest script whether Content Architecture is active.
 * v1.9 | 2026-10-18 — Target query field (scos_seo_target_query).
 * v1.10 | 2026-10-25 — Load the Validate_Redirects ability (#222).
 * v1.11 | 2026-11-26 — Load the Get_Url_Seo_State ability (#245); the Advanced tab shows a dot
 *                       when a field on it holds a value for this post (#113).
 * v1.12 | 2026-11-26 — Freeze modified date takes effect on the save that changes it: uses the
 *                       submitted checkbox, and in the block editor restores the date from
 *                       before the editor's REST save (#112 QA). Breakdance's automatic
 *                       launcher-block save on every block-editor open no longer bumps the
 *                       date (builder saves over admin-ajax still do).
 * v1.13 | 2026-11-26 — On the posts page and the shop page, title, description, robots, canonical
 *                       and sitemap fields are hidden with a link to Archive Settings, and a
 *                       save leaves their stored values alone (#282).
 */

namespace SiteEssentials\Modules\SeoMeta;

use SiteEssentials\Core\Abilities\Ability_Support;
use SiteEssentials\Core\Writing_Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meta_Box {

	public static function init() {
		add_action( 'add_meta_boxes',                   [ __CLASS__, 'register' ] );
		add_action( 'add_meta_boxes',                   [ __CLASS__, 'remove_legacy_meta_boxes' ], 999, 1 );
		add_action( 'save_post',                        [ __CLASS__, 'save' ], 10, 2 );
		add_action( 'admin_enqueue_scripts',            [ __CLASS__, 'enqueue_assets' ] );
		add_filter( 'wp_insert_post_data',              [ __CLASS__, 'maybe_freeze_modified_date' ], 10, 2 );
		add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_ability_category' ] );

		// Abilities need only the core Abilities API to register. They are
		// discoverable by MCP agents whether or not an AI plugin is installed;
		// generation inside them degrades to a WP_Error when it is not.
		if ( Ability_Support::is_abilities_api_available() ) {
			require_once __DIR__ . '/Abilities/Get_Seo_Meta_Instructions/Get_Seo_Meta_Instructions.php';
			require_once __DIR__ . '/Abilities/Get_Image_Meta_Instructions/Get_Image_Meta_Instructions.php';
			require_once __DIR__ . '/Abilities/Suggest_Seo_Meta/Suggest_Seo_Meta.php';
			require_once __DIR__ . '/Abilities/Suggest_Tldr/Suggest_Tldr.php';
			require_once __DIR__ . '/Abilities/Fill_Image_Meta/Fill_Image_Meta.php';
			require_once __DIR__ . '/Abilities/Validate_Redirects/Validate_Redirects.php';
			require_once __DIR__ . '/Abilities/Get_Url_Seo_State/Get_Url_Seo_State.php';
		}
	}

	/**
	 * Register the scos-seo-meta and scos-media ability categories.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function register_ability_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category( 'scos-seo-meta', [
			'label'       => __( 'SCOS: SEO Meta', 'site-essentials' ),
			'description' => __( 'Writing instructions and AI-assisted suggestions for SEO meta fields.', 'site-essentials' ),
		] );
		wp_register_ability_category( 'scos-media', [
			'label'       => __( 'SCOS: Media', 'site-essentials' ),
			'description' => __( 'AI-assisted media metadata: alt text, titles, and taxonomy assignment.', 'site-essentials' ),
		] );
	}

	// -------------------------------------------------------------------------

	public static function register() {
		foreach ( Meta_Fields::get_post_types() as $post_type ) {
			add_meta_box(
				'scos_seo_meta',
				__( 'SEO', 'site-essentials' ),
				[ __CLASS__, 'render' ],
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Remove legacy "Breadcrumb (Short Title)" box when SEO Meta owns breadcrumb label.
	 *
	 * @since 1.0.0
	 * @param string $post_type Current post type on the edit screen.
	 */
	public static function remove_legacy_meta_boxes( string $post_type ): void {
		if ( ! defined( 'SCOS_SEO_ACTIVE' ) ) {
			return;
		}
		foreach ( [ 'normal', 'side', 'advanced' ] as $ctx ) {
			remove_meta_box( 'bw_breadcrumb_meta', $post_type, $ctx );
		}
	}

	// -------------------------------------------------------------------------

	public static function render( $post ) {
		wp_nonce_field( 'scos_seo_meta_box', 'scos_seo_nonce' );

		// ---- Read primary scos_seo_* values first, fall back to legacy ----

		$breadcrumb_title = get_post_meta( $post->ID, 'scos_seo_breadcrumb_title', true );

		$target_query = get_post_meta( $post->ID, 'scos_seo_target_query', true );

		$tldr = get_post_meta( $post->ID, 'scos_seo_tldr', true );

		$title = get_post_meta( $post->ID, 'scos_seo_title', true );

		$description = get_post_meta( $post->ID, 'scos_seo_description', true );

		$canonical = get_post_meta( $post->ID, 'scos_seo_canonical', true );

		$robots = (array) get_post_meta( $post->ID, 'scos_seo_robots', true );

		$sitemap_exclude          = (array) get_post_meta( $post->ID, 'scos_seo_sitemap_exclude', true );
		$sitemap_noindex_override = (bool) get_post_meta( $post->ID, 'scos_seo_sitemap_noindex_override', true );
		$sitemap_noindex_auto     = (bool) get_post_meta( $post->ID, 'scos_seo_sitemap_noindex_auto', true );

		$freeze_date        = (bool) get_post_meta( $post->ID, 'scos_seo_freeze_og_date', true );
		$global_freeze_date = (bool) get_option( 'scos_seo_freeze_modified_date', false );

		// Advanced-tab fields with a value on this post (#113): drives the tab's dot.
		$advanced_overrides = Url_Seo_State::post_overrides( (int) $post->ID );

		// The posts page and the shop are served as archives: their SEO is in Archive Settings (#282).
		$archive_page      = Url_Seo_State::archive_slug_for_page( (int) $post->ID );
		$archive_page_link = $archive_page
			? add_query_arg( [ 'page' => \SiteEssentials\Core\Admin_UI::SEO_PAGE_SLUG, 'tab' => 'meta' ], admin_url( 'admin.php' ) ) . '#scos-archive-pt-' . $archive_page
			: '';

		include __DIR__ . '/views/meta-box.php';
	}

	// -------------------------------------------------------------------------

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['scos_seo_nonce'] )
			|| ! wp_verify_nonce( $_POST['scos_seo_nonce'], 'scos_seo_meta_box' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( wp_is_post_revision( $post_id ) ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
		if ( ! in_array( $post->post_type, Meta_Fields::get_post_types(), true ) ) { return; }

		// ---- Breadcrumb title (human-readable label for nav, NOT the YOURLS slug) ----
		if ( isset( $_POST['scos_seo_breadcrumb_title'] ) ) {
			$val = sanitize_text_field( $_POST['scos_seo_breadcrumb_title'] );
			self::update_or_delete( $post_id, 'scos_seo_breadcrumb_title', $val );
		}

		// ---- Target query (the GSC search term to win impressions for) ----
		if ( isset( $_POST['scos_seo_target_query'] ) ) {
			$val = sanitize_text_field( wp_unslash( $_POST['scos_seo_target_query'] ) );
			self::update_or_delete( $post_id, 'scos_seo_target_query', $val );
		}

		// ---- TLDR ----
		if ( isset( $_POST['scos_seo_tldr'] ) ) {
			$val = wp_kses_post( $_POST['scos_seo_tldr'] );
			self::update_or_delete( $post_id, 'scos_seo_tldr', $val );
		}

		// Posts page / shop: the title, description, canonical, robots and sitemap fields
		// aren't shown (Archive Settings own them), so leave what is stored (#282).
		if ( null !== Url_Seo_State::archive_slug_for_page( (int) $post_id ) ) {
			update_post_meta( $post_id, 'scos_seo_freeze_og_date', ! empty( $_POST['scos_seo_freeze_og_date'] ) ? '1' : '0' );
			return;
		}

		// ---- Meta title ----
		if ( isset( $_POST['scos_seo_title'] ) ) {
			$val = sanitize_text_field( $_POST['scos_seo_title'] );
			self::update_or_delete( $post_id, 'scos_seo_title', $val );
		}

		// ---- Meta description ----
		if ( isset( $_POST['scos_seo_description'] ) ) {
			$val = sanitize_textarea_field( $_POST['scos_seo_description'] );
			self::update_or_delete( $post_id, 'scos_seo_description', $val );
		}

		// ---- Canonical URL ----
		if ( isset( $_POST['scos_seo_canonical'] ) ) {
			$val = esc_url_raw( trim( $_POST['scos_seo_canonical'] ) );
			self::update_or_delete( $post_id, 'scos_seo_canonical', $val );
		}

		// ---- Robots directives (multi-check) ----
		$valid_robots = array_keys( Meta_Fields::robots_options() );
		if ( isset( $_POST['scos_seo_robots'] ) ) {
			$robots = array_values( array_filter(
				(array) $_POST['scos_seo_robots'],
				fn( $v ) => in_array( $v, $valid_robots, true )
			) );
		} else {
			$robots = [];
		}
		update_post_meta( $post_id, 'scos_seo_robots', $robots );

		// ---- Freeze modified date (per-post) ----
		// Always write so unchecking a previously-checked box is captured.
		update_post_meta(
			$post_id,
			'scos_seo_freeze_og_date',
			! empty( $_POST['scos_seo_freeze_og_date'] ) ? '1' : '0'
		);

		// ---- Sitemap exclusions (multi-check) ----
		$valid_sitemap = array_keys( Meta_Fields::sitemap_options() );
		if ( isset( $_POST['scos_seo_sitemap_exclude'] ) ) {
			$exclude = array_values( array_filter(
				(array) $_POST['scos_seo_sitemap_exclude'],
				fn( $v ) => in_array( $v, $valid_sitemap, true )
			) );
		} else {
			$exclude = [];
		}

		// ---- Noindex ↔ sitemap auto-sync ----
		// If noindex is set and the "include despite noindex" override is not checked,
		// silently add 'xml' to sitemap_exclude and record that we auto-set it.
		// If noindex is cleared and we previously auto-set the xml exclusion, remove it.
		$noindex_now      = in_array( 'noindex', $robots, true );
		$sitemap_override = ! empty( $_POST['scos_seo_sitemap_noindex_override'] );
		$was_auto_set     = (bool) get_post_meta( $post_id, 'scos_seo_sitemap_noindex_auto', true );

		if ( $noindex_now && ! $sitemap_override ) {
			if ( ! in_array( 'xml', $exclude, true ) ) {
				$exclude[] = 'xml';
			}
			update_post_meta( $post_id, 'scos_seo_sitemap_noindex_auto', '1' );
		} else {
			// noindex cleared OR override checked — undo the auto-exclusion if we set it.
			if ( $was_auto_set ) {
				$exclude = array_values( array_diff( $exclude, [ 'xml' ] ) );
			}
			delete_post_meta( $post_id, 'scos_seo_sitemap_noindex_auto' );
		}

		update_post_meta( $post_id, 'scos_seo_sitemap_noindex_override', $sitemap_override ? '1' : '0' );
		update_post_meta( $post_id, 'scos_seo_sitemap_exclude', $exclude );
	}

	// -------------------------------------------------------------------------

	/**
	 * The freeze checkbox as submitted with this save: '1', '0', or null when the
	 * request did not come from this post's SEO meta box.
	 */
	private static function submitted_freeze_flag( int $post_id ): ?string {
		if ( ! isset( $_POST['scos_seo_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scos_seo_nonce'] ) ), 'scos_seo_meta_box' )
			|| ( isset( $_POST['post_ID'] ) && absint( $_POST['post_ID'] ) !== $post_id )
			|| ! current_user_can( 'edit_post', $post_id ) ) {
			return null;
		}
		return ! empty( $_POST['scos_seo_freeze_og_date'] ) ? '1' : '0';
	}

	/**
	 * Whether this is the block editor saving a Breakdance page whose content is (or
	 * becomes) just the launcher block. Breakdance does this on its own every time the
	 * page opens in the block editor: it checks for its launcher block before the editor
	 * has loaded the blocks, re-adds it and calls savePost(). The page's real content
	 * lives in _breakdance_data and is saved by the builder over admin-ajax, which this
	 * does not match, so builder edits still bump the date.
	 */
	private static function is_breakdance_launcher_save( int $post_id, array $data ): bool {
		$launcher = '<!-- wp:breakdance/block-breakdance-launcher /-->';
		if ( trim( wp_unslash( (string) ( $data['post_content'] ?? '' ) ) ) !== $launcher ) {
			return false;
		}
		if ( ! self::is_block_editor_rest_save() ) {
			return false;
		}
		$old = get_post( $post_id );
		if ( ! $old || ! metadata_exists( 'post', $post_id, '_breakdance_data' ) ) {
			return false;
		}
		return wp_unslash( (string) ( $data['post_title'] ?? '' ) ) === $old->post_title
			&& wp_unslash( (string) ( $data['post_excerpt'] ?? '' ) ) === $old->post_excerpt
			&& (string) ( $data['post_status'] ?? '' ) === $old->post_status;
	}

	/** Whether this request is a core posts-endpoint REST save (/wp/v2/…), as the block editor sends. */
	private static function is_block_editor_rest_save(): bool {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}
		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
		if ( '' === $route ) {
			$route = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
			$route = (string) preg_replace( '#^.*?/' . preg_quote( rest_get_url_prefix(), '#' ) . '#', '', $route );
		}
		return 0 === strpos( $route, '/wp/v2/' );
	}

	// -------------------------------------------------------------------------

	/**
	 * Prevent WP from bumping post_modified when:
	 *   - The global "freeze all modified dates" option is on, OR
	 *   - The per-post scos_seo_freeze_og_date flag is checked.
	 *
	 * Per-post value '0' can override the global freeze for that one save.
	 */
	public static function maybe_freeze_modified_date( array $data, array $postarr ): array {
		$post_id = (int) ( $postarr['ID'] ?? 0 );
		if ( ! $post_id ) {
			return $data;
		}

		// Skip revisions, auto-drafts, trash
		$status = $data['post_status'] ?? '';
		if ( in_array( $status, [ 'auto-draft', 'inherit', 'trash' ], true ) ) {
			return $data;
		}

		// Skip during autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $data;
		}

		$global_freeze = (bool) get_option( 'scos_seo_freeze_modified_date', false );
		$submitted     = self::submitted_freeze_flag( $post_id );
		$per_post      = $submitted ?? get_post_meta( $post_id, 'scos_seo_freeze_og_date', true );

		// The block editor saves the post over REST first and the meta box in a second
		// request (meta-box-loader). Remember the date from before the first save, so the
		// second request can restore it when this save ticks the box.
		$is_meta_box_request = ! empty( $_REQUEST['meta-box-loader'] );
		$snapshot_key        = 'se_seo_modified_before_' . $post_id;
		if ( ! $is_meta_box_request ) {
			set_transient( $snapshot_key, [
				get_post_field( 'post_modified', $post_id ),
				get_post_field( 'post_modified_gmt', $post_id ),
			], 5 * MINUTE_IN_SECONDS );
		}

		// Breakdance rewrites a page's content to its launcher block and saves it when the
		// page is opened in the block editor without one. That is not an edit (the page's
		// content lives in _breakdance_data), so the date stays, in the REST save and in the
		// meta-box request that follows it.
		$launcher_key  = 'se_seo_launcher_save_' . $post_id;
		$launcher_save = false;
		if ( $is_meta_box_request ) {
			$launcher_save = (bool) get_transient( $launcher_key );
			delete_transient( $launcher_key );
		} elseif ( self::is_breakdance_launcher_save( $post_id, $data ) ) {
			$launcher_save = true;
			set_transient( $launcher_key, 1, MINUTE_IN_SECONDS );
		}

		// Resolve freeze intent:
		// Global ON  → freeze, unless per-post is explicitly '0' (user override)
		// Global OFF → freeze only if per-post is explicitly '1'
		if ( $global_freeze ) {
			$should_freeze = ( '0' !== (string) $per_post );
		} else {
			$should_freeze = ( '1' === (string) $per_post );
		}

		if ( $launcher_save ) {
			$should_freeze = true;
		}

		if ( ! $should_freeze ) {
			return $data;
		}

		// Preserve current modified date — on brand-new posts (no prior date) skip.
		$original     = get_post_field( 'post_modified',     $post_id );
		$original_gmt = get_post_field( 'post_modified_gmt', $post_id );
		$snapshot     = $is_meta_box_request ? get_transient( $snapshot_key ) : false;
		if ( is_array( $snapshot ) && ! empty( $snapshot[0] ) ) {
			[ $original, $original_gmt ] = $snapshot;
			delete_transient( $snapshot_key );
		}

		if ( $original && '0000-00-00 00:00:00' !== $original ) {
			$data['post_modified']     = $original;
			$data['post_modified_gmt'] = $original_gmt;
		}

		return $data;
	}

	// -------------------------------------------------------------------------

	public static function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) { return; }
		global $post;
		if ( ! $post || ! in_array( $post->post_type, Meta_Fields::get_post_types(), true ) ) { return; }

		$css_path = SITE_ESSENTIALS_PATH . 'Modules/SeoMeta/assets/meta-box.css';
		$js_path  = SITE_ESSENTIALS_PATH . 'Modules/SeoMeta/assets/meta-box.js';

		wp_enqueue_style(
			'scos-seo-meta-box',
			SITE_ESSENTIALS_URL . 'Modules/SeoMeta/assets/meta-box.css',
			[],
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0'
		);
		wp_enqueue_script(
			'scos-seo-meta-box',
			SITE_ESSENTIALS_URL . 'Modules/SeoMeta/assets/meta-box.js',
			[ 'jquery' ],
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0.0',
			true
		);
		wp_localize_script( 'scos-seo-meta-box', 'scosSeoMeta', [
			'noindexSitemapMsg' => esc_html__( 'This page has been removed from the sitemap because noindex is set.', 'site-essentials' ),
		] );

		// AI suggest script — needs the AI client, since the buttons drive
		// server-side generation rather than just calling an ability.
		if ( Ability_Support::is_ai_client_available() ) {
			$suggest_js_path = SITE_ESSENTIALS_PATH . 'Modules/SeoMeta/assets/scos-seo-suggest.js';
			wp_enqueue_script(
				'scos-seo-suggest',
				SITE_ESSENTIALS_URL . 'Modules/SeoMeta/assets/scos-seo-suggest.js',
				[ 'jquery' ],
				file_exists( $suggest_js_path ) ? (string) filemtime( $suggest_js_path ) : '1.0.0',
				true
			);

			wp_localize_script( 'scos-seo-suggest', 'ScosSeoSuggest', [
				'endpointSeoMeta' => rest_url( 'wp-abilities/v1/abilities/scos/suggest-seo-meta/run' ),
				'endpointTldr'    => rest_url( 'wp-abilities/v1/abilities/scos/suggest-tldr/run' ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'postId'          => $post->ID,
				'intentGoalText'  => sanitize_text_field( Writing_Context::intent_goal( (int) $post->ID ) ),
				// The intent-goal nudge only makes sense where there is somewhere to set one.
				'caActive'        => defined( 'SCOS_CA_ACTIVE' ),
			] );
		}
	}

	// ---- Helpers ----

	private static function update_or_delete( $post_id, $key, $value ) {
		if ( ! empty( $value ) ) {
			update_post_meta( $post_id, $key, $value );
		} else {
			delete_post_meta( $post_id, $key );
		}
	}
}
