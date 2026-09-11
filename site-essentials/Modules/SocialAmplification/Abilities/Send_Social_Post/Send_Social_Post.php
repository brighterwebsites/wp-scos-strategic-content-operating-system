<?php
/**
 * Send Social Post — WordPress Ability
 *
 * Runs the SCOS Social Amplification pipeline for a given post, making
 * the send-to-Postly workflow callable via WP Abilities API REST endpoint
 * and MCP tool calls.
 *
 * Ability slug: scos/send-social-post (permanent — do not rename after deployment)
 * Category:     scos-social-amplification
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification\Abilities\Send_Social_Post
 * v1.0 | 2026-07-01
 * v1.1 | 2026-09-11 — `retry_failed` resends only the slots that failed; the result
 *                      reports the run outcome, and `success` is true only when
 *                      every slot was scheduled.
 */

declare( strict_types=1 );

namespace SiteEssentials\Modules\SocialAmplification\Abilities\Send_Social_Post;

use WP_Error;
use WordPress\AI\Abstracts\Abstract_Ability;
use SiteEssentials\Modules\SocialAmplification\Amplification\Amplification_Engine;
use SiteEssentials\Modules\SocialAmplification\Publish_Hook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Send_Social_Post extends Abstract_Ability {

	// ──────────────────────────────────────────────────────────────────────────
	// Registration
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Register this ability with the WP Abilities API.
	 * Called via wp_abilities_api_init after class_exists guards confirm availability.
	 */
	public static function register(): void {
		if ( ! class_exists( 'WP_Ability' ) ) {
			return;
		}
		if ( ! class_exists( 'WordPress\AI\Abstracts\Abstract_Ability' ) ) {
			return;
		}
		wp_register_ability( 'scos/send-social-post', [
			'label'         => __( 'SCOS: Send Social Post', 'site-essentials' ),
			'description'   => __( 'Runs the SCOS Social Amplification pipeline for a published post — schedules captions to the configured Postly.ai channels. Pass captions you have written to have them scheduled as-is; omit them and they are generated from the site brand knowledge.', 'site-essentials' ),
			'category'      => 'scos-social-amplification',
			'ability_class' => self::class,
			'meta'          => [
				'show_in_rest' => true,
				'mcp'          => [
					'public' => true,
					'type'   => 'tool',
				],
			],
		] );
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Schema
	// ──────────────────────────────────────────────────────────────────────────

	public function guideline_categories(): array {
		return [];
	}

	public function input_schema(): array {
		return [
			'type'       => 'object',
			'required'   => [ 'post_id' ],
			'properties' => [
				'post_id'    => [
					'type'        => 'integer',
					'description' => 'WordPress post ID of the published post to amplify.',
					'minimum'     => 1,
				],
				'channel'    => [
					'type'        => 'string',
					'description' => 'Which channels to post to: facebook, instagram, gmb, others, all (default: all).',
					'enum'        => [ 'facebook', 'instagram', 'gmb', 'others', 'all' ],
					'default'     => 'all',
				],
				'post_count' => [
					'type'        => 'integer',
					'description' => 'Override the number of social posts to generate. Defaults to per-type or global setting.',
					'minimum'     => 1,
					'maximum'     => 10,
				],
				'force'      => [
					'type'        => 'boolean',
					'description' => 'Run even if the post has already been amplified.',
					'default'     => false,
				],
				'captions'   => [
					'type'        => 'array',
					'description' => 'Optional. Captions you have already written, in order, one per scheduled slot. Supply these and no AI generation happens — they are scheduled as-is. Omit them and captions are generated server-side from the site brand knowledge.',
					'items'       => [ 'type' => 'string' ],
					'maxItems'    => 10,
				],
				'gmb_caption' => [
					'type'        => 'string',
					'description' => 'Optional. A Google Business Profile caption you have already written. Supply it and no AI generation happens for GMB. Must contain no URLs, phone numbers or hashtags, and stay between 150 and 300 characters.',
				],
				'retry_failed' => [
					'type'        => 'boolean',
					'description' => 'Resend only the posts that failed in the last run (rate limit, Postly outage) — posts that were scheduled are left alone. Ignores channel, post_count, captions and force. Use this rather than force after a partial run, which would schedule duplicates.',
					'default'     => false,
				],
			],
		];
	}

	public function output_schema(): array {
		$slot = [
			'type'       => 'object',
			'properties' => [
				'slot'      => [ 'type' => 'integer' ],
				'scheduled' => [ 'type' => 'string' ],
				'status'    => [
					'type'        => 'string',
					'description' => 'scheduled, retry_scheduled (failed; an automatic retry is queued) or error (failed; needs retry_failed).',
				],
				'postly_id' => [ 'type' => [ 'string', 'null' ] ],
				'error'     => [ 'type' => 'string' ],
				'note'      => [ 'type' => 'string' ],
			],
		];

		return [
			'type'       => 'object',
			'properties' => [
				'success'         => [
					'type'        => 'boolean',
					'description' => 'True only when every post was scheduled.',
				],
				'post_id'         => [ 'type' => 'integer' ],
				'outcome'         => [
					'type'        => 'string',
					'description' => 'complete, partial, failed (nothing scheduled) or skipped (no channels configured).',
				],
				'message'         => [ 'type' => 'string' ],
				'retry_at'        => [
					'type'        => 'string',
					'description' => 'Site-time Y-m-d H:i of the automatic retry of failed posts, or empty.',
				],
				'pending_retries' => [ 'type' => 'integer' ],
				'standard_posts'  => [ 'type' => 'array', 'items' => $slot ],
				'gmb_posts'       => [ 'type' => 'array', 'items' => $slot ],
				'error'           => [ 'type' => 'string' ],
			],
		];
	}

	public function meta(): array {
		return [
			'show_in_rest' => true,
			'mcp'          => [
				'public' => true,
				'type'   => 'tool',
			],
		];
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Permissions
	// ──────────────────────────────────────────────────────────────────────────

	public function permission_callback( $input ) {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return new WP_Error(
					'scos_send_post_not_found',
					__( 'Post not found.', 'site-essentials' ),
					[ 'status' => 404 ]
				);
			}
			return current_user_can( 'edit_post', $post_id );
		}

		return current_user_can( 'edit_posts' );
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Execution
	// ──────────────────────────────────────────────────────────────────────────

	public function execute_callback( $input ) {
		$post_id    = (int) ( $input['post_id'] ?? 0 );
		$channel    = sanitize_key( (string) ( $input['channel'] ?? 'all' ) );
		$post_count = isset( $input['post_count'] ) ? max( 1, (int) $input['post_count'] ) : null;
		$force      = ! empty( $input['force'] );

		if ( ! $post_id ) {
			return new WP_Error( 'scos_send_missing_post_id', __( 'post_id is required.', 'site-essentials' ), [ 'status' => 400 ] );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'scos_send_not_found', sprintf( __( 'Post #%d not found.', 'site-essentials' ), $post_id ), [ 'status' => 404 ] );
		}

		if ( $post->post_status !== 'publish' ) {
			return new WP_Error(
				'scos_send_not_published',
				sprintf( __( 'Post #%d is not published (status: %s).', 'site-essentials' ), $post_id, $post->post_status ),
				[ 'status' => 400 ]
			);
		}

		if ( ! empty( $input['retry_failed'] ) ) {
			try {
				return self::result( $post_id, Amplification_Engine::retry_failed_slots( $post_id, true ) );
			} catch ( \RuntimeException $e ) {
				return new WP_Error( 'scos_send_retry_failed', $e->getMessage(), [ 'status' => 409 ] );
			}
		}

		if ( ! $force && get_post_meta( $post_id, Publish_Hook::AMPLIFIED_META, true ) === '1' ) {
			$queued = count( Amplification_Engine::get_retry_queue( $post_id ) );
			return new WP_Error(
				'scos_send_already_amplified',
				$queued
					? sprintf( __( 'Post #%1$d has already been amplified, and %2$d failed post(s) are waiting. Pass retry_failed: true to resend just those, or force: true to schedule a whole new set.', 'site-essentials' ), $post_id, $queued )
					: sprintf( __( 'Post #%d has already been amplified. Pass force: true to override.', 'site-essentials' ), $post_id ),
				[ 'status' => 409 ]
			);
		}

		$valid_channels = [ 'facebook', 'instagram', 'gmb', 'others', 'all' ];
		if ( ! in_array( $channel, $valid_channels, true ) ) {
			$channel = 'all';
		}

		$run_standard = in_array( $channel, [ 'facebook', 'instagram', 'others', 'all' ], true );
		$run_gmb      = in_array( $channel, [ 'gmb', 'all' ], true );

		if ( $run_gmb && '' === Amplification_Engine::resolve_gmb_channel_id() ) {
			$run_gmb = false;
		}

		$options = [
			'run_standard' => $run_standard,
			'run_gmb'      => $run_gmb,
		];

		// Caller-supplied captions (agent-authored) bypass generation entirely.
		// The engine keys standard captions post_1…post_N, so map the ordered list.
		$supplied = [];
		foreach ( (array) ( $input['captions'] ?? [] ) as $caption ) {
			$caption = trim( (string) $caption );
			if ( '' !== $caption ) {
				$supplied[] = $caption;
			}
		}
		if ( ! empty( $supplied ) ) {
			$keyed = [];
			foreach ( array_values( $supplied ) as $i => $caption ) {
				$keyed[ 'post_' . ( $i + 1 ) ] = $caption;
			}
			$options['captions'] = $keyed;

			// Without an explicit override, schedule exactly as many slots as captions given.
			if ( null === $post_count ) {
				$post_count = count( $supplied );
			}
		}

		$gmb_caption = trim( (string) ( $input['gmb_caption'] ?? '' ) );
		if ( '' !== $gmb_caption ) {
			$options['gmb_caption'] = $gmb_caption;
		}

		if ( $post_count !== null ) {
			$options['post_count'] = $post_count;
		}

		$options['trigger'] = 'ability';

		try {
			$result = Amplification_Engine::run( $post_id, $options );
			// The flag is the re-run lock; the outcome in the result says how it went.
			if ( 'skipped' !== ( $result['outcome'] ?? '' ) ) {
				update_post_meta( $post_id, Publish_Hook::AMPLIFIED_META, '1' );
			}

			return self::result( $post_id, $result );
		} catch ( \RuntimeException $e ) {
			return new WP_Error(
				'scos_send_amplification_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Shape a run or retry log entry for the caller.
	 */
	private static function result( int $post_id, array $entry ): array {
		$outcome = (string) ( $entry['outcome'] ?? Amplification_Engine::outcome_of( $entry ) );

		return [
			'success'         => 'complete' === $outcome,
			'post_id'         => $post_id,
			'outcome'         => $outcome,
			'message'         => Amplification_Engine::describe_outcome( $entry ),
			'retry_at'        => (string) ( $entry['retry_at'] ?? '' ),
			'pending_retries' => count( Amplification_Engine::get_retry_queue( $post_id ) ),
			'standard_posts'  => $entry['standard_posts'] ?? [],
			'gmb_posts'       => $entry['gmb_posts'] ?? [],
		];
	}
}

// Self-register on wp_abilities_api_init.
add_action( 'wp_abilities_api_init', [ Send_Social_Post::class, 'register' ] );
