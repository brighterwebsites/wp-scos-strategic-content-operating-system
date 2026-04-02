<?php
/**
 * SEO Meta — Archive Settings view
 *
 * Rendered inside the "Meta Tags" tab of Site Essentials > SEO.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\Views
 */

use SiteEssentials\Modules\SeoMeta\Archive_Settings;

defined( 'ABSPATH' ) || exit;

$archives = Archive_Settings::get_archives();

if ( isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
	echo '<div class="notice notice-success is-dismissible"><p>' .
	     esc_html__( 'Archive SEO settings saved.', 'site-essentials' ) .
	     '</p></div>';
}
?>

<div class="scos-archive-meta-wrap">

	<!-- Token cheat-sheet ──────────────────────────────────────────────── -->
	<div class="card se-module-settings-card" style="margin-bottom: 20px;">
		<h2 class="title"><?php esc_html_e( 'Template Tokens', 'site-essentials' ); ?></h2>
		<p><?php esc_html_e( 'Use these tokens in Meta Title and Meta Description fields. They are replaced with live values at render time.', 'site-essentials' ); ?></p>
		<table class="widefat striped" style="max-width: 600px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Token', 'site-essentials' ); ?></th>
					<th><?php esc_html_e( 'Resolves to', 'site-essentials' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr><td><code>%title%</code></td><td><?php esc_html_e( 'Archive name — post type plural label or blog page title', 'site-essentials' ); ?></td></tr>
				<tr><td><code>%sitename%</code></td><td><?php esc_html_e( 'Site name from Settings > General', 'site-essentials' ); ?></td></tr>
				<tr><td><code>%sep%</code></td><td><?php esc_html_e( 'Separator character (default –, filterable via scos_seo_title_sep)', 'site-essentials' ); ?></td></tr>
				<tr><td><code>%page%</code></td><td><?php esc_html_e( '"Page 2" on paginated views, blank on page 1', 'site-essentials' ); ?></td></tr>
			</tbody>
		</table>
		<p style="margin-top: 10px; color: #666;">
			<?php esc_html_e( 'Example title:', 'site-essentials' ); ?>
			<code>%title% %sep% %sitename%</code>
			<?php esc_html_e( '→', 'site-essentials' ); ?>
			<?php echo esc_html( sprintf( '%s – %s', __( 'Posts (Blog)', 'site-essentials' ), get_bloginfo( 'name' ) ) ); ?>
		</p>
	</div>

	<!-- Per-archive settings form ─────────────────────────────────────── -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="site_essentials_save_archive_meta">
		<?php wp_nonce_field( 'scos_save_archive_meta', 'scos_archive_meta_nonce' ); ?>

		<?php foreach ( $archives as $slug => $label ) :
			$s = Archive_Settings::get( $slug );
			$f = 'scos_archive[' . esc_attr( $slug ) . ']';
		?>
		<div class="card se-module-settings-card" style="margin-bottom: 20px;">

			<h2 class="title">
				<?php echo esc_html( $label ); ?>
				<small style="font-size: 13px; font-weight: 400; color: #666; margin-left: 8px;">
					<?php echo esc_html( 'post' === $slug
						? __( 'is_home()', 'site-essentials' )
						: 'is_post_type_archive(\'' . $slug . '\')' ); ?>
				</small>
			</h2>

			<!-- ── Title & Description ──────────────────────────────────── -->
			<table class="form-table" role="presentation">

				<tr>
					<th scope="row">
						<label for="scos-<?php echo esc_attr( $slug ); ?>-title">
							<?php esc_html_e( 'Meta Title', 'site-essentials' ); ?>
						</label>
					</th>
					<td>
						<input type="text"
							id="scos-<?php echo esc_attr( $slug ); ?>-title"
							name="<?php echo esc_attr( $f ); ?>[title]"
							value="<?php echo esc_attr( $s['title'] ); ?>"
							class="regular-text"
							placeholder="<?php echo esc_attr( '%title% %sep% %sitename%' ); ?>"
							maxlength="120">
						<p class="description"><?php esc_html_e( 'Supports tokens. Leave blank to use WordPress default. Aim for 50–60 characters.', 'site-essentials' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="scos-<?php echo esc_attr( $slug ); ?>-description">
							<?php esc_html_e( 'Meta Description', 'site-essentials' ); ?>
						</label>
					</th>
					<td>
						<textarea
							id="scos-<?php echo esc_attr( $slug ); ?>-description"
							name="<?php echo esc_attr( $f ); ?>[description]"
							rows="3"
							class="large-text"
							maxlength="320"><?php echo esc_textarea( $s['description'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Supports tokens. Leave blank to omit the meta description tag. Aim for 140–160 characters.', 'site-essentials' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="scos-<?php echo esc_attr( $slug ); ?>-breadcrumb">
							<?php esc_html_e( 'Breadcrumb Title', 'site-essentials' ); ?>
						</label>
					</th>
					<td>
						<input type="text"
							id="scos-<?php echo esc_attr( $slug ); ?>-breadcrumb"
							name="<?php echo esc_attr( $f ); ?>[breadcrumb_title]"
							value="<?php echo esc_attr( $s['breadcrumb_title'] ); ?>"
							class="regular-text">
						<p class="description"><?php esc_html_e( 'Short label used in breadcrumb navigation for this archive.', 'site-essentials' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="scos-<?php echo esc_attr( $slug ); ?>-tldr">
							<?php esc_html_e( 'TLDR Summary', 'site-essentials' ); ?>
						</label>
					</th>
					<td>
						<textarea
							id="scos-<?php echo esc_attr( $slug ); ?>-tldr"
							name="<?php echo esc_attr( $f ); ?>[tldr]"
							rows="2"
							class="large-text"><?php echo esc_textarea( $s['tldr'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One-sentence summary of this archive for internal reference and potential schema use.', 'site-essentials' ); ?></p>
					</td>
				</tr>

			</table>

			<!-- ── Robots ───────────────────────────────────────────────── -->
			<h3 style="margin: 16px 0 8px;"><?php esc_html_e( 'Meta Robots', 'site-essentials' ); ?></h3>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Meta Robots', 'site-essentials' ); ?></legend>
				<?php
				$robots_opts = [
					'noindex'      => __( 'noindex — Exclude from search engine indexes', 'site-essentials' ),
					'nofollow'     => __( 'nofollow — Do not follow links on this page', 'site-essentials' ),
					'noimageindex' => __( 'noimageindex — Exclude images from image search', 'site-essentials' ),
					'nosnippet'    => __( 'nosnippet — Do not show a text snippet in results', 'site-essentials' ),
				];
				foreach ( $robots_opts as $val => $label_text ) :
					$checked = in_array( $val, (array) $s['robots'], true );
				?>
				<label style="display: block; margin-bottom: 4px;">
					<input type="checkbox"
						name="<?php echo esc_attr( $f ); ?>[robots][]"
						value="<?php echo esc_attr( $val ); ?>"
						<?php checked( $checked ); ?>>
					<?php echo esc_html( $label_text ); ?>
				</label>
				<?php endforeach; ?>
			</fieldset>

			<!-- ── Sitemap Exclusion ────────────────────────────────────── -->
			<h3 style="margin: 16px 0 8px;"><?php esc_html_e( 'Sitemap Visibility', 'site-essentials' ); ?></h3>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Sitemap Visibility', 'site-essentials' ); ?></legend>
				<?php
				$sitemap_opts = [
					'xml'  => __( 'Exclude from XML Sitemap', 'site-essentials' ),
					'html' => __( 'Exclude from HTML Sitemap', 'site-essentials' ),
					'news' => __( 'Exclude from Google News Sitemap', 'site-essentials' ),
				];
				foreach ( $sitemap_opts as $val => $label_text ) :
					$checked = in_array( $val, (array) $s['sitemap_exclude'], true );
				?>
				<label style="display: block; margin-bottom: 4px;">
					<input type="checkbox"
						name="<?php echo esc_attr( $f ); ?>[sitemap_exclude][]"
						value="<?php echo esc_attr( $val ); ?>"
						<?php checked( $checked ); ?>>
					<?php echo esc_html( $label_text ); ?>
				</label>
				<?php endforeach; ?>
			</fieldset>

			<!-- ── OG Image ─────────────────────────────────────────────── -->
			<h3 style="margin: 16px 0 8px;"><?php esc_html_e( 'OG / Social Image', 'site-essentials' ); ?></h3>
			<div class="scos-og-image-field" style="display: flex; align-items: flex-start; gap: 12px;">
				<?php
				$og_id  = (int) $s['og_image_id'];
				$og_src = $og_id ? wp_get_attachment_image_url( $og_id, [ 120, 63 ] ) : '';
				?>
				<?php if ( $og_src ) : ?>
				<div class="scos-og-image-preview">
					<img src="<?php echo esc_url( $og_src ); ?>" alt="" width="120" height="63"
						style="width:120px;height:63px;object-fit:cover;border:1px solid #ddd;border-radius:3px;">
				</div>
				<?php endif; ?>
				<div>
					<input type="hidden"
						id="scos-<?php echo esc_attr( $slug ); ?>-og-image-id"
						name="<?php echo esc_attr( $f ); ?>[og_image_id]"
						value="<?php echo esc_attr( (string) $og_id ); ?>">
					<button type="button"
						class="button scos-og-image-select"
						data-target="scos-<?php echo esc_attr( $slug ); ?>-og-image-id"
						data-preview="scos-<?php echo esc_attr( $slug ); ?>-og-image-preview">
						<?php echo $og_id ? esc_html__( 'Change Image', 'site-essentials' ) : esc_html__( 'Select Image', 'site-essentials' ); ?>
					</button>
					<?php if ( $og_id ) : ?>
					<button type="button"
						class="button scos-og-image-remove"
						data-target="scos-<?php echo esc_attr( $slug ); ?>-og-image-id"
						style="margin-left: 4px;">
						<?php esc_html_e( 'Remove', 'site-essentials' ); ?>
					</button>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Recommended: 1200×630 px. Used as og:image for this archive on social shares.', 'site-essentials' ); ?></p>
				</div>
			</div>

			<!-- ── Pagination settings ──────────────────────────────────── -->
			<h3 style="margin: 16px 0 8px;"><?php esc_html_e( 'Pagination', 'site-essentials' ); ?></h3>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Pagination settings', 'site-essentials' ); ?></legend>

				<label style="display: block; margin-bottom: 8px;">
					<input type="checkbox"
						name="<?php echo esc_attr( $f ); ?>[pagination_noindex]"
						value="1"
						<?php checked( ! empty( $s['pagination_noindex'] ) ); ?>>
					<?php esc_html_e( 'noindex on paginated pages (e.g. /page/2/)', 'site-essentials' ); ?>
				</label>

				<label style="display: block; margin-bottom: 8px;">
					<input type="checkbox"
						name="<?php echo esc_attr( $f ); ?>[canonical_paged]"
						value="1"
						<?php checked( ! empty( $s['canonical_paged'] ) ); ?>>
					<?php esc_html_e( 'Self-canonical on paginated pages (page/2 canonical → page/2, not archive root)', 'site-essentials' ); ?>
				</label>

				<label style="display: block; margin-bottom: 4px;">
					<input type="checkbox"
						name="<?php echo esc_attr( $f ); ?>[rel_prevnext]"
						value="1"
						<?php checked( ! empty( $s['rel_prevnext'] ) ); ?>>
					<?php esc_html_e( 'Output rel="prev" / rel="next" links in <head> for paginated archives', 'site-essentials' ); ?>
				</label>
			</fieldset>

		</div><!-- /.card -->
		<?php endforeach; ?>

		<?php submit_button( __( 'Save Archive SEO Settings', 'site-essentials' ) ); ?>

	</form>
</div><!-- /.scos-archive-meta-wrap -->

<?php
// Enqueue WP media library for the OG image picker
wp_enqueue_media();
?>
<script>
(function ($) {
	'use strict';
	if (typeof wp === 'undefined' || !wp.media) { return; }

	$(document).on('click', '.scos-og-image-select', function () {
		var btn      = $(this);
		var targetId = btn.data('target');
		var frame    = wp.media({
			title:    '<?php echo esc_js( __( 'Select OG Image', 'site-essentials' ) ); ?>',
			button:   { text: '<?php echo esc_js( __( 'Use this image', 'site-essentials' ) ); ?>' },
			library:  { type: 'image' },
			multiple: false
		});

		frame.on('select', function () {
			var att = frame.state().get('selection').first().toJSON();
			$('#' + targetId).val(att.id);

			var preview = btn.siblings('.scos-og-image-preview');
			if (preview.length) {
				preview.find('img').attr('src', att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url);
			} else {
				var img = $('<div class="scos-og-image-preview"><img style="width:120px;height:63px;object-fit:cover;border:1px solid #ddd;border-radius:3px;" /></div>');
				img.find('img').attr('src', att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url);
				btn.before(img);
			}

			btn.text('<?php echo esc_js( __( 'Change Image', 'site-essentials' ) ); ?>');
			if (!btn.siblings('.scos-og-image-remove').length) {
				btn.after('<button type="button" class="button scos-og-image-remove" data-target="' + targetId + '" style="margin-left:4px;"><?php echo esc_js( __( 'Remove', 'site-essentials' ) ); ?></button>');
			}
		});

		frame.open();
	});

	$(document).on('click', '.scos-og-image-remove', function () {
		var targetId = $(this).data('target');
		$('#' + targetId).val('');
		$(this).siblings('.scos-og-image-preview').remove();
		$(this).siblings('.scos-og-image-select').text('<?php echo esc_js( __( 'Select Image', 'site-essentials' ) ); ?>');
		$(this).remove();
	});
}(jQuery));
</script>
