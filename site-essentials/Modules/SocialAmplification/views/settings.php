<?php
/**
 * Social Amplification — Settings page view
 *
 * Rendered by SocialAmplification_Module::render_settings() via Admin_UI.
 *
 * @package SiteEssentials
 */

defined( 'ABSPATH' ) || exit;

use SiteEssentials\Modules\SocialAmplification\Post_Framing;
use SiteEssentials\Modules\SocialAmplification\SocialAmplification_Module as SMA;

// Option reader: prefers scos_sma_* over legacy bw_*
$webhook_url     = SMA::get_option( 'scos_sma_webhook_url',     'bw_social_webhook_url' );
$webhook_enabled = SMA::get_option( 'scos_sma_webhook_enabled', 'bw_social_webhook_enabled', 0 );
$yourls_url      = SMA::get_option( 'scos_sma_yourls_url',      'bw_yourls_api_url' );
$yourls_sig      = SMA::get_option( 'scos_sma_yourls_signature', 'bw_yourls_signature' );
$yourls_user     = SMA::get_option( 'scos_sma_yourls_username',  'bw_yourls_username' );
$yourls_pass     = SMA::get_option( 'scos_sma_yourls_password',  'bw_yourls_password' );

// Postly / Anthropic fields
$postly_api_key        = get_option( 'bw_postly_api_key', '' );
$postly_workspace_id   = get_option( 'bw_postly_workspace_id', '' );
$postly_channel_ids    = get_option( 'bw_postly_channel_ids', '' );
$acf_gallery_keys      = get_option( 'bw_social_acf_gallery_keys', '' );
$acf_featured_key      = get_option( 'bw_social_acf_featured_key', '' );
$webhook_secret        = get_option( 'bw_social_webhook_secret', '' );
$social_enabled        = get_option( 'bw_social_enabled', '' );
$publish_time_min      = get_option( 'bw_social_publish_time_min', '09:00' );
$publish_time_max      = get_option( 'bw_social_publish_time_max', '17:00' );

// Last run log entry
$amplify_log      = get_option( \SiteEssentials\Modules\SocialAmplification\Amplification\Amplification_Engine::LOG_OPTION, [] );
$last_log_entries = is_array( $amplify_log ) ? array_slice( array_reverse( $amplify_log, true ), 0, 5, true ) : [];
$unamplified_projects = get_posts( [
	'post_type'      => 'projects',
	'post_status'    => 'publish',
	'posts_per_page' => 50,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'meta_query'     => [
		'relation' => 'OR',
		[
			'key'     => '_scos_sa_amplified',
			'compare' => 'NOT EXISTS',
		],
		[
			'key'     => '_scos_sa_amplified',
			'value'   => '1',
			'compare' => '!=',
		],
	],
] );

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'yourls';
$page_url   = admin_url( 'admin.php?page=site-essentials-social-amplification' );

// Meta box status
$post_types = \SiteEssentials\Modules\SocialAmplification\Meta_Fields::get_post_types();
?>

<!-- ── Quick links ── -->
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
	<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Post_Framing::POST_TYPE ) ); ?>"
	   class="button button-secondary" style="display:inline-flex;align-items:center;gap:6px;">
		<span class="dashicons dashicons-edit" style="margin-top:3px;font-size:16px;"></span>
		<?php esc_html_e( 'Post Framing', 'site-essentials' ); ?>
	</a>
	<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Post_Framing::POST_TYPE ) ); ?>"
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
	<a href="<?php echo esc_url( add_query_arg( 'tab', 'postly', $page_url ) ); ?>"
	   class="nav-tab <?php echo $active_tab === 'postly'  ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'Postly.ai', 'site-essentials' ); ?>
	</a>
	<a href="<?php echo esc_url( add_query_arg( 'tab', 'backfill', $page_url ) ); ?>"
	   class="nav-tab <?php echo $active_tab === 'backfill' ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'Schedule Existing Content', 'site-essentials' ); ?>
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

<?php elseif ( $active_tab === 'backfill' ) : ?>

	<h3 style="margin-top:0;"><?php esc_html_e( 'Schedule Existing Content', 'site-essentials' ); ?></h3>
	<p class="description" style="margin-bottom:18px;">
		<?php esc_html_e( 'Run Social Amplification for existing projects posts. Choose date range or select specific posts.', 'site-essentials' ); ?>
	</p>

	<div id="scos-sa-backfill-wrap"
		data-secret="<?php echo esc_attr( $webhook_secret ); ?>"
		data-rest="<?php echo esc_attr( rest_url( 'bw-social/v1/backfill' ) ); ?>">

		<p>
			<label><input type="radio" name="scos_sa_backfill_mode" value="date" checked> <?php esc_html_e( 'By date range', 'site-essentials' ); ?></label>
			&nbsp;&nbsp;
			<label><input type="radio" name="scos_sa_backfill_mode" value="posts"> <?php esc_html_e( 'Select posts', 'site-essentials' ); ?></label>
		</p>

		<div class="scos-sa-backfill-mode" data-mode="date">
			<table class="form-table">
				<tr>
					<th><label for="scos_sa_backfill_from"><?php esc_html_e( 'Date from', 'site-essentials' ); ?></label></th>
					<td><input type="date" id="scos_sa_backfill_from" value="<?php echo esc_attr( gmdate( 'Y-m-01' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="scos_sa_backfill_to"><?php esc_html_e( 'Date to', 'site-essentials' ); ?></label></th>
					<td><input type="date" id="scos_sa_backfill_to" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="scos_sa_backfill_limit"><?php esc_html_e( 'Limit', 'site-essentials' ); ?></label></th>
					<td><input type="number" id="scos_sa_backfill_limit" min="1" max="100" value="5"></td>
				</tr>
			</table>
		</div>

		<div class="scos-sa-backfill-mode" data-mode="posts" style="display:none;">
			<p><strong><?php esc_html_e( 'Unamplified projects', 'site-essentials' ); ?></strong></p>
			<div style="max-height:220px;overflow:auto;border:1px solid #dcdcde;padding:10px;background:#fff;">
				<?php if ( empty( $unamplified_projects ) ) : ?>
					<p><?php esc_html_e( 'No unamplified projects found.', 'site-essentials' ); ?></p>
				<?php else : ?>
					<?php foreach ( $unamplified_projects as $project ) : ?>
						<label style="display:block;margin-bottom:6px;">
							<input type="checkbox" class="scos-sa-backfill-post-id" value="<?php echo esc_attr( $project->ID ); ?>">
							<?php echo esc_html( get_the_title( $project ) ); ?>
							<span style="color:#8c8f94;">(<?php echo esc_html( mysql2date( 'Y-m-d', $project->post_date ) ); ?>)</span>
						</label>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<p style="margin-top:14px;">
			<button type="button" class="button button-primary" id="scos-sa-run-backfill">
				<?php esc_html_e( 'Run Backfill', 'site-essentials' ); ?>
			</button>
		</p>
		<div id="scos-sa-backfill-status" class="scos-sa-result" hidden></div>
		<div id="scos-sa-backfill-results"></div>
	</div>

<?php elseif ( $active_tab === 'postly' ) : ?>

	<!-- ── Postly.ai Settings ── -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'scos_sma_save', 'scos_sma_nonce' ); ?>
		<input type="hidden" name="action" value="site_essentials_save_sma">
		<input type="hidden" name="_scos_sma_tab" value="postly">

		<h3 style="margin-top:0;"><?php esc_html_e( 'Postly.ai Social Amplification', 'site-essentials' ); ?></h3>
		<p class="description" style="margin-bottom:18px;">
			<?php esc_html_e( 'Automatically generate and schedule 3 social posts when a Projects post is published. Uses Anthropic AI for caption generation and Postly.ai for scheduling. Anthropic API key is managed in Settings → AI API Keys.', 'site-essentials' ); ?>
		</p>

		<table class="form-table">

			<!-- Enable toggle -->
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Social Amplification', 'site-essentials' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bw_social_enabled" value="1" <?php checked( $social_enabled, '1' ); ?> />
						<?php esc_html_e( 'Automatically amplify when a Projects post is published', 'site-essentials' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Requires Postly API key, Workspace ID, and Anthropic API key (in Settings → AI API Keys) to be configured.', 'site-essentials' ); ?>
					</p>
				</td>
			</tr>

			<!-- Publish time window -->
			<tr>
				<th scope="row"><?php esc_html_e( 'Publish Time Window', 'site-essentials' ); ?></th>
				<td>
					<label for="bw_social_publish_time_min"><?php esc_html_e( 'From', 'site-essentials' ); ?></label>
					<input type="time" id="bw_social_publish_time_min" name="bw_social_publish_time_min"
						value="<?php echo esc_attr( $publish_time_min ); ?>"
						style="width:110px;" />
					<label for="bw_social_publish_time_max" style="margin-left:12px;"><?php esc_html_e( 'To', 'site-essentials' ); ?></label>
					<input type="time" id="bw_social_publish_time_max" name="bw_social_publish_time_max"
						value="<?php echo esc_attr( $publish_time_max ); ?>"
						style="width:110px;" />
					<p class="description">
						<?php esc_html_e( 'Posts are scheduled at a random time within this window (site timezone). Slot 1 is always pushed at least 60 minutes into the future to allow for Postly processing and human approval. Times are read in the site\'s configured timezone.', 'site-essentials' ); ?>
					</p>
				</td>
			</tr>

			<!-- Postly API Key -->
			<tr>
				<th scope="row">
					<label for="bw_postly_api_key"><?php esc_html_e( 'Postly API Key', 'site-essentials' ); ?></label>
				</th>
				<td>
					<input type="password" id="bw_postly_api_key" name="bw_postly_api_key"
						value="<?php echo esc_attr( $postly_api_key ); ?>"
						class="regular-text code" autocomplete="new-password"
						style="width:100%;max-width:560px;" />
					<p class="description">
						<?php esc_html_e( 'Your Postly.ai API key. Get it from ', 'site-essentials' ); ?>
						<a href="https://app.postly.ai" target="_blank" rel="noopener">app.postly.ai</a>.
					</p>
				</td>
			</tr>

			<!-- Postly Workspace ID -->
			<tr>
				<th scope="row">
					<label for="bw_postly_workspace_id"><?php esc_html_e( 'Postly Workspace ID', 'site-essentials' ); ?></label>
				</th>
				<td>
					<input type="text" id="bw_postly_workspace_id" name="bw_postly_workspace_id"
						value="<?php echo esc_attr( $postly_workspace_id ); ?>"
						class="regular-text code" style="width:100%;max-width:560px;" />
					<p class="description">
						<?php esc_html_e( 'The workspace ID from your Postly account (find it in the Workspace settings URL).', 'site-essentials' ); ?>
					</p>
				</td>
			</tr>

			<!-- Channel IDs (optional) -->
			<tr>
				<th scope="row">
					<label for="bw_postly_channel_ids"><?php esc_html_e( 'Target Channel IDs', 'site-essentials' ); ?></label>
				</th>
				<td>
					<input type="text" id="bw_postly_channel_ids" name="bw_postly_channel_ids"
						value="<?php echo esc_attr( $postly_channel_ids ); ?>"
						class="regular-text code" style="width:100%;max-width:560px;"
						placeholder="<?php esc_attr_e( 'id1, id2, id3', 'site-essentials' ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Comma-separated Postly social channel IDs to post to. Leave blank to post to all channels connected in the workspace. Find IDs via the Postly API: GET /workspaces/{id}/socials.', 'site-essentials' ); ?>
					</p>
				</td>
			</tr>

			<!-- ACF Gallery Keys -->
			<tr>
				<th scope="row">
					<label for="bw_social_acf_gallery_keys"><?php esc_html_e( 'ACF Gallery Field Keys', 'site-essentials' ); ?></label>
				</th>
				<td>
					<input type="text" id="bw_social_acf_gallery_keys" name="bw_social_acf_gallery_keys"
						value="<?php echo esc_attr( $acf_gallery_keys ); ?>"
						class="regular-text code" style="width:100%;max-width:560px;"
						placeholder="<?php esc_attr_e( 'project_gallery, secondary_gallery', 'site-essentials' ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Comma-separated ACF field keys that contain gallery images. These are combined with the featured image for post image sets.', 'site-essentials' ); ?>
					</p>
				</td>
			</tr>

			<!-- ACF Featured Image Key (optional override) -->
			<tr>
				<th scope="row">
					<label for="bw_social_acf_featured_key"><?php esc_html_e( 'ACF Featured Image Key', 'site-essentials' ); ?></label>
				</th>
				<td>
					<input type="text" id="bw_social_acf_featured_key" name="bw_social_acf_featured_key"
						value="<?php echo esc_attr( $acf_featured_key ); ?>"
						class="regular-text code" style="width:100%;max-width:560px;" />
					<p class="description">
						<?php esc_html_e( 'Optional. ACF field key for a custom featured/hero image. Overrides the standard WordPress featured image as the first image. Leave blank to use the WP featured image.', 'site-essentials' ); ?>
					</p>
				</td>
			</tr>

			<!-- Webhook Secret -->
			<tr>
				<th scope="row">
					<label for="bw_social_webhook_secret"><?php esc_html_e( 'Webhook Secret', 'site-essentials' ); ?></label>
				</th>
				<td>
					<?php if ( $webhook_secret ) : ?>
						<div style="display:flex;align-items:center;gap:10px;max-width:560px;">
							<input type="text" id="bw_social_webhook_secret_display"
								value="<?php echo esc_attr( $webhook_secret ); ?>"
								class="regular-text code" readonly style="flex:1;background:#f6f7f7;" />
							<button type="button" class="button"
								onclick="navigator.clipboard.writeText('<?php echo esc_js( $webhook_secret ); ?>').then(()=>this.textContent='Copied!').catch(()=>{})">
								<?php esc_html_e( 'Copy', 'site-essentials' ); ?>
							</button>
						</div>
					<?php else : ?>
						<p class="description" style="color:#b45309;"><?php esc_html_e( 'Not yet generated — save settings to auto-generate.', 'site-essentials' ); ?></p>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'Auto-generated. Used to authenticate the internal REST endpoint (POST /wp-json/bw-social/v1/amplify). Keep private.', 'site-essentials' ); ?>
					</p>
					<!-- Hidden: preserve secret across saves (will not change if already set) -->
					<input type="hidden" name="bw_social_webhook_secret" value="<?php echo esc_attr( $webhook_secret ); ?>" />
				</td>
			</tr>

		</table>

		<?php submit_button( __( 'Save Postly Settings', 'site-essentials' ) ); ?>

	</form>

	<!-- ── Status / Recent Runs ── -->
	<hr style="margin:28px 0 20px;">
	<h3 style="margin-top:0;"><?php esc_html_e( 'Recent Amplification Runs', 'site-essentials' ); ?></h3>

	<?php if ( empty( $last_log_entries ) ) : ?>
		<p class="description"><?php esc_html_e( 'No posts have been amplified yet.', 'site-essentials' ); ?></p>
	<?php else : ?>
		<table class="widefat striped" style="max-width:900px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Post', 'site-essentials' ); ?></th>
					<th><?php esc_html_e( 'Run At', 'site-essentials' ); ?></th>
					<th><?php esc_html_e( 'Shortlink', 'site-essentials' ); ?></th>
					<th><?php esc_html_e( 'Posts Scheduled', 'site-essentials' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $last_log_entries as $pid => $entry ) :
				$post_title = get_the_title( $pid );
				$scheduled_count = count( array_filter( $entry['posts'] ?? [], static fn( $p ) => ( $p['status'] ?? '' ) === 'scheduled' ) );
				$error_count     = count( array_filter( $entry['posts'] ?? [], static fn( $p ) => ( $p['status'] ?? '' ) === 'error' ) );
			?>
				<tr>
					<td>
						<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">
							<?php echo esc_html( $post_title ?: "#$pid" ); ?>
						</a>
					</td>
					<td><?php echo esc_html( $entry['ran_at'] ?? '—' ); ?></td>
					<td>
						<?php if ( ! empty( $entry['shortlink'] ) ) : ?>
							<a href="<?php echo esc_url( $entry['shortlink'] ); ?>" target="_blank" rel="noopener">
								<?php echo esc_html( $entry['shortlink'] ); ?>
							</a>
						<?php else : ?>—<?php endif; ?>
					</td>
					<td>
						<?php if ( $scheduled_count > 0 ) : ?>
							<span style="color:#16a34a;">&#10003; <?php echo esc_html( $scheduled_count ); ?> scheduled</span>
						<?php endif; ?>
						<?php if ( $error_count > 0 ) : ?>
							<span style="color:#b45309;margin-left:8px;">&#x26A0; <?php echo esc_html( $error_count ); ?> failed</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

<?php else : ?>

	<!-- ── Settings form (YOURLS + Make.com tabs share one form) ── -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'scos_sma_save', 'scos_sma_nonce' ); ?>
		<input type="hidden" name="action" value="site_essentials_save_sma">
		<input type="hidden" name="_scos_sma_tab" value="<?php echo esc_attr( $active_tab ); ?>">

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
