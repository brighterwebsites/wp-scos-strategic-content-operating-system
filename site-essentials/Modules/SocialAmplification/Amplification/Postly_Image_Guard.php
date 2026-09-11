<?php
/**
 * Postly image guard — make sure Postly receives a JPEG or PNG.
 *
 * Postly fetches each image URL and names its copy from the content type it
 * gets back; Facebook and Instagram then reject anything that isn't JPEG/PNG
 * ("File format ".webp" is not supported on facebook").
 *
 * A .jpg URL isn't proof of a JPEG. ShortPixel's .htaccess delivery swaps in
 * `image.jpg.webp` / `.avif` for browsers that accept them, and sends no Vary
 * header — so Cloudflare caches whichever version was fetched first and serves
 * it to everyone. Seen on guerillasteelstables.com.au, 2026-09-11.
 *
 * So any upload that has a WebP/AVIF sibling, or isn't a JPEG/PNG itself, is
 * sent from a copy in uploads/scos-social/ — a folder with no siblings, where
 * the URL can only ever return the JPEG. Copies are reused across runs.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification\Amplification
 * v1.0 | 2026-09-11
 */

namespace SiteEssentials\Modules\SocialAmplification\Amplification;

defined( 'ABSPATH' ) || exit;

class Postly_Image_Guard {

	const LOG_PREFIX = '[SCOS SMA Images]';

	/** Folder under uploads/ for the copies. */
	const COPY_DIR = 'scos-social';

	/** Sent as-is when nothing on the server can swap them for another format. */
	const SAFE_MIMES = [ 'image/jpeg', 'image/png' ];

	/**
	 * The URL to give Postly for an image, or null to leave the image out.
	 */
	public static function safe_url( string $url ): ?string {
		if ( '' === $url ) {
			return null;
		}

		$path = self::local_path( $url );

		if ( null === $path ) {
			// Not a file in this site's uploads — nothing to inspect or copy.
			if ( preg_match( '/[.](webp|avif|heic|heif)$/i', (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) {
				error_log( self::LOG_PREFIX . " Leaving out {$url}: Facebook and Instagram don't accept that format, and it isn't a local file that can be converted." );
				return null;
			}
			return $url;
		}

		$mime     = (string) wp_get_image_mime( $path );
		$siblings = self::format_siblings( $path );

		if ( in_array( $mime, self::SAFE_MIMES, true ) && empty( $siblings ) ) {
			return $url;
		}

		$copy = self::safe_copy( $path, $mime );
		$name = wp_basename( $path );

		if ( null === $copy ) {
			if ( in_array( $mime, self::SAFE_MIMES, true ) ) {
				error_log( self::LOG_PREFIX . " Couldn't copy {$name} into uploads/" . self::COPY_DIR . '/ — sending the original, which the server may answer with ' . implode( ' / ', array_map( 'wp_basename', $siblings ) ) . '.' );
				return $url;
			}
			error_log( self::LOG_PREFIX . " Leaving out {$name}: it's {$mime} and couldn't be converted to JPEG." );
			return null;
		}

		error_log( self::LOG_PREFIX . ' ' . ( in_array( $mime, self::SAFE_MIMES, true )
			? "Sending a copy of {$name} — the server also has " . implode( ', ', array_map( 'wp_basename', $siblings ) ) . ' and may answer the original URL with that.'
			: "Sending {$name} ({$mime}) converted to JPEG." ) );

		return $copy;
	}

	/**
	 * The file behind a URL in this site's uploads, or null.
	 */
	private static function local_path( string $url ): ?string {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return null;
		}

		// Compare without the scheme — the stored URL and the site setting can disagree on http/https.
		$strip    = static function ( string $u ): string {
			return (string) preg_replace( '#^https?:#i', '', $u );
		};
		$clean    = $strip( strtok( $url, '?#' ) );
		$base_url = trailingslashit( $strip( $uploads['baseurl'] ) );

		if ( 0 !== strpos( $clean, $base_url ) ) {
			return null;
		}

		$path     = realpath( trailingslashit( $uploads['basedir'] ) . rawurldecode( substr( $clean, strlen( $base_url ) ) ) );
		$base_dir = realpath( $uploads['basedir'] );

		if ( ! $path || ! $base_dir || 0 !== strpos( $path, $base_dir ) || ! is_file( $path ) ) {
			return null;
		}
		return $path;
	}

	/**
	 * WebP/AVIF versions next to a file (image.jpg.webp, image.webp, …) that
	 * content-negotiation rules can serve in place of the original.
	 *
	 * @return string[]
	 */
	private static function format_siblings( string $path ): array {
		$stem  = (string) preg_replace( '/[.][^.\/]+$/', '', $path );
		$found = [];
		foreach ( [ $path . '.webp', $path . '.avif', $stem . '.webp', $stem . '.avif' ] as $candidate ) {
			if ( $candidate !== $path && is_file( $candidate ) ) {
				$found[] = $candidate;
			}
		}
		return array_values( array_unique( $found ) );
	}

	/**
	 * Copy (JPEG/PNG) or convert (anything else → JPEG) into the copy folder.
	 * The name carries a hash of the source path and mtime, so an edited image
	 * gets a fresh copy and an unchanged one is reused.
	 *
	 * @return string|null Public URL of the copy.
	 */
	private static function safe_copy( string $path, string $mime ): ?string {
		$uploads = wp_upload_dir( null, false );
		$dir     = trailingslashit( $uploads['basedir'] ) . self::COPY_DIR;
		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}

		$keep = in_array( $mime, self::SAFE_MIMES, true );
		$name = sanitize_file_name( pathinfo( $path, PATHINFO_FILENAME ) )
			. '-' . substr( md5( $path . '|' . (string) filemtime( $path ) ), 0, 8 )
			. ( 'image/png' === $mime ? '.png' : '.jpg' );
		$dest = $dir . '/' . $name;

		if ( ! is_file( $dest ) ) {
			if ( $keep ) {
				if ( ! @copy( $path, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors -- failure is handled.
					return null;
				}
			} elseif ( ! self::convert_to_jpeg( $path, $dest ) ) {
				return null;
			}
		}

		return trailingslashit( $uploads['baseurl'] ) . self::COPY_DIR . '/' . rawurlencode( $name );
	}

	private static function convert_to_jpeg( string $source, string $dest ): bool {
		$editor = wp_get_image_editor( $source );
		if ( is_wp_error( $editor ) ) {
			return false;
		}

		// A site-wide image_editor_output_format mapping (e.g. JPEG → WebP) would
		// defeat the point, so ours wins for this one save.
		$no_mapping = static function (): array {
			return [];
		};
		add_filter( 'image_editor_output_format', $no_mapping, PHP_INT_MAX );
		$saved = $editor->save( $dest, 'image/jpeg' );
		remove_filter( 'image_editor_output_format', $no_mapping, PHP_INT_MAX );

		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			return false;
		}
		if ( $saved['path'] !== $dest || 'image/jpeg' !== wp_get_image_mime( $dest ) ) {
			wp_delete_file( $saved['path'] );
			return false;
		}
		return true;
	}
}
