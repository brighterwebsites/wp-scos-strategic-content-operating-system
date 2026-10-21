<?php
/**
 * Markdown Renderer
 *
 * v1.2 | 2026-10-21
 *
 * v1.2 | 2026-10-21 — Clean output on page-builder markup (#211): heading and
 *                     link text trimmed and collapsed, empty ones dropped,
 *                     entities decoded, indentation and blank-line noise
 *                     removed. Tag patterns anchored so <b>/<i>/<p>/<li> no
 *                     longer match <button>/<img>/<path>/<link>.
 *
 * Serves a Markdown representation of singular posts/pages and the front page
 * when an agent requests it. Two trigger paths coexist:
 *
 *   1. Accept-header negotiation (real content negotiation, spec-compliant):
 *      Any request with Accept: text/markdown in the header receives a markdown
 *      response. Normal browsers never send this header, so HTML responses are
 *      unaffected. This path satisfies the isitagentready.com / Cloudflare
 *      "Markdown for Agents" check.
 *
 *   2. Query-parameter override (back-compat, manual testing):
 *      ?format=md or ?format=markdown continues to work as before.
 *
 * Response format:
 *   - Content-Type: text/markdown; charset=utf-8
 *   - Vary: Accept  (correct cache separation between HTML and markdown variants)
 *   - X-Robots-Tag: noindex  (avoid duplicate-content indexing of markdown variant)
 *   - x-markdown-tokens: <estimated token count>
 *   - Optional YAML frontmatter: title, description, image
 *   - Body: heading/link/list/bold/italic/image/code-aware markdown conversion
 *
 * LiteSpeed Cache bypass: markdown responses are never stored in the page cache,
 * preventing a cached markdown variant from being served to a real browser.
 *
 * Extensibility seams (not yet built):
 *   - JSON-LD passthrough: add build_json_ld() and append after build_body()
 *   - Archive/listing pages: add build_archive_body() and extend scope guard
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Markdown_Renderer {

	/**
	 * Register the template_redirect hook when the setting is enabled.
	 * Called from Agentic_Module::init() — runs on every frontend request.
	 */
	public static function init(): void {
		if ( get_option( 'scos_agentic_markdown_enabled' ) ) {
			// Priority 1 — intercept before theme template resolution.
			add_action( 'template_redirect', [ __CLASS__, 'maybe_render' ], 1 );
		}
	}

	// ── Negotiation ───────────────────────────────────────────────────────────

	/**
	 * Determine whether this request should receive a markdown response.
	 *
	 * Returns true when:
	 *   a) the ?format=md|markdown query param is present (manual/back-compat), OR
	 *   b) the Accept request header contains text/markdown (real negotiation).
	 *
	 * Normal browser Accept strings never include text/markdown, so (b) is safe
	 * to check without full q-value parsing — presence is the signal.
	 */
	private static function should_serve_markdown(): bool {
		$format = isset( $_GET['format'] ) ? sanitize_key( $_GET['format'] ) : '';
		if ( in_array( $format, [ 'md', 'markdown' ], true ) ) {
			return true;
		}

		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? (string) $_SERVER['HTTP_ACCEPT'] : '';
		return strpos( $accept, 'text/markdown' ) !== false;
	}

	// ── Entry point ───────────────────────────────────────────────────────────

	/**
	 * Intercept the request and output a markdown response when appropriate.
	 * Falls through (returns without output) for any non-matching request.
	 */
	public static function maybe_render(): void {
		if ( ! self::should_serve_markdown() ) {
			return;
		}

		if ( ! is_singular() && ! is_front_page() ) {
			return;
		}

		// Ensure the global post is set up.
		the_post();

		$pid  = (int) get_the_ID();
		$fm   = self::build_frontmatter( $pid );
		$body = self::build_body();

		$markdown = $fm !== '' ? $fm . "\n\n" . $body : $body;

		// Prevent LiteSpeed (and other page caches) from storing this response.
		self::bypass_page_cache();

		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Vary: Accept' );
		header( 'X-Robots-Tag: noindex' );
		header( 'x-markdown-tokens: ' . self::estimate_tokens( $markdown ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — intentional plain-text markdown output
		echo $markdown;
		exit;
	}

	// ── Frontmatter ───────────────────────────────────────────────────────────

	/**
	 * Build YAML frontmatter for the current post.
	 *
	 * Matches Cloudflare's "Markdown for Agents" field priority:
	 *   title       — scos_seo_title meta, else WordPress post title
	 *   description — scos_seo_description meta, else excerpt
	 *   image       — featured image URL (primary og:image source on these sites)
	 *
	 * Returns empty string when no fields have values (frontmatter block omitted
	 * entirely per spec — not emitted with empty fields).
	 *
	 * Uses get_the_post_thumbnail_url() directly rather than calling
	 * brighter_inject_og_image_tags() from brighter-core, to keep this module
	 * independent of legacy brighter-core code.
	 *
	 * @param int $pid Post ID.
	 * @return string YAML frontmatter block (including --- delimiters), or ''.
	 */
	private static function build_frontmatter( int $pid ): string {
		$fields = [];

		// title: scos_seo_title meta > WordPress post title (mirrors Head_Output::get_title())
		$title = (string) get_post_meta( $pid, 'scos_seo_title', true );
		if ( '' === $title ) {
			$title = (string) get_the_title( $pid );
		}
		if ( '' !== $title ) {
			$fields['title'] = $title;
		}

		// description: scos_seo_description meta > excerpt (mirrors Head_Output::output_meta())
		$desc = (string) get_post_meta( $pid, 'scos_seo_description', true );
		if ( '' === $desc ) {
			$post = get_post( $pid );
			if ( $post && has_excerpt( $pid ) ) {
				$desc = wp_strip_all_tags( get_the_excerpt( $post ) );
			}
		}
		if ( '' !== $desc ) {
			$fields['description'] = $desc;
		}

		// image: featured image URL — primary og:image source on these sites.
		$image = (string) get_the_post_thumbnail_url( $pid, 'full' );
		if ( '' !== $image ) {
			$fields['image'] = $image;
		}

		if ( empty( $fields ) ) {
			return '';
		}

		$yaml = "---\n";
		foreach ( $fields as $key => $value ) {
			// Escape any double-quotes inside the value before wrapping in quotes.
			$safe  = str_replace( '"', '\\"', $value );
			$yaml .= $key . ': "' . $safe . '"' . "\n";
		}
		$yaml .= '---';

		return $yaml;
	}

	// ── Body conversion ───────────────────────────────────────────────────────

	/**
	 * Render the current post's content as Markdown.
	 *
	 * Extensibility seam: when archive support is needed, add a
	 * build_archive_body() method and branch here on is_singular() vs. the
	 * appropriate archive condition.
	 *
	 * @return string
	 */
	private static function build_body(): string {
		return self::content_to_markdown( get_the_content() );
	}

	/**
	 * Convert post content HTML to Markdown.
	 *
	 * Handles: headings (h1-h6), paragraphs and common block elements, links,
	 * images (both alt-first and src-first attribute orderings), bold/italic,
	 * list items, line breaks, and inline/block code.
	 * All other HTML is stripped after the named conversions so unrecognised
	 * tags never appear verbatim in the output.
	 *
	 * Formerly content_to_plain_text(); renamed for accuracy — the output is
	 * actual Markdown, not stripped plain text. The rename is safe because the
	 * method was always private.
	 *
	 * @param string $content Raw post content (pre-filter HTML).
	 * @return string
	 */
	private static function content_to_markdown( string $content ): string {
		// Run standard content filters (shortcodes, blocks, etc.) first.
		$content = apply_filters( 'the_content', $content );

		// ── Images → ![alt](src), matching both attribute orderings ───────────
		// Before links, so a linked image survives inside the link text.
		$content = preg_replace(
			'/<img\s[^>]*alt=["\']([^"\']*)["\'][^>]*src=["\']([^"\']+)["\'][^>]*\/?>/is',
			'![$1]($2)',
			$content
		);
		$content = preg_replace(
			'/<img\s[^>]*src=["\']([^"\']+)["\'][^>]*alt=["\']([^"\']*)["\'][^>]*\/?>/is',
			'![$2]($1)',
			$content
		);

		// ── Links → [text](url) ───────────────────────────────────────────────
		$content = preg_replace_callback(
			'/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
			[ __CLASS__, 'convert_link' ],
			$content
		);

		// ── Inline formatting ─────────────────────────────────────────────────
		// \b stops <b>/<i> matching <button>, <img>, <iframe>, <input> etc.
		$content = preg_replace_callback( '/<(strong|b)\b[^>]*>(.*?)<\/\1>/is', [ __CLASS__, 'convert_bold' ],   $content );
		$content = preg_replace_callback( '/<(em|i)\b[^>]*>(.*?)<\/\1>/is',     [ __CLASS__, 'convert_italic' ], $content );

		// ── Code (before block conversion to avoid mangling <pre> content) ────
		$content = preg_replace( '/<pre\b[^>]*><code[^>]*>(.*?)<\/code><\/pre>/is', "\n```\n$1\n```\n", $content );
		$content = preg_replace( '/<code\b[^>]*>(.*?)<\/code>/is',                   '`$1`',             $content );

		// ── Headings ─────────────────────────────────────────────────────────
		$content = preg_replace_callback( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', [ __CLASS__, 'convert_heading' ], $content );

		// ── Block elements → double newline ───────────────────────────────────
		$content = preg_replace(
			'/<\/?(?:p|div|blockquote|section|article|header|footer|main|aside)\b[^>]*>/i',
			"\n\n",
			$content
		);

		// ── List items ────────────────────────────────────────────────────────
		$content = preg_replace_callback( '/<li\b[^>]*>(.*?)<\/li>/is', [ __CLASS__, 'convert_list_item' ], $content );

		// ── Line breaks ───────────────────────────────────────────────────────
		$content = preg_replace( '/<br\s*\/?>/i', "\n", $content );

		// ── Strip remaining HTML, then decode entities once ───────────────────
		$content = wp_strip_all_tags( $content );
		$content = self::decode_entities( $content );

		return self::normalise_whitespace( $content );
	}

	// ── Conversion callbacks ──────────────────────────────────────────────────

	/**
	 * Reduce an HTML fragment to one line of text: tags stripped, whitespace
	 * collapsed. Builder markup indents element text across several lines.
	 *
	 * @param string $html Fragment.
	 * @return string
	 */
	private static function inline_text( string $html ): string {
		return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );
	}

	/**
	 * @param array $m [1] level, [2] inner HTML.
	 * @return string Heading on its own paragraph, or a break if it has no text.
	 */
	private static function convert_heading( array $m ): string {
		$text = self::inline_text( $m[2] );
		if ( '' === $text ) {
			return "\n\n";
		}
		return "\n\n" . str_repeat( '#', min( (int) $m[1], 4 ) ) . ' ' . $text . "\n\n";
	}

	/**
	 * Same-page anchors (#, #id) and javascript: links mean nothing outside
	 * the page, so only their text is kept. Links with no text are dropped.
	 *
	 * @param array $m [1] href, [2] inner HTML.
	 * @return string
	 */
	private static function convert_link( array $m ): string {
		$text = self::inline_text( $m[2] );
		$href = trim( $m[1] );
		if ( '' === $text ) {
			return '';
		}
		if ( '' === $href || '#' === $href[0] || 0 === stripos( $href, 'javascript:' ) ) {
			return $text;
		}
		return '[' . $text . '](' . $href . ')';
	}

	/**
	 * @param array $m [2] inner HTML.
	 * @return string
	 */
	private static function convert_bold( array $m ): string {
		$text = self::inline_text( $m[2] );
		return '' === $text ? '' : '**' . $text . '**';
	}

	/**
	 * @param array $m [2] inner HTML.
	 * @return string
	 */
	private static function convert_italic( array $m ): string {
		$text = self::inline_text( $m[2] );
		return '' === $text ? '' : '_' . $text . '_';
	}

	/**
	 * @param array $m [1] inner HTML.
	 * @return string
	 */
	private static function convert_list_item( array $m ): string {
		$text = trim( $m[1] );
		return '' === $text ? '' : '- ' . $text . "\n";
	}

	/**
	 * Decode HTML entities (&amp; → &). Non-breaking spaces become plain
	 * spaces so they count as whitespace in the clean-up that follows.
	 *
	 * @param string $text Tag-free text.
	 * @return string
	 */
	private static function decode_entities( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return str_replace( "\u{00A0}", ' ', $text );
	}

	/**
	 * Remove builder indentation and blank-line runs. Leading whitespace is
	 * stripped (four spaces would otherwise turn prose into a Markdown code
	 * block), except inside fenced code, which is left exactly as written.
	 *
	 * @param string $text Markdown with layout whitespace.
	 * @return string
	 */
	private static function normalise_whitespace( string $text ): string {
		$text  = str_replace( [ "\r\n", "\r" ], "\n", $text );
		$parts = preg_split( '/(\n```\n.*?\n```\n)/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE );

		foreach ( $parts as $i => $part ) {
			if ( 1 === $i % 2 ) {
				continue; // Fenced code block.
			}
			$part        = preg_replace( '/^\h+|\h+$/mu', '', $part );
			$parts[ $i ] = preg_replace( '/\n{3,}/', "\n\n", $part );
		}

		return trim( preg_replace( '/\n{3,}/', "\n\n", implode( '', $parts ) ) );
	}

	// ── Token estimate ────────────────────────────────────────────────────────

	/**
	 * Estimate the number of tokens in text using the 4-characters-per-token
	 * heuristic. Not exact, but gives agents a useful order-of-magnitude signal
	 * for context-window budgeting — consistent with common tokeniser
	 * approximations for English prose.
	 *
	 * @param string $text Markdown output to measure.
	 * @return int Estimated token count.
	 */
	private static function estimate_tokens( string $text ): int {
		return (int) ceil( strlen( $text ) / 4 );
	}

	// ── Cache bypass ──────────────────────────────────────────────────────────

	/**
	 * Prevent LiteSpeed (and other page caches) from storing the markdown response.
	 *
	 * Without this, a cached markdown variant could be served to a real browser,
	 * or a cached HTML variant could block an agent from receiving markdown.
	 * Mirrors the pattern already used in Seo_Module::disable_litespeed_cache()
	 * for sitemap requests.
	 */
	private static function bypass_page_cache(): void {
		if ( function_exists( 'litespeed_control' ) ) {
			litespeed_control( 'no-cache' );
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'LSCACHE_NO_CACHE' ) ) {
			define( 'LSCACHE_NO_CACHE', true );
		}

		if ( class_exists( '\LiteSpeed\API' ) && method_exists( '\LiteSpeed\API', 'set_nocache' ) ) {
			\LiteSpeed\API::set_nocache( 'markdown' );
		}

		if ( ! headers_sent() ) {
			header( 'X-LiteSpeed-Cache-Control: no-cache' );
		}
	}
}
