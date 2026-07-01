<?php
/**
 * WP-CLI Command: wp scos suggest-seo-meta
 *
 * Wraps the Suggest_Seo_Meta ability (scos/suggest-seo-meta) so it can be
 * called directly from WP-CLI or an MCP agent without going through the REST
 * API.
 *
 * Supports --apply to auto-save the top suggestion for each field:
 *   scos_seo_breadcrumb_title, scos_seo_title, scos_seo_description.
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

class Suggest_Seo_Meta_Command extends WP_CLI_Command {

	/**
	 * Suggest breadcrumb label, meta title, and meta description for a post.
	 *
	 * Wraps the scos/suggest-seo-meta ability. All three fields are generated
	 * in a single AI call. Requires the WordPress AI plugin to be active.
	 *
	 * ## OPTIONS
	 *
	 * --post-id=<id>
	 * : Post ID to analyse. Post content is fetched server-side.
	 *
	 * [--apply]
	 * : Save the first suggestion for each field to post meta:
	 *   scos_seo_breadcrumb_title, scos_seo_title, scos_seo_description.
	 *   Requires --post-id.
	 *
	 * [--format=<format>]
	 * : Output format: json (default) or table.
	 *
	 * ## EXAMPLES
	 *
	 *     # Suggest SEO meta for post 42
	 *     $ wp scos suggest-seo-meta --post-id=42
	 *
	 *     # Suggest and auto-save top results
	 *     $ wp scos suggest-seo-meta --post-id=42 --apply
	 *
	 *     # Display as table
	 *     $ wp scos suggest-seo-meta --post-id=42 --format=table
	 *
	 * @subcommand suggest-seo-meta
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associated arguments (flags).
	 */
	public function __invoke( $args, $assoc_args ) {
		if ( ! class_exists( 'WordPress\AI\Abstracts\Abstract_Ability' ) ) {
			WP_CLI::error( 'The WordPress AI plugin is not active. scos/suggest-seo-meta requires it.' );
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

		WP_CLI::log( "Running scos/suggest-seo-meta for post {$post_id}..." );

		$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'scos/suggest-seo-meta' ) : null;
		if ( ! $ability ) {
			WP_CLI::error( 'scos/suggest-seo-meta is not registered. Ensure the WordPress AI plugin is active and abilities are loaded.' );
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
			$saved = [];

			$breadcrumb = $result['breadcrumb_options'][0] ?? null;
			if ( $breadcrumb ) {
				update_post_meta( $post_id, 'scos_seo_breadcrumb_title', sanitize_text_field( $breadcrumb ) );
				$saved[] = "breadcrumb: \"{$breadcrumb}\"";
			}

			$title = $result['title_options'][0] ?? null;
			if ( $title ) {
				update_post_meta( $post_id, 'scos_seo_title', sanitize_text_field( $title ) );
				$saved[] = "title: \"{$title}\"";
			}

			$description = $result['description_options'][0] ?? null;
			if ( $description ) {
				update_post_meta( $post_id, 'scos_seo_description', sanitize_text_field( $description ) );
				$saved[] = "description: \"{$description}\"";
			}

			if ( $saved ) {
				WP_CLI::success( "Applied to post {$post_id}: " . implode( ', ', $saved ) );
			} else {
				WP_CLI::warning( '--apply set but no suggestions were returned. Nothing saved.' );
			}
		} else {
			WP_CLI::success( 'Done.' );
		}
	}

	/**
	 * Output results as a table (top suggestion per field).
	 *
	 * @param array $result Ability output array.
	 */
	private function output_table( array $result ): void {
		$rows = [
			[
				'Field'       => 'Breadcrumb Title',
				'Meta Key'    => 'scos_seo_breadcrumb_title',
				'#1 Option'   => $result['breadcrumb_options'][0] ?? '—',
				'#2 Option'   => $result['breadcrumb_options'][1] ?? '—',
				'#3 Option'   => $result['breadcrumb_options'][2] ?? '—',
			],
			[
				'Field'       => 'Meta Title',
				'Meta Key'    => 'scos_seo_title',
				'#1 Option'   => $result['title_options'][0] ?? '—',
				'#2 Option'   => $result['title_options'][1] ?? '—',
				'#3 Option'   => $result['title_options'][2] ?? '—',
			],
			[
				'Field'       => 'Meta Description',
				'Meta Key'    => 'scos_seo_description',
				'#1 Option'   => isset( $result['description_options'][0] ) ? substr( $result['description_options'][0], 0, 60 ) . '…' : '—',
				'#2 Option'   => isset( $result['description_options'][1] ) ? substr( $result['description_options'][1], 0, 60 ) . '…' : '—',
				'#3 Option'   => isset( $result['description_options'][2] ) ? substr( $result['description_options'][2], 0, 60 ) . '…' : '—',
			],
		];

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'Field', 'Meta Key', '#1 Option', '#2 Option', '#3 Option' ] );
	}
}
