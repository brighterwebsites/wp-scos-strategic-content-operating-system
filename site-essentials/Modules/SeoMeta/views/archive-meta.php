<?php
/**
 * SEO Meta — Archive Settings view
 *
 * v1.1 | 2026-05-19
 *
 * SCOS design system: scos-accordion replaces <details>, scos-form inside bodies,
 * scos-badge for status, scos-save-bar, scos__section-label. Inline <style> removed.
 * No functional changes — form action, field names, nonce, and jQuery OG picker unchanged.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\Views
 */

use SiteEssentials\Modules\SeoMeta\Archive_Settings;

defined( 'ABSPATH' ) || exit;

$archives = Archive_Settings::get_archives();

if ( isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
	echo '<div class="scos-notice scos-notice--success" style="margin-bottom:var(--scos-s-4)"><p>'
	   . esc_html__( 'Archive SEO settings saved.', 'site-essentials' )
	   . '</p></div>';
}

function scos_archive_has_data( array $s ): bool {
	return ! empty( $s['title'] )
	    || ! empty( $s['description'] )
	    || ! empty( $s['breadcrumb_title'] )
	    || ! empty( $s['tldr'] )
	    || ! empty( $s['robots'] )
	    || ! empty( $s['og_image_id'] );
}

$chevron_svg = '<svg class="scos-accordion__chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>';
?>

<!-- ── Token cheat-sheet ─────────────────────────────────────────── -->
<div class="scos-accordion" style="margin-bottom:var(--scos-s-4)">
	<div class="scos-accordion__item">
		<button type="button" class="scos-accordion__trigger" aria-expanded="false" aria-controls="scos-archive-tokens">
			<span class="scos-accordion__label">
				<span class="scos-accordion__title"><?php esc_html_e( 'Template Tokens', 'site-essentials' ); ?></span>
				<span class="scos-accordion__hint"><?php esc_html_e( 'Use in Meta Title and Meta Description fields', 'site-essentials' ); ?></span>
			</span>
			<?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</button>
		<div class="scos-accordion__body" id="scos-archive-tokens">
			<table class="scos-form" style="max-width:640px">
				<tbody>
					<tr><th><code>%title%</code></th><td><?php esc_html_e( 'Archive name — CPT plural label, term name, author name, or blog page title', 'site-essentials' ); ?></td></tr>
					<tr><th><code>%sitename%</code></th><td><?php esc_html_e( 'Site name from Settings › General', 'site-essentials' ); ?></td></tr>
					<tr><th><code>%sep%</code></th><td><?php esc_html_e( 'Separator (default –, filterable via scos_seo_title_sep)', 'site-essentials' ); ?></td></tr>
					<tr><th><code>%page%</code></th><td><?php esc_html_e( '"Page 2" on paginated views, blank on page 1', 'site-essentials' ); ?></td></tr>
					<tr><th><code>%term%</code></th><td><?php esc_html_e( 'Taxonomy term name (alias for %title% on term archives)', 'site-essentials' ); ?></td></tr>
					<tr><th><code>%taxonomy%</code></th><td><?php esc_html_e( 'Taxonomy plural label (e.g. "Categories", "Tags")', 'site-essentials' ); ?></td></tr>
					<tr><th><code>%description%</code></th><td><?php esc_html_e( 'Term description — the description set on the taxonomy term edit screen', 'site-essentials' ); ?></td></tr>
					<tr><th><code>%author%</code></th><td><?php esc_html_e( 'Author display name (author archives)', 'site-essentials' ); ?></td></tr>
					<tr><th><code>%search%</code></th><td><?php esc_html_e( 'Search query string (search results archives)', 'site-essentials' ); ?></td></tr>
				</tbody>
			</table>
			<p class="description" style="margin-top:var(--scos-s-3)">
				<?php esc_html_e( 'Example:', 'site-essentials' ); ?>
				<code>%title% %sep% %sitename%</code>
				&rarr;
				<em><?php echo esc_html( sprintf( '%s – %s', __( 'Posts (Blog)', 'site-essentials' ), get_bloginfo( 'name' ) ) ); ?></em>
			</p>
		</div>
	</div>
</div>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="site_essentials_save_archive_meta">
	<?php wp_nonce_field( 'scos_save_archive_meta', 'scos_archive_meta_nonce' ); ?>

	<!-- ══ Global SEO Defaults ═════════════════════════════════════════ -->
	<h2 class="scos__section-label"><?php esc_html_e( 'Global SEO Defaults', 'site-essentials' ); ?></h2>

	<div class="scos-accordion" style="margin-bottom:var(--scos-s-4)">
		<?php
		$freeze_on = get_option( 'scos_seo_freeze_modified_date' );
		$item_open = $freeze_on ? ' is-open' : '';
		?>
		<div class="scos-accordion__item<?php echo esc_attr( $item_open ); ?>">
			<button type="button" class="scos-accordion__trigger" aria-expanded="<?php echo $freeze_on ? 'true' : 'false'; ?>" aria-controls="scos-archive-modified-date">
				<span class="scos-accordion__label">
					<span class="scos-accordion__title"><?php esc_html_e( 'Modified Date', 'site-essentials' ); ?></span>
					<span class="scos-accordion__hint"><?php esc_html_e( 'Control how post_modified updates site-wide', 'site-essentials' ); ?></span>
				</span>
				<?php if ( $freeze_on ) : ?>
					<span class="scos-badge scos-badge--success"><?php esc_html_e( 'active', 'site-essentials' ); ?></span>
				<?php endif; ?>
				<?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</button>
			<div class="scos-accordion__body" id="scos-archive-modified-date">
				<label class="scos-checkbox-row" style="cursor:pointer">
					<input type="checkbox"
						name="scos_global[freeze_modified_date]"
						value="1"
						<?php checked( $freeze_on ); ?>>
					<span>
						<strong><?php esc_html_e( 'Freeze modified date globally', 'site-essentials' ); ?></strong>
						<span class="description" style="display:block;margin-top:2px"><?php esc_html_e( 'When enabled, saving any post will not update its "Last Modified" timestamp unless the post has the per-post freeze unchecked. Useful for minor edits, copy fixes, or SEO tweaks. Individual posts can override via the SEO metabox → Advanced tab.', 'site-essentials' ); ?></span>
					</span>
				</label>
			</div>
		</div>
	</div>

	<!-- ══ Post Type Archives ══════════════════════════════════════════ -->
	<h2 class="scos__section-label"><?php esc_html_e( 'Post Type Archives', 'site-essentials' ); ?></h2>

	<div class="scos-accordion" style="margin-bottom:var(--scos-s-4)">
	<?php
	foreach ( $archives as $slug => $label ) :
		$s               = Archive_Settings::get( $slug );
		$f               = 'scos_archive[' . esc_attr( $slug ) . ']';
		$has_archive_url = ( 'post' === $slug )
			? ( (int) get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' ) )
			: get_post_type_archive_link( $slug );
		$item_id = 'scos-archive-pt-' . esc_attr( $slug );
	?>
		<div class="scos-accordion__item">
			<button type="button" class="scos-accordion__trigger" aria-expanded="false" aria-controls="<?php echo esc_attr( $item_id ); ?>">
				<span class="scos-accordion__label">
					<span class="scos-accordion__title">
						<?php echo esc_html( $label ); ?>
						<span class="scos-accordion__hint">
							<?php echo esc_html( 'post' === $slug ? 'is_home()' : 'is_post_type_archive(\'' . $slug . '\')' ); ?>
						</span>
					</span>
				</span>
				<?php if ( scos_archive_has_data( $s ) ) : ?>
					<span class="scos-badge scos-badge--success"><?php esc_html_e( 'configured', 'site-essentials' ); ?></span>
				<?php elseif ( $has_archive_url ) : ?>
					<span class="scos-badge scos-badge--soft"><?php esc_html_e( 'has archive', 'site-essentials' ); ?></span>
				<?php endif; ?>
				<?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</button>

			<div class="scos-accordion__body" id="<?php echo esc_attr( $item_id ); ?>">

				<?php if ( $has_archive_url ) : ?>
				<p class="description" style="margin-bottom:var(--scos-s-4)">
					<?php esc_html_e( 'Archive URL:', 'site-essentials' ); ?>
					<a href="<?php echo esc_url( $has_archive_url ); ?>" target="_blank"><?php echo esc_html( $has_archive_url ); ?></a>
				</p>
				<?php else : ?>
				<p class="description" style="margin-bottom:var(--scos-s-4)"><?php esc_html_e( 'No archive URL — this post type does not currently have has_archive enabled. Settings will apply when an archive is enabled.', 'site-essentials' ); ?></p>
				<?php endif; ?>

				<h3 class="scos__section-label" style="font-size:var(--scos-fs-sm);margin-bottom:var(--scos-s-2)"><?php esc_html_e( 'Meta & SEO', 'site-essentials' ); ?></h3>
				<table class="scos-form" style="margin-bottom:var(--scos-s-4)">
					<tbody>
						<tr>
							<th>
								<label for="scos-<?php echo esc_attr( $slug ); ?>-title"><?php esc_html_e( 'Meta Title', 'site-essentials' ); ?></label>
							</th>
							<td>
								<input type="text" id="scos-<?php echo esc_attr( $slug ); ?>-title"
									name="<?php echo esc_attr( $f ); ?>[title]"
									value="<?php echo esc_attr( $s['title'] ); ?>"
									class="scos-input" placeholder="%title% %sep% %sitename%" maxlength="120">
								<p class="description"><?php esc_html_e( 'Supports tokens. Leave blank to use the WordPress default. Aim for 50–60 characters.', 'site-essentials' ); ?></p>
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos-<?php echo esc_attr( $slug ); ?>-description"><?php esc_html_e( 'Meta Description', 'site-essentials' ); ?></label>
							</th>
							<td>
								<textarea id="scos-<?php echo esc_attr( $slug ); ?>-description"
									name="<?php echo esc_attr( $f ); ?>[description]"
									rows="3" class="scos-input"
									maxlength="320"><?php echo esc_textarea( $s['description'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Supports tokens. Leave blank to omit. Aim for 140–160 characters.', 'site-essentials' ); ?></p>
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos-<?php echo esc_attr( $slug ); ?>-breadcrumb"><?php esc_html_e( 'Breadcrumb Title', 'site-essentials' ); ?></label>
							</th>
							<td>
								<input type="text" id="scos-<?php echo esc_attr( $slug ); ?>-breadcrumb"
									name="<?php echo esc_attr( $f ); ?>[breadcrumb_title]"
									value="<?php echo esc_attr( $s['breadcrumb_title'] ); ?>"
									class="scos-input">
								<p class="description"><?php esc_html_e( 'Short label used in breadcrumb navigation.', 'site-essentials' ); ?></p>
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos-<?php echo esc_attr( $slug ); ?>-tldr"><?php esc_html_e( 'TLDR Summary', 'site-essentials' ); ?></label>
							</th>
							<td>
								<textarea id="scos-<?php echo esc_attr( $slug ); ?>-tldr"
									name="<?php echo esc_attr( $f ); ?>[tldr]"
									rows="2" class="scos-input"><?php echo esc_textarea( $s['tldr'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One-sentence summary for internal reference and potential schema use.', 'site-essentials' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<h3 class="scos__section-label" style="font-size:var(--scos-fs-sm);margin-bottom:var(--scos-s-2)"><?php esc_html_e( 'Meta Robots', 'site-essentials' ); ?></h3>
				<fieldset style="margin-bottom:var(--scos-s-4)">
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
					<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-1)">
						<input type="checkbox"
							name="<?php echo esc_attr( $f ); ?>[robots][]"
							value="<?php echo esc_attr( $val ); ?>"
							<?php checked( $checked ); ?>>
						<span><?php echo esc_html( $label_text ); ?></span>
					</label>
					<?php endforeach; ?>
				</fieldset>

				<h3 class="scos__section-label" style="font-size:var(--scos-fs-sm);margin-bottom:var(--scos-s-2)"><?php esc_html_e( 'Sitemap Visibility', 'site-essentials' ); ?></h3>
				<fieldset style="margin-bottom:var(--scos-s-4)">
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
					<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-1)">
						<input type="checkbox"
							name="<?php echo esc_attr( $f ); ?>[sitemap_exclude][]"
							value="<?php echo esc_attr( $val ); ?>"
							<?php checked( $checked ); ?>>
						<span><?php echo esc_html( $label_text ); ?></span>
					</label>
					<?php endforeach; ?>
				</fieldset>

				<h3 class="scos__section-label" style="font-size:var(--scos-fs-sm);margin-bottom:var(--scos-s-2)"><?php esc_html_e( 'Social / OG Image', 'site-essentials' ); ?></h3>
				<?php
				$og_id  = (int) $s['og_image_id'];
				$og_src = $og_id ? wp_get_attachment_image_url( $og_id, [ 240, 126 ] ) : '';
				$input_id = 'scos-' . esc_attr( $slug ) . '-og-id';
				$thumb_id = 'scos-' . esc_attr( $slug ) . '-og-thumb';
				?>
				<div class="scos-og-wrap" style="margin-bottom:var(--scos-s-4)">
					<div class="scos-og-thumb-box<?php echo $og_src ? ' has-image' : ''; ?>" id="<?php echo esc_attr( $thumb_id ); ?>"
					     style="width:240px;height:126px;border:2px dashed var(--scos-border-strong);border-radius:var(--scos-r-md);display:flex;align-items:center;justify-content:center;overflow:hidden;background:var(--scos-surface-muted);color:var(--scos-ink-subtle);font-size:var(--scos-fs-sm);text-align:center;padding:var(--scos-s-2);margin-bottom:var(--scos-s-2)">
						<?php if ( $og_src ) : ?>
							<img src="<?php echo esc_url( $og_src ); ?>" alt="" style="width:100%;height:100%;object-fit:cover">
						<?php else : ?>
							<?php esc_html_e( 'Select your default thumbnail', 'site-essentials' ); ?>
						<?php endif; ?>
					</div>
					<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>"
						name="<?php echo esc_attr( $f ); ?>[og_image_id]"
						value="<?php echo esc_attr( (string) $og_id ); ?>">
					<div style="display:flex;gap:var(--scos-s-2);flex-wrap:wrap;margin-bottom:var(--scos-s-2)">
						<button type="button" class="scos-btn scos-btn--ghost scos-og-upload"
							data-input="<?php echo esc_attr( $input_id ); ?>"
							data-thumb="<?php echo esc_attr( $thumb_id ); ?>">
							<?php echo $og_id ? esc_html__( 'Change Image', 'site-essentials' ) : esc_html__( 'Upload an Image', 'site-essentials' ); ?>
						</button>
						<button type="button" class="scos-btn scos-btn--ghost scos-og-remove"
							data-input="<?php echo esc_attr( $input_id ); ?>"
							data-thumb="<?php echo esc_attr( $thumb_id ); ?>"
							style="<?php echo $og_id ? '' : 'display:none;'; ?>">
							<?php esc_html_e( 'Remove Image', 'site-essentials' ); ?>
						</button>
					</div>
					<p class="description"><?php esc_html_e( 'Recommended: 1200×630 px. Shown as og:image on social shares for this archive.', 'site-essentials' ); ?></p>
				</div>

				<h3 class="scos__section-label" style="font-size:var(--scos-fs-sm);margin-bottom:var(--scos-s-2)"><?php esc_html_e( 'Pagination', 'site-essentials' ); ?></h3>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Pagination settings', 'site-essentials' ); ?></legend>
					<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-1)">
						<input type="checkbox" name="<?php echo esc_attr( $f ); ?>[pagination_noindex]" value="1"
							<?php checked( ! empty( $s['pagination_noindex'] ) ); ?>>
						<span><?php esc_html_e( 'noindex on paginated pages (e.g. /page/2/)', 'site-essentials' ); ?></span>
					</label>
					<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-1)">
						<input type="checkbox" name="<?php echo esc_attr( $f ); ?>[canonical_paged]" value="1"
							<?php checked( ! empty( $s['canonical_paged'] ) ); ?>>
						<span><?php esc_html_e( 'Self-canonical on paginated pages (page/2 canonical → page/2, not archive root)', 'site-essentials' ); ?></span>
					</label>
					<label class="scos-checkbox-row">
						<input type="checkbox" name="<?php echo esc_attr( $f ); ?>[rel_prevnext]" value="1"
							<?php checked( ! empty( $s['rel_prevnext'] ) ); ?>>
						<span><?php esc_html_e( 'Output rel="prev" / rel="next" links in <head> for paginated archives', 'site-essentials' ); ?></span>
					</label>
				</fieldset>

			</div><!-- /.scos-accordion__body -->
		</div><!-- /.scos-accordion__item -->
	<?php endforeach; ?>
	</div><!-- /.scos-accordion (post types) -->

	<!-- ══ Special Archive Types ═══════════════════════════════════════ -->
	<h2 class="scos__section-label"><?php esc_html_e( 'Special Archive Types', 'site-essentials' ); ?></h2>
	<p class="description" style="margin-bottom:var(--scos-s-3)"><?php esc_html_e( 'Control SEO output and optionally disable author, date, and search archives with a 301 redirect to the URL of your choice.', 'site-essentials' ); ?></p>

	<div class="scos-accordion" style="margin-bottom:var(--scos-s-4)">
	<?php
	$special_archives = Archive_Settings::get_special_archives();
	foreach ( $special_archives as $slug => $label ) :
		$s           = Archive_Settings::get( $slug );
		$f           = 'scos_archive[' . esc_attr( $slug ) . ']';
		$has_disable = in_array( $slug, [ 'author', 'date', 'search' ], true );
		$is_disabled = $has_disable && ! empty( $s['disabled'] );
		$item_id     = 'scos-archive-special-' . esc_attr( $slug );
	?>
		<div class="scos-accordion__item">
			<button type="button" class="scos-accordion__trigger" aria-expanded="false" aria-controls="<?php echo esc_attr( $item_id ); ?>">
				<span class="scos-accordion__label">
					<span class="scos-accordion__title">
						<?php echo esc_html( $label ); ?>
						<span class="scos-accordion__hint">
							<?php echo esc_html( 'is_' . ( '404' === $slug ? '404' : $slug ) . '()' ); ?>
						</span>
					</span>
				</span>
				<?php if ( $is_disabled ) : ?>
					<span class="scos-badge scos-badge--danger"><?php esc_html_e( 'disabled – redirecting', 'site-essentials' ); ?></span>
				<?php elseif ( scos_archive_has_data( $s ) ) : ?>
					<span class="scos-badge scos-badge--success"><?php esc_html_e( 'configured', 'site-essentials' ); ?></span>
				<?php endif; ?>
				<?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</button>

			<div class="scos-accordion__body" id="<?php echo esc_attr( $item_id ); ?>">

				<?php if ( $has_disable ) : ?>
				<h3 class="scos__section-label" style="font-size:var(--scos-fs-sm);margin-bottom:var(--scos-s-2)"><?php esc_html_e( 'Archive Status', 'site-essentials' ); ?></h3>
				<?php
				$disable_labels = [
					'author' => __( 'Disable author archives — redirect all /author/ URLs', 'site-essentials' ),
					'date'   => __( 'Disable date archives — redirect all date archive URLs', 'site-essentials' ),
					'search' => __( 'Disable search results page — redirect all search queries', 'site-essentials' ),
				];
				?>
				<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-3)">
					<input type="checkbox" name="<?php echo esc_attr( $f ); ?>[disabled]" value="1" <?php checked( $is_disabled ); ?>>
					<span><?php echo esc_html( $disable_labels[ $slug ] ?? '' ); ?></span>
				</label>
				<table class="scos-form" style="margin-bottom:var(--scos-s-4)">
					<tbody>
						<tr>
							<th>
								<label for="scos-<?php echo esc_attr( $slug ); ?>-redirect"><?php esc_html_e( 'Redirect to URL', 'site-essentials' ); ?></label>
							</th>
							<td>
								<input type="url" id="scos-<?php echo esc_attr( $slug ); ?>-redirect"
									name="<?php echo esc_attr( $f ); ?>[redirect_url]"
									value="<?php echo esc_attr( $s['redirect_url'] ?? '' ); ?>"
									class="scos-input"
									placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
								<p class="description"><?php esc_html_e( 'Leave blank to redirect to the homepage. Only used when the archive is disabled above.', 'site-essentials' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php endif; ?>

				<?php if ( 'author' === $slug ) : ?>
				<h3 class="scos__section-label" style="font-size:var(--scos-fs-sm);margin-bottom:var(--scos-s-2)"><?php esc_html_e( 'Author Archive URL', 'site-essentials' ); ?></h3>
				<table class="scos-form" style="margin-bottom:var(--scos-s-4)">
					<tbody>
						<tr>
							<th>
								<label for="scos-author-slug"><?php esc_html_e( 'URL prefix', 'site-essentials' ); ?></label>
							</th>
							<td style="display:flex;align-items:center;gap:var(--scos-s-1);flex-wrap:wrap">
								<span style="color:var(--scos-ink-subtle)"><?php echo esc_html( rtrim( home_url( '/' ), '/' ) . '/' ); ?></span>
								<input type="text" id="scos-author-slug"
									name="<?php echo esc_attr( $f ); ?>[author_slug]"
									value="<?php echo esc_attr( $s['author_slug'] ?? '' ); ?>"
									class="scos-input" style="max-width:120px"
									placeholder="author">
								<span style="color:var(--scos-ink-subtle)">/username/</span>
								<p class="description" style="width:100%;margin-top:var(--scos-s-1)"><?php esc_html_e( 'Changes /author/ to a custom prefix (e.g. team). Leave blank to use the WordPress default. Rewrite rules are flushed automatically on save.', 'site-essentials' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php endif; ?>

				<h3 class="scos__section-label" style="font-size:var(--scos-fs-sm);margin-bottom:var(--scos-s-2)"><?php esc_html_e( 'Meta & SEO', 'site-essentials' ); ?></h3>
				<p class="description" style="margin-bottom:var(--scos-s-2)">
					<?php
					$token_hints = [
						'author' => __( 'Available tokens: %title% (author name), %author%, %sitename%, %sep%, %page%', 'site-essentials' ),
						'date'   => __( 'Available tokens: %title% (date label e.g. "January 2025"), %sitename%, %sep%, %page%', 'site-essentials' ),
						'search' => __( 'Available tokens: %title% (search query), %search%, %sitename%, %sep%', 'site-essentials' ),
						'404'    => __( 'Available tokens: %title% ("Page Not Found"), %sitename%, %sep%', 'site-essentials' ),
					];
					echo esc_html( $token_hints[ $slug ] ?? '' );
					?>
				</p>
				<table class="scos-form" style="margin-bottom:var(--scos-s-4)">
					<tbody>
						<tr>
							<th>
								<label for="scos-<?php echo esc_attr( $slug ); ?>-title"><?php esc_html_e( 'Meta Title', 'site-essentials' ); ?></label>
							</th>
							<td>
								<input type="text" id="scos-<?php echo esc_attr( $slug ); ?>-title"
									name="<?php echo esc_attr( $f ); ?>[title]"
									value="<?php echo esc_attr( $s['title'] ); ?>"
									class="scos-input" placeholder="%title% %sep% %sitename%" maxlength="120">
								<p class="description"><?php esc_html_e( 'Supports tokens. Leave blank to use the WordPress default.', 'site-essentials' ); ?></p>
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos-<?php echo esc_attr( $slug ); ?>-description"><?php esc_html_e( 'Meta Description', 'site-essentials' ); ?></label>
							</th>
							<td>
								<textarea id="scos-<?php echo esc_attr( $slug ); ?>-description"
									name="<?php echo esc_attr( $f ); ?>[description]"
									rows="3" class="scos-input"
									maxlength="320"><?php echo esc_textarea( $s['description'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Supports tokens. Leave blank to omit.', 'site-essentials' ); ?></p>
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos-<?php echo esc_attr( $slug ); ?>-breadcrumb"><?php esc_html_e( 'Breadcrumb Title', 'site-essentials' ); ?></label>
							</th>
							<td>
								<input type="text" id="scos-<?php echo esc_attr( $slug ); ?>-breadcrumb"
									name="<?php echo esc_attr( $f ); ?>[breadcrumb_title]"
									value="<?php echo esc_attr( $s['breadcrumb_title'] ); ?>"
									class="scos-input">
								<p class="description"><?php esc_html_e( 'Short label for breadcrumb navigation.', 'site-essentials' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<h3 class="scos__section-label" style="font-size:var(--scos-fs-sm);margin-bottom:var(--scos-s-2)"><?php esc_html_e( 'Meta Robots', 'site-essentials' ); ?></h3>
				<fieldset style="margin-bottom:var(--scos-s-4)">
					<legend class="screen-reader-text"><?php esc_html_e( 'Meta Robots', 'site-essentials' ); ?></legend>
					<?php
					$robots_opts = [
						'noindex'      => __( 'noindex — Exclude from search engine indexes', 'site-essentials' ),
						'nofollow'     => __( 'nofollow — Do not follow links on this page', 'site-essentials' ),
						'noimageindex' => __( 'noimageindex — Exclude images from image search', 'site-essentials' ),
						'nosnippet'    => __( 'nosnippet — Do not show a text snippet in results', 'site-essentials' ),
					];
					foreach ( $robots_opts as $val => $rbl ) :
						$checked = in_array( $val, (array) $s['robots'], true );
					?>
					<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-1)">
						<input type="checkbox"
							name="<?php echo esc_attr( $f ); ?>[robots][]"
							value="<?php echo esc_attr( $val ); ?>"
							<?php checked( $checked ); ?>>
						<span><?php echo esc_html( $rbl ); ?></span>
					</label>
					<?php endforeach; ?>
					<?php if ( in_array( $slug, [ 'search', 'date' ], true ) ) : ?>
					<p class="description" style="margin-top:var(--scos-s-2)"><?php esc_html_e( 'Tip: noindex is commonly applied to date and search archives to prevent duplicate-content issues.', 'site-essentials' ); ?></p>
					<?php endif; ?>
				</fieldset>

				<?php if ( 'author' === $slug ) : ?>
				<h3 class="scos__section-label" style="font-size:var(--scos-fs-sm);margin-bottom:var(--scos-s-2)"><?php esc_html_e( 'Default OG / Social Image', 'site-essentials' ); ?></h3>
				<?php
				$og_id    = (int) ( $s['og_image_id'] ?? 0 );
				$og_src   = $og_id ? wp_get_attachment_image_url( $og_id, [ 240, 126 ] ) : '';
				$input_id = 'scos-author-og-id';
				$thumb_id = 'scos-author-og-thumb';
				?>
				<div class="scos-og-wrap" style="margin-bottom:var(--scos-s-4)">
					<div class="scos-og-thumb-box<?php echo $og_src ? ' has-image' : ''; ?>" id="<?php echo esc_attr( $thumb_id ); ?>"
					     style="width:240px;height:126px;border:2px dashed var(--scos-border-strong);border-radius:var(--scos-r-md);display:flex;align-items:center;justify-content:center;overflow:hidden;background:var(--scos-surface-muted);color:var(--scos-ink-subtle);font-size:var(--scos-fs-sm);text-align:center;padding:var(--scos-s-2);margin-bottom:var(--scos-s-2)">
						<?php if ( $og_src ) : ?>
							<img src="<?php echo esc_url( $og_src ); ?>" alt="" style="width:100%;height:100%;object-fit:cover">
						<?php else : ?>
							<?php esc_html_e( 'Select your default thumbnail', 'site-essentials' ); ?>
						<?php endif; ?>
					</div>
					<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>"
						name="<?php echo esc_attr( $f ); ?>[og_image_id]"
						value="<?php echo esc_attr( (string) $og_id ); ?>">
					<div style="display:flex;gap:var(--scos-s-2);flex-wrap:wrap;margin-bottom:var(--scos-s-2)">
						<button type="button" class="scos-btn scos-btn--ghost scos-og-upload"
							data-input="<?php echo esc_attr( $input_id ); ?>"
							data-thumb="<?php echo esc_attr( $thumb_id ); ?>">
							<?php echo $og_id ? esc_html__( 'Change Image', 'site-essentials' ) : esc_html__( 'Upload an Image', 'site-essentials' ); ?>
						</button>
						<button type="button" class="scos-btn scos-btn--ghost scos-og-remove"
							data-input="<?php echo esc_attr( $input_id ); ?>"
							data-thumb="<?php echo esc_attr( $thumb_id ); ?>"
							style="<?php echo $og_id ? '' : 'display:none;'; ?>">
							<?php esc_html_e( 'Remove Image', 'site-essentials' ); ?>
						</button>
					</div>
					<p class="description"><?php esc_html_e( 'Used as og:image for author archive pages when no user profile image is available.', 'site-essentials' ); ?></p>
				</div>
				<?php endif; ?>

			</div><!-- /.scos-accordion__body -->
		</div><!-- /.scos-accordion__item -->
	<?php endforeach; ?>
	</div><!-- /.scos-accordion (special) -->

	<!-- ══ Taxonomy Archive Types ══════════════════════════════════════ -->
	<h2 class="scos__section-label"><?php esc_html_e( 'Taxonomy Archive Types', 'site-essentials' ); ?></h2>
	<p class="description" style="margin-bottom:var(--scos-s-3)"><?php esc_html_e( 'SEO settings for taxonomy term archive pages (categories, tags, and custom taxonomies). Use %title% or %term% for the term name, %taxonomy% for the taxonomy label.', 'site-essentials' ); ?></p>

	<div class="scos-accordion" style="margin-bottom:var(--scos-s-6)">
	<?php
	$tax_archives = Archive_Settings::get_taxonomy_archives();
	foreach ( $tax_archives as $slug => $label ) :
		$s         = Archive_Settings::get( $slug );
		$f         = 'scos_archive[' . esc_attr( $slug ) . ']';
		$tax_obj   = get_taxonomy( $slug );
		$tax_label = $tax_obj ? $tax_obj->labels->singular_name : $slug;
		$item_id   = 'scos-archive-tax-' . esc_attr( $slug );
	?>
		<div class="scos-accordion__item">
			<button type="button" class="scos-accordion__trigger" aria-expanded="false" aria-controls="<?php echo esc_attr( $item_id ); ?>">
				<span class="scos-accordion__label">
					<span class="scos-accordion__title">
						<?php echo esc_html( $label ); ?>
						<span class="scos-accordion__hint">
							<?php echo esc_html( 'is_tax(\'' . $slug . '\')' ); ?>
						</span>
					</span>
				</span>
				<?php if ( scos_archive_has_data( $s ) ) : ?>
					<span class="scos-badge scos-badge--success"><?php esc_html_e( 'configured', 'site-essentials' ); ?></span>
				<?php endif; ?>
				<?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</button>

			<div class="scos-accordion__body" id="<?php echo esc_attr( $item_id ); ?>">

				<p class="description" style="margin-bottom:var(--scos-s-4)">
					<?php
					printf(
						esc_html__( 'Each %1$s term has its own archive page. These settings provide default title/description templates for all %1$s term pages. Use %2$s to insert the term name.', 'site-essentials' ),
						esc_html( strtolower( $tax_label ) ),
						'<code>%title%</code>'
					);
					?>
				</p>

				<h3 class="scos__section-label" style="font-size:var(--scos-fs-sm);margin-bottom:var(--scos-s-2)"><?php esc_html_e( 'Meta & SEO', 'site-essentials' ); ?></h3>
				<p class="description" style="margin-bottom:var(--scos-s-2)">
					<?php
					printf(
						esc_html__( 'Tokens: %%title%% (%s term name), %%term%% (same), %%taxonomy%% (taxonomy label), %%sitename%%, %%sep%%, %%page%%', 'site-essentials' ),
						esc_html( strtolower( $tax_label ) )
					);
					?>
				</p>
				<table class="scos-form" style="margin-bottom:var(--scos-s-4)">
					<tbody>
						<tr>
							<th>
								<label for="scos-<?php echo esc_attr( $slug ); ?>-title"><?php esc_html_e( 'Meta Title', 'site-essentials' ); ?></label>
							</th>
							<td>
								<input type="text" id="scos-<?php echo esc_attr( $slug ); ?>-title"
									name="<?php echo esc_attr( $f ); ?>[title]"
									value="<?php echo esc_attr( $s['title'] ); ?>"
									class="scos-input" placeholder="%title% %sep% %sitename%" maxlength="120">
								<p class="description"><?php esc_html_e( 'Supports tokens. Leave blank to use the WordPress default.', 'site-essentials' ); ?></p>
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos-<?php echo esc_attr( $slug ); ?>-description"><?php esc_html_e( 'Meta Description', 'site-essentials' ); ?></label>
							</th>
							<td>
								<textarea id="scos-<?php echo esc_attr( $slug ); ?>-description"
									name="<?php echo esc_attr( $f ); ?>[description]"
									rows="3" class="scos-input"
									maxlength="320"><?php echo esc_textarea( $s['description'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Supports tokens. Leave blank to omit. Aim for 140–160 characters.', 'site-essentials' ); ?></p>
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos-<?php echo esc_attr( $slug ); ?>-breadcrumb"><?php esc_html_e( 'Breadcrumb Title', 'site-essentials' ); ?></label>
							</th>
							<td>
								<input type="text" id="scos-<?php echo esc_attr( $slug ); ?>-breadcrumb"
									name="<?php echo esc_attr( $f ); ?>[breadcrumb_title]"
									value="<?php echo esc_attr( $s['breadcrumb_title'] ); ?>"
									class="scos-input">
								<p class="description"><?php esc_html_e( 'Short label used in breadcrumb navigation for term archive pages.', 'site-essentials' ); ?></p>
							</td>
						</tr>
						<tr>
							<th>
								<label for="scos-<?php echo esc_attr( $slug ); ?>-tldr"><?php esc_html_e( 'TLDR Summary', 'site-essentials' ); ?></label>
							</th>
							<td>
								<textarea id="scos-<?php echo esc_attr( $slug ); ?>-tldr"
									name="<?php echo esc_attr( $f ); ?>[tldr]"
									rows="2" class="scos-input"><?php echo esc_textarea( $s['tldr'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One-sentence summary for internal reference.', 'site-essentials' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<h3 class="scos__section-label" style="font-size:var(--scos-fs-sm);margin-bottom:var(--scos-s-2)"><?php esc_html_e( 'Meta Robots', 'site-essentials' ); ?></h3>
				<fieldset style="margin-bottom:var(--scos-s-4)">
					<legend class="screen-reader-text"><?php esc_html_e( 'Meta Robots', 'site-essentials' ); ?></legend>
					<?php
					$robots_opts = [
						'noindex'      => __( 'noindex — Exclude from search engine indexes', 'site-essentials' ),
						'nofollow'     => __( 'nofollow — Do not follow links on this page', 'site-essentials' ),
						'noimageindex' => __( 'noimageindex — Exclude images from image search', 'site-essentials' ),
						'nosnippet'    => __( 'nosnippet — Do not show a text snippet in results', 'site-essentials' ),
					];
					foreach ( $robots_opts as $val => $rbl ) :
						$checked = in_array( $val, (array) $s['robots'], true );
					?>
					<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-1)">
						<input type="checkbox"
							name="<?php echo esc_attr( $f ); ?>[robots][]"
							value="<?php echo esc_attr( $val ); ?>"
							<?php checked( $checked ); ?>>
						<span><?php echo esc_html( $rbl ); ?></span>
					</label>
					<?php endforeach; ?>
				</fieldset>

				<h3 class="scos__section-label" style="font-size:var(--scos-fs-sm);margin-bottom:var(--scos-s-2)"><?php esc_html_e( 'Default OG / Social Image', 'site-essentials' ); ?></h3>
				<?php
				$og_id    = (int) $s['og_image_id'];
				$og_src   = $og_id ? wp_get_attachment_image_url( $og_id, [ 240, 126 ] ) : '';
				$input_id = 'scos-' . esc_attr( $slug ) . '-tax-og-id';
				$thumb_id = 'scos-' . esc_attr( $slug ) . '-tax-og-thumb';
				?>
				<div class="scos-og-wrap" style="margin-bottom:var(--scos-s-4)">
					<div class="scos-og-thumb-box<?php echo $og_src ? ' has-image' : ''; ?>" id="<?php echo esc_attr( $thumb_id ); ?>"
					     style="width:240px;height:126px;border:2px dashed var(--scos-border-strong);border-radius:var(--scos-r-md);display:flex;align-items:center;justify-content:center;overflow:hidden;background:var(--scos-surface-muted);color:var(--scos-ink-subtle);font-size:var(--scos-fs-sm);text-align:center;padding:var(--scos-s-2);margin-bottom:var(--scos-s-2)">
						<?php if ( $og_src ) : ?>
							<img src="<?php echo esc_url( $og_src ); ?>" alt="" style="width:100%;height:100%;object-fit:cover">
						<?php else : ?>
							<?php esc_html_e( 'Select your default thumbnail', 'site-essentials' ); ?>
						<?php endif; ?>
					</div>
					<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>"
						name="<?php echo esc_attr( $f ); ?>[og_image_id]"
						value="<?php echo esc_attr( (string) $og_id ); ?>">
					<div style="display:flex;gap:var(--scos-s-2);flex-wrap:wrap;margin-bottom:var(--scos-s-2)">
						<button type="button" class="scos-btn scos-btn--ghost scos-og-upload"
							data-input="<?php echo esc_attr( $input_id ); ?>"
							data-thumb="<?php echo esc_attr( $thumb_id ); ?>">
							<?php echo $og_id ? esc_html__( 'Change Image', 'site-essentials' ) : esc_html__( 'Upload an Image', 'site-essentials' ); ?>
						</button>
						<button type="button" class="scos-btn scos-btn--ghost scos-og-remove"
							data-input="<?php echo esc_attr( $input_id ); ?>"
							data-thumb="<?php echo esc_attr( $thumb_id ); ?>"
							style="<?php echo $og_id ? '' : 'display:none;'; ?>">
							<?php esc_html_e( 'Remove Image', 'site-essentials' ); ?>
						</button>
					</div>
					<p class="description"><?php esc_html_e( 'Fallback og:image for all term pages in this taxonomy when no featured image is set.', 'site-essentials' ); ?></p>
				</div>

				<h3 class="scos__section-label" style="font-size:var(--scos-fs-sm);margin-bottom:var(--scos-s-2)"><?php esc_html_e( 'Pagination', 'site-essentials' ); ?></h3>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Pagination', 'site-essentials' ); ?></legend>
					<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-1)">
						<input type="checkbox" name="<?php echo esc_attr( $f ); ?>[pagination_noindex]" value="1"
							<?php checked( ! empty( $s['pagination_noindex'] ) ); ?>>
						<span><?php esc_html_e( 'noindex on paginated pages (e.g. /page/2/)', 'site-essentials' ); ?></span>
					</label>
					<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-1)">
						<input type="checkbox" name="<?php echo esc_attr( $f ); ?>[canonical_paged]" value="1"
							<?php checked( ! empty( $s['canonical_paged'] ) ); ?>>
						<span><?php esc_html_e( 'Self-canonical on paginated pages', 'site-essentials' ); ?></span>
					</label>
					<label class="scos-checkbox-row">
						<input type="checkbox" name="<?php echo esc_attr( $f ); ?>[rel_prevnext]" value="1"
							<?php checked( ! empty( $s['rel_prevnext'] ) ); ?>>
						<span><?php esc_html_e( 'Output rel="prev" / rel="next" links in <head>', 'site-essentials' ); ?></span>
					</label>
				</fieldset>

			</div><!-- /.scos-accordion__body -->
		</div><!-- /.scos-accordion__item -->
	<?php endforeach; ?>
	</div><!-- /.scos-accordion (taxonomies) -->

	<div class="scos-save-bar">
		<span style="font-size:var(--scos-fs-sm);color:var(--scos-ink-subtle)">
			<strong><?php esc_html_e( 'Archive SEO', 'site-essentials' ); ?></strong>
			&mdash; <?php esc_html_e( 'unsaved changes will be lost', 'site-essentials' ); ?>
		</span>
		<button type="submit" class="scos-btn scos-btn--primary">
			<?php esc_html_e( 'Save Archive SEO Settings', 'site-essentials' ); ?>
		</button>
	</div>

</form>

<script>
/* global wp, jQuery */
( function () {
	'use strict';

	// Accordion toggle
	document.querySelectorAll( '.scos-accordion__trigger' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var item   = btn.closest( '.scos-accordion__item' );
			var isOpen = item.classList.toggle( 'is-open' );
			btn.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		} );
	} );

} )();
</script>

<script>
/* OG image picker — jQuery / wp.media */
( function ($) {
	'use strict';

	function openMediaFrame( inputId, thumbId ) {
		if ( typeof wp === 'undefined' || ! wp.media ) {
			alert( '<?php echo esc_js( __( 'Media library not available. Try reloading the page.', 'site-essentials' ) ); ?>' );
			return;
		}

		var frame = wp.media( {
			title:    '<?php echo esc_js( __( 'Select OG Image', 'site-essentials' ) ); ?>',
			button:   { text: '<?php echo esc_js( __( 'Use this image', 'site-essentials' ) ); ?>' },
			library:  { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			var src = ( att.sizes && att.sizes.medium ) ? att.sizes.medium.url : att.url;

			$( '#' + inputId ).val( att.id );

			var $thumb = $( '#' + thumbId );
			$thumb.addClass( 'has-image' ).html( '<img src="' + src + '" alt="" style="width:100%;height:100%;object-fit:cover">' );

			$thumb.closest( '.scos-og-wrap' ).find( '.scos-og-upload' )
				.text( '<?php echo esc_js( __( 'Change Image', 'site-essentials' ) ); ?>' );
			$thumb.closest( '.scos-og-wrap' ).find( '.scos-og-remove' ).show();
		} );

		frame.open();
	}

	$( document ).on( 'click', '.scos-og-upload', function () {
		openMediaFrame( $( this ).data( 'input' ), $( this ).data( 'thumb' ) );
	} );

	$( document ).on( 'click', '.scos-og-remove', function () {
		var $wrap   = $( this ).closest( '.scos-og-wrap' );
		var inputId = $( this ).data( 'input' );

		$( '#' + inputId ).val( '' );

		var $thumb = $( '#' + $( this ).data( 'thumb' ) );
		$thumb.removeClass( 'has-image' ).text( '<?php echo esc_js( __( 'Select your default thumbnail', 'site-essentials' ) ); ?>' );

		$wrap.find( '.scos-og-upload' ).text( '<?php echo esc_js( __( 'Upload an Image', 'site-essentials' ) ); ?>' );
		$( this ).hide();
	} );
}( jQuery ) );
</script>
