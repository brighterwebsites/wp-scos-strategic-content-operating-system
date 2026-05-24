<?php
/**
 * CPT — Review System tab.
 *
 * v1.0 | 2026-05-19
 *
 * TODO: migrate Reviews bw_* keys to scos_review_* / scos_cpt_* — see meta-key-prefixes.mdc.
 * Legacy keys still in use:
 *   - Post type:  bw_reviews
 *   - Taxonomy:   bw_review_platform
 *   - Meta:       bw_rating, bw_date, bw_date_precision, bw_verify_url,
 *                 bw_schema_id, bw_success_outcome, bw_customer_detail,
 *                 bw_is_featured, bw_review_excerpt
 *   - ACF:        bw_related_project, bw_reviews_related
 *   - Shortcodes: [bw_review_*]
 *
 * Variables in scope from cpt-page.php:
 * @var array  $opts
 * @var string $guide_reviews
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="scos-card">
	<div class="scos-card__header">
		<h2 class="scos-card__title"><?php esc_html_e( 'Review System', 'site-essentials' ); ?></h2>
		<p class="scos-card__desc">
			<?php esc_html_e( 'Reviews are a structured data source (SSOT). There is no public archive or single URLs — query via WP_Query using post_type=bw_reviews, combined with tax_query (bw_review_platform) and meta_query.', 'site-essentials' ); ?>
		</p>
	</div>
	<div class="scos-card__body">
		<p>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bw_reviews' ) ); ?>" class="scos-btn scos-btn--ghost">
				<?php esc_html_e( 'All Reviews', 'site-essentials' ); ?>
			</a>
			&nbsp;
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=bw_reviews' ) ); ?>" class="scos-btn scos-btn--ghost">
				<?php esc_html_e( 'Add New Review', 'site-essentials' ); ?>
			</a>
			&nbsp;
			<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=bw_review_platform&post_type=bw_reviews' ) ); ?>" class="scos-btn scos-btn--ghost">
				<?php esc_html_e( 'Manage Platforms', 'site-essentials' ); ?>
			</a>
		</p>
		<p class="description">
			<?php esc_html_e( 'Recommendation: Exclude from sitemap, add /reviews/ to robots.txt.', 'site-essentials' ); ?>
		</p>
	</div>
</div>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
	<?php wp_nonce_field( 'bw_reviews_csv_import', 'bw_reviews_csv_nonce' ); ?>
	<input type="hidden" name="action" value="site_essentials_reviews_csv_import">

	<div class="scos-card">
		<div class="scos-card__header">
			<h2 class="scos-card__title"><?php esc_html_e( 'Import reviews from CSV', 'site-essentials' ); ?></h2>
			<p class="scos-card__desc">
				<?php esc_html_e( 'Upload a CSV to bulk-import reviews. Rows where customer_name is empty are skipped. Platform terms are matched by name (case-insensitive) — new terms are created automatically.', 'site-essentials' ); ?>
			</p>
		</div>
		<div class="scos-card__body">
			<p><strong><?php esc_html_e( 'Required CSV headers (case-sensitive):', 'site-essentials' ); ?></strong></p>
			<code style="display:block;padding:8px 12px;background:var(--scos-surface-muted, #f6f7f7);border:1px solid var(--scos-border, #ddd);border-radius:var(--scos-r-md, 4px);margin-bottom:16px;font-size:12px;word-break:break-all;font-family:var(--scos-font-mono);">
				customer_name, review_text, rating, platform, date, date_precision, verify_url, success_outcome, customer_detail, is_featured, review_excerpt
			</code>

			<table class="scos-form">
				<tbody>
					<tr>
						<th>
							<label for="bw_reviews_csv"><?php esc_html_e( 'CSV file', 'site-essentials' ); ?></label>
							<div class="scos-form__slug">bw_reviews_csv</div>
						</th>
						<td>
							<input type="file" id="bw_reviews_csv" name="bw_reviews_csv" accept=".csv,text/csv">
							<p class="description"><?php esc_html_e( 'Upload a .csv file with the headers listed above.', 'site-essentials' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="scos-card__footer">
			<button type="submit" class="scos-btn scos-btn--primary">
				<?php esc_html_e( 'Import reviews', 'site-essentials' ); ?>
			</button>
		</div>
	</div>
</form>
