<?php
/**
 * WP-CLI Command: wp scos redirects
 *
 * Checks 301 and 410 rules with Redirect_Validator, the same check the
 * Redirections tab runs on save and the scos/validate-redirects ability
 * returns. Exits 1 when any rule has an error, so it can gate scripts.
 *
 * v1.0 | 2026-10-25
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\CLI
 */

namespace SiteEssentials\Modules\SeoMeta\CLI;

use WP_CLI;
use WP_CLI_Command;
use SiteEssentials\Modules\SeoMeta\Redirections;
use SiteEssentials\Modules\SeoMeta\Redirect_Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Redirects_Command extends WP_CLI_Command {

	/**
	 * Check 301 and 410 rules for loops, typos and rules that hide live pages.
	 *
	 * Checks the saved rules unless a file is given. A file replaces that list
	 * only, so proposed 301s are checked against the saved 410s and vice versa.
	 *
	 * ## OPTIONS
	 *
	 * [--rules-301-file=<path>]
	 * : Check this 301 rule text (one `/old => /new` per line) instead of the saved rules.
	 *
	 * [--rules-410-file=<path>]
	 * : Check this 410 path list instead of the saved one.
	 *
	 * [--format=<format>]
	 * : table (default) or json.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp scos redirects validate
	 *     $ wp scos redirects validate --rules-301-file=new-rules.txt --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associated arguments (flags).
	 */
	public function validate( $args, $assoc_args ) {
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		if ( ! in_array( $format, [ 'table', 'json' ], true ) ) {
			WP_CLI::error( "Invalid --format value: {$format}. Allowed: table, json." );
		}

		$raw_301 = $this->read_rules( $assoc_args, 'rules-301-file', Redirections::get_301_raw() );
		$raw_410 = $this->read_rules( $assoc_args, 'rules-410-file', Redirections::get_410_raw() );

		$report = Redirect_Validator::validate( $raw_301, $raw_410 );

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
		} else {
			$rows = [];
			foreach ( [ 'error' => $report['errors'], 'warning' => $report['warnings'] ] as $level => $issues ) {
				foreach ( $issues as $issue ) {
					$rows[] = [
						'level'   => $level,
						'list'    => $issue['list'],
						'line'    => $issue['line'],
						'rule'    => $issue['rule'],
						'code'    => $issue['code'],
						'message' => $issue['message'],
					];
				}
			}
			if ( $rows ) {
				WP_CLI\Utils\format_items( 'table', $rows, [ 'level', 'list', 'line', 'rule', 'code', 'message' ] );
			}
			WP_CLI::log( sprintf(
				'%d redirects, %d gone paths: %d errors, %d warnings.',
				$report['counts']['rules_301'],
				$report['counts']['paths_410'],
				$report['counts']['errors'],
				$report['counts']['warnings']
			) );
		}

		if ( ! $report['valid'] ) {
			WP_CLI::halt( 1 );
		}
	}

	/**
	 * Rule text from a file flag, or the fallback.
	 *
	 * @param array  $assoc_args Flags.
	 * @param string $flag       Flag name.
	 * @param string $fallback   Saved rules.
	 * @return string
	 */
	private function read_rules( array $assoc_args, string $flag, string $fallback ): string {
		if ( ! isset( $assoc_args[ $flag ] ) ) {
			return $fallback;
		}
		$path = $assoc_args[ $flag ];
		if ( ! is_readable( $path ) ) {
			WP_CLI::error( "Can't read --{$flag}: {$path}" );
		}
		return (string) file_get_contents( $path );
	}
}
