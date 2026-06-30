<?php
/**
 * WP-CLI Command: wp scos suggest-topics
 *
 * Wraps the Suggest_Topics ability (scos/suggest-topics) so it can be called
 * directly from WP-CLI or an MCP agent without going through the REST API.
 *
 * Supports --apply to auto-assign the top-confidence topic term to the post.
 *
 * v1.0 | 2026-07-01
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

class Suggest_Topics_Command extends WP_CLI_Command {

	/**
	 * Suggest scos_topic taxonomy terms for a post using AI.
	 *
	 * Wraps the scos/suggest-topics ability. Requires the WordPress AI plugin
	 * to be active.
	 *
	 * ## OPTIONS
	 *
	 * --post-id=<id>
	 * : Post ID to analyse. Post content is fetched server-side.
	 *
	 * [--apply]
	 * : Assign the highest-confidence suggested topic to the post via
	 * wp_set_post_terms(). Requires --post-id.
	 *
	 * [--format=<format>]
	 * : Output format: json (default) or table.
	 *
	 * ## EXAMPLES
	 *
	 *     # Suggest topics for post 42
	 *     $ wp scos suggest-topics --post-id=42
	 *
	 *     # Suggest and auto-assign top topic
	 *     $ wp scos suggest-topics --post-id=42 --apply
	 *
	 *     # Display as table
	 *     $ wp scos suggest-topics --post-id=42 --format=table
	 *
	 * @subcommand suggest-topics
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associated arguments (flags).
	 */
	public function __invoke( $args, $assoc_args ) {
		if ( ! class_exists( 'WordPress\AI\Abstracts\Abstract_Ability' ) ) {
			WP_CLI::error( 'The WordPress AI plugin is not active. scos/suggest-topics requires it.' );
		}

		$post_id = isset( $assoc_args['post-id'] ) ? (int) $assoc_args['post-id'] : 0;
		$apply   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', false );
		$format  = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'json';

		if ( ! $post_id ) {
			WP_CLI::error( '--post-id is required.' );
		}

		if ( $apply && ! $post_id ) {
			WP_CLI::error( '--apply requires --post-id.' );
		}

		if ( ! in_array( $format, [ 'json', 'table' ], true ) ) {
			WP_CLI::error( "Invalid --format value: {$format}. Allowed: json, table." );
		}

		require_once __DIR__ . '/../Abilities/Suggest_Topics/Suggest_Topics.php';

		WP_CLI::log( "Running scos/suggest-topics for post {$post_id}..." );

		$ability = new \SiteEssentials\Modules\ContentArchitecture\Abilities\Suggest_Topics\Suggest_Topics();
		$result  = $ability->execute_callback( [ 'post_id' => $post_id ] );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		if ( 'table' === $format ) {
			$this->output_table( $result );
		} else {
			WP_CLI::line( wp_json_encode( $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
		}

		if ( $apply ) {
			$top = $result['suggestions'][0] ?? null;
			if ( $top && ! empty( $top['term_id'] ) ) {
				$term_result = wp_set_post_terms( $post_id, [ (int) $top['term_id'] ], 'scos_topic' );
				if ( is_wp_error( $term_result ) ) {
					WP_CLI::error( 'Failed to assign topic: ' . $term_result->get_error_message() );
				}
				WP_CLI::success( sprintf(
					'Applied topic "%s" (term_id %d) to post %d.',
					$top['name'] ?? '',
					$top['term_id'],
					$post_id
				) );
			} else {
				WP_CLI::warning( '--apply set but no topic suggestions were returned. Nothing saved.' );
			}
		} else {
			WP_CLI::success( 'Done.' );
		}
	}

	/**
	 * Output results as a table.
	 *
	 * @param array $result Ability output array.
	 */
	private function output_table( array $result ): void {
		$suggestions = $result['suggestions'] ?? [];

		if ( empty( $suggestions ) ) {
			WP_CLI::log( 'No topic suggestions returned.' );
			return;
		}

		$rows = [];
		foreach ( $suggestions as $i => $item ) {
			$rows[] = [
				'#'              => $i + 1,
				'Term ID'        => $item['term_id'] ?? '',
				'Topic'          => $item['name'] ?? '',
				'Confidence'     => number_format( (float) ( $item['confidence'] ?? 0 ), 2 ),
				'Topic Coverage' => $item['topic_coverage'] ?? '',
			];
		}

		\WP_CLI\Utils\format_items( 'table', $rows, [ '#', 'Term ID', 'Topic', 'Confidence', 'Topic Coverage' ] );
	}
}
