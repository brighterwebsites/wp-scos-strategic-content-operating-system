<?php
/**
 * WP-CLI Delete Post Command
 *
 * Usage:
 *   wp scos-social delete <post_id> --slot=standard:2
 *   wp scos-social delete <post_id> --slot=gmb
 *   wp scos-social delete --postly-id=<id>
 *
 * Deletes a scheduled social post from Postly. With a post ID and slot, the slot
 * is looked up in the post's last run and marked deleted in its log, so the run's
 * outcome and counts stop including it. With --postly-id alone, the post is
 * removed from Postly only and no SCOS log changes.
 *
 * Deliberately WP-CLI only: there is no ability, so agents cannot delete posts (#259).
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification\CLI
 * v1.0 | 2026-11-28
 */

namespace SiteEssentials\Modules\SocialAmplification\CLI;

defined( 'ABSPATH' ) || exit;

use SiteEssentials\Modules\SocialAmplification\Amplification\Amplification_Engine;
use SiteEssentials\Modules\SocialAmplification\Amplification\Postly_Client;

class Delete_Post_Command {

	/**
	 * Delete a scheduled social post from Postly.
	 *
	 * ## OPTIONS
	 *
	 * [<post_id>]
	 * : WordPress post ID whose last run holds the slot. Required with --slot.
	 *
	 * [--slot=<slot>]
	 * : The slot to delete: standard:<n> (e.g. standard:2) or gmb.
	 *
	 * [--postly-id=<id>]
	 * : Delete this Postly post directly, for one that is not in a run log (e.g. a test post).
	 * The SCOS log is not changed.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp scos-social delete 123 --slot=standard:2
	 *     wp scos-social delete 123 --slot=gmb --yes
	 *     wp scos-social delete --postly-id=66f1c0ffee
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Named args.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$post_id   = isset( $args[0] ) ? (int) $args[0] : 0;
		$slot_arg  = trim( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'slot', '' ) );
		$postly_id = trim( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'postly-id', '' ) );

		if ( '' !== $postly_id ) {
			if ( $post_id || '' !== $slot_arg ) {
				\WP_CLI::error( 'Use either <post_id> --slot=… or --postly-id=…, not both.' );
			}
			\WP_CLI::confirm( "Delete Postly post {$postly_id}? The SCOS log is not changed.", $assoc_args );
			$client = new Postly_Client( (string) get_option( 'bw_postly_api_key', '' ), (string) get_option( 'bw_postly_workspace_id', '' ) );
			try {
				$client->delete_post( $postly_id );
			} catch ( \InvalidArgumentException | \RuntimeException $e ) {
				\WP_CLI::error( $e->getMessage() );
			}
			\WP_CLI::success( "Deleted Postly post {$postly_id}." );
			return;
		}

		if ( $post_id <= 0 || '' === $slot_arg ) {
			\WP_CLI::error( 'Pass <post_id> and --slot=standard:<n> or --slot=gmb (or --postly-id=… for a post not in a run log).' );
		}

		if ( 'gmb' === $slot_arg ) {
			$platform = 'gmb';
			$slot     = 1;
		} elseif ( preg_match( '/^standard:(\d{1,2})$/', $slot_arg, $m ) && (int) $m[1] > 0 ) {
			$platform = 'standard';
			$slot     = (int) $m[1];
		} else {
			\WP_CLI::error( "--slot must be standard:<n> or gmb, not \"{$slot_arg}\"." );
		}

		$label = 'gmb' === $platform ? 'the Google Business post' : "standard post {$slot}";
		\WP_CLI::confirm( "Delete {$label} of post #{$post_id} from Postly?", $assoc_args );

		try {
			$entry = Amplification_Engine::delete_slot( $post_id, $platform, $slot );
		} catch ( \RuntimeException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}

		\WP_CLI::success( "Deleted {$label} of post #{$post_id}. " . Amplification_Engine::describe_outcome( $entry ) );
	}
}
