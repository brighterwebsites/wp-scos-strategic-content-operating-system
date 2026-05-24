<?php
/**
 * Module Toggle Card
 *
 * v1.1 | 2026-05-19
 *
 * SCOS design system: scos-module, scos-toggle, scos-badge.
 * No functional changes — data-module-id, AJAX toggle checkbox unchanged.
 *
 * @package    SiteEssentials
 * @subpackage Views
 *
 * Variables available:
 * @var string $module_id    Module ID
 * @var string $name         Module name
 * @var string $description  Module description
 * @var string $tier         Module tier (basic, pro, agency)
 * @var array  $dependencies Module dependencies
 * @var bool   $is_enabled   Is module enabled
 * @var bool   $is_loaded    Is module loaded
 * @var bool   $has_failed   Did module fail to load
 */

defined( 'ABSPATH' ) || exit;

$card_class = 'scos-module' . ( $is_enabled ? ' scos-module--on' : '' );

if ( 'pro' === $tier ) {
	$badge_class = 'scos-badge--pro';
} elseif ( 'agency' === $tier || 'enterprise' === $tier ) {
	$badge_class = 'scos-badge--enterprise';
} else {
	$badge_class = 'scos-badge--basic';
}

if ( $has_failed ) {
	$status_class = 'scos-module__status--error';
	$status_icon  = '✗';
	$status_label = __( 'Failed to load', 'site-essentials' );
} elseif ( $is_loaded ) {
	$status_class = 'scos-module__status--on';
	$status_icon  = '✓';
	$status_label = __( 'Loaded', 'site-essentials' );
} elseif ( $is_enabled ) {
	$status_class = 'scos-module__status--off';
	$status_icon  = '○';
	$status_label = __( 'Enabled (loads on next page load)', 'site-essentials' );
} else {
	$status_class = 'scos-module__status--off';
	$status_icon  = '';
	$status_label = __( 'Toggle on to load', 'site-essentials' );
}
?>

<div class="<?php echo esc_attr( $card_class ); ?>"
     data-module-id="<?php echo esc_attr( $module_id ); ?>">

	<div class="scos-module__head">
		<span class="scos-module__title">
			<?php echo esc_html( $name ); ?>
		</span>
		<label class="scos-toggle" title="<?php echo $is_enabled ? esc_attr__( 'Disable module', 'site-essentials' ) : esc_attr__( 'Enable module', 'site-essentials' ); ?>">
			<input type="checkbox"
			       class="se-module-toggle"
			       data-module-id="<?php echo esc_attr( $module_id ); ?>"
			       <?php checked( $is_enabled ); ?>>
			<span class="scos-toggle__track"></span>
		</label>
	</div>

	<div class="scos-module__meta">
		<span class="scos-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( strtoupper( $tier ) ); ?></span>
		<?php if ( ! empty( $dependencies ) ) : ?>
			<span class="scos-badge scos-badge--soft" style="font-size:11px">
				<?php echo esc_html( implode( ', ', $dependencies ) ); ?>
			</span>
		<?php endif; ?>
	</div>

	<hr class="scos-module__divider">

	<div class="scos-module__foot">
		<span class="scos-module__status <?php echo esc_attr( $status_class ); ?>">
			<?php if ( $status_icon ) : ?>
				<span aria-hidden="true"><?php echo esc_html( $status_icon ); ?></span>
			<?php endif; ?>
			<?php echo esc_html( $status_label ); ?>
		</span>
	</div>

</div>
