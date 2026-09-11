<?php
/**
 * Amplification Engine
 *
 * Orchestrates the full publish-to-Postly workflow for a single post:
 *  1. Gather post data (title, excerpt, permalink, images)
 *  2. Create YOURLS shortlink (falls back to permalink)
 *  3. Use caller-supplied captions, or generate them via Caption_Generator
 *  4. Upload images to Postly CDN
 *  5. Schedule 3 posts (T+0, T+42d, T+84d — Mon/Wed/Fri aligned)
 *  6. Log results to wp_option `scos_sa_amplify_log`; the entry it replaces moves
 *     to the post's run history (post meta `_scos_sa_run_history`)
 *  7. Queue failed slots (post meta `_scos_sa_retry_queue`) and, for temporary
 *     failures, schedule a WP-Cron retry of just those slots
 *
 * Captions: pass `captions` (post_1…post_N) and/or `gmb_caption` in $options to
 * schedule text the caller already wrote — no AI call is made. Omit them and the
 * text is generated through the WP AI Client. See CLAUDE.md § 6.
 *
 * Outcome: every log entry carries `outcome` — complete (all slots scheduled),
 * partial, failed (none scheduled) or skipped (nothing configured). The
 * `_scos_sa_amplified` flag stays a re-run lock; the outcome says how it went.
 *
 * Image-selection rules:
 *  - Collect featured image + ACF gallery images, deduplicate.
 *  - If ≥ 5 images: Post 1 = first 4 gallery, Post 2 = last 4 gallery, Post 3 = random 4.
 *  - If < 5 images:  same set for all three posts (up to 4 images each).
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification\Amplification
 * v1.4 | 2026-09-11 — Anthropic_Client replaced by Caption_Generator (WP AI Client);
 *                      added caller-supplied caption pass-through.
 * v1.5 | 2026-09-11 — Failed slots are queued and retried (WP-Cron for temporary
 *                      failures, retry_failed_slots() on demand); run outcome;
 *                      run history instead of overwriting; Postly post IDs captured.
 * v1.6 | 2026-09-11 — Images pass through Postly_Image_Guard so Postly never
 *                      receives WebP/AVIF (Facebook rejected a .jpg the server
 *                      answered with WebP).
 */

namespace SiteEssentials\Modules\SocialAmplification\Amplification;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/Postly_Image_Guard.php';

class Amplification_Engine {

	const IMAGES_PER_POST    = 4;
	const LOG_OPTION         = 'scos_sa_amplify_log';
	const LOG_PREFIX         = '[SCOS SMA Engine]';

	/** WP-Cron hook that resends a post's queued slots. Arg: [ post_id ]. */
	const RETRY_HOOK = 'scos_sa_retry_slots';

	/** Post meta: slots that failed, with everything needed to resend them. */
	const RETRY_QUEUE_META = '_scos_sa_retry_queue';

	/** Post meta: earlier log entries for the post, newest first. */
	const HISTORY_META = '_scos_sa_run_history';

	/** Post meta: unix time a retry started — stops the cron and the button resending the same slot twice. */
	const RETRY_LOCK_META = '_scos_sa_retry_lock';

	/** Attempts per slot before automatic retries stop (the first try counts as one). */
	const MAX_AUTO_ATTEMPTS = 4;

	const HISTORY_LIMIT = 20;

	/** Minutes before a crashed retry's lock is ignored. */
	const LOCK_MINUTES = 15;

	public static function init(): void {
		add_action( self::RETRY_HOOK, [ __CLASS__, 'handle_retry_event' ] );
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Channel resolution
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Build the channel ID list for standard social posts.
	 * Combines per-platform IDs (Facebook, Instagram) and the legacy Others field.
	 *
	 * @return string[]
	 */
	private static function get_standard_channel_ids(): array {
		$ids = [];

		$fb_id = trim( (string) get_option( 'scos_sa_postly_fb_channel_id', '' ) );
		if ( $fb_id && get_option( 'scos_sa_postly_fb_enabled', '1' ) !== '0' ) {
			$ids[] = $fb_id;
		}

		$ig_id = trim( (string) get_option( 'scos_sa_postly_ig_channel_id', '' ) );
		if ( $ig_id && get_option( 'scos_sa_postly_ig_enabled', '1' ) !== '0' ) {
			$ids[] = $ig_id;
		}

		$others_raw = (string) get_option( 'bw_postly_channel_ids', '' );
		foreach ( array_filter( array_map( 'trim', explode( ',', $others_raw ) ) ) as $id ) {
			$ids[] = $id;
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Get the configured GMB channel ID (new key with legacy fallback).
	 * Public so CLI and external callers can check without instantiating.
	 */
	public static function resolve_gmb_channel_id(): string {
		$id = trim( (string) get_option( 'scos_sa_postly_gmb_channel_id', '' ) );
		if ( '' !== $id ) {
			return $id;
		}
		return trim( (string) get_option( 'se_postly_gmb_channel_id', '' ) );
	}

	private static function get_gmb_channel_id(): string {
		return self::resolve_gmb_channel_id();
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Entry point
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Run the full amplification pipeline for a single post.
	 *
	 * @param  int   $post_id
	 * @param  array $options Override schedule etc. { schedule_at: \DateTimeImmutable }
	 * @return array          Result summary logged to `scos_sa_amplify_log`.
	 * @throws \RuntimeException on unrecoverable error.
	 */
	public static function run( int $post_id, array $options = [] ): array {
		error_log( self::LOG_PREFIX . " ── Starting amplification run for post #{$post_id} ──" );

		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			$msg = "Post {$post_id} is not published or does not exist.";
			error_log( self::LOG_PREFIX . ' ' . $msg );
			throw new \RuntimeException( $msg );
		}

		// ── 1. Gather post data ──────────────────────────────────────────────
		$title     = get_the_title( $post );
		$permalink = get_permalink( $post );
		$excerpt   = self::get_excerpt( $post );
		error_log( self::LOG_PREFIX . " Post #{$post_id}: \"{$title}\" | {$permalink}" );

		// ── 2. YOURLS shortlink ──────────────────────────────────────────────
		$shortlink = self::get_shortlink( $post_id, $permalink );
		error_log( self::LOG_PREFIX . " Shortlink: {$shortlink}" );

		// ── 3. Determine content type ────────────────────────────────────────
		$content_type = self::get_content_type( $post );
		error_log( self::LOG_PREFIX . " Content type: {$content_type}" );

		$context = [
			'post_id'      => $post_id,
			'title'        => $title,
			'excerpt'      => $excerpt,
			'permalink'    => $permalink,
			'shortlink'    => $shortlink,
			'content_type' => $content_type,
		];

		$timezone = get_option( 'timezone_string', 'UTC' ) ?: 'UTC';

		// A fresh run replaces the last one — any slots still queued from it are dropped
		// so the retry doesn't add a second copy alongside this run's posts.
		$previous_queue = self::get_retry_queue( $post_id );
		$discarded      = count( $previous_queue );
		if ( $discarded ) {
			error_log( self::LOG_PREFIX . " Dropping {$discarded} queued retry slot(s) from the previous run of post #{$post_id}." );
		}
		self::clear_retry_queue( $post_id );

		// ── 4–5. Publish window and base schedule time ───────────────────────
		// Slot 1 lands at a random time in the configured window (default 09:00–17:00
		// site time), at least 60 minutes from now; otherwise tomorrow.
		$window  = self::publish_window();
		$now     = new \DateTimeImmutable( 'now', new \DateTimeZone( $timezone ) );
		$base_dt = self::first_slot_time( $options['schedule_at'] ?? $options['standard_schedule_at'] ?? $now, $now, $window );

		error_log( self::LOG_PREFIX . ' Base schedule (standard default): ' . $base_dt->format( 'Y-m-d H:i T' ) . " (window {$window['label']})" );

		// ── 6. Resolve per-post-type config ─────────────────────────────────
		$pt_config = \SiteEssentials\Modules\SocialAmplification\Post_Type_Config::get_config(
			$post->post_type
		);

		$run_standard = array_key_exists( 'run_standard', $options ) ? (bool) $options['run_standard'] : true;
		$run_gmb      = array_key_exists( 'run_gmb', $options ) ? (bool) $options['run_gmb'] : true;

		// Override post_count if passed explicitly via CLI/ability options.
		if ( isset( $options['post_count'] ) ) {
			$pt_config['post_count'] = max( 1, (int) $options['post_count'] );
		}

		$standard_results = [];
		$gmb_results      = [];
		$queue            = [];

		try {
			if ( $run_standard ) {
				$standard_results = self::run_standard_flow(
					$post_id,
					$context,
					$pt_config,
					$timezone,
					$options['standard_schedule_at'] ?? $base_dt,
					$options['captions'] ?? null,
					$queue
				);
			}

			if ( $run_gmb ) {
				try {
					$gmb_results = self::run_gmb_flow(
						$post_id,
						$context,
						$timezone,
						$options['gmb_schedule_at'] ?? null,
						$options['gmb_caption'] ?? null,
						$queue
					);
				} catch ( \RuntimeException $e ) {
					if ( empty( $standard_results ) ) {
						throw $e;
					}
					// The standard posts are already in Postly — record the GMB failure
					// (caption generation, before anything was sent) rather than lose them from the log.
					error_log( self::LOG_PREFIX . ' GMB flow failed after the standard posts were sent: ' . $e->getMessage() );
					$gmb_results = [ [
						'platform'  => 'gmb',
						'slot'      => 1,
						'scheduled' => '',
						'attempts'  => 0,
						'status'    => 'error',
						'error'     => self::clip( $e->getMessage() ),
					] ];
				}
			}
		} catch ( \RuntimeException $e ) {
			// Captions are generated before anything is sent, so nothing from this run
			// reached Postly — put the previous run's queue back as it was.
			if ( $previous_queue ) {
				error_log( self::LOG_PREFIX . " Run failed before sending anything — restoring {$discarded} queued retry slot(s)." );
				self::save_retry_queue( $post_id, $previous_queue );
				self::schedule_retry( $post_id, $previous_queue );
			}
			throw $e;
		}

		// ── 7. Queue failed slots; schedule a retry for temporary failures ──
		self::save_retry_queue( $post_id, $queue );
		$retry_at = self::schedule_retry( $post_id, $queue );

		// ── 8. Log results ───────────────────────────────────────────────────
		$log_entry = [
			'post_id'         => $post_id,
			'ran_at'          => current_time( 'mysql' ),
			'trigger'         => sanitize_key( (string) ( $options['trigger'] ?? 'run' ) ),
			'shortlink'       => $shortlink,
			'posts'           => $standard_results, // Back-compat with existing UI expectations.
			'standard_posts'  => $standard_results,
			'gmb_posts'       => $gmb_results,
		];
		$log_entry = self::with_status( $log_entry, $queue, $retry_at );

		$previous = self::get_log( $post_id );
		if ( ! empty( $previous ) ) {
			$log_entry['previous_run'] = self::describe_previous_run( $previous, $discarded );
		}

		self::write_log( $post_id, $log_entry );

		error_log( self::LOG_PREFIX . " ── Run complete for post #{$post_id}: {$log_entry['outcome']} ({$log_entry['scheduled_count']} of {$log_entry['slot_count']} scheduled) ──" );

		return $log_entry;
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Retry
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * WP-Cron callback for RETRY_HOOK.
	 *
	 * @param int|string $post_id
	 */
	public static function handle_retry_event( $post_id ): void {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		try {
			self::retry_failed_slots( $post_id, false );
		} catch ( \Throwable $e ) {
			error_log( self::LOG_PREFIX . " Scheduled retry for post #{$post_id} stopped: " . $e->getMessage() );
		}
	}

	/**
	 * Resend the slots that failed in the post's last run — nothing else.
	 *
	 * Automatic retries (cron) only resend temporary failures still under
	 * MAX_AUTO_ATTEMPTS. A manual retry resends every queued slot, including ones
	 * that failed permanently (fix the cause first — a channel, a setting).
	 * Slots whose time has passed, or is under an hour away, are moved forward.
	 *
	 * @return array The updated log entry, plus `retried` (slots attempted this call).
	 * @throws \RuntimeException When another retry for the post is still running.
	 */
	public static function retry_failed_slots( int $post_id, bool $manual = false ): array {
		$queue = self::get_retry_queue( $post_id );
		if ( empty( $queue ) ) {
			return self::get_log( $post_id ) + [ 'retried' => 0 ];
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			error_log( self::LOG_PREFIX . " Post #{$post_id} is no longer published — dropping " . count( $queue ) . ' queued slot(s).' );
			self::clear_retry_queue( $post_id );
			return self::get_log( $post_id ) + [ 'retried' => 0 ];
		}

		if ( ! self::acquire_retry_lock( $post_id ) ) {
			if ( ! $manual ) {
				// Another retry is mid-flight — look again shortly rather than drop this one.
				// (When that retry finishes it re-plans the event anyway.)
				wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::RETRY_HOOK, [ $post_id ] );
				return self::get_log( $post_id ) + [ 'retried' => 0 ];
			}
			throw new \RuntimeException( __( 'A retry for this post is already running. Try again in a few minutes.', 'site-essentials' ) );
		}

		try {
			// Manual retries run now; the cron event is re-planned from what's left.
			wp_clear_scheduled_hook( self::RETRY_HOOK, [ $post_id ] );

			$trigger = $manual ? 'manual-retry' : 'auto-retry';
			error_log( self::LOG_PREFIX . " ── {$trigger} for post #{$post_id}: " . count( $queue ) . ' queued slot(s) ──' );

			$timezone     = get_option( 'timezone_string', 'UTC' ) ?: 'UTC';
			$api_key      = (string) get_option( 'bw_postly_api_key', '' );
			$workspace_id = (string) get_option( 'bw_postly_workspace_id', '' );
			$std_channels = self::get_standard_channel_ids();
			$gmb_channel  = self::get_gmb_channel_id();
			$std_client   = new Postly_Client( $api_key, $workspace_id, $std_channels );
			$gmb_client   = new Postly_Client( $api_key, $workspace_id );

			$remaining = [];
			$rows      = [];

			foreach ( $queue as $item ) {
				$due = $manual || ( ! empty( $item['retryable'] ) && (int) ( $item['attempts'] ?? 0 ) < self::MAX_AUTO_ATTEMPTS );
				if ( ! $due ) {
					$remaining[] = $item;
					continue;
				}

				$is_gmb = 'gmb' === ( $item['platform'] ?? '' );
				if ( ( $is_gmb && '' === $gmb_channel ) || ( ! $is_gmb && empty( $std_channels ) ) ) {
					$attempt = self::slot_failed(
						self::slot_row( $item, (int) ( $item['attempts'] ?? 0 ) ),
						$item,
						new \RuntimeException( __( 'No Postly channel is configured for this platform any more.', 'site-essentials' ) ),
						(int) ( $item['attempts'] ?? 0 )
					);
				} else {
					$item['schedule'] = self::retry_schedule( (string) ( $item['schedule'] ?? '' ), $is_gmb, $timezone )->format( 'Y-m-d H:i' );
					$attempt          = $is_gmb
						? self::attempt_gmb_slot( $gmb_client, $item, $gmb_channel, $timezone )
						: self::attempt_standard_slot( $std_client, $item, $timezone );
				}

				$rows[] = $attempt['row'];
				if ( null !== $attempt['queue'] ) {
					$remaining[] = $attempt['queue'];
				}
			}

			self::save_retry_queue( $post_id, $remaining );
			$retry_at = self::schedule_retry( $post_id, $remaining );
			$entry    = self::apply_retry_to_log( $post_id, $rows, $trigger, $remaining, $retry_at );

			error_log( self::LOG_PREFIX . " ── {$trigger} done for post #{$post_id}: {$entry['outcome']}, " . count( $remaining ) . ' slot(s) still queued ──' );

			return $entry + [ 'retried' => count( $rows ) ];
		} finally {
			delete_post_meta( $post_id, self::RETRY_LOCK_META );
		}
	}

	/**
	 * Queued slots for a post.
	 *
	 * @return array[]
	 */
	public static function get_retry_queue( int $post_id ): array {
		$queue = get_post_meta( $post_id, self::RETRY_QUEUE_META, true );
		return is_array( $queue ) ? array_values( $queue ) : [];
	}

	private static function save_retry_queue( int $post_id, array $queue ): void {
		if ( empty( $queue ) ) {
			delete_post_meta( $post_id, self::RETRY_QUEUE_META );
			return;
		}
		update_post_meta( $post_id, self::RETRY_QUEUE_META, wp_slash( array_values( $queue ) ) );
	}

	private static function clear_retry_queue( int $post_id ): void {
		delete_post_meta( $post_id, self::RETRY_QUEUE_META );
		wp_clear_scheduled_hook( self::RETRY_HOOK, [ $post_id ] );
	}

	private static function acquire_retry_lock( int $post_id ): bool {
		$held_since = (int) get_post_meta( $post_id, self::RETRY_LOCK_META, true );
		if ( $held_since && ( time() - $held_since ) < self::LOCK_MINUTES * MINUTE_IN_SECONDS ) {
			return false;
		}
		if ( $held_since ) {
			delete_post_meta( $post_id, self::RETRY_LOCK_META );
		}
		return (bool) add_post_meta( $post_id, self::RETRY_LOCK_META, time(), true );
	}

	/**
	 * Schedule the next automatic retry if any queued slot is still worth one.
	 * Waits at least as long as Postly asked, backing off 2, 4, 6 minutes per round.
	 *
	 * @return int Unix time of the retry, or 0 when none is scheduled.
	 */
	private static function schedule_retry( int $post_id, array $queue ): int {
		wp_clear_scheduled_hook( self::RETRY_HOOK, [ $post_id ] );

		$wait     = 0;
		$attempts = 0;
		foreach ( $queue as $item ) {
			if ( empty( $item['retryable'] ) || (int) ( $item['attempts'] ?? 0 ) >= self::MAX_AUTO_ATTEMPTS ) {
				continue;
			}
			$wait     = max( $wait, (int) ( $item['retry_after'] ?? 0 ) );
			$attempts = max( $attempts, (int) ( $item['attempts'] ?? 0 ) );
		}
		if ( 0 === $attempts ) {
			return 0;
		}

		$at        = time() + max( $wait, 2 * MINUTE_IN_SECONDS * $attempts ) + wp_rand( 0, 30 );
		$scheduled = wp_schedule_single_event( $at, self::RETRY_HOOK, [ $post_id ] );
		if ( true !== $scheduled ) {
			error_log( self::LOG_PREFIX . " Could not schedule the retry for post #{$post_id} — use Retry failed posts." );
			return 0;
		}

		error_log( self::LOG_PREFIX . " Retry for post #{$post_id} scheduled for " . wp_date( 'Y-m-d H:i:s', $at ) . ' (site time).' );
		return $at;
	}

	/**
	 * Where a retried slot should land: its original time if that's still more
	 * than an hour away, otherwise the next slot in the publish window (GMB: an hour from now).
	 */
	private static function retry_schedule( string $original, bool $is_gmb, string $timezone ): \DateTimeImmutable {
		$tz   = new \DateTimeZone( $timezone );
		$now  = new \DateTimeImmutable( 'now', $tz );
		$soon = $now->modify( '+60 minutes' );
		$dt   = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $original, $tz );

		if ( $dt && $dt > $soon ) {
			return $dt;
		}
		return $is_gmb ? $soon : self::first_slot_time( $now, $now, self::publish_window() );
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Slot attempts — shared by the first run and every retry
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Upload a standard slot's images and create its post.
	 *
	 * @param  array $item Queue item: slot, caption_key, caption, source_images, schedule, attempts.
	 * @return array{row: array, queue: ?array} The log row; the queue item when it failed.
	 */
	private static function attempt_standard_slot( Postly_Client $client, array $item, string $timezone ): array {
		$attempts = (int) ( $item['attempts'] ?? 0 ) + 1;
		$sources  = array_values( (array) ( $item['source_images'] ?? [] ) );
		$uploaded = [];

		error_log( self::LOG_PREFIX . " Standard slot {$item['slot']}: scheduled {$item['schedule']} | attempt {$attempts} | " . count( $sources ) . ' image(s)' );

		try {
			$uploaded = self::upload_images( $client, $sources );
			error_log( self::LOG_PREFIX . ' Standard uploaded ' . count( $uploaded ) . ' of ' . count( $sources ) . ' images to Postly CDN' );

			$result = $client->create_post( [
				'text'        => (string) ( $item['caption'] ?? '' ),
				'media_urls'  => $uploaded,
				'schedule_at' => self::to_datetime( (string) $item['schedule'], $timezone ),
				'timezone'    => $timezone,
			] );

			return self::slot_succeeded( self::slot_row( $item, $attempts ) + [ 'images' => count( $uploaded ) ], $item, $result );
		} catch ( \RuntimeException $e ) {
			return self::slot_failed( self::slot_row( $item, $attempts ) + [ 'images' => count( $uploaded ) ], $item, $e, $attempts );
		}
	}

	/**
	 * Upload the GMB image (optional) and create the GMB post.
	 *
	 * @param  array $item Queue item: gmb_caption, cta_url, source_image, schedule, attempts.
	 * @return array{row: array, queue: ?array}
	 */
	private static function attempt_gmb_slot( Postly_Client $client, array $item, string $gmb_channel_id, string $timezone ): array {
		$attempts  = (int) ( $item['attempts'] ?? 0 ) + 1;
		$source    = Postly_Image_Guard::safe_url( (string) ( $item['source_image'] ?? '' ) ) ?? '';
		$image_url = '';

		if ( '' !== $source ) {
			try {
				$file_name = basename( (string) wp_parse_url( $source, PHP_URL_PATH ) ) ?: 'gmb-image.jpg';
				$image_url = $client->upload_image( $source, $file_name );
				error_log( self::LOG_PREFIX . ' GMB image uploaded to Postly CDN: ' . $image_url );
			} catch ( \RuntimeException $e ) {
				error_log( self::LOG_PREFIX . ' GMB image upload failed (continuing without image): ' . $e->getMessage() );
			}
		}

		try {
			$result = $client->create_gmb_post( [
				'gmb_caption'    => (string) ( $item['gmb_caption'] ?? '' ),
				'cta_url'        => (string) ( $item['cta_url'] ?? '' ),
				'image_url'      => $image_url,
				'schedule_at'    => self::to_datetime( (string) $item['schedule'], $timezone ),
				'timezone'       => $timezone,
				'gmb_channel_id' => $gmb_channel_id,
			] );

			return self::slot_succeeded( self::slot_row( $item, $attempts ) + [ 'images' => $image_url ? 1 : 0 ], $item, $result );
		} catch ( \RuntimeException $e ) {
			return self::slot_failed( self::slot_row( $item, $attempts ) + [ 'images' => $image_url ? 1 : 0 ], $item, $e, $attempts );
		}
	}

	private static function slot_row( array $item, int $attempts ): array {
		$row = [
			'platform'  => 'gmb' === ( $item['platform'] ?? '' ) ? 'gmb' : 'standard',
			'slot'      => (int) ( $item['slot'] ?? 1 ),
			'scheduled' => (string) ( $item['schedule'] ?? '' ),
		];
		if ( 'standard' === $row['platform'] ) {
			$row['caption_key'] = (string) ( $item['caption_key'] ?? '' );
		}
		$row['attempts'] = $attempts;
		return $row;
	}

	/** @return array{row: array, queue: null} */
	private static function slot_succeeded( array $row, array $item, array $response ): array {
		$row['status']    = 'scheduled';
		$row['postly_id'] = Postly_Client::extract_post_id( $response );

		if ( ! empty( $item['uncertain'] ) ) {
			$row['note'] = sprintf(
				/* translators: %s: original schedule date/time */
				__( 'An earlier attempt timed out or hit a gateway error, so Postly may already hold a copy (originally for %s). Delete one if you see two.', 'site-essentials' ),
				(string) ( $item['original_schedule'] ?? $item['schedule'] ?? '' )
			);
		}

		error_log( self::LOG_PREFIX . sprintf(
			' %s slot %d scheduled for %s%s',
			$row['platform'],
			$row['slot'],
			$row['scheduled'],
			$row['postly_id'] ? " (Postly ID {$row['postly_id']})" : ''
		) );

		return [ 'row' => $row, 'queue' => null ];
	}

	/** @return array{row: array, queue: array} */
	private static function slot_failed( array $row, array $item, \RuntimeException $e, int $attempts ): array {
		$retryable = $e instanceof Postly_Exception && $e->is_retryable();
		$uncertain = ( $e instanceof Postly_Exception && $e->is_uncertain() ) || ! empty( $item['uncertain'] );
		$auto      = $retryable && $attempts < self::MAX_AUTO_ATTEMPTS;
		$message   = self::clip( $e->getMessage() );

		$row['status'] = $auto ? 'retry_scheduled' : 'error';
		$row['error']  = $message;
		if ( $uncertain ) {
			$row['note'] = __( 'Timed out or hit a gateway error — Postly may have created this post anyway. Check Postly before resending.', 'site-essentials' );
		}

		error_log( self::LOG_PREFIX . sprintf(
			' %s slot %d failed (attempt %d, %s): %s',
			$row['platform'],
			$row['slot'],
			$attempts,
			$auto ? 'will retry' : ( $retryable ? 'automatic retries used up' : 'not retryable' ),
			$message
		) );

		$item['attempts']          = $attempts;
		$item['retryable']         = $retryable;
		$item['retry_after']       = $e instanceof Postly_Exception ? $e->retry_after() : 0;
		$item['uncertain']         = $uncertain;
		$item['original_schedule'] = $item['original_schedule'] ?? ( $item['schedule'] ?? '' );
		$item['last_error']        = $message;

		return [ 'row' => $row, 'queue' => $item ];
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Schedule helpers
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * The configured publish window (defaults 09:00–17:00 site time).
	 *
	 * @return array{min: int, max: int, label: string} Minutes after midnight.
	 */
	private static function publish_window(): array {
		$time_min_str = (string) get_option( 'bw_social_publish_time_min', '09:00' );
		$time_max_str = (string) get_option( 'bw_social_publish_time_max', '17:00' );
		[ $min_h, $min_m ] = array_map( 'intval', explode( ':', $time_min_str . ':00' ) );
		[ $max_h, $max_m ] = array_map( 'intval', explode( ':', $time_max_str . ':00' ) );
		$min_minutes = $min_h * 60 + $min_m;

		return [
			'min'   => $min_minutes,
			'max'   => max( $max_h * 60 + $max_m, $min_minutes + 1 ),
			'label' => "{$time_min_str}–{$time_max_str}",
		];
	}

	/**
	 * A random in-window time on $day — pushed to a random in-window time
	 * tomorrow when that's not at least 60 minutes from now (API processing
	 * and human approval buffer).
	 */
	private static function first_slot_time( \DateTimeImmutable $day, \DateTimeImmutable $now, array $window ): \DateTimeImmutable {
		$pick = static function ( \DateTimeImmutable $d ) use ( $window ): \DateTimeImmutable {
			$t = mt_rand( $window['min'], $window['max'] );
			return $d->setTime( intdiv( $t, 60 ), $t % 60, 0 );
		};

		$dt = $pick( $day );
		if ( $dt <= $now->modify( '+60 minutes' ) ) {
			$dt = $pick( $now->modify( '+1 day' ) );
		}
		return $dt;
	}

	private static function to_datetime( string $schedule, string $timezone ): \DateTimeImmutable {
		$tz = new \DateTimeZone( $timezone );
		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $schedule, $tz );
		return $dt ?: new \DateTimeImmutable( 'now', $tz );
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Helpers
	// ──────────────────────────────────────────────────────────────────────────

	private static function get_excerpt( \WP_Post $post ): string {
		if ( $post->post_excerpt ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}
		return wp_trim_words( wp_strip_all_tags( $post->post_content ), 40, '...' );
	}

	/**
	 * @deprecated Replaced by per-post-type config from Post_Type_Config::get_config().
	 *             Retained for external callers that may still reference this method.
	 */
	private static function get_platform_configs(): array {
		return [
			'standard' => [
				'count'    => max( 1, (int) get_option( 'scos_sa_postly_post_count', 3 ) ),
				'gap_days' => 42,
				'adapter'  => 'standard',
			],
			'gmb'      => [
				'count'    => 1,
				'gap_days' => 0,
				'adapter'  => 'gmb',
			],
		];
	}

	/**
	 * @param array $pt_config  Resolved config from Post_Type_Config::get_config().
	 *                          Keys: post_count, frames, no_featured, no_attachments,
	 *                          acf_gallery_keys, max_images.
	 * @param array $queue      Failed slots are appended here for retry.
	 */
	private static function run_standard_flow(
		int $post_id,
		array $context,
		array $pt_config,
		string $timezone,
		\DateTimeImmutable $base_dt,
		?array $preset_captions,
		array &$queue
	): array {
		$channel_ids = self::get_standard_channel_ids();

		if ( empty( $channel_ids ) ) {
			error_log( self::LOG_PREFIX . ' Standard not configured: no channel IDs found. Skipping standard flow.' );
			return [];
		}

		$api_key      = get_option( 'bw_postly_api_key', '' );
		$workspace_id = get_option( 'bw_postly_workspace_id', '' );
		$post_count   = max( 1, (int) ( $pt_config['post_count'] ?? 3 ) );
		$gap_days     = 42;
		$frames       = $pt_config['frames'] ?? [];
		error_log( self::LOG_PREFIX . " Standard flow workspace: {$workspace_id} | channels: " . implode( ',', $channel_ids ) . " | count: {$post_count} | tz: {$timezone}" );

		$all_images = self::collect_images( $post_id, $pt_config );
		error_log( self::LOG_PREFIX . ' Standard images collected: ' . count( $all_images ) . ' — ' . implode( ', ', array_map( 'basename', $all_images ) ) );
		$image_sets = self::build_image_sets( $all_images, $post_count, (int) ( $pt_config['max_images'] ?? self::IMAGES_PER_POST ) );

		// Captions supplied by the caller (agent-authored) are used as-is; otherwise
		// generate them through the WP AI Client. See CLAUDE.md § 6 — hybrid ability.
		if ( ! empty( $preset_captions ) ) {
			error_log( self::LOG_PREFIX . ' Using ' . count( $preset_captions ) . ' caller-supplied standard captions (no AI call).' );
			$captions = $preset_captions;
		} else {
			error_log( self::LOG_PREFIX . " Generating {$post_count} standard captions…" );
			$captions = Caption_Generator::generate_captions( $context, $frames, $post_count );
		}

		$client = new Postly_Client( $api_key, $workspace_id, $channel_ids );

		$post_results = [];

		for ( $i = 0; $i < $post_count; $i++ ) {
			$offset_days = $gap_days * $i;
			$caption_key = 'post_' . ( $i + 1 );

			// Everything needed to resend this slot later, should it fail.
			$item = [
				'platform'      => 'standard',
				'slot'          => $i + 1,
				'caption_key'   => $caption_key,
				'caption'       => (string) ( $captions[ $caption_key ] ?? '' ),
				'source_images' => array_values( $image_sets[ $i ] ?? [] ),
				'schedule'      => $base_dt->modify( "+{$offset_days} days" )->format( 'Y-m-d H:i' ),
				'attempts'      => 0,
			];

			$attempt        = self::attempt_standard_slot( $client, $item, $timezone );
			$post_results[] = $attempt['row'];
			if ( null !== $attempt['queue'] ) {
				$queue[] = $attempt['queue'];
			}
		}

		return $post_results;
	}

	/**
	 * @param array $queue Failed slots are appended here for retry.
	 */
	private static function run_gmb_flow(
		int $post_id,
		array $context,
		string $timezone,
		?\DateTimeImmutable $base_dt,
		?string $preset_caption,
		array &$queue
	): array {
		$gmb_channel_id = self::get_gmb_channel_id();
		if ( '' === $gmb_channel_id ) {
			error_log( self::LOG_PREFIX . ' GMB not configured: scos_sa_postly_gmb_channel_id / se_postly_gmb_channel_id is blank. Skipping GMB flow.' );
			return [];
		}

		$api_key      = get_option( 'bw_postly_api_key', '' );
		$workspace_id = get_option( 'bw_postly_workspace_id', '' );
		$client       = new Postly_Client( $api_key, $workspace_id );
		$now          = new \DateTimeImmutable( 'now', new \DateTimeZone( $timezone ) );

		$schedule_dt = $base_dt ?: $now->modify( '+60 minutes' );
		if ( $schedule_dt <= $now->modify( '+60 minutes' ) ) {
			$schedule_dt = $now->modify( '+60 minutes' );
		}

		$source_image = self::get_featured_og_image( $post_id );
		$permalink    = $context['permalink'] ?? '';
		// Caller-supplied caption (agent-authored) wins; otherwise generate. See CLAUDE.md § 6.
		if ( null !== $preset_caption && '' !== trim( $preset_caption ) ) {
			error_log( self::LOG_PREFIX . ' Using caller-supplied GMB caption (no AI call).' );
			$gmb_caption = trim( $preset_caption );
		} else {
			$gmb_caption = Caption_Generator::generate_gmb_caption( $context );
		}
		// GMB CTA always uses the permalink (never YOURLS shortlink).
		$cta_url      = self::build_cta_url( $permalink, '' );

		// GMB is always one post per amplification run (no multi-slot schedule like standard).
		$item = [
			'platform'     => 'gmb',
			'slot'         => 1,
			'gmb_caption'  => $gmb_caption,
			'cta_url'      => $cta_url,
			'source_image' => $source_image,
			'schedule'     => $schedule_dt->format( 'Y-m-d H:i' ),
			'attempts'     => 0,
		];

		$attempt = self::attempt_gmb_slot( $client, $item, $gmb_channel_id, $timezone );
		if ( null !== $attempt['queue'] ) {
			$queue[] = $attempt['queue'];
		}

		return [ $attempt['row'] ];
	}

	private static function get_shortlink( int $post_id, string $fallback ): string {
		if ( ! class_exists( '\BW_YOURLS_Helper' ) ) {
			return $fallback;
		}

		// Slug stored on the post (scos_sa_shortlink_slug or legacy bw_shortlink_slug)
		$slug = get_post_meta( $post_id, 'scos_sa_shortlink_slug', true )
			?: get_post_meta( $post_id, 'bw_shortlink_slug', true );

		if ( ! $slug ) {
			return $fallback;
		}

		// Build destination URL: permalink + UTM params.
		// YOURLS stores this as the long URL the shortlink resolves to.
		$long_url = add_query_arg( [
			'utm_source'   => 'social_media',
			'utm_medium'   => 'social',
			'utm_content'  => 'case-study_link',
			'utm_campaign' => 'none',
		], get_permalink( $post_id ) );

		try {
			// create_shortlink( $long_url, $keyword ) — long URL first, slug/keyword second.
			$result = \BW_YOURLS_Helper::create_shortlink( $long_url, $slug );
			if ( is_wp_error( $result ) ) {
				error_log( self::LOG_PREFIX . ' YOURLS WP_Error: ' . $result->get_error_message() );
			} elseif ( is_array( $result ) && ! empty( $result['shorturl'] ) ) {
				return $result['shorturl'];
			}
		} catch ( \Exception $e ) {
			error_log( self::LOG_PREFIX . ' YOURLS exception: ' . $e->getMessage() );
		}

		return $fallback;
	}

	private static function build_cta_url( string $permalink, string $shortlink ): string {
		$base_url = $shortlink ?: $permalink;
		if ( ! $base_url ) {
			return '';
		}

		$query = wp_parse_url( $base_url, PHP_URL_QUERY );
		if ( is_string( $query ) && $query !== '' ) {
			parse_str( $query, $existing_params );
			$utm_keys = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content' ];
			foreach ( $utm_keys as $utm_key ) {
				if ( ! empty( $existing_params[ $utm_key ] ) ) {
					error_log( self::LOG_PREFIX . " CTA URL already has {$utm_key}; skipping duplicate UTM injection." );
					return $base_url;
				}
			}
		}

		return add_query_arg( [
			'utm_source'   => 'social_media',
			'utm_medium'   => 'social',
			'utm_content'  => 'gmb_learn_more',
			'utm_campaign' => 'none',
		], $base_url );
	}

	private static function get_featured_og_image( int $post_id ): string {
		$featured_id = get_post_thumbnail_id( $post_id );
		if ( ! $featured_id ) {
			return '';
		}

		$og = wp_get_attachment_image_url( $featured_id, 'og-image' );
		if ( $og ) {
			return $og;
		}

		$sized = wp_get_attachment_image_url( $featured_id, [ 1200, 630 ] );
		if ( $sized ) {
			return $sized;
		}

		$full = wp_get_attachment_image_url( $featured_id, 'full' );
		return $full ?: '';
	}

	/**
	 * Collect all candidate images for a post using the per-type config.
	 *
	 * Config keys used:
	 *   no_featured      — skip the WP featured image
	 *   no_attachments   — skip post image attachments (not currently fetched by default,
	 *                      but governs future attachment-scan logic)
	 *   acf_gallery_keys — comma-separated ACF field keys (overrides global option when
	 *                      set to a non-empty string in per-type config)
	 *
	 * Falls back gracefully: if ACF gallery key doesn't exist on this post type the key
	 * simply returns empty and the engine continues with whatever images it found.
	 *
	 * @param  int   $post_id
	 * @param  array $config  Resolved config from Post_Type_Config::get_config().
	 * @return string[]
	 */
	private static function collect_images( int $post_id, array $config = [] ): array {
		$urls         = [];
		$no_featured  = ! empty( $config['no_featured'] );
		$acf_keys_raw = isset( $config['acf_gallery_keys'] )
			? (string) $config['acf_gallery_keys']
			: (string) get_option( 'bw_social_acf_gallery_keys', '' );

		// Featured image (unless disabled)
		if ( ! $no_featured ) {
			$featured_id = get_post_thumbnail_id( $post_id );
			if ( $featured_id ) {
				// Check for custom ACF key override (global option, not per-type)
				$featured_key = get_option( 'bw_social_acf_featured_key', '' );
				if ( $featured_key ) {
					$acf_featured = get_post_meta( $post_id, $featured_key, true );
					if ( $acf_featured ) {
						$url = is_array( $acf_featured ) ? ( $acf_featured['url'] ?? '' ) : (string) $acf_featured;
						if ( $url ) {
							$urls[] = $url;
						}
					}
				}
				if ( empty( $urls ) ) {
					$src = wp_get_attachment_image_url( $featured_id, 'large' );
					if ( $src ) {
						$urls[] = $src;
					}
				}
			}
		}

		// ACF gallery fields
		if ( $acf_keys_raw ) {
			$gallery_keys = array_filter( array_map( 'trim', explode( ',', $acf_keys_raw ) ) );
			foreach ( $gallery_keys as $key ) {
				$gallery = get_post_meta( $post_id, $key, true );
				if ( ! $gallery ) {
					continue; // Key absent or empty — fail gracefully
				}
				if ( is_array( $gallery ) ) {
					foreach ( $gallery as $img ) {
						if ( is_array( $img ) ) {
							$url = $img['url'] ?? '';
						} elseif ( is_numeric( $img ) ) {
							$url = wp_get_attachment_image_url( (int) $img, 'large' ) ?: '';
						} else {
							$url = (string) $img;
						}
						if ( $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
							$urls[] = $url;
						}
					}
				}
			}
		}

		// Deduplicate while preserving order
		return array_values( array_unique( $urls ) );
	}

	/**
	 * Divide collected images into N sets for N social posts.
	 *
	 * - ≥ (max_per_post + 1) images: vary sets across posts.
	 * - Fewer images: all posts share the same set.
	 *
	 * @param  string[] $images
	 * @param  int      $post_count   Number of social posts being created.
	 * @param  int      $max_per_post Max images per post slot.
	 * @return array[]  $post_count-element array of image URL sets.
	 */
	private static function build_image_sets( array $images, int $post_count = 3, int $max_per_post = self::IMAGES_PER_POST ): array {
		$max_per_post = max( 1, $max_per_post );
		$n            = count( $images );
		$sets         = [];

		if ( $n >= ( $max_per_post + 1 ) ) {
			// Enough images to vary: post 1 = first N, post 2 = last N, rest = random N.
			$sets[0] = array_slice( $images, 0, $max_per_post );
			$sets[1] = array_slice( $images, -$max_per_post );
			$pool    = $images;
			shuffle( $pool );
			$random = array_slice( $pool, 0, $max_per_post );
			for ( $i = 0; $i < $post_count; $i++ ) {
				if ( ! isset( $sets[ $i ] ) ) {
					$sets[ $i ] = $random;
				}
			}
		} else {
			$base = array_slice( $images, 0, $max_per_post );
			for ( $i = 0; $i < $post_count; $i++ ) {
				$sets[ $i ] = $base;
			}
		}

		return $sets;
	}

	/**
	 * Upload each image in the set to Postly CDN.
	 * Skips images that fail to upload (logs warning, does not throw).
	 * Each URL goes through Postly_Image_Guard first, so a queued slot that
	 * failed on a WebP gets the JPEG copy when it's resent.
	 *
	 * @param  Postly_Client $client
	 * @param  string[]      $image_urls
	 * @return string[]      Uploaded CDN URLs.
	 */
	private static function upload_images( Postly_Client $client, array $image_urls ): array {
		$uploaded = [];
		foreach ( $image_urls as $url ) {
			$url = Postly_Image_Guard::safe_url( (string) $url );
			if ( null === $url ) {
				continue; // The guard logged why.
			}
			try {
				$uploaded[] = $client->upload_image( $url );
			} catch ( \RuntimeException $e ) {
				// Non-fatal: log and continue
				error_log( "[SCOS SMA] Failed to upload image {$url}: " . $e->getMessage() );
			}
		}
		return $uploaded;
	}

	/**
	 * Detect content type for the prompt context.
	 * Uses BW_Content_Type_Helper if available, otherwise falls back to post_type.
	 */
	private static function get_content_type( \WP_Post $post ): string {
		if ( class_exists( '\BW_Content_Type_Helper' ) ) {
			try {
				return (string) \BW_Content_Type_Helper::get_content_type( $post->ID );
			} catch ( \Exception $e ) {
				// Fall through
			}
		}
		return $post->post_type;
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Log
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Store a new run's entry. The entry it replaces moves to the post's run
	 * history, so a re-run never loses what the earlier run scheduled.
	 */
	private static function write_log( int $post_id, array $entry ): void {
		$previous = self::get_log( $post_id );
		if ( ! empty( $previous ) ) {
			$history = self::get_history( $post_id );
			array_unshift( $history, $previous );
			update_post_meta( $post_id, self::HISTORY_META, wp_slash( array_slice( $history, 0, self::HISTORY_LIMIT ) ) );
		}
		self::store_log_entry( $post_id, $entry );
	}

	/** Replace the post's current entry in place (no history). */
	private static function store_log_entry( int $post_id, array $entry ): void {
		$log = get_option( self::LOG_OPTION, [] );
		if ( ! is_array( $log ) ) {
			$log = [];
		}
		// Keep last 200 entries to prevent unbounded growth. Re-adding moves the
		// post to the end, so the posts trimmed are the least recently run.
		unset( $log[ $post_id ] );
		$log[ $post_id ] = $entry;
		if ( count( $log ) > 200 ) {
			$log = array_slice( $log, -200, null, true );
		}
		update_option( self::LOG_OPTION, $log, false );
	}

	/**
	 * Earlier log entries for a post, newest first.
	 *
	 * @return array[]
	 */
	public static function get_history( int $post_id ): array {
		$history = get_post_meta( $post_id, self::HISTORY_META, true );
		return is_array( $history ) ? array_values( $history ) : [];
	}

	/**
	 * Fold retry results into the current entry: replace each retried slot's row,
	 * record the retry as an event, and recompute the outcome.
	 */
	private static function apply_retry_to_log( int $post_id, array $rows, string $trigger, array $queue, int $retry_at ): array {
		$entry = self::get_log( $post_id );
		if ( empty( $entry ) ) {
			$entry = [ 'post_id' => $post_id, 'ran_at' => current_time( 'mysql' ), 'trigger' => $trigger, 'standard_posts' => [], 'gmb_posts' => [] ];
		}

		foreach ( $rows as $row ) {
			$key   = 'gmb' === $row['platform'] ? 'gmb_posts' : 'standard_posts';
			$list  = array_values( (array) ( $entry[ $key ] ?? [] ) );
			$found = false;
			foreach ( $list as $i => $existing ) {
				if ( (int) ( $existing['slot'] ?? 0 ) === (int) $row['slot'] ) {
					$list[ $i ] = $row;
					$found      = true;
					break;
				}
			}
			if ( ! $found ) {
				$list[] = $row;
			}
			$entry[ $key ] = $list;
		}
		$entry['posts'] = $entry['standard_posts'] ?? [];

		$words   = [ 'scheduled' => 'scheduled', 'retry_scheduled' => 'failed, will retry', 'error' => 'failed' ];
		$summary = array_map( static function ( array $row ) use ( $words ): string {
			return sprintf(
				'%s #%d %s%s',
				'gmb' === $row['platform'] ? 'Google Business' : 'Social',
				$row['slot'],
				$words[ $row['status'] ] ?? $row['status'],
				'scheduled' === $row['status'] ? ' for ' . $row['scheduled'] : ''
			);
		}, $rows );

		$events   = (array) ( $entry['events'] ?? [] );
		$events[] = [
			'at'      => current_time( 'mysql' ),
			'trigger' => $trigger,
			'summary' => $summary ? implode( '; ', $summary ) : 'nothing due',
		];
		$entry['events'] = array_slice( $events, -self::HISTORY_LIMIT );

		$entry = self::with_status( $entry, $queue, $retry_at );
		self::store_log_entry( $post_id, $entry );
		return $entry;
	}

	/** Add outcome, counts and retry state to a log entry. */
	private static function with_status( array $entry, array $queue, int $retry_at ): array {
		$counts = self::slot_counts( $entry );

		$entry['outcome']         = self::outcome_of( $entry );
		$entry['slot_count']      = $counts['total'];
		$entry['scheduled_count'] = $counts['scheduled'];
		$entry['queued_count']    = count( $queue );
		$entry['retry_at']        = $retry_at ? wp_date( 'Y-m-d H:i', $retry_at ) : '';
		return $entry;
	}

	/**
	 * What a re-run replaced — so the log shows posts from the earlier run are
	 * still sitting in Postly alongside the new ones.
	 */
	private static function describe_previous_run( array $previous, int $discarded_retries ): array {
		$rows = array_merge( (array) ( $previous['standard_posts'] ?? $previous['posts'] ?? [] ), (array) ( $previous['gmb_posts'] ?? [] ) );
		$kept = array_values( array_filter( $rows, static function ( $row ): bool {
			return 'scheduled' === ( $row['status'] ?? '' );
		} ) );

		return [
			'ran_at'            => (string) ( $previous['ran_at'] ?? '' ),
			'outcome'           => self::outcome_of( $previous ),
			'still_scheduled'   => array_map( static function ( array $row ): array {
				return [
					'platform'  => (string) ( $row['platform'] ?? 'standard' ),
					'slot'      => (int) ( $row['slot'] ?? 0 ),
					'scheduled' => (string) ( $row['scheduled'] ?? '' ),
					'postly_id' => $row['postly_id'] ?? null,
				];
			}, $kept ),
			'discarded_retries' => $discarded_retries,
		];
	}

	/**
	 * complete (every slot scheduled), partial, failed (none scheduled) or
	 * skipped (no slots — nothing configured). Works on entries written before v1.5.
	 */
	public static function outcome_of( array $entry ): string {
		$counts = self::slot_counts( $entry );
		if ( 0 === $counts['total'] ) {
			return 'skipped';
		}
		if ( $counts['scheduled'] === $counts['total'] ) {
			return 'complete';
		}
		return $counts['scheduled'] > 0 ? 'partial' : 'failed';
	}

	/** @return array{total: int, scheduled: int} */
	public static function slot_counts( array $entry ): array {
		$rows = array_merge(
			(array) ( $entry['standard_posts'] ?? $entry['posts'] ?? [] ),
			(array) ( $entry['gmb_posts'] ?? [] )
		);
		$scheduled = 0;
		foreach ( $rows as $row ) {
			if ( 'scheduled' === ( $row['status'] ?? '' ) ) {
				$scheduled++;
			}
		}
		return [ 'total' => count( $rows ), 'scheduled' => $scheduled ];
	}

	/**
	 * One or two plain sentences on how a run or retry went, for the meta box
	 * and the ability response.
	 */
	public static function describe_outcome( array $entry ): string {
		if ( isset( $entry['retried'] ) && 0 === (int) $entry['retried'] ) {
			return __( 'Nothing to resend — no failed posts are waiting.', 'site-essentials' );
		}

		$counts  = self::slot_counts( $entry );
		$failed  = $counts['total'] - $counts['scheduled'];
		$outcome = $entry['outcome'] ?? self::outcome_of( $entry );

		switch ( $outcome ) {
			case 'complete':
				$text = sprintf(
					/* translators: %d: number of posts */
					_n( 'The post is scheduled in Postly.', 'All %d posts are scheduled in Postly.', $counts['total'], 'site-essentials' ),
					$counts['total']
				);
				break;
			case 'partial':
				$text = sprintf(
					/* translators: 1: posts scheduled, 2: posts in the run */
					__( '%1$d of %2$d posts scheduled.', 'site-essentials' ),
					$counts['scheduled'],
					$counts['total']
				);
				break;
			case 'failed':
				$text = __( 'No posts were scheduled.', 'site-essentials' );
				break;
			default:
				return __( 'Nothing was scheduled — no Postly channels are configured.', 'site-essentials' );
		}

		if ( $failed > 0 ) {
			$retry_at = (string) ( $entry['retry_at'] ?? '' );
			$text    .= ' ' . ( '' !== $retry_at
				? sprintf(
					/* translators: 1: number of failed posts, 2: time of the automatic retry */
					__( '%1$d failed — retrying automatically at %2$s.', 'site-essentials' ),
					$failed,
					mysql2date( 'g:i a', $retry_at )
				)
				: sprintf(
					/* translators: %d: number of failed posts */
					_n( '%d failed — see the error below.', '%d failed — see the errors below.', $failed, 'site-essentials' ),
					$failed
				) );
		}

		$still = count( (array) ( $entry['previous_run']['still_scheduled'] ?? [] ) );
		if ( $still > 0 && ! isset( $entry['retried'] ) ) {
			$text .= ' ' . sprintf(
				/* translators: %d: posts from the previous run */
				_n( 'The previous run’s post is still in Postly — delete it there if this one replaces it.', 'The previous run’s %d posts are still in Postly — delete any duplicates there.', $still, 'site-essentials' ),
				$still
			);
		}

		return $text;
	}

	private static function clip( string $text, int $max = 300 ): string {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $text ) > $max ? mb_substr( $text, 0, $max - 1 ) . '…' : $text;
		}
		return strlen( $text ) > $max ? substr( $text, 0, $max - 3 ) . '...' : $text;
	}

	/**
	 * Retrieve the log entry for a specific post, or all entries.
	 *
	 * @param  int|null $post_id
	 * @return array
	 */
	public static function get_log( ?int $post_id = null ): array {
		$log = get_option( self::LOG_OPTION, [] );
		if ( ! is_array( $log ) ) {
			return [];
		}
		if ( $post_id !== null ) {
			return $log[ $post_id ] ?? [];
		}
		return $log;
	}
}
