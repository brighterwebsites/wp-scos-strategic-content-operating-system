<?php
/**
 * EXIF / Metadata Stripper
 *
 * Removes EXIF, IPTC, and XMP metadata from uploaded images, either fully
 * (nuclear) or selectively (keeping configured XMP namespace:localname fields).
 *
 * Nuclear   — Imagick::stripImage() keeping only the ICC color profile.
 *             Falls back to GD re-save if Imagick is unavailable.
 * Selective — Nuclear strip, then re-injects a DOM-filtered XMP block
 *             containing only the fields listed in the "keep" config.
 *             Requires Imagick; degrades to nuclear when Imagick is absent.
 *
 * IMPORTANT: The upload hook fires at wp_generate_attachment_metadata
 * priority 99, ensuring MLA (Media Library Assistant) and other plugins
 * have finished reading metadata before we strip it.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Exif_Stripper {

	const OPTION_KEY = 'scos_exif_stripper';

	/** MIME types we can safely strip. */
	const SUPPORTED_MIME = [
		'image/jpeg',
		'image/png',
		'image/webp',
		'image/tiff',
	];

	// ── Defaults / getters ────────────────────────────────────────────────────

	public static function defaults(): array {
		return [
			'upload_mode'    => 'disabled', // 'disabled' | 'nuclear' | 'selective'
			'fields_to_keep' => self::default_keep_fields_str(),
		];
	}

	public static function get(): array {
		return wp_parse_args(
			(array) get_option( self::OPTION_KEY, [] ),
			self::defaults()
		);
	}

	/**
	 * Default "fields to keep" for selective mode — copyright, attribution,
	 * accessibility. Strips all camera/lens/GPS metadata.
	 */
	public static function default_keep_fields_str(): string {
		return implode( "\n", [
			'# Dublin Core — creator, rights, description, title',
			'dc:creator',
			'dc:rights',
			'dc:description',
			'dc:title',
			'dc:subject',
			'',
			'# XMP Rights Management',
			'xmpRights:Marked',
			'xmpRights:WebStatement',
			'xmpRights:UsageTerms',
			'',
			'# IPTC Core — accessibility & location',
			'Iptc4xmpCore:AltTextAccessibility',
			'Iptc4xmpCore:Location',
			'Iptc4xmpCore:CountryCode',
			'Iptc4xmpCore:City',
			'Iptc4xmpCore:ProvinceState',
			'',
			'# Photoshop — credit, source',
			'photoshop:Credit',
			'photoshop:Source',
		] );
	}

	/** Parse the keep-fields textarea into a sanitised string[] */
	private static function get_keep_fields(): array {
		$opts = self::get();
		$raw  = $opts['fields_to_keep'] ?? '';
		$out  = [];
		foreach ( explode( "\n", $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}
			// Accept 'prefix:localname' only — basic validation
			if ( preg_match( '/^[a-zA-Z0-9_]+:[a-zA-Z0-9_.]+$/', $line ) ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	// ── Bootstrap ─────────────────────────────────────────────────────────────

	public static function init(): void {
		$opts = self::get();

		if ( 'disabled' !== $opts['upload_mode'] ) {
			// Priority 99 — fires after MLA and other plugins have read metadata.
			add_filter( 'wp_generate_attachment_metadata', [ __CLASS__, 'strip_on_upload' ], 99, 2 );
		}

		// AJAX bulk handlers — always register so the buttons work regardless of upload_mode.
		add_action( 'wp_ajax_scos_exif_get_ids',     [ __CLASS__, 'ajax_get_image_ids' ] );
		add_action( 'wp_ajax_scos_exif_strip_batch', [ __CLASS__, 'ajax_strip_batch' ] );
	}

	// ── Upload hook ───────────────────────────────────────────────────────────

	/**
	 * Strip metadata from the original file after thumbnails are generated.
	 *
	 * Hooked to wp_generate_attachment_metadata filter (must return $meta).
	 *
	 * @param  array $meta          Attachment metadata array.
	 * @param  int   $attachment_id Attachment post ID.
	 * @return array Unchanged metadata (we only modify the physical file).
	 */
	public static function strip_on_upload( array $meta, int $attachment_id ): array {
		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! file_exists( $path ) ) {
			return $meta;
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, self::SUPPORTED_MIME, true ) ) {
			return $meta;
		}

		$opts = self::get();
		if ( 'nuclear' === $opts['upload_mode'] ) {
			self::nuclear_strip( $path );
		} elseif ( 'selective' === $opts['upload_mode'] ) {
			self::selective_strip( $path, self::get_keep_fields() );
		}

		return $meta;
	}

	// ── Strip methods ─────────────────────────────────────────────────────────

	/**
	 * Nuclear strip — removes all metadata profiles.
	 * ICC color profile is preserved to prevent color shifts.
	 *
	 * @param  string $path Absolute path to the image file.
	 * @return bool True on success.
	 */
	public static function nuclear_strip( string $path ): bool {
		if ( class_exists( 'Imagick' ) ) {
			return self::nuclear_strip_imagick( $path );
		}
		return self::nuclear_strip_gd( $path );
	}

	private static function nuclear_strip_imagick( string $path ): bool {
		try {
			$img = new \Imagick( $path );

			// Preserve ICC so colors render correctly after stripping.
			$icc = null;
			try {
				$icc = $img->getImageProfile( 'icc' );
			} catch ( \ImagickException $e ) {
				// No ICC — that's fine.
			}

			// Explicitly remove named profiles BEFORE stripImage().
			// Imagick's stripImage() can leave a corrupted APP1 EXIF marker in
			// the JPEG stream if it only zeroes the data without removing the
			// marker header, causing PHP exif_read_data() to throw an E_WARNING.
			// Removing them first ensures clean output.
			foreach ( [ 'exif', 'iptc', 'xmp', '8bim', 'psict', 'iptc-na' ] as $profile ) {
				try {
					$img->removeImageProfile( $profile );
				} catch ( \ImagickException $e ) {
					// Profile not present — fine.
				}
			}

			// Belt-and-suspenders: strip catches anything not named above.
			$img->stripImage();

			if ( $icc ) {
				$img->setImageProfile( 'icc', $icc );
			}

			$img->writeImage( $path );
			$img->clear();
			$img->destroy();
			return true;

		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * GD re-save strips EXIF metadata from JPEG/PNG/WebP.
	 * Used as Imagick fallback for nuclear strip.
	 */
	private static function nuclear_strip_gd( string $path ): bool {
		$type = @exif_imagetype( $path );
		if ( ! $type ) {
			return false;
		}

		switch ( $type ) {
			case IMAGETYPE_JPEG:
				$img = @imagecreatefromjpeg( $path );
				if ( ! $img ) {
					return false;
				}
				$ok = imagejpeg( $img, $path, 85 );
				imagedestroy( $img );
				return (bool) $ok;

			case IMAGETYPE_PNG:
				$img = @imagecreatefrompng( $path );
				if ( ! $img ) {
					return false;
				}
				imagealphablending( $img, false );
				imagesavealpha( $img, true );
				$ok = imagepng( $img, $path, 6 );
				imagedestroy( $img );
				return (bool) $ok;

			case IMAGETYPE_WEBP:
				if ( ! function_exists( 'imagecreatefromwebp' ) ) {
					return false;
				}
				$img = @imagecreatefromwebp( $path );
				if ( ! $img ) {
					return false;
				}
				$ok = imagewebp( $img, $path, 85 );
				imagedestroy( $img );
				return (bool) $ok;
		}

		return false;
	}

	/**
	 * Selective strip — nuclear + re-inject filtered XMP.
	 * EXIF and IPTC binary profiles are always stripped entirely.
	 * Requires Imagick; falls back to nuclear GD strip when unavailable.
	 *
	 * @param  string   $path          Absolute path to the image.
	 * @param  string[] $fields_to_keep List of 'namespace:localname' tags to keep.
	 * @return bool
	 */
	public static function selective_strip( string $path, array $fields_to_keep ): bool {
		if ( ! class_exists( 'Imagick' ) ) {
			return self::nuclear_strip_gd( $path );
		}

		try {
			$img = new \Imagick( $path );

			// Read XMP and ICC before stripping.
			$xmp = null;
			try {
				$xmp = $img->getImageProfile( 'xmp' );
			} catch ( \ImagickException $e ) {
				// No XMP profile.
			}

			$icc = null;
			try {
				$icc = $img->getImageProfile( 'icc' );
			} catch ( \ImagickException $e ) {
				// No ICC.
			}

			// Explicitly remove named profiles first (prevents corrupted APP1 markers).
			foreach ( [ 'exif', 'iptc', 'xmp', '8bim', 'psict', 'iptc-na' ] as $profile ) {
				try {
					$img->removeImageProfile( $profile );
				} catch ( \ImagickException $e ) {}
			}
			$img->stripImage();

			if ( $icc ) {
				$img->setImageProfile( 'icc', $icc );
			}

			if ( $xmp && ! empty( $fields_to_keep ) ) {
				$filtered = self::filter_xmp( $xmp, $fields_to_keep );
				if ( $filtered ) {
					$img->setImageProfile( 'xmp', $filtered );
				}
			}

			$img->writeImage( $path );
			$img->clear();
			$img->destroy();
			return true;

		} catch ( \Exception $e ) {
			return false;
		}
	}

	// ── XMP DOM filter ────────────────────────────────────────────────────────

	/**
	 * Filter an XMP XML blob, keeping only the specified namespace:localname
	 * elements from rdf:Description nodes.
	 *
	 * @param  string   $xmp_raw      Raw XMP profile bytes.
	 * @param  string[] $keep_fields  e.g. ['dc:creator', 'xmpRights:Marked'].
	 * @return string   Filtered XMP XML, or empty string on failure.
	 */
	public static function filter_xmp( string $xmp_raw, array $keep_fields ): string {
		if ( empty( $keep_fields ) || '' === trim( $xmp_raw ) ) {
			return '';
		}

		$prev_errors = libxml_use_internal_errors( true );
		$dom         = new \DOMDocument( '1.0', 'UTF-8' );
		$loaded      = $dom->loadXML( $xmp_raw );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev_errors );

		if ( ! $loaded ) {
			return '';
		}

		$xpath = new \DOMXPath( $dom );
		$xpath->registerNamespace( 'rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#' );

		$descriptions = $xpath->query( '//rdf:Description' );
		if ( ! $descriptions ) {
			return '';
		}

		foreach ( $descriptions as $desc ) {
			$to_remove = [];

			foreach ( $desc->childNodes as $child ) {
				if ( $child->nodeType !== XML_ELEMENT_NODE ) {
					continue;
				}

				$tag = $child->prefix . ':' . $child->localName;

				if ( ! in_array( $tag, $keep_fields, true ) ) {
					$to_remove[] = $child;
				}
			}

			foreach ( $to_remove as $node ) {
				$desc->removeChild( $node );
			}
		}

		$xml = $dom->saveXML();
		return $xml ?: '';
	}

	// ── AJAX — bulk processing ────────────────────────────────────────────────

	/** Return all image attachment IDs for the bulk strip operation. */
	public static function ajax_get_image_ids(): void {
		check_ajax_referer( 'scos_exif_bulk', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
		}

		$ids = get_posts( [
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => 'inherit',
		] );

		wp_send_json_success( [
			'ids'   => array_map( 'absint', $ids ),
			'total' => count( $ids ),
		] );
	}

	/**
	 * Process a batch of attachment IDs.
	 *
	 * Expected POST params:
	 *   nonce  — wp_nonce 'scos_exif_bulk'
	 *   mode   — 'nuclear' | 'selective'
	 *   ids    — JSON-encoded array of attachment IDs
	 */
	public static function ajax_strip_batch(): void {
		check_ajax_referer( 'scos_exif_bulk', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
		}

		$mode    = in_array( $_POST['mode'] ?? '', [ 'nuclear', 'selective' ], true )
			? sanitize_key( $_POST['mode'] )
			: 'nuclear';
		$raw_ids = isset( $_POST['ids'] )
			? json_decode( sanitize_text_field( wp_unslash( $_POST['ids'] ) ), true )
			: [];
		$ids     = array_map( 'absint', (array) $raw_ids );

		$processed = 0;
		$failed    = [];
		$skipped   = 0;

		$keep_fields = ( 'selective' === $mode ) ? self::get_keep_fields() : [];

		foreach ( $ids as $id ) {
			$path = get_attached_file( $id );

			if ( ! $path || ! file_exists( $path ) ) {
				++$skipped;
				continue;
			}

			$mime = get_post_mime_type( $id );
			if ( ! in_array( $mime, self::SUPPORTED_MIME, true ) ) {
				++$skipped;
				continue;
			}

			$ok = ( 'nuclear' === $mode )
				? self::nuclear_strip( $path )
				: self::selective_strip( $path, $keep_fields );

			if ( $ok ) {
				++$processed;
			} else {
				$failed[] = $id;
			}
		}

		wp_send_json_success( [
			'processed' => $processed,
			'failed'    => $failed,
			'skipped'   => $skipped,
		] );
	}

	// ── Save ─────────────────────────────────────────────────────────────────

	/**
	 * Called from Image_SEO::handle_save() after nonce + capability checks.
	 *
	 * @param array $post Raw wp_unslash'd $_POST data.
	 */
	public static function save( array $post ): void {
		$p = ( isset( $post['scos_exif'] ) && is_array( $post['scos_exif'] ) )
			? $post['scos_exif']
			: [];

		$mode = $p['upload_mode'] ?? 'disabled';
		if ( ! in_array( $mode, [ 'disabled', 'nuclear', 'selective' ], true ) ) {
			$mode = 'disabled';
		}

		update_option(
			self::OPTION_KEY,
			[
				'upload_mode'    => $mode,
				'fields_to_keep' => sanitize_textarea_field( $p['fields_to_keep'] ?? self::default_keep_fields_str() ),
			],
			false
		);
	}

	// ── Utility ───────────────────────────────────────────────────────────────

	public static function imagick_available(): bool {
		return class_exists( 'Imagick' );
	}
}
