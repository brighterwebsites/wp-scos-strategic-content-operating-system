<?php
/**
 * Business Info Module — settings view.
 * SCOS-BIZ-PASS1 — full SCOS design system rebuild.
 * Single-page accordion layout; no tabs. Shortcodes Reference removed.
 * All option keys, settings group, and save logic are untouched.
 */
defined( 'ABSPATH' ) || exit;

wp_enqueue_media();

// Saved notice
if ( isset( $_GET['settings-updated'] ) ) {
	echo '<div class="scos-notice scos-notice--success" style="margin-bottom:var(--scos-s-4)"><p>' . esc_html__( 'Business information saved.', 'site-essentials' ) . '</p></div>';
}
?>

<!-- Page chrome -->
<header class="scos__header">
	<div>
		<h1 class="scos__title"><?php esc_html_e( 'Business Information', 'site-essentials' ); ?></h1>
		<p class="scos__subtitle">Site Essentials &rsaquo; Business Information</p>
	</div>
	<div class="scos__header-actions">
		<button type="submit" form="scos-biz-form" class="scos-btn scos-btn--primary">
			<?php esc_html_e( 'Save changes', 'site-essentials' ); ?>
		</button>
	</div>
</header>

<!-- Single form wrapping all accordion sections -->
<form id="scos-biz-form" method="post" action="options.php">
	<?php settings_fields( 'scos_biz_settings_group' ); ?>
	<?php wp_nonce_field( 'scos_biz_save', 'scos_biz_nonce' ); ?>

	<div class="scos-accordion">

		<!-- Section 1: Entity Identity -->
		<div class="scos-accordion__item" id="scos-biz-section-entity">
			<button type="button" class="scos-accordion__trigger" aria-expanded="false" aria-controls="scos-biz-body-entity">
				<span class="scos-accordion__label">
					<span class="scos-accordion__title"><?php esc_html_e( 'Entity Identity', 'site-essentials' ); ?></span>
					<span class="scos-accordion__hint"><?php esc_html_e( 'Organisation type, name, category, description, ABN, founding date', 'site-essentials' ); ?></span>
				</span>
				<svg class="scos-accordion__chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
			</button>
			<div class="scos-accordion__body" id="scos-biz-body-entity">
				<table class="scos-form">
					<tbody>
						<tr>
							<th>
								<label for="scos_biz_organisation_type"><?php esc_html_e( 'Organisation Type', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_organisation_type</div>
							</th>
							<td>
								<select id="scos_biz_organisation_type" name="scos_biz_organisation_type" class="scos-select">
									<?php
									$org_types = [ 'Local Business', 'Organization', 'Person' ];
									$saved_org  = get_option( 'scos_biz_organisation_type', '' );
									foreach ( $org_types as $opt ) {
										printf( '<option value="%s"%s>%s</option>', esc_attr( $opt ), selected( $saved_org, $opt, false ), esc_html( $opt ) );
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_business_name"><?php esc_html_e( 'Business Name', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_business_name</div>
							</th>
							<td>
								<input id="scos_biz_business_name" name="scos_biz_business_name" type="text" class="scos-input"
									value="<?php echo esc_attr( get_option( 'scos_biz_business_name', '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_business_category"><?php esc_html_e( 'Business Category', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_business_category</div>
							</th>
							<td>
								<input id="scos_biz_business_category" name="scos_biz_business_category" type="text" class="scos-input"
									value="<?php echo esc_attr( get_option( 'scos_biz_business_category', '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_service_description"><?php esc_html_e( 'Service Description', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_service_description</div>
							</th>
							<td>
								<textarea id="scos_biz_service_description" name="scos_biz_service_description" rows="4" class="scos-textarea"><?php echo esc_textarea( get_option( 'scos_biz_service_description', '' ) ); ?></textarea>
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_abn"><?php esc_html_e( 'ABN', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_abn</div>
							</th>
							<td>
								<input id="scos_biz_abn" name="scos_biz_abn" type="text" class="scos-input"
									value="<?php echo esc_attr( get_option( 'scos_biz_abn', '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_founding_date"><?php esc_html_e( 'Founding Date', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_founding_date</div>
							</th>
							<td>
								<input id="scos_biz_founding_date" name="scos_biz_founding_date" type="text" class="scos-input"
									placeholder="YYYY-MM-DD"
									value="<?php echo esc_attr( get_option( 'scos_biz_founding_date', '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_founder_contact_name"><?php esc_html_e( 'Founder / Contact Name', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_founder_contact_name</div>
							</th>
							<td>
								<input id="scos_biz_founder_contact_name" name="scos_biz_founder_contact_name" type="text" class="scos-input"
									value="<?php echo esc_attr( get_option( 'scos_biz_founder_contact_name', '' ) ); ?>">
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div><!-- /entity -->

		<!-- Section 2: Contact Information -->
		<div class="scos-accordion__item" id="scos-biz-section-contact">
			<button type="button" class="scos-accordion__trigger" aria-expanded="false" aria-controls="scos-biz-body-contact">
				<span class="scos-accordion__label">
					<span class="scos-accordion__title"><?php esc_html_e( 'Contact Information', 'site-essentials' ); ?></span>
					<span class="scos-accordion__hint"><?php esc_html_e( 'Phone, email, address, coordinates, place ID', 'site-essentials' ); ?></span>
				</span>
				<svg class="scos-accordion__chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
			</button>
			<div class="scos-accordion__body" id="scos-biz-body-contact">
				<table class="scos-form">
					<tbody>
						<tr>
							<th>
								<label for="scos_biz_phone_number"><?php esc_html_e( 'Phone Number', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_phone_number</div>
							</th>
							<td>
								<input id="scos_biz_phone_number" name="scos_biz_phone_number" type="text" class="scos-input"
									value="<?php echo esc_attr( get_option( 'scos_biz_phone_number', '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_email"><?php esc_html_e( 'Email', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_email</div>
							</th>
							<td>
								<input id="scos_biz_email" name="scos_biz_email" type="email" class="scos-input"
									value="<?php echo esc_attr( get_option( 'scos_biz_email', '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_contact_name"><?php esc_html_e( 'Contact Name', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_contact_name</div>
							</th>
							<td>
								<input id="scos_biz_contact_name" name="scos_biz_contact_name" type="text" class="scos-input"
									value="<?php echo esc_attr( get_option( 'scos_biz_contact_name', '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_contact_type"><?php esc_html_e( 'Contact Type', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_contact_type</div>
							</th>
							<td>
								<select id="scos_biz_contact_type" name="scos_biz_contact_type" class="scos-select">
									<?php
									$contact_types = [
										''                        => __( 'Select', 'site-essentials' ),
										'customer support'        => __( 'Customer Support', 'site-essentials' ),
										'technical support'       => __( 'Technical Support', 'site-essentials' ),
										'billing support'         => __( 'Billing Support', 'site-essentials' ),
										'sales'                   => __( 'Sales', 'site-essentials' ),
										'emergency'               => __( 'Emergency', 'site-essentials' ),
									];
									$saved_ct = get_option( 'scos_biz_contact_type', '' );
									foreach ( $contact_types as $val => $label ) {
										printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $saved_ct, $val, false ), esc_html( $label ) );
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_contact_option"><?php esc_html_e( 'Contact Option', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_contact_option</div>
							</th>
							<td>
								<select id="scos_biz_contact_option" name="scos_biz_contact_option" class="scos-select">
									<?php
									$contact_options = [
										''                           => __( 'None', 'site-essentials' ),
										'TollFree'                   => __( 'Toll Free', 'site-essentials' ),
										'HearingImpairedSupported'   => __( 'Hearing Impaired Supported', 'site-essentials' ),
									];
									$saved_co = get_option( 'scos_biz_contact_option', '' );
									foreach ( $contact_options as $val => $label ) {
										printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $saved_co, $val, false ), esc_html( $label ) );
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_address"><?php esc_html_e( 'Street Address', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_address</div>
							</th>
							<td>
								<input id="scos_biz_address" name="scos_biz_address" type="text" class="scos-input"
									value="<?php echo esc_attr( get_option( 'scos_biz_address', '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_city"><?php esc_html_e( 'City', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_city</div>
							</th>
							<td>
								<input id="scos_biz_city" name="scos_biz_city" type="text" class="scos-input"
									value="<?php echo esc_attr( get_option( 'scos_biz_city', '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_state"><?php esc_html_e( 'State', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_state</div>
							</th>
							<td>
								<input id="scos_biz_state" name="scos_biz_state" type="text" class="scos-input"
									value="<?php echo esc_attr( get_option( 'scos_biz_state', '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_postcode"><?php esc_html_e( 'Postcode', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_postcode</div>
							</th>
							<td>
								<input id="scos_biz_postcode" name="scos_biz_postcode" type="text" class="scos-input"
									value="<?php echo esc_attr( get_option( 'scos_biz_postcode', '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_country"><?php esc_html_e( 'Country', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_country</div>
							</th>
							<td>
								<input id="scos_biz_country" name="scos_biz_country" type="text" class="scos-input"
									value="<?php echo esc_attr( get_option( 'scos_biz_country', '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_country_code"><?php esc_html_e( 'Country Code', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_country_code</div>
							</th>
							<td>
								<input id="scos_biz_country_code" name="scos_biz_country_code" type="text" class="scos-input"
									placeholder="AU"
									value="<?php echo esc_attr( get_option( 'scos_biz_country_code', '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_lat"><?php esc_html_e( 'Latitude', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_lat</div>
							</th>
							<td>
								<input id="scos_biz_lat" name="scos_biz_lat" type="text" class="scos-input scos-input--mono"
									placeholder="-33.865143"
									value="<?php echo esc_attr( get_option( 'scos_biz_lat', '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_long"><?php esc_html_e( 'Longitude', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_long</div>
							</th>
							<td>
								<input id="scos_biz_long" name="scos_biz_long" type="text" class="scos-input scos-input--mono"
									placeholder="151.209900"
									value="<?php echo esc_attr( get_option( 'scos_biz_long', '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_place_id"><?php esc_html_e( 'Google Place ID', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_place_id</div>
							</th>
							<td>
								<input id="scos_biz_place_id" name="scos_biz_place_id" type="text" class="scos-input scos-input--mono"
									value="<?php echo esc_attr( get_option( 'scos_biz_place_id', '' ) ); ?>">
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div><!-- /contact -->

		<!-- Section 3: Operational Details -->
		<div class="scos-accordion__item" id="scos-biz-section-ops">
			<button type="button" class="scos-accordion__trigger" aria-expanded="false" aria-controls="scos-biz-body-ops">
				<span class="scos-accordion__label">
					<span class="scos-accordion__title"><?php esc_html_e( 'Operational Details', 'site-essentials' ); ?></span>
					<span class="scos-accordion__hint"><?php esc_html_e( 'Hours, price tier, mobility', 'site-essentials' ); ?></span>
				</span>
				<svg class="scos-accordion__chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
			</button>
			<div class="scos-accordion__body" id="scos-biz-body-ops">
				<table class="scos-form">
					<tbody>
						<tr>
							<th>
								<label for="scos_biz_business_hours"><?php esc_html_e( 'Business Hours', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_business_hours</div>
							</th>
							<td>
								<textarea id="scos_biz_business_hours" name="scos_biz_business_hours" rows="5" class="scos-textarea"
									placeholder="Mo-Fr 09:00-17:00"><?php echo esc_textarea( get_option( 'scos_biz_business_hours', '' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One entry per line. Uses schema.org openingHours format, e.g. Mo-Fr 09:00-17:00', 'site-essentials' ); ?></p>
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_price_tier"><?php esc_html_e( 'Price Tier', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_price_tier</div>
							</th>
							<td>
								<select id="scos_biz_price_tier" name="scos_biz_price_tier" class="scos-select">
									<?php
									$price_tiers = [ '$', '$$', '$$$', '$$$$' ];
									$saved_pt    = get_option( 'scos_biz_price_tier', '' );
									foreach ( $price_tiers as $tier ) {
										printf( '<option value="%s"%s>%s</option>', esc_attr( $tier ), selected( $saved_pt, $tier, false ), esc_html( $tier ) );
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos_biz_provider_mobility"><?php esc_html_e( 'Provider Mobility', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_provider_mobility</div>
							</th>
							<td>
								<select id="scos_biz_provider_mobility" name="scos_biz_provider_mobility" class="scos-select">
									<?php
									$mobility_opts = [
										'static'  => __( 'Static', 'site-essentials' ),
										'dynamic' => __( 'Dynamic', 'site-essentials' ),
									];
									$saved_mob = get_option( 'scos_biz_provider_mobility', 'static' );
									foreach ( $mobility_opts as $val => $label ) {
										printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $saved_mob, $val, false ), esc_html( $label ) );
									}
									?>
								</select>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div><!-- /ops -->

		<!-- Section 4: Social Media & Web Presence -->
		<div class="scos-accordion__item" id="scos-biz-section-social">
			<button type="button" class="scos-accordion__trigger" aria-expanded="false" aria-controls="scos-biz-body-social">
				<span class="scos-accordion__label">
					<span class="scos-accordion__title"><?php esc_html_e( 'Social Media &amp; Web Presence', 'site-essentials' ); ?></span>
					<span class="scos-accordion__hint"><?php esc_html_e( 'Profile URLs, sharing links, additional accounts', 'site-essentials' ); ?></span>
				</span>
				<svg class="scos-accordion__chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
			</button>
			<div class="scos-accordion__body" id="scos-biz-body-social">
				<table class="scos-form">
					<tbody>
						<?php
						$social_fields = [
							'scos_biz_social_link_facebook'  => __( 'Facebook URL', 'site-essentials' ),
							'scos_biz_social_link_twitter'   => __( 'Twitter / X URL', 'site-essentials' ),
							'scos_biz_social_link_instagram' => __( 'Instagram URL', 'site-essentials' ),
							'scos_biz_social_link_youtube'   => __( 'YouTube URL', 'site-essentials' ),
							'scos_biz_social_link_linkedin'  => __( 'LinkedIn URL', 'site-essentials' ),
							'scos_biz_social_link_pinterest' => __( 'Pinterest URL', 'site-essentials' ),
							'scos_biz_google_maps_share'     => __( 'Google Maps Share Link', 'site-essentials' ),
							'scos_biz_knowledge_panel_share' => __( 'Knowledge Panel Share Link', 'site-essentials' ),
						];
						foreach ( $social_fields as $key => $label ) :
						?>
						<tr>
							<th>
								<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
								<div class="scos-form__slug"><?php echo esc_html( $key ); ?></div>
							</th>
							<td>
								<input id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
									type="url" class="scos-input"
									value="<?php echo esc_attr( get_option( $key, '' ) ); ?>">
							</td>
						</tr>
						<?php endforeach; ?>
						<tr>
							<th>
								<label for="scos_biz_additional_account_urls"><?php esc_html_e( 'Additional Account URLs', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_additional_account_urls</div>
							</th>
							<td>
								<textarea id="scos_biz_additional_account_urls" name="scos_biz_additional_account_urls" rows="4" class="scos-textarea"><?php echo esc_textarea( get_option( 'scos_biz_additional_account_urls', '' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One URL per line.', 'site-essentials' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div><!-- /social -->

		<!-- Section 5: Key Media -->
		<div class="scos-accordion__item" id="scos-biz-section-media">
			<button type="button" class="scos-accordion__trigger" aria-expanded="false" aria-controls="scos-biz-body-media">
				<span class="scos-accordion__label">
					<span class="scos-accordion__title"><?php esc_html_e( 'Key Media', 'site-essentials' ); ?></span>
					<span class="scos-accordion__hint"><?php esc_html_e( 'Logos, images, favicon, theme colour', 'site-essentials' ); ?></span>
				</span>
				<svg class="scos-accordion__chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
			</button>
			<div class="scos-accordion__body" id="scos-biz-body-media">
				<table class="scos-form">
					<tbody>
						<?php
						$media_fields = [
							'scos_biz_site_icon'       => __( 'Site Icon (Favicon)', 'site-essentials' ),
							'scos_biz_business_logo'   => __( 'Business Logo', 'site-essentials' ),
							'scos_biz_publisher_logo'  => __( 'Publisher Logo', 'site-essentials' ),
							'scos_biz_business_image'  => __( 'Business Image', 'site-essentials' ),
						];
						foreach ( $media_fields as $key => $label ) :
							$current_url = get_option( $key, '' );
						?>
						<tr>
							<th>
								<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
								<div class="scos-form__slug"><?php echo esc_html( $key ); ?></div>
							</th>
							<td>
								<div class="scos-media-field" style="display:flex;gap:var(--scos-s-2);align-items:center;flex-wrap:wrap;">
									<input id="<?php echo esc_attr( $key ); ?>"
										name="<?php echo esc_attr( $key ); ?>"
										type="url"
										class="scos-input"
										value="<?php echo esc_attr( $current_url ); ?>">
									<button type="button"
										class="scos-btn scos-btn--ghost scos-media-select"
										data-target="<?php echo esc_attr( $key ); ?>">
										<?php esc_html_e( 'Select Image', 'site-essentials' ); ?>
									</button>
								</div>
								<?php if ( $current_url ) : ?>
								<div id="<?php echo esc_attr( $key ); ?>_preview" style="margin-top:var(--scos-s-2);">
									<img src="<?php echo esc_url( $current_url ); ?>" alt="" style="max-width:160px;max-height:80px;border-radius:var(--scos-r-md);border:1px solid var(--scos-border);">
								</div>
								<?php else : ?>
								<div id="<?php echo esc_attr( $key ); ?>_preview"></div>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
						<tr>
							<th>
								<label for="scos_biz_mobile_theme_color"><?php esc_html_e( 'Mobile Theme Colour', 'site-essentials' ); ?></label>
								<div class="scos-form__slug">scos_biz_mobile_theme_color</div>
							</th>
							<td>
								<input id="scos_biz_mobile_theme_color" name="scos_biz_mobile_theme_color"
									type="text" class="scos-input scos-input--mono"
									placeholder="#ffffff"
									value="<?php echo esc_attr( get_option( 'scos_biz_mobile_theme_color', '' ) ); ?>">
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div><!-- /media -->

	</div><!-- /.scos-accordion -->
</form>

<!-- Fixed save bar — outside .wrap.scos so it overlays the admin chrome -->
<div class="scos-save-bar">
	<button type="submit" form="scos-biz-form" class="scos-btn scos-btn--primary">
		<?php esc_html_e( 'Save changes', 'site-essentials' ); ?>
	</button>
	<span style="font-size:var(--scos-fs-sm);color:var(--scos-ink-subtle);">
		<?php esc_html_e( 'All sections are saved together.', 'site-essentials' ); ?>
	</span>
</div>

<script>
( function () {
	// Accordion toggle
	document.querySelectorAll( '.scos-accordion__trigger' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var item = btn.closest( '.scos-accordion__item' );
			var isOpen = item.classList.toggle( 'is-open' );
			btn.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		} );
	} );

	// Media uploader — shared handler for all .scos-media-select buttons
	if ( typeof wp !== 'undefined' && wp.media ) {
		document.querySelectorAll( '.scos-media-select' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var targetId = btn.getAttribute( 'data-target' );
				var input    = document.getElementById( targetId );
				var preview  = document.getElementById( targetId + '_preview' );

				var frame = wp.media( {
					title:    '<?php echo esc_js( __( 'Select Image', 'site-essentials' ) ); ?>',
					button:   { text: '<?php echo esc_js( __( 'Use this image', 'site-essentials' ) ); ?>' },
					multiple: false,
					library:  { type: 'image' }
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					if ( input )   { input.value = attachment.url; }
					if ( preview ) {
						preview.innerHTML = '<img src="' + attachment.url + '" alt="" style="max-width:160px;max-height:80px;border-radius:var(--scos-r-md);border:1px solid var(--scos-border);">';
					}
				} );

				frame.open();
			} );
		} );
	}
} )();
</script>
