<?php
/**
 * Performance — Monitoring tab
 *
 * Displays ntfy push-notification monitoring status, connection test,
 * and per-monitor toggle overview. Monitoring requires constants defined
 * in wp-config.php. Manual push notifications are fully functional;
 * some automated monitors are partially implemented.
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

$ntfy_loaded   = class_exists( 'Brighter_Ntfy_Notifications' );
$ntfy_enabled  = $ntfy_loaded && Brighter_Ntfy_Notifications::is_enabled();
$ntfy_client   = null;
$ntfy_ok       = false;

if ( $ntfy_enabled && class_exists( 'Brighter_Ntfy_Client' ) ) {
	$ntfy_client = new Brighter_Ntfy_Client();
	$ntfy_ok     = $ntfy_client->is_configured();
}

// Handle test-push action
$test_result = null;
if ( isset( $_POST['scos_ntfy_test'] ) && check_admin_referer( 'scos_ntfy_test' ) && $ntfy_ok ) {
	// Topic bw-test matches Brighter_Ntfy_Client::test_connection() and legacy Support tab.
	$result = $ntfy_client->send(
		'bw-test',
		sprintf(
			__( 'Manual test from Site Essentials on %s', 'site-essentials' ),
			home_url()
		),
		[
			'title'    => __( '✅ Monitoring Test', 'site-essentials' ),
			'priority' => 'default',
			'tags'     => [ 'test', 'white_check_mark' ],
			'click'    => admin_url( 'admin.php?page=site-essentials-essentials&tab=monitoring' ),
		]
	);
	$test_result = is_wp_error( $result ) ? 'failed' : 'success';
}
?>

<?php if ( $test_result === 'success' ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test notification sent successfully.', 'site-essentials' ); ?></p></div>
<?php elseif ( $test_result === 'failed' ) : ?>
	<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Test notification failed. Check your ntfy configuration.', 'site-essentials' ); ?></p></div>
<?php endif; ?>

<div style="max-width:860px;">

	<!-- ── Connection Status ── -->
	<div class="card" style="padding:24px 28px;margin-bottom:20px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'ntfy Push Notifications', 'site-essentials' ); ?></h2>

		<?php if ( ! $ntfy_loaded ) : ?>
			<p style="color:#dc3232;">
				<?php esc_html_e( 'Monitoring class not found. Ensure brighter-core is active.', 'site-essentials' ); ?>
			</p>
		<?php elseif ( ! $ntfy_enabled ) : ?>
			<div class="notice notice-warning" style="margin:0 0 16px;">
				<p>
					<strong><?php esc_html_e( 'ntfy is not enabled.', 'site-essentials' ); ?></strong>
					<?php esc_html_e( 'Add the following constants to your', 'site-essentials' ); ?>
					<code>wp-config.php</code>:
				</p>
				<pre style="background:#f6f7f7;padding:12px 16px;border-left:4px solid #ffb900;border-radius:0 4px 4px 0;overflow-x:auto;font-size:12px;margin:0;">define('NTFY_ENABLED', true);
define('NTFY_SERVER_URL', 'https://ntfy.bweb1.com.au');
define('NTFY_USERNAME', 'your-username');
define('NTFY_PASSWORD', 'your-password');</pre>
			</div>
		<?php elseif ( ! $ntfy_ok ) : ?>
			<p style="color:#dc3232;font-size:16px;">
				⚠ <strong><?php esc_html_e( 'ntfy is enabled but not fully configured.', 'site-essentials' ); ?></strong>
			</p>
			<p class="description"><?php esc_html_e( 'NTFY_SERVER_URL, NTFY_USERNAME, or NTFY_PASSWORD may be missing from wp-config.php.', 'site-essentials' ); ?></p>
		<?php else : ?>
			<p style="color:#46b450;font-size:16px;">✅ <strong><?php esc_html_e( 'Connected', 'site-essentials' ); ?></strong></p>
			<?php if ( defined( 'NTFY_SERVER_URL' ) ) : ?>
				<p class="description">
					<?php esc_html_e( 'Server:', 'site-essentials' ); ?>
					<code><?php echo esc_html( NTFY_SERVER_URL ); ?></code>
				</p>
			<?php endif; ?>

			<form method="post" style="margin-top:16px;">
				<?php wp_nonce_field( 'scos_ntfy_test' ); ?>
				<button type="submit" name="scos_ntfy_test" value="1" class="button button-primary">
					<?php esc_html_e( 'Send Test Notification', 'site-essentials' ); ?>
				</button>
				<span style="margin-left:10px;color:#646970;font-size:12px;">
					<?php esc_html_e( 'Sends a test push to your ntfy client app.', 'site-essentials' ); ?>
				</span>
			</form>
		<?php endif; ?>
	</div>

	<!-- ── Monitors ── -->
	<div class="card" style="padding:24px 28px;margin-bottom:20px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Automated Monitors', 'site-essentials' ); ?></h2>
		<p class="description" style="margin-bottom:16px;">
			<?php esc_html_e( 'Monitors run via WP-Cron and send push notifications when issues are detected. Toggle each monitor with a constant in wp-config.php.', 'site-essentials' ); ?>
			<?php esc_html_e( 'Note: WP-Cron and form monitors are opt-in and partially implemented; enable their constants only if you need them.', 'site-essentials' ); ?>
		</p>

		<?php
		// Constant names must match brighter-core: see Brighter_Ntfy_Notifications::is_monitor_enabled().
		$monitors = [
			[
				'id'          => 'downtime',
				'label'       => __( 'Downtime Monitor', 'site-essentials' ),
				'description' => __( 'Checks HTTP response, database, and filesystem health every ~5 minutes via WP-Cron.', 'site-essentials' ),
				'constant'    => 'NTFY_MONITOR_DOWNTIME',
				'status'      => 'active',
			],
			[
				'id'          => 'robots',
				'label'       => __( 'Robots.txt Monitor', 'site-essentials' ),
				'description' => __( 'Daily check that robots.txt does not block all crawlers.', 'site-essentials' ),
				'constant'    => 'NTFY_MONITOR_ROBOTS',
				'status'      => 'active',
			],
			[
				'id'          => 'sitemap',
				'label'       => __( 'Sitemap Monitor', 'site-essentials' ),
				'description' => __( 'Daily check that the XML sitemap is accessible.', 'site-essentials' ),
				'constant'    => 'NTFY_MONITOR_SITEMAP',
				'status'      => 'active',
			],
			[
				'id'          => 'smtp',
				'label'       => __( 'SMTP / Email Monitor', 'site-essentials' ),
				'description' => __( 'Sends an alert if WordPress email fails to deliver (hooks into wp_mail failures).', 'site-essentials' ),
				'constant'    => 'NTFY_MONITOR_SMTP',
				'status'      => 'active',
			],
			[
				'id'          => 'forms',
				'label'       => __( 'Form Submission Monitor', 'site-essentials' ),
				'description' => __( 'Notifies on new contact form submissions. Opt-in per form. Implementation is partial.', 'site-essentials' ),
				'constant'    => 'NTFY_MONITOR_FORMS',
				'status'      => 'partial',
			],
			[
				'id'          => 'cron',
				'label'       => __( 'WP-Cron Monitor', 'site-essentials' ),
				'description' => __( 'Stub only (missed-event detection not implemented). Opt-in: set NTFY_MONITOR_CRON true in wp-config to load; no useful alerts yet.', 'site-essentials' ),
				'constant'    => 'NTFY_MONITOR_CRON',
				'status'      => 'partial',
			],
		];
		?>
		<table class="widefat" style="border:none;">
			<thead>
				<tr>
					<th style="width:180px;"><?php esc_html_e( 'Monitor', 'site-essentials' ); ?></th>
					<th><?php esc_html_e( 'Description', 'site-essentials' ); ?></th>
					<th style="width:120px;"><?php esc_html_e( 'Enable constant', 'site-essentials' ); ?></th>
					<th style="width:80px;"><?php esc_html_e( 'Status', 'site-essentials' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $monitors as $mon ) :
					$is_on = $ntfy_loaded && method_exists( 'Brighter_Ntfy_Notifications', 'is_monitor_enabled' )
						? Brighter_Ntfy_Notifications::is_monitor_enabled( $mon['id'] )
						: ( defined( $mon['constant'] ) && constant( $mon['constant'] ) === true );
				?>
				<tr>
					<td><strong><?php echo esc_html( $mon['label'] ); ?></strong></td>
					<td style="font-size:12px;color:#646970;"><?php echo esc_html( $mon['description'] ); ?></td>
					<td><code style="font-size:11px;"><?php echo esc_html( $mon['constant'] ); ?></code></td>
					<td>
						<?php if ( ! $ntfy_enabled ) : ?>
							<span style="color:#999;">—</span>
						<?php elseif ( $is_on ) : ?>
							<span style="color:#46b450;">
								✓ <?php esc_html_e( 'On', 'site-essentials' ); ?>
								<?php if ( $mon['status'] === 'partial' ) : ?>
									<span style="color:#ffb900;font-size:11px;"> (<?php esc_html_e( 'partial', 'site-essentials' ); ?>)</span>
								<?php endif; ?>
							</span>
						<?php else : ?>
							<span style="color:#dc3232;">
								✗ <?php esc_html_e( 'Off', 'site-essentials' ); ?>
								<?php if ( $mon['status'] === 'partial' ) : ?>
									<span style="color:#ffb900;font-size:11px;"> (<?php esc_html_e( 'partial', 'site-essentials' ); ?>)</span>
								<?php endif; ?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="description" style="margin-top:12px;">
			<?php esc_html_e( 'Use', 'site-essentials' ); ?>
			<code>NTFY_MONITOR_*</code>
			<?php esc_html_e( 'constants in wp-config.php (e.g. NTFY_MONITOR_SMTP). If a constant is omitted, brighter-core falls back to enabled for most monitors; forms stay opt-in (off).', 'site-essentials' ); ?>
		</p>
	</div>

	<!-- ── ntfy App ── -->
	<div class="card" style="padding:24px 28px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'ntfy App', 'site-essentials' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Notifications are delivered via', 'site-essentials' ); ?>
			<a href="https://ntfy.sh" target="_blank" rel="noopener">ntfy.sh</a>
			<?php esc_html_e( '(self-hosted instance). Install the free ntfy app on your phone or desktop to receive alerts.', 'site-essentials' ); ?>
		</p>
		<p>
			<a href="https://ntfy.sh/app" target="_blank" rel="noopener" class="button button-secondary">
				<?php esc_html_e( 'Get ntfy App', 'site-essentials' ); ?>
			</a>
			<?php if ( defined( 'NTFY_SERVER_URL' ) ) : ?>
				&nbsp;
				<a href="<?php echo esc_url( NTFY_SERVER_URL ); ?>" target="_blank" rel="noopener" class="button button-secondary">
					<?php esc_html_e( 'Open ntfy Server', 'site-essentials' ); ?>
				</a>
			<?php endif; ?>
		</p>
	</div>

</div>
