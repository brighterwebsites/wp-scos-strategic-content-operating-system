<?php
/**
 * Social Amplification meta box view.
 *
 * Variables from Meta_Box::render():
 *   $post             WP_Post
 *   $shortlink_slug   string
 *   $last_trigger     string  MySQL datetime or empty
 *   $webhook_url      string
 *   $is_published     bool
 *   $yourls_base      string  base URL e.g. https://bweb1.com.au (or empty)
 *
 * @package SiteEssentials
 */

defined( 'ABSPATH' ) || exit;

$webhook_configured = ! empty( $webhook_url );
$can_trigger        = $is_published && $webhook_configured;
?>

<div class="scos-sa-wrap">

	<!-- ── Shortlink Slug ── -->
	<div class="scos-sa-section">
		<div class="scos-sa-field">
			<label for="scos_sa_shortlink_slug">
				<?php esc_html_e( 'YOURLS Shortlink Slug', 'site-essentials' ); ?>
			</label>
			<div class="scos-sa-slug-row">
				<?php if ( $yourls_base ) : ?>
					<span class="scos-sa-slug-prefix"><?php echo esc_html( rtrim( $yourls_base, '/' ) . '/' ); ?></span>
				<?php endif; ?>
				<input type="text"
					name="scos_sa_shortlink_slug"
					id="scos_sa_shortlink_slug"
					value="<?php echo esc_attr( $shortlink_slug ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. seo-signals', 'site-essentials' ); ?>"
					class="scos-sa-slug-input">
				<?php if ( $yourls_base && $shortlink_slug ) : ?>
					<a href="<?php echo esc_url( $yourls_base . '/' . $shortlink_slug ); ?>"
						target="_blank" rel="noopener noreferrer" class="scos-sa-link-out" title="<?php esc_attr_e( 'Open shortlink', 'site-essentials' ); ?>">
						<span class="dashicons dashicons-external"></span>
					</a>
				<?php endif; ?>
			</div>
			<p class="scos-sa-help"><?php esc_html_e( 'Slug format, no spaces. Saved to YOURLS as the shortlink keyword.', 'site-essentials' ); ?></p>
		</div>
	</div>

	<!-- ── Create Social Post ── -->
	<div class="scos-sa-section scos-sa-section--trigger">

		<?php if ( ! $webhook_configured ) : ?>
			<div class="scos-sa-status scos-sa-status--warn">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Webhook not configured.', 'site-essentials' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bw-social-amplification' ) ); ?>">
					<?php esc_html_e( 'Open settings', 'site-essentials' ); ?>
				</a>
			</div>
		<?php elseif ( ! $is_published ) : ?>
			<div class="scos-sa-status scos-sa-status--info">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Publish this post to enable social post creation.', 'site-essentials' ); ?>
			</div>
		<?php endif; ?>

		<button type="button"
			id="scos-sa-trigger-btn"
			class="button button-primary scos-sa-trigger-btn"
			data-post-id="<?php echo esc_attr( $post->ID ); ?>"
			<?php disabled( ! $can_trigger ); ?>>
			<span class="dashicons dashicons-megaphone"></span>
			<?php esc_html_e( 'Create Social Post', 'site-essentials' ); ?>
		</button>

		<?php if ( $last_trigger ) : ?>
			<p class="scos-sa-last-trigger">
				<?php
				printf(
					/* translators: %s = time ago */
					esc_html__( 'Last sent: %s ago', 'site-essentials' ),
					esc_html( human_time_diff( strtotime( $last_trigger ), current_time( 'timestamp' ) ) )
				);
				?>
			</p>
		<?php endif; ?>

		<div id="scos-sa-status-msg" class="scos-sa-result" hidden></div>

	</div><!-- /trigger -->

</div><!-- /scos-sa-wrap -->
