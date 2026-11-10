<?php
/**
 * Commerce Module — settings view
 *
 * v1.0 | 2026-11-11
 *
 * Template C (single-section settings). Rendered inside <div class="wrap scos">
 * by Admin_UI::render_commerce_page() via Commerce_Module::render_settings().
 * Each option says in plain words what it changes, so the page doubles as the
 * "should this site have it on?" reference.
 *
 * Variables available:
 * @var array    $settings    store_only_assets, hide_loop_add_to_cart (bool).
 * @var bool     $woo_active  WooCommerce is active.
 * @var bool     $sitewide    A published Breakdance header/footer has a cart.
 * @var string[] $style_list  Style handles removed outside the store.
 * @var string[] $script_list Script handles removed outside the store.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Commerce\Views
 */

use SiteEssentials\Modules\Commerce\Commerce_Module;

defined( 'ABSPATH' ) || exit;

$scos_opt = Commerce_Module::OPTION;

/**
 * Comma-separated <code> list of handles.
 *
 * @param string[] $handles Handles.
 * @return string Escaped HTML.
 */
$scos_code_list = static function ( array $handles ) {
	return implode( ', ', array_map( static fn( $h ) => '<code>' . esc_html( $h ) . '</code>', $handles ) );
};
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="scos_save_commerce">
	<?php wp_nonce_field( 'scos_save_commerce', 'scos_commerce_nonce' ); ?>

	<header class="scos__header">
		<div>
			<h1 class="scos__title"><?php esc_html_e( 'Commerce', 'site-essentials' ); ?></h1>
			<p class="scos__subtitle"><?php esc_html_e( 'Site Essentials › Commerce', 'site-essentials' ); ?></p>
		</div>
		<div class="scos__header-actions">
			<button type="submit" class="scos-btn scos-btn--primary"><?php esc_html_e( 'Save changes', 'site-essentials' ); ?></button>
		</div>
	</header>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect. ?>
	<?php if ( isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) : ?>
		<div class="scos-notice scos-notice--success">
			<p><?php esc_html_e( 'Commerce settings saved. Purge the page cache so visitors get the change.', 'site-essentials' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $woo_active ) : ?>
		<div class="scos-notice scos-notice--info">
			<p><?php esc_html_e( 'WooCommerce is not active on this site, so nothing on this page has any effect. The module can stay on harmlessly.', 'site-essentials' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- ── Store-only assets ─────────────────────────────────────────────── -->
	<div class="scos-card">
		<div class="scos-card__header scos-card__header--plain">
			<div>
				<h2 class="scos-card__title"><?php esc_html_e( 'Load WooCommerce only in the store', 'site-essentials' ); ?></h2>
				<p class="scos-card__desc">
					<?php esc_html_e( 'WooCommerce and Breakdance load their shop CSS and JavaScript on every page, including blog posts and service pages that have nothing to sell. This turns them off outside the store. On a typical page that saves about 35 KB of CSS plus WooCommerce\'s scripts, and often jQuery with them, which helps page speed scores.', 'site-essentials' ); ?>
				</p>
			</div>
		</div>
		<div class="scos-card__body">
			<table class="scos-form">
				<tbody>
					<tr>
						<th>
							<label for="<?php echo esc_attr( $scos_opt . '_store_only_assets' ); ?>"><?php esc_html_e( 'Store-only assets', 'site-essentials' ); ?></label>
							<div class="scos-form__slug"><?php echo esc_html( $scos_opt . '[store_only_assets]' ); ?></div>
						</th>
						<td>
							<label class="scos-toggle">
								<input type="checkbox"
									id="<?php echo esc_attr( $scos_opt . '_store_only_assets' ); ?>"
									name="<?php echo esc_attr( $scos_opt . '[store_only_assets]' ); ?>"
									value="1" <?php checked( $settings['store_only_assets'] ); ?>>
								<span class="scos-toggle__track"></span>
							</label>
							<p class="description"><?php esc_html_e( 'Default: on (once the Commerce module is enabled).', 'site-essentials' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Status on this site', 'site-essentials' ); ?></th>
						<td>
							<?php if ( ! $woo_active ) : ?>
								<p class="description"><?php esc_html_e( 'WooCommerce not active: nothing is removed.', 'site-essentials' ); ?></p>
							<?php elseif ( $sitewide ) : ?>
								<div class="scos-notice scos-notice--warning">
									<p><?php esc_html_e( 'A published Breakdance header or footer has a cart, so WooCommerce has to load on every page for the cart to work. This setting currently saves nothing here. Remove the cart from the header/footer for it to take effect.', 'site-essentials' ); ?></p>
								</div>
							<?php elseif ( $settings['store_only_assets'] ) : ?>
								<p class="description"><?php esc_html_e( 'Active. No cart in the header or footer, so pages outside the store load without WooCommerce.', 'site-essentials' ); ?></p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Off. Every page loads WooCommerce. No cart in the header or footer, so turning this on would take effect.', 'site-essentials' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Pages that keep WooCommerce', 'site-essentials' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Shop, single products, product categories and tags, cart, checkout and My Account.', 'site-essentials' ); ?></p>
							<p class="description"><?php esc_html_e( 'Any page or post whose own content has a WooCommerce block, a WooCommerce shortcode ([products], [add_to_cart] and so on) or a Breakdance WooCommerce element.', 'site-essentials' ); ?></p>
							<p class="description"><?php esc_html_e( 'Every page, when a published header or footer contains a cart.', 'site-essentials' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Removed on every other page', 'site-essentials' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Styles:', 'site-essentials' ); ?>
								<?php echo $scos_code_list( $style_list ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>,
								<?php esc_html_e( 'and Breakdance\'s breakdance-woocommerce.css.', 'site-essentials' ); ?>
							</p>
							<p class="description">
								<?php esc_html_e( 'Scripts:', 'site-essentials' ); ?>
								<?php echo $scos_code_list( $script_list ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>.
							</p>
							<p class="description"><?php esc_html_e( 'Also the woocommerce-no-js body class and its inline script. jQuery drops out too when nothing else on the page needs it.', 'site-essentials' ); ?></p>
							<p class="description"><?php esc_html_e( 'Never touched: wp-admin, the Breakdance builder and previews, AJAX, REST, cron and WP-CLI.', 'site-essentials' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Watch out for', 'site-essentials' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'A WooCommerce element inside a Breakdance Global Block, or products shown by another plugin on a normal page. The check only reads the page\'s own content, so those pages lose their shop styling and add-to-cart. Check them after turning this on; fix with the scos_commerce_is_store_request filter.', 'site-essentials' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Good fit', 'site-essentials' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Any WooCommerce site whose header and footer have no cart, especially where most visits land on blog, service or information pages.', 'site-essentials' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- ── Loop add-to-cart ──────────────────────────────────────────────── -->
	<div class="scos-card">
		<div class="scos-card__header scos-card__header--plain">
			<div>
				<h2 class="scos-card__title"><?php esc_html_e( 'Hide add-to-cart in product lists', 'site-essentials' ); ?></h2>
				<p class="scos-card__desc">
					<?php esc_html_e( 'Catalogue mode for product lists. Visitors open the product to buy or enquire instead of adding to cart straight from the grid.', 'site-essentials' ); ?>
				</p>
			</div>
		</div>
		<div class="scos-card__body">
			<table class="scos-form">
				<tbody>
					<tr>
						<th>
							<label for="<?php echo esc_attr( $scos_opt . '_hide_loop_add_to_cart' ); ?>"><?php esc_html_e( 'Hide loop add-to-cart', 'site-essentials' ); ?></label>
							<div class="scos-form__slug"><?php echo esc_html( $scos_opt . '[hide_loop_add_to_cart]' ); ?></div>
						</th>
						<td>
							<label class="scos-toggle">
								<input type="checkbox"
									id="<?php echo esc_attr( $scos_opt . '_hide_loop_add_to_cart' ); ?>"
									name="<?php echo esc_attr( $scos_opt . '[hide_loop_add_to_cart]' ); ?>"
									value="1" <?php checked( $settings['hide_loop_add_to_cart'] ); ?>>
								<span class="scos-toggle__track"></span>
							</label>
							<p class="description"><?php esc_html_e( 'Default: off.', 'site-essentials' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'What changes', 'site-essentials' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'The add-to-cart button and quantity box disappear from the shop page, product categories and tags, Breakdance product lists and the Breakdance loop cart button element.', 'site-essentials' ); ?></p>
							<p class="description"><?php esc_html_e( 'Single product pages are unchanged: their add-to-cart stays. Breakdance\'s per-element "button" option still works; this is the site-wide switch.', 'site-essentials' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Good fit', 'site-essentials' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Quote-led or catalogue stores, and products that need options chosen on the product page before they can be bought.', 'site-essentials' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</form>
