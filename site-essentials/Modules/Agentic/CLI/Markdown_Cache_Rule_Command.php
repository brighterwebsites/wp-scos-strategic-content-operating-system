<?php
/**
 * WP-CLI Command: wp scos markdown-cache-rule
 *
 * Checks and syncs the .htaccess block that lets Accept: text/markdown
 * requests skip the LiteSpeed page cache. See Markdown_Cache_Rule.
 *
 * v1.0 | 2026-10-21
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic\CLI
 */

namespace SiteEssentials\Modules\Agentic\CLI;

use WP_CLI;
use WP_CLI_Command;
use SiteEssentials\Modules\Agentic\Markdown_Cache_Rule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Markdown_Cache_Rule_Command extends WP_CLI_Command {

	/**
	 * Report whether the .htaccess block matches the markdown setting.
	 *
	 * Exits 1 when it doesn't (setting on and block missing or out of date,
	 * or setting off and block still present), so a server check can call it.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table (default) or json.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp scos markdown-cache-rule status
	 *     $ wp scos markdown-cache-rule status --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associated arguments (flags).
	 */
	public function status( $args, $assoc_args ) {
		$status = Markdown_Cache_Rule::status();
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $status, JSON_UNESCAPED_SLASHES ) );
		} else {
			WP_CLI\Utils\format_items( 'table', [ $status ], array_keys( $status ) );
		}

		if ( ! $status['ok'] ) {
			WP_CLI::halt( 1 );
		}
	}

	/**
	 * Add, refresh or remove the block to match the markdown setting.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp scos markdown-cache-rule sync
	 *
	 * @when after_wp_load
	 */
	public function sync() {
		$result = Markdown_Cache_Rule::sync();
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		$status = Markdown_Cache_Rule::status();
		WP_CLI::success( $status['enabled'] ? 'Block present in ' . $status['path'] : 'Block removed (markdown setting is off).' );
	}
}
