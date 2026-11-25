<?php
/**
 * WP-CLI Command: wp scos leads
 *
 * Read the lead table (Lead_Store). Same query as GET /scos/v1/leads.
 *
 * v1.0 | 2026-11-24
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic\CLI
 */

namespace SiteEssentials\Modules\Agentic\CLI;

use WP_CLI;
use WP_CLI_Command;
use SiteEssentials\Modules\Agentic\Lead_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leads_Command extends WP_CLI_Command {

	/**
	 * List leads, oldest change first.
	 *
	 * ## OPTIONS
	 *
	 * [--since=<cursor>]
	 * : next_cursor from an earlier call.
	 *
	 * [--limit=<n>]
	 * : 1-500. Default 100.
	 *
	 * [--status=<status>]
	 * : unverified, verified or expired.
	 *
	 * [--channel=<channel>]
	 * : agent, link or form.
	 *
	 * [--format=<format>]
	 * : table (default) or json (the feed exactly as the REST endpoint returns it).
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp scos leads list
	 *     $ wp scos leads list --channel=agent --format=json
	 *
	 * @when after_wp_load
	 */
	public function list( $args, $assoc_args ) {
		$page = Lead_Store::query( [
			'since'   => $assoc_args['since'] ?? '',
			'limit'   => $assoc_args['limit'] ?? 100,
			'status'  => $assoc_args['status'] ?? '',
			'channel' => $assoc_args['channel'] ?? '',
		] );
		if ( is_wp_error( $page ) ) {
			WP_CLI::error( $page->get_error_message() );
		}

		if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) {
			WP_CLI::line( wp_json_encode( $page, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			return;
		}

		$rows = array_map(
			static function ( $lead ) {
				return [
					'id'         => $lead['id'],
					'created_at' => $lead['created_at'],
					'status'     => $lead['status'],
					'channel'    => $lead['channel'],
					'name'       => $lead['contact']['name'],
					'email'      => $lead['contact']['email'],
					'phone'      => $lead['contact']['phone'],
				];
			},
			$page['leads']
		);
		WP_CLI\Utils\format_items( 'table', $rows, [ 'id', 'created_at', 'status', 'channel', 'name', 'email', 'phone' ] );
		WP_CLI::line( 'next_cursor: ' . (string) $page['next_cursor'] . ( $page['has_more'] ? ' (more)' : '' ) );
	}

	/**
	 * Write every lead as CSV to stdout.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp scos leads export > leads.csv
	 *
	 * @when after_wp_load
	 */
	public function export( $args, $assoc_args ) {
		$out = fopen( 'php://stdout', 'w' );
		Lead_Store::write_csv( $out );
		fclose( $out );
	}

	/**
	 * Delete leads older than the retention limit now (it also runs daily).
	 *
	 * The limit is the scos_agentic_lead_retention_days option, default 90.
	 *
	 * @when after_wp_load
	 */
	public function prune( $args, $assoc_args ) {
		$n = Lead_Store::prune();
		WP_CLI::success( sprintf( 'Deleted %d lead(s) older than %d days.', $n, Lead_Store::retention_days() ) );
	}
}
