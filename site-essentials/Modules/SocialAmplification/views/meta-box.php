<?php
/**
 * Social Amplification meta box view.
 *
 * Variables from Meta_Box::render():
 *   $post             WP_Post
 *   $shortlink_slug   string
 *   $yourls_base      string  base URL e.g. https://bweb1.com.au (or empty)
 *   $status_html      string  Postly status block (views/amplify-status.php, already escaped)
 *
 * @package SiteEssentials
 */

defined( 'ABSPATH' ) || exit;
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

	<!-- ── Amplification Status / Re-run ── -->
	<div class="scos-sa-section scos-sa-section--amplify">
		<div id="scos-sa-amplify-status">
			<?php echo $status_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in views/amplify-status.php ?>
		</div>
		<div id="scos-sa-reamp-msg" class="scos-sa-result" hidden></div>
	</div>

</div><!-- /scos-sa-wrap -->
