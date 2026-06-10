<?php
/**
 * Content Architecture — Rendered Content Extractor
 *
 * Extracts the *rendered* front-end content of a published page rather than the
 * raw stored builder data. Breakdance stores only variable references for
 * Dynamic Fields / ACF, image ids (not URLs), and nothing for Query Loops /
 * Post Repeaters — so parsing `_breakdance_data` can never equal what renders.
 * This class fetches the page's own URL over an internal loopback request, so
 * ACF, dynamic fields, loops, repeaters and image URLs are all resolved by
 * WordPress + Breakdance exactly as a visitor would see them.
 *
 * Reusable from REST / WP-CLI / MCP tool calls (MCP-first, CLAUDE.md §1).
 *
 * The caller is responsible for falling back to the legacy JSON-tree parse
 * (BW_Content_Analysis::aggregate_content) when get_html() returns ''.
 *
 * // v1.0 | 2026-06-07
 *
 * @package    SiteEssentials
 * @subpackage Modules\ContentArchitecture
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\ContentArchitecture;

use SiteEssentials\Core\Cache_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rendered_Content_Extractor {

	/**
	 * Query var appended to loopback requests. Lets the front-end short-circuit
	 * view tracking / analytics injectors so analysis has no side effects.
	 *
	 * @var string
	 */
	const MARKER = 'se_render';

	/**
	 * Cache group for Cache_Helper (object cache / transient fallback).
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'content_architecture';

	/**
	 * Cache TTL for rendered HTML (12 hours).
	 *
	 * @var int
	 */
	const CACHE_TTL = 43200;

	/**
	 * Hard cap on stored/returned markdown length (bytes) to keep meta rows sane.
	 *
	 * @var int
	 */
	const MAX_MD_BYTES = 204800; // 200 KB

	/**
	 * Whether the rendered path can be used for this post.
	 *
	 * Only published posts have a public URL to fetch — drafts are skipped
	 * entirely (caller falls back to nothing / JSON parse as appropriate).
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_available( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return false;
		}
		// Password-protected pages render a form, not the content — skip.
		if ( '' !== (string) $post->post_password ) {
			return false;
		}
		$permalink = get_permalink( $post_id );
		return ( $permalink && ! is_wp_error( $permalink ) );
	}

	/**
	 * Get cleaned rendered main-content HTML for a post.
	 *
	 * Returns '' when the post is not eligible (draft, no permalink) or the
	 * loopback request fails — the caller should then fall back.
	 *
	 * @param int $post_id Post ID.
	 * @return string Cleaned HTML, or '' on failure.
	 */
	public static function get_html( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! self::is_available( $post_id ) ) {
			return '';
		}

		$post = get_post( $post_id );
		$key  = 'rendered_html_' . $post_id . '_' . md5( (string) $post->post_modified );

		return (string) Cache_Helper::remember( $key, function () use ( $post_id ) {
			$full = self::fetch_rendered_html( $post_id );
			if ( '' === $full ) {
				return '';
			}
			return self::extract_main( $full );
		}, self::CACHE_TTL, self::CACHE_GROUP );
	}

	/**
	 * Get rendered content as markdown.
	 *
	 * @param int $post_id Post ID.
	 * @return string Markdown, or '' on failure.
	 */
	public static function get_markdown( $post_id ) {
		$html = self::get_html( $post_id );
		if ( '' === $html ) {
			return '';
		}
		return self::to_markdown( $html );
	}

	/**
	 * Fetch the fully rendered front-end HTML via an internal loopback request.
	 *
	 * @param int $post_id Post ID.
	 * @return string Full page HTML, or '' on any failure.
	 */
	private static function fetch_rendered_html( $post_id ) {
		$url = get_permalink( $post_id );
		if ( ! $url || is_wp_error( $url ) ) {
			return '';
		}

		// Cache-bust query arg + marker so page caches (e.g. LiteSpeed) don't
		// serve stale HTML and our own injectors can short-circuit.
		$url = add_query_arg( self::MARKER, '1', $url );

		$args = apply_filters( 'scos_ca_render_request_args', [
			'timeout'     => 10,
			'redirection' => 3,
			'sslverify'   => false,
			'headers'     => [ 'Cache-Control' => 'no-cache' ],
			'user-agent'  => 'SiteEssentials-ContentAnalysis/1.0; ' . home_url(),
		], $post_id );

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return '';
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$body = (string) wp_remote_retrieve_body( $response );
		return ( '' !== trim( $body ) ) ? $body : '';
	}

	/**
	 * Isolate the main content region from a full HTML document and strip chrome.
	 *
	 * Strategy: prefer an explicit content container (#content, Breakdance main
	 * wrapper, <main>); fall back to <body>. Then remove scripts/styles/etc and
	 * reuse the legacy header/footer/nav exclusion filters so counts match the
	 * existing pipeline (BW_Content_Analysis::clean_content).
	 *
	 * @param string $html Full page HTML.
	 * @return string Cleaned content HTML.
	 */
	private static function extract_main( $html ) {
		if ( '' === trim( $html ) ) {
			return '';
		}

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();

		$xpath = new \DOMXPath( $dom );

		// 1. Remove noise tags outright.
		foreach ( [ 'script', 'style', 'noscript', 'template', 'svg', 'iframe' ] as $tag ) {
			self::remove_nodes( $xpath->query( '//' . $tag ) );
		}

		// 2. Remove header/footer/nav by tag (reuse legacy filter).
		$exclude_tags = apply_filters( 'bw_content_analysis_exclude_tags', [ 'header', 'footer', 'nav' ] );
		foreach ( (array) $exclude_tags as $tag ) {
			self::remove_nodes( $xpath->query( '//' . $tag ) );
		}

		// 3. Remove elements with known chrome classes (reuse legacy filter).
		$exclude_classes = apply_filters( 'bw_content_analysis_exclude_classes', [
			'ga-hrcy-header',
			'ga-hrcy-footer',
			'site-header',
			'site-footer',
			'main-navigation',
			'site-navigation',
		] );
		foreach ( (array) $exclude_classes as $class ) {
			self::remove_nodes( $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' " . $class . " ')]" ) );
		}

		// 4. Pick the content region.
		$region = null;
		$candidates = apply_filters( 'scos_ca_render_content_xpath', [
			"//*[@id='content']",
			"//main",
			"//*[contains(concat(' ', normalize-space(@class), ' '), ' breakdance ')]",
		], $html );
		foreach ( (array) $candidates as $query ) {
			$found = $xpath->query( $query );
			if ( $found && $found->length > 0 ) {
				$region = $found->item( 0 );
				break;
			}
		}
		if ( ! $region ) {
			$bodies = $dom->getElementsByTagName( 'body' );
			$region = $bodies->length > 0 ? $bodies->item( 0 ) : null;
		}
		if ( ! $region ) {
			return '';
		}

		$out = '';
		foreach ( $region->childNodes as $child ) {
			$out .= $dom->saveHTML( $child );
		}
		return $out;
	}

	/**
	 * Convert HTML to markdown. Pure utility — does no fetching.
	 *
	 * Preserves headings as #..###### so existing consumers
	 * (sanitize_content_for_prompt, count_h2) keep working.
	 *
	 * @param string $html HTML.
	 * @return string Markdown (capped at MAX_MD_BYTES).
	 */
	public static function to_markdown( $html ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return '';
		}

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><div id="se-md-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$root = $dom->getElementById( 'se-md-root' );
		$md   = $root ? self::children_to_md( $root ) : '';

		// Normalise whitespace.
		$md = preg_replace( "/[ \t]+/", ' ', $md );
		$md = preg_replace( "/ *\n */", "\n", $md );
		$md = preg_replace( "/\n{3,}/", "\n\n", $md );
		$md = trim( $md );

		if ( strlen( $md ) > self::MAX_MD_BYTES ) {
			$md = substr( $md, 0, self::MAX_MD_BYTES );
		}
		return $md;
	}

	/**
	 * Concatenate markdown for all child nodes.
	 *
	 * @param \DOMNode $node Parent node.
	 * @return string
	 */
	private static function children_to_md( \DOMNode $node ) {
		$out = '';
		foreach ( $node->childNodes as $child ) {
			$out .= self::node_to_md( $child );
		}
		return $out;
	}

	/**
	 * Convert a single DOM node to markdown.
	 *
	 * @param \DOMNode $node Node.
	 * @return string
	 */
	private static function node_to_md( \DOMNode $node ) {
		if ( XML_TEXT_NODE === $node->nodeType ) {
			return preg_replace( '/\s+/', ' ', $node->nodeValue );
		}
		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			return '';
		}

		$tag = strtolower( $node->nodeName );

		switch ( $tag ) {
			case 'script':
			case 'style':
			case 'noscript':
			case 'template':
			case 'svg':
				return '';

			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$level = (int) substr( $tag, 1 );
				$text  = trim( self::children_to_md( $node ) );
				return '' === $text ? '' : "\n\n" . str_repeat( '#', $level ) . ' ' . $text . "\n\n";

			case 'p':
			case 'div':
			case 'section':
			case 'article':
				$text = trim( self::children_to_md( $node ) );
				return '' === $text ? '' : "\n\n" . $text . "\n\n";

			case 'br':
				return "\n";

			case 'strong':
			case 'b':
				$text = trim( self::children_to_md( $node ) );
				return '' === $text ? '' : '**' . $text . '**';

			case 'em':
			case 'i':
				$text = trim( self::children_to_md( $node ) );
				return '' === $text ? '' : '*' . $text . '*';

			case 'a':
				$text = trim( self::children_to_md( $node ) );
				$href = $node instanceof \DOMElement ? trim( $node->getAttribute( 'href' ) ) : '';
				if ( '' === $text ) {
					return '';
				}
				return ( '' === $href || 0 === strpos( $href, '#' ) ) ? $text : '[' . $text . '](' . $href . ')';

			case 'img':
				if ( ! $node instanceof \DOMElement ) {
					return '';
				}
				$src = trim( $node->getAttribute( 'src' ) );
				$alt = trim( $node->getAttribute( 'alt' ) );
				return '' === $src ? '' : ' ![' . $alt . '](' . $src . ') ';

			case 'ul':
			case 'ol':
				$items = '';
				$i     = 1;
				foreach ( $node->childNodes as $li ) {
					if ( XML_ELEMENT_NODE === $li->nodeType && 'li' === strtolower( $li->nodeName ) ) {
						$marker = ( 'ol' === $tag ) ? ( $i++ . '. ' ) : '- ';
						$items .= $marker . trim( self::children_to_md( $li ) ) . "\n";
					}
				}
				return "\n" . $items . "\n";

			case 'blockquote':
				$text  = trim( self::children_to_md( $node ) );
				$lines = array_map( function ( $l ) {
					return '> ' . $l;
				}, explode( "\n", $text ) );
				return "\n\n" . implode( "\n", $lines ) . "\n\n";

			case 'pre':
				return "\n\n```\n" . trim( $node->textContent ) . "\n```\n\n";

			case 'code':
				$text = trim( self::children_to_md( $node ) );
				return '' === $text ? '' : '`' . $text . '`';

			default:
				return self::children_to_md( $node );
		}
	}

	/**
	 * Remove a DOMNodeList from its document (collect first — live lists shift).
	 *
	 * @param \DOMNodeList|false $nodes Nodes to remove.
	 * @return void
	 */
	private static function remove_nodes( $nodes ) {
		if ( ! $nodes || 0 === $nodes->length ) {
			return;
		}
		$to_remove = [];
		foreach ( $nodes as $node ) {
			$to_remove[] = $node;
		}
		foreach ( $to_remove as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}
	}
}
