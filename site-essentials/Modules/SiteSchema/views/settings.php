<?php
/**
 * Site Schema Module — settings view.
 *
 * Tabbed panel: Local Business | Success Stories | Product | Service
 * Replaces legacy wp-admin/admin.php?page=brighter-schema
 */
defined( 'ABSPATH' ) || exit;

$tabs = [
	'local-business'  => __( 'Local Business', 'site-essentials' ),
	'success-stories' => __( 'Success Stories', 'site-essentials' ),
	'product'         => __( 'Product', 'site-essentials' ),
	'service'         => __( 'Service', 'site-essentials' ),
];

$current_tab = isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $tabs )
	? sanitize_key( $_GET['tab'] )
	: 'local-business';

$base_url = add_query_arg( 'page', 'site-essentials-schema', admin_url( 'admin.php' ) );

$local_business  = get_option( 'scos_site_schema_local_business', '' );
$success_stories = get_option( 'scos_site_schema_success_stories', '' );
$product         = get_option( 'scos_site_schema_product', '' );
$product_ids     = get_option( 'scos_site_schema_product_ids', '' );
$service         = get_option( 'scos_site_schema_service', '' );
$service_ids     = get_option( 'scos_site_schema_service_ids', '' );

if ( isset( $_GET['scos_schema_saved'] ) ) {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Schema settings saved.', 'site-essentials' ) . '</p></div>';
}
?>

<nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom:20px" aria-label="<?php esc_attr_e( 'Schema tabs', 'site-essentials' ); ?>">
	<?php foreach ( $tabs as $tab => $label ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'tab', $tab, $base_url ) ); ?>"
		   class="nav-tab <?php echo $current_tab === $tab ? 'nav-tab-active' : ''; ?>">
			<?php echo esc_html( $label ); ?>
		</a>
	<?php endforeach; ?>
</nav>

<form method="post" id="scos-schema-form">
	<?php wp_nonce_field( 'scos_site_schema_save', 'scos_site_schema_nonce' ); ?>
	<input type="hidden" name="current_tab_hidden" value="<?php echo esc_attr( $current_tab ); ?>">

	<div class="scos-schema-tab-content">

		<?php if ( $current_tab === 'local-business' ) : ?>

			<h3 style="margin-top:0"><?php esc_html_e( 'Local Business Schema', 'site-essentials' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Site-wide LocalBusiness JSON-LD included in your schema graph on every page.', 'site-essentials' ); ?></p>

			<?php if ( function_exists( 'brighter_get_option' ) && brighter_get_option( 'business_name' ) ) : ?>
			<p style="margin-bottom:12px">
				<button type="button" id="scos-generate-local-biz" class="button button-secondary">
					<?php esc_html_e( 'Generate from Business Info', 'site-essentials' ); ?>
				</button>
				<span class="description" style="margin-left:8px"><?php esc_html_e( 'Pre-fills the textarea from your Business Info fields — review and save.', 'site-essentials' ); ?></span>
			</p>
			<?php endif; ?>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="scos_site_schema_local_business"><?php esc_html_e( 'Local Business JSON-LD', 'site-essentials' ); ?></label></th>
					<td>
						<textarea id="scos_site_schema_local_business" name="scos_site_schema_local_business"
							rows="22" class="large-text code scos-schema-json"
							style="font-family:monospace;font-size:12px;max-width:800px"
							placeholder='{"@type": "LocalBusiness", "@id": "<?php echo esc_js( home_url( '/#organization' ) ); ?>", "name": "Your Business", "url": "<?php echo esc_js( home_url( '/' ) ); ?>"}'><?php echo esc_textarea( $local_business ); ?></textarea>
						<div id="scos_site_schema_local_business-validation" class="scos-schema-validation" aria-live="polite" style="margin-top:6px;padding:8px 12px;border-radius:4px;display:none"></div>
						<p class="description"><?php esc_html_e( 'Leave empty to auto-generate from Business Info fields. Single block: { }. Multiple: [ { }, { } ].', 'site-essentials' ); ?></p>
					</td>
				</tr>
			</table>

		<?php elseif ( $current_tab === 'success-stories' ) : ?>

			<h3 style="margin-top:0"><?php esc_html_e( 'Success Stories Schema', 'site-essentials' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Merged into the schema graph on every single Project/Success Story page (post type: projects).', 'site-essentials' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="scos_site_schema_success_stories"><?php esc_html_e( 'Success Stories JSON-LD', 'site-essentials' ); ?></label></th>
					<td>
						<textarea id="scos_site_schema_success_stories" name="scos_site_schema_success_stories"
							rows="22" class="large-text code scos-schema-json"
							style="font-family:monospace;font-size:12px;max-width:800px"
							placeholder='{"@type": "CreativeWork", "name": "Example Success Story"}'><?php echo esc_textarea( $success_stories ); ?></textarea>
						<div id="scos_site_schema_success_stories-validation" class="scos-schema-validation" aria-live="polite" style="margin-top:6px;padding:8px 12px;border-radius:4px;display:none"></div>
						<p class="description"><?php esc_html_e( 'Single block: { }. Multiple: [ { }, { } ]. Invalid JSON is stored but not output.', 'site-essentials' ); ?></p>
					</td>
				</tr>
			</table>

		<?php elseif ( $current_tab === 'product' ) : ?>

			<h3 style="margin-top:0"><?php esc_html_e( 'Product Schema', 'site-essentials' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Merged into the schema graph on single posts/pages whose IDs are in the list below.', 'site-essentials' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="scos_site_schema_product_ids"><?php esc_html_e( 'Post/Page IDs', 'site-essentials' ); ?></label></th>
					<td>
						<input type="text" id="scos_site_schema_product_ids" name="scos_site_schema_product_ids"
							value="<?php echo esc_attr( $product_ids ); ?>" class="regular-text" placeholder="123, 456, 789">
						<p class="description"><?php esc_html_e( 'Comma-separated post or page IDs that should output Product schema.', 'site-essentials' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scos_site_schema_product"><?php esc_html_e( 'Product JSON-LD', 'site-essentials' ); ?></label></th>
					<td>
						<textarea id="scos_site_schema_product" name="scos_site_schema_product"
							rows="18" class="large-text code scos-schema-json"
							style="font-family:monospace;font-size:12px;max-width:800px"
							placeholder='{"@type": "Product", "name": "Product Name"}'><?php echo esc_textarea( $product ); ?></textarea>
						<div id="scos_site_schema_product-validation" class="scos-schema-validation" aria-live="polite" style="margin-top:6px;padding:8px 12px;border-radius:4px;display:none"></div>
						<p class="description"><?php esc_html_e( 'Single block: { }. Multiple: [ { }, { } ].', 'site-essentials' ); ?></p>
					</td>
				</tr>
			</table>

		<?php elseif ( $current_tab === 'service' ) : ?>

			<h3 style="margin-top:0"><?php esc_html_e( 'Service Schema', 'site-essentials' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Merged into the schema graph on single posts/pages whose IDs are in the list below.', 'site-essentials' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="scos_site_schema_service_ids"><?php esc_html_e( 'Post/Page IDs', 'site-essentials' ); ?></label></th>
					<td>
						<input type="text" id="scos_site_schema_service_ids" name="scos_site_schema_service_ids"
							value="<?php echo esc_attr( $service_ids ); ?>" class="regular-text" placeholder="123, 456, 789">
						<p class="description"><?php esc_html_e( 'Comma-separated post or page IDs that should output Service schema.', 'site-essentials' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scos_site_schema_service"><?php esc_html_e( 'Service JSON-LD', 'site-essentials' ); ?></label></th>
					<td>
						<textarea id="scos_site_schema_service" name="scos_site_schema_service"
							rows="18" class="large-text code scos-schema-json"
							style="font-family:monospace;font-size:12px;max-width:800px"
							placeholder='{"@type": "Service", "name": "Service Name"}'><?php echo esc_textarea( $service ); ?></textarea>
						<div id="scos_site_schema_service-validation" class="scos-schema-validation" aria-live="polite" style="margin-top:6px;padding:8px 12px;border-radius:4px;display:none"></div>
						<p class="description"><?php esc_html_e( 'Single block: { }. Multiple: [ { }, { } ].', 'site-essentials' ); ?></p>
					</td>
				</tr>
			</table>

		<?php endif; ?>

	</div>

	<p class="submit">
		<?php submit_button( __( 'Save Schema', 'site-essentials' ), 'primary', 'submit', false ); ?>
	</p>
</form>

<hr style="margin:28px 0 20px">

<details>
	<summary style="cursor:pointer;font-weight:600;font-size:13px"><?php esc_html_e( 'Available template variables (click to expand)', 'site-essentials' ); ?></summary>
	<ul style="line-height:1.9;margin-top:8px;margin-left:1em">
		<li><code>%%post_title%%</code> &mdash; <?php esc_html_e( 'Post/page title', 'site-essentials' ); ?></li>
		<li><code>%%post_excerpt%%</code> &mdash; <?php esc_html_e( 'Excerpt', 'site-essentials' ); ?></li>
		<li><code>%%post_date%%</code>, <code>%%post_modified%%</code> &mdash; <?php esc_html_e( 'Date (ISO 8601)', 'site-essentials' ); ?></li>
		<li><code>%%post_url%%</code>, <code>%%post_id%%</code>, <code>%%post_name%%</code></li>
		<li><code>%%post_author%%</code>, <code>%%post_thumbnail_url%%</code></li>
		<li><code>%%site_name%%</code>, <code>%%site_url%%</code></li>
		<li><code>%%_cmeta_meta_key%%</code> &mdash; <?php esc_html_e( 'Custom post meta', 'site-essentials' ); ?></li>
		<li><code>%%_cmeta_options_option_key%%</code> &mdash; <?php esc_html_e( 'WordPress option value (allowed option name prefixes: se_, scos_, site_essentials_; works in site-wide schema without a post)', 'site-essentials' ); ?></li>
		<li><code>%%_acf_field_name%%</code> &mdash; <?php esc_html_e( 'ACF field', 'site-essentials' ); ?></li>
	</ul>
	<p class="description"><?php esc_html_e( 'Multiple blocks: use a single array [ { … }, { … } ].', 'site-essentials' ); ?></p>
</details>

<style>
.scos-schema-validation.valid   { background:#d4edda;color:#155724;display:block!important }
.scos-schema-validation.invalid { background:#f8d7da;color:#721c24;display:block!important }
</style>
