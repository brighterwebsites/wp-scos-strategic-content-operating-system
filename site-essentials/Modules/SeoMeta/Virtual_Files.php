<?php
/**
 * Virtual Files — robots.txt and llms.txt management.
 *
 * robots.txt
 *   Hooks into WP's own `robots_txt` filter to replace the default output
 *   with custom content stored in `scos_robots_txt`. Blank content serves the
 *   default template (default_robots_txt()), so it stays current. When
 *   "Discourage search engines" is on, core still runs the filter (with
 *   $public falsy); we hand its Disallow: / back untouched.
 *
 * llms.txt
 *   Registers a rewrite rule to serve /llms.txt as a virtual plain-text
 *   file from a WordPress query var. Rewrite rules are flushed automatically
 *   when the enabled state changes. Blank content serves the generated
 *   version from Llms_Txt; anything typed in the editor replaces it.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 * @since      1.0.0
 *
 * v1.2 | 2026-11-26 — robots.txt: hand core's output back when the site discourages
 *                      search engines (#243). Blank content serves the default
 *                      template; the template gains an Agentmap line when the ARD
 *                      catalog is on, and drops the feed block while Tweaks ›
 *                      Disable RSS Feeds is on. Saving text identical to the default robots
 *                      or generated llms version stores '' so it keeps updating (#221).
 *                      /robots.txt is purged from the page cache when it is saved,
 *                      or when Discourage search engines or the ARD catalog changes.
 * v1.1 | 2026-10-21 — llms.txt with blank custom content serves the generated
 *                      version (Llms_Txt::build()), so it stays current.
 * v1.0 | 2026-10-01 — Default robots.txt: Content-Signal line, Cloudflare email-protection
 *                      disallow, note on the feed block when feeds are disabled.
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
	 * Gold-standard robots.txt template pre-filled with the site's sitemap URL,
	 * plus the agent catalog when the ARD catalog is switched on.
	 *
	 * The feed block is left out while Tweaks › Disable RSS Feeds is on: feed
	 * URLs then 301 to the post or home page, and a Disallow would stop crawlers
	 * from seeing that redirect.
	 */
	public static function default_robots_txt(): string {
		$sitemap = home_url( '/sitemap.xml' );
		$feeds   = self::feeds_disabled() ? [] : [
			'# Block RSS Feeds',
			'Disallow: /feed/',
			'Disallow: /*/feed/',
			'Disallow: /*/feed$',
			'Disallow: /feed/$',
			'Disallow: /?feed=',
			'Disallow: /wp-feed',
			'Disallow: /comments/feed',
			'',
		];
		$lines   = [
			'# WordPress',
			'User-agent: *',
			'Content-Signal: ai-train=yes, search=yes, ai-input=yes',
			'Disallow: /wp-admin/',
			'Allow: /wp-admin/admin-ajax.php',
			'',
			'# Cloudflare email obfuscation links',
			'Disallow: /cdn-cgi/l/email-protection',
			'',
			'# Block Search Results',
			'Disallow: /?s=',
			'Disallow: /page/*/?s=',
			'Disallow: /search/',
			'',
		];
		$lines = array_merge( $lines, $feeds, [
			'# Block Comment Replies',
			'Disallow: /*?replytocom=',
			'',
			'# Declare Sitemap',
			'Sitemap: ' . $sitemap,
		] );

		$ard = '\\SiteEssentials\\Modules\\Agentic\\Ard_Catalog';
		if ( class_exists( $ard ) && $ard::is_enabled() ) {
			$lines[] = '';
			$lines[] = 'Agentmap: ' . $ard::get_legacy_url();
		}

		return implode( "\n", $lines );
	}

	/**
	 * Whether Tweaks › Disable RSS Feeds is in force (module on and tweak ticked).
	 */
	private static function feeds_disabled(): bool {
		$sm = '\\SiteEssentials\\Core\\Settings_Manager';
		if ( ! class_exists( $sm ) ) {
			return false;
		}
		$settings = $sm::instance();
		if ( ! $settings->is_module_enabled( 'tweaks' ) ) {
			return false;
		}
		$tweaks = $settings->get_module_setting( 'tweaks', 'enabled_tweaks', [] );
		return is_array( $tweaks ) && ! empty( $tweaks['disable_rss_feeds'] );
	}

	/**
	 * Whether two plain-text bodies match once line endings and outer whitespace
	 * are ignored. Textareas post CRLF; generated text uses LF.
	 */
	private static function same_text( string $a, string $b ): bool {
		$norm = static function ( string $t ): string {
			return trim( str_replace( [ "\r\n", "\r" ], "\n", $t ) );
		};
		return $norm( $a ) === $norm( $b );
	}

	// ── Bootstrap ─────────────────────────────────────────────────────────────

	public static function init(): void {
		// Anything that changes what /robots.txt serves purges its cached copy.
		add_action( 'update_option_blog_public', [ __CLASS__, 'purge_robots_cache' ] );
		add_action( 'update_option_scos_agentic_ard_enabled', [ __CLASS__, 'purge_robots_cache' ] );

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
	 * Replaces WP's default robots.txt output with the stored content, or the
	 * default template when nothing custom is stored.
	 *
	 * When Settings › Reading › "Discourage search engines" is on, core builds
	 * "Disallow: /" and still runs this filter with $public falsy ('0'). That
	 * output is returned untouched so a dev/staging site stays hidden (#243).
	 *
	 * @param string          $output Current robots.txt text.
	 * @param bool|int|string $public Whether the site allows indexing (blog_public).
	 */
	public static function output_robots_txt( string $output, $public ): string {
		if ( ! $public ) {
			return $output;
		}
		$robots  = self::get_robots();
		$content = trim( $robots['content'] ?? '' );
		return '' !== $content ? $content : self::default_robots_txt();
	}

	/**
	 * Drop /robots.txt from the page cache. LiteSpeed caches it like a page, so
	 * without this a site going private keeps its old file (or a launched site
	 * keeps Disallow: /) until a manual purge. No-op without LiteSpeed Cache.
	 */
	public static function purge_robots_cache(): void {
		do_action( 'litespeed_purge_url', home_url( '/robots.txt' ) );
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
		$content = trim( (string) ( $llms['content'] ?? '' ) );
		if ( '' === $content ) {
			$content = Llms_Txt::build();
			// Generated from live content: keep a page-cache copy for an hour at most.
			do_action( 'litespeed_control_set_ttl', HOUR_IN_SECONDS );
		}

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

		$robots_content = sanitize_textarea_field( $robots_post['content'] ?? '' );
		// Unchanged template → store blank so the default keeps updating (#221).
		if ( self::same_text( $robots_content, self::default_robots_txt() ) ) {
			$robots_content = '';
		}

		update_option(
			self::OPTION_ROBOTS,
			[
				'enabled' => ! empty( $robots_post['enabled'] ),
				'content' => $robots_content,
			],
			false
		);

		// ── llms.txt ──
		$llms_post = ( isset( $post['scos_llms'] ) && is_array( $post['scos_llms'] ) )
			? $post['scos_llms']
			: [];

		$llms_was_enabled = ! empty( self::get_llms()['enabled'] );
		$llms_now_enabled = ! empty( $llms_post['enabled'] );

		$llms_content = sanitize_textarea_field( $llms_post['content'] ?? '' );
		// Saved exactly as generated → store blank so it stays generated (#221).
		if ( '' !== trim( $llms_content ) && self::same_text( $llms_content, sanitize_textarea_field( Llms_Txt::build() ) ) ) {
			$llms_content = '';
		}

		update_option(
			self::OPTION_LLMS,
			[
				'enabled' => $llms_now_enabled,
				'content' => $llms_content,
			],
			false
		);

		self::purge_robots_cache();

		// Flush rewrite rules whenever the llms.txt enabled state changes.
		if ( $llms_was_enabled !== $llms_now_enabled ) {
			delete_option( self::LLMS_REWRITE_VER );
			flush_rewrite_rules( false );
		}
	}
}
