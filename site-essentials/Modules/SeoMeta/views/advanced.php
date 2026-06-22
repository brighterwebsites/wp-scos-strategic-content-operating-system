<?php
/**
 * SEO — Advanced tab view
 *
 * v1.2 | 2026-06-21
 *
 * SCOS design system: scos-card per section, scos-checkbox-row, scos-input--mono,
 * scos-save-bar, scos-notice. Inline <style> block removed.
 * No functional changes — form action, field names, nonces, and AJAX hooks unchanged.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\Views
 */

use SiteEssentials\Modules\SeoMeta\Image_SEO;
use SiteEssentials\Modules\SeoMeta\Virtual_Files;
use SiteEssentials\Modules\SeoMeta\Exif_Stripper;

defined( 'ABSPATH' ) || exit;

$opts         = Image_SEO::get();
$robots_opts  = Virtual_Files::get_robots();
$llms_opts    = Virtual_Files::get_llms();
$exif_opts    = Exif_Stripper::get();
$blog_public  = (bool) get_option( 'blog_public', 1 );
$imagick_ok   = Exif_Stripper::imagick_available();
$exif_bulk_nonce = wp_create_nonce( 'scos_exif_bulk' );

if ( isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
	echo '<div class="scos-notice scos-notice--success" style="margin-bottom:var(--scos-s-4)"><p>'
	   . esc_html__( 'Advanced SEO settings saved.', 'site-essentials' )
	   . '</p></div>';
}
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="scos_save_image_seo">
	<?php wp_nonce_field( 'scos_save_image_seo', 'scos_image_seo_nonce' ); ?>

	<!-- ── Image SEO ────────────────────────────────────────────────── -->
	<h2 class="scos__section-label" style="margin-bottom:var(--scos-s-3)"><?php esc_html_e( 'Image SEO', 'site-essentials' ); ?></h2>

	<div class="scos-card" style="margin-bottom:var(--scos-s-4)">
		<div class="scos-card__body">
			<p class="description" style="margin-bottom:var(--scos-s-4)"><?php esc_html_e( 'WordPress creates an "attachment page" for every uploaded file. These pages are thin-content and rarely useful for SEO. The options below let you hide, disable, or redirect them cleanly.', 'site-essentials' ); ?></p>

			<label class="scos-checkbox-row" style="padding:var(--scos-s-3) 0;border-bottom:1px solid var(--scos-border);cursor:pointer;">
				<input type="checkbox" name="scos_image_seo[noindex_attachments]" value="1" <?php checked( $opts['noindex_attachments'] ); ?>>
				<span>
					<strong><?php esc_html_e( 'Noindex attachment pages', 'site-essentials' ); ?></strong>
					<span class="description" style="display:block;margin-top:2px"><?php esc_html_e( 'Adds noindex,nofollow to the robots meta tag on every attachment page. The pages remain accessible — nothing is redirected or deleted.', 'site-essentials' ); ?></span>
					<span class="description" style="display:block;margin-top:2px;font-style:italic"><?php esc_html_e( 'Redundant if "Redirect attachment pages" is also enabled.', 'site-essentials' ); ?></span>
				</span>
			</label>

			<label class="scos-checkbox-row" style="padding:var(--scos-s-3) 0;border-bottom:1px solid var(--scos-border);cursor:pointer;">
				<input type="checkbox" name="scos_image_seo[no_comments_attachments]" value="1" <?php checked( $opts['no_comments_attachments'] ); ?>>
				<span>
					<strong><?php esc_html_e( 'Disable comments on attachment pages', 'site-essentials' ); ?></strong>
					<span class="description" style="display:block;margin-top:2px"><?php esc_html_e( 'Closes comments and hides the comment count on all attachment pages. Reduces spam surface.', 'site-essentials' ); ?></span>
				</span>
			</label>

			<label class="scos-checkbox-row" style="padding:var(--scos-s-3) 0;border-bottom:1px solid var(--scos-border);cursor:pointer;">
				<input type="checkbox" name="scos_image_seo[redirect_attachments]" value="1" <?php checked( $opts['redirect_attachments'] ); ?>>
				<span>
					<strong><?php esc_html_e( 'Redirect attachment pages to file URL', 'site-essentials' ); ?></strong>
					<span class="description" style="display:block;margin-top:2px"><?php esc_html_e( 'Issues a 301 redirect from any attachment page directly to the physical file URL. Eliminates thin attachment pages from your crawl budget entirely.', 'site-essentials' ); ?></span>
					<span class="description" style="display:block;margin-top:2px;font-style:italic"><?php esc_html_e( 'Recommended. Compatible with Breakdance, Yoast, SEOPress, and most page builders.', 'site-essentials' ); ?></span>
				</span>
			</label>

			<label class="scos-checkbox-row" style="padding:var(--scos-s-3) 0;cursor:pointer;">
				<input type="checkbox" name="scos_image_seo[rename_files]" value="1" <?php checked( $opts['rename_files'] ); ?>>
				<span>
					<strong><?php esc_html_e( 'Auto-rename uploaded image files', 'site-essentials' ); ?></strong>
					<span class="description" style="display:block;margin-top:2px"><?php esc_html_e( 'At upload time, filenames are cleaned to be URL-friendly: forced lowercase, spaces and underscores replaced with hyphens. Applies to new uploads only.', 'site-essentials' ); ?></span>
					<span class="description" style="display:block;margin-top:2px;font-style:italic"><?php esc_html_e( 'Examples: "My Photo_01.JPG" → "my-photo-01.jpg" | "hero image.PNG" → "hero-image.png"', 'site-essentials' ); ?></span>
				</span>
			</label>
		</div>
	</div>

	<!-- ── Robots.txt ───────────────────────────────────────────────── -->
	<h2 class="scos__section-label" style="margin-bottom:var(--scos-s-3)"><?php esc_html_e( 'Robots.txt', 'site-essentials' ); ?></h2>

	<?php if ( ! $blog_public ) : ?>
	<div class="scos-notice scos-notice--warning" style="margin-bottom:var(--scos-s-3)">
		<p>
			<strong><?php esc_html_e( 'Heads up:', 'site-essentials' ); ?></strong>
			<?php
			printf(
				esc_html__( 'Your site is set to "Discourage search engines" in %s. WordPress outputs Disallow: / before this editor runs, so custom robots.txt content will have no effect until that setting is turned off.', 'site-essentials' ),
				'<a href="' . esc_url( admin_url( 'options-reading.php' ) ) . '">' . esc_html__( 'Settings › Reading', 'site-essentials' ) . '</a>'
			);
			?>
		</p>
	</div>
	<?php endif; ?>

	<div class="scos-card" style="margin-bottom:var(--scos-s-4)">
		<div class="scos-card__body">
			<p class="description" style="margin-bottom:var(--scos-s-3)">
				<?php esc_html_e( 'When enabled, Site Essentials completely replaces WordPress\'s default robots.txt output with the content below. The file is virtual — no physical file is written to disk.', 'site-essentials' ); ?>
				<a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" style="margin-left:var(--scos-s-1)"><?php esc_html_e( 'View current robots.txt ↗', 'site-essentials' ); ?></a>
			</p>

			<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-4);cursor:pointer;">
				<input type="checkbox" name="scos_robots[enabled]" id="scos_robots_enabled" value="1" <?php checked( $robots_opts['enabled'] ); ?>>
				<strong><?php esc_html_e( 'Enable custom robots.txt', 'site-essentials' ); ?></strong>
			</label>

			<div id="scos-robots-editor" <?php echo $robots_opts['enabled'] ? '' : 'style="opacity:.5;pointer-events:none;"'; ?>>
				<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--scos-s-2)">
					<label for="scos_robots_content" style="font-size:var(--scos-fs-lg);font-weight:600;color:var(--scos-ink)">
						<?php esc_html_e( 'robots.txt content', 'site-essentials' ); ?>
					</label>
					<button type="button" id="scos-robots-reset" class="scos-btn scos-btn--ghost"
						data-default="<?php echo esc_attr( Virtual_Files::default_robots_txt() ); ?>">
						<?php esc_html_e( '↺ Reset to gold standard', 'site-essentials' ); ?>
					</button>
				</div>
				<textarea name="scos_robots[content]" id="scos_robots_content" rows="22"
					class="scos-input scos-input--mono"
					placeholder="<?php esc_attr_e( 'Enter robots.txt content…', 'site-essentials' ); ?>"><?php echo esc_textarea( $robots_opts['content'] ?: Virtual_Files::default_robots_txt() ); ?></textarea>
				<p class="description" style="margin-top:var(--scos-s-1)"><?php esc_html_e( 'Each directive on its own line. No PHP, no HTML — plain text only.', 'site-essentials' ); ?></p>
			</div>
		</div>
	</div>

	<!-- ── LLMs.txt ─────────────────────────────────────────────────── -->
	<h2 class="scos__section-label" style="margin-bottom:var(--scos-s-3)"><?php esc_html_e( 'LLMs.txt', 'site-essentials' ); ?></h2>

	<div class="scos-card" style="margin-bottom:var(--scos-s-4)">
		<div class="scos-card__body">
			<p class="description" style="margin-bottom:var(--scos-s-3)">
				<?php esc_html_e( 'LLMs.txt is an emerging standard that helps AI crawlers and large language models understand your site\'s content and purpose. When enabled, Site Essentials serves /llms.txt as a virtual plain-text file.', 'site-essentials' ); ?>
				<a href="https://llmstxt.org/" target="_blank" style="margin-left:var(--scos-s-1)"><?php esc_html_e( 'Learn about LLMs.txt ↗', 'site-essentials' ); ?></a>
			</p>
			<?php if ( $llms_opts['enabled'] ) : ?>
			<p class="description" style="margin-bottom:var(--scos-s-3)">
				<a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank"><?php esc_html_e( 'View current llms.txt ↗', 'site-essentials' ); ?></a>
			</p>
			<?php endif; ?>

			<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-2);cursor:pointer;">
				<input type="checkbox" name="scos_llms[enabled]" id="scos_llms_enabled" value="1" <?php checked( $llms_opts['enabled'] ); ?>>
				<strong><?php esc_html_e( 'Enable virtual /llms.txt', 'site-essentials' ); ?></strong>
			</label>
			<p class="description" style="margin-bottom:var(--scos-s-4)"><?php esc_html_e( 'Changing the enabled state will flush WordPress rewrite rules on save.', 'site-essentials' ); ?></p>

			<div id="scos-llms-editor" <?php echo $llms_opts['enabled'] ? '' : 'style="opacity:.5;pointer-events:none;"'; ?>>
				<label for="scos_llms_content" style="display:block;font-size:var(--scos-fs-lg);font-weight:600;color:var(--scos-ink);margin-bottom:var(--scos-s-2)">
					<?php esc_html_e( 'llms.txt content', 'site-essentials' ); ?>
				</label>
				<textarea name="scos_llms[content]" id="scos_llms_content" rows="16"
					class="scos-input scos-input--mono"
					placeholder="<?php esc_attr_e( '# Add your llms.txt content here…', 'site-essentials' ); ?>"><?php echo esc_textarea( $llms_opts['content'] ); ?></textarea>
				<p class="description" style="margin-top:var(--scos-s-1)"><?php esc_html_e( 'Plain text only. The file is served with Content-Type: text/plain and X-Robots-Tag: noindex.', 'site-essentials' ); ?></p>
			</div>
		</div>
	</div>

	<!-- ── EXIF / Metadata Stripping ────────────────────────────────── -->
	<h2 class="scos__section-label" style="margin-bottom:var(--scos-s-3)"><?php esc_html_e( 'EXIF / Metadata Stripping', 'site-essentials' ); ?></h2>

	<div class="scos-notice <?php echo $imagick_ok ? 'scos-notice--success' : 'scos-notice--warning'; ?>" style="margin-bottom:var(--scos-s-3)">
		<p>
			<?php if ( $imagick_ok ) : ?>
				<strong><?php esc_html_e( '✓ ImageMagick (Imagick) available.', 'site-essentials' ); ?></strong>
				<?php esc_html_e( 'Nuclear and selective strip are both supported. ICC color profiles are preserved in all modes.', 'site-essentials' ); ?>
			<?php else : ?>
				<strong><?php esc_html_e( '⚠ ImageMagick (Imagick) is not available.', 'site-essentials' ); ?></strong>
				<?php esc_html_e( 'Nuclear strip will use GD (re-save at 85% quality). Selective strip is not supported without Imagick — it will fall back to nuclear.', 'site-essentials' ); ?>
			<?php endif; ?>
		</p>
	</div>

	<!-- Auto-strip on upload -->
	<div class="scos-card" style="margin-bottom:var(--scos-s-4)">
		<div class="scos-card__header scos-card__header--plain">
			<h3 class="scos-card__title"><?php esc_html_e( 'Auto-strip on upload', 'site-essentials' ); ?></h3>
		</div>
		<div class="scos-card__body">
			<p class="description" style="margin-bottom:var(--scos-s-4)"><?php esc_html_e( 'Runs after Media Library Assistant (MLA) and other plugins have read the metadata (wp_generate_attachment_metadata, priority 99).', 'site-essentials' ); ?></p>

			<label class="scos-checkbox-row" style="padding:var(--scos-s-2) 0;cursor:pointer;align-items:center">
				<input type="radio" name="scos_exif[upload_mode]" value="disabled" <?php checked( $exif_opts['upload_mode'], 'disabled' ); ?>>
				<span>
					<strong><?php esc_html_e( 'Disabled', 'site-essentials' ); ?></strong>
					<span class="description" style="display:block;margin-top:2px"><?php esc_html_e( 'New uploads are not stripped automatically.', 'site-essentials' ); ?></span>
				</span>
			</label>

			<label class="scos-checkbox-row" style="padding:var(--scos-s-2) 0;cursor:pointer;align-items:center">
				<input type="radio" name="scos_exif[upload_mode]" value="nuclear" <?php checked( $exif_opts['upload_mode'], 'nuclear' ); ?>>
				<span>
					<strong><?php esc_html_e( 'Nuclear strip', 'site-essentials' ); ?></strong>
					<span class="description" style="display:block;margin-top:2px"><?php esc_html_e( 'Removes ALL metadata from every newly uploaded image (EXIF, IPTC, XMP). ICC color profile is preserved.', 'site-essentials' ); ?></span>
				</span>
			</label>

			<label class="scos-checkbox-row" style="padding:var(--scos-s-2) 0;cursor:pointer;align-items:center">
				<input type="radio" name="scos_exif[upload_mode]" value="selective"
					<?php checked( $exif_opts['upload_mode'], 'selective' ); ?>
					<?php echo ! $imagick_ok ? 'disabled' : ''; ?>>
				<span>
					<strong><?php esc_html_e( 'Selective strip', 'site-essentials' ); ?></strong>
					<?php if ( ! $imagick_ok ) : ?>
						<span class="scos-badge scos-badge--soft" style="margin-left:var(--scos-s-2)"><?php esc_html_e( 'requires Imagick', 'site-essentials' ); ?></span>
					<?php endif; ?>
					<span class="description" style="display:block;margin-top:2px"><?php esc_html_e( 'Strips everything then re-injects only the XMP fields listed in "Fields to Keep" below.', 'site-essentials' ); ?></span>
				</span>
			</label>
		</div>
	</div>

	<!-- Bulk strip actions -->
	<div class="scos-card" style="margin-bottom:var(--scos-s-4)">
		<div class="scos-card__header scos-card__header--plain">
			<h3 class="scos-card__title"><?php esc_html_e( 'Bulk Strip Existing Media', 'site-essentials' ); ?></h3>
		</div>
		<div class="scos-card__body">
			<p class="description" style="margin-bottom:var(--scos-s-3)">
				<?php esc_html_e( 'Processes all images currently in the media library. This modifies the original files on disk —', 'site-essentials' ); ?>
				<strong style="color:var(--scos-warning,#b45309)"><?php esc_html_e( 'back up your uploads folder before running.', 'site-essentials' ); ?></strong>
			</p>
			<div style="display:flex;gap:var(--scos-s-2);flex-wrap:wrap">
				<button type="button" id="scos-exif-nuclear-btn" class="scos-btn scos-btn--ghost"
					data-mode="nuclear" data-nonce="<?php echo esc_attr( $exif_bulk_nonce ); ?>">
					<?php esc_html_e( '☢ Nuclear Strip All Images', 'site-essentials' ); ?>
				</button>
				<button type="button" id="scos-exif-selective-btn" class="scos-btn scos-btn--ghost"
					data-mode="selective" data-nonce="<?php echo esc_attr( $exif_bulk_nonce ); ?>"
					<?php echo ! $imagick_ok ? 'disabled title="' . esc_attr__( 'Requires Imagick', 'site-essentials' ) . '"' : ''; ?>>
					<?php esc_html_e( '✦ Selective Strip All Images', 'site-essentials' ); ?>
				</button>
			</div>

			<div id="scos-exif-progress" style="display:none;margin-top:var(--scos-s-4)">
				<div style="background:var(--scos-surface-muted);border-radius:var(--scos-r-md);height:10px;overflow:hidden">
					<div id="scos-exif-bar" style="background:var(--scos-accent);height:100%;width:0;transition:width 0.15s ease"></div>
				</div>
				<p id="scos-exif-status" class="description" style="margin-top:var(--scos-s-1)"></p>
			</div>
		</div>
	</div>

	<!-- Fields to keep -->
	<div class="scos-card" style="margin-bottom:var(--scos-s-6)">
		<div class="scos-card__header scos-card__header--plain">
			<h3 class="scos-card__title"><?php esc_html_e( 'Fields to Keep (Selective Mode)', 'site-essentials' ); ?></h3>
		</div>
		<div class="scos-card__body">
			<p class="description" style="margin-bottom:var(--scos-s-3)">
				<?php esc_html_e( 'One XMP field per line in namespace:localname format. Lines starting with # are comments.', 'site-essentials' ); ?>
				<?php esc_html_e( 'Example:', 'site-essentials' ); ?> <code>dc:creator</code>, <code>xmpRights:Marked</code>, <code>Iptc4xmpCore:AltTextAccessibility</code>
			</p>
			<div style="display:flex;justify-content:flex-end;margin-bottom:var(--scos-s-2)">
				<button type="button" id="scos-exif-fields-reset" class="scos-btn scos-btn--ghost"
					data-default="<?php echo esc_attr( Exif_Stripper::default_keep_fields_str() ); ?>">
					<?php esc_html_e( '↺ Reset to defaults', 'site-essentials' ); ?>
				</button>
			</div>
			<textarea name="scos_exif[fields_to_keep]" id="scos_exif_fields" rows="18"
				class="scos-input scos-input--mono"><?php echo esc_textarea( $exif_opts['fields_to_keep'] ); ?></textarea>
			<p class="description" style="margin-top:var(--scos-s-1)"><?php esc_html_e( 'Note: EXIF binary and IPTC binary profiles are always fully stripped in selective mode. ICC color profile is always retained.', 'site-essentials' ); ?></p>
		</div>
	</div>

	<script>
	( function () {
		var robotsCb = document.getElementById( 'scos_robots_enabled' );
		var robotsEd = document.getElementById( 'scos-robots-editor' );
		if ( robotsCb && robotsEd ) {
			robotsCb.addEventListener( 'change', function () {
				robotsEd.style.opacity       = this.checked ? '' : '0.5';
				robotsEd.style.pointerEvents = this.checked ? '' : 'none';
			} );
		}

		var resetBtn = document.getElementById( 'scos-robots-reset' );
		var robotsTa = document.getElementById( 'scos_robots_content' );
		if ( resetBtn && robotsTa ) {
			resetBtn.addEventListener( 'click', function () {
				if ( window.confirm( '<?php echo esc_js( __( 'Replace the current content with the gold-standard template?', 'site-essentials' ) ); ?>' ) ) {
					robotsTa.value = this.dataset.default;
				}
			} );
		}

		var llmsCb = document.getElementById( 'scos_llms_enabled' );
		var llmsEd = document.getElementById( 'scos-llms-editor' );
		if ( llmsCb && llmsEd ) {
			llmsCb.addEventListener( 'change', function () {
				llmsEd.style.opacity       = this.checked ? '' : '0.5';
				llmsEd.style.pointerEvents = this.checked ? '' : 'none';
			} );
		}
	} )();
	</script>

	<script>
	( function () {
		var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
		var batchSize = 10;

		function runBulkStrip( mode, nonce ) {
			var progress = document.getElementById( 'scos-exif-progress' );
			var bar      = document.getElementById( 'scos-exif-bar' );
			var status   = document.getElementById( 'scos-exif-status' );

			progress.style.display = 'block';
			status.textContent = '<?php echo esc_js( __( 'Fetching image list…', 'site-essentials' ) ); ?>';
			bar.style.width = '0';

			var fd = new FormData();
			fd.append( 'action', 'scos_exif_get_ids' );
			fd.append( 'nonce', nonce );

			fetch( ajaxUrl, { method: 'POST', body: fd } )
				.then( function(r) { return r.json(); } )
				.then( function(resp) {
					if ( ! resp.success ) {
						status.textContent = 'Error: ' + ( resp.data.message || 'Unknown error' );
						return;
					}
					var ids   = resp.data.ids;
					var total = ids.length;
					if ( 0 === total ) {
						status.textContent = '<?php echo esc_js( __( 'No images found in the media library.', 'site-essentials' ) ); ?>';
						return;
					}
					processBatch( ids, 0, total, mode, nonce, { processed:0, failed:0, skipped:0 } );
				} )
				.catch( function(e) {
					status.textContent = 'Network error: ' + e.message;
				} );
		}

		function processBatch( ids, offset, total, mode, nonce, counts ) {
			var bar    = document.getElementById( 'scos-exif-bar' );
			var status = document.getElementById( 'scos-exif-status' );
			var batch  = ids.slice( offset, offset + batchSize );

			if ( 0 === batch.length ) {
				bar.style.width  = '100%';
				status.textContent = '<?php echo esc_js( __( 'Done.', 'site-essentials' ) ); ?> '
					+ counts.processed + ' <?php echo esc_js( __( 'processed', 'site-essentials' ) ); ?>'
					+ ( counts.failed  ? ', ' + counts.failed  + ' <?php echo esc_js( __( 'failed', 'site-essentials' ) ); ?>' : '' )
					+ ( counts.skipped ? ', ' + counts.skipped + ' <?php echo esc_js( __( 'skipped', 'site-essentials' ) ); ?>' : '' )
					+ '.';
				return;
			}

			var pct = Math.round( ( offset / total ) * 100 );
			bar.style.width     = pct + '%';
			status.textContent  = offset + ' / ' + total + ' — '
				+ '<?php echo esc_js( __( 'processing…', 'site-essentials' ) ); ?>';

			var fd = new FormData();
			fd.append( 'action', 'scos_exif_strip_batch' );
			fd.append( 'nonce', nonce );
			fd.append( 'mode',  mode );
			fd.append( 'ids',   JSON.stringify( batch ) );

			fetch( ajaxUrl, { method: 'POST', body: fd } )
				.then( function(r) { return r.json(); } )
				.then( function(resp) {
					if ( resp.success ) {
						counts.processed += resp.data.processed;
						counts.failed    += resp.data.failed.length;
						counts.skipped   += resp.data.skipped;
					}
					processBatch( ids, offset + batchSize, total, mode, nonce, counts );
				} )
				.catch( function() {
					setTimeout( function() {
						processBatch( ids, offset, total, mode, nonce, counts );
					}, 2000 );
				} );
		}

		[ 'scos-exif-nuclear-btn', 'scos-exif-selective-btn' ].forEach( function( id ) {
			var btn = document.getElementById( id );
			if ( ! btn ) return;
			btn.addEventListener( 'click', function() {
				var confirm_msg = '<?php echo esc_js( __( 'This will permanently modify image files on disk. Are you sure? (Back up your uploads folder first.)', 'site-essentials' ) ); ?>';
				if ( ! window.confirm( confirm_msg ) ) return;
				runBulkStrip( this.dataset.mode, this.dataset.nonce );
			} );
		} );

		var fieldsReset = document.getElementById( 'scos-exif-fields-reset' );
		var fieldsTa    = document.getElementById( 'scos_exif_fields' );
		if ( fieldsReset && fieldsTa ) {
			fieldsReset.addEventListener( 'click', function() {
				if ( window.confirm( '<?php echo esc_js( __( 'Reset fields to defaults?', 'site-essentials' ) ); ?>' ) ) {
					fieldsTa.value = this.dataset.default;
				}
			} );
		}
	} )();
	</script>

	<!-- ── Other ────────────────────────────────────────────────────── -->
	<h2 class="scos__section-label" style="margin-bottom:var(--scos-s-3)"><?php esc_html_e( 'Other', 'site-essentials' ); ?></h2>

	<div class="scos-card" style="margin-bottom:var(--scos-s-6)">
		<div class="scos-card__header scos-card__header--plain">
			<h3 class="scos-card__title"><?php esc_html_e( 'Excerpt', 'site-essentials' ); ?></h3>
		</div>
		<div class="scos-card__body">
			<p class="description" style="margin-bottom:var(--scos-s-4)">
				<?php esc_html_e( 'Sets the fallback word count for WordPress auto-generated excerpts. Only applies when no manual excerpt is set and only to the WP excerpt API — it does not affect Breakdance\'s "Limit Characters" setting in the builder, which runs independently.', 'site-essentials' ); ?>
			</p>

			<div style="display:flex;align-items:flex-start;gap:var(--scos-s-4);flex-wrap:wrap">
				<label class="scos-checkbox-row" style="flex:1;min-width:220px;cursor:pointer;padding:0">
					<input type="checkbox"
						name="scos_image_seo[excerpt_length_enabled]"
						id="scos_excerpt_enabled"
						value="1"
						<?php checked( $opts['excerpt_length_enabled'] ); ?>>
					<span>
						<strong><?php esc_html_e( 'Limit auto-excerpt word count', 'site-essentials' ); ?></strong>
						<span class="description" style="display:block;margin-top:2px">
							<?php esc_html_e( 'Overrides WordPress\'s default of 55 words. Has no effect when a manual excerpt is set on the post.', 'site-essentials' ); ?>
						</span>
					</span>
				</label>

				<div style="display:flex;align-items:center;gap:var(--scos-s-2);flex-shrink:0;padding-top:2px">
					<input type="number"
						name="scos_image_seo[excerpt_length_words]"
						id="scos_excerpt_words"
						value="<?php echo esc_attr( (int) $opts['excerpt_length_words'] ); ?>"
						min="5"
						max="200"
						class="scos-input"
						style="width:72px;text-align:center"
						<?php echo empty( $opts['excerpt_length_enabled'] ) ? 'disabled' : ''; ?>>
					<span style="color:var(--scos-ink-subtle);font-size:var(--scos-fs-sm);white-space:nowrap">
						<?php esc_html_e( 'words', 'site-essentials' ); ?>
					</span>
				</div>
			</div>
		</div>
	</div>

	<script>
	( function () {
		var excerptCb    = document.getElementById( 'scos_excerpt_enabled' );
		var excerptWords = document.getElementById( 'scos_excerpt_words' );
		if ( excerptCb && excerptWords ) {
			excerptCb.addEventListener( 'change', function () {
				excerptWords.disabled = ! this.checked;
				if ( this.checked ) {
					excerptWords.focus();
				}
			} );
		}
	} )();
	</script>

	<div class="scos-save-bar">
		<button type="submit" class="scos-btn scos-btn--primary">
			<?php esc_html_e( 'Save Advanced SEO Settings', 'site-essentials' ); ?>
		</button>
	</div>

</form>
