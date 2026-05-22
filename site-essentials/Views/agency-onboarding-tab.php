<?php
/**
 * Agency → Onboarding tab.
 *
 * Mounted from Views/agency-page.php — sits inside the same <form> as the other
 * agency tabs but POSTs a distinct nonce (se_onboarding_save) so the handler
 * can route accordingly. The "Send / Test / Preview" buttons all submit the
 * same form with a hidden se_onboarding_action value.
 *
 * v1.0 | 2026-05-22
 *
 * @package SiteEssentials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SiteEssentials\Core\Admin_UI;
use SiteEssentials\Modules\ClientOnboarding\Onboarding_Email_Sender;

$subject_template = Onboarding_Email_Sender::get_subject_template();
$body_template    = Onboarding_Email_Sender::get_body_template();
$expiry_days      = (int) get_option(
	Onboarding_Email_Sender::OPT_EXPIRY_DAYS,
	Onboarding_Email_Sender::DEFAULT_EXPIRY_DAYS
);
if ( $expiry_days < 1 ) {
	$expiry_days = Onboarding_Email_Sender::DEFAULT_EXPIRY_DAYS;
}

$eligible_users = Onboarding_Email_Sender::eligible_users();

// Result notices passed via query string from the handler
$result    = isset( $_GET['se_onboarding_result'] ) ? sanitize_key( wp_unslash( $_GET['se_onboarding_result'] ) ) : '';
$result_to = isset( $_GET['se_onboarding_to'] ) ? sanitize_text_field( wp_unslash( $_GET['se_onboarding_to'] ) ) : '';
$result_msg = isset( $_GET['se_onboarding_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['se_onboarding_msg'] ) ) : '';

$show_preview = ( 'preview' === $result );
$preview_html = '';
if ( $show_preview ) {
	$sender       = new Onboarding_Email_Sender();
	$preview_uid  = isset( $_GET['se_onboarding_uid'] ) ? absint( $_GET['se_onboarding_uid'] ) : 0;
	$preview_html = $sender->preview_html( $preview_uid ?: null );
}
?>

<?php /* ── Result notices ─────────────────────────────────────────── */ ?>
<?php if ( 'saved' === $result ) : ?>
	<div class="scos-notice scos-notice--success">
		<p><?php esc_html_e( 'Onboarding template saved.', 'site-essentials' ); ?></p>
	</div>
<?php elseif ( 'sent_test' === $result ) : ?>
	<div class="scos-notice scos-notice--success">
		<p>
			<?php
			/* translators: %s: email address */
			printf( esc_html__( 'Test email sent to %s.', 'site-essentials' ), '<strong>' . esc_html( $result_to ) . '</strong>' );
			?>
		</p>
	</div>
<?php elseif ( 'sent_live' === $result ) : ?>
	<div class="scos-notice scos-notice--success">
		<p>
			<?php
			/* translators: %s: email address */
			printf( esc_html__( 'Onboarding email sent to %s.', 'site-essentials' ), '<strong>' . esc_html( $result_to ) . '</strong>' );
			?>
		</p>
	</div>
<?php elseif ( 'error' === $result ) : ?>
	<div class="scos-notice scos-notice--danger">
		<p>
			<strong><?php esc_html_e( 'Send failed:', 'site-essentials' ); ?></strong>
			<?php echo esc_html( $result_msg ?: __( 'Unknown error.', 'site-essentials' ) ); ?>
		</p>
	</div>
<?php endif; ?>

<?php /* ── Card 1 — Send onboarding email ─────────────────────── */ ?>
<div class="scos-card">
	<div class="scos-card__header">
		<h2 class="scos-card__title"><?php esc_html_e( 'Send onboarding email', 'site-essentials' ); ?></h2>
		<p class="scos-card__desc">
			<?php esc_html_e( 'Pick a user, preview or test first, then send. The email contains a secure "Set your password" link generated fresh on each send.', 'site-essentials' ); ?>
		</p>
	</div>
	<div class="scos-card__body">
		<table class="scos-form">
			<tbody>
				<tr>
					<th>
						<label for="se_onboarding_user_id"><?php esc_html_e( 'Send to user', 'site-essentials' ); ?></label>
						<div class="scos-form__slug">se_onboarding_user_id</div>
					</th>
					<td>
						<?php if ( empty( $eligible_users ) ) : ?>
							<p class="description">
								<?php esc_html_e( 'No eligible users found. Administrators, Editors, and Shop Managers can receive onboarding.', 'site-essentials' ); ?>
							</p>
						<?php else : ?>
							<select id="se_onboarding_user_id" name="se_onboarding_user_id" class="scos-select">
								<option value=""><?php esc_html_e( '— Select a user —', 'site-essentials' ); ?></option>
								<?php foreach ( $eligible_users as $u ) : ?>
									<option value="<?php echo esc_attr( (int) $u->ID ); ?>">
										<?php
										echo esc_html(
											sprintf(
												'%s · %s · (%s)',
												$u->display_name,
												$u->user_email,
												$u->user_login
											)
										);
										?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Only Administrators, Editors, and Shop Managers are listed.', 'site-essentials' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<div style="display:flex;gap:var(--scos-s-3);flex-wrap:wrap;margin-top:var(--scos-s-4);">
			<button type="submit" name="se_onboarding_action" value="preview" class="scos-btn">
				<?php esc_html_e( 'Preview in browser', 'site-essentials' ); ?>
			</button>
			<button type="submit" name="se_onboarding_action" value="send_test" class="scos-btn">
				<?php
				printf(
					/* translators: %s: current user's email */
					esc_html__( 'Send test to me (%s)', 'site-essentials' ),
					esc_html( wp_get_current_user()->user_email )
				);
				?>
			</button>
			<button type="submit" name="se_onboarding_action" value="send_live" class="scos-btn scos-btn--primary"
				onclick="return confirm('<?php echo esc_js( __( 'Send the onboarding email to the selected user now? They will receive it immediately.', 'site-essentials' ) ); ?>');">
				<?php esc_html_e( 'Send to selected user', 'site-essentials' ); ?>
			</button>
		</div>
	</div>
</div>

<?php /* ── Card 2 — Email content ──────────────────────────────── */ ?>
<div class="scos-card">
	<div class="scos-card__header">
		<h2 class="scos-card__title"><?php esc_html_e( 'Email content', 'site-essentials' ); ?></h2>
		<p class="scos-card__desc">
			<?php esc_html_e( 'Edit the subject and HTML body. Use the tokens listed below to personalise the email.', 'site-essentials' ); ?>
		</p>
	</div>
	<div class="scos-card__body">
		<table class="scos-form">
			<tbody>
				<tr>
					<th>
						<label for="se_onboarding_subject_template"><?php esc_html_e( 'Subject', 'site-essentials' ); ?></label>
						<div class="scos-form__slug">se_onboarding_subject_template</div>
					</th>
					<td>
						<input type="text" id="se_onboarding_subject_template" name="se_onboarding_subject_template"
							class="scos-input"
							value="<?php echo esc_attr( $subject_template ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Tokens supported. Example default:', 'site-essentials' ); ?>
							<code>Welcome to {site_name} — your website access is ready</code>
						</p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="se_onboarding_password_link_expiry_days"><?php esc_html_e( 'Password link expiry (days)', 'site-essentials' ); ?></label>
						<div class="scos-form__slug">se_onboarding_password_link_expiry_days</div>
					</th>
					<td>
						<input type="number" id="se_onboarding_password_link_expiry_days"
							name="se_onboarding_password_link_expiry_days"
							class="scos-input" style="max-width:120px;"
							min="1" max="30" step="1"
							value="<?php echo esc_attr( (string) $expiry_days ); ?>" />
						<p class="description">
							<?php esc_html_e( 'How long the "Set your password" link remains valid. Default 7 days. Applies to ALL password reset links on this site, not just onboarding.', 'site-essentials' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="se_onboarding_html_template"><?php esc_html_e( 'HTML body', 'site-essentials' ); ?></label>
						<div class="scos-form__slug">se_onboarding_html_template</div>
						<p class="scos-metabox__hint">
							<?php esc_html_e( 'Inline CSS only. Leave empty to use the default template shipped with the plugin.', 'site-essentials' ); ?>
						</p>
					</th>
					<td>
						<textarea id="se_onboarding_html_template" name="se_onboarding_html_template"
							class="scos-textarea scos-input--mono" rows="22"
							style="font-family:var(--scos-font-mono,monospace);font-size:12px;line-height:1.5;"><?php echo esc_textarea( $body_template ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'The default template is loaded here for you to edit. To revert to the default, clear this field and save.', 'site-essentials' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	<div class="scos-card__footer">
		<button type="submit" name="se_onboarding_action" value="save" class="scos-btn scos-btn--primary">
			<?php esc_html_e( 'Save changes', 'site-essentials' ); ?>
		</button>
	</div>
</div>

<?php /* ── Card 3 — Token reference ────────────────────────────── */ ?>
<div class="scos-card">
	<div class="scos-card__header">
		<h2 class="scos-card__title"><?php esc_html_e( 'Available tokens', 'site-essentials' ); ?></h2>
		<p class="scos-card__desc">
			<?php esc_html_e( 'Drop these into the subject or HTML body — they are replaced at send time for each recipient.', 'site-essentials' ); ?>
		</p>
	</div>
	<div class="scos-card__body">
		<table class="scos-form">
			<tbody>
				<tr><th><code>{site_name}</code></th><td><?php esc_html_e( 'The site name (Settings → General).', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{site_url}</code></th><td><?php esc_html_e( 'The site home URL.', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{site_logo_url}</code></th><td><?php esc_html_e( 'URL of the site custom logo (if set in the theme).', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{user_first_name}</code></th><td><?php esc_html_e( 'Recipient first name (falls back to display name).', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{user_login}</code></th><td><?php esc_html_e( 'Their WordPress username.', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{user_email}</code></th><td><?php esc_html_e( 'Their email address.', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{user_display_name}</code></th><td><?php esc_html_e( 'Their full display name.', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{password_set_link}</code></th><td><?php esc_html_e( 'Secure one-time link to set their password. Generated fresh on each send.', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{password_link_expiry_days}</code></th><td><?php esc_html_e( 'How long the password link is valid for (configured above).', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{support_page_url}</code></th><td><?php esc_html_e( 'Their Site Essentials Support hub URL.', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{login_url}</code></th><td><?php esc_html_e( 'The WordPress login URL.', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{agency_name}</code></th><td><?php esc_html_e( 'Your agency name (from Agency Settings).', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{agency_email}</code></th><td><?php esc_html_e( 'Your agency support email.', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{agency_phone}</code></th><td><?php esc_html_e( 'Your agency support phone.', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{agency_url}</code></th><td><?php esc_html_e( 'Your agency / support base URL.', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{agency_logo_url}</code></th><td><?php esc_html_e( 'Your agency logo URL (from Agency Settings).', 'site-essentials' ); ?></td></tr>
				<tr><th><code>{current_year}</code></th><td><?php esc_html_e( 'Current year (e.g. for copyright).', 'site-essentials' ); ?></td></tr>
			</tbody>
		</table>
	</div>
</div>

<?php /* ── Preview pane (shown only after a Preview submit) ──── */ ?>
<?php if ( $show_preview && $preview_html !== '' ) : ?>
<div class="scos-card">
	<div class="scos-card__header">
		<h2 class="scos-card__title"><?php esc_html_e( 'Preview', 'site-essentials' ); ?></h2>
		<p class="scos-card__desc">
			<?php esc_html_e( 'Rendered with token substitutions for the selected user (or you, if no user was selected). No email was sent.', 'site-essentials' ); ?>
		</p>
	</div>
	<div class="scos-card__body" style="padding:0;">
		<iframe
			srcdoc="<?php echo esc_attr( $preview_html ); ?>"
			style="display:block;width:100%;min-height:720px;border:0;background:#f4f4f7;"
			title="<?php esc_attr_e( 'Onboarding email preview', 'site-essentials' ); ?>"></iframe>
	</div>
</div>
<?php endif; ?>
