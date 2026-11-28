<?php
/**
 * WP-CLI Backfill Command
 *
 * Usage:
 *   wp scos-social backfill
 *   wp scos-social backfill --post-type=projects --post-from=2025-06-01 --before=2025-12-31 --limit=10 --dry-run
 *
 * Finds published posts of the specified post type whose **publish date** falls in
 * [--post-from] … [--before] (default: no lower bound … today inclusive),
 * skips any that have already been amplified, and schedules them using a
 * Mon/Wed/Fri spread calendar. Optional: --schedule-from (first slot not before
 * this date) and --slot-gap-days (minimum days between slots in this run).
 *
 * The spread calendar works by:
 *  1. Fetching existing Postly scheduled posts for the workspace.
 *  2. Building a list of Mon/Wed/Fri slots starting from max(tomorrow, --schedule-from).
 *  3. Assigning each project to the next free slot, optionally enforcing --slot-gap-days
 *     between consecutive slots in this run (wider spread when batching).
 *
 * Captions: --captions-file=<path> supplies captions someone (or an agent) has
 * already written, per post ID. Posts in the file are scheduled with those captions
 * and no AI call; posts not in it are generated as before.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification\CLI
 * v1.1 | 2026-07-01
 * v1.2 | 2026-11-28 - Slots pick the day only; the engine draws the time from the publish
 *                      window, so backfill posts no longer land at 00:00 (#252).
 * v1.3 | 2026-11-28 - --captions-file: supplied captions per post ID (#253).
 */

namespace SiteEssentials\Modules\SocialAmplification\CLI;

defined( 'ABSPATH' ) || exit;

use SiteEssentials\Modules\SocialAmplification\Amplification\Amplification_Engine;
use SiteEssentials\Modules\SocialAmplification\Amplification\Postly_Client;
use SiteEssentials\Modules\SocialAmplification\Publish_Hook;

class Backfill_Command {

	/**
	 * Backfill social amplification for existing posts.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<slug>]
	 * : **WordPress post type** to backfill. Default: projects.
	 *
	 * [--before=<date>]
	 * : **WordPress post publish date** upper bound: include posts published **on or before**
	 * this date (YYYY-MM-DD). Default: today. Does not control the first Postly schedule day.
	 *
	 * [--post-from=<date>]
	 * : **WordPress post publish date** lower bound: include posts published **on or after**
	 * this date (YYYY-MM-DD). Optional; omit for no minimum (oldest posts first).
	 *
	 * [--schedule-from=<date>]
	 * : **Social calendar** lower bound: do not assign the first (or any) slot to a Mon/Wed/Fri
	 * **before** this date (YYYY-MM-DD in the site timezone). Default: tomorrow.
	 * Does not filter WordPress posts — use --post-from / --before for that.
	 *
	 * [--slot-gap-days=<n>]
	 * : Minimum **calendar days** between consecutive slots **in this run** (0 = default:
	 * pack into the next available Mon/Wed/Fri like before). Example: `7` spaces slots
	 * at least a week apart for the batch. Still respects Postly occupied dates.
	 *
	 * [--limit=<n>]
	 * : Maximum number of posts to process. Default: 3.
	 *
	 * [--captions-file=<path>]
	 * : JSON file of captions already written, keyed by post ID:
	 * `{"123": {"captions": ["...", "..."], "gmb_caption": "..."}}`, or `{"123": ["...", "..."]}`
	 * for standard captions only. A post in the file gets one standard post per caption, and
	 * no AI call is made for what the file supplies. Write {link} where the tracked link goes.
	 * Posts not in the file are generated as before. The whole file is checked before anything is sent.
	 *
	 * [--dry-run]
	 * : Print which posts would be processed without actually calling the APIs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp scos-social backfill
	 *     wp scos-social backfill --post-type=projects --post-from=2025-01-01 --before=2025-12-31 --limit=5
	 *     wp scos-social backfill --post-type=post --schedule-from=2026-05-01 --slot-gap-days=7 --limit=5 --dry-run
	 *     wp scos-social backfill --post-type=projects --limit=5 --captions-file=/tmp/captions.json --dry-run
	 *
	 * @when after_wp_load
	 *
	 * @param  array $args       Positional args (unused).
	 * @param  array $assoc_args Named args.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$post_type = \WP_CLI\Utils\get_flag_value( $assoc_args, 'post-type', 'projects' );
		$post_type = sanitize_key( (string) $post_type );
		$before    = \WP_CLI\Utils\get_flag_value( $assoc_args, 'before', date( 'Y-m-d' ) );
		$post_from = \WP_CLI\Utils\get_flag_value( $assoc_args, 'post-from', '' );
		$post_from = is_string( $post_from ) ? trim( $post_from ) : '';
		$sched_from = \WP_CLI\Utils\get_flag_value( $assoc_args, 'schedule-from', '' );
		$sched_from = is_string( $sched_from ) ? trim( $sched_from ) : '';
		$slot_gap  = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'slot-gap-days', 0 );
		$limit     = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 3 );
		$dry_run   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$cap_file  = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'captions-file', '' );

		if ( ! post_type_exists( $post_type ) ) {
			\WP_CLI::error( "Post type '{$post_type}' does not exist." );
		}
		if ( ! $this->validate_ymd( $before ) ) {
			\WP_CLI::error( 'Invalid --before= date. Use YYYY-MM-DD.' );
		}
		if ( $post_from !== '' && ! $this->validate_ymd( $post_from ) ) {
			\WP_CLI::error( 'Invalid --post-from= date. Use YYYY-MM-DD.' );
		}
		if ( $sched_from !== '' && ! $this->validate_ymd( $sched_from ) ) {
			\WP_CLI::error( 'Invalid --schedule-from= date. Use YYYY-MM-DD.' );
		}
		if ( $post_from !== '' && strcmp( $post_from, $before ) > 0 ) {
			\WP_CLI::error( '--post-from must be on or before --before.' );
		}
		if ( $slot_gap < 0 || $slot_gap > 365 ) {
			\WP_CLI::error( '--slot-gap-days must be between 0 and 365.' );
		}

		$this->validate_settings();

		$supplied = '' !== trim( $cap_file ) ? $this->load_captions_file( trim( $cap_file ) ) : [];
		if ( $supplied ) {
			\WP_CLI::line( sprintf( 'Captions file: %d post(s) with supplied captions.', count( $supplied ) ) );
		}

		$date_clause = [
			'before'    => $before,
			'inclusive' => true,
		];
		if ( $post_from !== '' ) {
			$date_clause['after'] = $post_from;
		}

		// ── Query posts ───────────────────────────────────────────────────────
		$posts = get_posts( [
			'post_type'   => $post_type,
			'post_status' => 'publish',
			'numberposts' => $limit * 3,
			'order'       => 'ASC',
			'orderby'     => 'date',
			'date_query'  => [ $date_clause ],
		] );

		if ( empty( $posts ) ) {
			$range = $post_from !== '' ? "{$post_from} … {$before}" : "≤ {$before}";
			\WP_CLI::line( "No published {$post_type} posts found in publish-date window {$range}." );
			return;
		}

		// Filter out already-amplified posts
		$pending = array_filter( $posts, static function ( \WP_Post $p ) {
			return get_post_meta( $p->ID, Publish_Hook::AMPLIFIED_META, true ) !== '1';
		} );

		$pending = array_slice( array_values( $pending ), 0, $limit );

		if ( empty( $pending ) ) {
			\WP_CLI::success( "All found {$post_type} posts have already been amplified (or limit={$limit} reached). Nothing to do." );
			return;
		}

		\WP_CLI::line( sprintf( 'Found %d post(s) to amplify (post type: %s).', count( $pending ), $post_type ) );

		// Captions for posts this run won't reach would be silently ignored, so say so.
		$unused = array_diff( array_keys( $supplied ), array_map( static function ( \WP_Post $p ): int {
			return (int) $p->ID;
		}, $pending ) );
		if ( $unused ) {
			\WP_CLI::warning( 'Captions file has posts this run will not process (not matched, already amplified or past --limit): #' . implode( ', #', $unused ) );
		}

		// ── Build spread schedule ─────────────────────────────────────────────
		$timezone = get_option( 'timezone_string', 'UTC' ) ?: 'UTC';
		if ( $sched_from !== '' || $slot_gap > 0 ) {
			\WP_CLI::line(
				sprintf(
					'Calendar: schedule-from=%s, slot-gap-days=%d (site TZ: %s)',
					$sched_from !== '' ? $sched_from : '(tomorrow)',
					$slot_gap,
					$timezone
				)
			);
		}
		$slots          = $this->get_free_slots( count( $pending ), $timezone, $sched_from, $slot_gap );
		$gmb_channel_id = Amplification_Engine::resolve_gmb_channel_id();
		$gmb_slots      = [];
		if ( $gmb_channel_id !== '' ) {
			$gmb_slots = $this->get_gmb_free_slots( count( $pending ), $timezone, $sched_from, $slot_gap );
			\WP_CLI::line( sprintf( 'GMB enabled (%s). Using separate Tue/Thu calendar (%d slots).', $gmb_channel_id, count( $gmb_slots ) ) );
		} else {
			\WP_CLI::line( 'GMB not configured; skipping GMB backfill flow.' );
		}

		if ( count( $slots ) < count( $pending ) ) {
			\WP_CLI::warning( 'Not enough free schedule slots for all posts. Some may overlap.' );
		}

		// ── Process each post ─────────────────────────────────────────────────
		$success = 0;
		$failed  = 0;

		foreach ( $pending as $i => $post ) {
			$slot_dt = $slots[ $i ] ?? null;
			$title   = get_the_title( $post );

			if ( $dry_run ) {
				$slot_str = $slot_dt ? $slot_dt->format( 'Y-m-d' ) . ' (time picked in the publish window)' : '(no slot available)';
				$gmb_slot = $gmb_slots[ $i ] ?? null;
				$gmb_str  = $gmb_slot ? $gmb_slot->format( 'Y-m-d' ) : ( $gmb_channel_id !== '' ? '(no GMB slot available)' : '(GMB disabled)' );
				\WP_CLI::line( "[DRY RUN] Would amplify post #{$post->ID} \"{$title}\" -- standard slot: {$slot_str}; gmb slot: {$gmb_str}; captions: " . $this->describe_captions( $supplied[ $post->ID ] ?? null ) );
				continue;
			}

			$options = [
				'run_standard' => true,
				'run_gmb'      => ( $gmb_channel_id !== '' ),
			];
			if ( $slot_dt ) {
				$options['standard_schedule_at'] = $slot_dt;
			}

			$gmb_slot = $gmb_slots[ $i ] ?? null;
			if ( $gmb_slot && $gmb_channel_id !== '' ) {
				$options['gmb_schedule_at'] = $gmb_slot;
			}

			// Supplied captions: one standard post per caption, as scos/send-social-post does.
			$entry = $supplied[ $post->ID ] ?? null;
			if ( $entry && $entry['captions'] ) {
				$keyed = [];
				foreach ( $entry['captions'] as $n => $caption ) {
					$keyed[ 'post_' . ( $n + 1 ) ] = $caption;
				}
				$options['captions']   = $keyed;
				$options['post_count'] = count( $keyed );
			}
			if ( $entry && '' !== $entry['gmb_caption'] ) {
				$options['gmb_caption'] = $entry['gmb_caption'];
			}

			try {
				$result = Amplification_Engine::run( $post->ID, $options );
				update_post_meta( $post->ID, Publish_Hook::AMPLIFIED_META, '1' );

				$scheduled_standard = array_column( $result['standard_posts'] ?? [], 'scheduled' );
				$scheduled_gmb      = array_column( $result['gmb_posts'] ?? [], 'scheduled' );
				\WP_CLI::success(
					"#{$post->ID} \"{$title}\" -- standard: "
					. ( $scheduled_standard ? implode( ', ', $scheduled_standard ) : 'none' )
					. ' | gmb: '
					. ( $scheduled_gmb ? implode( ', ', $scheduled_gmb ) : 'none' )
				);
				$success++;
			} catch ( \RuntimeException $e ) {
				\WP_CLI::warning( "#{$post->ID} \"{$title}\" -- FAILED: " . $e->getMessage() );
				$failed++;
			}

			usleep( 500000 ); // 0.5s pause between API calls
		}

		if ( ! $dry_run ) {
			\WP_CLI::success( "Backfill complete. Processed: {$success} succeeded, {$failed} failed." );
		}
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Helpers
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Read and check a captions file. Any problem stops the run before anything is sent.
	 *
	 * @return array<int, array{captions: string[], gmb_caption: string}> Keyed by post ID.
	 */
	private function load_captions_file( string $path ): array {
		if ( ! is_readable( $path ) || ! is_file( $path ) ) {
			\WP_CLI::error( "--captions-file: cannot read {$path}." );
		}
		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) || ( $data && array_is_list( $data ) ) ) {
			\WP_CLI::error( '--captions-file: expected a JSON object keyed by post ID, e.g. {"123": {"captions": ["..."]}}.' . ( JSON_ERROR_NONE !== json_last_error() ? ' ' . json_last_error_msg() . '.' : '' ) );
		}

		$out = [];
		foreach ( $data as $post_id => $entry ) {
			$id = (int) $post_id;
			if ( $id <= 0 || (string) $id !== (string) $post_id ) {
				\WP_CLI::error( "--captions-file: \"{$post_id}\" is not a post ID." );
			}
			if ( is_array( $entry ) && array_is_list( $entry ) ) {
				$entry = [ 'captions' => $entry ];
			}
			if ( ! is_array( $entry ) ) {
				\WP_CLI::error( "--captions-file: post {$id} needs a list of captions or an object with captions / gmb_caption." );
			}
			$extra = array_diff( array_keys( $entry ), [ 'captions', 'gmb_caption' ] );
			if ( $extra ) {
				\WP_CLI::error( "--captions-file: post {$id} has unknown key(s): " . implode( ', ', $extra ) . '. Use captions and gmb_caption.' );
			}

			$captions = $entry['captions'] ?? [];
			if ( ! is_array( $captions ) || ! array_is_list( $captions ) ) {
				\WP_CLI::error( "--captions-file: post {$id} captions must be a list of strings." );
			}
			$clean = [];
			foreach ( $captions as $n => $caption ) {
				$caption = is_string( $caption ) ? trim( $caption ) : '';
				if ( '' === $caption ) {
					\WP_CLI::error( sprintf( '--captions-file: post %d caption %d is empty or not text.', $id, $n + 1 ) );
				}
				$clean[] = $caption;
			}
			if ( count( $clean ) > 10 ) {
				\WP_CLI::error( "--captions-file: post {$id} has more than 10 captions." );
			}

			$gmb = $entry['gmb_caption'] ?? '';
			if ( ! is_string( $gmb ) ) {
				\WP_CLI::error( "--captions-file: post {$id} gmb_caption must be text." );
			}
			if ( ! $clean && '' === trim( $gmb ) ) {
				\WP_CLI::error( "--captions-file: post {$id} has no captions and no gmb_caption." );
			}

			$out[ $id ] = [ 'captions' => $clean, 'gmb_caption' => trim( $gmb ) ];
		}
		return $out;
	}

	/**
	 * Dry-run note on where a post's captions come from.
	 *
	 * @param array{captions: string[], gmb_caption: string}|null $entry
	 */
	private function describe_captions( ?array $entry ): string {
		if ( ! $entry ) {
			return 'generated';
		}
		$standard = $entry['captions'] ? count( $entry['captions'] ) . ' supplied' : 'generated';
		$gmb      = '' !== $entry['gmb_caption'] ? 'supplied' : 'generated';
		return "standard {$standard}, gmb {$gmb}";
	}

	/**
	 * @param string $d YYYY-MM-DD.
	 */
	private function validate_ymd( string $d ): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
			return false;
		}
		$parts = array_map( 'intval', explode( '-', $d ) );
		return checkdate( $parts[1], $parts[2], $parts[0] );
	}

	/**
	 * Abort early with a clear message if required settings are missing.
	 */
	private function validate_settings(): void {
		$missing  = [];
		$required = [
			'bw_postly_api_key'       => 'Postly API key',
			'bw_postly_workspace_id'  => 'Postly Workspace ID',
			'bw_social_webhook_secret' => 'Webhook secret',
		];
		foreach ( $required as $key => $label ) {
			if ( ! get_option( $key ) ) {
				$missing[] = $label . " ({$key})";
			}
		}
		if ( ! empty( $missing ) ) {
			\WP_CLI::error( 'Missing required settings: ' . implode( ', ', $missing ) );
		}
	}

	/**
	 * Build a list of Mon/Wed/Fri slots, skipping Postly-occupied dates.
	 *
	 * @param  int    $count             Number of slots needed.
	 * @param  string $timezone          WP timezone string.
	 * @param  string $schedule_from     YYYY-MM-DD or '' — first slot on this calendar day or later.
	 * @param  int    $slot_gap_days     Minimum days between consecutive slots (0 = pack tightly).
	 * @return \DateTimeImmutable[]
	 */
	private function get_free_slots( int $count, string $timezone, string $schedule_from = '', int $slot_gap_days = 0 ): array {
		$tz         = new \DateTimeZone( $timezone );
		$today      = new \DateTimeImmutable( 'today', $tz );
		$tomorrow   = $today->modify( '+1 day' )->setTime( 0, 0, 0 );
		$scan_start = $tomorrow;

		if ( $schedule_from !== '' ) {
			$floor = new \DateTimeImmutable( $schedule_from . ' 00:00:00', $tz );
			if ( $floor > $scan_start ) {
				$scan_start = $floor;
			}
		}

		$occupied = $this->get_occupied_dates( $tz );

		$slots         = [];
		$day           = $scan_start;
		$next_earliest = null;

		$guard = max( 500, $count * ( 7 + max( 0, $slot_gap_days ) ) * 3 );

		while ( count( $slots ) < $count && $guard-- > 0 ) {
			if ( $next_earliest !== null && $day->format( 'Y-m-d' ) < $next_earliest->format( 'Y-m-d' ) ) {
				$day = $day->modify( '+1 day' );
				continue;
			}

			$dow = (int) $day->format( 'N' ); // 1=Mon … 7=Sun
			if ( in_array( $dow, [ 1, 3, 5 ], true ) ) {
				$date_str = $day->format( 'Y-m-d' );
				if ( ! in_array( $date_str, $occupied, true ) ) {
					$slots[] = $day->setTime( 0, 0, 0 );
					if ( $slot_gap_days > 0 ) {
						$next_earliest = $day->modify( '+' . $slot_gap_days . ' days' )->setTime( 0, 0, 0 );
					} else {
						$next_earliest = null;
					}
				}
			}
			$day = $day->modify( '+1 day' );
		}

		return $slots;
	}

	/**
	 * Build a separate GMB calendar: Tue/Thu slots only.
	 *
	 * @return \DateTimeImmutable[]
	 */
	private function get_gmb_free_slots( int $count, string $timezone, string $schedule_from = '', int $slot_gap_days = 0 ): array {
		$tz         = new \DateTimeZone( $timezone );
		$today      = new \DateTimeImmutable( 'today', $tz );
		$tomorrow   = $today->modify( '+1 day' )->setTime( 0, 0, 0 );
		$scan_start = $tomorrow;

		if ( $schedule_from !== '' ) {
			$floor = new \DateTimeImmutable( $schedule_from . ' 00:00:00', $tz );
			if ( $floor > $scan_start ) {
				$scan_start = $floor;
			}
		}

		$occupied      = $this->get_occupied_dates( $tz );
		$slots         = [];
		$day           = $scan_start;
		$next_earliest = null;
		$guard         = max( 500, $count * ( 7 + max( 0, $slot_gap_days ) ) * 3 );

		while ( count( $slots ) < $count && $guard-- > 0 ) {
			if ( $next_earliest !== null && $day->format( 'Y-m-d' ) < $next_earliest->format( 'Y-m-d' ) ) {
				$day = $day->modify( '+1 day' );
				continue;
			}

			$dow = (int) $day->format( 'N' ); // 2=Tue, 4=Thu
			if ( in_array( $dow, [ 2, 4 ], true ) ) {
				$date_str = $day->format( 'Y-m-d' );
				if ( ! in_array( $date_str, $occupied, true ) ) {
					$slots[] = $day->setTime( 0, 0, 0 );
					if ( $slot_gap_days > 0 ) {
						$next_earliest = $day->modify( '+' . $slot_gap_days . ' days' )->setTime( 0, 0, 0 );
					} else {
						$next_earliest = null;
					}
				}
			}
			$day = $day->modify( '+1 day' );
		}

		return $slots;
	}

	/**
	 * Fetch dates (YYYY-MM-DD) of already-scheduled Postly posts.
	 * Silently returns empty array if Postly API is unreachable.
	 *
	 * @return string[]
	 */
	private function get_occupied_dates( \DateTimeZone $tz ): array {
		$api_key      = (string) get_option( 'bw_postly_api_key', '' );
		$workspace_id = (string) get_option( 'bw_postly_workspace_id', '' );

		if ( ! $api_key || ! $workspace_id ) {
			return [];
		}

		try {
			$client = new Postly_Client( $api_key, $workspace_id );
			$dates  = [];
			$skip   = 0;

			do {
				$page = $client->fetch_posts( $skip );
				if ( empty( $page ) ) {
					break;
				}
				foreach ( $page as $post ) {
					$sched = $post['one_off_schedule'] ?? [];
					$d     = $sched['one_off_date'] ?? '';
					if ( $d ) {
						$dates[] = substr( $d, 0, 10 );
					}
				}
				$skip += count( $page );
			} while ( count( $page ) >= 50 );

			return array_unique( $dates );

		} catch ( \RuntimeException $e ) {
			\WP_CLI::debug( 'Could not fetch Postly posts for schedule check: ' . $e->getMessage() );
			return [];
		}
	}
}
