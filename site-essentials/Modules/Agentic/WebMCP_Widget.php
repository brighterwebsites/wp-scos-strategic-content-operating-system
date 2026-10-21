<?php
/**
 * Agentic — WebMCP Widget
 *
 * v1.2 | 2026-10-21
 *
 * v1.2 | 2026-10-21 — Trigger button shows a small code-agent icon (</> in a
 *                     robot head) instead of a blank square, gets a green dot
 *                     when connected, and is keyboard reachable with a label.
 *                     Done from the loader; the bundled webmcp.js is untouched.
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

	// ── Trigger icon ──────────────────────────────────────────────────────────

	/**
	 * Small code-agent mark for the widget trigger: </> inside a rounded
	 * robot head with an antenna. Drawn in currentColor so it follows the
	 * trigger's text colour on any widget colour.
	 *
	 * @return string Inline SVG.
	 */
	private static function icon_svg(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. '<line x1="12" y1="3.6" x2="12" y2="6"/>'
			. '<circle cx="12" cy="2.6" r="1.1" fill="currentColor" stroke="none"/>'
			. '<rect x="3" y="6" width="18" height="14" rx="4.5"/>'
			. '<polyline points="9,10.2 6.8,13 9,15.8"/>'
			. '<polyline points="15,10.2 17.2,13 15,15.8"/>'
			. '<line x1="13.1" y1="9.8" x2="10.9" y2="16.2"/>'
			. '</svg>';
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
        size:     '34px',
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

    // ── Trigger icon ─────────────────────────────────────────────────────
    // webmcp.js blanks the trigger on disconnect and writes a check mark on
    // connect, so the icon is repainted after every connection-state change.
    var icon = <?php echo wp_json_encode( self::icon_svg() ); ?>;
    var labels = <?php echo wp_json_encode( [
        'off' => __( 'Connect an AI agent to this site (WebMCP)', 'site-essentials' ),
        'on'  => __( 'AI agent connected (WebMCP)', 'site-essentials' ),
    ] ); ?>;

    function paintTrigger(connected) {
        var el = document.querySelector('[data-webmcp-widget] .webmcp-trigger');
        if (!el) { return; }
        el.innerHTML = icon + (connected
            ? '<span aria-hidden="true" style="position:absolute;top:-3px;right:-3px;width:10px;height:10px;border-radius:50%;background:#22c55e;border:2px solid #fff"></span>'
            : '');
        el.style.position = 'relative';
        el.style.color = '#fff';
        el.setAttribute('role', 'button');
        el.setAttribute('tabindex', '0');
        el.setAttribute('aria-label', connected ? labels.on : labels.off);
        el.title = connected ? labels.on : labels.off;
    }

    var updateConnectionUI = mcp._updateConnectionUI;
    if (typeof updateConnectionUI === 'function') {
        mcp._updateConnectionUI = function (connected) {
            updateConnectionUI.call(mcp, connected);
            paintTrigger(connected);
        };
    }
    var trigger = document.querySelector('[data-webmcp-widget] .webmcp-trigger');
    paintTrigger(!!trigger && trigger.textContent === '✓');
    if (trigger) {
        trigger.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); trigger.click(); }
        });
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
