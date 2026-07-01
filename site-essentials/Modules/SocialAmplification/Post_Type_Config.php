<?php
/**
 * Post Type Config
 *
 * Service class that resolves the effective Social Amplification configuration
 * for a given post type. Merges per-type overrides on top of global defaults.
 *
 * Option keys:
 *   scos_sa_pt_config             — serialized array, keyed by post_type slug
 *   scos_sa_postly_frames         — JSON array of framing angle strings (global)
 *   scos_sa_postly_max_images     — int, global max images per post (default 4)
 *   scos_sa_postly_no_featured    — int, globally disable featured image
 *   scos_sa_postly_no_attachments — int, globally disable image attachments
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification
 * v1.0 | 2026-07-01
 */

namespace SiteEssentials\Modules\SocialAmplification;

defined( 'ABSPATH' ) || exit;

class Post_Type_Config {

	const OPTION_KEY           = 'scos_sa_pt_config';
	const FRAMES_OPTION        = 'scos_sa_postly_frames';
	const MAX_IMAGES_OPTION    = 'scos_sa_postly_max_images';
	const NO_FEATURED_OPTION   = 'scos_sa_postly_no_featured';
	const NO_ATTACH_OPTION     = 'scos_sa_postly_no_attachments';
	const DEFAULT_MAX_IMAGES   = 4;

	const DEFAULT_FRAMES = [
		'Storytelling angle — draw the reader into the project.',
		'Results / outcome angle — focus on what was delivered and why it holds up.',
		'Behind-the-scenes / process angle — tease the craft or a specific build decision.',
	];

	// ─────────────────────────────────────────────────────────────────────────
	// Registry
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Return all publicly queryable post types (plus bw_reviews if registered).
	 * Keyed by slug, value is the human-readable singular label.
	 *
	 * @return array<string, string>
	 */
	public static function get_all_registered(): array {
		$objects = get_post_types( [ 'publicly_queryable' => true ], 'objects' );

		$exclude = [ 'attachment', 'revision', 'nav_menu_item' ];
		$result  = [];

		foreach ( $objects as $slug => $obj ) {
			if ( in_array( $slug, $exclude, true ) ) {
				continue;
			}
			$result[ $slug ] = $obj->labels->singular_name ?? $slug;
		}

		// bw_reviews may not be publicly_queryable but is a primary SA post type.
		if ( post_type_exists( 'bw_reviews' ) && ! isset( $result['bw_reviews'] ) ) {
			$obj                   = get_post_type_object( 'bw_reviews' );
			$result['bw_reviews'] = $obj ? ( $obj->labels->singular_name ?? 'Review' ) : 'Review';
		}

		return $result;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Global defaults
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Return the configured global framing angles (falls back to hardcoded defaults).
	 *
	 * @return string[]
	 */
	public static function get_global_frames(): array {
		$raw = (string) get_option( self::FRAMES_OPTION, '' );
		if ( $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$frames = array_values( array_filter( array_map( 'strval', $decoded ), 'strlen' ) );
				if ( ! empty( $frames ) ) {
					return $frames;
				}
			}
		}
		return self::DEFAULT_FRAMES;
	}

	/**
	 * Return the global defaults used when no per-type override is active.
	 *
	 * @return array{enabled: bool, post_count: int, no_featured: bool, no_attachments: bool, acf_gallery_keys: string, max_images: int, frames: string[]}
	 */
	public static function get_global_defaults(): array {
		return [
			'enabled'          => false,
			'post_count'       => max( 1, (int) get_option( 'scos_sa_postly_post_count', 3 ) ),
			'no_featured'      => (bool) get_option( self::NO_FEATURED_OPTION, 0 ),
			'no_attachments'   => (bool) get_option( self::NO_ATTACH_OPTION, 0 ),
			'acf_gallery_keys' => (string) get_option( 'bw_social_acf_gallery_keys', '' ),
			'max_images'       => max( 1, (int) get_option( self::MAX_IMAGES_OPTION, self::DEFAULT_MAX_IMAGES ) ),
			'frames'           => self::get_global_frames(),
		];
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Per-type config storage
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Return all saved per-type configs as a raw array keyed by post_type slug.
	 *
	 * @return array<string, array>
	 */
	public static function get_all_configs(): array {
		$saved = get_option( self::OPTION_KEY, [] );
		return is_array( $saved ) ? $saved : [];
	}

	/**
	 * Resolve the effective config for a given post type.
	 * Returns the per-type override when enabled; otherwise returns global defaults.
	 *
	 * @param  string $post_type WP post type slug.
	 * @return array{enabled: bool, post_count: int, no_featured: bool, no_attachments: bool, acf_gallery_keys: string, max_images: int, frames: string[]}
	 */
	public static function get_config( string $post_type ): array {
		$all    = self::get_all_configs();
		$pt_cfg = $all[ $post_type ] ?? [];
		$global = self::get_global_defaults();

		if ( ! empty( $pt_cfg['enabled'] ) ) {
			$frames = ( ! empty( $pt_cfg['frames'] ) && is_array( $pt_cfg['frames'] ) )
				? array_values( array_filter( array_map( 'strval', $pt_cfg['frames'] ), 'strlen' ) )
				: $global['frames'];

			if ( empty( $frames ) ) {
				$frames = $global['frames'];
			}

			return [
				'enabled'          => true,
				'post_count'       => isset( $pt_cfg['post_count'] )
					? max( 1, (int) $pt_cfg['post_count'] )
					: $global['post_count'],
				'no_featured'      => isset( $pt_cfg['no_featured'] )
					? (bool) $pt_cfg['no_featured']
					: $global['no_featured'],
				'no_attachments'   => isset( $pt_cfg['no_attachments'] )
					? (bool) $pt_cfg['no_attachments']
					: $global['no_attachments'],
				'acf_gallery_keys' => isset( $pt_cfg['acf_gallery_keys'] )
					? (string) $pt_cfg['acf_gallery_keys']
					: $global['acf_gallery_keys'],
				'max_images'       => isset( $pt_cfg['max_images'] )
					? max( 1, (int) $pt_cfg['max_images'] )
					: $global['max_images'],
				'frames'           => $frames,
			];
		}

		return $global;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Persistence
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Sanitize and save the complete per-type config array.
	 * Only registered post types are persisted; unknown slugs are silently dropped.
	 *
	 * @param array<string, array> $data Raw POST data keyed by post_type slug.
	 */
	public static function save_all( array $data ): void {
		$registered = array_keys( self::get_all_registered() );
		$clean      = [];

		foreach ( $registered as $slug ) {
			if ( ! isset( $data[ $slug ] ) ) {
				continue;
			}
			$raw = $data[ $slug ];

			$frames = [];
			if ( isset( $raw['frames'] ) && is_array( $raw['frames'] ) ) {
				foreach ( $raw['frames'] as $f ) {
					$f = sanitize_textarea_field( (string) $f );
					if ( '' !== $f ) {
						$frames[] = $f;
					}
				}
			}

			$clean[ $slug ] = [
				'enabled'          => ! empty( $raw['enabled'] ),
				'post_count'       => max( 1, absint( $raw['post_count'] ?? 3 ) ),
				'no_featured'      => ! empty( $raw['no_featured'] ),
				'no_attachments'   => ! empty( $raw['no_attachments'] ),
				'acf_gallery_keys' => sanitize_text_field( $raw['acf_gallery_keys'] ?? '' ),
				'max_images'       => max( 1, absint( $raw['max_images'] ?? self::DEFAULT_MAX_IMAGES ) ),
				'frames'           => $frames,
			];
		}

		update_option( self::OPTION_KEY, $clean );
	}
}
