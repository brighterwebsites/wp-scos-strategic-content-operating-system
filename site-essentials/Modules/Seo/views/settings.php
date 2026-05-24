<?php
/**
 * SEO Module Settings View — Sitemaps tab
 *
 * v1.1 | 2026-05-19
 *
 * SCOS design system: 3 scos-cards (Stats, XML Sitemaps, HTML Sitemaps).
 * No functional changes — form action, field names, nonce, and AJAX hooks unchanged.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Seo
 *
 * Variables available:
 * @var array $sitemap_settings  Current sitemap settings
 * @var array $all_post_types    All public post types
 */

defined( 'ABSPATH' ) || exit;

$cache_time = get_transient( 'se_sitemap_cache_time' );
$cache_age  = $cache_time ? human_time_diff( $cache_time, current_time( 'timestamp' ) ) : null;
?>

<!-- ── Card 1: Sitemap Stats ──────────────────────────────────────── -->
<div class="scos-card" style="margin-bottom:var(--scos-s-4)">
	<div class="scos-card__header scos-card__header--plain">
		<h2 class="scos-card__title"><?php esc_html_e( 'Sitemap Stats', 'site-essentials' ); ?></h2>
	</div>
	<div class="scos-card__body">

		<div class="scos-notice scos-notice--info" style="margin-bottom:var(--scos-s-4)">
			<p>
				<strong><?php esc_html_e( 'Your Sitemap URL:', 'site-essentials' ); ?></strong>
				<a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank">
					<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>
				</a><br>
				<small><?php esc_html_e( 'Submit this URL to Google Search Console for indexing.', 'site-essentials' ); ?></small>
			</p>
		</div>

		<table class="scos-form">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Total URLs', 'site-essentials' ); ?></th>
					<td><?php echo number_format( $sitemap_stats['total_urls'] ); ?></td>
				</tr>
				<?php foreach ( $sitemap_stats['post_types'] as $data ) : ?>
				<tr>
					<th style="padding-left:var(--scos-s-5)">↳ <?php echo esc_html( $data['label'] ); ?></th>
					<td><?php echo number_format( $data['count'] ); ?></td>
				</tr>
				<?php endforeach; ?>
				<?php foreach ( $sitemap_stats['taxonomies'] as $data ) : ?>
				<tr>
					<th style="padding-left:var(--scos-s-5)">↳ <?php echo esc_html( $data['label'] ); ?></th>
					<td><?php echo number_format( $data['count'] ); ?></td>
				</tr>
				<?php endforeach; ?>
				<tr>
					<th><?php esc_html_e( 'Last Generated', 'site-essentials' ); ?></th>
					<td>
						<?php echo $cache_age
							? esc_html( $cache_age ) . ' ' . esc_html__( 'ago', 'site-essentials' )
							: esc_html__( 'Never', 'site-essentials' ); ?>
					</td>
				</tr>
			</tbody>
		</table>

	</div>
</div>

<!-- ── Cards 2 + 3 share a single form ───────────────────────────── -->
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="scos-sitemaps-form">
	<?php wp_nonce_field( 'site_essentials_seo', 'site_essentials_seo_nonce' ); ?>
	<input type="hidden" name="action" value="site_essentials_save_seo">

	<!-- ── Card 2: XML Sitemaps ───────────────────────────────────── -->
	<div class="scos-card" style="margin-bottom:var(--scos-s-4)">
		<div class="scos-card__header">
			<div>
				<h2 class="scos-card__title"><?php esc_html_e( 'XML Sitemaps', 'site-essentials' ); ?></h2>
				<p class="scos-card__desc"><?php esc_html_e( 'Configure which content is included in your XML sitemap.', 'site-essentials' ); ?></p>
			</div>
		</div>
		<div class="scos-card__body">
			<table class="scos-form">
				<tbody>

					<tr>
						<th>
							<label for="sitemap_enabled"><?php esc_html_e( 'Enable XML Sitemaps', 'site-essentials' ); ?></label>
						</th>
						<td>
							<label class="scos-checkbox-row">
								<input type="checkbox"
								       id="sitemap_enabled"
								       name="sitemap[enabled]"
								       value="1"
								       <?php checked( ! empty( $sitemap_settings['enabled'] ) ); ?>>
								<span><?php esc_html_e( 'Automatically generate XML sitemaps for your site content.', 'site-essentials' ); ?></span>
							</label>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e( 'Include Post Types', 'site-essentials' ); ?></th>
						<td>
							<?php foreach ( $all_post_types as $post_type => $post_type_obj ) : ?>
								<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-1)">
									<input type="checkbox"
									       name="sitemap[post_types][]"
									       value="<?php echo esc_attr( $post_type ); ?>"
									       <?php checked( in_array( $post_type, $sitemap_settings['post_types'], true ) ); ?>>
									<span>
										<?php echo esc_html( $post_type_obj->labels->name ); ?>
										<small style="color:var(--scos-ink-subtle)">(<?php echo esc_html( $post_type ); ?>)</small>
									</span>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Posts & Pages are ON by default.', 'site-essentials' ); ?></p>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e( 'Include Taxonomies', 'site-essentials' ); ?></th>
						<td>
							<?php foreach ( $all_taxonomies as $taxonomy => $taxonomy_obj ) : ?>
								<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-1)">
									<input type="checkbox"
									       name="sitemap[taxonomies][]"
									       value="<?php echo esc_attr( $taxonomy ); ?>"
									       <?php checked( in_array( $taxonomy, ! empty( $sitemap_settings['taxonomies'] ) ? $sitemap_settings['taxonomies'] : [], true ) ); ?>>
									<span>
										<?php echo esc_html( $taxonomy_obj->labels->name ); ?>
										<small style="color:var(--scos-ink-subtle)">(<?php echo esc_html( $taxonomy ); ?>)</small>
									</span>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Categories are ON by default. Empty terms are automatically excluded.', 'site-essentials' ); ?></p>
						</td>
					</tr>

					<tr>
						<th>
							<label for="sitemap_include_images"><?php esc_html_e( 'Include Images', 'site-essentials' ); ?></label>
						</th>
						<td>
							<label class="scos-checkbox-row">
								<input type="checkbox"
								       id="sitemap_include_images"
								       name="sitemap[include_images]"
								       value="1"
								       <?php checked( ! empty( $sitemap_settings['include_images'] ) ); ?>>
								<span><?php esc_html_e( 'Add featured and content images to sitemap (image sitemap).', 'site-essentials' ); ?></span>
							</label>
						</td>
					</tr>

					<tr>
						<th>
							<label for="sitemap_entries"><?php esc_html_e( 'Entries Per Sitemap', 'site-essentials' ); ?></label>
							<div class="scos-form__slug">sitemap[entries_per_sitemap]</div>
						</th>
						<td>
							<input type="number"
							       id="sitemap_entries"
							       name="sitemap[entries_per_sitemap]"
							       value="<?php echo esc_attr( $sitemap_settings['entries_per_sitemap'] ); ?>"
							       min="100" max="50000" step="100"
							       class="scos-input" style="max-width:120px">
							<p class="description"><?php esc_html_e( 'Max URLs per sitemap file. Google recommends 50,000 max. Default: 2000.', 'site-essentials' ); ?></p>
						</td>
					</tr>

					<tr>
						<th>
							<label for="sitemap_exclude_ids"><?php esc_html_e( 'Exclude Post IDs', 'site-essentials' ); ?></label>
							<div class="scos-form__slug">sitemap[exclude_ids]</div>
						</th>
						<td>
							<input type="text"
							       id="sitemap_exclude_ids"
							       name="sitemap[exclude_ids]"
							       value="<?php echo esc_attr( implode( ',', $sitemap_settings['exclude_ids'] ) ); ?>"
							       class="scos-input"
							       placeholder="123,456,789">
							<p class="description"><?php esc_html_e( 'Comma-separated post IDs to exclude from HTML & XML sitemaps.', 'site-essentials' ); ?></p>
						</td>
					</tr>

				</tbody>
			</table>
		</div>
	</div>

	<!-- ── Card 3: HTML Sitemap ───────────────────────────────────── -->
	<div class="scos-card">
		<div class="scos-card__header">
			<div>
				<h2 class="scos-card__title"><?php esc_html_e( 'HTML Sitemap', 'site-essentials' ); ?></h2>
				<p class="scos-card__desc"><?php esc_html_e( 'A user-friendly sitemap page via shortcode, grouped by post type with published and updated dates.', 'site-essentials' ); ?></p>
			</div>
		</div>
		<div class="scos-card__body">
			<table class="scos-form">
				<tbody>
					<tr>
						<th>
							<label for="html_sitemap_enabled"><?php esc_html_e( 'Enable HTML Sitemap', 'site-essentials' ); ?></label>
						</th>
						<td>
							<label class="scos-checkbox-row">
								<input type="checkbox"
								       id="html_sitemap_enabled"
								       name="sitemap[html_sitemap_enabled]"
								       value="1"
								       <?php checked( ! empty( $sitemap_settings['html_sitemap_enabled'] ) ); ?>>
								<span><?php esc_html_e( 'Enable HTML sitemap with shortcode', 'site-essentials' ); ?></span>
							</label>
						</td>
					</tr>
					<?php if ( ! empty( $sitemap_settings['html_sitemap_enabled'] ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'Shortcode', 'site-essentials' ); ?></th>
						<td>
							<div style="display:flex;gap:var(--scos-s-2);align-items:center;max-width:360px">
								<input type="text"
								       id="se-html-sitemap-shortcode"
								       value="[site_essentials_sitemap]"
								       readonly
								       class="scos-input scos-input--mono"
								       onclick="this.select()">
								<button type="button" class="scos-btn scos-btn--ghost" id="se-copy-shortcode" style="white-space:nowrap">
									<?php esc_html_e( 'Copy', 'site-essentials' ); ?>
								</button>
							</div>
							<p class="description"><?php esc_html_e( 'Paste this shortcode on any page to display the HTML sitemap.', 'site-essentials' ); ?></p>
						</td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<div class="scos-card__footer">
			<button type="submit" class="scos-btn scos-btn--primary">
				<?php esc_html_e( 'Save SEO Settings', 'site-essentials' ); ?>
			</button>
			<button type="button" class="scos-btn scos-btn--ghost" id="se-clear-sitemap-cache">
				<?php esc_html_e( 'Clear Sitemap Cache', 'site-essentials' ); ?>
			</button>
		</div>
	</div>

</form>

<script>
jQuery(document).ready(function($) {
	$('#se-copy-shortcode').on('click', function() {
		var $input = $('#se-html-sitemap-shortcode');
		$input.select();
		document.execCommand('copy');
		var $btn = $(this);
		var orig = $btn.text();
		$btn.text('<?php echo esc_js( __( 'Copied!', 'site-essentials' ) ); ?>');
		setTimeout(function() { $btn.text(orig); }, 2000);
	});

	$('#se-clear-sitemap-cache').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'Clear all sitemap caches? Sitemaps will be regenerated on next request.', 'site-essentials' ) ); ?>')) {
			return;
		}
		var $button = $(this);
		$button.prop('disabled', true).text('<?php echo esc_js( __( 'Clearing…', 'site-essentials' ) ); ?>');
		$.ajax({
			url: siteEssentials.ajaxurl,
			type: 'POST',
			data: {
				action: 'site_essentials_clear_sitemap_cache',
				nonce: siteEssentials.nonce
			},
			success: function(response) {
				if (response.success) {
					alert('<?php echo esc_js( __( 'Sitemap cache cleared successfully!', 'site-essentials' ) ); ?>');
				} else {
					alert('<?php echo esc_js( __( 'Failed to clear cache: ', 'site-essentials' ) ); ?>' + (response.data.message || '<?php echo esc_js( __( 'Unknown error', 'site-essentials' ) ); ?>'));
				}
			},
			error: function() {
				alert('<?php echo esc_js( __( 'Failed to communicate with server', 'site-essentials' ) ); ?>');
			},
			complete: function() {
				$button.prop('disabled', false).text('<?php echo esc_js( __( 'Clear Sitemap Cache', 'site-essentials' ) ); ?>');
			}
		});
	});
});
</script>
