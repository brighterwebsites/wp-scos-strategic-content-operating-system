<?php
/**
 * WP-CLI Command: wp scos image-meta-instructions
 *
 * Prints the image meta writing instructions — the same data the
 * scos/get-image-meta-instructions ability returns. No AI involved.
 *
 * v1.0 | 2026-09-30
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\CLI
 */

namespace SiteEssentials\Modules\SeoMeta\CLI;

use WP_CLI;
use WP_CLI_Command;
use SiteEssentials\Modules\SeoMeta\Image_Meta_Instructions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Image_Meta_Instructions_Command extends WP_CLI_Command {

	/**
	 * Show how image alt text and media titles are written.
	 *
	 * ## OPTIONS
	 *
	 * [--attachment-id=<id>]
	 * : Image the meta is for. Shows current values and the page it is attached to.
	 *
	 * [--format=<format>]
	 * : json (default) for the structured data, or prompt for the plain-text
	 *   rendering used inside system prompts.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp scos image-meta-instructions
	 *     $ wp scos image-meta-instructions --attachment-id=123 --format=prompt
	 *
	 * @subcommand image-meta-instructions
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associated arguments (flags).
	 */
	public function __invoke( $args, $assoc_args ) {
		$attachment_id = isset( $assoc_args['attachment-id'] ) ? (int) $assoc_args['attachment-id'] : 0;
		$format        = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'json';

		if ( ! in_array( $format, [ 'json', 'prompt' ], true ) ) {
			WP_CLI::error( "Invalid --format value: {$format}. Allowed: json, prompt." );
		}

		if ( $attachment_id && 'attachment' !== get_post_type( $attachment_id ) ) {
			WP_CLI::error( "Attachment {$attachment_id} not found." );
		}

		$instructions = Image_Meta_Instructions::get( [ 'attachment_id' => $attachment_id ] );

		if ( 'prompt' === $format ) {
			WP_CLI::line( Image_Meta_Instructions::to_prompt( $instructions ) );
			return;
		}

		WP_CLI::line( wp_json_encode( $instructions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
	}
}
