<?php
/**
 * Media Meta Filler
 *
 * Drives the bulk "Fill Image Meta" workflow:
 *   - AJAX: get attachment IDs missing alt or title, grouped by post_parent
 *   - AJAX: run a single group through the scos/fill-image-meta ability
 *   - Standard WP media library bulk action (upload.php list table)
 *   - MLA (Media Library Assistant) bulk action (mla_list_table_* hooks)
 *   - Admin UI: "Fill All Empty" button + progress panel injected on upload.php
 *
 * All processing is delegated to Fill_Image_Meta::execute_callback() so there
 * is a single source of truth for the generation + save logic.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 *
 * v1.0 | 2026-07-01
 * v1.1 | 2026-07-02 — Use wp_get_ability()->execute(); fix MLA bulk action hooks.
 */

namespace SiteEssentials\Modules\SeoMeta;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Media_Meta_Filler {

	const AJAX_GET_IDS   = 'scos_fill_image_meta_get_ids';
	const AJAX_RUN_BATCH = 'scos_fill_image_meta_run_batch';
	const NONCE_ACTION   = 'scos_fill_image_meta';
	const BULK_ACTION    = 'scos_fill_image_meta';

	/** @var array<int, int[]> Groups queued during MLA begin_bulk_action. */
	private static $mla_pending_groups = [];

	// ── Bootstrap ─────────────────────────────────────────────────────────────

	public static function init(): void {
		// AJAX handlers (admin only — upload_files users).
		add_action( 'wp_ajax_' . self::AJAX_GET_IDS,   [ __CLASS__, 'ajax_get_ids' ] );
		add_action( 'wp_ajax_' . self::AJAX_RUN_BATCH, [ __CLASS__, 'ajax_run_batch' ] );

		// Standard WP media library bulk action.
		add_filter( 'bulk_actions-upload',         [ __CLASS__, 'register_bulk_action' ] );
		add_filter( 'handle_bulk_actions-upload',  [ __CLASS__, 'handle_bulk_action' ], 10, 3 );

		// MLA (Media Library Assistant) bulk action.
		add_filter( 'mla_list_table_get_bulk_actions',   [ __CLASS__, 'register_mla_bulk_action' ] );
		add_filter( 'mla_list_table_begin_bulk_action',  [ __CLASS__, 'mla_begin_bulk_action' ], 10, 2 );
		add_filter( 'mla_list_table_end_bulk_action',    [ __CLASS__, 'mla_end_bulk_action' ], 10, 2 );

		// Inject "Fill All Empty" button + progress UI on upload.php.
		add_action( 'admin_footer-upload.php', [ __CLASS__, 'inject_admin_ui' ] );

		// Bulk action result notice.
		add_action( 'admin_notices', [ __CLASS__, 'maybe_show_bulk_notice' ] );
	}

	// ── AJAX: get IDs ─────────────────────────────────────────────────────────

	/**
	 * Return all image attachment IDs missing alt text OR having a title that
	 * looks like a raw filename stem. Results are grouped by post_parent for
	 * efficient batching (one AI call per parent group).
	 *
	 * POST params: nonce
	 */
	public static function ajax_get_ids(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'site-essentials' ) ] );
		}

		$overwrite = ! empty( $_POST['overwrite'] );

		$all_ids = get_posts( [
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		] );

		if ( empty( $all_ids ) ) {
			wp_send_json_success( [ 'groups' => [], 'total' => 0 ] );
			return;
		}

		// When not overwriting, filter to only those missing alt OR title.
		if ( ! $overwrite ) {
			$filtered = [];
			foreach ( $all_ids as $id ) {
				$id  = absint( $id );
				$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );

				// Consider title "empty" if it matches the raw filename stem.
				$post          = get_post( $id );
				$filename_stem = pathinfo( (string) get_attached_file( $id ), PATHINFO_FILENAME );
				$title_empty   = ! $post || empty( $post->post_title ) || $post->post_title === $filename_stem;

				if ( '' === $alt || $title_empty ) {
					$filtered[] = $id;
				}
			}
			$all_ids = $filtered;
		}

		if ( empty( $all_ids ) ) {
			wp_send_json_success( [ 'groups' => [], 'total' => 0 ] );
			return;
		}

		// Group by post_parent.
		$groups = [];
		foreach ( $all_ids as $id ) {
			$id     = absint( $id );
			$post   = get_post( $id );
			$parent = $post ? absint( $post->post_parent ) : 0;

			$groups[ $parent ][] = $id;
		}

		// Convert to array of group objects.
		$group_list = [];
		foreach ( $groups as $parent_id => $ids ) {
			$group_list[] = [
				'parent_post_id' => $parent_id,
				'attachment_ids' => $ids,
			];
		}

		// Sort: groups with a parent first (more context), then orphans.
		usort( $group_list, function ( $a, $b ) {
			return ( $b['parent_post_id'] > 0 ? 1 : 0 ) - ( $a['parent_post_id'] > 0 ? 1 : 0 );
		} );

		wp_send_json_success( [
			'groups'   => $group_list,
			'total'    => count( $all_ids ),
			'overwrite' => $overwrite,
		] );
	}

	// ── AJAX: run a single group ───────────────────────────────────────────────

	/**
	 * Process one parent group through the Fill_Image_Meta ability.
	 *
	 * POST params: nonce, parent_post_id, attachment_ids (JSON), overwrite
	 */
	public static function ajax_run_batch(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'site-essentials' ) ] );
		}

		if ( ! class_exists( 'WordPress\AI\Abstracts\Abstract_Ability' ) ) {
			wp_send_json_error( [ 'message' => __( 'WordPress AI plugin is required for this feature.', 'site-essentials' ) ] );
		}

		$parent_post_id = absint( $_POST['parent_post_id'] ?? 0 );
		$raw_ids        = isset( $_POST['attachment_ids'] )
			? json_decode( sanitize_text_field( wp_unslash( $_POST['attachment_ids'] ) ), true )
			: [];
		$attachment_ids = array_filter( array_map( 'absint', (array) $raw_ids ) );
		$overwrite      = ! empty( $_POST['overwrite'] );

		if ( empty( $attachment_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'No attachment IDs provided.', 'site-essentials' ) ] );
		}

		$result = self::execute_fill_image_meta( $attachment_ids, $parent_post_id, $overwrite );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			] );
		}

		wp_send_json_success( $result );
	}

	// ── Standard WP media bulk action ─────────────────────────────────────────

	/**
	 * Add "Fill Image Meta" to the standard media library bulk actions dropdown.
	 *
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public static function register_bulk_action( array $actions ): array {
		$actions[ self::BULK_ACTION ] = __( 'Fill Image Meta', 'site-essentials' );
		return $actions;
	}

	/**
	 * Handle the standard WP bulk action.
	 * Processes selected IDs synchronously (suitable for small selections; large
	 * batches should use the "Fill All Empty" async button).
	 *
	 * @param string   $redirect_to Redirect URL after action.
	 * @param string   $doaction    Action slug.
	 * @param int[]    $post_ids    Selected attachment IDs.
	 * @return string
	 */
	public static function handle_bulk_action( string $redirect_to, string $doaction, array $post_ids ): string {
		if ( self::BULK_ACTION !== $doaction ) {
			return $redirect_to;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return $redirect_to;
		}

		if ( ! class_exists( 'WordPress\AI\Abstracts\Abstract_Ability' ) ) {
			return add_query_arg( 'scos_fim_error', 'no_ai_plugin', $redirect_to );
		}

		$groups = self::group_attachment_ids_by_parent( array_filter( array_map( 'absint', $post_ids ) ) );
		$totals = self::run_groups( $groups, false );

		return add_query_arg(
			[
				'scos_fim_processed' => $totals['processed'],
				'scos_fim_errors'    => $totals['errors'],
			],
			$redirect_to
		);
	}

	// ── MLA bulk action ────────────────────────────────────────────────────────

	/**
	 * Add "Fill Image Meta" to the MLA list table bulk actions.
	 * MLA uses an array of [ 'slug' => 'label' ] pairs.
	 *
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public static function register_mla_bulk_action( array $actions ): array {
		$actions[ self::BULK_ACTION ] = __( 'Fill Image Meta', 'site-essentials' );
		return $actions;
	}

	/**
	 * MLA begin bulk action — queue selected IDs for grouped processing.
	 *
	 * @param mixed  $item_content NULL when unhandled.
	 * @param string $bulk_action  Action slug.
	 * @return mixed
	 */
	public static function mla_begin_bulk_action( $item_content, string $bulk_action ) {
		if ( self::BULK_ACTION !== $bulk_action ) {
			return $item_content;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return $item_content;
		}

		$raw_ids        = isset( $_REQUEST['cb_attachment'] ) ? (array) $_REQUEST['cb_attachment'] : [];
		$attachment_ids = array_filter( array_map( 'absint', $raw_ids ) );

		if ( empty( $attachment_ids ) ) {
			return [
				'message'         => __( 'No images selected.', 'site-essentials' ),
				'body'            => '',
				'prevent_default' => true,
			];
		}

		self::$mla_pending_groups = self::group_attachment_ids_by_parent( $attachment_ids );

		return [
			'message'         => '',
			'body'            => '',
			'prevent_default' => true,
		];
	}

	/**
	 * MLA end bulk action — run queued groups through the ability.
	 *
	 * @param mixed  $item_content NULL when unhandled.
	 * @param string $bulk_action  Action slug.
	 * @return mixed
	 */
	public static function mla_end_bulk_action( $item_content, string $bulk_action ) {
		if ( self::BULK_ACTION !== $bulk_action || empty( self::$mla_pending_groups ) ) {
			return $item_content;
		}

		if ( ! class_exists( 'WordPress\AI\Abstracts\Abstract_Ability' ) ) {
			self::$mla_pending_groups = [];
			return [
				'message'         => __( 'WordPress AI plugin is required.', 'site-essentials' ),
				'body'            => '',
				'prevent_default' => true,
			];
		}

		$totals = self::run_groups( self::$mla_pending_groups, false );
		self::$mla_pending_groups = [];

		/* translators: 1: number processed, 2: number of errors */
		$message = sprintf(
			__( 'Fill Image Meta: %1$d updated, %2$d errors.', 'site-essentials' ),
			$totals['processed'],
			$totals['errors']
		);

		return [
			'message'         => $message,
			'body'            => '',
			'prevent_default' => true,
		];
	}

	// ── Admin UI ───────────────────────────────────────────────────────────────

	/**
	 * Inject the "Fill All Empty" button and async progress panel into
	 * the media library footer, along with localised JS data.
	 */
	public static function inject_admin_ui(): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$has_ai = class_exists( 'WordPress\AI\Abstracts\Abstract_Ability' );

		wp_enqueue_script(
			'scos-fill-image-meta',
			SITE_ESSENTIALS_URL . 'Modules/SeoMeta/assets/js/scos-fill-image-meta.js',
			[ 'jquery' ],
			'1.0',
			true
		);

		wp_localize_script( 'scos-fill-image-meta', 'ScosFillImageMeta', [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
			'hasAi'     => $has_ai,
			'i18n'      => [
				'fillAllEmpty'   => __( 'Fill All Empty Image Meta', 'site-essentials' ),
				'fillOverwrite'  => __( 'Fill + Overwrite All', 'site-essentials' ),
				'running'        => __( 'Running…', 'site-essentials' ),
				'done'           => __( 'Complete', 'site-essentials' ),
				'noImages'       => __( 'No images need updating.', 'site-essentials' ),
				'noAiPlugin'     => __( 'WordPress AI plugin required. Please install and connect a provider.', 'site-essentials' ),
				'processed'      => __( 'Updated', 'site-essentials' ),
				'skipped'        => __( 'Skipped', 'site-essentials' ),
				'errors'         => __( 'Errors', 'site-essentials' ),
			],
		] );

		// Render the button panel (hidden until JS activates it).
		echo '<div id="scos-fim-panel" style="display:none;margin:12px 0 0;padding:12px 16px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;max-width:800px;">';
		echo '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
		echo '<button type="button" id="scos-fim-run" class="button button-primary" data-overwrite="0">';
		echo esc_html__( 'Fill All Empty Image Meta', 'site-essentials' );
		echo '</button>';
		echo '<button type="button" id="scos-fim-run-overwrite" class="button" data-overwrite="1">';
		echo esc_html__( 'Fill + Overwrite All', 'site-essentials' );
		echo '</button>';
		echo '<span id="scos-fim-status" style="margin-left:8px;color:#646970;"></span>';
		echo '</div>';
		echo '<div id="scos-fim-progress-wrap" style="display:none;margin-top:10px;">';
		echo '<div style="background:#f0f0f1;border-radius:3px;overflow:hidden;height:6px;">';
		echo '<div id="scos-fim-progress-bar" style="background:#2271b1;height:6px;width:0%;transition:width 0.3s;"></div>';
		echo '</div>';
		echo '<div id="scos-fim-log" style="margin-top:8px;font-size:12px;color:#646970;max-height:120px;overflow-y:auto;"></div>';
		echo '</div>';
		echo '</div>';
	}

	// ── Admin notices ──────────────────────────────────────────────────────────

	/**
	 * Show success/error notice after a synchronous bulk action redirects back.
	 */
	public static function maybe_show_bulk_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'upload' !== $screen->base ) {
			return;
		}

		if ( isset( $_GET['scos_fim_error'] ) ) {
			$msg = 'no_ai_plugin' === sanitize_key( $_GET['scos_fim_error'] )
				? __( 'Fill Image Meta: WordPress AI plugin is required. Please install and connect a provider.', 'site-essentials' )
				: __( 'Fill Image Meta: An error occurred.', 'site-essentials' );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			return;
		}

		if ( isset( $_GET['scos_fim_processed'] ) ) {
			$processed = absint( $_GET['scos_fim_processed'] );
			$errors    = absint( $_GET['scos_fim_errors'] ?? 0 );
			/* translators: 1: images updated count, 2: error count */
			$msg = sprintf(
				__( 'Fill Image Meta: %1$d image(s) updated.', 'site-essentials' ),
				$processed
			);
			if ( $errors > 0 ) {
				/* translators: %d: error count */
				$msg .= ' ' . sprintf( __( '%d error(s).', 'site-essentials' ), $errors );
			}
			$type = $errors > 0 && 0 === $processed ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	// ── Shared ability runner ──────────────────────────────────────────────────

	/**
	 * Execute the scos/fill-image-meta ability via the Abilities API.
	 *
	 * @param int[] $attachment_ids
	 * @param int   $parent_post_id
	 * @param bool  $overwrite
	 * @return array<string, mixed>|WP_Error
	 */
	private static function execute_fill_image_meta( array $attachment_ids, int $parent_post_id, bool $overwrite ) {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new WP_Error(
				'no_abilities_api',
				__( 'WordPress Abilities API is not available.', 'site-essentials' )
			);
		}

		$ability = wp_get_ability( 'scos/fill-image-meta' );
		if ( ! $ability ) {
			return new WP_Error(
				'ability_not_registered',
				__( 'Fill Image Meta ability is not registered. Ensure the WordPress AI plugin is active.', 'site-essentials' )
			);
		}

		return $ability->execute( [
			'attachment_ids' => $attachment_ids,
			'parent_post_id' => $parent_post_id,
			'overwrite'      => $overwrite,
		] );
	}

	/**
	 * Group attachment IDs by post_parent for efficient AI batching.
	 *
	 * @param int[] $attachment_ids
	 * @return array<int, int[]> Map of parent_post_id => attachment IDs.
	 */
	private static function group_attachment_ids_by_parent( array $attachment_ids ): array {
		$groups = [];
		foreach ( $attachment_ids as $id ) {
			$id     = absint( $id );
			$post   = get_post( $id );
			$parent = $post ? absint( $post->post_parent ) : 0;

			$groups[ $parent ][] = $id;
		}
		return $groups;
	}

	/**
	 * Run one or more parent groups through the ability.
	 *
	 * @param array<int, int[]> $groups    Map of parent_post_id => attachment IDs.
	 * @param bool              $overwrite
	 * @return array{processed: int, errors: int}
	 */
	private static function run_groups( array $groups, bool $overwrite ): array {
		$processed = 0;
		$errors    = 0;

		foreach ( $groups as $parent_id => $ids ) {
			$result = self::execute_fill_image_meta( $ids, (int) $parent_id, $overwrite );

			if ( is_wp_error( $result ) ) {
				$errors += count( $ids );
				continue;
			}

			$processed += (int) ( $result['processed'] ?? 0 );
			$errors    += (int) ( $result['errors'] ?? 0 );
		}

		return [
			'processed' => $processed,
			'errors'    => $errors,
		];
	}
}
