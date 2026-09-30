<?php
/**
 * AI Knowledge — reader for per-site knowledge files.
 *
 * Sites may keep brand and strategy notes as markdown in
 * wp-content/ai-knowledge/. This class is the shared way to read one: it
 * returns an empty string when the file is absent, so callers can always carry
 * on without it.
 *
 * TODO: Caption_Generator (Social Amplification) has its own private copy of
 *       this read + UTF-8 logic, plus the HTTP access guard for the directory.
 *       Point it here when that module is next touched.
 *
 * @package    SiteEssentials
 * @subpackage Core
 *
 * v1.0 | 2026-09-30
 */

declare( strict_types=1 );

namespace SiteEssentials\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ai_Knowledge {

	/** Directory under WP_CONTENT_DIR that holds the knowledge files. */
	const DIR = 'ai-knowledge';

	/**
	 * Absolute path for a knowledge file, or an empty string for an unsafe name.
	 *
	 * Only a bare markdown filename is accepted — no sub-directories — so a
	 * caller-supplied name can never walk out of the knowledge directory.
	 *
	 * @param string $filename e.g. `205-brand-voice.md`.
	 * @return string
	 */
	public static function path( string $filename ): string {
		if ( $filename !== basename( $filename ) || ! preg_match( '/^[A-Za-z0-9._-]+\.md$/', $filename ) ) {
			return '';
		}

		return trailingslashit( WP_CONTENT_DIR ) . self::DIR . '/' . $filename;
	}

	/**
	 * Whether a knowledge file exists and can be read.
	 *
	 * @param string $filename e.g. `205-brand-voice.md`.
	 * @return bool
	 */
	public static function exists( string $filename ): bool {
		$path = self::path( $filename );

		return '' !== $path && is_file( $path ) && is_readable( $path );
	}

	/**
	 * Read a knowledge file as valid UTF-8.
	 *
	 * Files saved from Windows as "ANSI" (Windows-1252) carry dashes and smart
	 * quotes as single bytes that are not valid UTF-8, and the WP AI Client
	 * rejects a whole request because of them. A file is saved in one encoding,
	 * so converting it as a whole is safe.
	 *
	 * @param string $filename e.g. `205-brand-voice.md`.
	 * @return string File contents, or an empty string when absent or unreadable.
	 */
	public static function read( string $filename ): string {
		if ( ! self::exists( $filename ) ) {
			return '';
		}

		$content = file_get_contents( self::path( $filename ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $content || '' === $content ) {
			return '';
		}

		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $content, 'UTF-8' ) ) {
			error_log( "[SCOS AI Knowledge] ai-knowledge/{$filename} is not UTF-8 — converted from Windows-1252. Resave it as UTF-8 to silence this." );
			$converted = mb_convert_encoding( $content, 'UTF-8', 'Windows-1252' );
			$content   = is_string( $converted ) ? $converted : $content;
		}

		return trim( $content );
	}
}
