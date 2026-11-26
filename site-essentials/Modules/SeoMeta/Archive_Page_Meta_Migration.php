<?php
/**
 * SEO Meta — Archive page meta migration (#282)
 *
 * The posts page (Settings › Reading) and the WooCommerce shop page are served
 * as archives: their SEO comes from SEO › Archive Settings, not the Page's own
 * scos_seo_title / scos_seo_description. This copies those Page values into the
 * archive's settings where the archive's own value is empty. A non-empty
 * archive value is never overwritten. One-off; dry run unless $apply.
 *
 * v1.0 | 2026-11-26
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 */

namespace SiteEssentials\Modules\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Archive_Page_Meta_Migration {

	/** Page meta key => Archive Settings field. */
	const FIELDS = [
		'scos_seo_title'       => 'title',
		'scos_seo_description' => 'description',
	];

	/**
	 * Plan (and with $apply, make) the copy for this site.
	 *
	 * @param bool $apply Write the changes.
	 * @return array<int, array{archive:string, page_id:int, field:string, action:string, value:string}>
	 */
	public static function run( bool $apply = false ): array {
		$rows  = [];
		$pages = [ 'post' => (int) get_option( 'page_for_posts' ) ];
		if ( function_exists( 'wc_get_page_id' ) && post_type_exists( 'product' ) ) {
			$pages['product'] = (int) wc_get_page_id( 'shop' );
		}

		foreach ( $pages as $slug => $page_id ) {
			if ( $page_id <= 0 || ! get_post( $page_id ) ) {
				continue;
			}
			$opt      = 'scos_seo_archive_' . sanitize_key( $slug );
			$saved    = get_option( $opt, [] );
			$saved    = is_array( $saved ) ? $saved : [];
			$current  = Archive_Settings::get( $slug );
			$changed  = false;

			foreach ( self::FIELDS as $meta_key => $field ) {
				$page_value = trim( (string) get_post_meta( $page_id, $meta_key, true ) );
				if ( '' === $page_value ) {
					continue;
				}
				$archive_value = trim( (string) ( $current[ $field ] ?? '' ) );
				if ( '' !== $archive_value ) {
					$rows[] = [ 'archive' => $slug, 'page_id' => $page_id, 'field' => $field, 'action' => $archive_value === $page_value ? 'same' : 'keep_archive', 'value' => $page_value ];
					continue;
				}
				$rows[]          = [ 'archive' => $slug, 'page_id' => $page_id, 'field' => $field, 'action' => $apply ? 'copied' : 'would_copy', 'value' => $page_value ];
				$saved[ $field ] = $page_value;
				$changed         = true;
			}

			// Only the copied keys are added to what is saved: unsaved fields keep following the defaults.
			if ( $apply && $changed ) {
				update_option( $opt, $saved, false );
			}
		}

		if ( $apply && class_exists( '\SiteEssentials\Core\Cache_Helper' ) ) {
			\SiteEssentials\Core\Cache_Helper::flush( 'seo' );
		}
		return $rows;
	}
}
