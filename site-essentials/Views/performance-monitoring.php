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

$ntfy_loaded  = class_exists( 'Brighter_Ntfy_Notifications' );
$ntfy_enabled = $ntfy_loaded && Brighter_Ntfy_Notifications::is_enabled();
$ntfy_client  = null;
$ntfy_ok      = false;

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
			'title'    => __( 'Monitoring Test', 'site-essentials' ),
			'priority' => 'default',
			'tags'     => [ 'test', 'white_check_mark' ],
			'click'    => admin_url( 'admin.php?page=site-essentials-essentials&tab=monitoring' ),
		]
	);
	$test_result = is_wp_error( $result ) ? 'failed' : 'success';
}
?>

<?php if ( $test_result === 'success' ) : ?>
	<div class="scos-notice scos-notice--success">
		<p><?php esc_html_e( 'Test notification sent successfully.', 'site-essentials' ); ?></p>
	</div>
<?php elseif ( $test_result === 'failed' ) : ?>
	<div class="scos-notice scos-notice--danger">
		<p><?php esc_html_e( 'Test notification failed. Check your ntfy configuration.', 'site-essentials' ); ?></p>
	</div>
<?php endif; ?>

<!-- ── Card 1: ntfy Push Notifications ──────────────────────────────────── -->
<div class="scos-card">
	<div class="scos-card__header">
		<h2 class="scos-card__title"><?php esc_html_e( 'ntfy Push Notifications', 'site-essentials' ); ?></h2>
	</div>
	<div class="scos-card__body">

		<?php if ( ! $ntfy_loaded ) : ?>
			<p><?php esc_html_e( 'Monitoring class not found. Ensure brighter-core is active.', 'site-essentials' ); ?></p>

		<?php elseif ( ! $ntfy_enabled ) : ?>
			<div class="scos-notice scos-notice--warning">
				<p>
					<strong><?php esc_html_e( 'ntfy is not enabled.', 'site-essentials' ); ?></strong>
					<?php esc_html_e( 'Add the following constants to your', 'site-essentials' ); ?>
					<code>wp-config.php</code>:
				</p>
				<pre>define('NTFY_ENABLED', true);
define('NTFY_SERVER_URL', 'https://ntfy.bweb1.com.au');
define('NTFY_USERNAME', 'your-username');
define('NTFY_PASSWORD', 'your-password');</pre>
			</div>

		<?php elseif ( ! $ntfy_ok ) : ?>
			<p>
				<strong><?php esc_html_e( 'ntfy is enabled but not fully configured.', 'site-essentials' ); ?></strong>
			</p>
			<p class="description"><?php esc_html_e( 'NTFY_SERVER_URL, NTFY_USERNAME, or NTFY_PASSWORD may be missing from wp-config.php.', 'site-essentials' ); ?></p>

		<?php else : ?>
			<p><strong><?php esc_html_e( 'Connected', 'site-essentials' ); ?></strong></p>
			<?php if ( defined( 'NTFY_SERVER_URL' ) ) : ?>
				<p class="description">
					<?php esc_html_e( 'Server:', 'site-essentials' ); ?>
					<code><?php echo esc_html( NTFY_SERVER_URL ); ?></code>
				</p>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'scos_ntfy_test' ); ?>
				<button type="submit" name="scos_ntfy_test" value="1" class="scos-btn scos-btn--primary">
					<?php esc_html_e( 'Send Test Notification', 'site-essentials' ); ?>
				</button>
				<span class="description">
					<?php esc_html_e( 'Sends a test push to your ntfy client app.', 'site-essentials' ); ?>
				</span>
			</form>
		<?php endif; ?>

	</div>
	<!-- no guide link for Card 1 per spec -->
</div>

<!-- ── Card 2: Automated Monitors ───────────────────────────────────────── -->
<div class="scos-card">
	<div class="scos-card__header">
		<h2 class="scos-card__title"><?php esc_html_e( 'Automated Monitors', 'site-essentials' ); ?></h2>
	</div>
	<div class="scos-card__body">

		<p class="description">
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

		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Monitor', 'site-essentials' ); ?></th>
					<th><?php esc_html_e( 'Description', 'site-essentials' ); ?></th>
					<th><?php esc_html_e( 'Enable constant', 'site-essentials' ); ?></th>
					<th><?php esc_html_e( 'Status', 'site-essentials' ); ?></th>
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
					<td class="description"><?php echo esc_html( $mon['description'] ); ?></td>
					<td><code><?php echo esc_html( $mon['constant'] ); ?></code></td>
					<td>
						<?php if ( ! $ntfy_enabled ) : ?>
							<span>—</span>
						<?php elseif ( $is_on ) : ?>
							<span>
								&#10003; <?php esc_html_e( 'On', 'site-essentials' ); ?>
								<?php if ( $mon['status'] === 'partial' ) : ?>
									<span>(<?php esc_html_e( 'partial', 'site-essentials' ); ?>)</span>
								<?php endif; ?>
							</span>
						<?php else : ?>
							<span>
								&#10007; <?php esc_html_e( 'Off', 'site-essentials' ); ?>
								<?php if ( $mon['status'] === 'partial' ) : ?>
									<span>(<?php esc_html_e( 'partial', 'site-essentials' ); ?>)</span>
								<?php endif; ?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="description">
			<?php esc_html_e( 'Use', 'site-essentials' ); ?>
			<code>NTFY_MONITOR_*</code>
			<?php esc_html_e( 'constants in wp-config.php (e.g. NTFY_MONITOR_SMTP). If a constant is omitted, brighter-core falls back to enabled for most monitors; forms stay opt-in (off).', 'site-essentials' ); ?>
		</p>

	</div>
	<div class="scos-card__footer">
		<a href="https://brighterwebsites.com.au/software/performance/ntfy-site-monitoring/"
		   target="_blank" rel="noopener" class="scos-btn scos-btn--ghost">
			<?php esc_html_e( 'View guide', 'site-essentials' ); ?>
		</a>
	</div>
</div>

<!-- ── Card 3: ntfy App ──────────────────────────────────────────────────── -->
<div class="scos-card">
	<div class="scos-card__header">
		<h2 class="scos-card__title"><?php esc_html_e( 'ntfy App', 'site-essentials' ); ?></h2>
	</div>
	<div class="scos-card__body">

		<p class="description">
			<?php esc_html_e( 'Notifications are delivered via', 'site-essentials' ); ?>
			<a href="https://ntfy.sh" target="_blank" rel="noopener">ntfy.sh</a>
			<?php esc_html_e( '(self-hosted instance). Install the free ntfy app on your phone or desktop to receive alerts.', 'site-essentials' ); ?>
		</p>
		<p>
			<a href="https://ntfy.sh/app" target="_blank" rel="noopener" class="scos-btn scos-btn--ghost">
				<?php esc_html_e( 'Get ntfy App', 'site-essentials' ); ?>
			</a>
			<?php if ( defined( 'NTFY_SERVER_URL' ) ) : ?>
				<a href="<?php echo esc_url( NTFY_SERVER_URL ); ?>" target="_blank" rel="noopener" class="scos-btn scos-btn--ghost">
					<?php esc_html_e( 'Open ntfy Server', 'site-essentials' ); ?>
				</a>
			<?php endif; ?>
		</p>

	</div>
	<div class="scos-card__footer">
		<a href="https://brighterwebsites.com.au/software/performance/ntfy-monitoring-setup/"
		   target="_blank" rel="noopener" class="scos-btn scos-btn--ghost">
			<?php esc_html_e( 'View guide', 'site-essentials' ); ?>
		</a>
	</div>
</div>
