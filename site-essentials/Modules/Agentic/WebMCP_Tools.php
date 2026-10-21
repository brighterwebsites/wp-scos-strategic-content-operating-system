<?php
/**
 * Agentic — WebMCP tool definitions
 *
 * v1.0 | 2026-10-21
 *
 * One source of truth for the tools exposed to browser agents. Both the
 * /mcp/ widget (WebMCP_Widget) and the native document.modelContext
 * registration (WebMCP_Native) read from here, so a tool's name, wording
 * and schema can't drift between the two.
 *
 * Every tool is a read-only wrapper over a public REST endpoint. A tool
 * exposes nothing a visitor couldn't already fetch (see #200, D8).
 *
 * Definition shape (consumed by the JS executors):
 *   name         string  1–128 chars, [A-Za-z0-9_.-]
 *   description  string  what the tool does and when to call it
 *   inputSchema  array   JSON Schema (type: object)
 *   base         string  REST namespace root, e.g. rest_url( 'scos/v1' )
 *   path         string  route under base; {param} is filled from input
 *   query        array   input keys passed as query-string params
 *   annotations  array   WebMCP hints, e.g. [ 'readOnlyHint' => true ]
 *
 * Other modules add tools through the scos_webmcp_tools filter.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class WebMCP_Tools {

	/**
	 * All tool definitions, after the scos_webmcp_tools filter.
	 *
	 * @return array[]
	 */
	public static function definitions(): array {
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$base = esc_url_raw( rest_url( 'scos/v1' ) );
		$ro   = [ 'readOnlyHint' => true ];

		$purpose = [
			'type'        => 'string',
			'description' => 'Optional. Filter by content type. Common values: service-page, pillar, case-study, supporting, authority-page, resource-guide.',
		];
		$intent  = [
			'type'        => 'string',
			'description' => 'Optional. Filter by search intent. Common values: informational, commercial, transact_lead, trust.',
		];

		$tools = [
			[
				'name'        => 'search_content',
				'description' => sprintf( "Search %s's service pages, case studies and articles by keyword, optionally filtered by content type (purpose) or search intent. Returns title, slug, excerpt, url, purpose and intent for each match.", $site ),
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'query'   => [ 'type' => 'string', 'description' => 'Keyword to search for.' ],
						'purpose' => $purpose,
						'intent'  => $intent,
					],
					'required'   => [ 'query' ],
				],
				'base'        => $base,
				'path'        => '/search',
				'query'       => [ 'query', 'purpose', 'intent' ],
				'annotations' => $ro,
			],
			[
				'name'        => 'list_content',
				'description' => sprintf( 'List all published content on %s, optionally filtered by content type (purpose) or search intent. Use this to see the available topics before drilling into one.', $site ),
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'purpose' => $purpose,
						'intent'  => $intent,
					],
				],
				'base'        => $base,
				'path'        => '/list',
				'query'       => [ 'purpose', 'intent' ],
				'annotations' => $ro,
			],
			[
				'name'        => 'get_content',
				'description' => sprintf( 'Get the full text of one %s page or article by its slug. Use search_content or list_content first to find the slug.', $site ),
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'slug' => [ 'type' => 'string', 'description' => 'The URL slug of the page or post.' ],
					],
					'required'   => [ 'slug' ],
				],
				'base'        => $base,
				'path'        => '/content/{slug}',
				'query'       => [],
				'annotations' => $ro,
			],
			[
				'name'        => 'get_site_info',
				'description' => sprintf( 'Get context about %s: what the business does, where it is, how to contact it and its services, with links. Call this first to understand the site.', $site ),
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
				'base'        => $base,
				'path'        => '/site-info',
				'query'       => [],
				'annotations' => $ro,
			],
		];

		/**
		 * Filter: scos_webmcp_tools
		 *
		 * @param array[] $tools Tool definitions (shape in the file header).
		 */
		return (array) apply_filters( 'scos_webmcp_tools', $tools );
	}
}
