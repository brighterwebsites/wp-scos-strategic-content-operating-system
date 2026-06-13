<?php
/**
 * Revision Viewer — Floating Front-End Panel
 *
 * Variables injected by Revision_Viewer::render_panel():
 *   int|null  $current_id            — active revision ID, null = live
 *   string    $live_url              — clean permalink (no query args)
 *   string    $next_step             — scos_ca_next_step value
 *   array     $rev_list              — WP_Post[] revisions, newest-first
 *   int       $rev_count             — total revision count
 *   string|null $newer_url           — URL for the newer step (null = disabled)
 *   string|null $older_url           — URL for the older step (null = disabled)
 *   string    $pos_label             — "Live version" | "Revision N of M"
 *   string|null $viewing_date        — formatted date of active revision
 *   string|null $viewing_author      — display name of revision author
 *   bool      $is_viewing_revision   — true when previewing a revision
 *
 * @package SiteEssentials\Modules\RevisionViewer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status_labels = [
	'revise' => __( 'Revise',  'site-essentials' ),
	'review' => __( 'Review',  'site-essentials' ),
];
$status_label = isset( $status_labels[ $next_step ] ) ? $status_labels[ $next_step ] : esc_html( ucfirst( $next_step ) );
?>
<aside id="scos-rv-panel" class="scos-rv-panel scos-rv-panel--<?php echo esc_attr( $next_step ); ?>" aria-label="<?php esc_attr_e( 'Revision viewer', 'site-essentials' ); ?>">
	<details open>
		<summary class="scos-rv-panel__header">
			<span class="scos-rv-panel__title"><?php esc_html_e( 'Revisions', 'site-essentials' ); ?></span>
			<span class="scos-rv-panel__badge scos-rv-badge--<?php echo esc_attr( $next_step ); ?>"><?php echo esc_html( $status_label ); ?></span>
		</summary>

		<div class="scos-rv-panel__body">

			<p class="scos-rv-panel__state">
				<?php if ( $is_viewing_revision ) : ?>
					<strong><?php echo esc_html( $pos_label ); ?></strong>
					<?php if ( $viewing_date ) : ?>
						<span class="scos-rv-panel__meta"><?php echo esc_html( $viewing_date ); ?></span>
					<?php endif; ?>
					<?php if ( $viewing_author ) : ?>
						<span class="scos-rv-panel__meta"><?php printf( esc_html__( 'by %s', 'site-essentials' ), esc_html( $viewing_author ) ); ?></span>
					<?php endif; ?>
				<?php else : ?>
					<strong><?php esc_html_e( 'Live version', 'site-essentials' ); ?></strong>
					<span class="scos-rv-panel__meta">
						<?php printf(
							/* translators: %d: number of revisions */
							esc_html( _n( '%d revision available', '%d revisions available', $rev_count, 'site-essentials' ) ),
							$rev_count
						); ?>
					</span>
				<?php endif; ?>
			</p>

			<nav class="scos-rv-panel__nav" aria-label="<?php esc_attr_e( 'Revision navigation', 'site-essentials' ); ?>">

				<?php if ( $newer_url ) : ?>
					<a href="<?php echo esc_url( $newer_url ); ?>"
					   class="scos-rv-btn"
					   title="<?php esc_attr_e( 'View newer revision', 'site-essentials' ); ?>">
						&#8592; <?php esc_html_e( 'Newer', 'site-essentials' ); ?>
					</a>
				<?php else : ?>
					<span class="scos-rv-btn scos-rv-btn--disabled" aria-disabled="true">&#8592; <?php esc_html_e( 'Newer', 'site-essentials' ); ?></span>
				<?php endif; ?>

				<?php if ( $is_viewing_revision ) : ?>
					<a href="<?php echo esc_url( $live_url ); ?>" class="scos-rv-btn scos-rv-btn--live"><?php esc_html_e( 'Live', 'site-essentials' ); ?></a>
				<?php else : ?>
					<span class="scos-rv-btn scos-rv-btn--live scos-rv-btn--active" aria-current="true"><?php esc_html_e( 'Live', 'site-essentials' ); ?></span>
				<?php endif; ?>

				<?php if ( $older_url ) : ?>
					<a href="<?php echo esc_url( $older_url ); ?>"
					   class="scos-rv-btn"
					   title="<?php esc_attr_e( 'View older revision', 'site-essentials' ); ?>">
						<?php esc_html_e( 'Older', 'site-essentials' ); ?> &#8594;
					</a>
				<?php else : ?>
					<span class="scos-rv-btn scos-rv-btn--disabled" aria-disabled="true"><?php esc_html_e( 'Older', 'site-essentials' ); ?> &#8594;</span>
				<?php endif; ?>

			</nav>

		</div>
	</details>
</aside>
