<?php
/**
 * WP-CLI Command: wp scos seo-meta-instructions
 *
 * Prints the SEO meta writing instructions — the same data the
 * scos/get-seo-meta-instructions ability returns. No AI involved.
 *
 * v1.0 | 2026-09-30
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\CLI
 */

namespace SiteEssentials\Modules\SeoMeta\CLI;

use WP_CLI;
use WP_CLI_Command;
use SiteEssentials\Modules\SeoMeta\Seo_Meta_Instructions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Meta_Instructions_Command extends WP_CLI_Command {

	/**
	 * Show how SCOS SEO meta is written for a post or post type.
	 *
	 * ## OPTIONS
	 *
	 * [--post-id=<id>]
	 * : Post the meta is for. Sets the content type and shows current values.
	 *
	 * [--post-type=<type>]
	 * : Post type slug, when there is no post yet.
	 *
	 * [--content-type=<type>]
	 * : Force a content type: article, page, product, service, case-study.
	 *
	 * [--format=<format>]
	 * : json (default) for the structured data, or prompt for the plain-text
	 *   rendering used inside system prompts.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp scos seo-meta-instructions --post-id=42
	 *     $ wp scos seo-meta-instructions --post-type=product --format=prompt
	 *
	 * @subcommand seo-meta-instructions
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associated arguments (flags).
	 */
	public function __invoke( $args, $assoc_args ) {
		$post_id = isset( $assoc_args['post-id'] ) ? (int) $assoc_args['post-id'] : 0;
		$format  = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'json';

		if ( ! in_array( $format, [ 'json', 'prompt' ], true ) ) {
			WP_CLI::error( "Invalid --format value: {$format}. Allowed: json, prompt." );
		}

		if ( $post_id && ! get_post( $post_id ) ) {
			WP_CLI::error( "Post {$post_id} not found." );
		}

		$instructions = Seo_Meta_Instructions::get( [
			'post_id'      => $post_id,
			'post_type'    => isset( $assoc_args['post-type'] ) ? $assoc_args['post-type'] : '',
			'content_type' => isset( $assoc_args['content-type'] ) ? $assoc_args['content-type'] : '',
		] );

		if ( 'prompt' === $format ) {
			WP_CLI::line( Seo_Meta_Instructions::to_prompt( $instructions ) );
			return;
		}

		WP_CLI::line( wp_json_encode( $instructions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
	}
}
