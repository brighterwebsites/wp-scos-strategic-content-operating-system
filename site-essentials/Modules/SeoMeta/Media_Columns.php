<?php
/**
 * Media Library — Extra Admin Columns
 *
 * Adds opt-in columns to the WordPress media library list table (upload.php):
 *   scos_media_alt      — Alt text (shows "—" when empty; highlights empty in red)
 *   scos_media_fileinfo — MIME type + original upload dimensions + human-readable file size
 *
 * When Media Library Assistant (MLA) controls the upload.php list table, only the
 * File Info column is added here — MLA already provides a native Alt Text column
 * in Screen Options. Enable MLA's Alt Text column there instead of duplicating it.
 *
 * Toggle path: SEO › Advanced › Image SEO → "Extra Media Library Columns".
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 *
 * v1.0 | 2026-07-01
 * v1.1 | 2026-07-02 — Add MLA list table column hooks; gate inside callbacks not at init.
 * v1.2 | 2026-07-02 — WP core table: Alt + File Info. MLA table: File Info only.
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
		// Standard WP media library list table (upload.php when MLA view support is off).
		add_filter( 'manage_media_columns',           [ __CLASS__, 'add_wp_columns' ], 10, 2 );
		add_action( 'manage_media_custom_column',     [ __CLASS__, 'render_column' ], 10, 2 );
		add_filter( 'manage_upload_sortable_columns', [ __CLASS__, 'sortable_columns' ] );
		add_action( 'admin_head-upload.php',          [ __CLASS__, 'column_styles' ] );

		// MLA list table — upload.php (when MLA view is on) and Media/Assistant submenu.
		// Register on init priority 0 so MLA picks up columns on first table build.
		add_action( 'init', [ __CLASS__, 'register_mla_column_hooks' ], 0 );
	}

	/**
	 * Register MLA column hooks early on init (MLA recommendation).
	 */
	public static function register_mla_column_hooks(): void {
		add_filter( 'mla_list_table_get_columns',          [ __CLASS__, 'add_mla_columns' ] );
		add_filter( 'mla_list_table_column_default',       [ __CLASS__, 'mla_render_column' ], 10, 3 );
		add_filter( 'mla_list_table_get_sortable_columns', [ __CLASS__, 'sortable_columns' ] );
		add_action( 'admin_head-media_page_mla-menu',      [ __CLASS__, 'column_styles' ] );
	}

	/**
	 * Whether extra media columns are enabled in settings.
	 */
	private static function is_enabled(): bool {
		$opts = Image_SEO::get();
		return ! empty( $opts['extra_media_columns'] );
	}

	// ── Column definitions ────────────────────────────────────────────────────

	/**
	 * Core WP media list table — Alt Text + File Info.
	 *
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public static function add_wp_columns( array $columns ): array {
		if ( ! self::is_enabled() ) {
			return $columns;
		}

		return self::insert_columns_after_title( $columns, [
			self::COL_ALT      => __( 'Alt Text', 'site-essentials' ),
			self::COL_FILEINFO => __( 'File Info', 'site-essentials' ),
		] );
	}

	/**
	 * MLA list table — File Info only (MLA provides Alt Text via Screen Options).
	 *
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public static function add_mla_columns( array $columns ): array {
		if ( ! self::is_enabled() ) {
			return $columns;
		}

		return self::insert_columns_after_title( $columns, [
			self::COL_FILEINFO => __( 'File Info', 'site-essentials' ),
		] );
	}

	/**
	 * Insert columns after the title column (WP or MLA slug).
	 *
	 * @param array<string, string> $columns
	 * @param array<string, string> $to_add
	 * @return array<string, string>
	 */
	private static function insert_columns_after_title( array $columns, array $to_add ): array {
		$new        = [];
		$inserted   = false;
		$after_keys = [ 'title_name', 'title', 'post_title' ];

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( in_array( $key, $after_keys, true ) ) {
				foreach ( $to_add as $col_key => $col_label ) {
					$new[ $col_key ] = $col_label;
				}
				$inserted = true;
			}
		}

		if ( ! $inserted ) {
			$new = array_merge( $new, $to_add );
		}

		return $new;
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public static function sortable_columns( array $columns ): array {
		if ( ! self::is_enabled() ) {
			return $columns;
		}
		$columns[ self::COL_ALT ] = self::COL_ALT;
		return $columns;
	}

	// ── Column rendering ──────────────────────────────────────────────────────

	/**
	 * Standard WP media list table column renderer.
	 *
	 * @param string     $column_name
	 * @param int|string $post_id
	 */
	public static function render_column( string $column_name, $post_id ): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		self::output_column( $column_name, absint( $post_id ) );
	}

	/**
	 * MLA list table column renderer.
	 *
	 * @param mixed  $content     Existing content (null when unhandled).
	 * @param array  $item        Row data.
	 * @param string $column_name Column slug.
	 * @return mixed
	 */
	public static function mla_render_column( $content, $item, $column_name ) {
		if ( ! self::is_enabled() || self::COL_FILEINFO !== $column_name ) {
			return $content;
		}

		$post_id = isset( $item['ID'] ) ? absint( $item['ID'] ) : 0;
		if ( ! $post_id ) {
			return $content;
		}

		ob_start();
		self::render_fileinfo( $post_id );
		return ob_get_clean();
	}

	/**
	 * Shared output for WP column renderer.
	 */
	private static function output_column( string $column_name, int $post_id ): void {
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

		$display = mb_strlen( $alt ) > 80 ? mb_substr( $alt, 0, 77 ) . '…' : $alt;
		echo '<span title="' . esc_attr( $alt ) . '">' . esc_html( $display ) . '</span>';
	}

	private static function render_fileinfo( int $post_id ): void {
		$mime = get_post_mime_type( $post_id );
		if ( ! $mime ) {
			echo '—';
			return;
		}

		$mime_label = strtoupper( (string) pathinfo( (string) get_attached_file( $post_id ), PATHINFO_EXTENSION ) );
		if ( '' === $mime_label ) {
			$parts      = explode( '/', $mime );
			$mime_label = strtoupper( end( $parts ) );
		}

		$lines = [ $mime_label ];

		$meta = wp_get_attachment_metadata( $post_id );
		if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			$lines[] = absint( $meta['width'] ) . '×' . absint( $meta['height'] ) . 'px';
		}

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
		if ( ! self::is_enabled() ) {
			return;
		}
		echo '<style>';
		echo '.column-' . esc_attr( self::COL_ALT )     . '{ width:200px; }';
		echo '.column-' . esc_attr( self::COL_FILEINFO ) . '{ width:130px; }';
		echo '</style>';
	}
}
