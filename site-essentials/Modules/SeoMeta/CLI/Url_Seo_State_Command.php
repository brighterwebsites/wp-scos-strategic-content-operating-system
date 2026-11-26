<?php
/**
 * WP-CLI Command: wp scos url-seo-state
 *
 * What URLs or posts actually serve for SEO — the same report the
 * scos/get-url-seo-state ability returns. No AI involved.
 *
 * v1.0 | 2026-11-26
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

class Url_Seo_State_Command extends WP_CLI_Command {

	/**
	 * Show robots, canonical, sitemap and redirect state for URLs or post IDs.
	 *
	 * ## OPTIONS
	 *
	 * <target>...
	 * : URLs, paths starting with "/", or post IDs.
	 *
	 * [--format=<format>]
	 * : json (default) for the full report, or table for one line per item.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp scos url-seo-state 42 /blog/ /category/news/
	 *     $ wp scos url-seo-state https://example.com/services/ --format=table
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associated arguments (flags).
	 */
	public function __invoke( $args, $assoc_args ) {
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'json';
		if ( ! in_array( $format, [ 'json', 'table' ], true ) ) {
			WP_CLI::error( "Invalid --format value: {$format}. Allowed: json, table." );
		}

		$urls = [];
		$ids  = [];
		foreach ( $args as $arg ) {
			if ( ctype_digit( (string) $arg ) ) {
				$ids[] = (int) $arg;
			} else {
				$urls[] = (string) $arg;
			}
		}

		$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'scos/get-url-seo-state' ) : null;
		if ( ! $ability ) {
			WP_CLI::error( 'scos/get-url-seo-state is not registered. It needs the WordPress Abilities API and the SEO module.' );
		}

		// Read-only report: without --user, run as the first administrator for the permission check.
		if ( ! get_current_user_id() ) {
			$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID' ] );
			if ( empty( $admins ) ) {
				WP_CLI::error( 'No administrator account found. Pass --user=<id|login|email>.' );
			}
			wp_set_current_user( absint( $admins[0] ) );
		}

		$result = $ability->execute( [
			'urls'             => $urls,
			'post_ids'         => $ids,
			'include_settings' => 'json' === $format,
		] );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
			return;
		}

		$rows = [];
		foreach ( $result['items'] as $item ) {
			if ( isset( $item['error'] ) ) {
				$rows[] = [ 'input' => $item['input'], 'target' => 'error', 'robots' => $item['error']['message'], 'canonical' => '', 'xml' => '', 'html' => '', 'redirect' => '', 'warnings' => '' ];
				continue;
			}
			$t      = $item['target'];
			$rows[] = [
				'input'     => $item['input'],
				'target'    => $t['type'] . ( isset( $t['post_id'] ) ? ' #' . $t['post_id'] : '' ) . ( isset( $t['slug'] ) ? ' ' . $t['slug'] : '' ),
				'robots'    => ( $item['robots']['directives'] ? implode( ',', $item['robots']['directives'] ) : 'index,follow' ) . ' (' . $item['robots']['source'] . ')',
				'canonical' => $item['canonical']['url'] . ' (' . $item['canonical']['source'] . ')',
				'xml'       => ( $item['in_xml_sitemap']['included'] ? 'yes' : 'no' ) . ' (' . $item['in_xml_sitemap']['reason'] . ')',
				'html'      => ( $item['in_html_sitemap']['included'] ? 'yes' : 'no' ) . ' (' . $item['in_html_sitemap']['reason'] . ')',
				'redirect'  => $item['redirects'] ? $item['redirects']['status'] . ' ' . $item['redirects']['to'] : '',
				'warnings'  => count( $item['warnings'] ),
			];
		}
		WP_CLI\Utils\format_items( 'table', $rows, [ 'input', 'target', 'robots', 'canonical', 'xml', 'html', 'redirect', 'warnings' ] );
	}
}
