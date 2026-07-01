<?php
/**
 * WP-CLI Command: wp scos suggest-tldr
 *
 * Wraps the Suggest_Tldr ability (scos/suggest-tldr) so it can be called
 * directly from WP-CLI or an MCP agent without going through the REST API.
 *
 * Supports --apply to auto-save the top TLDR suggestion to scos_seo_tldr.
 *
 * v1.0 | 2026-07-01
 * v1.1 | 2026-07-01 — Use wp_get_ability() + execute() instead of direct instantiation.
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

class Suggest_Tldr_Command extends WP_CLI_Command {

	/**
	 * Suggest TLDR article summary options for a post using AI.
	 *
	 * Wraps the scos/suggest-tldr ability. When the post has a linked Search
	 * Intent Goal, the TLDR is written to directly answer that question.
	 * Requires the WordPress AI plugin to be active.
	 *
	 * ## OPTIONS
	 *
	 * --post-id=<id>
	 * : Post ID to analyse. Post content and intent goal are fetched
	 * server-side.
	 *
	 * [--apply]
	 * : Save the first TLDR option to scos_seo_tldr post meta.
	 * Requires --post-id.
	 *
	 * [--format=<format>]
	 * : Output format: json (default) or table.
	 *
	 * ## EXAMPLES
	 *
	 *     # Suggest TLDR options for post 42
	 *     $ wp scos suggest-tldr --post-id=42
	 *
	 *     # Suggest and auto-save top result
	 *     $ wp scos suggest-tldr --post-id=42 --apply
	 *
	 *     # Display as table
	 *     $ wp scos suggest-tldr --post-id=42 --format=table
	 *
	 * @subcommand suggest-tldr
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associated arguments (flags).
	 */
	public function __invoke( $args, $assoc_args ) {
		if ( ! class_exists( 'WordPress\AI\Abstracts\Abstract_Ability' ) ) {
			WP_CLI::error( 'The WordPress AI plugin is not active. scos/suggest-tldr requires it.' );
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

		WP_CLI::log( "Running scos/suggest-tldr for post {$post_id}..." );

		$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'scos/suggest-tldr' ) : null;
		if ( ! $ability ) {
			WP_CLI::error( 'scos/suggest-tldr is not registered. Ensure the WordPress AI plugin is active and abilities are loaded.' );
		}

		$result  = $ability->execute( [ 'post_id' => $post_id ] );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		if ( 'table' === $format ) {
			$this->output_table( $result );
		} else {
			WP_CLI::line( wp_json_encode( $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
		}

		if ( $apply ) {
			$top_tldr = $result['tldr_options'][0] ?? null;
			if ( $top_tldr ) {
				update_post_meta( $post_id, 'scos_seo_tldr', sanitize_text_field( $top_tldr ) );
				WP_CLI::success( "Applied TLDR to post {$post_id}: \"{$top_tldr}\"" );
			} else {
				WP_CLI::warning( '--apply set but no TLDR options were returned. Nothing saved.' );
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
		$tldr_options = $result['tldr_options'] ?? [];
		$intent_goal  = $result['intent_goal_used'] ?? null;

		if ( $intent_goal ) {
			WP_CLI::log( "Intent goal used: \"{$intent_goal}\"" );
		}

		if ( empty( $tldr_options ) ) {
			WP_CLI::log( 'No TLDR options returned.' );
			return;
		}

		$rows = [];
		foreach ( $tldr_options as $i => $tldr ) {
			$rows[] = [
				'#'    => $i + 1,
				'TLDR' => $tldr,
			];
		}

		\WP_CLI\Utils\format_items( 'table', $rows, [ '#', 'TLDR' ] );
	}
}
