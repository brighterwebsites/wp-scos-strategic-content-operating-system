<?php
/**
 * SEO Meta — Sitemap noindex cleanup (#281)
 *
 * Before #281 the meta box wrote 'xml' into scos_seo_sitemap_exclude whenever a
 * post was saved as noindex, and set scos_seo_sitemap_noindex_auto. The sitemap
 * now derives that from noindex itself, so the stored 'xml' is noise that reports
 * as excluded_by_post. This removes it and the flag.
 *
 * A post that is no longer noindex keeps its 'xml' exclusion (only the flag goes),
 * so no post enters the XML sitemap because of this cleanup. Dry run unless $apply.
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

class Sitemap_Noindex_Cleanup {

	/**
	 * @param bool $apply Write the changes.
	 * @return array<int, array{post_id:int, type:string, action:string}>
	 *         action: remove_xml (noindex: no sitemap change), keep_xml (no longer
	 *         noindex: exclusion kept, flag cleared), flag_only (no 'xml' stored).
	 */
	public static function run( bool $apply = false ): array {
		$ids = get_posts( [
			'post_type'        => 'any',
			'post_status'      => 'any',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_key'         => 'scos_seo_sitemap_noindex_auto',
			'meta_value'       => '1',
		] );
		if ( empty( $ids ) ) {
			return [];
		}
		update_meta_cache( 'post', $ids );

		$rows = [];
		foreach ( $ids as $id ) {
			$id      = (int) $id;
			$exclude = array_values( array_filter( (array) get_post_meta( $id, 'scos_seo_sitemap_exclude', true ) ) );
			if ( ! in_array( 'xml', $exclude, true ) ) {
				$action = 'flag_only';
			} elseif ( Url_Seo_State::post_is_noindex( $id ) ) {
				$action = 'remove_xml';
			} else {
				$action = 'keep_xml';
			}
			$rows[] = [ 'post_id' => $id, 'type' => (string) get_post_type( $id ), 'action' => $action ];

			if ( $apply ) {
				if ( 'remove_xml' === $action ) {
					update_post_meta( $id, 'scos_seo_sitemap_exclude', array_values( array_diff( $exclude, [ 'xml' ] ) ) );
				}
				delete_post_meta( $id, 'scos_seo_sitemap_noindex_auto' );
			}
		}

		if ( $apply && class_exists( '\SiteEssentials\Core\Cache_Helper' ) ) {
			\SiteEssentials\Core\Cache_Helper::flush( 'seo' );
		}
		return $rows;
	}
}
