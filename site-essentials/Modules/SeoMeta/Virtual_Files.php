<?php
/**
 * Virtual Files — robots.txt and llms.txt management.
 *
 * robots.txt
 *   Hooks into WP's own `robots_txt` filter to replace the default output
 *   with custom content stored in `scos_robots_txt`. Only active when the
 *   site is set to allow indexing (blog_public = 1); WP short-circuits the
 *   filter when "discourage search engines" is on.
 *
 * llms.txt
 *   Registers a rewrite rule to serve /llms.txt as a virtual plain-text
 *   file from a WordPress query var. Rewrite rules are flushed automatically
 *   when the enabled state changes.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Virtual_Files {

	const OPTION_ROBOTS    = 'scos_robots_txt';
	const OPTION_LLMS      = 'scos_llms_txt';
	const LLMS_QV          = 'scos_llms_txt_serve';
	const LLMS_REWRITE_VER = 'scos_llms_rewrite_ver';

	// ── Defaults / getters ────────────────────────────────────────────────────

	public static function get_robots(): array {
		return wp_parse_args(
			(array) get_option( self::OPTION_ROBOTS, [] ),
			[
				'enabled' => false,
				'content' => '',
			]
		);
	}

	public static function get_llms(): array {
		return wp_parse_args(
			(array) get_option( self::OPTION_LLMS, [] ),
			[
				'enabled' => false,
				'content' => '',
			]
		);
	}

	/**
	 * Gold-standard robots.txt template pre-filled with the site's sitemap URL.
	 */
	public static function default_robots_txt(): string {
		$sitemap = home_url( '/sitemap.xml' );
		return implode( "\n", [
			'# WordPress',
			'User-agent: *',
			'Disallow: /wp-admin/',
			'Allow: /wp-admin/admin-ajax.php',
			'',
			'# Block Search Results',
			'Disallow: /?s=',
			'Disallow: /page/*/?s=',
			'Disallow: /search/',
			'',
			'# Block RSS Feeds',
			'Disallow: /feed/',
			'Disallow: /*/feed/',
			'Disallow: /*/feed$',
			'Disallow: /feed/$',
			'Disallow: /?feed=',
			'Disallow: /wp-feed',
			'Disallow: /comments/feed',
			'',
			'# Block Comment Replies',
			'Disallow: /*?replytocom=',
			'',
			'# Declare Sitemap',
			'Sitemap: ' . $sitemap,
		] );
	}

	// ── Bootstrap ─────────────────────────────────────────────────────────────

	public static function init(): void {
		$robots = self::get_robots();
		if ( ! empty( $robots['enabled'] ) ) {
			// Priority 99 — run after other plugins so we take full ownership.
			add_filter( 'robots_txt', [ __CLASS__, 'output_robots_txt' ], 99, 2 );
		}

		$llms = self::get_llms();
		if ( ! empty( $llms['enabled'] ) ) {
			// Always register the rewrite rule so WP can route the request.
			add_rewrite_rule( '^llms\.txt$', 'index.php?' . self::LLMS_QV . '=1', 'top' );
			add_filter( 'query_vars',        [ __CLASS__, 'add_llms_query_var' ] );
			add_action( 'template_redirect', [ __CLASS__, 'serve_llms_txt' ], 1 );

			// Flush rewrite rules if the rule was just added (once per version bump).
			if ( ! get_option( self::LLMS_REWRITE_VER ) ) {
				add_action( 'init', static function () {
					flush_rewrite_rules( false );
					update_option( self::LLMS_REWRITE_VER, '1', false );
				}, 99 );
			}
		}
	}

	// ── robots.txt callback ───────────────────────────────────────────────────

	/**
	 * Completely replaces WP's default robots.txt output with stored content.
	 *
	 * Note: WP short-circuits this filter and outputs "Disallow: /" when
	 * Settings > Reading > "Discourage search engines" is checked. In that
	 * case this callback never fires — which is the correct behaviour.
	 *
	 * @param string $output  Current robots.txt text.
	 * @param bool   $public  Whether the site allows indexing.
	 */
	public static function output_robots_txt( string $output, $public ): string {
		$robots  = self::get_robots();
		$content = trim( $robots['content'] ?? '' );
		return '' !== $content ? $content : $output;
	}

	// ── llms.txt callbacks ────────────────────────────────────────────────────

	public static function add_llms_query_var( array $vars ): array {
		$vars[] = self::LLMS_QV;
		return $vars;
	}

	/**
	 * Serve /llms.txt as a plain-text response.
	 */
	public static function serve_llms_txt(): void {
		if ( ! get_query_var( self::LLMS_QV ) ) {
			return;
		}
		$llms    = self::get_llms();
		$content = $llms['content'] ?? '';

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $content;
		exit;
	}

	// ── Save ─────────────────────────────────────────────────────────────────

	/**
	 * Called from Image_SEO::handle_save() after nonce + capability checks.
	 *
	 * @param array $post Raw $_POST data (already wp_unslashed by caller).
	 */
	public static function save( array $post ): void {
		// ── robots.txt ──
		$robots_post = ( isset( $post['scos_robots'] ) && is_array( $post['scos_robots'] ) )
			? $post['scos_robots']
			: [];

		update_option(
			self::OPTION_ROBOTS,
			[
				'enabled' => ! empty( $robots_post['enabled'] ),
				'content' => sanitize_textarea_field( $robots_post['content'] ?? '' ),
			],
			false
		);

		// ── llms.txt ──
		$llms_post = ( isset( $post['scos_llms'] ) && is_array( $post['scos_llms'] ) )
			? $post['scos_llms']
			: [];

		$llms_was_enabled = ! empty( self::get_llms()['enabled'] );
		$llms_now_enabled = ! empty( $llms_post['enabled'] );

		update_option(
			self::OPTION_LLMS,
			[
				'enabled' => $llms_now_enabled,
				'content' => sanitize_textarea_field( $llms_post['content'] ?? '' ),
			],
			false
		);

		// Flush rewrite rules whenever the llms.txt enabled state changes.
		if ( $llms_was_enabled !== $llms_now_enabled ) {
			delete_option( self::LLMS_REWRITE_VER );
			flush_rewrite_rules( false );
		}
	}
}
