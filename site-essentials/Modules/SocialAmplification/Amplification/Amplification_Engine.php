<?php
/**
 * Amplification Engine
 *
 * Orchestrates the full publish-to-Postly workflow for a single post:
 *  1. Gather post data (title, excerpt, permalink, images)
 *  2. Create YOURLS shortlink (falls back to permalink)
 *  3. Call Anthropic for 3 captions
 *  4. Upload images to Postly CDN
 *  5. Schedule 3 posts (T+0, T+42d, T+84d — Mon/Wed/Fri aligned)
 *  6. Log results to wp_option `scos_sa_amplify_log`
 *
 * Image-selection rules:
 *  - Collect featured image + ACF gallery images, deduplicate.
 *  - If ≥ 5 images: Post 1 = first 4 gallery, Post 2 = last 4 gallery, Post 3 = random 4.
 *  - If < 5 images:  same set for all three posts (up to 4 images each).
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification\Amplification
 */

namespace SiteEssentials\Modules\SocialAmplification\Amplification;

defined( 'ABSPATH' ) || exit;

class Amplification_Engine {

	// Days between each scheduled post
	const POST_INTERVAL_DAYS = 42;

	// Max images per Postly post
	const IMAGES_PER_POST = 4;

	// Option key for the run log (keyed by post_id)
	const LOG_OPTION = 'scos_sa_amplify_log';

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
		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			throw new \RuntimeException( "Post {$post_id} is not published or does not exist." );
		}

		// ── 1. Gather post data ──────────────────────────────────────────────
		$title     = get_the_title( $post );
		$permalink = get_permalink( $post );
		$excerpt   = self::get_excerpt( $post );

		// ── 2. YOURLS shortlink ──────────────────────────────────────────────
		$shortlink = self::get_shortlink( $post_id, $permalink );

		// ── 3. Image selection ──────────────────────────────────────────────
		$all_images   = self::collect_images( $post_id );
		$image_sets   = self::build_image_sets( $all_images );

		// ── 4. Determine content type ────────────────────────────────────────
		$content_type = self::get_content_type( $post );

		// ── 5. Generate captions via Anthropic ──────────────────────────────
		$captions = Anthropic_Client::generate_captions( [
			'post_id'      => $post_id,
			'title'        => $title,
			'excerpt'      => $excerpt,
			'permalink'    => $permalink,
			'shortlink'    => $shortlink,
			'content_type' => $content_type,
		] );

		// ── 6. Build Postly client ───────────────────────────────────────────
		$api_key      = get_option( 'bw_postly_api_key', '' );
		$workspace_id = get_option( 'bw_postly_workspace_id', '' );
		$channel_ids  = array_filter( array_map(
			'trim',
			explode( ',', (string) get_option( 'bw_postly_channel_ids', '' ) )
		) );
		$timezone     = get_option( 'timezone_string', 'UTC' ) ?: 'UTC';

		$client = new Postly_Client( $api_key, $workspace_id, $channel_ids );

		// ── 7. Base schedule time ────────────────────────────────────────────
		$base_dt = $options['schedule_at'] ?? new \DateTimeImmutable( 'now', new \DateTimeZone( $timezone ) );
		// Align to a reasonable posting time (10:00 AM site timezone)
		$base_dt = $base_dt->setTime( 10, 0, 0 );

		// ── 8. Schedule 3 posts ──────────────────────────────────────────────
		$post_results = [];
		$caption_keys = [ 'post_1', 'post_2', 'post_3' ];

		for ( $i = 0; $i < 3; $i++ ) {
			$offset_days = self::POST_INTERVAL_DAYS * $i;
			$schedule_dt = $base_dt->modify( "+{$offset_days} days" );

			// Upload images for this post slot
			$uploaded_urls = self::upload_images( $client, $image_sets[ $i ] ?? [] );

			$caption_key = $caption_keys[ $i ];
			$caption     = $captions[ $caption_key ] ?? '';

			try {
				$result = $client->create_post( [
					'text'        => $caption,
					'media_urls'  => $uploaded_urls,
					'schedule_at' => $schedule_dt,
					'timezone'    => $timezone,
				] );

				$post_results[] = [
					'slot'        => $i + 1,
					'scheduled'   => $schedule_dt->format( 'Y-m-d H:i' ),
					'caption_key' => $caption_key,
					'status'      => 'scheduled',
					'postly_id'   => $result['_id'] ?? ( $result['id'] ?? null ),
					'images'      => count( $uploaded_urls ),
				];
			} catch ( \RuntimeException $e ) {
				$post_results[] = [
					'slot'        => $i + 1,
					'scheduled'   => $schedule_dt->format( 'Y-m-d H:i' ),
					'caption_key' => $caption_key,
					'status'      => 'error',
					'error'       => $e->getMessage(),
					'images'      => count( $uploaded_urls ),
				];
			}
		}

		// ── 9. Log results ───────────────────────────────────────────────────
		$log_entry = [
			'post_id'    => $post_id,
			'ran_at'     => current_time( 'mysql' ),
			'shortlink'  => $shortlink,
			'posts'      => $post_results,
		];

		self::write_log( $post_id, $log_entry );

		return $log_entry;
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

	private static function get_shortlink( int $post_id, string $fallback ): string {
		if ( ! class_exists( '\BW_YOURLS_Helper' ) ) {
			return $fallback;
		}

		// Get the stored slug from scos_sa_shortlink_slug or legacy bw_shortlink_slug
		$slug = get_post_meta( $post_id, 'scos_sa_shortlink_slug', true )
			?: get_post_meta( $post_id, 'bw_shortlink_slug', true );

		if ( ! $slug ) {
			return $fallback;
		}

		try {
			$result = \BW_YOURLS_Helper::create_shortlink( $slug, get_permalink( $post_id ) );
			if ( ! empty( $result['shorturl'] ) ) {
				return $result['shorturl'];
			}
		} catch ( \Exception $e ) {
			// Non-fatal: fall back to permalink
		}

		return $fallback;
	}

	/**
	 * Collect all candidate images: featured + ACF gallery fields.
	 * Returns array of publicly accessible image URLs, deduplicated.
	 *
	 * @return string[]
	 */
	private static function collect_images( int $post_id ): array {
		$urls = [];

		// Featured image
		$featured_id = get_post_thumbnail_id( $post_id );
		if ( $featured_id ) {
			// Check for custom ACF key override
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

		// ACF gallery fields
		$gallery_keys_raw = get_option( 'bw_social_acf_gallery_keys', '' );
		if ( $gallery_keys_raw ) {
			$gallery_keys = array_filter( array_map( 'trim', explode( ',', $gallery_keys_raw ) ) );
			foreach ( $gallery_keys as $key ) {
				$gallery = get_post_meta( $post_id, $key, true );
				if ( ! $gallery ) {
					continue;
				}
				// ACF gallery returns array of image arrays [{ID, url, ...}] or array of IDs
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
	 * Divide collected images into three sets following the spec rules.
	 *
	 * - ≥ 5 images: Post1 = first 4, Post2 = last 4, Post3 = random 4.
	 * - < 5 images: all three posts get the same set (up to 4 images).
	 *
	 * @param  string[] $images
	 * @return array[]  3-element array of image URL sets.
	 */
	private static function build_image_sets( array $images ): array {
		$n = count( $images );

		if ( $n >= 5 ) {
			// Post 1: first 4
			$set1 = array_slice( $images, 0, self::IMAGES_PER_POST );
			// Post 2: last 4
			$set2 = array_slice( $images, -self::IMAGES_PER_POST );
			// Post 3: random 4
			$pool = $images;
			shuffle( $pool );
			$set3 = array_slice( $pool, 0, self::IMAGES_PER_POST );
		} else {
			$set1 = $set2 = $set3 = array_slice( $images, 0, self::IMAGES_PER_POST );
		}

		return [ $set1, $set2, $set3 ];
	}

	/**
	 * Upload each image in the set to Postly CDN.
	 * Skips images that fail to upload (logs warning, does not throw).
	 *
	 * @param  Postly_Client $client
	 * @param  string[]      $image_urls
	 * @return string[]      Uploaded CDN URLs.
	 */
	private static function upload_images( Postly_Client $client, array $image_urls ): array {
		$uploaded = [];
		foreach ( $image_urls as $url ) {
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

	private static function write_log( int $post_id, array $entry ): void {
		$log = get_option( self::LOG_OPTION, [] );
		if ( ! is_array( $log ) ) {
			$log = [];
		}
		// Keep last 200 entries to prevent unbounded growth
		$log[ $post_id ] = $entry;
		if ( count( $log ) > 200 ) {
			$log = array_slice( $log, -200, null, true );
		}
		update_option( self::LOG_OPTION, $log, false );
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
