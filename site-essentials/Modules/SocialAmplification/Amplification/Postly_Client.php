<?php
/**
 * Postly.ai API Client
 *
 * Wraps the two Postly endpoints needed by the amplification pipeline:
 *  - POST /v1/files/upload-from-url  → returns hosted CDN URL
 *  - POST /v1/posts                  → schedules a post across all workspace socials
 *
 * Auth: X-API-KEY header.
 * Base: https://openapi.postly.ai/v1/
 *
 * Failures throw Postly_Exception (a RuntimeException) carrying a one-line reason,
 * whether the failure is temporary, and how long Postly asked us to wait.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification\Amplification
 * v1.1 | 2026-07-02
 * v1.2 | 2026-09-11 — 60 s timeout; one inline retry for fast temporary failures;
 *                      honour 429 "Try again in N seconds" across the request;
 *                      readable error text instead of Cloudflare HTML; GMB sends the
 *                      caption as text when there is no image; extract_post_id().
 */

namespace SiteEssentials\Modules\SocialAmplification\Amplification;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/Postly_Exception.php';

class Postly_Client {

	const BASE_URL   = 'https://openapi.postly.ai/v1';
	const LOG_PREFIX = '[SCOS SMA Postly]';

	/** Seconds per request. Was 30 — Postly took longer than that on 2026-09-11. */
	const TIMEOUT = 60;

	/** Longest back-off we will sleep through inside a request before handing over to the retry queue. */
	const MAX_INLINE_WAIT = 20;

	/** Only retry inline when the failed attempt came back within this many seconds. */
	const FAST_FAIL = 15;

	/** Longest error text kept in logs and slot rows. */
	const ERROR_MAX_CHARS = 200;

	/**
	 * Workspace ID => unix time until which Postly has rate-limited us.
	 * Once one call gets a 429, later calls in the same PHP request fail fast
	 * instead of spending the rest of the quota window hitting the limit.
	 *
	 * @var array<string,int>
	 */
	private static array $blocked_until = [];

	/** @var string */
	private string $api_key;

	/** @var string */
	private string $workspace_id;

	/** @var string[] Optional: specific social channel IDs to target. Empty = all workspace channels. */
	private array $channel_ids;

	public function __construct( string $api_key, string $workspace_id, array $channel_ids = [] ) {
		$this->api_key      = $api_key;
		$this->workspace_id = $workspace_id;
		$this->channel_ids  = $channel_ids;
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Public API
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Upload an image to Postly from a public URL.
	 *
	 * @param  string $url       Publicly accessible image URL.
	 * @param  string $file_name Optional filename hint.
	 * @return string            Postly-hosted CDN URL.
	 * @throws \RuntimeException on failure.
	 */
	public function upload_image( string $url, string $file_name = '' ): string {
		$body = [ 'url' => $url ];
		if ( $file_name ) {
			$body['file_name'] = $file_name;
		}

		$data = $this->request( 'POST', '/files/upload-from-url', $body );

		// Response: {"success": true, "data": {"id": "...", "url": "...", "file_name": "..."}}
		$hosted = $data['data']['url'] ?? '';
		if ( ! $hosted ) {
			throw new \RuntimeException( 'Postly upload-from-url did not return a URL. Response: ' . wp_json_encode( $data ) );
		}

		return $hosted;
	}

	/**
	 * Create a scheduled post on Postly.
	 *
	 * @param  array{
	 *     text:        string,
	 *     media_urls:  string[],
	 *     schedule_at: \DateTimeImmutable,
	 *     timezone:    string,
	 * } $params
	 * @return array  Raw Postly post response data.
	 * @throws \RuntimeException on failure.
	 */
	public function create_post( array $params ): array {
		$text      = $params['text']        ?? '';
		$media_raw = $params['media_urls']  ?? [];
		$schedule  = $params['schedule_at'] ?? null;
		$timezone  = $params['timezone']    ?? 'UTC';

		// Build media array — Postly requires [{url, type}]
		$media = array_map( static function ( string $url ) {
			return [ 'url' => $url, 'type' => 'image/jpeg' ];
		}, array_values( array_filter( $media_raw ) ) );

		$body = [
			'text'      => $text,
			'workspace' => $this->workspace_id,
		];

		if ( ! empty( $media ) ) {
			$body['media'] = $media;
		}

		// target_platforms: Postly expects a STRING ("all", platform names, or comma-separated channel IDs).
		// Use configured channel IDs directly; fallback to "all".
		if ( ! empty( $this->channel_ids ) ) {
			$ids = array_values( array_unique( array_filter( array_map( 'strval', $this->channel_ids ) ) ) );
			$body['target_platforms'] = ! empty( $ids ) ? implode( ',', $ids ) : 'all';
		} else {
			$body['target_platforms'] = 'all';
		}

		error_log( '[SCOS SMA Postly] Sending target_platforms (string): ' . $body['target_platforms'] );

		if ( $schedule instanceof \DateTimeImmutable ) {
			$body['one_off_schedule'] = [
				'one_off_date' => $schedule->format( 'Y-m-d' ),
				// Send local wall-clock time so Postly applies the provided timezone
				// without double-converting from an offset ISO timestamp.
				'time'         => $schedule->format( 'H:i' ),
				'timezone'     => $timezone,
			];
		}

		return $this->request( 'POST', '/posts', $body );
	}

	/**
	 * Create a GMB post with platform-specific settings.
	 *
	 * @param array{
	 *   gmb_caption:string,
	 *   cta_url:string,
	 *   image_url:string,
	 *   schedule_at:\DateTimeImmutable,
	 *   timezone:string,
	 *   gmb_channel_id:string
	 * } $params
	 */
	public function create_gmb_post( array $params ): array {
		$caption        = (string) ( $params['gmb_caption'] ?? '' );
		$cta_url        = (string) ( $params['cta_url'] ?? '' );
		$image_url      = (string) ( $params['image_url'] ?? '' );
		$timezone       = (string) ( $params['timezone'] ?? 'UTC' );
		$gmb_channel_id = (string) ( $params['gmb_channel_id'] ?? '' );
		$schedule       = $params['schedule_at'] ?? null;

		// Postly stores GMB fields under platform_posts[].settings (not flat platform_settings).
		// Verified via GET /posts/{id} on a UI-created golden post vs API-created post.
		$gmb_settings = [
			'identifier'         => 'googleMyBusiness',
			'type'               => 'update',
			'language_code'      => 'en-AU',
			'summary'            => $caption,
			'call_to_action'     => 'learn_more',
			'call_to_action_url' => $cta_url,
		];

		$body = [
			// Postly rejects a post with neither text nor media ("Post content must include
			// text or media"), so without an image the caption goes in as text as well.
			'text'             => '' === $image_url ? $caption : '',
			'workspace'        => $this->workspace_id,
			'target_platforms' => $gmb_channel_id,
			'platform_posts'   => [
				[
					'identifier' => 'googleMyBusiness',
					'settings'   => $gmb_settings,
				],
			],
		];

		if ( $image_url !== '' ) {
			$body['media'] = [
				[
					'url'  => $image_url,
					'type' => 'image/*',
				],
			];
		}

		if ( $schedule instanceof \DateTimeImmutable ) {
			$body['one_off_schedule'] = [
				'one_off_date' => $schedule->format( 'Y-m-d' ),
				'time'         => $schedule->format( 'H:i' ),
				'timezone'     => $timezone,
			];
		}

		error_log( '[SCOS SMA Postly] Sending GMB payload: ' . wp_json_encode( $body ) );
		return $this->request( 'POST', '/posts', $body );
	}

	/**
	 * Fetch connected social accounts for the workspace.
	 * Results are cached per request to avoid redundant calls when scheduling 3 posts.
	 *
	 * @return array[]
	 */
	public function get_socials(): array {
		static $cache = [];
		$key = $this->workspace_id;

		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		try {
			$raw = $this->request( 'GET', "/workspaces/{$this->workspace_id}/socials" );
			error_log( '[SCOS SMA Postly] Raw socials response: ' . wp_json_encode( $raw ) );
			$cache[ $key ] = $raw['data'] ?? ( is_array( $raw ) && isset( $raw[0] ) ? $raw : [] );
		} catch ( \RuntimeException $e ) {
			error_log( '[SCOS SMA Postly] Failed to fetch socials: ' . $e->getMessage() );
			$cache[ $key ] = [];
		}

		return $cache[ $key ];
	}

	/**
	 * Fetch scheduled posts for the workspace (used by the CLI backfill to find free slots).
	 *
	 * @param  int $skip Pagination offset.
	 * @return array[]   Array of post objects.
	 * @throws \RuntimeException on failure.
	 */
	public function fetch_posts( int $skip = 0 ): array {
		$data = $this->request( 'GET', '/posts', [], [
			'workspaceId' => $this->workspace_id,
			'skip'        => $skip,
		] );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Find the Postly post ID in a create-post response.
	 *
	 * The response shape isn't documented, so check the likely places and log the
	 * keys when none match — the next run's log then shows where the ID lives.
	 */
	public static function extract_post_id( array $response ): ?string {
		$paths = [
			[ '_id' ],
			[ 'id' ],
			[ 'data', '_id' ],
			[ 'data', 'id' ],
			[ 'data', 'post', '_id' ],
			[ 'data', 'post', 'id' ],
			[ 'post', '_id' ],
			[ 'post', 'id' ],
			[ 'data', 0, '_id' ],
			[ 'data', 0, 'id' ],
		];

		foreach ( $paths as $path ) {
			$value = $response;
			foreach ( $path as $segment ) {
				if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
					$value = null;
					break;
				}
				$value = $value[ $segment ];
			}
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				return (string) $value;
			}
		}

		$keys = implode( ',', array_keys( $response ) );
		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			$keys .= ' | data: ' . implode( ',', array_keys( $response['data'] ) );
		}
		error_log( self::LOG_PREFIX . " No post ID found in the create-post response. Keys: {$keys}" );
		return null;
	}

	// ──────────────────────────────────────────────────────────────────────────
	// HTTP helper
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Send a request, retrying once inline when the failure was quick and temporary.
	 *
	 * Slow failures (a 60 s timeout) and long waits (a 429 asking for 144 s) are
	 * thrown straight back — the engine queues those for a WP-Cron retry rather
	 * than holding an admin-ajax request open.
	 *
	 * @throws Postly_Exception
	 */
	private function request( string $method, string $endpoint, array $body = [], array $query = [] ): array {
		$blocked_for = ( self::$blocked_until[ $this->workspace_id ] ?? 0 ) - time();
		if ( $blocked_for > 0 ) {
			throw new Postly_Exception(
				"Postly rate limit still active — skipped {$method} {$endpoint} (try again in {$blocked_for} seconds)",
				429,
				true,
				$blocked_for
			);
		}

		$started = microtime( true );
		try {
			return $this->send( $method, $endpoint, $body, $query );
		} catch ( Postly_Exception $e ) {
			$elapsed = microtime( true ) - $started;
			if ( ! $e->is_retryable() || $e->retry_after() > self::MAX_INLINE_WAIT || $elapsed > self::FAST_FAIL ) {
				throw $e;
			}
			error_log( self::LOG_PREFIX . ' ' . $e->getMessage() . " — retrying once in {$e->retry_after()}s." );
			if ( $e->retry_after() > 0 ) {
				sleep( $e->retry_after() );
			}
			return $this->send( $method, $endpoint, $body, $query );
		}
	}

	/**
	 * One HTTP round trip. Classifies failures into Postly_Exception.
	 *
	 * @throws Postly_Exception
	 */
	private function send( string $method, string $endpoint, array $body, array $query ): array {
		$url = self::BASE_URL . $endpoint;
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$args = [
			'method'  => strtoupper( $method ),
			'timeout' => self::TIMEOUT,
			'headers' => [
				'X-API-KEY'    => $this->api_key,
				'Content-Type' => 'application/json',
			],
		];

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			// No HTTP response. A timeout or dropped connection may still have reached
			// Postly; a DNS or connect failure never did.
			$reason    = $response->get_error_message();
			$uncertain = (bool) preg_match( '/timed out|cURL error (28|52|56)\b/i', $reason );
			throw new Postly_Exception(
				"Postly API request failed on {$method} {$endpoint}: " . self::clip( $reason ),
				0,
				true,
				$uncertain ? 120 : 5,
				$uncertain
			);
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = (string) wp_remote_retrieve_body( $response );
		$decoded  = json_decode( $raw_body, true );

		if ( $code >= 200 && $code < 300 ) {
			return is_array( $decoded ) ? $decoded : [];
		}

		$message = "Postly API error ({$code}) on {$method} {$endpoint}: " . self::summarise_error( $decoded, $raw_body );

		if ( 429 === $code ) {
			$wait = self::parse_retry_after( $response, $message );
			self::$blocked_until[ $this->workspace_id ] = time() + $wait;
			throw new Postly_Exception( $message, $code, true, $wait );
		}

		if ( in_array( $code, [ 500, 502, 503, 504 ], true ) ) {
			// 503 means Postly refused the request; 500/502/504 may have processed it.
			throw new Postly_Exception( $message, $code, true, 10, 503 !== $code );
		}

		throw new Postly_Exception( $message, $code );
	}

	/**
	 * Seconds to wait after a 429: the Retry-After header, else Postly's
	 * "Rate limit exceeded. Try again in 144 seconds", else 60.
	 *
	 * @param array|\WP_Error $response
	 */
	private static function parse_retry_after( $response, string $message ): int {
		$header = wp_remote_retrieve_header( $response, 'retry-after' );
		if ( is_array( $header ) ) {
			$header = reset( $header );
		}
		if ( is_string( $header ) && ctype_digit( trim( $header ) ) ) {
			return max( 1, (int) trim( $header ) );
		}

		if ( preg_match( '/try again in (\d+)\s*(minutes?|mins?)?/i', $message, $m ) ) {
			$seconds = (int) $m[1] * ( empty( $m[2] ) ? 1 : 60 );
			return max( 1, $seconds );
		}

		return 60;
	}

	/**
	 * One readable line from an error response: the JSON message, the HTML page
	 * title (Cloudflare error pages), or the stripped text — never the whole page.
	 *
	 * @param mixed $decoded json_decode() of the body.
	 */
	private static function summarise_error( $decoded, string $raw_body ): string {
		if ( is_array( $decoded ) ) {
			$message = $decoded['message'] ?? ( $decoded['error'] ?? '' );
			if ( is_array( $message ) ) {
				$message = wp_json_encode( $message );
			}
			$message = trim( (string) $message );
			return self::clip( '' !== $message ? $message : (string) wp_json_encode( $decoded ) );
		}

		if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $raw_body, $m ) ) {
			$title = trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES ) );
			if ( '' !== $title ) {
				return self::clip( $title . ' (HTML error page)' );
			}
		}

		$text = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $raw_body ) ) );
		return '' !== $text ? self::clip( $text ) : '(empty response body)';
	}

	private static function clip( string $text ): string {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $text ) > self::ERROR_MAX_CHARS ? mb_substr( $text, 0, self::ERROR_MAX_CHARS - 1 ) . '…' : $text;
		}
		return strlen( $text ) > self::ERROR_MAX_CHARS ? substr( $text, 0, self::ERROR_MAX_CHARS - 3 ) . '...' : $text;
	}
}
