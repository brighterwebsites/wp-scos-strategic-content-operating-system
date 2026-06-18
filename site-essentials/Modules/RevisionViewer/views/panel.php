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
 *   string    $edit_url              — WP admin edit link for the post
 *   string    $commenter_url         — current URL + ?simple-commenter=true
 *   string    $approve_nonce         — nonce for scos_rv_approve AJAX action
 *   int       $post_id               — current post ID
 *
 * v1.1 | 2026-06-18
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

			<div class="scos-rv-panel__actions">

				<button type="button"
				        class="scos-rv-action scos-rv-action--approve"
				        data-post-id="<?php echo esc_attr( $post_id ); ?>"
				        data-nonce="<?php echo esc_attr( $approve_nonce ); ?>"
				        data-tooltip="<?php esc_attr_e( 'Moves this page to Testing status — approved for live, monitoring in search', 'site-essentials' ); ?>">
					<svg class="scos-rv-action__icon" width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true">
						<circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/>
						<path d="M5 8l2 2.5 4-4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
					<?php esc_html_e( 'Approve — No changes needed', 'site-essentials' ); ?>
				</button>

				<a href="<?php echo esc_url( $commenter_url ); ?>"
				   class="scos-rv-action scos-rv-action--comment"
				   data-tooltip="<?php esc_attr_e( 'Use the commenter to add annotations to the page', 'site-essentials' ); ?>">
					<svg class="scos-rv-action__icon" width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true">
						<path d="M13.5 2h-11A1.5 1.5 0 0 0 1 3.5V10a1.5 1.5 0 0 0 1.5 1.5H4L6 14l2-2.5h5.5A1.5 1.5 0 0 0 15 10V3.5A1.5 1.5 0 0 0 13.5 2Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
						<path d="M4.5 6.5h7M4.5 8.5h4.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
					</svg>
					<?php esc_html_e( 'Make comments', 'site-essentials' ); ?>
				</a>

				<?php if ( $edit_url ) : ?>
				<a href="<?php echo esc_url( $edit_url ); ?>"
				   class="scos-rv-action scos-rv-action--edit"
				   target="_blank"
				   rel="noopener noreferrer"
				   data-tooltip="<?php esc_attr_e( 'Opens the live version to edit', 'site-essentials' ); ?>">
					<svg class="scos-rv-action__icon" width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true">
						<path d="M11.5 1.5 14.5 4.5l-8 8H3.5v-3l8-8Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
						<path d="M9.5 3.5l3 3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
					</svg>
					<?php esc_html_e( 'Edit live version', 'site-essentials' ); ?>
				</a>
				<?php endif; ?>

			</div>

		</div>
	</details>
</aside>
