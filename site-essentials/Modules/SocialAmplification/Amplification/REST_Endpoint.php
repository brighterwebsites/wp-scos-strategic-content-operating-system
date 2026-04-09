<?php
/**
 * REST API Endpoint — bw-social/v1/amplify
 *
 * Registers a POST endpoint that triggers the amplification pipeline for a
 * given post. Validated by the `bw_social_webhook_secret` option.
 *
 * Route:  POST /wp-json/bw-social/v1/amplify
 * Params: { post_id: int, secret: string }
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification\Amplification
 */

namespace SiteEssentials\Modules\SocialAmplification\Amplification;

defined( 'ABSPATH' ) || exit;

class REST_Endpoint {

	const NAMESPACE = 'bw-social/v1';
	const ROUTE     = '/amplify';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route( self::NAMESPACE, self::ROUTE, [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ __CLASS__, 'handle' ],
			'permission_callback' => [ __CLASS__, 'permission_check' ],
			'args'                => [
				'post_id' => [
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'description'       => 'ID of the post to amplify.',
				],
				'secret' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => 'Must match bw_social_webhook_secret.',
				],
				'schedule_at' => [
					'required'    => false,
					'type'        => 'string',
					'description' => 'ISO 8601 datetime for first post slot. Defaults to now.',
				],
			],
		] );
	}

	public static function permission_check( \WP_REST_Request $request ): bool {
		$secret         = (string) $request->get_param( 'secret' );
		$stored_secret  = (string) get_option( 'bw_social_webhook_secret', '' );

		if ( ! $stored_secret || ! hash_equals( $stored_secret, $secret ) ) {
			return false;
		}
		return true;
	}

	public static function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id     = (int) $request->get_param( 'post_id' );
		$schedule_at = $request->get_param( 'schedule_at' );

		$options = [];
		if ( $schedule_at ) {
			try {
				$options['schedule_at'] = new \DateTimeImmutable(
					$schedule_at,
					new \DateTimeZone( get_option( 'timezone_string', 'UTC' ) ?: 'UTC' )
				);
			} catch ( \Exception $e ) {
				return new \WP_REST_Response( [
					'success' => false,
					'error'   => 'Invalid schedule_at datetime: ' . $e->getMessage(),
				], 400 );
			}
		}

		try {
			$result = Amplification_Engine::run( $post_id, $options );
			return new \WP_REST_Response( [
				'success' => true,
				'data'    => $result,
			], 200 );
		} catch ( \RuntimeException $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => $e->getMessage(),
			], 500 );
		}
	}
}
