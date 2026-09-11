<?php
/**
 * Postly amplification status block — rendered in the meta box and returned
 * by the scos_sa_amplify / scos_sa_retry_failed AJAX actions.
 *
 * Variables from Meta_Box::render_status():
 *   $post          WP_Post
 *   $is_published  bool
 *   $amplified     bool    _scos_sa_amplified (the re-run lock)
 *   $log_entry     array   Current log entry, empty if never run
 *   $outcome       string  complete | partial | failed | skipped | '' (never run)
 *   $counts        array   { total: int, scheduled: int }
 *   $slot_rows     array   Standard and Google Business slot rows
 *   $queue         array   Failed slots waiting to be resent
 *   $next_retry    int     Unix time of the automatic retry, 0 if none
 *   $history       array   Earlier log entries, newest first
 *
 * @package SiteEssentials
 * v1.0 | 2026-09-11
 */

use SiteEssentials\Modules\SocialAmplification\Amplification\Amplification_Engine;

defined( 'ABSPATH' ) || exit;

if ( 'partial' === $outcome ) {
	$badge_class = 'is-partial';
	/* translators: 1: posts scheduled, 2: posts in the run */
	$badge_label = sprintf( __( 'Partly amplified — %1$d of %2$d', 'site-essentials' ), $counts['scheduled'], $counts['total'] );
} elseif ( 'failed' === $outcome ) {
	$badge_class = 'is-no';
	$badge_label = __( 'Failed — nothing scheduled', 'site-essentials' );
} elseif ( 'complete' === $outcome || $amplified ) {
	$badge_class = 'is-yes';
	$badge_label = __( 'Amplified', 'site-essentials' );
} else {
	$badge_class = 'is-no';
	$badge_label = __( 'Not yet amplified', 'site-essentials' );
}

$status_labels   = [
	'scheduled'       => __( 'Scheduled', 'site-essentials' ),
	'retry_scheduled' => __( 'Retrying', 'site-essentials' ),
	'error'           => __( 'Failed', 'site-essentials' ),
];
$platform_labels = [
	'standard' => __( 'Social', 'site-essentials' ),
	'gmb'      => __( 'Google Business', 'site-essentials' ),
];
$trigger_labels  = [
	'publish'      => __( 'on publish', 'site-essentials' ),
	'button'       => __( 'button', 'site-essentials' ),
	'ability'      => __( 'AI agent', 'site-essentials' ),
	'auto-retry'   => __( 'automatic retry', 'site-essentials' ),
	'manual-retry' => __( 'Retry failed posts', 'site-essentials' ),
	'cli'          => __( 'WP-CLI', 'site-essentials' ),
	'run'          => __( 'backfill', 'site-essentials' ),
];
$outcome_labels  = [
	'complete' => __( 'all scheduled', 'site-essentials' ),
	'partial'  => __( 'partly scheduled', 'site-essentials' ),
	'failed'   => __( 'nothing scheduled', 'site-essentials' ),
	'skipped'  => __( 'nothing to schedule', 'site-essentials' ),
];

$ran_at     = (string) ( $log_entry['ran_at'] ?? '' );
$trigger    = (string) ( $log_entry['trigger'] ?? '' );
$previous   = (array) ( $log_entry['previous_run'] ?? [] );
$still      = (array) ( $previous['still_scheduled'] ?? [] );
$events     = (array) ( $log_entry['events'] ?? [] );
$auto_count = count( array_filter( $queue, static function ( $item ): bool {
	return ! empty( $item['retryable'] ) && (int) ( $item['attempts'] ?? 0 ) < Amplification_Engine::MAX_AUTO_ATTEMPTS;
} ) );
?>

<div class="scos-sa-amplify-header">
	<strong><?php esc_html_e( 'Postly Amplification Status', 'site-essentials' ); ?></strong>
	<span class="scos-sa-amplify-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_label ); ?></span>
</div>

<?php if ( $ran_at ) : ?>
	<p class="scos-sa-help">
		<?php
		printf(
			/* translators: %s date */
			esc_html__( 'Last ran: %s', 'site-essentials' ),
			esc_html( mysql2date( 'j M Y g:i a', $ran_at ) )
		);
		if ( isset( $trigger_labels[ $trigger ] ) ) {
			echo ' <span class="scos-sa-muted">(' . esc_html( $trigger_labels[ $trigger ] ) . ')</span>';
		}
		?>
	</p>
<?php endif; ?>

<?php if ( ! empty( $still ) ) : ?>
	<p class="scos-sa-notice">
		<?php
		$still_list = array_map( static function ( $row ) use ( $platform_labels ): string {
			$platform = (string) ( $row['platform'] ?? 'standard' );
			return ( $platform_labels[ $platform ] ?? $platform ) . ' ' . (string) ( $row['scheduled'] ?? '' );
		}, $still );
		printf(
			esc_html(
				/* translators: 1: date of the previous run, 2: number of posts, 3: list of platform + date */
				_n(
					'This run replaced one from %1$s that had %2$d post scheduled in Postly (%3$s). It is still there — delete it in Postly if you don’t want both.',
					'This run replaced one from %1$s that had %2$d posts scheduled in Postly (%3$s). They are still there — delete any duplicates in Postly.',
					count( $still ),
					'site-essentials'
				)
			),
			esc_html( (string) mysql2date( 'j M Y g:i a', (string) ( $previous['ran_at'] ?? '' ) ) ),
			(int) count( $still ),
			esc_html( implode( ', ', $still_list ) )
		);
		?>
	</p>
<?php endif; ?>

<?php if ( ! empty( $slot_rows ) ) : ?>
	<table class="widefat striped scos-sa-slot-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Platform', 'site-essentials' ); ?></th>
				<th><?php esc_html_e( 'Slot', 'site-essentials' ); ?></th>
				<th><?php esc_html_e( 'Scheduled', 'site-essentials' ); ?></th>
				<th><?php esc_html_e( 'Status', 'site-essentials' ); ?></th>
				<th><?php esc_html_e( 'Postly ID', 'site-essentials' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $slot_rows as $slot_row ) : ?>
			<?php
			$row_status   = (string) ( $slot_row['status'] ?? '' );
			$row_platform = (string) ( $slot_row['platform'] ?? 'standard' );
			?>
			<tr class="scos-sa-slot--<?php echo esc_attr( $row_status ); ?>">
				<td><?php echo esc_html( $platform_labels[ $row_platform ] ?? $row_platform ); ?></td>
				<td><?php echo esc_html( (string) ( $slot_row['slot'] ?? '—' ) ); ?></td>
				<td><?php echo esc_html( (string) ( $slot_row['scheduled'] ?? '' ) ?: '—' ); ?></td>
				<td>
					<?php echo esc_html( $status_labels[ $row_status ] ?? ( $row_status ?: '—' ) ); ?>
					<?php if ( ! empty( $slot_row['error'] ) ) : ?>
						<div class="scos-sa-slot-error"><?php echo esc_html( (string) $slot_row['error'] ); ?></div>
					<?php endif; ?>
					<?php if ( ! empty( $slot_row['note'] ) ) : ?>
						<div class="scos-sa-slot-note"><?php echo esc_html( (string) $slot_row['note'] ); ?></div>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( (string) ( $slot_row['postly_id'] ?? '' ) ?: '—' ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( ! empty( $queue ) ) : ?>
	<p class="scos-sa-help">
		<?php
		$manual_count = count( $queue );
		if ( $next_retry && $auto_count ) {
			printf(
				esc_html(
					/* translators: 1: number of posts, 2: time */
					_n( '%1$d failed post will be resent automatically at %2$s.', '%1$d failed posts will be resent automatically at %2$s.', $auto_count, 'site-essentials' )
				),
				(int) $auto_count,
				esc_html( wp_date( 'g:i a', $next_retry ) )
			);
			$manual_count -= $auto_count;
		}
		if ( $manual_count > 0 ) {
			echo ' ';
			printf(
				esc_html(
					/* translators: %d: number of posts */
					_n( '%d failed post is waiting for Retry failed posts (fix the cause first if it’s a setting).', '%d failed posts are waiting for Retry failed posts (fix the cause first if it’s a setting).', $manual_count, 'site-essentials' )
				),
				(int) $manual_count
			);
		}
		?>
	</p>
<?php endif; ?>

<?php if ( $is_published ) : ?>
	<p class="scos-sa-actions">
		<button type="button"
			id="scos-sa-reamp-btn"
			class="button <?php echo $amplified ? 'button-secondary' : 'button-primary'; ?>"
			data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
			data-amplified="<?php echo $amplified ? '1' : '0'; ?>"
			data-scheduled="<?php echo esc_attr( (string) $counts['scheduled'] ); ?>">
			<?php if ( $amplified ) : ?>
				<?php esc_html_e( 'Reset & Re-amplify', 'site-essentials' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Create Social Post', 'site-essentials' ); ?>
			<?php endif; ?>
		</button>
		<?php if ( ! empty( $queue ) ) : ?>
			<button type="button"
				id="scos-sa-retry-btn"
				class="button button-primary"
				data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>">
				<?php echo $next_retry ? esc_html__( 'Retry failed posts now', 'site-essentials' ) : esc_html__( 'Retry failed posts', 'site-essentials' ); ?>
			</button>
		<?php endif; ?>
	</p>
<?php endif; ?>

<?php if ( ! empty( $events ) || ! empty( $history ) ) : ?>
	<details class="scos-sa-history">
		<summary>
			<?php
			printf(
				/* translators: %d: number of earlier runs */
				esc_html( _n( 'Activity (%d earlier run)', 'Activity (%d earlier runs)', count( $history ), 'site-essentials' ) ),
				(int) count( $history )
			);
			?>
		</summary>
		<ul>
			<?php foreach ( array_reverse( $events ) as $event ) : ?>
				<li>
					<?php
					$event_trigger = (string) ( $event['trigger'] ?? '' );
					echo esc_html( sprintf(
						'%s — %s: %s',
						mysql2date( 'j M Y g:i a', (string) ( $event['at'] ?? '' ) ),
						$trigger_labels[ $event_trigger ] ?? $event_trigger,
						(string) ( $event['summary'] ?? '' )
					) );
					?>
				</li>
			<?php endforeach; ?>
			<?php foreach ( $history as $old ) : ?>
				<li>
					<?php
					$old_counts  = Amplification_Engine::slot_counts( (array) $old );
					$old_outcome = Amplification_Engine::outcome_of( (array) $old );
					$old_trigger = (string) ( $old['trigger'] ?? '' );
					$old_ids     = [];
					foreach ( array_merge( (array) ( $old['standard_posts'] ?? $old['posts'] ?? [] ), (array) ( $old['gmb_posts'] ?? [] ) ) as $old_row ) {
						if ( ! empty( $old_row['postly_id'] ) ) {
							$old_ids[] = (string) $old_row['postly_id'];
						}
					}
					echo esc_html( sprintf(
						/* translators: 1: date, 2: trigger, 3: outcome, 4: posts scheduled, 5: posts in the run */
						__( '%1$s — earlier run (%2$s): %3$s, %4$d of %5$d', 'site-essentials' ),
						mysql2date( 'j M Y g:i a', (string) ( $old['ran_at'] ?? '' ) ),
						$trigger_labels[ $old_trigger ] ?? ( $old_trigger ?: '—' ),
						$outcome_labels[ $old_outcome ] ?? $old_outcome,
						$old_counts['scheduled'],
						$old_counts['total']
					) );
					if ( $old_ids ) {
						echo ' <span class="scos-sa-muted">' . esc_html( 'Postly IDs: ' . implode( ', ', $old_ids ) ) . '</span>';
					}
					?>
				</li>
			<?php endforeach; ?>
		</ul>
	</details>
<?php endif; ?>
