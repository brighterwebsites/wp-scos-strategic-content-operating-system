<?php
/**
 * Markdown Renderer
 *
 * v1.0 | 2026-06-22
 *
 * Intercepts ?format=md (or ?format=markdown) on any singular post/page and serves
 * a plain-text version: title + stripped content. No theme chrome, no menus,
 * no popups — clean signal for AI agents that browse via HTTP.
 *
 * Breakdance "Limit Characters" and other builder post-processing do not apply;
 * this reads raw post content directly.
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

	/**
	 * Output plain-text content if the ?format=md|markdown parameter is present
	 * and the current request is a singular post/page or the front page.
	 */
	public static function maybe_render(): void {
		$format = isset( $_GET['format'] ) ? sanitize_key( $_GET['format'] ) : '';

		if ( ! in_array( $format, [ 'md', 'markdown' ], true ) ) {
			return;
		}

		if ( ! is_singular() && ! is_front_page() ) {
			return;
		}

		// Ensure the global post is set up.
		the_post();

		$title   = wp_strip_all_tags( get_the_title() );
		$content = self::content_to_plain_text( get_the_content() );

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );

		echo '# ' . $title . "\n\n";
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput — intentional plain-text output
		exit;
	}

	/**
	 * Convert post content HTML to readable plain text.
	 * Preserves links as [text](url) and paragraph breaks.
	 *
	 * @param string $content Raw post content (pre-filter).
	 * @return string
	 */
	private static function content_to_plain_text( string $content ): string {
		// Run standard content filters (shortcodes, blocks) first.
		$content = apply_filters( 'the_content', $content );

		// Convert links to Markdown format before stripping tags.
		$content = preg_replace(
			'/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
			'[$2]($1)',
			$content
		);

		// Convert headings to Markdown headings.
		$content = preg_replace( '/<h1[^>]*>(.*?)<\/h1>/is', '# $1', $content );
		$content = preg_replace( '/<h2[^>]*>(.*?)<\/h2>/is', '## $1', $content );
		$content = preg_replace( '/<h3[^>]*>(.*?)<\/h3>/is', '### $1', $content );
		$content = preg_replace( '/<h[456][^>]*>(.*?)<\/h[456]>/is', '#### $1', $content );

		// Paragraphs and block breaks → double newline.
		$content = preg_replace( '/<\/?(?:p|div|blockquote|section|article)[^>]*>/i', "\n\n", $content );

		// List items.
		$content = preg_replace( '/<li[^>]*>(.*?)<\/li>/is', '- $1', $content );

		// Line breaks → single newline.
		$content = preg_replace( '/<br\s*\/?>/i', "\n", $content );

		// Strip remaining HTML.
		$content = wp_strip_all_tags( $content );

		// Normalise whitespace: collapse 3+ newlines to 2.
		$content = preg_replace( '/\n{3,}/', "\n\n", $content );

		return trim( $content );
	}
}
