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
			'description'   => __( 'Runs the SCOS Social Amplification pipeline for a published post — generates AI captions and schedules them to the configured Postly.ai channels.', 'site-essentials' ),
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
			],
		];
	}

	public function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'success'        => [ 'type' => 'boolean' ],
				'post_id'        => [ 'type' => 'integer' ],
				'standard_posts' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'slot'      => [ 'type' => 'integer' ],
							'scheduled' => [ 'type' => 'string' ],
							'status'    => [ 'type' => 'string' ],
						],
					],
				],
				'gmb_posts'      => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'slot'      => [ 'type' => 'integer' ],
							'scheduled' => [ 'type' => 'string' ],
							'status'    => [ 'type' => 'string' ],
						],
					],
				],
				'error'          => [ 'type' => 'string' ],
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

		if ( ! $force && get_post_meta( $post_id, Publish_Hook::AMPLIFIED_META, true ) === '1' ) {
			return new WP_Error(
				'scos_send_already_amplified',
				sprintf( __( 'Post #%d has already been amplified. Pass force: true to override.', 'site-essentials' ), $post_id ),
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
		if ( $post_count !== null ) {
			$options['post_count'] = $post_count;
		}

		try {
			$result = Amplification_Engine::run( $post_id, $options );
			update_post_meta( $post_id, Publish_Hook::AMPLIFIED_META, '1' );

			return [
				'success'        => true,
				'post_id'        => $post_id,
				'standard_posts' => $result['standard_posts'] ?? [],
				'gmb_posts'      => $result['gmb_posts'] ?? [],
			];
		} catch ( \RuntimeException $e ) {
			return new WP_Error(
				'scos_send_amplification_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}
}

// Self-register on wp_abilities_api_init.
add_action( 'wp_abilities_api_init', [ Send_Social_Post::class, 'register' ] );
