<?php
/**
 * WP-CLI Backfill Command
 *
 * Usage:
 *   wp bw-social backfill
 *   wp bw-social backfill --post-from=2025-06-01 --before=2025-12-31 --limit=10 --dry-run
 *
 * Finds published `projects` posts whose **publish date** falls in the window
 * [--post-from] … [--before] (default: no lower bound … today inclusive),
 * skips any that have already been amplified, and schedules them using a
 * Mon/Wed/Fri spread calendar. Optional: --schedule-from (first slot not before this
 * date) and --slot-gap-days (minimum days between slots in this run).
 *
 * The spread calendar works by:
 *  1. Fetching existing Postly scheduled posts for the workspace.
 *  2. Building a list of Mon/Wed/Fri slots starting from max(tomorrow, --schedule-from).
 *  3. Assigning each project to the next free slot, optionally enforcing --slot-gap-days
 *     between consecutive slots in this run (wider spread when batching).
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification\CLI
 */

namespace SiteEssentials\Modules\SocialAmplification\CLI;

defined( 'ABSPATH' ) || exit;

use SiteEssentials\Modules\SocialAmplification\Amplification\Amplification_Engine;
use SiteEssentials\Modules\SocialAmplification\Amplification\Postly_Client;
use SiteEssentials\Modules\SocialAmplification\Publish_Hook;

class Backfill_Command {

	/**
	 * Backfill social amplification for existing projects posts.
	 *
	 * ## OPTIONS
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
	 * [--dry-run]
	 * : Print which posts would be processed without actually calling the APIs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bw-social backfill
	 *     wp bw-social backfill --post-from=2025-01-01 --before=2025-12-31 --limit=5
	 *     wp bw-social backfill --schedule-from=2026-05-01 --slot-gap-days=7 --limit=5 --dry-run
	 *
	 * @when after_wp_load
	 *
	 * @param  array $args       Positional args (unused).
	 * @param  array $assoc_args Named args.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$before    = \WP_CLI\Utils\get_flag_value( $assoc_args, 'before', date( 'Y-m-d' ) );
		$post_from = \WP_CLI\Utils\get_flag_value( $assoc_args, 'post-from', '' );
		$post_from = is_string( $post_from ) ? trim( $post_from ) : '';
		$sched_from = \WP_CLI\Utils\get_flag_value( $assoc_args, 'schedule-from', '' );
		$sched_from = is_string( $sched_from ) ? trim( $sched_from ) : '';
		$slot_gap  = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'slot-gap-days', 0 );
		$limit     = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 3 );
		$dry_run   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

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

		$date_clause = [
			'before'    => $before,
			'inclusive' => true,
		];
		if ( $post_from !== '' ) {
			$date_clause['after'] = $post_from;
		}

		// ── Query projects ────────────────────────────────────────────────────
		$posts = get_posts( [
			'post_type'   => 'projects',
			'post_status' => 'publish',
			'numberposts' => $limit * 3, // fetch extra to account for already-amplified
			'order'       => 'ASC',
			'orderby'     => 'date',
			'date_query'  => [ $date_clause ],
		] );

		if ( empty( $posts ) ) {
			$range = $post_from !== '' ? "{$post_from} … {$before}" : "≤ {$before}";
			\WP_CLI::line( 'No published projects found in publish-date window ' . $range . '.' );
			return;
		}

		// Filter out already-amplified posts
		$pending = array_filter( $posts, static function ( \WP_Post $p ) {
			return get_post_meta( $p->ID, Publish_Hook::AMPLIFIED_META, true ) !== '1';
		} );

		$pending = array_slice( array_values( $pending ), 0, $limit );

		if ( empty( $pending ) ) {
			\WP_CLI::success( "All found projects have already been amplified (or limit={$limit} reached). Nothing to do." );
			return;
		}

		\WP_CLI::line( sprintf( 'Found %d post(s) to amplify.', count( $pending ) ) );

		// ── Build spread schedule ────────────────────────────────────────────
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
		$slots = $this->get_free_slots( count( $pending ), $timezone, $sched_from, $slot_gap );

		if ( count( $slots ) < count( $pending ) ) {
			\WP_CLI::warning( 'Not enough free schedule slots for all posts. Some may overlap.' );
		}

		// ── Process each post ────────────────────────────────────────────────
		$success = 0;
		$failed  = 0;

		foreach ( $pending as $i => $post ) {
			$slot_dt = $slots[ $i ] ?? null;
			$title   = get_the_title( $post );

			if ( $dry_run ) {
				$slot_str = $slot_dt ? $slot_dt->format( 'Y-m-d H:i' ) : '(no slot available)';
				\WP_CLI::line( "[DRY RUN] Would amplify post #{$post->ID} \"{$title}\" -- slot: {$slot_str}" );
				continue;
			}

			$options = [];
			if ( $slot_dt ) {
				$options['schedule_at'] = $slot_dt;
			}

			try {
				$result = Amplification_Engine::run( $post->ID, $options );
				// Mark as amplified
				update_post_meta( $post->ID, Publish_Hook::AMPLIFIED_META, '1' );

				$scheduled = array_column( $result['posts'] ?? [], 'scheduled' );
				\WP_CLI::success( "#{$post->ID} \"{$title}\" -- scheduled posts at: " . implode( ', ', $scheduled ) );
				$success++;
			} catch ( \RuntimeException $e ) {
				\WP_CLI::warning( "#{$post->ID} \"{$title}\" -- FAILED: " . $e->getMessage() );
				$failed++;
			}

			// Small pause between API calls
			usleep( 500000 ); // 0.5s
		}

		if ( ! $dry_run ) {
			\WP_CLI::success( "Backfill complete. Processed: {$success} succeeded, {$failed} failed." );
		}
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Helpers
	// ──────────────────────────────────────────────────────────────────────────

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
		$missing = [];
		$required = [
			'bw_anthropic_api_key'    => 'Anthropic API key',
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
	 * @param  string $schedule_from     YYYY-MM-DD or '' — first slot on this calendar day or later (also never before tomorrow).
	 * @param  int    $slot_gap_days     Minimum days between consecutive slots (0 = pack tightly on MWF).
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
		$next_earliest = null; // next slot date must be >= this (Y-m-d compare).

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
	 * Fetch dates (YYYY-MM-DD) of already-scheduled Postly posts for the workspace.
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
					// Postly returns ISO-8601 (e.g. "2026-05-19T00:00:00.000Z") — extract date part only.
					$dates[] = substr( $d, 0, 10 );
				}
			}
				$skip += count( $page );
			} while ( count( $page ) >= 50 ); // Postly default page size appears to be 50

			return array_unique( $dates );

		} catch ( \RuntimeException $e ) {
			\WP_CLI::debug( 'Could not fetch Postly posts for schedule check: ' . $e->getMessage() );
			return [];
		}
	}
}
