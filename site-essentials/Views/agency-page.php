<?php
/**
 * Agency white label settings page (se_agency_* options).
 *
 * @package SiteEssentials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SiteEssentials\Core\Admin_UI;

$saved = isset( $_GET['updated'] ) && '1' === sanitize_key( wp_unslash( $_GET['updated'] ) );
$page_url = admin_url( 'admin.php' );
?>
<div class="wrap scos">

	<form id="se-agency-form" method="post" action="<?php echo esc_url( $page_url ); ?>">
		<?php wp_nonce_field( 'se_agency_save', 'se_agency_nonce' ); ?>
		<input type="hidden" name="se_agency_save" value="1" />
		<input type="hidden" name="page" value="<?php echo esc_attr( Admin_UI::AGENCY_PAGE_SLUG ); ?>" />
		<input type="hidden" name="se_agency_tab" value="<?php echo esc_attr( $active_tab ); ?>" />

		<header class="scos__header">
			<div>
				<h1 class="scos__title"><?php esc_html_e( 'Agency', 'site-essentials' ); ?></h1>
				<p class="scos__subtitle"><?php esc_html_e( 'Site Essentials › Agency', 'site-essentials' ); ?></p>
			</div>
			<div class="scos__header-actions">
				<?php if ( 'support-settings' !== $active_tab ) : ?>
				<button type="submit" class="scos-btn scos-btn--primary">
					<?php esc_html_e( 'Save changes', 'site-essentials' ); ?>
				</button>
				<?php endif; ?>
			</div>
		</header>

		<?php if ( $saved ) : ?>
		<div class="scos-notice scos-notice--success">
			<p><?php esc_html_e( 'Settings saved.', 'site-essentials' ); ?></p>
		</div>
		<?php endif; ?>

		<nav class="scos__tabs">
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => Admin_UI::AGENCY_PAGE_SLUG, 'tab' => 'agency-settings' ], $page_url ) ); ?>"
			   class="scos__tab <?php echo 'agency-settings' === $active_tab ? 'scos__tab--active' : ''; ?>">
				<?php esc_html_e( 'Agency Settings', 'site-essentials' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => Admin_UI::AGENCY_PAGE_SLUG, 'tab' => 'support-settings' ], $page_url ) ); ?>"
			   class="scos__tab <?php echo 'support-settings' === $active_tab ? 'scos__tab--active' : ''; ?>">
				<?php esc_html_e( 'Support Settings', 'site-essentials' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => Admin_UI::AGENCY_PAGE_SLUG, 'tab' => 'access' ], $page_url ) ); ?>"
			   class="scos__tab <?php echo 'access' === $active_tab ? 'scos__tab--active' : ''; ?>">
				<?php esc_html_e( 'Access', 'site-essentials' ); ?>
			</a>
		</nav>

		<?php if ( 'agency-settings' === $active_tab ) : ?>

			<?php /* ── Agency identity ─────────────────────────────────────────── */ ?>
			<div class="scos-card">
				<div class="scos-card__header">
					<h2 class="scos-card__title"><?php esc_html_e( 'Agency identity', 'site-essentials' ); ?></h2>
					<p class="scos-card__desc"><?php esc_html_e( 'Shown in client dashboards, site credits, and the Support hub.', 'site-essentials' ); ?></p>
				</div>
				<div class="scos-card__body">
					<table class="scos-form">
						<tbody>
							<tr>
								<th>
									<label for="se_agency_name"><?php esc_html_e( 'Agency name', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_name</div>
								</th>
								<td>
									<input type="text" id="se_agency_name" name="se_agency_name" class="scos-input"
										value="<?php echo esc_attr( get_option( 'se_agency_name', '' ) ); ?>" />
								</td>
							</tr>
							<tr>
								<th>
									<label for="se_agency_contact"><?php esc_html_e( 'Agency contact name', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_contact</div>
								</th>
								<td>
									<input type="text" id="se_agency_contact" name="se_agency_contact" class="scos-input"
										value="<?php echo esc_attr( get_option( 'se_agency_contact', '' ) ); ?>" />
								</td>
							</tr>
							<tr>
								<th>
									<label for="se_agency_url"><?php esc_html_e( 'Agency / support base URL', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_url</div>
								</th>
								<td>
									<input type="url" id="se_agency_url" name="se_agency_url" class="scos-input"
										value="<?php echo esc_url( get_option( 'se_agency_url', '' ) ); ?>" />
									<p class="description"><?php esc_html_e( 'Root URL for support portal. Used to resolve relative manual paths and the floating Support link.', 'site-essentials' ); ?></p>
								</td>
							</tr>
							<tr>
								<th>
									<label for="se_agency_email"><?php esc_html_e( 'Support email', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_email</div>
								</th>
								<td>
									<input type="email" id="se_agency_email" name="se_agency_email" class="scos-input"
										value="<?php echo esc_attr( get_option( 'se_agency_email', '' ) ); ?>" />
								</td>
							</tr>
							<tr>
								<th>
									<label for="se_agency_phone"><?php esc_html_e( 'Support phone', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_phone</div>
								</th>
								<td>
									<input type="tel" id="se_agency_phone" name="se_agency_phone" class="scos-input"
										value="<?php echo esc_attr( get_option( 'se_agency_phone', '' ) ); ?>" />
								</td>
							</tr>
							<tr>
								<th>
									<label for="se_agency_logo"><?php esc_html_e( 'Agency logo', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_logo</div>
								</th>
								<td>
									<input type="number" id="se_agency_logo" name="se_agency_logo" class="scos-input"
										value="<?php echo esc_attr( (int) get_option( 'se_agency_logo', 0 ) ); ?>" min="0" />
									<p class="description"><?php esc_html_e( 'Enter the WordPress attachment ID of the logo image.', 'site-essentials' ); ?></p>
								</td>
							</tr>
							<tr>
								<th>
									<label for="se_agency_location"><?php esc_html_e( 'Agency location', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_location</div>
								</th>
								<td>
									<input type="text" id="se_agency_location" name="se_agency_location" class="scos-input"
										value="<?php echo esc_attr( get_option( 'se_agency_location', '' ) ); ?>" />
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<?php /* ── Head meta tags ──────────────────────────────────────────── */ ?>
			<div class="scos-card">
				<div class="scos-card__header">
					<h2 class="scos-card__title"><?php esc_html_e( 'Head meta tags', 'site-essentials' ); ?></h2>
				</div>
				<div class="scos-card__body">
					<table class="scos-form">
						<tbody>
							<tr>
								<th>
									<label for="se_agency_meta_designer"><?php esc_html_e( 'Meta designer', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_meta_designer</div>
								</th>
								<td>
									<input type="text" id="se_agency_meta_designer" name="se_agency_meta_designer" class="scos-input"
										value="<?php echo esc_attr( get_option( 'se_agency_meta_designer', '' ) ); ?>" />
									<p class="description"><?php esc_html_e( 'Output in <meta name="designer">', 'site-essentials' ); ?></p>
								</td>
							</tr>
							<tr>
								<th>
									<label for="se_agency_meta_author"><?php esc_html_e( 'Meta web author', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_meta_author</div>
								</th>
								<td>
									<input type="text" id="se_agency_meta_author" name="se_agency_meta_author" class="scos-input"
										value="<?php echo esc_attr( get_option( 'se_agency_meta_author', '' ) ); ?>" />
									<p class="description"><?php esc_html_e( 'Output in <meta name="author">', 'site-essentials' ); ?></p>
								</td>
							</tr>
							<tr>
								<th>
									<label for="se_agency_generator"><?php esc_html_e( 'Generator tag', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_generator</div>
								</th>
								<td>
									<input type="text" id="se_agency_generator" name="se_agency_generator" class="scos-input"
										value="<?php echo esc_attr( get_option( 'se_agency_generator', '' ) ); ?>" />
									<p class="description"><?php esc_html_e( 'Replaces the WP default generator tag.', 'site-essentials' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<?php /* ── Site credit ───────────────────────────────────────────────── */ ?>
			<div class="scos-card">
				<div class="scos-card__header">
					<h2 class="scos-card__title"><?php esc_html_e( 'Site credit', 'site-essentials' ); ?></h2>
				</div>
				<div class="scos-card__body">
					<table class="scos-form">
						<tbody>
							<tr>
								<th>
									<label for="se_agency_credit_prefix"><?php esc_html_e( 'Credit prefix', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_credit_prefix</div>
								</th>
								<td>
									<input type="text" id="se_agency_credit_prefix" name="se_agency_credit_prefix" class="scos-input"
										value="<?php echo esc_attr( get_option( 'se_agency_credit_prefix', 'Proudly Built by' ) ); ?>" />
								</td>
							</tr>
							<tr>
								<th>
									<label for="se_agency_credit_anchor"><?php esc_html_e( 'Credit anchor text', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_credit_anchor</div>
								</th>
								<td>
									<input type="text" id="se_agency_credit_anchor" name="se_agency_credit_anchor" class="scos-input"
										value="<?php echo esc_attr( get_option( 'se_agency_credit_anchor', '' ) ); ?>" />
									<p class="description"><?php esc_html_e( 'Fallback: se_agency_name', 'site-essentials' ); ?></p>
								</td>
							</tr>
							<tr>
								<th>
									<label for="se_agency_credit_utm"><?php esc_html_e( 'UTM suffix', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_credit_utm</div>
								</th>
								<td>
									<input type="text" id="se_agency_credit_utm" name="se_agency_credit_utm" class="scos-input"
										value="<?php echo esc_attr( get_option( 'se_agency_credit_utm', '' ) ); ?>" />
									<p class="description"><?php esc_html_e( 'Appended to credit URL.', 'site-essentials' ); ?></p>
								</td>
							</tr>
							<tr>
								<th>
									<label for="se_agency_credit_target"><?php esc_html_e( 'Link target', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_credit_target</div>
								</th>
								<td>
									<input type="text" id="se_agency_credit_target" name="se_agency_credit_target" class="scos-input"
										value="<?php echo esc_attr( get_option( 'se_agency_credit_target', '_blank' ) ); ?>" />
								</td>
							</tr>
							<tr>
								<th>
									<label for="se_agency_credit_rel"><?php esc_html_e( 'Link rel', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_credit_rel</div>
								</th>
								<td>
									<input type="text" id="se_agency_credit_rel" name="se_agency_credit_rel" class="scos-input"
										value="<?php echo esc_attr( get_option( 'se_agency_credit_rel', 'noopener designer' ) ); ?>" />
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<?php /* ── Humans.txt ───────────────────────────────────────────────── */ ?>
			<div class="scos-card">
				<div class="scos-card__header">
					<h2 class="scos-card__title"><?php esc_html_e( 'Humans.txt', 'site-essentials' ); ?></h2>
				</div>
				<div class="scos-card__body">
					<table class="scos-form">
						<tbody>
							<tr>
								<th>
									<label for="se_agency_humans_txt_enabled"><?php esc_html_e( 'Enable humans.txt', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_humans_txt_enabled</div>
								</th>
								<td>
									<label class="scos-checkbox-row">
										<input type="checkbox" id="se_agency_humans_txt_enabled" name="se_agency_humans_txt_enabled"
											value="1" <?php checked( get_option( 'se_agency_humans_txt_enabled', '' ), '1' ); ?> />
										<?php esc_html_e( 'Publish /humans.txt', 'site-essentials' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th>
									<label for="se_agency_humans_txt"><?php esc_html_e( 'Humans.txt content', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_humans_txt</div>
								</th>
								<td>
									<textarea id="se_agency_humans_txt" name="se_agency_humans_txt" class="scos-textarea" rows="8"
									><?php echo esc_textarea( get_option( 'se_agency_humans_txt', '' ) ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Published at /humans.txt when enabled.', 'site-essentials' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="scos-card__footer">
					<button type="submit" class="scos-btn scos-btn--primary">
						<?php esc_html_e( 'Save changes', 'site-essentials' ); ?>
					</button>
				</div>
			</div>

		<?php elseif ( 'support-settings' === $active_tab ) : ?>

			<div class="scos-card">
				<div class="scos-card__body">
					<div class="scos-empty">
						<div class="scos-empty__icon">&#x2699;&#xFE0F;</div>
						<p class="scos-empty__title"><?php esc_html_e( 'Support settings', 'site-essentials' ); ?></p>
						<p class="scos-empty__desc"><?php esc_html_e( 'Configuration for the client Support landing page. Coming in the next pass.', 'site-essentials' ); ?></p>
					</div>
				</div>
			</div>

		<?php elseif ( 'access' === $active_tab ) : ?>

			<div class="scos-card">
				<div class="scos-card__header">
					<h2 class="scos-card__title"><?php esc_html_e( 'Login redirects', 'site-essentials' ); ?></h2>
					<p class="scos-card__desc"><?php esc_html_e( 'Where users land after logging in.', 'site-essentials' ); ?></p>
				</div>
				<div class="scos-card__body">
					<table class="scos-form">
						<tbody>
							<tr>
								<th>
									<label for="se_agency_login_redirect_admin"><?php esc_html_e( 'Admin redirect', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_login_redirect_admin</div>
								</th>
								<td>
									<input type="url" id="se_agency_login_redirect_admin" name="se_agency_login_redirect_admin" class="scos-input"
										value="<?php echo esc_url( get_option( 'se_agency_login_redirect_admin', admin_url( 'admin.php?page=site-essentials-support' ) ) ); ?>" />
								</td>
							</tr>
							<tr>
								<th>
									<label for="se_agency_login_redirect_editor"><?php esc_html_e( 'Editor redirect', 'site-essentials' ); ?></label>
									<div class="scos-form__slug">se_agency_login_redirect_editor</div>
								</th>
								<td>
									<input type="url" id="se_agency_login_redirect_editor" name="se_agency_login_redirect_editor" class="scos-input"
										value="<?php echo esc_url( get_option( 'se_agency_login_redirect_editor', admin_url( 'admin.php?page=site-essentials-support' ) ) ); ?>" />
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="scos-card__footer">
					<button type="submit" class="scos-btn scos-btn--primary">
						<?php esc_html_e( 'Save changes', 'site-essentials' ); ?>
					</button>
				</div>
			</div>

		<?php endif; ?>

	</form>

</div>
