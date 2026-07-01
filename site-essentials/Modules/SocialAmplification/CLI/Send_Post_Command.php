<?php
/**
 * WP-CLI Send Post Command
 *
 * Usage:
 *   wp scos-social sendpost --postid=<id>
 *   wp scos-social sendpost --postid=42 --channel=facebook --socialpostcount=2
 *
 * Runs the full amplification pipeline for a single post, with optional
 * overrides for channel(s) and post count. Useful for one-off sends, testing,
 * and MCP-driven agent workflows.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification\CLI
 * v1.0 | 2026-07-01
 */

namespace SiteEssentials\Modules\SocialAmplification\CLI;

defined( 'ABSPATH' ) || exit;

use SiteEssentials\Modules\SocialAmplification\Amplification\Amplification_Engine;
use SiteEssentials\Modules\SocialAmplification\Publish_Hook;

class Send_Post_Command {

	/**
	 * Run social amplification for a single post.
	 *
	 * ## OPTIONS
	 *
	 * --postid=<id>
	 * : WordPress post ID to amplify. Required.
	 *
	 * [--channel=<slug>]
	 * : Which channels to post to. One of: facebook, instagram, gmb, others, all.
	 * Default: all. Note: this overrides channel enable settings for this run only.
	 *
	 * [--socialpostcount=<n>]
	 * : Number of social posts to create. Overrides per-type and global settings.
	 *
	 * [--force]
	 * : Run even if the post has already been amplified (_scos_sa_amplified = '1').
	 *
	 * [--dry-run]
	 * : Print what would happen without calling any APIs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp scos-social sendpost --postid=42
	 *     wp scos-social sendpost --postid=42 --channel=facebook --socialpostcount=2
	 *     wp scos-social sendpost --postid=42 --channel=all --dry-run
	 *
	 * @when after_wp_load
	 *
	 * @param  array $args       Positional args (unused).
	 * @param  array $assoc_args Named args.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$post_id    = absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'postid', 0 ) );
		$channel    = sanitize_key( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'channel', 'all' ) );
		$post_count = \WP_CLI\Utils\get_flag_value( $assoc_args, 'socialpostcount', null );
		$force      = \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		$dry_run    = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		if ( ! $post_id ) {
			\WP_CLI::error( '--postid is required and must be a positive integer.' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			\WP_CLI::error( "Post #{$post_id} not found." );
		}
		if ( $post->post_status !== 'publish' ) {
			\WP_CLI::error( "Post #{$post_id} is not published (status: {$post->post_status})." );
		}

		if ( ! $force && get_post_meta( $post_id, Publish_Hook::AMPLIFIED_META, true ) === '1' ) {
			\WP_CLI::error( "Post #{$post_id} has already been amplified. Use --force to run again." );
		}

		$valid_channels = [ 'facebook', 'instagram', 'gmb', 'others', 'all' ];
		if ( ! in_array( $channel, $valid_channels, true ) ) {
			\WP_CLI::error( '--channel must be one of: ' . implode( ', ', $valid_channels ) );
		}

		$this->validate_settings();

		// Resolve run_standard / run_gmb based on --channel
		$run_standard = in_array( $channel, [ 'facebook', 'instagram', 'others', 'all' ], true );
		$run_gmb      = in_array( $channel, [ 'gmb', 'all' ], true );

		$gmb_channel_id = Amplification_Engine::resolve_gmb_channel_id();
		if ( $run_gmb && '' === $gmb_channel_id ) {
			\WP_CLI::warning( 'GMB channel not configured — skipping GMB even though --channel includes gmb.' );
			$run_gmb = false;
		}

		if ( $dry_run ) {
			\WP_CLI::line( "[DRY RUN] Would amplify post #{$post_id} \"{$post->post_title}\"" );
			\WP_CLI::line( "  → run_standard: " . ( $run_standard ? 'yes' : 'no' ) );
			\WP_CLI::line( "  → run_gmb:      " . ( $run_gmb ? 'yes' : 'no' ) );
			if ( $post_count !== null ) {
				\WP_CLI::line( "  → post_count override: " . absint( $post_count ) );
			}
			return;
		}

		$options = [
			'run_standard' => $run_standard,
			'run_gmb'      => $run_gmb,
		];
		if ( $post_count !== null ) {
			$options['post_count'] = max( 1, absint( $post_count ) );
		}

		try {
			$result = Amplification_Engine::run( $post_id, $options );
			update_post_meta( $post_id, Publish_Hook::AMPLIFIED_META, '1' );

			$scheduled_standard = array_column( $result['standard_posts'] ?? [], 'scheduled' );
			$scheduled_gmb      = array_column( $result['gmb_posts'] ?? [], 'scheduled' );

			\WP_CLI::success(
				"Post #{$post_id} amplified — standard: "
				. ( $scheduled_standard ? implode( ', ', $scheduled_standard ) : 'none' )
				. ' | gmb: '
				. ( $scheduled_gmb ? implode( ', ', $scheduled_gmb ) : 'none' )
			);
		} catch ( \RuntimeException $e ) {
			\WP_CLI::error( 'Amplification failed: ' . $e->getMessage() );
		}
	}

	private function validate_settings(): void {
		$missing  = [];
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
}
