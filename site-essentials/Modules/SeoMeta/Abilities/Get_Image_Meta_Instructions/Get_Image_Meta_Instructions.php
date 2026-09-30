<?php
/**
 * Get Image Meta Instructions — WordPress Ability
 *
 * Returns how image alt text and media titles are written: where each is
 * stored, the length limits and the rules. Any agent or tool that writes image
 * meta calls this first, so every writer follows the same rules.
 *
 * Tool ability — no AI inside, and it works on a site with no AI plugin.
 * All logic lives in Image_Meta_Instructions; this class only exposes it.
 *
 * Ability slug: scos/get-image-meta-instructions (permanent — do not rename after deployment)
 * Category:     scos-media
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\Abilities\Get_Image_Meta_Instructions
 *
 * v1.0 | 2026-09-30
 */

declare( strict_types=1 );

namespace SiteEssentials\Modules\SeoMeta\Abilities\Get_Image_Meta_Instructions;

use WP_Error;
use SiteEssentials\Core\Abilities\Abstract_Scos_Ability;
use SiteEssentials\Core\Abilities\Ability_Support;
use SiteEssentials\Modules\SeoMeta\Image_Meta_Instructions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Get_Image_Meta_Instructions extends Abstract_Scos_Ability {

	/**
	 * Register this ability with the WordPress core Abilities API.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! Ability_Support::is_abilities_api_available() ) {
			return;
		}
		wp_register_ability( 'scos/get-image-meta-instructions', [
			'label'         => __( 'SCOS: Get Image Meta Instructions', 'site-essentials' ),
			'description'   => __( 'Returns the rules for writing image alt text and media titles: where each is stored, length limits and how to write them. With an attachment_id it also returns the current values and the page the image is attached to. Call this before writing or updating image meta by any route. Read-only, no AI inside.', 'site-essentials' ),
			'category'      => 'scos-media',
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

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'default'    => [],
			'properties' => [
				'attachment_id' => [
					'type'        => 'integer',
					'description' => 'The image the meta is for. Returns its current alt text and title, and the page it is attached to.',
				],
				'fields'        => [
					'type'        => 'array',
					'description' => 'Limit the result to these fields. Omit for all.',
					'items'       => [
						'type' => 'string',
						'enum' => array_keys( Image_Meta_Instructions::fields() ),
					],
				],
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'version'       => [
					'type'        => 'string',
					'description' => 'Version of the shipped rules.',
				],
				'attachment'    => [
					'type'        => 'object',
					'description' => 'The image id and URL, when attachment_id was given.',
				],
				'attached_to'   => [
					'type'        => 'object',
					'description' => 'The page the image is attached to: id, title, type. Empty when unattached.',
				],
				'business'      => [
					'type'        => 'object',
					'description' => 'Who the business is: name, category, location, offering, description. Any may be empty.',
				],
				'fields'        => [
					'type'        => 'object',
					'description' => 'Keyed by field (alt, title). Each has meta_key or post_field, label, unit, max, optional min, format, rules and (with attachment_id) current.',
				],
				'general_rules' => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'facts'         => [
					'type'        => 'array',
					'description' => 'Image-specific facts supplied by site plugins.',
					'items'       => [ 'type' => 'string' ],
				],
				'saving'        => [
					'type'        => 'array',
					'description' => 'How to store the values.',
					'items'       => [ 'type' => 'string' ],
				],
				'notes'         => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
			],
		];
	}

	/**
	 * Execute the ability — return the instructions.
	 *
	 * @param mixed $input Validated input array.
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute_callback( $input ) {
		$input         = is_array( $input ) ? $input : [];
		$attachment_id = isset( $input['attachment_id'] ) ? absint( $input['attachment_id'] ) : 0;

		if ( $attachment_id && 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error(
				'attachment_not_found',
				/* translators: %d: Attachment ID. */
				sprintf( esc_html__( 'Attachment with ID %d not found.', 'site-essentials' ), $attachment_id ),
				[ 'status' => 404 ]
			);
		}

		return Image_Meta_Instructions::get( $input );
	}

	/**
	 * @param mixed $input Validated input array.
	 * @return bool|WP_Error
	 */
	public function permission_callback( $input ) {
		return current_user_can( 'upload_files' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function meta(): array {
		return [
			'show_in_rest' => true,
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'mcp'          => [
				'public' => true,
				'type'   => 'tool',
			],
		];
	}
}

add_action( 'wp_abilities_api_init', [ Get_Image_Meta_Instructions::class, 'register' ] );
