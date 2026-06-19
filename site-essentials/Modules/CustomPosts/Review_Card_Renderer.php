<?php
// v1.6 | 2026-06-19

/**
 * Review Card Renderer
 *
 * Renders a single bw_reviews post as a self-contained review card.
 * Called by [bw_review_card] shortcode and by the SCOS Review Card BDE element (via ssr.php).
 *
 * Shortcode usage:
 *   [bw_review_card]                       — current post in loop
 *   [bw_review_card id="42"]               — specific review
 *   [bw_review_card layout="hero" id="42"] — with layout preset
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 */

namespace SiteEssentials\Modules\CustomPosts;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Review_Card_Renderer {

    // Layout options
    const LAYOUTS = [ 'stacked', 'horizontal', 'quote', 'hero' ];

    /**
     * Shortcode handler — maps [bw_review_card] atts to render().
     */
    public function shortcode( array $atts ): string {
        $atts = shortcode_atts( [
            'id'                  => '',
            'layout'              => 'stacked',
            // Field toggles (1 = show, 0 = hide)
            'show_rating'         => '1',
            'show_excerpt'        => '1',
            'show_full_text'      => '0',
            'show_outcome'        => '1',
            'show_name'           => '1',
            'show_detail'         => '1',
            'show_date'           => '1',
            'show_platform'       => '1',
            'show_verify'         => '1',
            'show_featured'       => '0',
            'show_platform_icon'  => '1',
            'show_project_image'  => '1',
            'show_project_name'   => '1',
            'show_project_link'   => '1',
        ], $atts, 'bw_review_card' );

        $post_id = ! empty( $atts['id'] ) ? absint( $atts['id'] ) : get_the_ID();

        if ( ! $post_id ) {
            return '';
        }

        return $this->render( $post_id, $atts );
    }

    /**
     * Core render method — reusable from WP-CLI / MCP tool calls.
     *
     * @param int   $post_id  bw_reviews post ID.
     * @param array $atts     Display options (layout, show_* flags).
     * @return string         HTML output.
     */
    public function render( int $post_id, array $atts = [] ): string {
        // ===== TEMP DEBUG — trace every card render. Remove after diagnosis. =====
        // Logs to wp-content/scos-review-debug.log. Captures who calls render(),
        // during which request/AJAX action, so we can pin the "unexpected output
        // during AJAX request" leak and the stray card after <body>.
        if ( defined( 'SCOS_REVIEW_DEBUG' ) && SCOS_REVIEW_DEBUG ) {
            $frames = array_slice( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), 0, 14 );
            $stack  = array_map(
                static function ( $f ) {
                    $where = isset( $f['file'] ) ? basename( $f['file'] ) . ':' . ( $f['line'] ?? '?' ) : '(internal)';
                    $fn    = ( isset( $f['class'] ) ? $f['class'] . $f['type'] : '' ) . ( $f['function'] ?? '?' );
                    return $fn . '  @ ' . $where;
                },
                $frames
            );
            $entry = sprintf(
                "[%s] render() post_id=%d ajax=%s action=%s builder=%s rest=%s uri=%s\n    %s\n",
                gmdate( 'H:i:s' ),
                $post_id,
                ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ? 'YES' : 'no',
                isset( $_REQUEST['action'] ) ? preg_replace( '/[^a-z0-9_\-]/i', '', (string) $_REQUEST['action'] ) : '(none)',
                ( defined( 'BREAKDANCE_BUILDER' ) && BREAKDANCE_BUILDER ) ? 'YES' : 'no',
                ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ? 'YES' : 'no',
                isset( $_SERVER['REQUEST_URI'] ) ? preg_replace( '/[\r\n]/', '', (string) $_SERVER['REQUEST_URI'] ) : '(none)',
                implode( "\n    ", $stack )
            );
            error_log( $entry, 3, WP_CONTENT_DIR . '/scos-review-debug.log' );
        }
        // ===== END TEMP DEBUG =====

        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'bw_reviews' ) {
            return '';
        }

        $layout = in_array( $atts['layout'] ?? 'stacked', self::LAYOUTS, true )
            ? ( $atts['layout'] ?? 'stacked' )
            : 'stacked';

        $show = $this->parse_show_flags( $atts );

        // Gather all data up front — one meta read per field
        $data = $this->get_review_data( $post_id, $post );

        ob_start();
        $this->render_card( $data, $layout, $show );
        return (string) ob_get_clean();
    }

    // =========================================================================
    // DATA
    // =========================================================================

    /**
     * Fetch all review data from one post. One meta read per field (batch-safe
     * because WordPress caches meta after the first get_post_meta call).
     */
    private function get_review_data( int $post_id, \WP_Post $post ): array {
        $raw_date      = get_post_meta( $post_id, 'bw_date', true );
        $precision     = get_post_meta( $post_id, 'bw_date_precision', true ) ?: 'full';
        $excerpt_meta  = get_post_meta( $post_id, 'bw_review_excerpt', true );
        $excerpt       = $excerpt_meta
            ? $excerpt_meta
            : wp_trim_words( wp_strip_all_tags( $post->post_content ), 25, '&hellip;' );

        $platforms    = get_the_terms( $post_id, 'bw_review_platform' );
        $platform_obj = ( $platforms && ! is_wp_error( $platforms ) ) ? $platforms[0] : null;

        // Project relationship (ACF or fallback meta)
        $project_id = 0;
        if ( function_exists( 'get_field' ) ) {
            $project_id = (int) get_field( 'bw_related_project', $post_id );
        }
        if ( ! $project_id ) {
            $project_id = (int) get_post_meta( $post_id, 'bw_related_project', true );
        }

        return [
            'post_id'          => $post_id,
            'customer_name'    => get_the_title( $post_id ),
            // Raw body. Formatted at display time (see render_card). We deliberately do
            // NOT run apply_filters( 'the_content', ... ) here: Breakdance hooks the_content
            // to render builder content, so re-entering that chain from inside an element's
            // SSR/AJAX render recurses into Breakdance and leaks output ("Unexpected output
            // during AJAX request"). wpautop + do_shortcode covers what a review body needs.
            'full_text'        => $post->post_content,
            'excerpt'          => $excerpt,
            'rating'           => (int) get_post_meta( $post_id, 'bw_rating', true ),
            'date_raw'         => $raw_date,
            'date_formatted'   => $this->format_date( $raw_date, $precision ),
            'verify_url'       => get_post_meta( $post_id, 'bw_verify_url', true ),
            'outcome'          => get_post_meta( $post_id, 'bw_success_outcome', true ),
            'customer_detail'  => get_post_meta( $post_id, 'bw_customer_detail', true ),
            'is_featured'      => get_post_meta( $post_id, 'bw_is_featured', true ) === '1',
            'platform_name'    => $platform_obj ? $platform_obj->name : '',
            'platform_slug'    => $platform_obj ? $platform_obj->slug : '',
            'platform_logo_id' => $platform_obj ? absint( get_term_meta( $platform_obj->term_id, 'bw_platform_logo_id', true ) ) : 0,
            'project_id'       => $project_id,
            'project_title'    => $project_id ? get_the_title( $project_id ) : '',
            'project_url'      => $project_id ? get_permalink( $project_id ) : '',
            'project_thumb_id' => $project_id ? get_post_thumbnail_id( $project_id ) : 0,
        ];
    }

    /**
     * Format date respecting bw_date_precision.
     */
    private function format_date( string $raw, string $precision ): string {
        if ( ! $raw ) {
            return '';
        }
        $ts = strtotime( $raw );
        if ( ! $ts ) {
            return esc_html( $raw );
        }
        switch ( $precision ) {
            case 'year':       return date_i18n( 'Y', $ts );
            case 'month-year': return date_i18n( 'F Y', $ts );
            default:           return date_i18n( get_option( 'date_format' ), $ts );
        }
    }

    /**
     * Normalise show_* flags to booleans.
     */
    private function parse_show_flags( array $atts ): array {
        $keys = [
            'rating', 'excerpt', 'full_text', 'outcome',
            'name', 'detail', 'date', 'platform',
            'verify', 'featured', 'platform_icon', 'project_image', 'project_name', 'project_link',
        ];
        $show = [];
        foreach ( $keys as $key ) {
            $show[ $key ] = ! empty( $atts[ 'show_' . $key ] ) && $atts[ 'show_' . $key ] !== '0';
        }
        return $show;
    }

    // =========================================================================
    // HTML
    // =========================================================================

    /**
     * Output the card HTML.
     * All layouts share the same element structure; CSS controls ordering and direction.
     */
    private function render_card( array $d, string $layout, array $show ): void {
        $classes = 'bde-review-card bde-review-card--layout-' . esc_attr( $layout );

        // Has project data we can show
        $has_project = $d['project_id'] && ( $show['project_image'] || $show['project_name'] );
        $has_project_image = $has_project && $show['project_image'] && $d['project_thumb_id'];
        ?>
        <div class="<?php echo esc_attr( $classes ); ?>">

            <?php if ( $has_project_image ) : ?>
            <div class="bde-review-card__media">
                <?php echo wp_get_attachment_image(
                    $d['project_thumb_id'],
                    'medium_large',
                    false,
                    [
                        'class'   => 'bde-review-card__project-img',
                        'loading' => 'lazy',
                        'alt'     => esc_attr( $d['project_title'] ),
                    ]
                ); ?>
            </div>
            <?php endif; ?>

            <div class="bde-review-card__content">

                <?php if ( $show['featured'] && $d['is_featured'] ) : ?>
                <span class="bde-review-card__featured-badge"><?php esc_html_e( 'Featured', 'site-essentials' ); ?></span>
                <?php endif; ?>

                <?php if ( $show['platform_icon'] && $d['platform_logo_id'] ) : ?>
                <div class="bde-review-card__platform-icon">
                    <?php echo wp_get_attachment_image(
                        $d['platform_logo_id'],
                        'full',
                        false,
                        [
                            'class'   => 'bde-review-card__platform-icon-img',
                            'loading' => 'lazy',
                            'alt'     => esc_attr( $d['platform_name'] ),
                        ]
                    ); ?>
                </div>
                <?php endif; ?>

                <?php if ( $show['rating'] && $d['rating'] ) : ?>
                <div class="bde-review-card__stars" aria-label="<?php echo esc_attr( $d['rating'] . ' out of 5 stars' ); ?>" role="img">
                    <?php echo $this->render_stars( $d['rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <?php endif; ?>

                <?php if ( $show['excerpt'] && $d['excerpt'] ) : ?>
                <blockquote class="bde-review-card__quote">
                    <p><?php echo esc_html( $d['excerpt'] ); ?></p>
                </blockquote>
                <?php endif; ?>

                <?php if ( $show['full_text'] && ! $show['excerpt'] && $d['full_text'] ) : ?>
                <div class="bde-review-card__full-text">
                    <?php echo wp_kses_post( wpautop( do_shortcode( $d['full_text'] ) ) ); ?>
                </div>
                <?php endif; ?>

                <?php if ( $show['outcome'] && $d['outcome'] ) : ?>
                <p class="bde-review-card__outcome"><?php echo esc_html( $d['outcome'] ); ?></p>
                <?php endif; ?>

                <div class="bde-review-card__footer">
                    <div class="bde-review-card__author">
                        <?php if ( $show['name'] && $d['customer_name'] ) : ?>
                        <strong class="bde-review-card__name"><?php echo esc_html( $d['customer_name'] ); ?></strong>
                        <?php endif; ?>

                        <?php if ( $show['detail'] && $d['customer_detail'] ) : ?>
                        <span class="bde-review-card__detail"><?php echo esc_html( $d['customer_detail'] ); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ( $show['platform'] && $d['platform_name'] ) : ?>
                    <span class="bde-review-card__platform bde-review-card__platform--<?php echo esc_attr( $d['platform_slug'] ); ?>">
                        <?php echo esc_html( $d['platform_name'] ); ?>
                    </span>
                    <?php endif; ?>

                    <?php if ( $show['date'] && $d['date_formatted'] ) : ?>
                    <time class="bde-review-card__date" datetime="<?php echo esc_attr( $d['date_raw'] ); ?>">
                        <?php echo esc_html( $d['date_formatted'] ); ?>
                    </time>
                    <?php endif; ?>

                    <?php if ( $show['verify'] && $d['verify_url'] ) : ?>
                    <a class="bde-review-card__verify"
                       href="<?php echo esc_url( $d['verify_url'] ); ?>"
                       target="_blank"
                       rel="noopener noreferrer">
                        <?php esc_html_e( 'Verify review', 'site-essentials' ); ?>
                    </a>
                    <?php endif; ?>

                    <?php if ( $has_project && $d['project_title'] ) : ?>
                    <div class="bde-review-card__project-meta">
                        <?php if ( $show['project_name'] ) : ?>
                        <?php if ( $show['project_link'] && $d['project_url'] ) : ?>
                        <a class="bde-review-card__project-link" href="<?php echo esc_url( $d['project_url'] ); ?>">
                            <?php echo esc_html( $d['project_title'] ); ?>
                        </a>
                        <?php else : ?>
                        <span class="bde-review-card__project-name"><?php echo esc_html( $d['project_title'] ); ?></span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div><!-- /.bde-review-card__footer -->
            </div><!-- /.bde-review-card__content -->

        </div><!-- /.bde-review-card -->
        <?php
    }

    /**
     * Render SVG star icons for a given rating.
     */
    private function render_stars( int $rating ): string {
        $rating = max( 1, min( 5, $rating ) );
        $svg_path = 'M259.3 17.8L194 150.2 47.9 171.5c-26.2 3.8-36.7 36.1-17.7 54.6l105.7 103-25 145.5c-4.5 26.3 23.2 46 46.4 33.7L288 439.6l130.7 68.7c23.2 12.2 50.9-7.4 46.4-33.7l-25-145.5 105.7-103c19-18.5 8.5-50.8-17.7-54.6L382 150.2 316.7 17.8c-11.7-23.6-45.6-23.9-57.4 0z';
        $star_svg  = '<svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" class="bde-review-card__star-svg"><path d="' . $svg_path . '"/></svg>';

        $html = '<div class="bde-review-card__stars-inner">';
        for ( $i = 1; $i <= 5; $i++ ) {
            $state  = $i <= $rating ? 'filled' : 'empty';
            $html  .= '<span class="bde-review-card__star bde-review-card__star--' . $state . '">' . $star_svg . '</span>';
        }
        $html .= '</div>';
        return $html;
    }
}
