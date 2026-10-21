<?php
/**
 * Business Profile — the site's business, as agents should describe it
 *
 * v1.0 | 2026-10-21
 *
 * One read-only view of the business built from the Business Info fields
 * (scos_biz_*), with WordPress fallbacks where a field is empty. Agent-facing
 * outputs read from here so they all describe the same business:
 *   - get_site_info (scos/v1/site-info, WebMCP tool)
 *   - the generated /llms.txt
 *
 * Services: published content with scos_ca_purpose = service-page. A site
 * that has not been classified yet falls back to the child pages of its
 * /services/ page.
 *
 * Filter: scos_business_profile (array $profile).
 *
 * @package    SiteEssentials
 * @subpackage Modules\BusinessInfo
 */

namespace SiteEssentials\Modules\BusinessInfo;

defined( 'ABSPATH' ) || exit;

class Business_Profile {

	const MAX_SERVICES = 30;

	/**
	 * @return array Profile with empty values removed.
	 */
	public static function get(): array {
		$tagline = self::text( get_bloginfo( 'description' ) );

		$profile = [
			'name'        => self::name(),
			'tagline'     => $tagline,
			'description' => self::opt( 'service_description' ) ?: $tagline,
			'category'    => self::opt( 'business_category' ),
			'offering'    => self::opt( 'business_offering' ),
			'location'    => implode( ', ', array_filter( [ self::opt( 'city' ), self::opt( 'state' ), self::opt( 'country' ) ] ) ),
			'contact'     => array_filter( [
				'phone' => self::opt( 'phone_number' ),
				'email' => self::opt( 'email' ),
				'hours' => self::opt( 'business_hours' ),
			] ),
			'founded'     => self::opt( 'founding_date' ),
			'url'         => home_url( '/' ),
			'services'    => self::services(),
			'same_as'     => self::same_as(),
		];

		return (array) apply_filters( 'scos_business_profile', array_filter( $profile ) );
	}

	/**
	 * Business name, falling back to the site title.
	 */
	public static function name(): string {
		return self::opt( 'business_name' ) ?: self::text( get_bloginfo( 'name' ) );
	}

	/**
	 * @return array[] Each: name, url, summary (summary only when set).
	 */
	public static function services(): array {
		$posts = get_posts( [
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'posts_per_page' => self::MAX_SERVICES,
			'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
			'meta_key'       => 'scos_ca_purpose', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => 'service-page',    // phpcs:ignore WordPress.DB.SlowDBQuery
			'no_found_rows'  => true,
		] );

		if ( ! $posts ) {
			$parent = get_page_by_path( 'services' );
			if ( $parent && 'publish' === $parent->post_status ) {
				$posts = get_posts( [
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_parent'    => $parent->ID,
					'posts_per_page' => self::MAX_SERVICES,
					'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
					'no_found_rows'  => true,
				] );
			}
		}

		// get_posts primes the meta cache, so the reads below are not N+1.
		$services = [];
		foreach ( $posts as $post ) {
			$services[] = array_filter( [
				'name'    => self::text( get_the_title( $post ) ),
				'url'     => get_permalink( $post ),
				'summary' => self::text( (string) get_post_meta( $post->ID, 'scos_seo_description', true ) ),
			] );
		}
		return $services;
	}

	/**
	 * @return string[] Social and profile URLs that identify the business.
	 */
	public static function same_as(): array {
		$urls = [];
		foreach ( [ 'facebook', 'instagram', 'linkedin', 'youtube', 'pinterest', 'twitter' ] as $network ) {
			$urls[] = self::opt( 'social_link_' . $network );
		}
		$urls[] = self::opt( 'google_maps_share' );
		return array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private static function opt( string $key ): string {
		return self::text( (string) get_option( 'scos_biz_' . $key, '' ) );
	}

	/**
	 * Plain text: tags stripped, entities decoded, whitespace collapsed.
	 */
	private static function text( string $value ): string {
		$value = html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $value ) );
	}
}
