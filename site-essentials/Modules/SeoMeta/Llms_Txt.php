<?php
/**
 * Llms_Txt — generated /llms.txt
 *
 * v1.1 | 2026-10-22
 *
 * v1.1 | 2026-10-22 — the enquiry line points at the enquiry handoff (form
 *                     fields + link route) when an enquiry page is set.
 *
 * Builds an llms.txt (llmstxt.org format) from the site's own data:
 *   - H1 + summary from Business_Profile (scos_biz_* fields, WP fallbacks)
 *   - "For AI agents": the agent-native ways into the site (the MCP page,
 *     Markdown negotiation, the scos/v1 content API, the discovery files),
 *     each listed only when the Agentic module and that feature are on
 *   - Services from Business_Profile::services()
 *   - Key pages and recent articles, then an Optional section
 *
 * Why it matters: told to use only agent-native tools, agents fetch
 * /llms.txt before anything else. Without it they fall back to clicking
 * through the page. This file is the front door that points them at the
 * tools that already exist.
 *
 * Served live by Virtual_Files when llms.txt is enabled and its custom
 * content is blank, so it stays current as pages and business details change.
 *
 * Filter: scos_llms_txt (string $text, array $profile).
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 */

namespace SiteEssentials\Modules\SeoMeta;

use SiteEssentials\Core\Settings_Manager;
use SiteEssentials\Modules\BusinessInfo\Business_Profile;
use SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Llms_Txt {

	const RECENT_POSTS = 10;

	/**
	 * @return string llms.txt body.
	 */
	public static function build(): string {
		$profile = Business_Profile::get();

		$lines   = [ '# ' . ( $profile['name'] ?? '' ), '' ];
		$summary = $profile['description'] ?? ( $profile['tagline'] ?? '' );
		if ( '' !== $summary ) {
			$lines[] = '> ' . $summary;
			$lines[] = '';
		}

		$facts = array_filter( [
			isset( $profile['category'] ) ? 'Business type: ' . $profile['category'] : '',
			isset( $profile['location'] ) ? 'Location: ' . $profile['location'] : '',
			isset( $profile['contact']['phone'] ) ? 'Phone: ' . $profile['contact']['phone'] : '',
			isset( $profile['contact']['email'] ) ? 'Email: ' . $profile['contact']['email'] : '',
			isset( $profile['contact']['hours'] ) ? 'Hours: ' . $profile['contact']['hours'] : '',
			'Website: ' . home_url( '/' ),
		] );
		foreach ( $facts as $fact ) {
			$lines[] = '- ' . $fact;
		}
		$lines[] = '';

		self::section( $lines, 'For AI agents', self::agent_lines() );
		self::section( $lines, 'Services', self::link_lines( $profile['services'] ?? [] ) );
		self::section( $lines, 'Key pages', self::key_pages() );
		self::section( $lines, 'Recent articles', self::recent_posts() );
		self::section( $lines, 'Optional', self::optional_lines( $profile ) );

		$text = trim( implode( "\n", $lines ) ) . "\n";
		return (string) apply_filters( 'scos_llms_txt', $text, $profile );
	}

	// ── Sections ──────────────────────────────────────────────────────────────

	/**
	 * @param string[] $lines Output, appended to.
	 * @param string   $title H2 title.
	 * @param string[] $items Section lines; the section is skipped when empty.
	 */
	private static function section( array &$lines, string $title, array $items ): void {
		if ( ! $items ) {
			return;
		}
		$lines[] = '## ' . $title;
		$lines[] = '';
		array_push( $lines, ...$items );
		$lines[] = '';
	}

	/**
	 * The agent-native ways in. Each line only appears when its feature is on,
	 * so the file never points at something that 404s.
	 *
	 * @return string[]
	 */
	private static function agent_lines(): array {
		if ( ! Settings_Manager::instance()->is_module_enabled( 'agentic' ) ) {
			return [];
		}

		$lines = [ 'This site can be used without clicking through pages:', '' ];

		$mcp = self::mcp_page_url();
		if ( '' !== $mcp ) {
			$lines[] = '- [Agent guide](' . $mcp . '): how to connect browser agents and desktop AI apps (WebMCP), and what the tools do.';
		}
		if ( get_option( 'scos_agentic_markdown_enabled' ) ) {
			$lines[] = '- Markdown: every page returns clean Markdown when requested with `Accept: text/markdown`, or with `?format=md` added to the URL.';
		}

		$api     = untrailingslashit( rest_url( 'scos/v1' ) );
		$lines[] = '- [Business details](' . $api . '/site-info): who the business is, where, how to contact it, and its services, as JSON.';
		$lines[] = '- Search: `GET ' . $api . '/search?query={keyword}`. List everything: `GET ' . $api . '/list`. Read one page: `GET ' . $api . '/content/{slug}`. Read-only, no key needed.';
		$lines[] = '- [OpenAPI description](' . Agentic\OpenAPI_Spec::get_spec_url() . ') of that API.';

		if ( get_option( 'scos_agentic_webmcp_enabled' ) ) {
			$lines[] = '- WebMCP: browsers with a built-in AI agent get the tools `get_site_info`, `search_content`, `list_content` and `get_content` on every page.';
		}

		$lines[] = '- Discovery: [API catalog](' . Agentic\Api_Catalog::get_url() . '), [MCP server card](' . Agentic\Mcp_Server_Card::get_url() . '), [Agent skills](' . Agentic\Agent_Skills::get_index_url() . ')'
			. ( get_option( 'scos_agentic_ard_enabled' ) ? ', [ARD catalog](' . Agentic\Ard_Catalog::get_url() . ')' : '' ) . '.';

		$contact = get_page_by_path( 'contact' );
		if ( Agentic\Enquiry_Form::is_enabled() ) {
			$lines[] = '- Enquiries: agents can\'t send them, but can prepare one. [Form fields](' . Agentic\Enquiry_API::get_url() . '), then `POST ' . Agentic\Enquiry_API::get_link_url() . '` returns a link that opens the [form](' . Agentic\Enquiry_Form::page_url() . ') filled in, for the person to check and send.';
		} elseif ( $contact && 'publish' === $contact->post_status ) {
			$lines[] = '- Enquiries and bookings are not available through the API. Use the [contact page](' . get_permalink( $contact ) . ').';
		}

		return $lines;
	}

	/**
	 * @return string[]
	 */
	private static function key_pages(): array {
		$pages = [];
		foreach ( [ 'about', 'services', 'contact' ] as $path ) {
			$page = get_page_by_path( $path );
			if ( $page && 'publish' === $page->post_status ) {
				$pages[] = [ 'name' => get_the_title( $page ), 'url' => get_permalink( $page ) ];
			}
		}
		$blog = (int) get_option( 'page_for_posts' );
		if ( $blog && 'publish' === get_post_status( $blog ) ) {
			$pages[] = [ 'name' => get_the_title( $blog ), 'url' => get_permalink( $blog ) ];
		}
		return self::link_lines( $pages );
	}

	/**
	 * @return string[]
	 */
	private static function recent_posts(): array {
		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => self::RECENT_POSTS,
			'no_found_rows'  => true,
		] );

		// get_posts primes the meta cache, so the reads below are not N+1.
		$items = [];
		foreach ( $posts as $post ) {
			$items[] = [
				'name'    => get_the_title( $post ),
				'url'     => get_permalink( $post ),
				'summary' => (string) get_post_meta( $post->ID, 'scos_seo_description', true ),
			];
		}
		return self::link_lines( $items );
	}

	/**
	 * @param array $profile Business profile.
	 * @return string[]
	 */
	private static function optional_lines( array $profile ): array {
		$items = [ [ 'name' => 'XML sitemap', 'url' => home_url( '/sitemap.xml' ) ] ];
		foreach ( $profile['same_as'] ?? [] as $url ) {
			$host    = (string) wp_parse_url( $url, PHP_URL_HOST );
			$items[] = [ 'name' => preg_replace( '/^www\./', '', strtolower( $host ) ), 'url' => $url ];
		}
		return self::link_lines( $items );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * @param array[] $items Each: name, url, optional summary.
	 * @return string[] "- [name](url): summary"
	 */
	private static function link_lines( array $items ): array {
		$lines = [];
		foreach ( $items as $item ) {
			$name = self::clean( (string) ( $item['name'] ?? '' ) );
			$url  = esc_url_raw( (string) ( $item['url'] ?? '' ) );
			if ( '' === $name || '' === $url ) {
				continue;
			}
			$summary = self::clean( (string) ( $item['summary'] ?? '' ) );
			$lines[] = '- [' . $name . '](' . $url . ')' . ( '' !== $summary ? ': ' . $summary : '' );
		}
		return $lines;
	}

	private static function clean( string $text ): string {
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * The page the WebMCP widget runs on, when the widget is on.
	 */
	private static function mcp_page_url(): string {
		if ( ! get_option( 'scos_agentic_webmcp_enabled' ) ) {
			return '';
		}
		$id   = absint( get_option( 'scos_agentic_webmcp_page_id', 0 ) );
		$page = $id ? get_post( $id ) : get_page_by_path( 'mcp' );
		return ( $page && 'publish' === $page->post_status ) ? (string) get_permalink( $page ) : '';
	}
}
