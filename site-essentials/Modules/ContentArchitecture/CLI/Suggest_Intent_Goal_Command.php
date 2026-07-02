<?php
/**
 * WP-CLI Command: wp scos suggest-intent-goal
 *
 * Wraps the CA_Suggest ability (scos/suggest-intent-goal) so it can be called
 * directly from WP-CLI or an MCP agent without going through the REST API.
 *
 * Supports --apply to auto-save the top suggestion to scos_ca_intent_goal.
 *
 * v1.0 | 2026-07-01
 * v1.1 | 2026-07-01 — Use wp_get_ability() + execute() instead of direct instantiation.
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

class Suggest_Intent_Goal_Command extends WP_CLI_Command {

	/**
	 * Suggest search intent goal phrasings for a post using AI.
	 *
	 * Wraps the scos/suggest-intent-goal ability. Requires the WordPress AI
	 * plugin to be active.
	 *
	 * ## OPTIONS
	 *
	 * --post-id=<id>
	 * : Post ID to analyse. Post content is fetched server-side.
	 *
	 * [--topic-term-id=<id>]
	 * : Optional. scos_topic term ID to scope intent goal suggestions to a
	 * specific topic perspective.
	 *
	 * [--existing-intent-goal=<text>]
	 * : Optional. Current intent goal text. Triggers reassessment mode.
	 *
	 * [--apply]
	 * : Save the top-ranked intent goal suggestion to scos_ca_intent_goal
	 * post meta. Requires --post-id.
	 *
	 * [--format=<format>]
	 * : Output format: json (default) or table.
	 *
	 * ## EXAMPLES
	 *
	 *     # Suggest intent goals for post 42
	 *     $ wp scos suggest-intent-goal --post-id=42
	 *
	 *     # Suggest scoped to a specific topic
	 *     $ wp scos suggest-intent-goal --post-id=42 --topic-term-id=7
	 *
	 *     # Suggest and auto-save top result
	 *     $ wp scos suggest-intent-goal --post-id=42 --apply
	 *
	 *     # Display as table
	 *     $ wp scos suggest-intent-goal --post-id=42 --format=table
	 *
	 * @subcommand suggest-intent-goal
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associated arguments (flags).
	 */
	public function __invoke( $args, $assoc_args ) {
		if ( ! class_exists( 'WordPress\AI\Abstracts\Abstract_Ability' ) ) {
			WP_CLI::error( 'The WordPress AI plugin is not active. scos/suggest-intent-goal requires it.' );
		}

		$post_id              = isset( $assoc_args['post-id'] ) ? (int) $assoc_args['post-id'] : 0;
		$topic_term_id        = isset( $assoc_args['topic-term-id'] ) ? (int) $assoc_args['topic-term-id'] : 0;
		$existing_intent_goal = isset( $assoc_args['existing-intent-goal'] ) ? $assoc_args['existing-intent-goal'] : null;
		$apply                = \WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', false );
		$format               = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'json';

		if ( ! $post_id ) {
			WP_CLI::error( '--post-id is required.' );
		}

		if ( $apply && ! $post_id ) {
			WP_CLI::error( '--apply requires --post-id.' );
		}

		if ( ! in_array( $format, [ 'json', 'table' ], true ) ) {
			WP_CLI::error( "Invalid --format value: {$format}. Allowed: json, table." );
		}

		WP_CLI::log( "Running scos/suggest-intent-goal for post {$post_id}..." );

		$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'scos/suggest-intent-goal' ) : null;
		if ( ! $ability ) {
			WP_CLI::error( 'scos/suggest-intent-goal is not registered. Ensure the WordPress AI plugin is active and abilities are loaded.' );
		}

		$input = [ 'post_id' => $post_id ];
		if ( $topic_term_id > 0 ) {
			$input['topic_term_id'] = $topic_term_id;
		}
		if ( $existing_intent_goal ) {
			$input['existing_intent_goal'] = $existing_intent_goal;
		}

		$result = $ability->execute( $input );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		if ( 'table' === $format ) {
			$this->output_table( $result );
		} else {
			WP_CLI::line( wp_json_encode( $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
		}

		if ( $apply ) {
			$top_goal = $result['intent_goals'][0]['goal'] ?? null;
			if ( $top_goal ) {
				update_post_meta( $post_id, 'scos_ca_intent_goal', sanitize_text_field( $top_goal ) );
				WP_CLI::success( "Applied intent goal to post {$post_id}: \"{$top_goal}\"" );
			} else {
				WP_CLI::warning( '--apply set but no intent goals were returned. Nothing saved.' );
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
		$intent_goals = $result['intent_goals'] ?? [];

		if ( isset( $result['matched_faq'] ) ) {
			$faq = $result['matched_faq'];
			WP_CLI::log( sprintf(
				'Matched FAQ (ID %d, quality: %s): %s',
				$faq['faq_id'] ?? 0,
				$faq['match_quality'] ?? '?',
				$faq['title'] ?? ''
			) );
		}

		if ( empty( $intent_goals ) ) {
			WP_CLI::log( 'No intent goal suggestions returned.' );
			return;
		}

		$rows = [];
		foreach ( $intent_goals as $i => $item ) {
			$rows[] = [
				'#'          => $i + 1,
				'Confidence' => number_format( (float) ( $item['confidence'] ?? 0 ), 2 ),
				'Intent Goal' => $item['goal'] ?? '',
			];
		}

		\WP_CLI\Utils\format_items( 'table', $rows, [ '#', 'Confidence', 'Intent Goal' ] );
	}
}
