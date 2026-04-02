<?php
/**
 * Settings — API Settings tab
 *
 * Manages the site's Brighter X API token (X-Brighter-Token) used to
 * authenticate inbound requests to the brighter-core/v1 REST endpoints
 * (Custom GPT, MCP servers, etc.).
 *
 * Third-party API keys (YOURLS, Airtable, Make.com) are stored in their
 * own module settings — Social Amplification and Content Architecture.
 *
 * @package    SiteEssentials
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

// Handle save / generate
$notice = '';
if ( isset( $_POST['scos_api_action'] ) && check_admin_referer( 'scos_api_settings' ) ) {
	if ( $_POST['scos_api_action'] === 'generate' ) {
		$new_token = wp_generate_password( 40, false );
		update_option( 'brighter_api_token', $new_token );
		$notice = 'generated';
	} elseif ( $_POST['scos_api_action'] === 'delete' && isset( $_POST['scos_api_confirm_delete'] ) ) {
		delete_option( 'brighter_api_token' );
		$notice = 'deleted';
	}
}

$token     = (string) get_option( 'brighter_api_token', '' );
$has_token = ! empty( $token );
$rest_base = esc_url( rest_url( 'brighter-core/v1/' ) );
?>

<?php if ( $notice === 'generated' ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'API token generated.', 'site-essentials' ); ?></p></div>
<?php elseif ( $notice === 'deleted' ) : ?>
	<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'API token deleted.', 'site-essentials' ); ?></p></div>
<?php endif; ?>

<div style="max-width:860px;">

	<!-- ── Brighter X Token ── -->
	<div class="card" style="padding:24px 28px;margin-bottom:20px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Brighter X API Token', 'site-essentials' ); ?></h2>
		<p class="description" style="margin-bottom:16px;">
			<?php esc_html_e( 'A single site-specific token that authenticates inbound requests to this site\'s REST API endpoints. Used by Custom GPT, MCP servers, and any tool that reads structured data from this install.', 'site-essentials' ); ?>
			<br>
			<?php esc_html_e( 'Pass it as the', 'site-essentials' ); ?>
			<code>X-Brighter-Token</code>
			<?php esc_html_e( 'header on every request.', 'site-essentials' ); ?>
		</p>

		<?php if ( $has_token ) : ?>
			<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
				<input
					type="password"
					id="scos-api-token-field"
					value="<?php echo esc_attr( $token ); ?>"
					readonly
					style="width:380px;font-family:monospace;font-size:13px;"
				>
				<button type="button" class="button" id="scos-api-reveal">
					<?php esc_html_e( 'Show', 'site-essentials' ); ?>
				</button>
				<button type="button" class="button" id="scos-api-copy">
					<?php esc_html_e( 'Copy', 'site-essentials' ); ?>
				</button>
			</div>
			<script>
			( function() {
				var field  = document.getElementById( 'scos-api-token-field' );
				var reveal = document.getElementById( 'scos-api-reveal' );
				var copy   = document.getElementById( 'scos-api-copy' );
				if ( reveal ) {
					reveal.addEventListener( 'click', function() {
						if ( field.type === 'password' ) {
							field.type = 'text';
							reveal.textContent = '<?php echo esc_js( __( 'Hide', 'site-essentials' ) ); ?>';
						} else {
							field.type = 'password';
							reveal.textContent = '<?php echo esc_js( __( 'Show', 'site-essentials' ) ); ?>';
						}
					} );
				}
				if ( copy ) {
					copy.addEventListener( 'click', function() {
						navigator.clipboard.writeText( field.value ).then( function() {
							copy.textContent = '<?php echo esc_js( __( 'Copied!', 'site-essentials' ) ); ?>';
							setTimeout( function() {
								copy.textContent = '<?php echo esc_js( __( 'Copy', 'site-essentials' ) ); ?>';
							}, 2000 );
						} );
					} );
				}
			} )();
			</script>
		<?php else : ?>
			<p style="color:#dc3232;">
				<strong><?php esc_html_e( 'No token set.', 'site-essentials' ); ?></strong>
				<?php esc_html_e( 'Generate one below to enable API access.', 'site-essentials' ); ?>
			</p>
		<?php endif; ?>

		<form method="post" style="display:inline-flex;gap:10px;align-items:center;flex-wrap:wrap;">
			<?php wp_nonce_field( 'scos_api_settings' ); ?>
			<input type="hidden" name="scos_api_action" value="generate">
			<button type="submit" class="button <?php echo $has_token ? 'button-secondary' : 'button-primary'; ?>">
				<?php echo $has_token
					? esc_html__( 'Regenerate Token', 'site-essentials' )
					: esc_html__( 'Generate Token', 'site-essentials' ); ?>
			</button>
			<?php if ( $has_token ) : ?>
				<span style="color:#646970;font-size:12px;">
					<?php esc_html_e( 'Regenerating will invalidate the existing token immediately.', 'site-essentials' ); ?>
				</span>
			<?php endif; ?>
		</form>

		<?php if ( $has_token ) : ?>
			<details style="margin-top:16px;">
				<summary style="cursor:pointer;color:#646970;font-size:12px;">
					<?php esc_html_e( 'Delete token (disable API access)', 'site-essentials' ); ?>
				</summary>
				<form method="post" style="margin-top:10px;display:flex;gap:10px;align-items:center;">
					<?php wp_nonce_field( 'scos_api_settings' ); ?>
					<input type="hidden" name="scos_api_action" value="delete">
					<label style="font-size:13px;">
						<input type="checkbox" name="scos_api_confirm_delete" value="1">
						<?php esc_html_e( 'Yes, delete the token and block API access', 'site-essentials' ); ?>
					</label>
					<button type="submit" class="button" style="color:#dc3232;border-color:#dc3232;">
						<?php esc_html_e( 'Delete Token', 'site-essentials' ); ?>
					</button>
				</form>
			</details>
		<?php endif; ?>
	</div>

	<!-- ── Example Usage ── -->
	<div class="card" style="padding:24px 28px;margin-bottom:20px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Usage', 'site-essentials' ); ?></h2>
		<table class="form-table" role="presentation" style="margin:0;">
			<tr>
				<th style="width:160px;padding-left:0;"><?php esc_html_e( 'REST Base URL', 'site-essentials' ); ?></th>
				<td><code><?php echo $rest_base; ?></code></td>
			</tr>
			<tr>
				<th style="padding-left:0;"><?php esc_html_e( 'Auth Header', 'site-essentials' ); ?></th>
				<td><code>X-Brighter-Token: your_token_here</code></td>
			</tr>
			<tr>
				<th style="padding-left:0;"><?php esc_html_e( 'Example', 'site-essentials' ); ?></th>
				<td>
					<code style="display:block;padding:8px 12px;background:#f6f7f7;border:1px solid #ddd;border-radius:4px;white-space:pre-wrap;">GET <?php echo $rest_base; ?>posts?per_page=15
X-Brighter-Token: <?php echo $has_token ? substr( $token, 0, 8 ) . '…' : 'your_token_here'; ?></code>
				</td>
			</tr>
		</table>

		<h3 style="margin-top:20px;"><?php esc_html_e( 'Available Endpoints', 'site-essentials' ); ?></h3>
		<ul style="margin:0;list-style:disc;padding-left:20px;color:#3c434a;font-size:13px;line-height:1.8;">
			<li><code>GET /posts</code> — <?php esc_html_e( 'All public post types with content data', 'site-essentials' ); ?></li>
			<li><code>GET /faqs</code> — <?php esc_html_e( 'All FAQ entries', 'site-essentials' ); ?></li>
			<li><code>GET /faqs/search?q=</code> — <?php esc_html_e( 'Search FAQs by keyword', 'site-essentials' ); ?></li>
			<li><code>GET /faqs/export</code> — <?php esc_html_e( 'Export FAQs (admin only)', 'site-essentials' ); ?></li>
			<li><code>GET /social-amplification/talking-points</code> — <?php esc_html_e( 'Post Framing entries', 'site-essentials' ); ?></li>
		</ul>
	</div>

	<!-- ── Third-Party Keys ── -->
	<div class="card" style="padding:24px 28px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Third-Party Integration Keys', 'site-essentials' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'These use their own dedicated keys stored in their respective modules — not the Brighter X token above.', 'site-essentials' ); ?>
		</p>
		<table class="form-table" role="presentation" style="margin:0;">
			<tr>
				<th style="width:160px;padding-left:0;">YOURLS</th>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=site-essentials-social-amplification&tab=yourls' ) ); ?>">
						<?php esc_html_e( 'Social Amplification → YOURLS Settings', 'site-essentials' ); ?>
					</a>
				</td>
			</tr>
			<tr>
				<th style="padding-left:0;">Make.com</th>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=site-essentials-social-amplification&tab=makecom' ) ); ?>">
						<?php esc_html_e( 'Social Amplification → Make.com Settings', 'site-essentials' ); ?>
					</a>
				</td>
			</tr>
			<tr>
				<th style="padding-left:0;">Airtable</th>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=scos-content-architecture&tab=integrations' ) ); ?>">
						<?php esc_html_e( 'Content Architecture → Integrations', 'site-essentials' ); ?>
					</a>
				</td>
			</tr>
		</table>
	</div>

</div>
