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

<div class="scos-analytics-settings" style="max-width:860px">

	<?php if ( isset( $_GET['scos_analytics_saved'] ) ) : ?>
		<div class="notice notice-success inline is-dismissible" style="margin:0 0 20px"><p><?php esc_html_e( 'Settings saved.', 'site-essentials' ); ?></p></div>
	<?php endif; ?>

	<!-- ── GA4 Measurement ID ──────────────────────────────────────────── -->
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
						<?php esc_html_e( 'Your GA4 Measurement ID (e.g. G-ABC123DEF4). Find it in GA4 → Admin → Data Streams → Web Stream Details.', 'site-essentials' ); ?>
						<?php if ( $ga4_id ) : ?>
							<strong style="color:#1a7e3d;margin-left:6px">&#10003; <?php esc_html_e( 'Configured', 'site-essentials' ); ?></strong>
						<?php else : ?>
							<strong style="color:#b45309;margin-left:6px">&#9888; <?php esc_html_e( 'Not set', 'site-essentials' ); ?></strong>
						<?php endif; ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save GA4 Settings', 'site-essentials' ), 'primary', 'submit', false ); ?>
	</form>

	<hr style="margin:28px 0 24px">

	<!-- ── Event Seeding ──────────────────────────────────────────────── -->
	<h3 style="margin-top:0"><?php esc_html_e( 'GA4 Event Seeding', 'site-essentials' ); ?></h3>

	<?php if ( $seeded ) : ?>

		<div class="notice notice-success inline" style="padding:10px 14px">
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
				class="button button-secondary"
				onclick="return confirm('<?php esc_attr_e( 'Reset seed flag so you can re-seed?', 'site-essentials' ); ?>')">
				<?php esc_html_e( 'Reset &amp; Re-Seed', 'site-essentials' ); ?>
			</a>
		</p>

		<!-- Seeded events table -->
		<h4 style="margin-top:20px"><?php esc_html_e( 'Events Registered', 'site-essentials' ); ?></h4>
		<table class="wp-list-table widefat striped" style="max-width:820px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Event Name', 'site-essentials' ); ?></th>
					<th><?php esc_html_e( 'Category', 'site-essentials' ); ?></th>
					<th><?php esc_html_e( 'Purpose', 'site-essentials' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr style="background:#fff4e5"><td><code>click_meeting</code></td><td><?php esc_html_e( 'Meetings', 'site-essentials' ); ?></td><td><strong><?php esc_html_e( '🔥 High-value conversion', 'site-essentials' ); ?></strong></td></tr>
				<tr style="background:#fff4e5"><td><code>generate_lead</code></td><td><?php esc_html_e( 'Forms', 'site-essentials' ); ?></td><td><strong><?php esc_html_e( '🔥 Primary conversion — mark as conversion in GA4 Admin → Events', 'site-essentials' ); ?></strong></td></tr>
				<tr style="background:#fff4e5"><td><code>form_submit</code></td><td><?php esc_html_e( 'Forms', 'site-essentials' ); ?></td><td><strong><?php esc_html_e( '🔥 Primary conversion', 'site-essentials' ); ?></strong></td></tr>
				<tr style="background:#fff9e5"><td><code>click_main_cta</code></td><td><?php esc_html_e( 'Quote', 'site-essentials' ); ?></td><td><?php esc_html_e( '⭐ Conversion intent', 'site-essentials' ); ?></td></tr>
				<tr><td><code>get_lead_magnet</code></td><td><?php esc_html_e( 'Lead Magnet', 'site-essentials' ); ?></td><td><?php esc_html_e( 'Lead generation', 'site-essentials' ); ?></td></tr>
				<tr><td><code>click_phone</code></td><td><?php esc_html_e( 'Contact', 'site-essentials' ); ?></td><td><?php esc_html_e( 'Direct contact', 'site-essentials' ); ?></td></tr>
				<tr><td><code>click_email</code></td><td><?php esc_html_e( 'Contact', 'site-essentials' ); ?></td><td><?php esc_html_e( 'Direct contact', 'site-essentials' ); ?></td></tr>
				<tr><td><code>view_pricing</code></td><td><?php esc_html_e( 'Trust', 'site-essentials' ); ?></td><td><?php esc_html_e( 'Purchase consideration', 'site-essentials' ); ?></td></tr>
				<tr><td><code>subscribe</code></td><td><?php esc_html_e( 'Subscribe', 'site-essentials' ); ?></td><td><?php esc_html_e( 'Lead nurture', 'site-essentials' ); ?></td></tr>
				<tr><td colspan="3" style="text-align:center;font-style:italic;color:#666"><?php esc_html_e( '+ 15 more engagement events', 'site-essentials' ); ?></td></tr>
			</tbody>
		</table>

		<div class="notice notice-info inline" style="margin-top:16px">
			<p>
				<strong><?php esc_html_e( 'Filter seed events from reports:', 'site-essentials' ); ?></strong>
				<?php esc_html_e( 'In GA4 Explorations add a filter:', 'site-essentials' ); ?>
				<code>event_label does not contain "[SEED]"</code>
			</p>
		</div>

		<h4><?php esc_html_e( 'Next Steps', 'site-essentials' ); ?></h4>
		<ol style="line-height:1.9;max-width:580px">
			<li><?php esc_html_e( 'Go to GA4 → Admin → Events', 'site-essentials' ); ?></li>
			<li><?php esc_html_e( 'Events appear within 24 hours — mark high-value ones as conversions', 'site-essentials' ); ?></li>
			<li><?php esc_html_e( 'Set up Attribution Settings in GA4 → Admin → Attribution', 'site-essentials' ); ?></li>
			<li><?php esc_html_e( 'Build conversion funnels in Explorations', 'site-essentials' ); ?></li>
		</ol>

	<?php else : ?>

		<div class="notice notice-warning inline" style="padding:10px 14px">
			<p><strong><?php esc_html_e( 'Events Not Yet Seeded', 'site-essentials' ); ?></strong>
			&mdash; <?php esc_html_e( "GA4 won't show your events until they've fired at least once. Seeding registers them immediately.", 'site-essentials' ); ?></p>
		</div>

		<?php if ( $ga4_id ) : ?>
			<p>
				<a href="<?php echo esc_url( $seed_url ); ?>"
					class="button button-primary"
					target="_blank">
					<?php esc_html_e( 'Seed Events Now', 'site-essentials' ); ?>
				</a>
				<span class="description" style="margin-left:10px">
					<?php esc_html_e( 'Opens your homepage with ?seedEvents=true — keep it open ~10 seconds, then close.', 'site-essentials' ); ?>
				</span>
			</p>
			<p class="description"><?php esc_html_e( 'With low traffic, natural event registration can take months. Seeding registers all events immediately so you can set up conversions and attribution right away.', 'site-essentials' ); ?></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Enter your GA4 Measurement ID above before seeding.', 'site-essentials' ); ?></p>
		<?php endif; ?>

	<?php endif; ?>

	<hr style="margin:28px 0 24px">

	<!-- ── Custom Dimensions ──────────────────────────────────────────── -->
	<h3 style="margin-top:0"><?php esc_html_e( 'Custom Dimensions Sent to GA4', 'site-essentials' ); ?></h3>
	<p class="description" style="margin-bottom:12px">
		<?php esc_html_e( 'Every page view automatically includes SCOS content strategy metadata as GA4 event parameters. Register these as Custom Dimensions in GA4 → Admin → Custom Definitions to use them in reports.', 'site-essentials' ); ?>
	</p>

	<table class="wp-list-table widefat striped" style="max-width:820px">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Parameter Name', 'site-essentials' ); ?></th>
				<th><?php esc_html_e( 'Description', 'site-essentials' ); ?></th>
				<th><?php esc_html_e( 'Example Values', 'site-essentials' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr><td><code>post_type</code></td><td><?php esc_html_e( 'WordPress post type', 'site-essentials' ); ?></td><td>page, post, project</td></tr>
			<tr style="background:#e8f5e9"><td><code>content_cluster</code></td><td><strong><?php esc_html_e( 'Strategic Content Cluster', 'site-essentials' ); ?></strong></td><td>AI-First SEO, Conversion Optimisation</td></tr>
			<tr style="background:#e8f5e9"><td><code>content_topic</code></td><td><strong><?php esc_html_e( 'Primary Topic', 'site-essentials' ); ?></strong></td><td>Content Strategy, Technical SEO</td></tr>
			<tr><td><code>content_intent</code></td><td><?php esc_html_e( 'User search intent', 'site-essentials' ); ?></td><td>informational, commercial, transactional</td></tr>
			<tr><td><code>content_purpose</code></td><td><?php esc_html_e( 'Content role in strategy', 'site-essentials' ); ?></td><td>pillar, service-page, supporting, conversion-hub</td></tr>
			<tr style="background:#e8f5e9"><td><code>content_maturity</code></td><td><strong><?php esc_html_e( 'Content Maturity Level', 'site-essentials' ); ?></strong></td><td>learner, practitioner, expert</td></tr>
			<tr><td><code>pillar_page</code></td><td><?php esc_html_e( 'Parent pillar/service page title', 'site-essentials' ); ?></td><td><?php esc_html_e( 'Title of linked pillar', 'site-essentials' ); ?></td></tr>
			<tr><td><code>service_pathway</code></td><td><?php esc_html_e( 'Service/Product pathway page title', 'site-essentials' ); ?></td><td><?php esc_html_e( 'Title of linked service pathway', 'site-essentials' ); ?></td></tr>
			<tr><td><code>content_plan</code></td><td><?php esc_html_e( 'Content workflow/optimisation status', 'site-essentials' ); ?></td><td>approve, testing, revise, archive</td></tr>
			<tr style="background:#fff3cd"><td><code>lead_tier</code></td><td><?php esc_html_e( 'Lead Quality Tier (form-based)', 'site-essentials' ); ?></td><td>hot, warm, cold</td></tr>
			<tr style="background:#fff3cd"><td><code>lead_type</code></td><td><?php esc_html_e( 'Lead Type (form-based)', 'site-essentials' ); ?></td><td>quote_request, contact_form, newsletter</td></tr>
			<tr style="background:#fff3cd"><td><code>cta_label</code></td><td><?php esc_html_e( 'CTA label before form submit', 'site-essentials' ); ?></td><td><?php esc_html_e( 'Text of CTA clicked', 'site-essentials' ); ?></td></tr>
			<tr style="background:#fff3cd"><td><code>cta_location</code></td><td><?php esc_html_e( 'CTA location section', 'site-essentials' ); ?></td><td>header, footer, atf, mid_cta</td></tr>
			<tr style="background:#fff3cd"><td><code>cta_type</code></td><td><?php esc_html_e( 'CTA type', 'site-essentials' ); ?></td><td>main, micro, assist</td></tr>
		</tbody>
	</table>

	<p class="description" style="margin-top:10px">
		<?php esc_html_e( 'Green rows = SCOS content strategy dimensions. Yellow rows = CTA/form context dimensions (only sent with form_submit and generate_lead events).', 'site-essentials' ); ?>
	</p>

	<hr style="margin:28px 0 24px">

	<!-- ── How It Works ────────────────────────────────────────────────── -->
	<h3 style="margin-top:0"><?php esc_html_e( 'How It Works', 'site-essentials' ); ?></h3>

	<p style="max-width:640px">
		<?php esc_html_e( 'The SCOS Content Architecture Record (CAR) is injected into every page head as window.scosCAR. The GA4 tracking script reads this and sends content strategy metadata with every page view event — cluster, topic, intent, purpose, maturity, pillar, and service pathway. This lets you analyse performance by strategy, not just traffic.', 'site-essentials' ); ?>
	</p>

	<ol style="line-height:1.9;max-width:560px">
		<li><?php esc_html_e( 'Add your GA4 Measurement ID above and save.', 'site-essentials' ); ?></li>
		<li>
			<?php esc_html_e( 'Register custom dimensions in GA4 → Admin → Custom Definitions (Event scope):', 'site-essentials' ); ?>
			<br>
			<code>content_cluster</code>, <code>content_topic</code>, <code>content_maturity</code>, <code>content_intent</code>, <code>content_purpose</code>
		</li>
		<li><?php esc_html_e( 'Seed events so GA4 registers them immediately (important for low-traffic sites).', 'site-essentials' ); ?></li>
		<li><?php esc_html_e( 'Mark high-value events as conversions in GA4 → Admin → Events.', 'site-essentials' ); ?></li>
		<li><?php esc_html_e( 'Build funnels and attribution reports in GA4 Explorations.', 'site-essentials' ); ?></li>
	</ol>

	<p>
		<a href="https://brighterwebsites.com.au/software/analytics-module/" target="_blank" rel="noopener">
			<?php esc_html_e( 'Full Analytics Module documentation →', 'site-essentials' ); ?>
		</a>
	</p>

</div>
