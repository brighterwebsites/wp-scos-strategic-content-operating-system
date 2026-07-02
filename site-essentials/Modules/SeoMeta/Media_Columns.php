<?php
/**
 * Media Library — Extra Admin Columns
 *
 * Adds opt-in columns to the media library list table (upload.php):
 *   scos_media_alt      — Alt text (shows "—" when empty; highlights empty in red)
 *   scos_media_fileinfo — MIME type + original upload dimensions + human-readable file size
 *
 * Only registered when the `extra_media_columns` toggle in Image_SEO settings
 * is enabled. Toggle path: SEO › Advanced › Image SEO → "Extra Media Library Columns".
 *
 * Note: caption is already available as a standard WP core media column and
 * does not need to be added here.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 *
 * v1.0 | 2026-07-01
 */

namespace SiteEssentials\Modules\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Media_Columns {

	const COL_ALT      = 'scos_media_alt';
	const COL_FILEINFO = 'scos_media_fileinfo';

	// ── Bootstrap ─────────────────────────────────────────────────────────────

	public static function init(): void {
		$opts = Image_SEO::get();
		if ( empty( $opts['extra_media_columns'] ) ) {
			return;
		}

		add_filter( 'manage_media_columns',        [ __CLASS__, 'add_columns' ] );
		add_action( 'manage_media_custom_column',  [ __CLASS__, 'render_column' ], 10, 2 );
		add_filter( 'manage_upload_sortable_columns', [ __CLASS__, 'sortable_columns' ] );
		add_action( 'admin_head-upload.php',       [ __CLASS__, 'column_styles' ] );
	}

	// ── Column definitions ────────────────────────────────────────────────────

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public static function add_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			// Insert our columns after the "title" column.
			if ( 'title' === $key ) {
				$new[ self::COL_ALT ]      = __( 'Alt Text', 'site-essentials' );
				$new[ self::COL_FILEINFO ] = __( 'File Info', 'site-essentials' );
			}
		}
		return $new;
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public static function sortable_columns( array $columns ): array {
		$columns[ self::COL_ALT ] = self::COL_ALT;
		return $columns;
	}

	// ── Column rendering ──────────────────────────────────────────────────────

	/**
	 * @param string $column_name
	 * @param int    $post_id
	 */
	public static function render_column( string $column_name, int $post_id ): void {
		if ( self::COL_ALT === $column_name ) {
			self::render_alt( $post_id );
		} elseif ( self::COL_FILEINFO === $column_name ) {
			self::render_fileinfo( $post_id );
		}
	}

	// ── Private renderers ─────────────────────────────────────────────────────

	private static function render_alt( int $post_id ): void {
		$alt = (string) get_post_meta( $post_id, '_wp_attachment_image_alt', true );

		if ( '' === $alt ) {
			echo '<span style="color:#b32d2e;font-style:italic;">' . esc_html__( '— missing —', 'site-essentials' ) . '</span>';
			return;
		}

		// Truncate long alt text for display; show full on hover.
		$display = mb_strlen( $alt ) > 80 ? mb_substr( $alt, 0, 77 ) . '…' : $alt;
		echo '<span title="' . esc_attr( $alt ) . '">' . esc_html( $display ) . '</span>';
	}

	private static function render_fileinfo( int $post_id ): void {
		$mime = get_post_mime_type( $post_id );
		if ( ! $mime ) {
			echo '—';
			return;
		}

		// Human-friendly MIME label.
		$mime_label = strtoupper( (string) pathinfo( (string) get_attached_file( $post_id ), PATHINFO_EXTENSION ) );
		if ( '' === $mime_label ) {
			// Fall back to last part of MIME string (e.g. "jpeg" from "image/jpeg").
			$parts      = explode( '/', $mime );
			$mime_label = strtoupper( end( $parts ) );
		}

		$lines = [ $mime_label ];

		// Original dimensions + file size from attachment metadata.
		$meta = wp_get_attachment_metadata( $post_id );
		if ( is_array( $meta ) ) {
			if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				$lines[] = absint( $meta['width'] ) . '×' . absint( $meta['height'] ) . 'px';
			}
		}

		// File size from the physical file.
		$file = get_attached_file( $post_id );
		if ( $file && file_exists( $file ) ) {
			$bytes = filesize( $file );
			if ( false !== $bytes ) {
				$lines[] = size_format( $bytes, 1 );
			}
		}

		echo esc_html( implode( ' · ', $lines ) );
	}

	// ── Inline CSS for column widths ──────────────────────────────────────────

	public static function column_styles(): void {
		echo '<style>';
		echo '.column-' . esc_attr( self::COL_ALT )      . '{ width:200px; }';
		echo '.column-' . esc_attr( self::COL_FILEINFO )  . '{ width:130px; }';
		echo '</style>';
	}
}
