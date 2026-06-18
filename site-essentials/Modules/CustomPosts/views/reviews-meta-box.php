<?php
/**
 * Reviews CPT — Review Details Meta Box
 *
 * Rendered by Cpt_Module::render_reviews_meta_box().
 *
 * Variables available (set in render_reviews_meta_box before include):
 *
 * @var WP_Post  $post            Current post
 * @var string   $rating          bw_rating value (1–5)
 * @var string   $date            bw_date value (YYYY-MM-DD)
 * @var string   $date_precision  bw_date_precision value (year|month-year|full)
 * @var string   $verify_url      bw_verify_url value
 * @var string   $schema_id       bw_schema_id value
 * @var string   $success_outcome bw_success_outcome value
 * @var string   $customer_detail bw_customer_detail value
 * @var string   $is_featured     bw_is_featured value (1|0|'')
 * @var string   $review_excerpt  bw_review_excerpt value
 * @var int      $related_project bw_related_project value (project post ID, 0 when none)
 * @var WP_Post[] $projects        Published projects list (empty when ACF is active)
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<style>
.bw-reviews-meta table.form-table th { width: 200px; }
.bw-reviews-meta .bw-schema-warning { color: #a00; font-style: italic; font-size: 12px; margin-top: 4px; }
.bw-reviews-meta .description { margin-top: 4px; }
</style>

<div class="bw-reviews-meta">
    <table class="form-table" role="presentation">
        <tbody>

            <!-- ── Related Project (native — only shown when ACF is not active) ── -->
            <?php if ( ! function_exists( 'get_field' ) && ! empty( $projects ) ) : ?>
            <tr>
                <th scope="row">
                    <label for="bw_related_project"><?php esc_html_e( 'Related Project', 'site-essentials' ); ?></label>
                </th>
                <td>
                    <select id="bw_related_project" name="bw_related_project">
                        <option value="0"><?php esc_html_e( '— None —', 'site-essentials' ); ?></option>
                        <?php foreach ( $projects as $project ) : ?>
                        <option value="<?php echo esc_attr( $project->ID ); ?>"
                                <?php selected( $related_project, $project->ID ); ?>>
                            <?php echo esc_html( $project->post_title ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Link this review to a project/success story. Used to display reviews on project single templates.', 'site-essentials' ); ?></p>
                </td>
            </tr>
            <?php elseif ( ! function_exists( 'get_field' ) ) : ?>
            <tr>
                <th scope="row"><?php esc_html_e( 'Related Project', 'site-essentials' ); ?></th>
                <td><p class="description"><?php esc_html_e( 'No published projects found. Create a project first.', 'site-essentials' ); ?></p></td>
            </tr>
            <?php endif; ?>

            <!-- ── Rating ────────────────────────────────────────── -->
            <tr>
                <th scope="row">
                    <label for="bw_rating"><?php esc_html_e('Rating', 'site-essentials'); ?></label>
                </th>
                <td>
                    <input type="number"
                           id="bw_rating"
                           name="bw_rating"
                           value="<?php echo esc_attr($rating); ?>"
                           min="1"
                           max="5"
                           step="1"
                           style="width:80px;">
                    <p class="description"><?php esc_html_e('1–5, integer only.', 'site-essentials'); ?></p>
                </td>
            </tr>

            <!-- ── Review Date ───────────────────────────────────── -->
            <tr>
                <th scope="row">
                    <label for="bw_date"><?php esc_html_e('Review Date', 'site-essentials'); ?></label>
                </th>
                <td>
                    <input type="date"
                           id="bw_date"
                           name="bw_date"
                           value="<?php echo esc_attr($date); ?>">
                    <p class="description"><?php esc_html_e('Stored as YYYY-MM-DD.', 'site-essentials'); ?></p>
                </td>
            </tr>

            <!-- ── Date Precision ────────────────────────────────── -->
            <tr>
                <th scope="row">
                    <label for="bw_date_precision"><?php esc_html_e('Date Precision', 'site-essentials'); ?></label>
                </th>
                <td>
                    <select id="bw_date_precision" name="bw_date_precision">
                        <option value="full"       <?php selected($date_precision, 'full'); ?>>
                            <?php esc_html_e('Full date (day / month / year)', 'site-essentials'); ?>
                        </option>
                        <option value="month-year" <?php selected($date_precision, 'month-year'); ?>>
                            <?php esc_html_e('Month and year only', 'site-essentials'); ?>
                        </option>
                        <option value="year"       <?php selected($date_precision, 'year'); ?>>
                            <?php esc_html_e('Year only', 'site-essentials'); ?>
                        </option>
                    </select>
                    <p class="description"><?php esc_html_e('Controls how the date is displayed in templates and shortcodes.', 'site-essentials'); ?></p>
                </td>
            </tr>

            <!-- ── Review Source URL ─────────────────────────────── -->
            <tr>
                <th scope="row">
                    <label for="bw_verify_url"><?php esc_html_e('Review Source URL', 'site-essentials'); ?></label>
                </th>
                <td>
                    <input type="url"
                           id="bw_verify_url"
                           name="bw_verify_url"
                           value="<?php echo esc_attr($verify_url); ?>"
                           class="large-text"
                           placeholder="https://">
                    <p class="description"><?php esc_html_e('Link to the live review on Google, Facebook, Trustpilot, etc.', 'site-essentials'); ?></p>
                </td>
            </tr>

            <!-- ── Schema ID ─────────────────────────────────────── -->
            <tr>
                <th scope="row">
                    <label for="bw_schema_id"><?php esc_html_e('Schema ID', 'site-essentials'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="bw_schema_id"
                           name="bw_schema_id"
                           value="<?php echo esc_attr($schema_id); ?>"
                           class="regular-text">
                    <?php if (!empty($schema_id)) : ?>
                        <p class="description bw-schema-warning">
                            ⚠️ <?php esc_html_e('Changing this ID will break any schema or AI references using it. Only edit if you know what you are doing.', 'site-essentials'); ?>
                        </p>
                    <?php else : ?>
                        <p class="description">
                            <?php esc_html_e('Auto-generated on first save from customer name + platform (e.g. jane-smith-google). Leave blank to auto-generate.', 'site-essentials'); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>

            <!-- ── Success Outcome ───────────────────────────────── -->
            <tr>
                <th scope="row">
                    <label for="bw_success_outcome"><?php esc_html_e('Success Outcome', 'site-essentials'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="bw_success_outcome"
                           name="bw_success_outcome"
                           value="<?php echo esc_attr($success_outcome); ?>"
                           class="large-text"
                           maxlength="150">
                    <p class="description"><?php esc_html_e('What this review proves. ~100 chars. e.g. "Completed full garden redesign on time and budget".', 'site-essentials'); ?></p>
                </td>
            </tr>

            <!-- ── Customer Detail ───────────────────────────────── -->
            <tr>
                <th scope="row">
                    <label for="bw_customer_detail"><?php esc_html_e('Customer Detail', 'site-essentials'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="bw_customer_detail"
                           name="bw_customer_detail"
                           value="<?php echo esc_attr($customer_detail); ?>"
                           class="large-text"
                           maxlength="150">
                    <p class="description"><?php esc_html_e('Second line — company name, suburb, or role.', 'site-essentials'); ?></p>
                </td>
            </tr>

            <!-- ── Featured ──────────────────────────────────────── -->
            <tr>
                <th scope="row"><?php esc_html_e('Featured', 'site-essentials'); ?></th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="bw_is_featured"
                               value="1"
                               <?php checked($is_featured, '1'); ?>>
                        <?php esc_html_e('Mark as featured review', 'site-essentials'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Stored as 1/0. Queryable via meta_query in WP_Query loops.', 'site-essentials'); ?></p>
                </td>
            </tr>

            <!-- ── Review Excerpt ────────────────────────────────── -->
            <tr>
                <th scope="row">
                    <label for="bw_review_excerpt"><?php esc_html_e('Review Excerpt', 'site-essentials'); ?></label>
                </th>
                <td>
                    <textarea id="bw_review_excerpt"
                              name="bw_review_excerpt"
                              class="large-text"
                              rows="3"
                              maxlength="200"><?php echo esc_textarea($review_excerpt); ?></textarea>
                    <p class="description"><?php esc_html_e('Optional. ~150 char curated pull-quote for loops. Falls back to auto-truncation of review text if left empty.', 'site-essentials'); ?></p>
                </td>
            </tr>

        </tbody>
    </table>
</div>
