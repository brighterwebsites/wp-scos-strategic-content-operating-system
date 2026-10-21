<?php
/**
 * Agentic — WebMCP Widget
 *
 * v1.1 | 2026-10-21
 *
 * v1.1 | 2026-10-21 — Tools now come from WebMCP_Tools (shared with the native
 *                     document.modelContext registration) and describe the
 *                     site by its own name, not the agency's.
 *
 * Conditionally loads the jasonjmcghee/WebMCP widget and registers the
 * SCOS content tools on the designated /mcp/ page only. Site-wide, browser-
 * native registration is WebMCP_Native's job.
 *
 * Tools registered (definitions in WebMCP_Tools; executors call scos/v1):
 *   search_content  — keyword + optional purpose/intent filters
 *   list_content    — optional purpose/intent filters, full list
 *   get_content     — full body for one item by slug
 *   get_site_info   — static site framing
 *
 * Activation:
 *   1. Enable the toggle: scos_agentic_webmcp_enabled = 1
 *   2. Set the page ID:  scos_agentic_webmcp_page_id = <page ID of /mcp/>
 *
 * webmcp.js source: jasonjmcghee/WebMCP v0.1.13 (src/webmcp.js, MIT licence)
 * Bundled at: Modules/Agentic/assets/webmcp.js
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class WebMCP_Widget {

	// ── Boot ─────────────────────────────────────────────────────────────────

	public static function init(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
		add_action( 'wp_footer',          [ __CLASS__, 'output_tool_registration' ], 20 );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private static function is_enabled(): bool {
		return (bool) get_option( 'scos_agentic_webmcp_enabled', false );
	}

	private static function is_mcp_page(): bool {
		$page_id = absint( get_option( 'scos_agentic_webmcp_page_id', 0 ) );

		if ( $page_id > 0 ) {
			return is_page( $page_id );
		}

		// Fallback: match slug /mcp/ if no page ID is set yet.
		return is_page( 'mcp' );
	}

	// ── Asset enqueue ─────────────────────────────────────────────────────────

	public static function enqueue(): void {
		if ( ! self::is_mcp_page() ) {
			return;
		}

		wp_enqueue_script(
			'scos-webmcp',
			plugin_dir_url( __FILE__ ) . 'assets/webmcp.js',
			[],
			'0.1.13',
			true
		);
	}

	// ── Tool registration ─────────────────────────────────────────────────────

	/**
	 * Output the inline JS that initialises the WebMCP widget and registers
	 * the four SCOS content tools. Only fires on the /mcp/ page.
	 */
	public static function output_tool_registration(): void {
		if ( ! self::is_mcp_page() ) {
			return;
		}

		// Widget colour — falls back to the standard BW link colour.
		$color = apply_filters( 'scos_webmcp_widget_color', '#4f46e5' );
		?>
<script>
(function () {
    var mcp = new WebMCP({
        color:    <?php echo wp_json_encode( $color ); ?>,
        position: 'bottom-right',
        size:     '28px',
        padding:  '16px'
    });

    var tools = <?php echo wp_json_encode( WebMCP_Tools::definitions() ); ?>;

    function buildUrl(def, args) {
        var path = def.path.replace(/\{(\w+)\}/g, function (m, key) {
            return encodeURIComponent(args[key] == null ? '' : args[key]);
        });
        var qs = (def.query || []).filter(function (key) {
            return args[key] != null && args[key] !== '';
        }).map(function (key) {
            return key + '=' + encodeURIComponent(args[key]);
        }).join('&');
        return def.base.replace(/\/$/, '') + path + (qs ? '?' + qs : '');
    }

    // Same definitions as the native registration (WebMCP_Tools.php).
    tools.forEach(function (def) {
        mcp.registerTool(def.name, def.description, def.inputSchema, function (args) {
            return fetch(buildUrl(def, args || {})).then(function (r) { return r.json(); });
        });
    });

}());
</script>
		<?php
	}
}
