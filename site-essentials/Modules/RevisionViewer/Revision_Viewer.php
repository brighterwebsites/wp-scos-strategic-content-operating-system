<?php
/**
 * Revision Viewer — Core Logic
 *
 * Injects a floating revision navigation panel on the front end for
 * editors/admins. Swaps page content with a specific revision when
 * ?view_revision=ID appears in the URL.
 *
 * Activation: post/page must have scos_ca_next_step = "revise" or "review".
 * Access: users with edit_pages or edit_posts capability only.
 * Security: revision ownership verified against current post before loading.
 *
 * @package    SiteEssentials
 * @subpackage Modules\RevisionViewer
 * @since      1.0.0
 *
 * v1.0 | 2026-06-10
 */

namespace SiteEssentials\Modules\RevisionViewer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Revision_Viewer {

	/** Workflow statuses that enable the front-end revision viewer. */
	const ACTIVE_STATUSES = [ 'revise', 'review' ];

	/** @var int|null Revision ID being previewed; null means live. */
	private static $active_revision_id = null;

	/** @var \WP_Post|null The revision being previewed. */
	private static $active_revision = null;

	public static function init() {
		add_filter( 'query_vars',         [ self::class, 'add_query_vars' ] );
		add_action( 'wp',                 [ self::class, 'maybe_load_revision' ] );
		add_filter( 'the_content',        [ self::class, 'maybe_swap_content' ], 1 );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'wp_footer',          [ self::class, 'render_panel' ] );
	}

	// -------------------------------------------------------------------------
	// Hooks
	// -------------------------------------------------------------------------

	public static function add_query_vars( $vars ) {
		$vars[] = 'view_revision';
		return $vars;
	}

	/**
	 * At the wp hook: load and validate the requested revision.
	 */
	public static function maybe_load_revision() {
		if ( ! is_singular() || ! self::can_access() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! self::is_enabled_for_post( $post_id ) ) {
			return;
		}

		$revision_id = absint( get_query_var( 'view_revision', 0 ) );
		if ( ! $revision_id ) {
			return;
		}

		$revision = wp_get_post_revision( $revision_id );

		// Verify the revision belongs to this post — prevents viewing arbitrary revisions.
		if ( ! $revision || (int) $revision->post_parent !== $post_id ) {
			return;
		}

		self::$active_revision_id = $revision_id;
		self::$active_revision    = $revision;
	}

	/**
	 * Swap live post content with the active revision's content.
	 *
	 * Runs at priority 1 so it fires before block parsing (priority 9).
	 * Temporarily removes itself before re-applying the full filter chain
	 * on the revision content to avoid infinite recursion.
	 *
	 * @param string $content
	 * @return string
	 */
	public static function maybe_swap_content( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( null === self::$active_revision ) {
			return $content;
		}

		// Remove self, process revision through full filter chain (blocks, shortcodes, etc.), re-add.
		remove_filter( 'the_content', [ self::class, 'maybe_swap_content' ], 1 );
		$processed = apply_filters( 'the_content', self::$active_revision->post_content );
		add_filter( 'the_content', [ self::class, 'maybe_swap_content' ], 1 );

		return $processed;
	}

	/**
	 * Enqueue the panel CSS only on eligible singular pages for eligible users.
	 */
	public static function enqueue_assets() {
		if ( ! is_singular() || ! self::can_access() ) {
			return;
		}

		if ( ! self::is_enabled_for_post( get_the_ID() ) ) {
			return;
		}

		wp_enqueue_style(
			'scos-revision-viewer',
			SITE_ESSENTIALS_URL . 'Modules/RevisionViewer/assets/css/revision-viewer.css',
			[],
			'1.0.0'
		);
	}

	/**
	 * Output the floating revision panel in the footer.
	 */
	public static function render_panel() {
		if ( ! is_singular() || ! self::can_access() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! self::is_enabled_for_post( $post_id ) ) {
			return;
		}

		$revisions = self::get_revisions( $post_id );
		if ( empty( $revisions ) ) {
			return;
		}

		$current_id = self::$active_revision_id;
		$live_url   = get_permalink( $post_id );
		$next_step  = get_post_meta( $post_id, 'scos_ca_next_step', true );

		// Build a sequential (0-indexed) list, newest-first.
		$rev_list  = array_values( $revisions );
		$rev_count = count( $rev_list );

		$newer_url      = null;
		$older_url      = null;
		$pos_label      = 'Live version';
		$viewing_date   = null;
		$viewing_author = null;

		if ( null !== $current_id ) {
			// Find this revision in the list.
			$cur_idx = null;
			foreach ( $rev_list as $i => $rev ) {
				if ( (int) $rev->ID === $current_id ) {
					$cur_idx = $i;
					break;
				}
			}

			if ( null !== $cur_idx ) {
				$current_rev    = $rev_list[ $cur_idx ];
				$pos_label      = sprintf(
					/* translators: 1: revision number, 2: total revisions */
					__( 'Revision %1$d of %2$d', 'site-essentials' ),
					$cur_idx + 1,
					$rev_count
				);
				$viewing_date   = date_i18n( 'j M Y, g:i a', strtotime( $current_rev->post_date ) );
				$viewing_author = get_the_author_meta( 'display_name', $current_rev->post_author );

				// Newer = lower index (toward live). At index 0, newer = live.
				$newer_url = $cur_idx > 0
					? add_query_arg( 'view_revision', $rev_list[ $cur_idx - 1 ]->ID, $live_url )
					: $live_url;

				// Older = higher index (away from live).
				$older_url = $cur_idx < $rev_count - 1
					? add_query_arg( 'view_revision', $rev_list[ $cur_idx + 1 ]->ID, $live_url )
					: null;
			}
		} else {
			// On live version — can navigate into revisions (oldest from here = revision 1).
			$older_url = add_query_arg( 'view_revision', $rev_list[0]->ID, $live_url );
		}

		$is_viewing_revision = null !== $current_id;

		include __DIR__ . '/views/panel.php';
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private static function can_access() {
		return is_user_logged_in() &&
		       ( current_user_can( 'edit_pages' ) || current_user_can( 'edit_posts' ) );
	}

	private static function is_enabled_for_post( $post_id ) {
		$status = get_post_meta( $post_id, 'scos_ca_next_step', true );
		return in_array( $status, self::ACTIVE_STATUSES, true );
	}

	/**
	 * Return non-autosave revisions for a post, newest-first.
	 *
	 * @param int $post_id
	 * @return \WP_Post[]
	 */
	private static function get_revisions( $post_id ) {
		$revisions = wp_get_post_revisions( $post_id, [
			'order'   => 'DESC',
			'orderby' => 'date ID',
		] );

		return array_filter( $revisions, function( $rev ) {
			return ! wp_is_post_autosave( $rev );
		} );
	}
}
