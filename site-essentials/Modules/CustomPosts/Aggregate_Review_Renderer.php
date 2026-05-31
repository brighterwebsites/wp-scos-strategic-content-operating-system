<?php
// v1.0 | 2026-06-01

/**
 * Aggregate Review Renderer
 *
 * Renders a review aggregate widget (count + average + platform logo + stars).
 * Called by [bw_aggregate_review] shortcode and the SCOS Aggregate Review BDE element.
 *
 * Layouts:
 *   simple       — "[avg] From [count] Reviews" text only
 *   google-simple — platform logo + star row
 *   google-full  — platform logo + stars + business name + link
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 */

namespace SiteEssentials\Modules\CustomPosts;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aggregate_Review_Renderer {

    const LAYOUTS = [ 'simple', 'google-simple', 'google-full' ];

    // =========================================================================
    // SHORTCODE ENTRY POINT
    // =========================================================================

    public function shortcode( array $atts ): string {
        $atts = shortcode_atts( [
            'layout'      => 'google-full',
            'platform'    => 'google',
            'reviews_url' => '',
            'show_icon'   => '1',
            'show_stars'  => '1',
            'show_name'   => '1',
            'show_link'   => '1',
            'decimals'    => '1',
        ], $atts, 'bw_aggregate_review' );

        return $this->render( $atts );
    }

    /**
     * Core render method — reusable from WP-CLI / MCP tool calls.
     *
     * @param array $atts Display options.
     * @return string HTML output.
     */
    public function render( array $atts = [] ): string {
        $layout = in_array( $atts['layout'] ?? 'google-full', self::LAYOUTS, true )
            ? $atts['layout']
            : 'google-full';

        $platform_slug = sanitize_title( $atts['platform'] ?? 'google' );
        $decimals      = max( 0, min( 2, intval( $atts['decimals'] ?? 1 ) ) );

        $show = [
            'icon'  => ( $atts['show_icon']  ?? '1' ) !== '0',
            'stars' => ( $atts['show_stars'] ?? '1' ) !== '0',
            'name'  => ( $atts['show_name']  ?? '1' ) !== '0',
            'link'  => ( $atts['show_link']  ?? '1' ) !== '0',
        ];

        // Simple layout aggregates across all platforms; google layouts filter by slug.
        $filter_slug = ( $layout !== 'simple' ) ? $platform_slug : '';

        $data = $this->get_aggregate_data( $filter_slug, $platform_slug, $decimals );

        if ( $data['count'] === 0 ) {
            if ( defined( 'BREAKDANCE_BUILDER' ) && BREAKDANCE_BUILDER ) {
                return '<div class="bde-aggregate-review__placeholder">No published reviews found for this platform.</div>';
            }
            return '';
        }

        $data['reviews_url'] = esc_url_raw( $atts['reviews_url'] ?? '' );

        ob_start();
        $this->render_card( $data, $layout, $show );
        return (string) ob_get_clean();
    }

    // =========================================================================
    // DATA
    // =========================================================================

    private function get_aggregate_data( string $filter_slug, string $platform_slug, int $decimals ): array {
        $args = [
            'post_type'              => 'bw_reviews',
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ];

        if ( $filter_slug ) {
            $args['tax_query'] = [ [
                'taxonomy' => 'bw_review_platform',
                'field'    => 'slug',
                'terms'    => $filter_slug,
            ] ];
        }

        $query = new \WP_Query( $args );
        $total = 0.0;
        $count = 0;

        foreach ( $query->posts as $post_id ) {
            $rating = get_post_meta( (int) $post_id, 'bw_rating', true );
            if ( $rating !== '' && is_numeric( $rating ) ) {
                $total += (float) $rating;
                $count++;
            }
        }

        $raw_avg = $count > 0 ? $total / $count : 0.0;
        $average = round( $raw_avg, $decimals );

        // Platform term + logo
        $platform_term = get_term_by( 'slug', $platform_slug, 'bw_review_platform' );
        $logo_id       = 0;
        $platform_name = '';
        if ( $platform_term && ! is_wp_error( $platform_term ) ) {
            $logo_id       = absint( get_term_meta( $platform_term->term_id, 'bw_platform_logo_id', true ) );
            $platform_name = $platform_term->name;
        }

        return [
            'count'         => $count,
            'average'       => number_format( $average, $decimals ),
            'average_raw'   => $raw_avg,
            'platform_name' => $platform_name,
            'platform_slug' => $platform_slug,
            'logo_id'       => $logo_id,
            'business_name' => (string) get_option( 'scos_biz_business_name', get_bloginfo( 'name' ) ),
            'reviews_url'   => '',
        ];
    }

    // =========================================================================
    // HTML
    // =========================================================================

    private function render_card( array $d, string $layout, array $show ): void {
        $class = 'bde-aggregate-review bde-aggregate-review--layout-' . esc_attr( $layout );
        ?>
        <div class="<?php echo esc_attr( $class ); ?>" itemscope itemtype="https://schema.org/AggregateRating">
            <meta itemprop="worstRating" content="1">
            <meta itemprop="bestRating" content="5">
            <?php
            switch ( $layout ) {
                case 'simple':
                    $this->render_layout_simple( $d );
                    break;
                case 'google-simple':
                    $this->render_layout_google_simple( $d, $show );
                    break;
                case 'google-full':
                default:
                    $this->render_layout_google_full( $d, $show );
                    break;
            }
            ?>
        </div><!-- /.bde-aggregate-review -->
        <?php
    }

    private function render_layout_simple( array $d ): void {
        ?>
        <span itemprop="ratingValue" class="bde-aggregate-review__score"><?php echo esc_html( $d['average'] ); ?></span>
        <span class="bde-aggregate-review__from">From</span>
        <span itemprop="reviewCount" class="bde-aggregate-review__count"><?php echo absint( $d['count'] ); ?></span>
        <span class="bde-aggregate-review__label">Reviews</span>
        <?php
    }

    private function render_layout_google_simple( array $d, array $show ): void {
        ?>
        <?php if ( $show['icon'] && $d['logo_id'] ) : ?>
        <div class="bde-aggregate-review__icon">
            <?php echo wp_get_attachment_image( $d['logo_id'], 'full', false, [
                'class' => 'bde-aggregate-review__icon-img',
                'alt'   => esc_attr( $d['platform_name'] ),
            ] ); ?>
        </div>
        <?php endif; ?>
        <?php if ( $show['stars'] ) : ?>
        <div class="bde-aggregate-review__stars"
             role="img"
             aria-label="<?php echo esc_attr( $d['average'] . ' out of 5' ); ?>"
             itemprop="ratingValue"
             content="<?php echo esc_attr( $d['average'] ); ?>">
            <meta itemprop="reviewCount" content="<?php echo absint( $d['count'] ); ?>">
            <div class="bde-aggregate-review__stars-inner">
                <?php echo $this->render_stars( (int) round( $d['average_raw'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>
        <?php endif; ?>
        <?php
    }

    private function render_layout_google_full( array $d, array $show ): void {
        $link_text = absint( $d['count'] ) . ' ' . esc_html( $d['platform_name'] ) . ' Reviews';
        ?>
        <div class="bde-aggregate-review__top">
            <?php if ( $show['icon'] && $d['logo_id'] ) : ?>
            <div class="bde-aggregate-review__icon">
                <?php echo wp_get_attachment_image( $d['logo_id'], 'full', false, [
                    'class' => 'bde-aggregate-review__icon-img',
                    'alt'   => esc_attr( $d['platform_name'] ),
                ] ); ?>
            </div>
            <?php endif; ?>
            <?php if ( $show['stars'] ) : ?>
            <div class="bde-aggregate-review__stars"
                 role="img"
                 aria-label="<?php echo esc_attr( $d['average'] . ' out of 5' ); ?>"
                 itemprop="ratingValue"
                 content="<?php echo esc_attr( $d['average'] ); ?>">
                <div class="bde-aggregate-review__stars-inner">
                    <?php echo $this->render_stars( (int) round( $d['average_raw'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
            <?php endif; ?>
        </div><!-- /.bde-aggregate-review__top -->

        <?php if ( $show['name'] && $d['business_name'] ) : ?>
        <span class="bde-aggregate-review__business"><?php echo esc_html( $d['business_name'] ); ?></span>
        <?php endif; ?>

        <?php if ( $show['link'] ) : ?>
        <?php if ( $d['reviews_url'] ) : ?>
        <a class="bde-aggregate-review__link"
           href="<?php echo esc_url( $d['reviews_url'] ); ?>"
           target="_blank"
           rel="noopener noreferrer"
           itemprop="reviewCount"
           content="<?php echo absint( $d['count'] ); ?>">
            <?php echo $link_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — components individually escaped above ?>
        </a>
        <?php else : ?>
        <span class="bde-aggregate-review__link" itemprop="reviewCount" content="<?php echo absint( $d['count'] ); ?>">
            <?php echo $link_text; // phpcs:ignore ?>
        </span>
        <?php endif; ?>
        <?php endif; ?>
        <?php
    }

    /**
     * Render SVG star icons for a given integer rating (0–5).
     */
    private function render_stars( int $rating ): string {
        $rating   = max( 0, min( 5, $rating ) );
        $svg_path = 'M259.3 17.8L194 150.2 47.9 171.5c-26.2 3.8-36.7 36.1-17.7 54.6l105.7 103-25 145.5c-4.5 26.3 23.2 46 46.4 33.7L288 439.6l130.7 68.7c23.2 12.2 50.9-7.4 46.4-33.7l-25-145.5 105.7-103c19-18.5 8.5-50.8-17.7-54.6L382 150.2 316.7 17.8c-11.7-23.6-45.6-23.9-57.4 0z';
        $star_svg = '<svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" class="bde-aggregate-review__star-svg"><path d="' . $svg_path . '"/></svg>';

        $html = '';
        for ( $i = 1; $i <= 5; $i++ ) {
            $state = $i <= $rating ? 'filled' : 'empty';
            $html .= '<span class="bde-aggregate-review__star bde-aggregate-review__star--' . $state . '">' . $star_svg . '</span>';
        }
        return $html;
    }
}
