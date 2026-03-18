<?php
/**
 * Analytics Module — Site Essentials settings panel.
 */
defined( 'ABSPATH' ) || exit;

$ga4_id    = get_option( 'brighter_ga4_measurement_id', '' );
$seeded    = get_transient( 'brighter_ga4_events_seeded' );
$seed_date = get_transient( 'brighter_ga4_seed_date' );
$seed_url  = home_url( '/?seedEvents=true' );
$reset_url = admin_url( 'admin.php?page=site-essentials-settings&scos_reset_seed=1' );

// Handle seed flag reset
if ( isset( $_GET['scos_reset_seed'] ) && current_user_can( 'manage_options' ) ) {
	delete_transient( 'brighter_ga4_events_seeded' );
	delete_transient( 'brighter_ga4_seed_date' );
	$seeded    = false;
	$seed_date = false;
}
?>

<div class="scos-analytics-settings">

	<?php if ( isset( $_POST['scos_analytics_nonce'] ) ) : ?>
		<div class="notice notice-success inline is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'site-essentials' ); ?></p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'scos_analytics_settings', 'scos_analytics_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="brighter_ga4_measurement_id"><?php esc_html_e( 'GA4 Measurement ID', 'site-essentials' ); ?></label>
				</th>
				<td>
					<input type="text"
						id="brighter_ga4_measurement_id"
						name="brighter_ga4_measurement_id"
						value="<?php echo esc_attr( $ga4_id ); ?>"
						class="regular-text"
						placeholder="G-XXXXXXXXXX" />
					<p class="description">
						<?php esc_html_e( 'Your GA4 Measurement ID. Find it in GA4 → Admin → Data Streams → Web Stream Details.', 'site-essentials' ); ?>
						<?php if ( $ga4_id ) : ?>
							<span style="color:#1a7e3d;font-weight:600;margin-left:6px">&#10003; <?php esc_html_e( 'Configured', 'site-essentials' ); ?></span>
						<?php else : ?>
							<span style="color:#b45309;font-weight:600;margin-left:6px">&#9888; <?php esc_html_e( 'Not set', 'site-essentials' ); ?></span>
						<?php endif; ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save GA4 Settings', 'site-essentials' ), 'primary', 'submit', false ); ?>
	</form>

	<hr style="margin:28px 0 24px">

	<!-- ── Event Seeding Status ────────────────────────────────────────── -->
	<h3 style="margin-top:0"><?php esc_html_e( 'GA4 Event Seeding', 'site-essentials' ); ?></h3>

	<?php if ( $seeded ) : ?>

		<div class="notice notice-success inline" style="padding:10px 14px">
			<p>
				<strong><?php esc_html_e( 'Events Seeded', 'site-essentials' ); ?></strong>
				&mdash;
				<?php
				printf(
					/* translators: %s = date/time */
					esc_html__( 'Seeded on %s.', 'site-essentials' ),
					'<strong>' . esc_html( $seed_date ) . '</strong>'
				);
				?>
			</p>
		</div>
		<p>
			<a href="<?php echo esc_url( $reset_url ); ?>"
				class="button button-secondary"
				onclick="return confirm('<?php esc_attr_e( 'Reset seed flag so you can re-seed?', 'site-essentials' ); ?>')">
				<?php esc_html_e( 'Reset &amp; Re-Seed', 'site-essentials' ); ?>
			</a>
		</p>

	<?php else : ?>

		<div class="notice notice-warning inline" style="padding:10px 14px">
			<p><strong><?php esc_html_e( 'Events Not Yet Seeded', 'site-essentials' ); ?></strong>
			&mdash; <?php esc_html_e( 'GA4 won\'t show your events until they\'ve fired at least once.', 'site-essentials' ); ?></p>
		</div>

		<?php if ( $ga4_id ) : ?>
			<p>
				<a href="<?php echo esc_url( $seed_url ); ?>"
					class="button button-primary"
					target="_blank">
					<?php esc_html_e( 'Seed Events Now', 'site-essentials' ); ?>
				</a>
				<span class="description" style="margin-left:10px">
					<?php esc_html_e( 'Opens your site homepage with ?seedEvents=true — keep it open for ~10 seconds, then close.', 'site-essentials' ); ?>
				</span>
			</p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Enter your GA4 Measurement ID above before seeding.', 'site-essentials' ); ?></p>
		<?php endif; ?>

	<?php endif; ?>

	<hr style="margin:28px 0 24px">

	<!-- ── How It Works ────────────────────────────────────────────────── -->
	<h3 style="margin-top:0"><?php esc_html_e( 'How It Works', 'site-essentials' ); ?></h3>

	<p>
		<?php esc_html_e( 'Every page view automatically sends content strategy metadata to GA4 as custom dimensions — cluster, topic, intent, purpose, maturity, pillar, and service pathway. This lets you analyse performance by strategy, not just traffic.', 'site-essentials' ); ?>
	</p>

	<ol style="line-height:1.9;max-width:560px">
		<li><?php esc_html_e( 'Add your GA4 Measurement ID above.', 'site-essentials' ); ?></li>
		<li>
			<?php esc_html_e( 'Register custom dimensions in GA4 → Admin → Custom Definitions:', 'site-essentials' ); ?>
			<br>
			<code>altc_primary</code>, <code>altc_topic</code>, <code>content_maturity</code>, <code>content_intent</code>, <code>content_purpose</code>
		</li>
		<li><?php esc_html_e( 'Seed events so GA4 registers them immediately (low-traffic sites especially).', 'site-essentials' ); ?></li>
		<li><?php esc_html_e( 'Mark conversions in GA4 → Admin → Events, then build funnels and attribution reports.', 'site-essentials' ); ?></li>
	</ol>

	<p>
		<a href="https://brighterwebsites.com.au/software/analytics-module/" target="_blank" rel="noopener">
			<?php esc_html_e( 'Full Analytics Module documentation &rarr;', 'site-essentials' ); ?>
		</a>
	</p>

</div>
