<?php
/**
 * Business Info — Shortcodes Reference panel.
 */
defined( 'ABSPATH' ) || exit;

$fields = function_exists( 'brighter_get_business_info_fields' ) ? brighter_get_business_info_fields() : [];
?>
<hr style="margin:36px 0 24px">

<details id="scos-biz-shortcodes" style="max-width:720px">
	<summary style="cursor:pointer;font-size:14px;font-weight:600;padding:8px 0">
		<?php esc_html_e( 'Shortcodes Reference', 'site-essentials' ); ?>
	</summary>

	<div style="margin-top:16px">

		<h4 style="margin-top:0">[business_info setting="..."]</h4>
		<p class="description" style="margin-bottom:10px">
			<?php esc_html_e( 'Outputs a single business info field. Use the setting names below.', 'site-essentials' ); ?>
		</p>

		<?php if ( $fields ) : ?>
		<table class="wp-list-table widefat striped" style="max-width:620px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Shortcode', 'site-essentials' ); ?></th>
					<th><?php esc_html_e( 'Current value', 'site-essentials' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $fields as $field ) : ?>
				<tr>
					<td><code>[business_info setting="<?php echo esc_attr( $field ); ?>"]</code></td>
					<td>
						<?php
						$val = function_exists( 'brighter_get_option' ) ? brighter_get_option( $field ) : '';
						echo $val ? esc_html( $val ) : '<span style="color:#999;font-style:italic">' . esc_html__( 'not set', 'site-essentials' ) . '</span>';
						?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<h4 style="margin-top:24px">[site_copyright]</h4>
		<p class="description">
			<?php esc_html_e( 'Outputs:', 'site-essentials' ); ?>
			<code>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>.
			<?php
			$abn = function_exists( 'brighter_get_option' ) ? brighter_get_option( 'abn' ) : '';
			if ( $abn ) {
				echo 'ABN ' . esc_html( preg_replace( '/[^0-9\s]/', '', $abn ) ) . '. ';
			}
			?>
			<?php esc_html_e( 'All rights reserved.', 'site-essentials' ); ?></code>
		</p>

	</div>
</details>
