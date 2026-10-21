<?php
/**
 * Agentic Module — settings view
 *
 * v1.2 | 2026-10-21
 *
 * Template C (single-section settings). Rendered inside <div class="wrap scos">
 * by Admin_UI::render_agentic_page().
 *
 * Variables available:
 * @var bool $enabled        Whether Markdown for Agents is enabled.
 * @var bool $webmcp_enabled Whether the WebMCP widget is enabled.
 * @var int  $webmcp_page_id Page ID for the /mcp/ page.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic\Views
 */

defined( 'ABSPATH' ) || exit;
?>

<header class="scos__header">
	<div>
		<h1 class="scos__title"><?php esc_html_e( 'Agentic', 'site-essentials' ); ?></h1>
		<p class="scos__subtitle"><?php esc_html_e( 'Site Essentials › Agentic', 'site-essentials' ); ?></p>
	</div>
</header>

<?php if ( isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) : ?>
	<div class="scos-notice scos-notice--success" style="margin-bottom:var(--scos-s-4)">
		<p><?php esc_html_e( 'Agentic settings saved.', 'site-essentials' ); ?></p>
	</div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="scos_save_agentic">
	<?php wp_nonce_field( 'scos_save_agentic', 'scos_agentic_nonce' ); ?>

	<!-- ── General Settings ──────────────────────────────────────────────── -->
	<div class="scos-card" style="margin-bottom:var(--scos-s-6)">
		<div class="scos-card__header scos-card__header--plain">
			<h2 class="scos-card__title"><?php esc_html_e( 'General Settings', 'site-essentials' ); ?></h2>
		</div>
		<div class="scos-card__body">
			<label class="scos-checkbox-row" style="cursor:pointer">
				<input
					type="checkbox"
					name="scos_agentic_markdown_enabled"
					id="scos_agentic_markdown_enabled"
					value="1"
					<?php checked( $enabled ); ?>>
				<span>
					<strong><?php esc_html_e( 'Enable Markdown for Agents', 'site-essentials' ); ?></strong>
					<span class="description" style="display:block;margin-top:2px">
						<?php esc_html_e( 'When enabled, AI agents that send an Accept: text/markdown request header receive a Markdown version of the page — YAML frontmatter (title, description, image) plus converted content, with no theme chrome. Normal browser requests are completely unaffected. Also responds to ?format=md or ?format=markdown query parameters for manual testing.', 'site-essentials' ); ?>
					</span>
					<span class="description" style="display:block;margin-top:4px;font-style:italic">
						<?php
						printf(
							/* translators: %s example URL with format=md parameter */
							esc_html__( 'Manual test: %s', 'site-essentials' ),
							'<code>' . esc_html( home_url( '/your-page/?format=md' ) ) . '</code>'
						);
						?>
					</span>
				</span>
			</label>
			<div class="scos-form__slug" style="margin-top:var(--scos-s-3)">scos_agentic_markdown_enabled</div>
			<?php
			$scos_md_rule = \SiteEssentials\Modules\Agentic\Markdown_Cache_Rule::status();
			if ( $scos_md_rule['enabled'] && ! $scos_md_rule['ok'] ) :
				?>
				<div class="scos-notice scos-notice--warning" style="margin-top:var(--scos-s-3)">
					<p>
						<?php esc_html_e( 'The page-cache rule for Markdown requests is missing from .htaccess, so cached pages answer agents with HTML.', 'site-essentials' ); ?>
						<?php if ( ! $scos_md_rule['writable'] ) : ?>
							<?php esc_html_e( '.htaccess is not writable by WordPress. Run: wp scos markdown-cache-rule sync', 'site-essentials' ); ?>
						<?php endif; ?>
					</p>
				</div>
			<?php elseif ( $scos_md_rule['enabled'] ) : ?>
				<p class="description" style="margin-top:var(--scos-s-2)">
					<?php esc_html_e( 'Page-cache rule present in .htaccess (Markdown requests skip the LiteSpeed cache).', 'site-essentials' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<!-- ── WebMCP Widget ─────────────────────────────────────────────────── -->
	<div class="scos-card" style="margin-bottom:var(--scos-s-6)">
		<div class="scos-card__header scos-card__header--plain">
			<h2 class="scos-card__title"><?php esc_html_e( 'WebMCP Widget', 'site-essentials' ); ?></h2>
		</div>
		<div class="scos-card__body">
			<label class="scos-checkbox-row" style="cursor:pointer;margin-bottom:var(--scos-s-4)">
				<input
					type="checkbox"
					name="scos_agentic_webmcp_enabled"
					id="scos_agentic_webmcp_enabled"
					value="1"
					<?php checked( $webmcp_enabled ); ?>>
				<span>
					<strong><?php esc_html_e( 'Enable WebMCP Widget', 'site-essentials' ); ?></strong>
					<span class="description" style="display:block;margin-top:2px">
						<?php esc_html_e( 'Registers the SCOS content tools (search, list, content, site-info) with the browser\'s built-in AI agent on every page (native WebMCP, does nothing in browsers without it), and loads the WebMCP widget for desktop MCP clients on the MCP page set below.', 'site-essentials' ); ?>
					</span>
				</span>
			</label>
			<div class="scos-form__slug" style="margin-bottom:var(--scos-s-4)">scos_agentic_webmcp_enabled</div>

			<table class="scos-form">
				<tbody>
					<tr>
						<th>
							<label for="scos_agentic_webmcp_page_id"><?php esc_html_e( 'MCP Page ID', 'site-essentials' ); ?></label>
							<div class="scos-form__slug">scos_agentic_webmcp_page_id</div>
						</th>
						<td>
							<input
								id="scos_agentic_webmcp_page_id"
								name="scos_agentic_webmcp_page_id"
								type="number"
								class="scos-input"
								value="<?php echo esc_attr( $webmcp_page_id ?: '' ); ?>"
								min="0"
								style="width:120px">
							<p class="description">
								<?php esc_html_e( 'WordPress page ID of your /mcp/ page. The WebMCP widget loads only on this page. Leave blank to fall back to slug "mcp".', 'site-essentials' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="description" style="margin-top:var(--scos-s-4)">
				<?php
				printf(
					/* translators: %s: REST API URL */
					esc_html__( 'REST API base: %s', 'site-essentials' ),
					'<code>' . esc_html( rest_url( 'scos/v1' ) ) . '</code>'
				);
				?>
			</p>
		</div>
	</div>

	<!-- ── Experimental: draft specs ─────────────────────────────────────── -->
	<div class="scos-card" style="margin-bottom:var(--scos-s-6)">
		<div class="scos-card__header scos-card__header--plain">
			<h2 class="scos-card__title">
				<?php esc_html_e( 'Experimental: draft specs', 'site-essentials' ); ?>
				<span class="scos-badge scos-badge--soft"><?php esc_html_e( 'Beta', 'site-essentials' ); ?></span>
			</h2>
			<p class="scos-card__desc">
				<?php esc_html_e( 'Discovery files for agent standards that are still drafts and will change. Leave off on client sites: publishing an ARD file switches Lighthouse\'s agent audit from "not applicable" to strict.', 'site-essentials' ); ?>
			</p>
		</div>
		<div class="scos-card__body">
			<?php
			$scos_drafts = [
				'scos_agentic_ard_enabled'   => [
					__( 'ARD catalog (Agentic Resource Discovery v0.91)', 'site-essentials' ),
					__( 'Serves /.well-known/ard.json and /.well-known/ai-catalog.json, listing the API catalog, OpenAPI description and MCP server card.', 'site-essentials' ),
					\SiteEssentials\Modules\Agentic\Ard_Catalog::get_url(),
				],
				'scos_agentic_poppy_enabled' => [
					__( 'Personal Agent Protocol manifest (Draft 0.2)', 'site-essentials' ),
					__( 'Serves /.well-known/poppy.json: business name and domain only. No agent, API or session declarations.', 'site-essentials' ),
					\SiteEssentials\Modules\Agentic\Poppy_Manifest::get_url(),
				],
			];
			foreach ( $scos_drafts as $scos_key => $scos_draft ) :
				?>
				<label class="scos-checkbox-row" style="cursor:pointer;margin-bottom:var(--scos-s-2)">
					<input
						type="checkbox"
						name="<?php echo esc_attr( $scos_key ); ?>"
						id="<?php echo esc_attr( $scos_key ); ?>"
						value="1"
						<?php checked( (bool) get_option( $scos_key, false ) ); ?>>
					<span>
						<strong><?php echo esc_html( $scos_draft[0] ); ?></strong>
						<span class="description" style="display:block;margin-top:2px">
							<?php echo esc_html( $scos_draft[1] ); ?>
							<?php if ( get_option( $scos_key, false ) ) : ?>
								<a href="<?php echo esc_url( $scos_draft[2] ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $scos_draft[2] ); ?></code></a>
							<?php endif; ?>
						</span>
					</span>
				</label>
				<div class="scos-form__slug" style="margin-bottom:var(--scos-s-4)"><?php echo esc_html( $scos_key ); ?></div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- ── Discovery Endpoints (read-only reference) ──────────────────────── -->
	<div class="scos-card" style="margin-bottom:var(--scos-s-6)">
		<div class="scos-card__header scos-card__header--plain">
			<h2 class="scos-card__title"><?php esc_html_e( 'Discovery Endpoints', 'site-essentials' ); ?></h2>
			<p class="scos-card__desc">
				<?php esc_html_e( 'Published automatically. These let an agent find the API without being told where it is.', 'site-essentials' ); ?>
			</p>
		</div>
		<div class="scos-card__body">
			<table class="scos-form">
				<tbody>
					<?php
					$scos_discovery = [
						__( 'API Catalog (RFC 9727)', 'site-essentials' )  => \SiteEssentials\Modules\Agentic\Api_Catalog::get_url(),
						__( 'OpenAPI 3.1 description', 'site-essentials' ) => \SiteEssentials\Modules\Agentic\OpenAPI_Spec::get_spec_url(),
						__( 'Agent Skills index', 'site-essentials' )      => \SiteEssentials\Modules\Agentic\Agent_Skills::get_index_url(),
						__( 'MCP Server Card', 'site-essentials' )         => \SiteEssentials\Modules\Agentic\Mcp_Server_Card::get_url(),
						__( 'Health probe', 'site-essentials' )            => \SiteEssentials\Modules\Agentic\OpenAPI_Spec::get_health_url(),
					];
					foreach ( $scos_discovery as $scos_label => $scos_url ) :
						?>
						<tr>
							<th><?php echo esc_html( $scos_label ); ?></th>
							<td>
								<a href="<?php echo esc_url( $scos_url ); ?>" target="_blank" rel="noopener">
									<code><?php echo esc_html( $scos_url ); ?></code>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="scos-save-bar">
		<button type="submit" class="scos-btn scos-btn--primary">
			<?php esc_html_e( 'Save Agentic Settings', 'site-essentials' ); ?>
		</button>
	</div>

</form>
