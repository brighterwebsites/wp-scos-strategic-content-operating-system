<?php
/**
 * WP-CLI Command: wp scos sitemap-noindex-cleanup
 *
 * Removes the 'xml' exclusion the meta box used to add for noindex posts, and
 * its scos_seo_sitemap_noindex_auto flag (#281). Dry run unless --apply.
 *
 * v1.0 | 2026-11-26
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\CLI
 */

namespace SiteEssentials\Modules\SeoMeta\CLI;

use SiteEssentials\Modules\SeoMeta\Sitemap_Noindex_Cleanup;
use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sitemap_Noindex_Cleanup_Command extends WP_CLI_Command {

	/**
	 * Report (or clear) auto-added XML sitemap exclusions on noindex posts.
	 *
	 * ## OPTIONS
	 *
	 * [--apply]
	 * : Write the changes. Without it, nothing is written.
	 *
	 * [--format=<format>]
	 * : summary (default), table or json.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp scos sitemap-noindex-cleanup
	 *     $ wp scos sitemap-noindex-cleanup --apply
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( $args, $assoc_args ) {
		$apply  = ! empty( $assoc_args['apply'] );
		$rows   = Sitemap_Noindex_Cleanup::run( $apply );
		$format = $assoc_args['format'] ?? 'summary';
		if ( 'summary' === $format ) {
			$counts = array_count_values( array_column( $rows, 'action' ) );
			WP_CLI::log( sprintf(
				'%d flagged posts: remove_xml %d, keep_xml %d, flag_only %d.%s',
				count( $rows ),
				$counts['remove_xml'] ?? 0,
				$counts['keep_xml'] ?? 0,
				$counts['flag_only'] ?? 0,
				$apply ? ' Applied.' : ' Dry run: nothing written.'
			) );
			return;
		}
		if ( $rows ) {
			WP_CLI\Utils\format_items( $format, $rows, [ 'post_id', 'type', 'action' ] );
		}
	}
}
