<?php
/**
 * WP-CLI Command: wp scos legacy-meta
 *
 * Report and clean up meta keys listed in Core\Legacy_Meta. CLI only — there is
 * deliberately no admin interface for deleting data.
 *
 * v1.0 | 2026-10-20
 *
 * @package    SiteEssentials
 * @subpackage Core\CLI
 */

namespace SiteEssentials\Core\CLI;

use SiteEssentials\Core\Legacy_Meta;
use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Legacy_Meta_Command extends WP_CLI_Command {

	/**
	 * List the legacy meta registry.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Only show keys with this status.
	 * ---
	 * options:
	 *   - deprecated
	 *   - retired
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp scos legacy-meta list
	 *     wp scos legacy-meta list --status=retired --format=json
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$status = (string) ( $assoc_args['status'] ?? '' );
		\WP_CLI\Utils\format_items(
			$assoc_args['format'] ?? 'table',
			Legacy_Meta::entries( $status ),
			[ 'key', 'object', 'status', 'replacement', 'reason', 'issue' ]
		);
	}

	/**
	 * Count rows on this site for every registered key. Read-only.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Only audit keys with this status.
	 * ---
	 * options:
	 *   - deprecated
	 *   - retired
	 * ---
	 *
	 * [--unregistered]
	 * : Also list bw_ / _bw_ post meta keys that are not in the registry. For triage —
	 *   many are live custom post data and must stay.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp scos legacy-meta audit
	 *     wp scos legacy-meta audit --status=retired --unregistered
	 */
	public function audit( $args, $assoc_args ) {
		$status = (string) ( $assoc_args['status'] ?? '' );
		$format = $assoc_args['format'] ?? 'table';

		\WP_CLI\Utils\format_items(
			$format,
			Legacy_Meta::audit( $status ),
			[ 'key', 'object', 'status', 'rows', 'acf_refs', 'replacement', 'issue' ]
		);

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'unregistered', false ) ) {
			$unregistered = Legacy_Meta::unregistered();
			if ( 'table' === $format ) {
				WP_CLI::log( '' );
				WP_CLI::log( 'Unregistered bw_ keys (not touched by delete):' );
			}
			if ( empty( $unregistered ) ) {
				WP_CLI::log( 'None.' );
				return;
			}
			\WP_CLI\Utils\format_items( $format, $unregistered, [ 'key', 'rows' ] );
		}
	}

	/**
	 * Delete retired keys. Dry run unless --yes is passed.
	 *
	 * Writes a JSON backup of every affected row (mode 600) before deleting, and
	 * stops if the backup fails. Deprecated keys are never deleted.
	 *
	 * ## OPTIONS
	 *
	 * [--key=<key>]
	 * : Delete one retired key. Default: every retired key with rows.
	 *
	 * [--backup-dir=<dir>]
	 * : Where to write the backup.
	 * ---
	 * default: /root/wp-standardise-backups
	 * ---
	 *
	 * [--yes]
	 * : Actually delete. Without it, only reports what would be deleted.
	 *
	 * ## EXAMPLES
	 *
	 *     wp scos legacy-meta delete
	 *     wp scos legacy-meta delete --key=bw_intent --yes
	 *     wp scos legacy-meta delete --yes --backup-dir=/root/wp-standardise-backups
	 */
	public function delete( $args, $assoc_args ) {
		$only = (string) ( $assoc_args['key'] ?? '' );

		if ( '' !== $only ) {
			$def = Legacy_Meta::KEYS[ $only ] ?? null;
			if ( null === $def ) {
				WP_CLI::error( sprintf( '%s is not in the legacy meta registry.', $only ) );
			}
			if ( Legacy_Meta::STATUS_RETIRED !== $def[1] ) {
				WP_CLI::error( sprintf( '%s is %s, not retired. Only retired keys can be deleted.', $only, $def[1] ) );
			}
		}

		$targets = [];
		foreach ( Legacy_Meta::audit( Legacy_Meta::STATUS_RETIRED ) as $row ) {
			if ( '' !== $only && $row['key'] !== $only ) {
				continue;
			}
			if ( $row['rows'] + $row['acf_refs'] > 0 ) {
				$targets[] = $row;
			}
		}

		if ( empty( $targets ) ) {
			WP_CLI::success( 'Nothing to delete.' );
			return;
		}

		\WP_CLI\Utils\format_items( 'table', $targets, [ 'key', 'object', 'rows', 'acf_refs', 'replacement' ] );

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false ) ) {
			WP_CLI::log( 'Dry run. Re-run with --yes to back up and delete these rows.' );
			return;
		}

		$keys   = wp_list_pluck( $targets, 'key' );
		$backup = Legacy_Meta::backup( $keys, (string) ( $assoc_args['backup-dir'] ?? Legacy_Meta::BACKUP_DIR ) );
		if ( is_wp_error( $backup ) ) {
			WP_CLI::error( $backup->get_error_message() . ' Nothing deleted.' );
		}
		WP_CLI::log( sprintf( 'Backup written: %s', $backup ) );

		$total = 0;
		foreach ( $keys as $key ) {
			$deleted = Legacy_Meta::delete( $key );
			if ( is_wp_error( $deleted ) ) {
				WP_CLI::warning( $deleted->get_error_message() );
				continue;
			}
			$total += $deleted;
			WP_CLI::log( sprintf( 'Deleted %d rows: %s', $deleted, $key ) );
		}

		WP_CLI::success( sprintf( 'Deleted %d rows across %d keys.', $total, count( $keys ) ) );
	}
}
