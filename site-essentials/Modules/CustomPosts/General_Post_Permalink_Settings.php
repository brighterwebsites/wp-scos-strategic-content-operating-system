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

	/** Option: hash of category slugs used to know when to flush rewrites. */
	private const OPTION_CAT_PREFIX_HASH = 'scos_cat_prefix_slug_hash';

	/** @var string Sanitized custom segment when mode is custom_prefix. */
	private static $custom_prefix = '';

	/** @var string post_link_mode value from last bootstrap. */
	private static $post_link_mode = 'default';

	/**
	 * Guards against re-entrancy if post_link runs during permalink/sample generation.
	 *
	 * @var int
	 */
	private static $post_link_depth = 0;

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
			add_action( 'init', [ self::class, 'register_category_prefix_rewrite_rules' ], 11 );
			add_action( 'init', [ self::class, 'maybe_sync_category_prefix_flush' ], 20 );
			add_action( 'created_term', [ self::class, 'on_category_term_saved' ], 10, 3 );
			add_action( 'edited_term', [ self::class, 'on_category_term_saved' ], 10, 3 );
			add_action( 'delete_term', [ self::class, 'on_category_term_deleted' ], 10, 4 );
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

		// Build explicit per-category archive rules (no broad catch-all).
		// The previous naive str_replace('category/', ...) could generate:
		//   (.+?)/?$ => index.php?category_name=$matches[1]
		// which hijacks CPT archives/pages and causes widespread 404s.
		$new_rules = [];
		$slugs     = self::get_all_category_slugs();

		foreach ( $slugs as $slug ) {
			if ( $slug === '' || self::is_reserved_url_segment( $slug ) ) {
				continue;
			}

			$quoted = preg_quote( $slug, '/' );
			$new_rules[ $quoted . '/?$' ]                           = 'index.php?category_name=' . $slug;
			$new_rules[ $quoted . '/page/?([0-9]{1,})/?$' ]         = 'index.php?category_name=' . $slug . '&paged=$matches[1]';
			$new_rules[ $quoted . '/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?category_name=' . $slug . '&feed=$matches[1]';
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
		$pos    = strpos( $termlink, $needle );
		if ( $pos !== false ) {
			return substr_replace( $termlink, '/', $pos, strlen( $needle ) );
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
	 * Register one rewrite rule per category slug so /{cat-slug}/{post-name}/ resolves.
	 * Without this, post_link URLs 404 because core has no rule for that path.
	 *
	 * @return void
	 */
	public static function register_category_prefix_rewrite_rules(): void {
		if ( 'category_prefix' !== self::$post_link_mode ) {
			return;
		}
		$slugs = self::get_all_category_slugs();
		foreach ( $slugs as $slug ) {
			if ( $slug === '' || self::is_reserved_url_segment( $slug ) ) {
				continue;
			}
			add_rewrite_rule(
				'^' . preg_quote( $slug, '/' ) . '/([^/]+)/?$',
				'index.php?post_type=post&name=$matches[1]',
				'top'
			);
		}
	}

	/**
	 * After rules are registered, persist them if the category slug set changed.
	 *
	 * @return void
	 */
	public static function maybe_sync_category_prefix_flush(): void {
		if ( 'category_prefix' !== self::$post_link_mode ) {
			return;
		}
		$slugs = self::get_all_category_slugs();
		sort( $slugs );
		$hash = md5( wp_json_encode( $slugs ) );
		$stored = (string) get_option( self::OPTION_CAT_PREFIX_HASH, '' );
		if ( $hash === $stored ) {
			return;
		}
		update_option( self::OPTION_CAT_PREFIX_HASH, $hash, false );
		flush_rewrite_rules( false );
	}

	/**
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	public static function on_category_term_saved( $term_id, $tt_id, $taxonomy ): void {
		unset( $term_id, $tt_id );
		if ( 'category' !== $taxonomy || 'category_prefix' !== self::$post_link_mode ) {
			return;
		}
		self::rewrite_flush_after_category_slugs_changed();
	}

	/**
	 * @param int          $term_id       Term ID.
	 * @param int          $tt_id         Term taxonomy ID.
	 * @param string       $taxonomy      Taxonomy name.
	 * @param mixed        $deleted_term  Copy of deleted term (WP_Term) when available.
	 * @param array<mixed> $object_ids    Object IDs that were linked (WP 4.5+).
	 * @return void
	 */
	public static function on_category_term_deleted( $term_id, $tt_id, $taxonomy, $deleted_term = null, $object_ids = null ): void {
		unset( $term_id, $tt_id, $deleted_term, $object_ids );
		if ( 'category' !== $taxonomy || 'category_prefix' !== self::$post_link_mode ) {
			return;
		}
		self::rewrite_flush_after_category_slugs_changed();
	}

	/**
	 * Recompute slug hash and flush so new/edited/removed categories get matching rules.
	 *
	 * @return void
	 */
	private static function rewrite_flush_after_category_slugs_changed(): void {
		$slugs = self::get_all_category_slugs();
		sort( $slugs );
		update_option( self::OPTION_CAT_PREFIX_HASH, md5( wp_json_encode( $slugs ) ), false );
		flush_rewrite_rules( false );
	}

	/**
	 * @return string[]
	 */
	private static function get_all_category_slugs(): array {
		$terms = get_terms(
			[
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'fields'     => 'slugs',
			]
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}
		$out = [];
		foreach ( $terms as $slug ) {
			if ( ! is_string( $slug ) || $slug === '' ) {
				continue;
			}
			$out[] = sanitize_title( $slug );
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Avoid stealing core paths (feed, embed, wp-json is not matched as single segment).
	 *
	 * @param string $slug Category slug.
	 * @return bool
	 */
	private static function is_reserved_url_segment( string $slug ): bool {
		$reserved = [
			'wp-admin',
			'wp-content',
			'wp-includes',
			'feed',
			'rss',
			'embed',
			'trackback',
			'page',
			'comments',
			'search',
			'author',
			'attachment',
			'post',
			'category',
			'tag',
		];
		return in_array( $slug, $reserved, true );
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
	 * Order by term_order so the first *assigned* category wins (matches UI intent).
	 *
	 * @param string         $permalink Default permalink.
	 * @param \WP_Post|null  $post        Post object.
	 * @param bool           $leavename Whether to leave the post name.
	 * @return string
	 */
	public static function post_link_first_category_prefix( $permalink, $post, $leavename ) {
		unset( $leavename );
		if ( self::$post_link_depth > 2 ) {
			return $permalink;
		}
		if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
			return $permalink;
		}
		if ( 'publish' !== $post->post_status ) {
			return $permalink;
		}
		// Pretty permalinks required; otherwise leave core ?p= links untouched.
		if ( ! (string) get_option( 'permalink_structure' ) ) {
			return $permalink;
		}

		self::$post_link_depth++;
		try {
			$terms = wp_get_post_terms(
				$post->ID,
				'category',
				[
					'number'     => 1,
					'orderby'    => 'term_order',
					'order'      => 'ASC',
					'fields'     => 'all',
					'hide_empty' => false,
				]
			);

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				$cat_slug = self::get_default_category_slug_for_urls();
			} else {
				$cat_slug = $terms[0]->slug;
			}

			return home_url( user_trailingslashit( $cat_slug . '/' . $post->post_name ) );
		} finally {
			self::$post_link_depth--;
		}
	}

	/**
	 * Slug for posts with no category (matches default category term when possible).
	 *
	 * @return string
	 */
	private static function get_default_category_slug_for_urls(): string {
		$default_id = (int) get_option( 'default_category' );
		if ( $default_id > 0 ) {
			$t = get_term( $default_id, 'category' );
			if ( $t instanceof \WP_Term && ! is_wp_error( $t ) && $t->slug !== '' ) {
				return $t->slug;
			}
		}
		return 'uncategorized';
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
					'orderby'    => 'term_order',
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
