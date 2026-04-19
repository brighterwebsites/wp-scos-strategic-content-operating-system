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

use SiteEssentials\Core\Settings_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class General_Post_Permalink_Settings {

	/** @var string Sanitized custom segment when mode is custom_prefix. */
	private static $custom_prefix = '';

	/** @var string post_link_mode value from last bootstrap. */
	private static $post_link_mode = 'default';

	/**
	 * @param array $opts Parsed `cpt` module settings.
	 */
	public static function bootstrap( array $opts ): void {
		self::$custom_prefix = '';
		self::$post_link_mode = isset( $opts['post_link_mode'] ) ? sanitize_key( (string) $opts['post_link_mode'] ) : 'default';

		if ( ! empty( $opts['general_post_slug_prefix'] ) && 'custom_prefix' === self::$post_link_mode ) {
			self::$custom_prefix = sanitize_title( (string) $opts['general_post_slug_prefix'] );
		}

		if ( ! empty( $opts['general_remove_category_base'] ) ) {
			add_filter( 'category_rewrite_rules', [ self::class, 'strip_category_from_rewrite_rules' ] );
			add_filter( 'category_link', [ self::class, 'strip_category_from_category_link' ], 10, 2 );
		}

		if ( 'category_prefix' === self::$post_link_mode ) {
			add_filter( 'post_link', [ self::class, 'post_link_first_category_prefix' ], 20, 3 );
		} elseif ( self::$custom_prefix !== '' ) {
			add_action( 'init', [ self::class, 'register_custom_prefix_rewrite_rule' ], 11 );
			add_filter( 'post_link', [ self::class, 'post_link_custom_prefix' ], 20, 3 );
		}

		add_filter( 'bw_breadcrumb_items', [ self::class, 'filter_breadcrumb_items' ], 10, 1 );
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
	 * @param int    $term_id  Term ID.
	 * @return string
	 */
	public static function strip_category_from_category_link( $termlink, $term_id ) {
		unset( $term_id );
		if ( ! is_string( $termlink ) || $termlink === '' ) {
			return $termlink;
		}
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
	 * @param string         $permalink Default permalink.
	 * @param \WP_Post|null  $post        Post object.
	 * @param bool           $leavename   Whether to leave the post name.
	 * @return string
	 */
	public static function post_link_custom_prefix( $permalink, $post, $leavename ) {
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
	 * Use wp_get_post_terms (not get_the_category) — safer inside post_link / early bootstrap.
	 *
	 * @param string         $permalink Default permalink.
	 * @param \WP_Post|null  $post        Post object.
	 * @param bool           $leavename Whether to leave the post name.
	 * @return string
	 */
	public static function post_link_first_category_prefix( $permalink, $post, $leavename ) {
		unset( $leavename );
		if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
			return $permalink;
		}
		if ( 'publish' !== $post->post_status ) {
			return $permalink;
		}

		$terms = wp_get_post_terms(
			$post->ID,
			'category',
			[
				'number'     => 1,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
				'fields'     => 'all',
				'hide_empty' => false,
			]
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			$cat_slug = 'uncategorized';
		} else {
			$cat_slug = $terms[0]->slug;
		}

		return home_url( user_trailingslashit( $cat_slug . '/' . $post->post_name ) );
	}

	/**
	 * Insert prefix or category crumb before the current item on single posts.
	 *
	 * @param array $items Breadcrumb rows from brighter-core.
	 * @return array
	 */
	public static function filter_breadcrumb_items( $items ) {
		if ( ! is_array( $items ) || ! is_singular( 'post' ) ) {
			return $items;
		}

		$opts = self::get_cpt_opts_merged();
		$mode = isset( $opts['post_link_mode'] ) ? sanitize_key( (string) $opts['post_link_mode'] ) : 'default';
		if ( 'default' === $mode ) {
			return $items;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return $items;
		}

		$extras = [];

		if ( 'custom_prefix' === $mode ) {
			$prefix = isset( $opts['general_post_slug_prefix'] ) ? sanitize_title( (string) $opts['general_post_slug_prefix'] ) : '';
			if ( $prefix !== '' ) {
				$extras[] = [
					'name'    => self::human_slug_label( $prefix ),
					'url'     => home_url( user_trailingslashit( $prefix ) ),
					'current' => false,
				];
			}
		} elseif ( 'category_prefix' === $mode ) {
			$terms = wp_get_post_terms(
				$post_id,
				'category',
				[
					'number'     => 1,
					'orderby'    => 'term_id',
					'order'      => 'ASC',
					'hide_empty' => false,
				]
			);
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$t    = $terms[0];
				$link = get_term_link( $t );
				if ( ! is_wp_error( $link ) ) {
					$extras[] = [
						'name'    => $t->name,
						'url'     => $link,
						'current' => false,
					];
				}
			}
		}

		if ( empty( $extras ) || count( $items ) < 2 ) {
			return $items;
		}

		$current = array_pop( $items );
		return array_merge( $items, $extras, [ $current ] );
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function get_cpt_opts_merged(): array {
		$defaults = [
			'post_link_mode'           => 'default',
			'general_post_slug_prefix' => '',
		];
		if ( ! class_exists( Settings_Manager::class ) ) {
			return $defaults;
		}
		$raw = Settings_Manager::instance()->get_module_setting( 'cpt', null, [] );
		if ( ! is_array( $raw ) ) {
			return $defaults;
		}
		return wp_parse_args( $raw, $defaults );
	}

	private static function human_slug_label( string $slug ): string {
		$slug = str_replace( [ '-', '_' ], ' ', $slug );
		return ucwords( $slug );
	}
}
