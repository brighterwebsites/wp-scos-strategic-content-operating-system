<?php
/**
 * Publish Hook — any post type
 *
 * Hooks into `save_post` and fires the amplification pipeline when all
 * of the following conditions are met:
 *  - Post status is publish (not a revision or autosave)
 *  - scos_ca_optimization_progress contains 'amplification'
 *  - _scos_sa_amplified is not already '1' (prevents re-runs)
 *  - bw_social_enabled == '1'
 *  - bw_anthropic_api_key, bw_postly_api_key, bw_postly_workspace_id,
 *    and bw_social_webhook_secret are all configured
 *
 * Fires an internal loopback request to the REST endpoint so the
 * amplification runs asynchronously and does not block the save.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification
 * @version    1.1 | 2026-06-24
 */

namespace SiteEssentials\Modules\SocialAmplification;

defined( 'ABSPATH' ) || exit;

class Publish_Hook {

	/** Meta key: set to '1' to prevent re-amplification on minor saves */
	const AMPLIFIED_META = '_scos_sa_amplified';

	public static function init(): void {
		add_action( 'save_post', [ __CLASS__, 'on_save_post' ], 10, 2 );
	}

	/**
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public static function on_save_post( int $post_id, \WP_Post $post ): void {
		// Only run on the real post, not revisions or auto-saves.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Post must be published.
		if ( $post->post_status !== 'publish' ) {
			return;
		}

		// scos_ca_optimization_progress must include 'amplification'.
		$progress = get_post_meta( $post_id, 'scos_ca_optimization_progress', true );
		if ( ! is_array( $progress ) || ! in_array( 'amplification', $progress, true ) ) {
			return;
		}

		// Avoid double-firing (e.g. minor saves after initial publish).
		if ( get_post_meta( $post_id, self::AMPLIFIED_META, true ) === '1' ) {
			return;
		}

		// Guard: all required settings must be configured.
		if ( ! self::settings_complete() ) {
			return;
		}

		// Mark immediately so concurrent saves don't double-fire.
		update_post_meta( $post_id, self::AMPLIFIED_META, '1' );

		// Fire loopback request — non-blocking so the save doesn't time out
		$endpoint = rest_url( 'bw-social/v1/amplify' );
		$secret   = get_option( 'bw_social_webhook_secret', '' );

		wp_remote_post( $endpoint, [
			'timeout'   => 0.1,   // Fire-and-forget; WP handles the rest
			'blocking'  => false,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			'headers'   => [ 'Content-Type' => 'application/json' ],
			'body'      => wp_json_encode( [
				'post_id' => $post_id,
				'secret'  => $secret,
			] ),
		] );
	}

	/**
	 * Check that every required option has a value.
	 */
	private static function settings_complete(): bool {
		$required = [
			'bw_social_enabled',
			'bw_anthropic_api_key',
			'bw_postly_api_key',
			'bw_postly_workspace_id',
			'bw_social_webhook_secret',
		];

		foreach ( $required as $key ) {
			$value = get_option( $key, '' );
			if ( '' === (string) $value || '0' === (string) $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Clear the "already amplified" flag — useful after a major content update
	 * when you want the post to be re-amplified on next save.
	 *
	 * @param int $post_id
	 */
	public static function reset_flag( int $post_id ): void {
		delete_post_meta( $post_id, self::AMPLIFIED_META );
	}
}
