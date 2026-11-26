<?php
/**
 * WP-CLI Command: wp scos archive-page-meta
 *
 * Copies the posts page's and shop page's own meta title / description into
 * SEO › Archive Settings where the archive's value is empty (#282).
 * Dry run unless --apply.
 *
 * v1.0 | 2026-11-26
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\CLI
 */

namespace SiteEssentials\Modules\SeoMeta\CLI;

use SiteEssentials\Modules\SeoMeta\Archive_Page_Meta_Migration;
use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Archive_Page_Meta_Command extends WP_CLI_Command {

	/**
	 * Report (or copy) posts page / shop page meta into Archive Settings.
	 *
	 * ## OPTIONS
	 *
	 * [--apply]
	 * : Write the changes. Without it, nothing is written.
	 *
	 * [--format=<format>]
	 * : table (default) or json.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp scos archive-page-meta
	 *     $ wp scos archive-page-meta --apply
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( $args, $assoc_args ) {
		$apply = ! empty( $assoc_args['apply'] );
		$rows  = Archive_Page_Meta_Migration::run( $apply );
		if ( empty( $rows ) ) {
			WP_CLI::success( 'Nothing to copy: no posts page or shop page with its own title or description.' );
			return;
		}
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, [ 'archive', 'page_id', 'field', 'action', 'value' ] );
		if ( ! $apply ) {
			WP_CLI::log( 'Dry run: nothing written. Re-run with --apply to copy the would_copy rows.' );
		}
	}
}
