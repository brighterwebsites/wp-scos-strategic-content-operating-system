<?php
/**
 * Support & agency white label (Site Essentials).
 *
 * @package SiteEssentials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SiteEssentials\Core\Admin_UI;

$can_staff = function_exists( 'scos_agency_user_can_manage_agency_setup' )
	&& scos_agency_user_can_manage_agency_setup( wp_get_current_user() );

?>
<div class="wrap site-essentials-wrap">
	<h1><?php esc_html_e( 'Support & agency', 'site-essentials' ); ?></h1>

	<?php if ( ! empty( $_GET['updated'] ) && 'true' === $_GET['updated'] ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'site-essentials' ); ?></p></div>
	<?php endif; ?>

	<h2 class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin_UI::SUPPORT_PAGE_SLUG . '&tab=agency-setup' ) ); ?>"
			class="nav-tab <?php echo 'agency-setup' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Agency setup', 'site-essentials' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin_UI::SUPPORT_PAGE_SLUG . '&tab=support' ) ); ?>"
			class="nav-tab <?php echo 'support' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Support landing', 'site-essentials' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin_UI::SUPPORT_PAGE_SLUG . '&tab=support-settings' ) ); ?>"
			class="nav-tab <?php echo 'support-settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Support settings', 'site-essentials' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin_UI::SUPPORT_PAGE_SLUG . '&tab=access' ) ); ?>"
			class="nav-tab <?php echo 'access' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Access', 'site-essentials' ); ?>
		</a>
	</h2>

	<?php if ( 'agency-setup' === $active_tab ) : ?>
		<?php if ( ! $can_staff ) : ?>
			<p class="description"><?php esc_html_e( 'Agency setup is restricted to staff (see Access tab / domain allowlist). Client admins can use Support landing and Support settings.', 'site-essentials' ); ?></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'site_essentials_support', 'site_essentials_support_nonce' ); ?>
				<input type="hidden" name="action" value="site_essentials_save_support" />
				<input type="hidden" name="se_support_save_tab" value="agency-setup" />

				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="se_agency_name"><?php esc_html_e( 'Agency name', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_name" id="se_agency_name" type="text" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_name', '' ) ); ?>" /></td></tr>
					<tr><th scope="row"><label for="se_agency_contact"><?php esc_html_e( 'Agency contact name', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_contact" id="se_agency_contact" type="text" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_contact', '' ) ); ?>" /></td></tr>
					<tr><th scope="row"><label for="se_agency_url"><?php esc_html_e( 'Agency / support base URL', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_url" id="se_agency_url" type="url" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_url', '' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Used for site credit link base and floating “Support” link (…/support appended when needed).', 'site-essentials' ); ?></p></td></tr>
					<tr><th scope="row"><label for="se_agency_email"><?php esc_html_e( 'Support email', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_email" id="se_agency_email" type="email" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_email', '' ) ); ?>" /></td></tr>
					<tr><th scope="row"><label for="se_agency_phone"><?php esc_html_e( 'Support phone', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_phone" id="se_agency_phone" type="text" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_phone', '' ) ); ?>" /></td></tr>
					<tr><th scope="row"><label for="se_agency_logo_id"><?php esc_html_e( 'Agency logo (attachment ID)', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_logo_id" id="se_agency_logo_id" type="number" min="0" class="small-text" value="<?php echo esc_attr( (string) absint( get_option( 'se_agency_logo_id', 0 ) ) ); ?>" /></td></tr>
					<tr><th scope="row"><label for="se_agency_location"><?php esc_html_e( 'Agency location', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_location" id="se_agency_location" type="text" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_location', '' ) ); ?>" /></td></tr>
					<tr><th colspan="2"><h3><?php esc_html_e( 'Head meta tags', 'site-essentials' ); ?></h3></th></tr>
					<tr><th scope="row"><label for="se_agency_meta_designer"><?php esc_html_e( 'Meta designer', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_meta_designer" id="se_agency_meta_designer" type="text" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_meta_designer', '' ) ); ?>" /></td></tr>
					<tr><th scope="row"><label for="se_agency_meta_web_author"><?php esc_html_e( 'Meta web author', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_meta_web_author" id="se_agency_meta_web_author" type="text" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_meta_web_author', '' ) ); ?>" /></td></tr>
					<tr><th scope="row"><label for="se_agency_meta_generator"><?php esc_html_e( 'Meta generator', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_meta_generator" id="se_agency_meta_generator" type="text" class="large-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_meta_generator', '' ) ); ?>" /></td></tr>
					<tr><th colspan="2"><h3><?php esc_html_e( 'Site credit shortcode', 'site-essentials' ); ?></h3></th></tr>
					<tr><th scope="row"><label for="se_agency_credit_prefix"><?php esc_html_e( 'Credit prefix', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_credit_prefix" id="se_agency_credit_prefix" type="text" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_credit_prefix', '' ) ); ?>" /></td></tr>
					<tr><th scope="row"><label for="se_agency_credit_anchor"><?php esc_html_e( 'Credit anchor text', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_credit_anchor" id="se_agency_credit_anchor" type="text" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_credit_anchor', '' ) ); ?>" /></td></tr>
					<tr><th scope="row"><label for="se_agency_credit_utm"><?php esc_html_e( 'UTM query string', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_credit_utm" id="se_agency_credit_utm" type="text" class="large-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_credit_utm', '' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Optional. If empty, default UTM params are applied. Example: utm_source=site&utm_medium=footer', 'site-essentials' ); ?></p></td></tr>
					<tr><th scope="row"><label for="se_agency_credit_target"><?php esc_html_e( 'Link target', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_credit_target" id="se_agency_credit_target" type="text" class="small-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_credit_target', '' ) ); ?>" /></td></tr>
					<tr><th scope="row"><label for="se_agency_credit_rel"><?php esc_html_e( 'Link rel', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_credit_rel" id="se_agency_credit_rel" type="text" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_credit_rel', '' ) ); ?>" /></td></tr>
					<tr><th scope="row"><label for="se_agency_humans_txt"><?php esc_html_e( 'humans.txt override', 'site-essentials' ); ?></label></th>
						<td><textarea name="se_agency_humans_txt" id="se_agency_humans_txt" rows="12" class="large-text code"><?php echo esc_textarea( (string) get_option( 'se_agency_humans_txt', '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Leave empty to auto-build humans.txt from the fields above.', 'site-essentials' ); ?></p></td></tr>
					<tr><th colspan="2"><h3><?php esc_html_e( 'Login redirects', 'site-essentials' ); ?></h3></th></tr>
					<tr><th scope="row"><label for="se_agency_login_redirect_admin"><?php esc_html_e( 'Administrators', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_login_redirect_admin" id="se_agency_login_redirect_admin" type="url" class="large-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_login_redirect_admin', '' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Must be a wp-admin URL. Empty = default Support hub.', 'site-essentials' ); ?></p></td></tr>
					<tr><th scope="row"><label for="se_agency_login_redirect_editor"><?php esc_html_e( 'Editors (non-admin)', 'site-essentials' ); ?></label></th>
						<td><input name="se_agency_login_redirect_editor" id="se_agency_login_redirect_editor" type="url" class="large-text" value="<?php echo esc_attr( (string) get_option( 'se_agency_login_redirect_editor', '' ) ); ?>" /></td></tr>
				</table>
				<?php submit_button( __( 'Save agency setup', 'site-essentials' ) ); ?>
			</form>
		<?php endif; ?>

	<?php elseif ( 'support' === $active_tab ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'site_essentials_support', 'site_essentials_support_nonce' ); ?>
			<input type="hidden" name="action" value="site_essentials_save_support" />
			<input type="hidden" name="se_support_save_tab" value="support" />
			<p class="description"><?php esc_html_e( 'Optional HTML shown at the top of the Brighter Support hub (Support → Support Info).', 'site-essentials' ); ?></p>
			<?php
			wp_editor(
				(string) get_option( 'se_support_landing_html', '' ),
				'se_support_landing_html',
				[
					'textarea_name' => 'se_support_landing_html',
					'textarea_rows' => 10,
					'media_buttons' => false,
				]
			);
			?>
			<?php submit_button( __( 'Save landing content', 'site-essentials' ) ); ?>
		</form>

	<?php elseif ( 'support-settings' === $active_tab ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'site_essentials_support', 'site_essentials_support_nonce' ); ?>
			<input type="hidden" name="action" value="site_essentials_save_support" />
			<input type="hidden" name="se_support_save_tab" value="support-settings" />
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="se_support_manual_full"><?php esc_html_e( 'Website owners manual (full)', 'site-essentials' ); ?></label></th>
					<td><input name="se_support_manual_full" id="se_support_manual_full" type="url" class="large-text" value="<?php echo esc_attr( function_exists( 'scos_se_support_get' ) ? scos_se_support_get( 'manual_full', '' ) : '' ); ?>" /></td></tr>
				<tr><th scope="row"><label for="se_support_manual_quick"><?php esc_html_e( 'Quick manual', 'site-essentials' ); ?></label></th>
					<td><input name="se_support_manual_quick" id="se_support_manual_quick" type="url" class="large-text" value="<?php echo esc_attr( function_exists( 'scos_se_support_get' ) ? scos_se_support_get( 'manual_quick', '' ) : '' ); ?>" /></td></tr>
				<tr><th scope="row"><label for="se_support_management_portal"><?php esc_html_e( 'Project portal', 'site-essentials' ); ?></label></th>
					<td><input name="se_support_management_portal" id="se_support_management_portal" type="url" class="large-text" value="<?php echo esc_attr( function_exists( 'scos_se_support_get' ) ? scos_se_support_get( 'management_portal', '' ) : '' ); ?>" /></td></tr>
				<tr><th scope="row"><label for="se_support_website_ranking"><?php esc_html_e( 'Website ranking report', 'site-essentials' ); ?></label></th>
					<td><input name="se_support_website_ranking" id="se_support_website_ranking" type="url" class="large-text" value="<?php echo esc_attr( function_exists( 'scos_se_support_get' ) ? scos_se_support_get( 'website_ranking', '' ) : '' ); ?>" /></td></tr>
				<tr><th scope="row"><label for="se_support_map_ranking"><?php esc_html_e( 'Map ranking report', 'site-essentials' ); ?></label></th>
					<td><input name="se_support_map_ranking" id="se_support_map_ranking" type="url" class="large-text" value="<?php echo esc_attr( function_exists( 'scos_se_support_get' ) ? scos_se_support_get( 'map_ranking', '' ) : '' ); ?>" /></td></tr>
				<tr><th colspan="2"><h3><?php esc_html_e( 'AI tool links', 'site-essentials' ); ?></h3></th></tr>
				<tr><th scope="row"><label for="se_support_ai_content"><?php esc_html_e( 'Content writing', 'site-essentials' ); ?></label></th>
					<td><input name="se_support_ai_content" id="se_support_ai_content" type="url" class="large-text" value="<?php echo esc_attr( function_exists( 'scos_se_support_get' ) ? scos_se_support_get( 'ai_content', '' ) : '' ); ?>" /></td></tr>
				<tr><th scope="row"><label for="se_support_ai_research"><?php esc_html_e( 'Research', 'site-essentials' ); ?></label></th>
					<td><input name="se_support_ai_research" id="se_support_ai_research" type="url" class="large-text" value="<?php echo esc_attr( function_exists( 'scos_se_support_get' ) ? scos_se_support_get( 'ai_research', '' ) : '' ); ?>" /></td></tr>
				<tr><th scope="row"><label for="se_support_ai_social"><?php esc_html_e( 'Social media', 'site-essentials' ); ?></label></th>
					<td><input name="se_support_ai_social" id="se_support_ai_social" type="url" class="large-text" value="<?php echo esc_attr( function_exists( 'scos_se_support_get' ) ? scos_se_support_get( 'ai_social', '' ) : '' ); ?>" /></td></tr>
				<tr><th scope="row"><label for="se_support_ai_competitor"><?php esc_html_e( 'Competitor research', 'site-essentials' ); ?></label></th>
					<td><input name="se_support_ai_competitor" id="se_support_ai_competitor" type="url" class="large-text" value="<?php echo esc_attr( function_exists( 'scos_se_support_get' ) ? scos_se_support_get( 'ai_competitor', '' ) : '' ); ?>" /></td></tr>
				<tr><th colspan="2"><h3><?php esc_html_e( 'Head snippets', 'site-essentials' ); ?></h3></th></tr>
				<tr><th scope="row"><label for="se_support_simple_commenter_script"><?php esc_html_e( 'Simple Commenter', 'site-essentials' ); ?></label></th>
					<td><textarea name="se_support_simple_commenter_script" id="se_support_simple_commenter_script" rows="4" class="large-text code"><?php echo esc_textarea( function_exists( 'scos_se_support_get' ) ? scos_se_support_get( 'simple_commenter_script', '' ) : '' ); ?></textarea></td></tr>
				<tr><th scope="row"><label for="se_support_ahrefs_script"><?php esc_html_e( 'Ahrefs analytics', 'site-essentials' ); ?></label></th>
					<td><textarea name="se_support_ahrefs_script" id="se_support_ahrefs_script" rows="4" class="large-text code"><?php echo esc_textarea( function_exists( 'scos_se_support_get' ) ? scos_se_support_get( 'ahrefs_script', '' ) : '' ); ?></textarea></td></tr>
			</table>
			<?php submit_button( __( 'Save support settings', 'site-essentials' ) ); ?>
		</form>

	<?php elseif ( 'access' === $active_tab ) : ?>
		<?php if ( ! $can_staff ) : ?>
			<p class="description"><?php esc_html_e( 'Only staff can change the domain allowlist.', 'site-essentials' ); ?></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'site_essentials_support', 'site_essentials_support_nonce' ); ?>
				<input type="hidden" name="action" value="site_essentials_save_support" />
				<input type="hidden" name="se_support_save_tab" value="access" />
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="se_agency_staff_domains"><?php esc_html_e( 'Staff email domains', 'site-essentials' ); ?></label></th>
						<td><textarea name="se_agency_staff_domains" id="se_agency_staff_domains" rows="3" class="large-text"><?php echo esc_textarea( (string) get_option( 'se_agency_staff_domains', '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Comma-separated domains (no @). Users with these email hosts and manage_options may edit Agency setup and Access. If empty, legacy @brighterwebsites.com.au applies.', 'site-essentials' ); ?></p></td></tr>
				</table>
				<?php submit_button( __( 'Save access rules', 'site-essentials' ) ); ?>
			</form>
		<?php endif; ?>
	<?php endif; ?>
</div>
