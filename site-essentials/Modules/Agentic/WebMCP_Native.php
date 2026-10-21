<?php
/**
 * Agentic — native WebMCP registration
 *
 * v1.0 | 2026-10-21
 *
 * Registers the SCOS tools (WebMCP_Tools) with the browser-native agent API,
 * document.modelContext.registerTool(), on every front-end page.
 *
 * WHY THIS IS SEPARATE FROM WebMCP_Widget
 * ---------------------------------------
 * The widget bundles a third-party library that bridges the page to a desktop
 * MCP client over a websocket. It only loads on the /mcp/ page. A browser's
 * built-in agent (and agent-readiness scanners) look for tools registered via
 * the native API on page load, which the widget never does. Both share the
 * scos_agentic_webmcp_enabled toggle and the same tool definitions. (#200 D6, D10)
 *
 * The script feature-detects the API and does nothing without it, so visitors
 * whose browser has no agent pay for one small deferred file and nothing else.
 *
 * LiteSpeed: the script is excluded from JS optimisation (data-no-optimize)
 * so "Delay JS" can't hold registration back until the first user interaction.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class WebMCP_Native {

	const HANDLE = 'scos-webmcp-native';

	public static function init(): void {
		if ( ! get_option( 'scos_agentic_webmcp_enabled', false ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
		add_filter( 'script_loader_tag', [ __CLASS__, 'exclude_from_optimisation' ], 10, 2 );
	}

	public static function enqueue(): void {
		wp_enqueue_script(
			self::HANDLE,
			plugin_dir_url( __FILE__ ) . 'assets/webmcp-native.js',
			[],
			'1.0',
			[ 'in_footer' => true, 'strategy' => 'defer' ]
		);

		// Inline "before" rather than wp_localize_script: it lands inside the
		// same tag string the filter below marks, so LiteSpeed can't delay the
		// config separately from the script that reads it.
		wp_add_inline_script(
			self::HANDLE,
			'window.scosWebMCPNative = ' . wp_json_encode( [ 'tools' => WebMCP_Tools::definitions() ] ) . ';',
			'before'
		);
	}

	/**
	 * Keep LiteSpeed from combining or delaying the registration script and
	 * its inline config (every <script> in the tag string is marked).
	 *
	 * @param string $tag    The <script> tag.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public static function exclude_from_optimisation( string $tag, string $handle ): string {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}

		return str_replace( '<script ', '<script data-no-optimize="1" ', $tag );
	}
}
