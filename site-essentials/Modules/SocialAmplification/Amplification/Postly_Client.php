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
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification\Amplification
 */

namespace SiteEssentials\Modules\SocialAmplification\Amplification;

defined( 'ABSPATH' ) || exit;

class Postly_Client {

	const BASE_URL = 'https://openapi.postly.ai/v1';

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

		// Postly requires full channel objects (same shape as GET /workspaces/{id}/socials).
		// Fetch them fresh, then optionally filter to only the configured channel IDs.
		$channels = $this->get_socials();
		error_log( '[SCOS SMA Postly] get_socials() returned ' . count( $channels ) . ' channel(s): ' . wp_json_encode( $channels ) );

		if ( ! empty( $this->channel_ids ) ) {
			$channels = array_values( array_filter( $channels, function ( array $ch ) {
				return in_array( (string) $ch['id'], $this->channel_ids, true )
					|| in_array( (string) ( $ch['parent_id'] ?? '' ), $this->channel_ids, true );
			} ) );
			error_log( '[SCOS SMA Postly] After filtering to channel_ids [' . implode( ',', $this->channel_ids ) . ']: ' . count( $channels ) . ' channel(s)' );
		}

		if ( ! empty( $channels ) ) {
			// Format: [{identifier: channel.target, id: channel.id}]
			// id = channel's own id field — NOT parent_id (confirmed by Postly developer).
			$body['target_platforms'] = array_map( static function ( array $ch ) {
				return [
					'identifier' => $ch['target'] ?? '',
					'id'         => $ch['id'],
				];
			}, $channels );
			error_log( '[SCOS SMA Postly] Sending target_platforms: ' . wp_json_encode( $body['target_platforms'] ) );
		} else {
			// No channel filter — post to all connected channels in the workspace.
			$body['target_platforms'] = 'all';
			error_log( '[SCOS SMA Postly] No channels configured — sending target_platforms: all' );
		}

		if ( $schedule instanceof \DateTimeImmutable ) {
			$body['one_off_schedule'] = [
				'one_off_date' => $schedule->format( 'Y-m-d' ),
				'time'         => $schedule->format( \DateTimeInterface::ATOM ),
				'timezone'     => $timezone,
			];
		}

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

	// ──────────────────────────────────────────────────────────────────────────
	// HTTP helper
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * @throws \RuntimeException
	 */
	private function request( string $method, string $endpoint, array $body = [], array $query = [] ): array {
		$url = self::BASE_URL . $endpoint;
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$args = [
			'method'  => strtoupper( $method ),
			'timeout' => 30,
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
			throw new \RuntimeException( 'Postly API request failed: ' . $response->get_error_message() );
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$decoded  = json_decode( $raw_body, true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $decoded ) ? ( $decoded['message'] ?? $raw_body ) : $raw_body;
			throw new \RuntimeException( "Postly API error ({$code}) on {$method} {$endpoint}: {$msg}" );
		}

		return is_array( $decoded ) ? $decoded : [];
	}
}
