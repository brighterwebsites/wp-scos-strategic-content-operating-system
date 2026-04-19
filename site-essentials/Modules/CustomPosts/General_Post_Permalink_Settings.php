<?php
/**
 * General post permalink helpers (default `post` type only).
 *
 * Options live in the CPT module (`site_essentials_cpt`); UI is on
 * Site Essentials → Custom Posts (no separate module card).
 *
 * @package SiteEssentials
 */

namespace SiteEssentials\Modules\CustomPosts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class General_Post_Permalink_Settings {

	/** @var string Sanitized custom segment when mode is custom_prefix. */
	private static $custom_prefix = '';

	/**
	 * @param array $opts Parsed `cpt` module settings.
	 */
	public static function bootstrap( array $opts ): void {
		self::$custom_prefix = '';

		$mode = isset( $opts['post_link_mode'] ) ? sanitize_key( (string) $opts['post_link_mode'] ) : 'default';
		if ( ! empty( $opts['general_post_slug_prefix'] ) && 'custom_prefix' === $mode ) {
			self::$custom_prefix = sanitize_title( (string) $opts['general_post_slug_prefix'] );
		}

		if ( ! empty( $opts['general_remove_category_base'] ) ) {
			add_filter( 'category_rewrite_rules', [ self::class, 'strip_category_from_rewrite_rules' ] );
			add_filter( 'category_link', [ self::class, 'strip_category_from_category_link' ], 10, 2 );
		}

		if ( 'category_prefix' === $mode ) {
			add_filter( 'post_link', [ self::class, 'post_link_first_category_prefix' ], 10, 3 );
		} elseif ( self::$custom_prefix !== '' ) {
			add_action( 'init', [ self::class, 'register_custom_prefix_rewrite_rule' ], 11 );
			add_filter( 'post_link', [ self::class, 'post_link_custom_prefix' ], 10, 3 );
		}
	}

	/**
	 * @param array<string,string> $rules Raw rewrite rule map.
	 * @return array<string,string>
	 */
	public static function strip_category_from_rewrite_rules( $rules ) {
		if ( ! is_array( $rules ) ) {
			return $rules;
		}
		$new_rules = [];
		foreach ( $rules as $pattern => $rewrite ) {
			$new_pattern = str_replace( 'category/', '', $pattern );
			$new_rules[ $new_pattern ] = $rewrite;
		}
		return $new_rules;
	}

	/**
	 * @param string $termlink Category archive link.
	 */
	public static function strip_category_from_category_link( $termlink, $term_id ): string {
		unset( $term_id );
		$base = get_option( 'category_base' );
		$slug = ( $base !== '' && $base !== false ) ? trim( (string) $base, '/' ) : 'category';
		$needle = '/' . $slug . '/';
		if ( strpos( $termlink, $needle ) !== false ) {
			return str_replace( $needle, '/', $termlink, 1 );
		}
		return $termlink;
	}

	public static function register_custom_prefix_rewrite_rule(): void {
		if ( self::$custom_prefix === '' ) {
			return;
		}
		add_rewrite_rule(
			'^' . preg_quote( self::$custom_prefix, '/' ) . '/([^/]+)/?$',
			'index.php?name=$matches[1]',
			'top'
		);
	}

	/**
	 * @param string  $permalink Default permalink.
	 * @param \WP_Post $post Post object.
	 */
	public static function post_link_custom_prefix( $permalink, $post, $leavename ): string {
		unset( $leavename );
		if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type || self::$custom_prefix === '' ) {
			return $permalink;
		}
		if ( 'publish' !== $post->post_status ) {
			return $permalink;
		}

		return home_url( user_trailingslashit( self::$custom_prefix . '/' . $post->post_name ) );
	}

	/**
	 * First assigned category slug as path segment (simplified — not full rewrite coverage).
	 *
	 * @param string  $permalink Default permalink.
	 * @param \WP_Post $post Post object.
	 */
	public static function post_link_first_category_prefix( $permalink, $post, $leavename ): string {
		unset( $leavename );
		if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
			return $permalink;
		}
		if ( 'publish' !== $post->post_status ) {
			return $permalink;
		}

		$cats = get_the_category( $post->ID );
		$cat_slug = 'uncategorized';
		if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
			$cat_slug = $cats[0]->slug;
		}

		return home_url( user_trailingslashit( $cat_slug . '/' . $post->post_name ) );
	}
}
