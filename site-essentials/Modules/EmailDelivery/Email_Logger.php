<?php
/**
 * Email log table and prune jobs for Site Essentials transactional email.
 *
 * @package    SiteEssentials
 * @subpackage Modules\EmailDelivery
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\EmailDelivery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the se_email_log custom table (no message bodies or keys).
 */
class Email_Logger {

	/**
	 * Action name for weekly prune WP-Cron.
	 */
	public const CRON_HOOK = 'scos_email_log_prune';

	/**
	 * Max rows to keep after each insert (spec).
	 */
	private const MAX_ROWS = 100;

	/**
	 * Days after which rows are pruned by cron.
	 */
	private const PRUNE_DAYS = 30;

	/**
	 * Cached table name for one request.
	 *
	 * @var string|null
	 */
	private static $table_name_cache;

	/**
	 * Fully qualified log table name including prefix.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		if ( null !== self::$table_name_cache ) {
			return self::$table_name_cache;
		}
		global $wpdb;
		self::$table_name_cache = $wpdb->prefix . 'se_email_log';
		return self::$table_name_cache;
	}

	/**
	 * Whether the log table exists.
	 *
	 * @return bool
	 */
	public static function table_exists(): bool {
		global $wpdb;
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix + literal suffix.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	/**
	 * Create the log table via dbDelta().
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::get_table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			sent_at datetime NOT NULL,
			to_address varchar(255) NOT NULL,
			subject varchar(500) NOT NULL,
			status varchar(10) NOT NULL,
			message_id varchar(255) DEFAULT NULL,
			error_text varchar(500) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY sent_at (sent_at)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Insert a log row and enforce max row count (100).
	 *
	 * @param string $to_address Recipient email (truncated if needed).
	 * @param string $subject    Subject line (truncated).
	 * @param string $status     sent|failed.
	 * @param string $message_id Optional provider message id.
	 * @param string $error_text Optional short error (no bodies).
	 * @return void
	 */
	public static function log( string $to_address, string $subject, string $status, string $message_id = '', string $error_text = '' ): void {
		if ( ! self::table_exists() ) {
			return;
		}

		global $wpdb;

		$table = self::get_table_name();

		$wpdb->insert(
			$table,
			[
				'sent_at'    => current_time( 'mysql' ),
				'to_address' => mb_substr( $to_address, 0, 255 ),
				'subject'    => mb_substr( $subject, 0, 500 ),
				'status'     => mb_substr( $status, 0, 10 ),
				'message_id' => $message_id !== '' ? mb_substr( $message_id, 0, 255 ) : null,
				'error_text' => $error_text !== '' ? mb_substr( $error_text, 0, 500 ) : null,
			],
			[
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			]
		);

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count > self::MAX_ROWS ) {
			$excess = $count - self::MAX_ROWS;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} ORDER BY id ASC LIMIT %d", $excess ) );
		}
	}

	/**
	 * Delete log rows older than 30 days (WP-Cron callback).
	 *
	 * @return void
	 */
	public static function prune_old_entries(): void {
		if ( ! self::table_exists() ) {
			return;
		}

		global $wpdb;

		$table    = self::get_table_name();
		$cutoff = wp_date( 'Y-m-d H:i:s', time() - ( self::PRUNE_DAYS * DAY_IN_SECONDS ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE sent_at < %s",
				$cutoff
			)
		);
	}

	/**
	 * Recent log rows for admin UI (newest first).
	 *
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_recent( int $limit = 10 ): array {
		if ( ! self::table_exists() || $limit < 1 ) {
			return [];
		}

		global $wpdb;

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, sent_at, to_address, subject, status, message_id, error_text FROM {$table} ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Schedule weekly prune if not scheduled.
	 *
	 * @return void
	 */
	public static function schedule_prune_cron(): void {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK );
	}

	/**
	 * Clear scheduled prune event.
	 *
	 * @return void
	 */
	public static function unschedule_prune_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}
}
