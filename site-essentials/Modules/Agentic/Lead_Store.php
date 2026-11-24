<?php
/**
 * Agentic — the lead table: one record per lead, for CRMs to collect
 *
 * v1.0 | 2026-11-24
 *
 * WHAT THIS IS FOR
 * ----------------
 * A CRM is often mission-critical, so the site never pushes to one. Every
 * lead gets a row here, and a CRM fetches them when it chooses (#269):
 *
 *   GET /wp-json/scos/v1/leads?since=<cursor>   Leads_API
 *   scos/list-leads                             ability (MCP, REST)
 *   wp scos leads list|export|prune             WP-CLI
 *   CSV                                         Site Essentials › Agentic
 *
 * All of them call query() here.
 *
 * WHERE ROWS COME FROM (channel)
 * ------------------------------
 *   agent  An agent sent it through the API or MCP; the person confirmed by
 *          email (Enquiry_Send).
 *   link   The person sent the form after opening an agent's pre-filled link.
 *   form   The person typed it into the form.
 * link and form are written by the Breakdance action "SCOS: Save lead"
 * (Lead_Form_Action), on the forms that have it ticked.
 *
 * status only ever describes verification: unverified, verified, expired.
 * Every write sets updated_at, so a CRM that pulls with ?since= sees a lead
 * again when its status changes.
 *
 * PERSONAL DATA
 * -------------
 * Rows are deleted after scos_agentic_lead_retention_days (default 90; no
 * settings field yet: wp option update). WordPress's personal-data export
 * and erase tools find rows by email address.
 *
 * Table: {prefix}se_leads. Schema version: site_essentials_leads_db_version.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Lead_Store {

	const DB_VERSION        = '1';
	const OPTION_DB_VERSION = 'site_essentials_leads_db_version';
	const OPTION_RETENTION  = 'scos_agentic_lead_retention_days';
	const RETENTION_DEFAULT = 90;
	const PRUNE_HOOK        = 'se_leads_prune';
	const CAPABILITY        = 'scos_read_leads';
	const ROLE              = 'scos_lead_reader';
	const FEED_VERSION      = 1;

	/** Lead_Form_Action::SLUG, repeated so reading it never loads a class that needs Breakdance. */
	const BREAKDANCE_ACTION = 'scos_save_lead';

	/** Hidden field Enquiry_Prefill adds to a form opened from an agent's link. */
	const LINK_MARKER = 'scos_lead_channel';

	const STATUSES = [ 'unverified', 'verified', 'expired' ];
	const CHANNELS = [ 'agent', 'link', 'form' ];

	/** Field types never stored: they hold no lead data, or data we must not keep. */
	const SKIP_TYPES = [ 'html', 'step', 'file', 'password', 'recaptcha', 'hpinput', 'submit', 'button' ];

	// ── Boot ─────────────────────────────────────────────────────────────────

	public static function init(): void {
		if ( self::DB_VERSION !== get_option( self::OPTION_DB_VERSION ) ) {
			self::install();
		}

		add_action( self::PRUNE_HOOK, [ __CLASS__, 'prune' ] );
		if ( ! wp_next_scheduled( self::PRUNE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PRUNE_HOOK );
		}

		add_filter( 'wp_privacy_personal_data_exporters', [ __CLASS__, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ __CLASS__, 'register_eraser' ] );
		add_action( 'init', [ __CLASS__, 'register_breakdance_action' ] );
	}

	/**
	 * Offer "SCOS: Save lead" in Breakdance's form actions. Only loaded when
	 * Breakdance is: Lead_Form_Action extends its Action class.
	 */
	public static function register_breakdance_action(): void {
		if ( ! class_exists( '\Breakdance\Forms\Actions\Action' ) || ! function_exists( '\Breakdance\Forms\Actions\registerAction' ) ) {
			return;
		}
		\Breakdance\Forms\Actions\registerAction( new Lead_Form_Action() );
	}

	/**
	 * Whether the enquiry form (Enquiry_Form) has "SCOS: Save lead" ticked.
	 *
	 * @param bool $fetch False reads only the cached schema (safe while rendering a page).
	 * @return bool|null null when there's no Breakdance enquiry form to check.
	 */
	public static function enquiry_form_saves_leads( bool $fetch = true ): ?bool {
		if ( ! Enquiry_Form::is_enabled() ) {
			return null;
		}
		$target = ( Enquiry_Form::get_schema( $fetch ) ?? [] )['target'] ?? null;
		if ( ! $target || 'breakdance' !== ( $target['builder'] ?? '' ) ) {
			return null;
		}
		return self::form_saves_leads( (int) $target['post_id'], (int) $target['form_id'] );
	}

	/**
	 * Whether a Breakdance form has "SCOS: Save lead" ticked.
	 *
	 * @return bool|null null when the form can't be read.
	 */
	public static function form_saves_leads( int $post_id, int $form_id ): ?bool {
		if ( ! function_exists( '\Breakdance\Forms\getFormSettings' ) ) {
			return null;
		}
		try {
			$settings = \Breakdance\Forms\getFormSettings( $post_id, $form_id );
		} catch ( \Throwable $e ) {
			return null;
		}
		if ( ! $settings ) {
			return null;
		}
		return in_array( self::BREAKDANCE_ACTION, (array) ( $settings['actions']['actions'] ?? [] ), true );
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'se_leads';
	}

	/** Create or upgrade the table, and the Lead reader role. */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		// dbDelta's format: two spaces after PRIMARY KEY, one column per line.
		dbDelta( "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ref varchar(32) NOT NULL,
  created_at datetime(6) NOT NULL,
  updated_at datetime(6) NOT NULL,
  verified_at datetime(6) DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'unverified',
  channel varchar(20) NOT NULL DEFAULT '',
  source varchar(40) NOT NULL DEFAULT '',
  agent varchar(60) NOT NULL DEFAULT '',
  post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  form_id bigint(20) unsigned NOT NULL DEFAULT 0,
  name varchar(200) NOT NULL DEFAULT '',
  email varchar(200) NOT NULL DEFAULT '',
  phone varchar(40) NOT NULL DEFAULT '',
  message text NOT NULL,
  fields longtext NOT NULL,
  context longtext NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY ref (ref),
  KEY updated (updated_at,id),
  KEY created_at (created_at),
  KEY email (email(100))
) {$charset};" );

		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, __( 'Lead reader', 'site-essentials' ), [ 'read' => true, self::CAPABILITY => true ] );
		}
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( self::CAPABILITY ) ) {
			$admin->add_cap( self::CAPABILITY );
		}

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
	}

	public static function retention_days(): int {
		$days = absint( get_option( self::OPTION_RETENTION, self::RETENTION_DEFAULT ) );
		return $days > 0 ? $days : self::RETENTION_DEFAULT;
	}

	// ── Write ────────────────────────────────────────────────────────────────

	/**
	 * Record a lead.
	 *
	 * @param array $lead {
	 *     @type string $channel agent | link | form.
	 *     @type string $status  unverified | verified. Default verified.
	 *     @type string $source  Detail within the channel (rest, mcp, breakdance).
	 *     @type string $agent   The assistant's name.
	 *     @type int    $post_id The post the form is on.
	 *     @type int    $form_id The form's id on that post.
	 *     @type array  $fields  List of [ name, label, type, value ]; value is a string or string[].
	 *     @type array  $context Anything else (quote answers, service). Stored as JSON.
	 * }
	 * @return string|\WP_Error The lead's ref.
	 */
	public static function insert( array $lead ) {
		global $wpdb;

		$channel = (string) ( $lead['channel'] ?? '' );
		$status  = (string) ( $lead['status'] ?? 'verified' );
		if ( ! in_array( $channel, self::CHANNELS, true ) || ! in_array( $status, self::STATUSES, true ) ) {
			return new \WP_Error( 'scos_lead_invalid', 'Unknown lead channel or status.' );
		}

		$fields  = self::clean_fields( (array) ( $lead['fields'] ?? [] ) );
		$contact = self::contact( $fields );
		if ( '' === $contact['email'] && '' === $contact['phone'] ) {
			return new \WP_Error( 'scos_lead_no_contact', 'A lead needs an email address or a phone number.' );
		}

		$now = self::now();
		$ref = bin2hex( random_bytes( 12 ) );
		$ok  = $wpdb->insert(
			self::table(),
			[
				'ref'         => $ref,
				'created_at'  => $now,
				'updated_at'  => $now,
				'verified_at' => 'verified' === $status ? $now : null,
				'status'      => $status,
				'channel'     => $channel,
				'source'      => mb_substr( sanitize_key( (string) ( $lead['source'] ?? '' ) ), 0, 40 ),
				'agent'       => mb_substr( sanitize_text_field( (string) ( $lead['agent'] ?? '' ) ), 0, 60 ),
				'post_id'     => absint( $lead['post_id'] ?? 0 ),
				'form_id'     => absint( $lead['form_id'] ?? 0 ),
				'name'        => mb_substr( $contact['name'], 0, 200 ),
				'email'       => mb_substr( $contact['email'], 0, 200 ),
				'phone'       => mb_substr( $contact['phone'], 0, 40 ),
				'message'     => $contact['message'],
				'fields'      => (string) wp_json_encode( $fields ),
				'context'     => (string) wp_json_encode( (object) ( $lead['context'] ?? [] ) ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( ! $ok ) {
			error_log( 'SCOS leads: insert failed: ' . $wpdb->last_error );
			return new \WP_Error( 'scos_lead_insert_failed', 'The lead could not be saved.' );
		}

		/**
		 * Action: scos_lead_recorded — a lead row was written. For on-site use
		 * only (a site plugin's own log). Never use it to push to a CRM.
		 *
		 * @param string $ref  The lead's ref.
		 * @param array  $lead What was passed to insert().
		 */
		do_action( 'scos_lead_recorded', $ref, $lead );

		return $ref;
	}

	/** Change a lead's status. Bumps updated_at, so pulls see it again. */
	public static function set_status( string $ref, string $status ): bool {
		global $wpdb;
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		$now  = self::now();
		$data = [ 'status' => $status, 'updated_at' => $now ];
		if ( 'verified' === $status ) {
			$data['verified_at'] = $now;
		}
		return (bool) $wpdb->update( self::table(), $data, [ 'ref' => $ref ] );
	}

	/** Delete rows older than the retention limit. Runs daily. */
	public static function prune(): int {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::retention_days() * DAY_IN_SECONDS );
		$table  = self::table();
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// ── Read ─────────────────────────────────────────────────────────────────

	/**
	 * Leads changed after a cursor, oldest change first.
	 *
	 * @param array $args { since: cursor, limit: 1-500 (100), status, channel }
	 * @return array|\WP_Error { version, leads[], next_cursor, has_more }
	 */
	public static function query( array $args = [] ) {
		global $wpdb;

		$limit = max( 1, min( 500, absint( $args['limit'] ?? 100 ) ?: 100 ) );
		$where = [ '1=1' ];
		$vals  = [];

		$since = (string) ( $args['since'] ?? '' );
		if ( '' !== $since ) {
			$point = self::decode_cursor( $since );
			if ( ! $point ) {
				return new \WP_Error( 'scos_leads_bad_cursor', 'The since cursor is not valid. Use next_cursor from an earlier reply, or leave it out to start from the oldest lead.', [ 'status' => 400 ] );
			}
			$where[] = '(updated_at > %s OR (updated_at = %s AND id > %d))';
			array_push( $vals, $point[0], $point[0], $point[1] );
		}

		foreach ( [ 'status' => self::STATUSES, 'channel' => self::CHANNELS ] as $col => $allowed ) {
			$value = (string) ( $args[ $col ] ?? '' );
			if ( '' === $value ) {
				continue;
			}
			if ( ! in_array( $value, $allowed, true ) ) {
				/* translators: 1: parameter name, 2: allowed values */
				return new \WP_Error( 'scos_leads_bad_filter', sprintf( '%1$s must be one of: %2$s.', $col, implode( ', ', $allowed ) ), [ 'status' => 400 ] );
			}
			$where[] = "{$col} = %s";
			$vals[]  = $value;
		}

		$table = self::table();
		$sql   = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY updated_at ASC, id ASC LIMIT %d';
		$vals[] = $limit + 1;

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = is_array( $rows ) ? $rows : [];

		$has_more = count( $rows ) > $limit;
		$rows     = array_slice( $rows, 0, $limit );
		$last     = end( $rows );

		return [
			'version'     => self::FEED_VERSION,
			'leads'       => array_map( [ __CLASS__, 'format' ], $rows ),
			'next_cursor' => $last ? self::encode_cursor( $last['updated_at'], (int) $last['id'] ) : ( '' !== $since ? $since : null ),
			'has_more'    => $has_more,
		];
	}

	public static function get( string $ref ): ?array {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE ref = %s", $ref ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? self::format( $row ) : null;
	}

	public static function count(): int {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** A row as the feed returns it. The internal id is never exposed. */
	public static function format( array $row ): array {
		$fields = json_decode( (string) $row['fields'], true );
		$fields = is_array( $fields ) ? $fields : [];

		return [
			'id'          => $row['ref'],
			'status'      => $row['status'],
			'channel'     => $row['channel'],
			'source'      => $row['source'],
			'agent'       => $row['agent'],
			'created_at'  => self::iso( $row['created_at'] ),
			'updated_at'  => self::iso( $row['updated_at'] ),
			'verified_at' => self::iso( $row['verified_at'] ),
			'contact'     => [
				'name'    => $row['name'],
				'email'   => $row['email'],
				'phone'   => $row['phone'],
				'message' => $row['message'],
			],
			'fields'      => (object) array_column( $fields, 'value', 'name' ),
			'labels'      => (object) array_column( $fields, 'label', 'name' ),
			'form'        => [
				'post_id' => (int) $row['post_id'],
				'form_id' => (int) $row['form_id'],
				'page'    => $row['post_id'] ? (string) get_permalink( (int) $row['post_id'] ) : '',
			],
			'context'     => json_decode( (string) $row['context'] ) ?: new \stdClass(),
		];
	}

	// ── CSV ──────────────────────────────────────────────────────────────────

	const CSV_COLUMNS = [ 'id', 'status', 'channel', 'source', 'agent', 'created_at', 'updated_at', 'verified_at', 'name', 'email', 'phone', 'message', 'page', 'fields' ];

	/**
	 * Write every lead as CSV to a stream.
	 *
	 * @param resource $out
	 */
	public static function write_csv( $out ): int {
		fputcsv( $out, self::CSV_COLUMNS );
		$n     = 0;
		$since = '';
		do {
			$page = self::query( [ 'since' => $since, 'limit' => 500 ] );
			if ( is_wp_error( $page ) ) {
				break;
			}
			foreach ( $page['leads'] as $lead ) {
				$row = [
					$lead['id'], $lead['status'], $lead['channel'], $lead['source'], $lead['agent'],
					$lead['created_at'], $lead['updated_at'], $lead['verified_at'],
					$lead['contact']['name'], $lead['contact']['email'], $lead['contact']['phone'], $lead['contact']['message'],
					$lead['form']['page'], (string) wp_json_encode( $lead['fields'] ),
				];
				fputcsv( $out, array_map( [ __CLASS__, 'csv_safe' ], $row ) );
				++$n;
			}
			$since = (string) $page['next_cursor'];
		} while ( $page['has_more'] );
		return $n;
	}

	/** Stop a spreadsheet treating a value as a formula. */
	private static function csv_safe( $value ): string {
		$value = (string) $value;
		return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
	}

	// ── Privacy tools ────────────────────────────────────────────────────────

	public static function register_exporter( array $exporters ): array {
		$exporters['scos-leads'] = [
			'exporter_friendly_name' => __( 'Enquiries (SCOS leads)', 'site-essentials' ),
			'callback'               => [ __CLASS__, 'export_personal_data' ],
		];
		return $exporters;
	}

	public static function register_eraser( array $erasers ): array {
		$erasers['scos-leads'] = [
			'eraser_friendly_name' => __( 'Enquiries (SCOS leads)', 'site-essentials' ),
			'callback'             => [ __CLASS__, 'erase_personal_data' ],
		];
		return $erasers;
	}

	public static function export_personal_data( string $email, int $page = 1 ): array {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s ORDER BY id", strtolower( trim( $email ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$items = [];
		foreach ( (array) $rows as $row ) {
			$lead = self::format( $row );
			$data = [
				[ 'name' => __( 'Received', 'site-essentials' ), 'value' => $lead['created_at'] ],
				[ 'name' => __( 'Status', 'site-essentials' ), 'value' => $lead['status'] ],
				[ 'name' => __( 'Sent by', 'site-essentials' ), 'value' => $lead['agent'] ?: $lead['channel'] ],
			];
			foreach ( (array) $lead['fields'] as $key => $value ) {
				$data[] = [
					'name'  => (string) ( ( (array) $lead['labels'] )[ $key ] ?? $key ),
					'value' => is_array( $value ) ? implode( ', ', $value ) : (string) $value,
				];
			}
			$items[] = [
				'group_id'    => 'scos-leads',
				'group_label' => __( 'Enquiries', 'site-essentials' ),
				'item_id'     => 'scos-lead-' . $lead['id'],
				'data'        => $data,
			];
		}
		return [ 'data' => $items, 'done' => true ];
	}

	public static function erase_personal_data( string $email, int $page = 1 ): array {
		global $wpdb;
		$removed = (int) $wpdb->delete( self::table(), [ 'email' => strtolower( trim( $email ) ) ], [ '%s' ] );
		return [ 'items_removed' => $removed > 0, 'items_retained' => false, 'messages' => [], 'done' => true ];
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * @param array $fields List of [ name, label, type, value ].
	 * @return array Same shape, values trimmed to strings or string lists, empties and skipped types dropped.
	 */
	private static function clean_fields( array $fields ): array {
		$out = [];
		foreach ( $fields as $field ) {
			$name = sanitize_key( (string) ( $field['name'] ?? '' ) );
			$type = sanitize_key( (string) ( $field['type'] ?? 'text' ) );
			if ( '' === $name || in_array( $type, self::SKIP_TYPES, true ) ) {
				continue;
			}
			$value = $field['value'] ?? '';
			$value = is_array( $value )
				? array_values( array_filter( array_map( [ __CLASS__, 'clean_value' ], $value ), 'strlen' ) )
				: self::clean_value( $value );
			if ( '' === $value || [] === $value ) {
				continue;
			}
			$out[] = [
				'name'  => $name,
				'label' => mb_substr( sanitize_text_field( (string) ( $field['label'] ?? '' ) ), 0, 200 ),
				'type'  => $type,
				'value' => is_array( $value ) && 1 === count( $value ) && 'checkbox' !== $type ? $value[0] : $value,
			];
		}
		return $out;
	}

	private static function clean_value( $value ): string {
		return is_scalar( $value ) ? mb_substr( trim( sanitize_textarea_field( (string) $value ) ), 0, 5000 ) : '';
	}

	/**
	 * The standard contact block, from the fields' types, so a CRM maps it once for every site.
	 *
	 * @return array{name: string, email: string, phone: string, message: string}
	 */
	private static function contact( array $fields ): array {
		$contact = [ 'name' => '', 'email' => '', 'phone' => '', 'message' => '' ];
		$names   = [];

		foreach ( $fields as $field ) {
			$value = is_array( $field['value'] ) ? implode( ', ', $field['value'] ) : $field['value'];
			$words = $field['name'] . ' ' . $field['label'];

			if ( 'email' === $field['type'] || ( '' === $contact['email'] && is_email( $value ) ) ) {
				if ( '' === $contact['email'] && is_email( $value ) ) {
					$contact['email'] = strtolower( $value );
				}
			} elseif ( 'tel' === $field['type'] || preg_match( '/phone|mobile/i', $words ) ) {
				if ( '' === $contact['phone'] ) {
					$contact['phone'] = $value;
				}
			} elseif ( 'textarea' === $field['type'] ) {
				if ( '' === $contact['message'] ) {
					$contact['message'] = $value;
				}
			} elseif ( 'text' === $field['type'] && preg_match( '/name/i', $words ) && ! preg_match( '/company|business|organi[sz]ation|user|file/i', $words ) ) {
				$names[] = $value; // First and last name fields join up.
			}
		}

		$contact['name'] = implode( ' ', $names );
		return $contact;
	}

	private static function now(): string {
		return gmdate( 'Y-m-d H:i:s' ) . sprintf( '.%06d', (int) ( fmod( microtime( true ), 1 ) * 1000000 ) );
	}

	private static function iso( ?string $datetime ): ?string {
		if ( ! $datetime ) {
			return null;
		}
		$time = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s.u', $datetime, new \DateTimeZone( 'UTC' ) )
			?: \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $datetime, new \DateTimeZone( 'UTC' ) );
		return $time ? $time->format( 'Y-m-d\TH:i:s.u\Z' ) : null;
	}

	private static function encode_cursor( string $updated_at, int $id ): string {
		return rtrim( strtr( base64_encode( $updated_at . '|' . $id ), '+/', '-_' ), '=' );
	}

	/** @return array{0: string, 1: int}|null */
	private static function decode_cursor( string $cursor ): ?array {
		$raw = base64_decode( strtr( $cursor, '-_', '+/' ), true );
		if ( ! $raw || ! preg_match( '/^(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d(?:\.\d{1,6})?)\|(\d+)$/', $raw, $m ) ) {
			return null;
		}
		return [ $m[1], (int) $m[2] ];
	}
}
