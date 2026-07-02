<?php
/**
 * WP-CLI Command: wp scos fill-image-meta
 *
 * Wraps the Fill_Image_Meta ability (scos/fill-image-meta) so it can be
 * called from WP-CLI or an MCP agent without going through the REST API or
 * admin browser.
 *
 * Supports three modes:
 *   --attachment-id=<id>  Process a single attachment.
 *   --post-id=<id>        Process all qualifying images attached to a parent post.
 *   (no flag)             Process all qualifying images site-wide (grouped by parent).
 *
 * Use --overwrite to fill images that already have alt text and/or a title.
 *
 * v1.0 | 2026-07-01
 * v1.1 | 2026-07-02 — Use wp_get_ability()->execute() instead of direct instantiation.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\CLI
 */

namespace SiteEssentials\Modules\SeoMeta\CLI;

use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fill_Image_Meta_Command extends WP_CLI_Command {

	/**
	 * Generate alt text and titles for images missing metadata.
	 *
	 * Wraps the scos/fill-image-meta ability. Images are grouped by their
	 * parent post — one AI call per parent group — so the AI has full context
	 * for all images on the same page in a single request.
	 *
	 * ## OPTIONS
	 *
	 * [--attachment-id=<id>]
	 * : Process a single attachment post ID only.
	 *
	 * [--post-id=<id>]
	 * : Process all qualifying images attached to this parent post ID.
	 *
	 * [--overwrite]
	 * : Fill images that already have both alt text and a title. Default: fill-empty-only.
	 *
	 * [--dry-run]
	 * : Show which images would be processed without making any changes.
	 *
	 * [--format=<format>]
	 * : Output format: json (default) or table.
	 *
	 * ## EXAMPLES
	 *
	 *     # Fill all images site-wide that are missing alt or title
	 *     $ wp scos fill-image-meta
	 *
	 *     # Fill images attached to post 42
	 *     $ wp scos fill-image-meta --post-id=42
	 *
	 *     # Fill a single attachment
	 *     $ wp scos fill-image-meta --attachment-id=123
	 *
	 *     # Overwrite existing metadata
	 *     $ wp scos fill-image-meta --post-id=42 --overwrite
	 *
	 *     # Dry run: see which images would be processed
	 *     $ wp scos fill-image-meta --dry-run
	 *
	 * @subcommand fill-image-meta
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associated arguments / flags.
	 */
	public function __invoke( $args, $assoc_args ) {
		if ( ! class_exists( 'WordPress\AI\Abstracts\Abstract_Ability' ) ) {
			WP_CLI::error( 'The WordPress AI plugin is not active. scos/fill-image-meta requires it.' );
		}

		$attachment_id = isset( $assoc_args['attachment-id'] ) ? absint( $assoc_args['attachment-id'] ) : 0;
		$post_id       = isset( $assoc_args['post-id'] )       ? absint( $assoc_args['post-id'] )       : 0;
		$overwrite     = \WP_CLI\Utils\get_flag_value( $assoc_args, 'overwrite', false );
		$dry_run       = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run',   false );
		$format        = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'json';

		// Ensure ability class is loaded.
		require_once dirname( __DIR__ ) . '/Abilities/Fill_Image_Meta/Fill_Image_Meta.php';

		// ── Build groups ──────────────────────────────────────────────────────

		$groups = [];

		if ( $attachment_id > 0 ) {
			// Single attachment.
			$post   = get_post( $attachment_id );
			$parent = $post ? absint( $post->post_parent ) : 0;
			$groups[] = [ 'parent_post_id' => $parent, 'attachment_ids' => [ $attachment_id ] ];

		} elseif ( $post_id > 0 ) {
			// All images attached to a specific parent post.
			$ids = get_posts( [
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'post_parent'    => $post_id,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			] );
			if ( empty( $ids ) ) {
				WP_CLI::warning( "No image attachments found for post {$post_id}." );
				return;
			}
			$groups[] = [ 'parent_post_id' => $post_id, 'attachment_ids' => array_map( 'absint', $ids ) ];

		} else {
			// All qualifying images site-wide, grouped by parent.
			$all_ids = get_posts( [
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			] );

			if ( empty( $all_ids ) ) {
				WP_CLI::warning( 'No image attachments found in the media library.' );
				return;
			}

			// When not overwriting, filter to empty-only.
			if ( ! $overwrite ) {
				$filtered = [];
				foreach ( $all_ids as $id ) {
					$id            = absint( $id );
					$alt           = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
					$att           = get_post( $id );
					$filename_stem = pathinfo( (string) get_attached_file( $id ), PATHINFO_FILENAME );
					$title_empty   = ! $att || empty( $att->post_title ) || $att->post_title === $filename_stem;

					if ( '' === $alt || $title_empty ) {
						$filtered[] = $id;
					}
				}
				$all_ids = $filtered;
			}

			if ( empty( $all_ids ) ) {
				WP_CLI::success( 'All images already have alt text and titles. Nothing to do.' );
				return;
			}

			// Group by parent.
			$by_parent = [];
			foreach ( $all_ids as $id ) {
				$id     = absint( $id );
				$post   = get_post( $id );
				$parent = $post ? absint( $post->post_parent ) : 0;

				$by_parent[ $parent ][] = $id;
			}
			foreach ( $by_parent as $parent => $ids ) {
				$groups[] = [ 'parent_post_id' => $parent, 'attachment_ids' => $ids ];
			}
		}

		$total_images = array_sum( array_map( fn( $g ) => count( $g['attachment_ids'] ), $groups ) );

		WP_CLI::log( sprintf( 'Found %d image(s) in %d group(s).', $total_images, count( $groups ) ) );

		if ( $dry_run ) {
			WP_CLI::log( 'Dry run — no changes made.' );
			foreach ( $groups as $group ) {
				$parent_label = $group['parent_post_id'] > 0
					? 'Parent post #' . $group['parent_post_id'] . ' (' . get_the_title( $group['parent_post_id'] ) . ')'
					: 'Unattached';
				WP_CLI::log( '  ' . $parent_label . ': IDs ' . implode( ', ', $group['attachment_ids'] ) );
			}
			return;
		}

		// ── Process each group ────────────────────────────────────────────────

		$grand_processed = 0;
		$grand_skipped   = 0;
		$grand_errors    = 0;
		$all_results     = [];

		$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'scos/fill-image-meta' ) : null;
		if ( ! $ability ) {
			WP_CLI::error( 'scos/fill-image-meta is not registered. Ensure the WordPress AI plugin is active and abilities are loaded.' );
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Processing groups', count( $groups ) );

		foreach ( $groups as $group ) {
			$result = $ability->execute( [
				'attachment_ids' => $group['attachment_ids'],
				'parent_post_id' => $group['parent_post_id'],
				'overwrite'      => $overwrite,
			] );

			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( 'Group (parent ' . $group['parent_post_id'] . '): ' . $result->get_error_message() );
				$grand_errors += count( $group['attachment_ids'] );
			} else {
				$grand_processed += (int) ( $result['processed'] ?? 0 );
				$grand_skipped   += (int) ( $result['skipped']   ?? 0 );
				$grand_errors    += (int) ( $result['errors']    ?? 0 );
				$all_results      = array_merge( $all_results, $result['results'] ?? [] );
			}

			$progress->tick();
		}

		$progress->finish();

		if ( 'table' === $format && ! empty( $all_results ) ) {
			$rows = [];
			foreach ( $all_results as $r ) {
				$rows[] = [
					'ID'       => $r['id']       ?? '—',
					'Alt'      => isset( $r['alt'] )   ? mb_substr( $r['alt'], 0, 60 )   . ( mb_strlen( $r['alt'] ) > 60 ? '…' : '' ) : '—',
					'Title'    => $r['title']    ?? '—',
					'Category' => $r['category'] ?? '—',
					'Tag'      => $r['tag']      ?? '—',
					'Skipped'  => ! empty( $r['skipped'] ) ? 'yes' : 'no',
				];
			}
			\WP_CLI\Utils\format_items( 'table', $rows, [ 'ID', 'Alt', 'Title', 'Category', 'Tag', 'Skipped' ] );
		} else {
			WP_CLI::line( wp_json_encode( $all_results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
		}

		WP_CLI::success( sprintf(
			'Done. Updated: %d | Skipped: %d | Errors: %d',
			$grand_processed,
			$grand_skipped,
			$grand_errors
		) );
	}
}
