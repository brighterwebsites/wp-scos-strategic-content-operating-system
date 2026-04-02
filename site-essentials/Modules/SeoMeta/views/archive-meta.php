<?php
/**
 * SEO Meta — Archive Settings view
 *
 * Rendered inside the "Archive SEO" tab of Site Essentials > SEO.
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

/**
 * Returns true if the settings array has any non-default values saved,
 * so we open the accordion by default for archives that are already configured.
 */
function scos_archive_has_data( array $s ): bool {
	return ! empty( $s['title'] )
	    || ! empty( $s['description'] )
	    || ! empty( $s['breadcrumb_title'] )
	    || ! empty( $s['tldr'] )
	    || ! empty( $s['robots'] )
	    || ! empty( $s['og_image_id'] );
}
?>
<style>
/* ── Archive accordion cards ─────────────────────────── */
.scos-archive-meta-wrap details.scos-archive-card {
	border: 1px solid #dcdcde;
	border-radius: 4px;
	background: #fff;
	margin-bottom: 12px;
	padding: 0;
}
.scos-archive-meta-wrap details.scos-archive-card summary {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 14px 18px;
	cursor: pointer;
	user-select: none;
	list-style: none;
	gap: 10px;
}
.scos-archive-meta-wrap details.scos-archive-card summary::-webkit-details-marker { display: none; }
.scos-archive-meta-wrap details.scos-archive-card summary::marker { display: none; }
.scos-archive-meta-wrap details.scos-archive-card summary .scos-card-title {
	font-size: 14px;
	font-weight: 600;
	color: #1d2327;
	margin: 0;
	flex: 1;
}
.scos-archive-meta-wrap details.scos-archive-card summary .scos-card-meta {
	font-size: 12px;
	font-weight: 400;
	color: #787c82;
	margin-left: 8px;
}
.scos-archive-meta-wrap details.scos-archive-card summary .scos-card-badge {
	font-size: 11px;
	background: #e8f0e8;
	color: #2d6a2d;
	border-radius: 10px;
	padding: 2px 8px;
	white-space: nowrap;
}
.scos-archive-meta-wrap details.scos-archive-card summary .scos-card-chevron {
	font-size: 18px;
	color: #787c82;
	transition: transform 0.15s ease;
	flex-shrink: 0;
}
.scos-archive-meta-wrap details.scos-archive-card[open] summary .scos-card-chevron {
	transform: rotate(180deg);
}
.scos-archive-meta-wrap details.scos-archive-card[open] summary {
	border-bottom: 1px solid #dcdcde;
}
.scos-archive-card-body {
	padding: 18px 18px 24px;
}
.scos-archive-card-body h3 {
	font-size: 13px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: #50575e;
	margin: 24px 0 10px;
	padding-bottom: 6px;
	border-bottom: 1px solid #f0f0f1;
}
.scos-archive-card-body h3:first-child { margin-top: 4px; }

/* ── OG image picker ─────────────────────────────────── */
.scos-og-wrap { display: flex; flex-direction: column; gap: 8px; max-width: 260px; }
.scos-og-thumb-box {
	width: 240px;
	height: 126px;
	border: 2px dashed #c3c4c7;
	border-radius: 4px;
	display: flex;
	align-items: center;
	justify-content: center;
	overflow: hidden;
	background: #f6f7f7;
	color: #787c82;
	font-size: 12px;
	text-align: center;
	padding: 8px;
}
.scos-og-thumb-box img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
}
.scos-og-thumb-box.has-image { border-style: solid; border-color: #c3c4c7; }
.scos-og-actions { display: flex; gap: 6px; flex-wrap: wrap; }
</style>

<div class="scos-archive-meta-wrap">

	<!-- Token cheat-sheet ──────────────────────────────────────────────── -->
	<details class="scos-archive-card" style="margin-bottom: 20px;">
		<summary>
			<span class="scos-card-title"><?php esc_html_e( 'Template Tokens', 'site-essentials' ); ?></span>
			<span class="scos-card-meta"><?php esc_html_e( 'Use in Meta Title and Meta Description fields', 'site-essentials' ); ?></span>
			<span class="scos-card-chevron">&#8964;</span>
		</summary>
		<div class="scos-archive-card-body">
			<table class="widefat striped" style="max-width: 580px;">
				<thead>
					<tr>
						<th style="width:130px;"><?php esc_html_e( 'Token', 'site-essentials' ); ?></th>
						<th><?php esc_html_e( 'Resolves to', 'site-essentials' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr><td><code>%title%</code></td><td><?php esc_html_e( 'Archive name — post type plural label, or blog page title', 'site-essentials' ); ?></td></tr>
					<tr><td><code>%sitename%</code></td><td><?php esc_html_e( 'Site name from Settings › General', 'site-essentials' ); ?></td></tr>
					<tr><td><code>%sep%</code></td><td><?php esc_html_e( 'Separator (default –, filterable via scos_seo_title_sep)', 'site-essentials' ); ?></td></tr>
					<tr><td><code>%page%</code></td><td><?php esc_html_e( '"Page 2" on paginated views, blank on page 1', 'site-essentials' ); ?></td></tr>
				</tbody>
			</table>
			<p style="margin-top: 10px; color: #50575e; font-size: 13px;">
				<?php esc_html_e( 'Example:', 'site-essentials' ); ?>
				<code>%title% %sep% %sitename%</code>
				&rarr;
				<em><?php echo esc_html( sprintf( '%s – %s', __( 'Posts (Blog)', 'site-essentials' ), get_bloginfo( 'name' ) ) ); ?></em>
			</p>
		</div>
	</details>

	<!-- Per-archive settings form ─────────────────────────────────────── -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="site_essentials_save_archive_meta">
		<?php wp_nonce_field( 'scos_save_archive_meta', 'scos_archive_meta_nonce' ); ?>

		<?php
		$first = true;
		foreach ( $archives as $slug => $label ) :
			$s        = Archive_Settings::get( $slug );
			$f        = 'scos_archive[' . esc_attr( $slug ) . ']';
			$is_open  = $first || scos_archive_has_data( $s );
			$first    = false;

			$has_archive_url = ( 'post' === $slug )
				? ( (int) get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' ) )
				: get_post_type_archive_link( $slug );
		?>
		<details class="scos-archive-card"<?php echo $is_open ? ' open' : ''; ?>>
			<summary>
				<span class="scos-card-title">
					<?php echo esc_html( $label ); ?>
					<span class="scos-card-meta">
						<?php echo esc_html( 'post' === $slug ? 'is_home()' : 'is_post_type_archive(\'' . $slug . '\')' ); ?>
					</span>
				</span>
				<?php if ( scos_archive_has_data( $s ) ) : ?>
				<span class="scos-card-badge"><?php esc_html_e( 'configured', 'site-essentials' ); ?></span>
				<?php elseif ( $has_archive_url ) : ?>
				<span class="scos-card-badge" style="background:#f0f0f1;color:#50575e;"><?php esc_html_e( 'has archive', 'site-essentials' ); ?></span>
				<?php endif; ?>
				<span class="scos-card-chevron">&#8964;</span>
			</summary>

			<div class="scos-archive-card-body">

				<?php if ( $has_archive_url ) : ?>
				<p style="margin: 0 0 16px; font-size: 12px; color: #787c82;">
					<?php esc_html_e( 'Archive URL:', 'site-essentials' ); ?>
					<a href="<?php echo esc_url( $has_archive_url ); ?>" target="_blank"><?php echo esc_html( $has_archive_url ); ?></a>
				</p>
				<?php else : ?>
				<p style="margin: 0 0 16px; font-size: 12px; color: #a7aaad;">
					<?php esc_html_e( 'No archive URL — this post type does not currently have has_archive enabled. Settings will apply when an archive is enabled.', 'site-essentials' ); ?>
				</p>
				<?php endif; ?>

				<!-- ── Title & Description ──────────────────────── -->
				<h3><?php esc_html_e( 'Meta & SEO', 'site-essentials' ); ?></h3>
				<table class="form-table" role="presentation" style="margin-top: 0;">

					<tr>
						<th scope="row" style="width: 180px;">
							<label for="scos-<?php echo esc_attr( $slug ); ?>-title">
								<?php esc_html_e( 'Meta Title', 'site-essentials' ); ?>
							</label>
						</th>
						<td>
							<input type="text"
								id="scos-<?php echo esc_attr( $slug ); ?>-title"
								name="<?php echo esc_attr( $f ); ?>[title]"
								value="<?php echo esc_attr( $s['title'] ); ?>"
								class="large-text"
								placeholder="%title% %sep% %sitename%"
								maxlength="120">
							<p class="description"><?php esc_html_e( 'Supports tokens. Leave blank to use the WordPress default. Aim for 50–60 characters.', 'site-essentials' ); ?></p>
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
							<p class="description"><?php esc_html_e( 'Supports tokens. Leave blank to omit. Aim for 140–160 characters.', 'site-essentials' ); ?></p>
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
							<p class="description"><?php esc_html_e( 'Short label used in breadcrumb navigation.', 'site-essentials' ); ?></p>
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
							<p class="description"><?php esc_html_e( 'One-sentence summary for internal reference and potential schema use.', 'site-essentials' ); ?></p>
						</td>
					</tr>

				</table>

				<!-- ── Robots ───────────────────────────────────── -->
				<h3><?php esc_html_e( 'Meta Robots', 'site-essentials' ); ?></h3>
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
					<label style="display: block; margin-bottom: 5px;">
						<input type="checkbox"
							name="<?php echo esc_attr( $f ); ?>[robots][]"
							value="<?php echo esc_attr( $val ); ?>"
							<?php checked( $checked ); ?>>
						<?php echo esc_html( $label_text ); ?>
					</label>
					<?php endforeach; ?>
				</fieldset>

				<!-- ── Sitemap ──────────────────────────────────── -->
				<h3><?php esc_html_e( 'Sitemap Visibility', 'site-essentials' ); ?></h3>
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
					<label style="display: block; margin-bottom: 5px;">
						<input type="checkbox"
							name="<?php echo esc_attr( $f ); ?>[sitemap_exclude][]"
							value="<?php echo esc_attr( $val ); ?>"
							<?php checked( $checked ); ?>>
						<?php echo esc_html( $label_text ); ?>
					</label>
					<?php endforeach; ?>
				</fieldset>

				<!-- ── OG Image ─────────────────────────────────── -->
				<h3><?php esc_html_e( 'Social / OG Image', 'site-essentials' ); ?></h3>
				<?php
				$og_id  = (int) $s['og_image_id'];
				$og_src = $og_id ? wp_get_attachment_image_url( $og_id, [ 240, 126 ] ) : '';
				$input_id = 'scos-' . esc_attr( $slug ) . '-og-id';
				$thumb_id = 'scos-' . esc_attr( $slug ) . '-og-thumb';
				?>
				<div class="scos-og-wrap">
					<div class="scos-og-thumb-box<?php echo $og_src ? ' has-image' : ''; ?>" id="<?php echo esc_attr( $thumb_id ); ?>">
						<?php if ( $og_src ) : ?>
							<img src="<?php echo esc_url( $og_src ); ?>" alt="">
						<?php else : ?>
							<?php esc_html_e( 'Select your default thumbnail', 'site-essentials' ); ?>
						<?php endif; ?>
					</div>
					<input type="hidden"
						id="<?php echo esc_attr( $input_id ); ?>"
						name="<?php echo esc_attr( $f ); ?>[og_image_id]"
						value="<?php echo esc_attr( (string) $og_id ); ?>">
					<div class="scos-og-actions">
						<button type="button"
							class="button scos-og-upload"
							data-input="<?php echo esc_attr( $input_id ); ?>"
							data-thumb="<?php echo esc_attr( $thumb_id ); ?>">
							<?php echo $og_id ? esc_html__( 'Change Image', 'site-essentials' ) : esc_html__( 'Upload an Image', 'site-essentials' ); ?>
						</button>
						<button type="button"
							class="button scos-og-remove"
							data-input="<?php echo esc_attr( $input_id ); ?>"
							data-thumb="<?php echo esc_attr( $thumb_id ); ?>"
							style="<?php echo $og_id ? '' : 'display:none;'; ?>">
							<?php esc_html_e( 'Remove Image', 'site-essentials' ); ?>
						</button>
					</div>
					<p class="description" style="margin-top: 4px;"><?php esc_html_e( 'Recommended: 1200×630 px. Shown as og:image on social shares for this archive.', 'site-essentials' ); ?></p>
				</div>

				<!-- ── Pagination ───────────────────────────────── -->
				<h3><?php esc_html_e( 'Pagination', 'site-essentials' ); ?></h3>
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

			</div><!-- /.scos-archive-card-body -->
		</details>
		<?php endforeach; ?>

		<!-- ══════════════════════════════════════════════════════════════
		     Special Archive Types
		     ══════════════════════════════════════════════════════════════ -->
		<h2 style="margin: 32px 0 12px; font-size: 15px; color: #1d2327; border-bottom: 2px solid #dcdcde; padding-bottom: 8px;">
			<?php esc_html_e( 'Special Archive Types', 'site-essentials' ); ?>
		</h2>
		<p style="color: #50575e; margin: 0 0 16px; font-size: 13px;">
			<?php esc_html_e( 'Control SEO output and optionally disable author, date, and search archives with a 301 redirect to the URL of your choice.', 'site-essentials' ); ?>
		</p>

		<?php
		$special_archives = Archive_Settings::get_special_archives();
		foreach ( $special_archives as $slug => $label ) :
			$s          = Archive_Settings::get( $slug );
			$f          = 'scos_archive[' . esc_attr( $slug ) . ']';
			$has_disable = in_array( $slug, [ 'author', 'date', 'search' ], true );
			$is_disabled = $has_disable && ! empty( $s['disabled'] );
			$is_open     = scos_archive_has_data( $s ) || $is_disabled;
		?>
		<details class="scos-archive-card"<?php echo $is_open ? ' open' : ''; ?>>
			<summary>
				<span class="scos-card-title">
					<?php echo esc_html( $label ); ?>
					<span class="scos-card-meta">
						<?php echo esc_html( 'is_' . ( '404' === $slug ? '404' : $slug ) . '()' ); ?>
					</span>
				</span>
				<?php if ( $is_disabled ) : ?>
				<span class="scos-card-badge" style="background:#fce8e8;color:#8b1a1a;"><?php esc_html_e( 'disabled – redirecting', 'site-essentials' ); ?></span>
				<?php elseif ( scos_archive_has_data( $s ) ) : ?>
				<span class="scos-card-badge"><?php esc_html_e( 'configured', 'site-essentials' ); ?></span>
				<?php endif; ?>
				<span class="scos-card-chevron">&#8964;</span>
			</summary>

			<div class="scos-archive-card-body">

				<?php if ( $has_disable ) : ?>
				<!-- ── Disable / Redirect ──────────────────────── -->
				<h3><?php esc_html_e( 'Archive Status', 'site-essentials' ); ?></h3>
				<?php
				$disable_labels = [
					'author' => __( 'Disable author archives — redirect all /author/ URLs', 'site-essentials' ),
					'date'   => __( 'Disable date archives — redirect all date archive URLs', 'site-essentials' ),
					'search' => __( 'Disable search results page — redirect all search queries', 'site-essentials' ),
				];
				?>
				<label style="display: block; margin-bottom: 10px;">
					<input type="checkbox"
						name="<?php echo esc_attr( $f ); ?>[disabled]"
						value="1"
						<?php checked( $is_disabled ); ?>>
					<?php echo esc_html( $disable_labels[ $slug ] ?? '' ); ?>
				</label>
				<table class="form-table" role="presentation" style="margin-top: 0;">
					<tr>
						<th scope="row" style="width:180px;">
							<label for="scos-<?php echo esc_attr( $slug ); ?>-redirect">
								<?php esc_html_e( 'Redirect to URL', 'site-essentials' ); ?>
							</label>
						</th>
						<td>
							<input type="url"
								id="scos-<?php echo esc_attr( $slug ); ?>-redirect"
								name="<?php echo esc_attr( $f ); ?>[redirect_url]"
								value="<?php echo esc_attr( $s['redirect_url'] ?? '' ); ?>"
								class="regular-text"
								placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Leave blank to redirect to the homepage. Only used when the archive is disabled above.', 'site-essentials' ); ?></p>
						</td>
					</tr>
				</table>
				<?php endif; ?>

				<?php if ( 'author' === $slug ) : ?>
				<!-- ── Author Slug ────────────────────────────── -->
				<h3><?php esc_html_e( 'Author Archive URL', 'site-essentials' ); ?></h3>
				<table class="form-table" role="presentation" style="margin-top:0;">
					<tr>
						<th scope="row" style="width:180px;">
							<label for="scos-author-slug">
								<?php esc_html_e( 'URL prefix', 'site-essentials' ); ?>
							</label>
						</th>
						<td style="display:flex; align-items:center; gap:4px; flex-wrap:wrap;">
							<span style="color:#50575e;"><?php echo esc_html( rtrim( home_url( '/' ), '/' ) . '/' ); ?></span>
							<input type="text"
								id="scos-author-slug"
								name="<?php echo esc_attr( $f ); ?>[author_slug]"
								value="<?php echo esc_attr( $s['author_slug'] ?? '' ); ?>"
								class="small-text"
								placeholder="author"
								style="width:120px;">
							<span style="color:#50575e;">/username/</span>
							<p class="description" style="width:100%; margin-top:4px;"><?php esc_html_e( 'Changes /author/ to a custom prefix (e.g. team). Leave blank to use the WordPress default. Rewrite rules are flushed automatically on save.', 'site-essentials' ); ?></p>
						</td>
					</tr>
				</table>
				<?php endif; ?>

				<!-- ── Meta & SEO ────────────────────────────── -->
				<h3><?php esc_html_e( 'Meta & SEO', 'site-essentials' ); ?></h3>
				<p style="margin:0 0 12px; font-size:12px; color:#787c82;">
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
				<table class="form-table" role="presentation" style="margin-top:0;">
					<tr>
						<th scope="row" style="width:180px;">
							<label for="scos-<?php echo esc_attr( $slug ); ?>-title">
								<?php esc_html_e( 'Meta Title', 'site-essentials' ); ?>
							</label>
						</th>
						<td>
							<input type="text"
								id="scos-<?php echo esc_attr( $slug ); ?>-title"
								name="<?php echo esc_attr( $f ); ?>[title]"
								value="<?php echo esc_attr( $s['title'] ); ?>"
								class="large-text"
								placeholder="%title% %sep% %sitename%"
								maxlength="120">
							<p class="description"><?php esc_html_e( 'Supports tokens. Leave blank to use the WordPress default.', 'site-essentials' ); ?></p>
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
							<p class="description"><?php esc_html_e( 'Supports tokens. Leave blank to omit.', 'site-essentials' ); ?></p>
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
							<p class="description"><?php esc_html_e( 'Short label for breadcrumb navigation.', 'site-essentials' ); ?></p>
						</td>
					</tr>
				</table>

				<!-- ── Meta Robots ───────────────────────────── -->
				<h3><?php esc_html_e( 'Meta Robots', 'site-essentials' ); ?></h3>
				<fieldset>
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
					<label style="display: block; margin-bottom: 5px;">
						<input type="checkbox"
							name="<?php echo esc_attr( $f ); ?>[robots][]"
							value="<?php echo esc_attr( $val ); ?>"
							<?php checked( $checked ); ?>>
						<?php echo esc_html( $rbl ); ?>
					</label>
					<?php endforeach; ?>
					<?php if ( in_array( $slug, [ 'search', 'date' ], true ) ) : ?>
					<p class="description" style="margin-top:6px;">
						<?php esc_html_e( 'Tip: noindex is commonly applied to date and search archives to prevent duplicate-content issues in search results.', 'site-essentials' ); ?>
					</p>
					<?php endif; ?>
				</fieldset>

				<?php if ( 'author' === $slug ) : ?>
				<!-- ── Author OG Image ───────────────────────── -->
				<h3><?php esc_html_e( 'Default OG / Social Image', 'site-essentials' ); ?></h3>
				<?php
				$og_id    = (int) ( $s['og_image_id'] ?? 0 );
				$og_src   = $og_id ? wp_get_attachment_image_url( $og_id, [ 240, 126 ] ) : '';
				$input_id = 'scos-author-og-id';
				$thumb_id = 'scos-author-og-thumb';
				?>
				<div class="scos-og-wrap">
					<div class="scos-og-thumb-box<?php echo $og_src ? ' has-image' : ''; ?>" id="<?php echo esc_attr( $thumb_id ); ?>">
						<?php if ( $og_src ) : ?>
							<img src="<?php echo esc_url( $og_src ); ?>" alt="">
						<?php else : ?>
							<?php esc_html_e( 'Select your default thumbnail', 'site-essentials' ); ?>
						<?php endif; ?>
					</div>
					<input type="hidden"
						id="<?php echo esc_attr( $input_id ); ?>"
						name="<?php echo esc_attr( $f ); ?>[og_image_id]"
						value="<?php echo esc_attr( (string) $og_id ); ?>">
					<div class="scos-og-actions">
						<button type="button" class="button scos-og-upload"
							data-input="<?php echo esc_attr( $input_id ); ?>"
							data-thumb="<?php echo esc_attr( $thumb_id ); ?>">
							<?php echo $og_id ? esc_html__( 'Change Image', 'site-essentials' ) : esc_html__( 'Upload an Image', 'site-essentials' ); ?>
						</button>
						<button type="button" class="button scos-og-remove"
							data-input="<?php echo esc_attr( $input_id ); ?>"
							data-thumb="<?php echo esc_attr( $thumb_id ); ?>"
							style="<?php echo $og_id ? '' : 'display:none;'; ?>">
							<?php esc_html_e( 'Remove Image', 'site-essentials' ); ?>
						</button>
					</div>
					<p class="description" style="margin-top:4px;"><?php esc_html_e( 'Used as og:image for author archive pages when no user profile image is available.', 'site-essentials' ); ?></p>
				</div>
				<?php endif; ?>

			</div><!-- /.scos-archive-card-body -->
		</details>
		<?php endforeach; ?>

		<p style="margin-top: 20px;">
			<?php submit_button( __( 'Save Archive SEO Settings', 'site-essentials' ), 'primary', 'submit', false ); ?>
		</p>

	</form>
</div><!-- /.scos-archive-meta-wrap -->

<script>
/* global wp, jQuery */
(function ($) {
	'use strict';

	function openMediaFrame(inputId, thumbId) {
		if (typeof wp === 'undefined' || !wp.media) {
			// eslint-disable-next-line no-alert
			alert('<?php echo esc_js( __( 'Media library not available. Try reloading the page.', 'site-essentials' ) ); ?>');
			return;
		}

		var frame = wp.media({
			title:    '<?php echo esc_js( __( 'Select OG Image', 'site-essentials' ) ); ?>',
			button:   { text: '<?php echo esc_js( __( 'Use this image', 'site-essentials' ) ); ?>' },
			library:  { type: 'image' },
			multiple: false
		});

		frame.on('select', function () {
			var att    = frame.state().get('selection').first().toJSON();
			var src    = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;

			$('#' + inputId).val(att.id);

			var $thumb = $('#' + thumbId);
			$thumb.addClass('has-image').html('<img src="' + src + '" alt="">');

			// Update upload button label & show remove
			$thumb.closest('.scos-og-wrap').find('.scos-og-upload')
				.text('<?php echo esc_js( __( 'Change Image', 'site-essentials' ) ); ?>');
			$thumb.closest('.scos-og-wrap').find('.scos-og-remove').show();
		});

		frame.open();
	}

	$(document).on('click', '.scos-og-upload', function () {
		openMediaFrame($(this).data('input'), $(this).data('thumb'));
	});

	$(document).on('click', '.scos-og-remove', function () {
		var $wrap  = $(this).closest('.scos-og-wrap');
		var inputId = $(this).data('input');

		$('#' + inputId).val('');

		var $thumb = $('#' + $(this).data('thumb'));
		$thumb.removeClass('has-image').text('<?php echo esc_js( __( 'Select your default thumbnail', 'site-essentials' ) ); ?>');

		$wrap.find('.scos-og-upload').text('<?php echo esc_js( __( 'Upload an Image', 'site-essentials' ) ); ?>');
		$(this).hide();
	});
}(jQuery));
</script>
