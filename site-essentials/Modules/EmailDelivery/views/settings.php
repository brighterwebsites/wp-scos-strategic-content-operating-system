<?php
/**
 * Plugin Settings → Email tab.
 *
 * @package SiteEssentials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enabled       = (bool) get_option( 'se_email_enabled', false );
$api_opt       = get_option( 'se_email_api_key', '' );
$from_address  = get_option( 'se_email_from_address', '' );
$from_name     = get_option( 'se_email_from_name', '' );
$reply_to      = get_option( 'se_email_reply_to', '' );

$api_from_constant = defined( 'SE_EMAIL_API_KEY' ) && is_string( SE_EMAIL_API_KEY ) && SE_EMAIL_API_KEY !== '';
// Never print the bearer token in HTML when supplied via wp-config.
$api_key_input   = $api_from_constant ? '' : ( is_string( $api_opt ) ? $api_opt : '' );
$has_key         = $api_from_constant || ( is_string( $api_opt ) && $api_opt !== '' );

if ( defined( 'SE_EMAIL_FROM_ADDRESS' ) && is_string( SE_EMAIL_FROM_ADDRESS ) && SE_EMAIL_FROM_ADDRESS !== '' ) {
	$from_address = SE_EMAIL_FROM_ADDRESS;
}
if ( defined( 'SE_EMAIL_FROM_NAME' ) && is_string( SE_EMAIL_FROM_NAME ) && SE_EMAIL_FROM_NAME !== '' ) {
	$from_name = SE_EMAIL_FROM_NAME;
}
if ( defined( 'SE_EMAIL_REPLY_TO' ) && is_string( SE_EMAIL_REPLY_TO ) && SE_EMAIL_REPLY_TO !== '' ) {
	$reply_to = SE_EMAIL_REPLY_TO;
}
$connected     = $enabled && $has_key;
$status_color  = $connected ? '#16a34a' : '#9ca3af';
$status_label  = $connected
	? __( 'Connected (transactional email active)', 'site-essentials' )
	: __( 'Not connected', 'site-essentials' );

$recent_logs = \SiteEssentials\Modules\EmailDelivery\Email_Logger::get_recent( 10 );

$form_action = esc_url( admin_url( 'admin-post.php' ) );
?>

<?php
if ( isset( $_GET['scos_email_saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['scos_email_saved'] ) ) ) {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'site-essentials' ) . '</p></div>';
}
?>

<div class="site-essentials-email-settings">
	<div class="card">
		<h2><?php esc_html_e( 'Transactional email (CyberPanel)', 'site-essentials' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'When enabled, outgoing mail from WordPress uses CyberPanel Email Delivery instead of PHP mail / SMTP.', 'site-essentials' ); ?>
		</p>

		<p style="margin: 12px 0;">
			<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?php echo esc_attr( $status_color ); ?>;vertical-align:middle;margin-right:8px;"></span>
			<strong><?php echo esc_html( $status_label ); ?></strong>
		</p>

		<form method="post" action="<?php echo $form_action; ?>">
			<?php wp_nonce_field( 'scos_save_email_settings', 'scos_email_nonce' ); ?>
			<input type="hidden" name="action" value="scos_save_email_settings">

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable transactional email', 'site-essentials' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="se_email_enabled" id="se_email_enabled" value="1" <?php checked( $enabled ); ?>>
								<?php esc_html_e( 'Route wp_mail through CyberPanel Email Delivery', 'site-essentials' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="se_email_api_key"><?php esc_html_e( 'API key', 'site-essentials' ); ?></label>
							<p class="description" style="font-weight:normal;">se_email_api_key</p>
						</th>
						<td>
							<input type="password" name="se_email_api_key" id="se_email_api_key" class="regular-text code"
								value="<?php echo esc_attr( $api_key_input ); ?>"
								autocomplete="new-password" style="max-width:560px;width:100%;"
								<?php echo $api_from_constant ? 'readonly' : ''; ?>>
							<button type="button" class="button" id="se_email_toggle_key"><?php esc_html_e( 'Show', 'site-essentials' ); ?></button>
							<?php if ( $has_key && ! $api_from_constant ) : ?>
								<p class="description" style="color:#16a34a;margin-top:6px;">
									<?php esc_html_e( 'A key is saved. Enter a new value to replace it.', 'site-essentials' ); ?>
								</p>
							<?php endif; ?>
							<?php if ( $api_from_constant ) : ?>
								<p class="description"><?php esc_html_e( 'API key is set via SE_EMAIL_API_KEY in wp-config.php (not stored in the database).', 'site-essentials' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="se_email_from_address"><?php esc_html_e( 'From email', 'site-essentials' ); ?></label>
							<p class="description" style="font-weight:normal;">se_email_from_address</p>
						</th>
						<td>
							<input type="email" name="se_email_from_address" id="se_email_from_address" class="regular-text"
								value="<?php echo esc_attr( is_string( $from_address ) ? $from_address : '' ); ?>"
								style="max-width:560px;width:100%;">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="se_email_from_name"><?php esc_html_e( 'From name', 'site-essentials' ); ?></label>
							<p class="description" style="font-weight:normal;">se_email_from_name</p>
						</th>
						<td>
							<input type="text" name="se_email_from_name" id="se_email_from_name" class="regular-text"
								value="<?php echo esc_attr( is_string( $from_name ) ? $from_name : '' ); ?>"
								style="max-width:560px;width:100%;">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="se_email_reply_to"><?php esc_html_e( 'Reply-To address', 'site-essentials' ); ?></label>
							<p class="description" style="font-weight:normal;">se_email_reply_to</p>
						</th>
						<td>
							<input type="email" name="se_email_reply_to" id="se_email_reply_to" class="regular-text"
								value="<?php echo esc_attr( is_string( $reply_to ) ? $reply_to : '' ); ?>"
								style="max-width:560px;width:100%;">
							<p class="description"><?php esc_html_e( 'Optional. Used when the outgoing mail does not set Reply-To.', 'site-essentials' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'Save changes', 'site-essentials' ) ); ?>
		</form>

		<hr>

		<h3><?php esc_html_e( 'Send test email', 'site-essentials' ); ?></h3>
		<p class="description">
			<?php
			printf(
				/* translators: %s: admin email */
				esc_html__( 'Sends a message to %s.', 'site-essentials' ),
				'<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>'
			);
			?>
		</p>
		<p>
			<button type="button" class="button button-secondary" id="se_email_send_test">
				<?php esc_html_e( 'Send test email', 'site-essentials' ); ?>
			</button>
			<span id="se_email_test_result" style="margin-left:12px;"></span>
		</p>
	</div>

	<?php if ( ! empty( $recent_logs ) ) : ?>
		<div class="card" style="margin-top:20px;">
			<h2><?php esc_html_e( 'Recent delivery log', 'site-essentials' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Last 10 entries (no message bodies stored).', 'site-essentials' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'site-essentials' ); ?></th>
						<th><?php esc_html_e( 'To', 'site-essentials' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'site-essentials' ); ?></th>
						<th><?php esc_html_e( 'Status', 'site-essentials' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_logs as $row ) : ?>
						<tr>
							<td><?php echo esc_html( isset( $row['sent_at'] ) ? (string) $row['sent_at'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $row['to_address'] ) ? (string) $row['to_address'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $row['subject'] ) ? (string) $row['subject'] : '' ); ?></td>
							<td>
								<?php
								$st = isset( $row['status'] ) ? (string) $row['status'] : '';
								echo esc_html( $st );
								if ( $st === 'failed' && ! empty( $row['error_text'] ) ) {
									echo ' — <span class="description">' . esc_html( (string) $row['error_text'] ) . '</span>';
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<script>
(function() {
	var keyInput = document.getElementById('se_email_api_key');
	var toggleBtn = document.getElementById('se_email_toggle_key');
	if (keyInput && toggleBtn) {
		toggleBtn.addEventListener('click', function() {
			if (keyInput.type === 'password') {
				keyInput.type = 'text';
				toggleBtn.textContent = <?php echo wp_json_encode( __( 'Hide', 'site-essentials' ) ); ?>;
			} else {
				keyInput.type = 'password';
				toggleBtn.textContent = <?php echo wp_json_encode( __( 'Show', 'site-essentials' ) ); ?>;
			}
		});
	}
	var testBtn = document.getElementById('se_email_send_test');
	var testOut = document.getElementById('se_email_test_result');
	if (testBtn && testOut && typeof siteEssentials !== 'undefined') {
		testBtn.addEventListener('click', function() {
			testOut.textContent = <?php echo wp_json_encode( __( 'Sending…', 'site-essentials' ) ); ?>;
			var xhr = new XMLHttpRequest();
			xhr.open('POST', siteEssentials.ajaxurl, true);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
			xhr.onload = function() {
				try {
					var r = JSON.parse(xhr.responseText);
					if (r.success && r.data) {
						var msg = r.data.message || '';
						if (r.data.message_id) {
							msg += ' (ID: ' + r.data.message_id + ')';
						}
						testOut.textContent = msg;
						testOut.style.color = '#16a34a';
					} else {
						testOut.textContent = (r.data && r.data.message) ? r.data.message : <?php echo wp_json_encode( __( 'Request failed.', 'site-essentials' ) ); ?>;
						testOut.style.color = '#b91c1c';
					}
				} catch (e) {
					testOut.textContent = <?php echo wp_json_encode( __( 'Invalid response.', 'site-essentials' ) ); ?>;
					testOut.style.color = '#b91c1c';
				}
			};
			xhr.send('action=scos_send_test_email&nonce=' + encodeURIComponent(siteEssentials.nonce));
		});
	}
})();
</script>
