<?php
/**
 * Social Amplification — Settings page view
 *
 * Rendered by SocialAmplification_Module::render_settings() via Admin_UI.
 *
 * @package SiteEssentials
 */

defined( 'ABSPATH' ) || exit;

use SiteEssentials\Modules\SocialAmplification\SocialAmplification_Module as SMA;

// Option reader: prefers scos_sma_* over legacy bw_*
$webhook_url     = SMA::get_option( 'scos_sma_webhook_url',     'bw_social_webhook_url' );
$webhook_enabled = SMA::get_option( 'scos_sma_webhook_enabled', 'bw_social_webhook_enabled', 0 );
$yourls_url      = SMA::get_option( 'scos_sma_yourls_url',      'bw_yourls_api_url' );
$yourls_sig      = SMA::get_option( 'scos_sma_yourls_signature', 'bw_yourls_signature' );
$yourls_user     = SMA::get_option( 'scos_sma_yourls_username',  'bw_yourls_username' );
$yourls_pass     = SMA::get_option( 'scos_sma_yourls_password',  'bw_yourls_password' );

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'yourls';
$page_url   = admin_url( 'admin.php?page=site-essentials-social-amplification' );

// Meta box status
$post_types = \SiteEssentials\Modules\SocialAmplification\Meta_Fields::get_post_types();
?>

<!-- ── Quick links ── -->
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
	<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bw_talking_point' ) ); ?>"
	   class="button button-secondary" style="display:inline-flex;align-items:center;gap:6px;">
		<span class="dashicons dashicons-edit" style="margin-top:3px;font-size:16px;"></span>
		<?php esc_html_e( 'Post Framing', 'site-essentials' ); ?>
	</a>
	<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=bw_talking_point' ) ); ?>"
	   class="button" style="display:inline-flex;align-items:center;gap:6px;">
		<span class="dashicons dashicons-plus-alt2" style="margin-top:3px;font-size:16px;"></span>
		<?php esc_html_e( 'Add Post Frame', 'site-essentials' ); ?>
	</a>
</div>

<!-- ── Status bar ── -->
<div class="scos-sma-status-bar" style="display:flex;align-items:center;gap:12px;margin-bottom:20px;padding:12px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;">
	<span style="color:#16a34a;font-size:18px;">&#10003;</span>
	<div>
		<strong><?php esc_html_e( 'Single page meta box enabled', 'site-essentials' ); ?></strong>
		<span style="color:#555;margin-left:8px;">
			<?php
			printf(
				/* translators: %s: comma-separated post type list */
				esc_html__( 'Active on: %s', 'site-essentials' ),
				'<code>' . esc_html( implode( ', ', $post_types ) ) . '</code>'
			);
			?>
		</span>
	</div>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=site-essentials-social-amplification&tab=yourls' ) ); ?>"
	   style="margin-left:auto;white-space:nowrap;">
		<?php esc_html_e( 'View settings ↓', 'site-essentials' ); ?>
	</a>
</div>

<!-- ── Tabs ── -->
<h2 class="nav-tab-wrapper" style="margin-bottom:0;">
	<a href="<?php echo esc_url( add_query_arg( 'tab', 'yourls', $page_url ) ); ?>"
	   class="nav-tab <?php echo $active_tab === 'yourls'  ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'YOURLS Integration', 'site-essentials' ); ?>
	</a>
	<a href="<?php echo esc_url( add_query_arg( 'tab', 'makecom', $page_url ) ); ?>"
	   class="nav-tab <?php echo $active_tab === 'makecom' ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'Make.com Integration', 'site-essentials' ); ?>
	</a>
	<a href="<?php echo esc_url( add_query_arg( 'tab', 'docs', $page_url ) ); ?>"
	   class="nav-tab <?php echo $active_tab === 'docs'    ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'Documentation', 'site-essentials' ); ?>
	</a>
</h2>

<div style="background:#fff;border:1px solid #c3c4c7;border-top:none;border-radius:0 0 4px 4px;padding:24px 28px;">

<?php if ( $active_tab === 'docs' ) : ?>

	<!-- ── Documentation ── -->
	<h3 style="margin-top:0;"><?php esc_html_e( 'Documentation', 'site-essentials' ); ?></h3>

	<table class="form-table" style="max-width:900px;">
		<tr>
			<th scope="row"><?php esc_html_e( 'Webhook Payload', 'site-essentials' ); ?></th>
			<td>
				<p><?php esc_html_e( 'When "Create Social Post" is clicked, WordPress POSTs this JSON to your Make.com webhook URL:', 'site-essentials' ); ?></p>
				<pre style="background:#f5f5f5;padding:14px 18px;overflow-x:auto;border-radius:4px;font-size:12px;line-height:1.6;max-width:640px;">{
  "post_id":                 123,
  "post_url":                "https://example.com/blog/post-title/",
  "post_title":              "Post Title",
  "post_type":               "post",
  "post_excerpt":            "Brief excerpt...",
  "post_date":               "2025-12-02T10:30:00+00:00",
  "post_modified":           "2025-12-02T11:00:00+00:00",
  "breadcrumb":              "seo-signals",
  "content_type":            "article",
  "featured_image_url":      "https://.../image.jpg",
  "featured_image_caption":  "Image caption",
  "featured_image_social_url": "https://.../image-1080x1080.jpg",
  "site_url":                "https://example.com",
  "trigger_time":            "2025-12-02 11:00:00",
  "trigger_type":            "manual"
}</pre>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'API Documentation', 'site-essentials' ); ?></th>
			<td>
				<p><strong><?php esc_html_e( 'Generate Prompt endpoint (Make.com calls this for AI post generation):', 'site-essentials' ); ?></strong></p>
				<code style="display:block;background:#f5f5f5;padding:8px 12px;margin:8px 0;border-radius:4px;word-break:break-all;">
					<?php echo esc_html( get_site_url() ); ?>/wp-json/brighter-core/v1/social-amplification/generate-prompt
				</code>
				<p class="description">
					<?php esc_html_e( 'Include header: ', 'site-essentials' ); ?>
					<code>X-Brighter-Token: &lt;your-token&gt;</code><br>
					<?php esc_html_e( 'Returns: post context, framing options (Post Frames matched by content type), H2 source material, and TL;DR for AI prompt assembly.', 'site-essentials' ); ?>
				</p>

				<p><strong><?php esc_html_e( 'Create Shortlink endpoint:', 'site-essentials' ); ?></strong></p>
				<code style="display:block;background:#f5f5f5;padding:8px 12px;margin:8px 0;border-radius:4px;word-break:break-all;">
					POST <?php echo esc_html( get_site_url() ); ?>/wp-json/brighter-core/v1/social-amplification/create-shortlink
				</code>
				<p class="description"><?php esc_html_e( 'Parameters: post_id, platform (facebook/linkedin/twitter/instagram/gmb), format (link/img/reel/video)', 'site-essentials' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Make.com Scenario Blueprint', 'site-essentials' ); ?></th>
			<td>
				<p><?php esc_html_e( 'The Social Amplification Make.com scenario handles AI prompt generation, post framing selection, and social content scheduling.', 'site-essentials' ); ?></p>
				<p>
					<a href="https://us2.make.com/public/shared-scenario/8mA5tz0TNtE/gs-social-amplification"
					   target="_blank" rel="noopener noreferrer" class="button button-secondary">
						&#x29C9; <?php esc_html_e( 'View / Copy Shared Scenario', 'site-essentials' ); ?>
					</a>
				</p>
				<p class="description">
					<?php esc_html_e( 'Open the link to preview the scenario. Use "Save a copy" in Make.com to add it to your account. You will need to reconnect your HTTP / ChatGPT / Gemini / Google Sheets modules and set your webhook URL below.', 'site-essentials' ); ?>
				</p>
			</td>
		</tr>
	</table>

<?php else : ?>

	<!-- ── Settings form (YOURLS + Make.com tabs share one form) ── -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'scos_sma_save', 'scos_sma_nonce' ); ?>
		<input type="hidden" name="action" value="site_essentials_save_sma">

		<?php if ( $active_tab === 'yourls' ) : ?>

			<h3 style="margin-top:0;"><?php esc_html_e( 'YOURLS Shortlink Integration', 'site-essentials' ); ?></h3>
			<p class="description" style="margin-bottom:18px;">
				<?php esc_html_e( 'Configure your self-hosted YOURLS installation. The shortlink slug entered on each post page is used as the YOURLS keyword.', 'site-essentials' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="scos_sma_yourls_url"><?php esc_html_e( 'YOURLS API URL', 'site-essentials' ); ?></label>
					</th>
					<td>
						<input type="url" id="scos_sma_yourls_url" name="scos_sma_yourls_url"
							value="<?php echo esc_attr( $yourls_url ); ?>"
							class="regular-text code" style="width:100%;max-width:560px;"
							placeholder="https://bweb1.com.au/yourls-api.php" />
						<p class="description"><?php esc_html_e( 'Full path to yourls-api.php on your YOURLS installation.', 'site-essentials' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="scos_sma_yourls_signature"><?php esc_html_e( 'Signature Token', 'site-essentials' ); ?></label>
					</th>
					<td>
						<input type="text" id="scos_sma_yourls_signature" name="scos_sma_yourls_signature"
							value="<?php echo esc_attr( $yourls_sig ); ?>"
							class="regular-text code" style="width:100%;max-width:560px;"
							autocomplete="off" />
						<p class="description">
							<?php esc_html_e( 'Recommended. Found in YOURLS Admin → Tools → Signature Token.', 'site-essentials' ); ?><br>
							<strong><?php esc_html_e( 'OR', 'site-essentials' ); ?></strong>
							<?php esc_html_e( 'use the username + password below (less secure).', 'site-essentials' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="scos_sma_yourls_username"><?php esc_html_e( 'Username', 'site-essentials' ); ?></label>
					</th>
					<td>
						<input type="text" id="scos_sma_yourls_username" name="scos_sma_yourls_username"
							value="<?php echo esc_attr( $yourls_user ); ?>"
							class="regular-text" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'Only needed if not using signature token.', 'site-essentials' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="scos_sma_yourls_password"><?php esc_html_e( 'Password', 'site-essentials' ); ?></label>
					</th>
					<td>
						<input type="password" id="scos_sma_yourls_password" name="scos_sma_yourls_password"
							value="<?php echo esc_attr( $yourls_pass ); ?>"
							class="regular-text" autocomplete="new-password" />
						<p class="description"><?php esc_html_e( 'Only needed if not using signature token.', 'site-essentials' ); ?></p>
					</td>
				</tr>
			</table>

		<?php else : /* make.com tab */ ?>

			<h3 style="margin-top:0;"><?php esc_html_e( 'Make.com Integration', 'site-essentials' ); ?></h3>
			<p class="description" style="margin-bottom:18px;">
				<?php esc_html_e( 'Configure the Make.com webhook that receives the social post trigger. The "Create Social Post" button on each post sends a payload to this URL.', 'site-essentials' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="scos_sma_webhook_url"><?php esc_html_e( 'Webhook URL', 'site-essentials' ); ?></label>
					</th>
					<td>
						<input type="url" id="scos_sma_webhook_url" name="scos_sma_webhook_url"
							value="<?php echo esc_attr( $webhook_url ); ?>"
							class="regular-text code" style="width:100%;max-width:560px;"
							placeholder="https://hook.us2.make.com/..." />
						<p class="description">
							<?php esc_html_e( 'Your Make.com custom webhook URL (starts with https://hook.us2.make.com/…).', 'site-essentials' ); ?>
							<a href="<?php echo esc_url( add_query_arg( 'tab', 'docs', $page_url ) ); ?>">
								<?php esc_html_e( 'See payload reference →', 'site-essentials' ); ?>
							</a>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="scos_sma_webhook_enabled"><?php esc_html_e( 'Auto-trigger on Publish', 'site-essentials' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="scos_sma_webhook_enabled" name="scos_sma_webhook_enabled" value="1"
								<?php checked( $webhook_enabled, 1 ); ?> />
							<?php esc_html_e( 'Automatically notify Make.com when a post is published or updated', 'site-essentials' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Leave off to use manual "Create Social Post" button only — recommended for controlled social scheduling.', 'site-essentials' ); ?>
						</p>
					</td>
				</tr>
			</table>

		<?php endif; ?>

		<?php submit_button( __( 'Save Settings', 'site-essentials' ) ); ?>

	</form>

<?php endif; ?>
</div>
