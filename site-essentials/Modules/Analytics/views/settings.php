<?php
/**
 * Analytics Module — settings panel.
 *
 * Replaces the legacy brighter-analytics multi-tab admin page.
 * Reset and save handlers are registered in Analytics_Module::admin_init hooks.
 */
defined( 'ABSPATH' ) || exit;

$ga4_id    = get_option( 'brighter_ga4_measurement_id', '' );
$seeded    = get_transient( 'brighter_ga4_events_seeded' );
$seed_date = get_transient( 'brighter_ga4_seed_date' );
$seed_url  = home_url( '/?seedEvents=true' );
$reset_url = admin_url( 'admin.php?page=site-essentials-analytics&scos_reset_seed=1' );
?>

<header class="scos__header">
	<div>
		<h1 class="scos__title"><?php esc_html_e( 'Analytics', 'site-essentials' ); ?></h1>
		<p class="scos__subtitle">Site Essentials &rsaquo; Analytics</p>
	</div>
	<div class="scos__header-actions">
		<!-- no page-level primary action for this page -->
	</div>
</header>

<?php if ( isset( $_GET['scos_analytics_saved'] ) ) : ?>
	<div class="scos-notice scos-notice--success">
		<p><?php esc_html_e( 'Settings saved.', 'site-essentials' ); ?></p>
	</div>
<?php endif; ?>

<!-- ── Card 1: Google Analytics Set Up ────────────────────────────────── -->
<div class="scos-card">
	<div class="scos-card__header">
		<h2 class="scos-card__title">
			<?php esc_html_e( 'Google Analytics Set Up', 'site-essentials' ); ?>
		</h2>
	</div>
	<form method="post">
		<?php wp_nonce_field( 'scos_analytics_settings', 'scos_analytics_nonce' ); ?>
		<div class="scos-card__body">
			<table class="scos-form">
				<tbody>
					<tr>
						<th>
							<label for="brighter_ga4_measurement_id">
								<?php esc_html_e( 'GA4 Measurement ID', 'site-essentials' ); ?>
							</label>
							<div class="scos-form__slug">brighter_ga4_measurement_id</div>
						</th>
						<td>
							<input
								id="brighter_ga4_measurement_id"
								name="brighter_ga4_measurement_id"
								type="text"
								class="scos-input scos-input--mono"
								value="<?php echo esc_attr( $ga4_id ); ?>"
								placeholder="G-XXXXXXXXXX"
							>
							<p class="description">
								<?php esc_html_e( 'Enter your GA4 Measurement ID. Find it in Google Analytics → Admin → Data Streams → Web Stream Details.', 'site-essentials' ); ?>
								<?php if ( $ga4_id ) : ?>
									<strong class="scos-status scos-status--ok">&#10003; <?php esc_html_e( 'Configured', 'site-essentials' ); ?></strong>
								<?php else : ?>
									<strong class="scos-status scos-status--warn">&#9888; <?php esc_html_e( 'Not set', 'site-essentials' ); ?></strong>
								<?php endif; ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="scos-card__footer">
			<button type="submit" name="submit" class="scos-btn scos-btn--primary">
				<?php esc_html_e( 'Save GA4 Settings', 'site-essentials' ); ?>
			</button>
		</div>
	</form>
</div>

<!-- ── Card 2: Google Analytics Event Seeding ─────────────────────────── -->
<div class="scos-card">
	<div class="scos-card__header">
		<h2 class="scos-card__title">
			<?php esc_html_e( 'Google Analytics Event Seeding', 'site-essentials' ); ?>
		</h2>
		<p class="scos-card__desc">
			<?php esc_html_e( 'Seed your Analytics Events', 'site-essentials' ); ?>
		</p>
	</div>
	<div class="scos-card__body">

		<?php if ( $seeded ) : ?>

			<div class="scos-notice scos-notice--success">
				<p>
					<strong><?php esc_html_e( 'Events Seeded', 'site-essentials' ); ?></strong>
					&mdash;
					<?php
					printf(
						/* translators: %s = date/time string */
						esc_html__( 'Seeded on %s.', 'site-essentials' ),
						'<strong>' . esc_html( $seed_date ) . '</strong>'
					);
					?>
				</p>
			</div>

			<p>
				<a href="<?php echo esc_url( $reset_url ); ?>"
					class="scos-btn scos-btn--ghost"
					onclick="return confirm('<?php esc_attr_e( 'Reset seed flag so you can re-seed?', 'site-essentials' ); ?>')">
					<?php esc_html_e( 'Reset &amp; Re-Seed', 'site-essentials' ); ?>
				</a>
			</p>

			<h4><?php esc_html_e( 'Events Registered', 'site-essentials' ); ?></h4>
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Event Name', 'site-essentials' ); ?></th>
						<th><?php esc_html_e( 'Category', 'site-essentials' ); ?></th>
						<th><?php esc_html_e( 'Purpose', 'site-essentials' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr><td><code>click_meeting</code></td><td><?php esc_html_e( 'Meetings', 'site-essentials' ); ?></td><td><strong><?php esc_html_e( 'High-value conversion', 'site-essentials' ); ?></strong></td></tr>
					<tr><td><code>generate_lead</code></td><td><?php esc_html_e( 'Forms', 'site-essentials' ); ?></td><td><strong><?php esc_html_e( 'Primary conversion — mark as conversion in GA4 Admin → Events', 'site-essentials' ); ?></strong></td></tr>
					<tr><td><code>form_submit</code></td><td><?php esc_html_e( 'Forms', 'site-essentials' ); ?></td><td><strong><?php esc_html_e( 'Primary conversion', 'site-essentials' ); ?></strong></td></tr>
					<tr><td><code>click_main_cta</code></td><td><?php esc_html_e( 'Quote', 'site-essentials' ); ?></td><td><?php esc_html_e( 'Conversion intent', 'site-essentials' ); ?></td></tr>
					<tr><td><code>get_lead_magnet</code></td><td><?php esc_html_e( 'Lead Magnet', 'site-essentials' ); ?></td><td><?php esc_html_e( 'Lead generation', 'site-essentials' ); ?></td></tr>
					<tr><td><code>click_phone</code></td><td><?php esc_html_e( 'Contact', 'site-essentials' ); ?></td><td><?php esc_html_e( 'Direct contact', 'site-essentials' ); ?></td></tr>
					<tr><td><code>click_email</code></td><td><?php esc_html_e( 'Contact', 'site-essentials' ); ?></td><td><?php esc_html_e( 'Direct contact', 'site-essentials' ); ?></td></tr>
					<tr><td><code>view_pricing</code></td><td><?php esc_html_e( 'Trust', 'site-essentials' ); ?></td><td><?php esc_html_e( 'Purchase consideration', 'site-essentials' ); ?></td></tr>
					<tr><td><code>subscribe</code></td><td><?php esc_html_e( 'Subscribe', 'site-essentials' ); ?></td><td><?php esc_html_e( 'Lead nurture', 'site-essentials' ); ?></td></tr>
					<tr><td colspan="3"><?php esc_html_e( '+ 15 more engagement events', 'site-essentials' ); ?></td></tr>
				</tbody>
			</table>

			<div class="scos-notice scos-notice--info">
				<p>
					<strong><?php esc_html_e( 'Filter seed events from reports:', 'site-essentials' ); ?></strong>
					<?php esc_html_e( 'In GA4 Explorations add a filter:', 'site-essentials' ); ?>
					<code>event_label does not contain "[SEED]"</code>
				</p>
			</div>

			<h4><?php esc_html_e( 'Next Steps', 'site-essentials' ); ?></h4>
			<ol>
				<li><?php esc_html_e( 'Go to GA4 → Admin → Events', 'site-essentials' ); ?></li>
				<li><?php esc_html_e( 'Events appear within 24 hours — mark high-value ones as conversions', 'site-essentials' ); ?></li>
				<li><?php esc_html_e( 'Set up Attribution Settings in GA4 → Admin → Attribution', 'site-essentials' ); ?></li>
				<li><?php esc_html_e( 'Build conversion funnels in Explorations', 'site-essentials' ); ?></li>
			</ol>

		<?php else : ?>

			<p>
				<?php esc_html_e( 'Seeding fires events once and registers them in Google Analytics immediately — so you can set up conversions, attribution and reports right away.', 'site-essentials' ); ?>
			</p>

			<?php if ( $ga4_id ) : ?>
				<p>
					<a href="<?php echo esc_url( $seed_url ); ?>"
						class="scos-btn scos-btn--primary"
						target="_blank">
						<?php esc_html_e( 'Seed Events Now', 'site-essentials' ); ?>
					</a>
				</p>
				<p class="description">
					<?php esc_html_e( 'Click Seed Events Now — your homepage will open with ?seedEvents=true. Keep it open for about 10 seconds, then close.', 'site-essentials' ); ?>
				</p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Enter your GA4 Measurement ID above before seeding.', 'site-essentials' ); ?></p>
			<?php endif; ?>

		<?php endif; ?>

	</div>
</div>

<!-- ── Card 3: About the Analytics Module ─────────────────────────────── -->
<div class="scos-card">
	<div class="scos-card__header">
		<h2 class="scos-card__title">
			<?php esc_html_e( 'About the Analytics Module', 'site-essentials' ); ?>
		</h2>
	</div>
	<div class="scos-card__body">

		<h3><?php esc_html_e( 'Google Analytics Setup', 'site-essentials' ); ?></h3>
		<p>
			<?php esc_html_e( 'Learn how to set up and connect your Google Analytics property.', 'site-essentials' ); ?>
			<a href="https://brighterwebsites.com.au/software/analytics/ga4-setup/"
				target="_blank" rel="noopener">
				<?php esc_html_e( 'View guide →', 'site-essentials' ); ?>
			</a>
		</p>

		<h3><?php esc_html_e( 'Event Seeding', 'site-essentials' ); ?></h3>
		<p>
			<?php esc_html_e( 'Local service websites often have lower traffic — natural event registration can take months. Seeding registers all events immediately so your analytics account and reporting can be set up right after go-live.', 'site-essentials' ); ?>
			<a href="https://brighterwebsites.com.au/software/analytics/ga4-event-seeding/"
				target="_blank" rel="noopener">
				<?php esc_html_e( 'View guide →', 'site-essentials' ); ?>
			</a>
		</p>

		<h3><?php esc_html_e( 'Events &amp; Dimensions', 'site-essentials' ); ?></h3>

		<h4><?php esc_html_e( 'Custom Dimensions', 'site-essentials' ); ?></h4>
		<p>
			<?php esc_html_e( 'Learn about the custom dimensions sent automatically to Google Analytics, how to register them, and how to set up reporting.', 'site-essentials' ); ?>
			<a href="https://brighterwebsites.com.au/software/analytics/custom-dimensions/"
				target="_blank" rel="noopener">
				<?php esc_html_e( 'View guide →', 'site-essentials' ); ?>
			</a>
		</p>

		<h4><?php esc_html_e( 'Event Registration', 'site-essentials' ); ?></h4>
		<p>
			<?php esc_html_e( 'Learn how to apply SCOS event tracking to elements on the page and find them in Google Analytics reports. Includes help with form tracking and CTA tracking.', 'site-essentials' ); ?>
			<a href="https://brighterwebsites.com.au/software/analytics/selector-attribution/"
				target="_blank" rel="noopener">
				<?php esc_html_e( 'View guide →', 'site-essentials' ); ?>
			</a>
		</p>

		<p>
			<a href="https://brighterwebsites.com.au/software/analytics/"
				target="_blank" rel="noopener" class="scos-btn scos-btn--ghost">
				<?php esc_html_e( 'Full Analytics Module Guide →', 'site-essentials' ); ?>
			</a>
		</p>

	</div>
</div>
