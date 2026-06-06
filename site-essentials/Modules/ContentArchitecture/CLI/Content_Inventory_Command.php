<?php
/**
 * WP-CLI Command: wp scos content-inventory
 *
 * Gathers full WordPress content inventory via WP-CLI.
 * Reuses Content_Inventory_Gatherer (same logic as REST endpoint).
 *
 * Supports:
 * - Incremental collection via --since=<timestamp>
 * - Output formats: json (default), table, csv (phase 2.3)
 * - File output: --file=<path> to write JSON instead of stdout
 *
 * v1.0 | 2026-06-06
 *
 * @package    SiteEssentials
 * @subpackage Modules\ContentArchitecture\CLI
 */

namespace SiteEssentials\Modules\ContentArchitecture\CLI;

use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Content_Inventory_Command extends WP_CLI_Command {

	/**
	 * Gather WordPress content inventory.
	 *
	 * Collects all published posts/pages with analysis metadata, taxonomies, and URLs.
	 * Supports incremental collection via --since to retrieve only changed posts.
	 *
	 * ## OPTIONS
	 *
	 * [--since=<timestamp>]
	 * : ISO 8601 timestamp. Return posts modified or analyzed since this time.
	 * ---
	 * example: '2026-06-01 12:00:00' or '2026-06-01T12:00:00Z'
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: json
	 * options:
	 *   - json
	 *   - table
	 *   - csv
	 * ---
	 *
	 * [--file=<path>]
	 * : Write output to file instead of stdout (JSON format only).
	 * If not provided, output goes to STDOUT.
	 * ---
	 * example: '/home/example.com/data/content-inventory.json'
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 * wp scos content-inventory
	 * : Gather all content, output JSON to STDOUT.
	 *
	 * wp scos content-inventory --format=table
	 * : Gather all content, display as table.
	 *
	 * wp scos content-inventory --since="2026-06-01" --file="/tmp/inventory.json"
	 * : Gather posts changed since June 1st, write to file.
	 *
	 * @subcommand content-inventory
	 *
	 * @when after_wp_load
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associated arguments (flags).
	 */
	public function __invoke( $args, $assoc_args ) {
		$since   = WP_CLI::get_flag_value( $assoc_args, 'since' );
		$format  = WP_CLI::get_flag_value( $assoc_args, 'format', 'json' );
		$file    = WP_CLI::get_flag_value( $assoc_args, 'file' );

		// Validate format
		$allowed_formats = [ 'json', 'table', 'csv' ];
		if ( ! in_array( $format, $allowed_formats, true ) ) {
			WP_CLI::error( "Invalid format: $format. Allowed: " . implode( ', ', $allowed_formats ) );
		}

		// CSV is phase 2.3
		if ( 'csv' === $format ) {
			WP_CLI::error( 'CSV format coming in phase 2.3. Use --format=json or --format=table for now.' );
		}

		WP_CLI::log( 'Gathering content inventory...' );

		try {
			// Increase memory limit for large inventories
			$original_memory = ini_get( 'memory_limit' );
			@ini_set( 'memory_limit', '256M' );

			// Gather inventory using the same class as REST endpoint
			$inventory = \SiteEssentials\Modules\ContentArchitecture\Content_Inventory_Gatherer::gather( $since );

			// Restore original memory limit
			@ini_set( 'memory_limit', $original_memory );

			// Extract metadata for summary
			$meta  = $inventory['meta'];
			$posts = $inventory['posts'];

			// Output summary to stderr (WP-CLI logs)
			WP_CLI::log(
				sprintf(
					'✓ Gathered %d posts (%d complete, %d pending analysis)',
					$meta['total_posts_included'],
					$meta['analysis_complete_count'],
					$meta['analysis_pending_count']
				)
			);

			if ( $since ) {
				WP_CLI::log( "Since: {$since}" );
			}

			// Handle output based on format
			switch ( $format ) {
				case 'json':
					$this->output_json( $inventory, $file );
					break;

				case 'table':
					$this->output_table( $posts );
					break;

				case 'csv':
					// Phase 2.3
					WP_CLI::error( 'CSV format not yet implemented.' );
					break;
			}

			WP_CLI::success( 'Content inventory complete.' );

		} catch ( \Exception $e ) {
			WP_CLI::error( 'Error gathering inventory: ' . $e->getMessage() );
		}
	}

	/**
	 * Output inventory as JSON.
	 *
	 * @param array  $inventory Full inventory array.
	 * @param string $file Optional file path to write to.
	 */
	private function output_json( $inventory, $file = null ) {
		$json = wp_json_encode( $inventory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

		if ( ! empty( $file ) ) {
			// Write to file
			$dir = dirname( $file );
			if ( ! is_dir( $dir ) ) {
				if ( ! mkdir( $dir, 0755, true ) ) {
					WP_CLI::error( "Could not create directory: {$dir}" );
				}
			}

			if ( ! @file_put_contents( $file, $json ) ) {
				WP_CLI::error( "Could not write to file: {$file}" );
			}

			WP_CLI::log( "Wrote JSON to: {$file}" );
		} else {
			// Output to STDOUT
			WP_CLI::line( $json );
		}
	}

	/**
	 * Output inventory as table (first 20 posts, key fields only).
	 *
	 * @param array $posts Array of post records.
	 */
	private function output_table( $posts ) {
		if ( empty( $posts ) ) {
			WP_CLI::log( 'No posts found.' );
			return;
		}

		// Prepare table rows (first 20 posts)
		$display_posts = array_slice( $posts, 0, 20 );
		$rows          = [];

		foreach ( $display_posts as $post ) {
			$rows[] = [
				'ID'              => $post['id'],
				'Title'           => substr( $post['title'], 0, 40 ),
				'Type'            => $post['post_type'],
				'Status'          => $post['analysis_status'],
				'Words'           => $post['word_count'] ?: '—',
				'Cluster'         => substr( $post['cluster'] ?: '—', 0, 20 ),
				'Modified'        => substr( $post['post_modified'], 0, 10 ),
			];
		}

		// Display table
		WP_CLI\Utils\format_items( 'table', $rows, [ 'ID', 'Title', 'Type', 'Status', 'Words', 'Cluster', 'Modified' ] );

		if ( count( $posts ) > 20 ) {
			WP_CLI::log( sprintf( '... and %d more posts (use --format=json for full output)', count( $posts ) - 20 ) );
		}
	}
}
