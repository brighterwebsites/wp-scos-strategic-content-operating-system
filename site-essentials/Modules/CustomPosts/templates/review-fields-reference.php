<?php
/**
 * Reviews CPT — Custom Field Output Reference
 *
 * This file documents every custom field registered on the bw_reviews post type,
 * with PHP output examples and shortcode equivalents.
 *
 * USE CASE: Vanessa / Breakdance — use these snippets as dynamic data source
 * references, or drop shortcodes directly into Breakdance text elements.
 *
 * POST TYPE:  bw_reviews
 * TAXONOMY:   bw_review_platform
 * ALL META KEYS ARE PREFIXED: bw_
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * QUICK REFERENCE TABLE
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * | Meta Key              | Type     | Shortcode                              |
 * |-----------------------|----------|----------------------------------------|
 * | bw_rating             | int 1–5  | [bw_review_rating id="X"]              |
 * | bw_date               | YYYY-MM-DD | [bw_review_date id="X"]              |
 * | bw_date_precision     | select   | (used internally by date shortcode)    |
 * | bw_verify_url         | URL      | [bw_review_verify_url id="X"]          |
 * | bw_schema_id          | text     | [bw_review_schema_id id="X"]           |
 * | bw_success_outcome    | text     | [bw_review_outcome id="X"]             |
 * | bw_customer_detail    | text     | [bw_review_customer_detail id="X"]     |
 * | bw_is_featured        | 1/0      | [bw_review_featured id="X"]            |
 * | bw_review_excerpt     | textarea | [bw_review_excerpt id="X"]             |
 * | post_title            | text     | (standard: get_the_title())            |
 * | post_content          | text     | (standard: get_the_content())          |
 * | bw_review_platform    | taxonomy | (standard: get_the_terms())            |
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 * @version    1.0.0
 */

// This file is a reference/template only — it should not be executed directly.
if (!defined('ABSPATH')) {
    // Allow viewing as plain text in dev tools, but stop execution
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: text/plain');
    }
}

/*
 * ════════════════════════════════════════════════════════════════════════════
 * SECTION 1 — QUERYING REVIEWS WITH WP_QUERY
 * ════════════════════════════════════════════════════════════════════════════
 *
 * bw_reviews is a non-public SSOT post type. It has no front-end URL but it
 * IS fully queryable via WP_Query, which is what Breakdance loops use.
 *
 * EXAMPLE A: All published reviews
 */

/*
$query = new WP_Query([
    'post_type'      => 'bw_reviews',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'meta_value_num',
    'meta_key'       => 'bw_date',
    'order'          => 'DESC',
]);
*/

/*
 * EXAMPLE B: Featured reviews only
 */

/*
$query = new WP_Query([
    'post_type'      => 'bw_reviews',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
    'meta_query'     => [
        [
            'key'   => 'bw_is_featured',
            'value' => '1',
        ],
    ],
]);
*/

/*
 * EXAMPLE C: Filter by platform (Google reviews only)
 */

/*
$query = new WP_Query([
    'post_type'      => 'bw_reviews',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'tax_query'      => [
        [
            'taxonomy' => 'bw_review_platform',
            'field'    => 'slug',
            'terms'    => 'google',
        ],
    ],
]);
*/

/*
 * EXAMPLE D: 5-star Google reviews, most recent first
 */

/*
$query = new WP_Query([
    'post_type'      => 'bw_reviews',
    'post_status'    => 'publish',
    'posts_per_page' => 10,
    'tax_query'      => [
        [
            'taxonomy' => 'bw_review_platform',
            'field'    => 'slug',
            'terms'    => 'google',
        ],
    ],
    'meta_query'     => [
        [
            'key'     => 'bw_rating',
            'value'   => 5,
            'compare' => '=',
            'type'    => 'NUMERIC',
        ],
    ],
    'orderby'        => 'meta_value',
    'meta_key'       => 'bw_date',
    'order'          => 'DESC',
]);
*/


/*
 * ════════════════════════════════════════════════════════════════════════════
 * SECTION 2 — FIELD OUTPUT EXAMPLES (PHP, for use inside a WP_Query loop)
 * ════════════════════════════════════════════════════════════════════════════
 *
 * Assumes you are inside a while ( $query->have_posts() ) : $query->the_post(); loop,
 * or that the global post is set to the correct bw_reviews post.
 */

/*
// ── Customer Name (post title) ───────────────────────────────────────────
$customer_name = get_the_title();
// Output: "Jane Smith"

// ── Review Text (post content) ───────────────────────────────────────────
$review_text = get_the_content();
// Or for display (applies filters, wraps in <p>):
// $review_text = apply_filters( 'the_content', $review_text );

// ── Rating (1–5 integer) ─────────────────────────────────────────────────
$post_id = get_the_ID();
$rating  = get_post_meta( $post_id, 'bw_rating', true );
// Output: "5"

// ── Review Date ──────────────────────────────────────────────────────────
// Raw stored value (YYYY-MM-DD):
$date_raw = get_post_meta( $post_id, 'bw_date', true );
// Output: "2024-11-15"

// Formatted using bw_date_precision:
$precision = get_post_meta( $post_id, 'bw_date_precision', true ) ?: 'full';
$timestamp = $date_raw ? strtotime( $date_raw ) : 0;

if ( $timestamp ) {
    switch ( $precision ) {
        case 'year':
            $date_formatted = date_i18n( 'Y', $timestamp );
            // Output: "2024"
            break;
        case 'month-year':
            $date_formatted = date_i18n( 'F Y', $timestamp );
            // Output: "November 2024"
            break;
        case 'full':
        default:
            $date_formatted = date_i18n( get_option( 'date_format' ), $timestamp );
            // Output: "15 November 2024" (respects WordPress date format setting)
    }
}

// ── Review Source URL (Verify URL) ───────────────────────────────────────
$verify_url = get_post_meta( $post_id, 'bw_verify_url', true );
// Output: "https://g.co/kgs/abc123"
// Wrap in link:
// echo '<a href="' . esc_url( $verify_url ) . '" target="_blank" rel="noopener">View on Google</a>';

// ── Schema ID ────────────────────────────────────────────────────────────
$schema_id = get_post_meta( $post_id, 'bw_schema_id', true );
// Output: "jane-smith-google"
// Use as schema @id: home_url( '/#review-' . $schema_id )

// ── Success Outcome ──────────────────────────────────────────────────────
$success_outcome = get_post_meta( $post_id, 'bw_success_outcome', true );
// Output: "Completed full garden redesign on time and budget"

// ── Customer Detail ──────────────────────────────────────────────────────
$customer_detail = get_post_meta( $post_id, 'bw_customer_detail', true );
// Output: "Ballarat, VIC"

// ── Is Featured ──────────────────────────────────────────────────────────
$is_featured = get_post_meta( $post_id, 'bw_is_featured', true );
// Output: "1" or "0"
// Boolean check:
// if ( $is_featured === '1' ) { ... }

// ── Review Excerpt ───────────────────────────────────────────────────────
$review_excerpt = get_post_meta( $post_id, 'bw_review_excerpt', true );
// If empty, fall back to auto-truncated post_content:
if ( empty( $review_excerpt ) ) {
    $post_obj       = get_post( $post_id );
    $review_excerpt = wp_trim_words( wp_strip_all_tags( $post_obj->post_content ), 25, '&hellip;' );
}
// Output: "The team transformed our backyard completely. Very professional…"

// ── Platform Taxonomy ────────────────────────────────────────────────────
$platforms = get_the_terms( $post_id, 'bw_review_platform' );
if ( $platforms && ! is_wp_error( $platforms ) ) {
    $platform_name = $platforms[0]->name; // "Google"
    $platform_slug = $platforms[0]->slug; // "google"
}
*/


/*
 * ════════════════════════════════════════════════════════════════════════════
 * SECTION 3 — SHORTCODE REFERENCE
 * ════════════════════════════════════════════════════════════════════════════
 *
 * All shortcodes accept an optional id attribute.
 * If id is omitted, the current post ID is used (for inside Breakdance loops).
 *
 * ── Usage with explicit ID ───────────────────────────────────────────────
 * [bw_review_rating id="42"]
 * [bw_review_date id="42"]
 * [bw_review_verify_url id="42"]
 * [bw_review_schema_id id="42"]
 * [bw_review_outcome id="42"]
 * [bw_review_customer_detail id="42"]
 * [bw_review_excerpt id="42"]
 * [bw_review_featured id="42"]
 *
 * ── Usage inside loops (no id needed) ───────────────────────────────────
 * [bw_review_rating]
 * [bw_review_date]
 * [bw_review_verify_url]
 * [bw_review_schema_id]
 * [bw_review_outcome]
 * [bw_review_customer_detail]
 * [bw_review_excerpt]
 * [bw_review_featured]
 *
 * ── Shortcode output descriptions ────────────────────────────────────────
 *
 * [bw_review_rating]
 *   Returns the star rating as an integer string: "5"
 *   Use in a template for CSS star rendering.
 *
 * [bw_review_date]
 *   Returns the formatted date respecting bw_date_precision.
 *   year       → "2024"
 *   month-year → "November 2024"
 *   full       → "15 November 2024" (uses WP date format setting)
 *
 * [bw_review_verify_url]
 *   Returns the raw verify URL: "https://g.co/kgs/abc123"
 *   Wrap manually in an anchor if needed:
 *   <a href="[bw_review_verify_url]" target="_blank">View review</a>
 *
 * [bw_review_schema_id]
 *   Returns the stable schema ID string: "jane-smith-google"
 *   Used as the @id reference in Review schema (Phase 2).
 *
 * [bw_review_outcome]
 *   Returns the success outcome text: "Completed full redesign on time"
 *
 * [bw_review_customer_detail]
 *   Returns the customer second line: "Ballarat, VIC"
 *
 * [bw_review_excerpt]
 *   Returns the curated excerpt, or auto-truncates post_content (~150 chars)
 *   if the excerpt field is empty.
 *
 * [bw_review_featured]
 *   Returns "1" if featured, "0" if not. Use in conditional logic.
 */


/*
 * ════════════════════════════════════════════════════════════════════════════
 * SECTION 4 — EXAMPLE REVIEW CARD TEMPLATE (PHP)
 * ════════════════════════════════════════════════════════════════════════════
 *
 * Paste this into a custom PHP Breakdance element or a template part.
 * Assumes $post_id is set (e.g. inside a WP_Query loop).
 */

/*
// ── Example review card ───────────────────────────────────────────────────
$post_id         = get_the_ID();
$customer_name   = get_the_title();
$rating          = get_post_meta( $post_id, 'bw_rating', true );
$date_raw        = get_post_meta( $post_id, 'bw_date', true );
$date_precision  = get_post_meta( $post_id, 'bw_date_precision', true ) ?: 'full';
$verify_url      = get_post_meta( $post_id, 'bw_verify_url', true );
$success_outcome = get_post_meta( $post_id, 'bw_success_outcome', true );
$customer_detail = get_post_meta( $post_id, 'bw_customer_detail', true );
$review_excerpt  = get_post_meta( $post_id, 'bw_review_excerpt', true );
$platforms       = get_the_terms( $post_id, 'bw_review_platform' );
$platform_name   = ( $platforms && ! is_wp_error( $platforms ) ) ? $platforms[0]->name : '';

// Format date
$date_formatted = '';
if ( $date_raw ) {
    $ts = strtotime( $date_raw );
    if ( $ts ) {
        switch ( $date_precision ) {
            case 'year':       $date_formatted = date_i18n( 'Y', $ts ); break;
            case 'month-year': $date_formatted = date_i18n( 'F Y', $ts ); break;
            default:           $date_formatted = date_i18n( get_option( 'date_format' ), $ts );
        }
    }
}

// Fallback excerpt
if ( empty( $review_excerpt ) ) {
    $post_obj       = get_post( $post_id );
    $review_excerpt = wp_trim_words( wp_strip_all_tags( $post_obj->post_content ), 25, '&hellip;' );
}

// Stars HTML (simple)
$stars_html = '';
for ( $i = 1; $i <= 5; $i++ ) {
    $stars_html .= ( $i <= intval( $rating ) ) ? '★' : '☆';
}
?>

<article class="bw-review-card" itemscope itemtype="https://schema.org/Review">
    <div class="bw-review-card__stars" aria-label="<?php echo esc_attr( $rating ) . ' out of 5 stars'; ?>">
        <?php echo esc_html( $stars_html ); ?>
    </div>

    <?php if ( $review_excerpt ) : ?>
    <blockquote class="bw-review-card__excerpt" itemprop="reviewBody">
        <p><?php echo esc_html( $review_excerpt ); ?></p>
    </blockquote>
    <?php endif; ?>

    <?php if ( $success_outcome ) : ?>
    <p class="bw-review-card__outcome"><?php echo esc_html( $success_outcome ); ?></p>
    <?php endif; ?>

    <footer class="bw-review-card__footer">
        <strong class="bw-review-card__name" itemprop="author"><?php echo esc_html( $customer_name ); ?></strong>

        <?php if ( $customer_detail ) : ?>
        <span class="bw-review-card__detail"><?php echo esc_html( $customer_detail ); ?></span>
        <?php endif; ?>

        <?php if ( $platform_name ) : ?>
        <span class="bw-review-card__platform"><?php echo esc_html( $platform_name ); ?></span>
        <?php endif; ?>

        <?php if ( $date_formatted ) : ?>
        <time class="bw-review-card__date" datetime="<?php echo esc_attr( $date_raw ); ?>">
            <?php echo esc_html( $date_formatted ); ?>
        </time>
        <?php endif; ?>

        <?php if ( $verify_url ) : ?>
        <a class="bw-review-card__verify"
           href="<?php echo esc_url( $verify_url ); ?>"
           target="_blank"
           rel="noopener noreferrer">
            <?php esc_html_e( 'Verify review', 'site-essentials' ); ?>
        </a>
        <?php endif; ?>
    </footer>
</article>
*/


/*
 * ════════════════════════════════════════════════════════════════════════════
 * SECTION 5 — FUTURE INTEGRATIONS (DO NOT BUILD IN PHASE 1)
 * ════════════════════════════════════════════════════════════════════════════
 *
 * Schema Module (Phase 2):
 *   bw_reviews will be a data source for Review and AggregateRating schema.
 *   bw_schema_id is the stable reference key — do not change it once set.
 *
 * AI / llms.txt (Phase 2):
 *   Dynamic reviews.md endpoint will query all published bw_reviews and output
 *   structured markdown. Uses:
 *     - bw_schema_id as the identifier
 *     - bw_success_outcome as the proof context
 *     - bw_verify_url as the verification link
 *
 * Airtable Sync (Future):
 *   One-way push from bw_reviews to Claims table and Verification Artifacts table.
 *   bw_verify_url maps to Verification Artifacts.
 *   ACF relationship to Projects maps to Usage Tracking.
 *
 * Google Reviews API (Future):
 *   Auto-import of latest reviews. bw_reviews is the SSOT — API feeds into it.
 *
 * Breakdance Custom Block (Future):
 *   Aggregated review card block. Queries bw_reviews filtered by bw_is_featured
 *   or bw_review_platform taxonomy.
 */
