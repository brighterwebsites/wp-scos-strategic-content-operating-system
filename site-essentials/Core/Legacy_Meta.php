<?php
/**
 * Legacy meta registry — which meta keys are dead, and safe cleanup of them.
 *
 * An explicit, per-key list. Never sweep by prefix: live custom post data still
 * uses bw_ keys (bw__prj_*, bw_rating, bw_views_count …), so only keys listed
 * here are ever counted or deleted.
 *
 *   deprecated — still read, or data not yet moved. Reported, never deleted.
 *   retired    — data migrated and nothing needs it. Deletable.
 *
 * Moving a key from deprecated to retired is the decision point: do it in the
 * PR that removes the key's last reader.
 *
 * Interface-free on purpose: called by `wp scos legacy-meta` (Core\CLI\Legacy_Meta_Command),
 * and reusable from an ability if an agent ever needs it.
 *
 * @package    SiteEssentials
 * @subpackage Core
 *
 * v1.0 | 2026-10-20
 */

namespace SiteEssentials\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Legacy_Meta {

	const STATUS_DEPRECATED = 'deprecated';
	const STATUS_RETIRED    = 'retired';

	/**
	 * Default backup directory for delete(). Root-only, outside every web root.
	 */
	const BACKUP_DIR = '/root/wp-standardise-backups';

	/**
	 * The registry. key => [ object, status, replacement, reason, issue ].
	 *
	 * object: 'post' or 'term' (meta table).
	 */
	const KEYS = [
		// ---- bw_* content strategy → scos_ca_* (migration complete, 2026-07-15) ----
		'bw_primary_altc_id'     => [ 'post', self::STATUS_RETIRED, 'scos_content_cluster term', 'bw_ → scos_ migration complete', '#117' ],
		'bw_primary_topic_id'    => [ 'post', self::STATUS_RETIRED, 'scos_topic term', 'bw_ → scos_ migration complete', '#117' ],
		'bw_page_topic'          => [ 'post', self::STATUS_RETIRED, 'scos_topic term', 'bw_ → scos_ migration complete', '#117' ],
		'bw_cont_maturity'       => [ 'post', self::STATUS_RETIRED, 'scos_ca_maturity', 'bw_ → scos_ migration complete', '#117' ],
		'bw_intent'              => [ 'post', self::STATUS_RETIRED, 'scos_ca_intent', 'bw_ → scos_ migration complete', '#117' ],
		'bw_purpose'             => [ 'post', self::STATUS_RETIRED, 'scos_ca_purpose', 'bw_ → scos_ migration complete', '#117' ],
		'bw_altc_notes'          => [ 'post', self::STATUS_RETIRED, 'scos_ca_intent_goal', 'bw_ → scos_ migration complete', '#117' ],
		'bw_notes'               => [ 'post', self::STATUS_RETIRED, '', 'No SCOS equivalent; unused', '#117' ],
		'bw_pillar_page_id'      => [ 'post', self::STATUS_RETIRED, 'scos_ca_pillar_page_id', 'bw_ → scos_ migration complete', '#117' ],
		'bw_service_pathway_id'  => [ 'post', self::STATUS_RETIRED, 'scos_ca_service_pathway_id', 'bw_ → scos_ migration complete', '#117' ],
		'bw_index_status'        => [ 'post', self::STATUS_RETIRED, 'scos_ca_index_status', 'bw_ → scos_ migration complete', '#117' ],
		'bw_breadcrumb_schema'   => [ 'post', self::STATUS_RETIRED, 'scos_seo_breadcrumb_title', 'bw_ → scos_ migration complete', '#117' ],
		'workflow_progress'      => [ 'post', self::STATUS_RETIRED, 'scos_ca_optimization_progress', 'bw_ → scos_ migration complete', '#117' ],
		'content_plan'           => [ 'post', self::STATUS_RETIRED, 'scos_ca_next_step', 'bw_ → scos_ migration complete', '#117' ],

		// ---- bw_* content analysis → scos_ca_* (written only when the CA module is off) ----
		'bw_word_count'          => [ 'post', self::STATUS_RETIRED, 'scos_ca_word_count', 'Content analysis moved to CA', '#117' ],
		'bw_image_count'         => [ 'post', self::STATUS_RETIRED, 'scos_ca_image_count', 'Content analysis moved to CA', '#117' ],
		'bw_reading_time'        => [ 'post', self::STATUS_RETIRED, 'scos_ca_reading_time', 'Content analysis moved to CA', '#117' ],
		'bw_reading_time_iso'    => [ 'post', self::STATUS_RETIRED, 'scos_ca_reading_time_iso', 'Content analysis moved to CA', '#117' ],
		'bw_internal_link_count' => [ 'post', self::STATUS_RETIRED, 'scos_ca_links_to_internal', 'Content analysis moved to CA', '#117' ],
		'bw_external_link_count' => [ 'post', self::STATUS_RETIRED, 'scos_ca_links_to_external', 'Content analysis moved to CA', '#117' ],
		'bw_internal_links'      => [ 'post', self::STATUS_RETIRED, 'scos_ca_links_to_internal_list', 'Content analysis moved to CA', '#117' ],
		'bw_external_links'      => [ 'post', self::STATUS_RETIRED, 'scos_ca_links_to_external_list', 'Content analysis moved to CA', '#117' ],
		'_bw_last_analyzed'      => [ 'post', self::STATUS_RETIRED, 'scos_ca_last_analyzed', 'Content analysis moved to CA', '#92' ],

		// ---- Deprecated: still read, keep the data ----
		'scos_ca_pillar_page_id'        => [ 'post', self::STATUS_DEPRECATED, 'scos_topic + scos_ca_supporting_topics', 'Hidden from UI; content inventory and brighter-core API still read it', '#171' ],
		'scos_ca_next_step'             => [ 'post', self::STATUS_DEPRECATED, 'Ops Centre', 'Workflow state moving to the Ops Centre', '#192' ],
		'scos_ca_optimization_progress' => [ 'post', self::STATUS_DEPRECATED, 'Ops Centre', 'Workflow state moving to the Ops Centre', '#192' ],
		'scos_ca_index_status'          => [ 'post', self::STATUS_DEPRECATED, 'Ops Centre', 'Workflow state moving to the Ops Centre', '#192' ],
		'tldr'                          => [ 'post', self::STATUS_DEPRECATED, 'scos_seo_tldr', 'Pre-bw_ ACF field; schema output still falls back to it', '#129' ],
		'topic_sameas_url'              => [ 'term', self::STATUS_DEPRECATED, 'scos_topic_same_as', 'Legacy altc_topic term meta; schema still falls back to it', '#129' ],
	];

	/**
	 * Registry rows, optionally filtered by status.
	 *
	 * @param string $status '' for all, or a STATUS_* value.
	 * @return array<int, array{key:string, object:string, status:string, replacement:string, reason:string, issue:string}>
	 */
	public static function entries( string $status = '' ): array {
		$rows = [];
		foreach ( self::KEYS as $key => $def ) {
			if ( '' !== $status && $def[1] !== $status ) {
				continue;
			}
			$rows[] = [
				'key'         => $key,
				'object'      => $def[0],
				'status'      => $def[1],
				'replacement' => $def[2],
				'reason'      => $def[3],
				'issue'       => $def[4],
			];
		}
		return $rows;
	}

	/**
	 * Registry rows with row counts on this site.
	 *
	 * `rows` counts the key itself; `acf_refs` counts its ACF field-reference
	 * companion (`_<key>`), which delete() removes along with it.
	 *
	 * @param string $status '' for all, or a STATUS_* value.
	 * @return array
	 */
	public static function audit( string $status = '' ): array {
		$entries = self::entries( $status );

		$by_object = [];
		foreach ( $entries as $e ) {
			$by_object[ $e['object'] ][] = $e['key'];
			$by_object[ $e['object'] ][] = '_' . $e['key'];
		}

		$counts = [];
		foreach ( $by_object as $object => $keys ) {
			$counts[ $object ] = self::count_keys( $object, $keys );
		}

		foreach ( $entries as &$e ) {
			$e['rows']     = $counts[ $e['object'] ][ $e['key'] ] ?? 0;
			$e['acf_refs'] = $counts[ $e['object'] ][ '_' . $e['key'] ] ?? 0;
		}
		unset( $e );

		return $entries;
	}

	/**
	 * bw_ / _bw_ post meta keys present on this site but not in the registry.
	 *
	 * For triage only: many of these are live CPT data and must stay.
	 *
	 * @return array<int, array{key:string, rows:int}>
	 */
	public static function unregistered(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one aggregate read, CLI only.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, COUNT(*) AS n FROM {$wpdb->postmeta}
				 WHERE meta_key LIKE %s OR meta_key LIKE %s
				 GROUP BY meta_key ORDER BY meta_key",
				$wpdb->esc_like( 'bw_' ) . '%',
				$wpdb->esc_like( '_bw_' ) . '%'
			),
			ARRAY_A
		);

		$rows = [];
		foreach ( (array) $results as $r ) {
			$key = (string) $r['meta_key'];
			if ( isset( self::KEYS[ $key ] ) ) {
				continue;
			}
			// ACF companion of a registered key — already counted by audit().
			if ( '_' === $key[0] && isset( self::KEYS[ substr( $key, 1 ) ] ) ) {
				continue;
			}
			$rows[] = [ 'key' => $key, 'rows' => (int) $r['n'] ];
		}
		return $rows;
	}

	/**
	 * Write every row of the given retired keys (and ACF companions) to a JSON file.
	 *
	 * @param string[] $keys Registry keys, all retired.
	 * @param string   $dir  Backup directory; created if missing.
	 * @return string|\WP_Error Backup file path.
	 */
	public static function backup( array $keys, string $dir = self::BACKUP_DIR ) {
		global $wpdb;

		$dir = untrailingslashit( $dir );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'scos_legacy_meta_backup_dir', sprintf( 'Cannot create backup directory %s.', $dir ) );
		}

		$data = [
			'site'    => home_url(),
			'created' => gmdate( 'c' ),
			'rows'    => [],
		];

		foreach ( $keys as $key ) {
			$object = self::KEYS[ $key ][0];
			$table  = 'term' === $object ? $wpdb->termmeta : $wpdb->postmeta;
			$id_col = 'term' === $object ? 'term_id' : 'post_id';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- backup read, CLI only.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT {$id_col} AS object_id, meta_key, meta_value FROM {$table} WHERE meta_key IN (%s, %s)",
					$key,
					'_' . $key
				),
				ARRAY_A
			);
			foreach ( (array) $rows as $r ) {
				$r['object']    = $object;
				$data['rows'][] = $r;
			}
		}

		$file = sprintf( '%s/scos-legacy-meta-%s-%s.json', $dir, sanitize_file_name( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ), gmdate( 'Ymd-His' ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions -- CLI context, outside the web root.
		$written = file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		if ( false === $written ) {
			return new \WP_Error( 'scos_legacy_meta_backup_write', sprintf( 'Cannot write backup file %s.', $file ) );
		}
		chmod( $file, 0600 );

		return $file;
	}

	/**
	 * Delete every row of one retired key, plus its ACF companion.
	 *
	 * @param string $key Registry key.
	 * @return int|\WP_Error Rows deleted.
	 */
	public static function delete( string $key ) {
		if ( ! isset( self::KEYS[ $key ] ) ) {
			return new \WP_Error( 'scos_legacy_meta_unknown', sprintf( '%s is not in the legacy meta registry.', $key ) );
		}
		if ( self::STATUS_RETIRED !== self::KEYS[ $key ][1] ) {
			return new \WP_Error( 'scos_legacy_meta_not_retired', sprintf( '%s is %s, not retired. Only retired keys can be deleted.', $key, self::KEYS[ $key ][1] ) );
		}

		$object = self::KEYS[ $key ][0];
		$counts = self::count_keys( $object, [ $key, '_' . $key ] );
		$total  = array_sum( $counts );

		// delete_metadata() with $delete_all clears the object cache for every affected ID.
		delete_metadata( $object, 0, $key, '', true );
		delete_metadata( $object, 0, '_' . $key, '', true );

		return $total;
	}

	/**
	 * Row counts for a set of keys in one meta table.
	 *
	 * @param string   $object 'post' or 'term'.
	 * @param string[] $keys   Meta keys.
	 * @return array<string, int>
	 */
	private static function count_keys( string $object, array $keys ): array {
		global $wpdb;

		if ( empty( $keys ) ) {
			return [];
		}

		$table        = 'term' === $object ? $wpdb->termmeta : $wpdb->postmeta;
		$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, COUNT(*) AS n FROM {$table} WHERE meta_key IN ({$placeholders}) GROUP BY meta_key",
				$keys
			),
			ARRAY_A
		);

		$counts = [];
		foreach ( (array) $results as $r ) {
			$counts[ $r['meta_key'] ] = (int) $r['n'];
		}
		return $counts;
	}
}
