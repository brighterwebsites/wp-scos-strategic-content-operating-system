<?php
/**
 * Content Architecture — Content Analysis Engine
 *
 * Runs on save_post and writes scos_ca_* analysis meta (word count, H2s,
 * images, reading time, internal/external link counts + detail lists).
 *
 * Skips re-analysis if post content hasn't changed since the last run
 * (checked via scos_ca_last_analyzed vs post_modified).
 *
 * Reuses BW_Content_Analysis::get_aggregated_content() when available so
 * Breakdance + ACF field content is included — same source the legacy module
 * uses. Falls back to post_content only if the legacy class isn't loaded.
 *
 * Runs at save_post priority 25 (after legacy BW_Content_Analysis at 20) so
 * both sets of meta keys are written without conflicting.
 *
 * @package    SiteEssentials
 * @subpackage Modules\ContentArchitecture
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\ContentArchitecture;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Content_Analysis {

	public static function init() {
		add_action( 'save_post', [ __CLASS__, 'analyze' ], 25, 3 );
		add_action( 'wp_ajax_scos_run_analysis_batch', [ __CLASS__, 'ajax_run_batch' ] );
		add_action( 'wp_ajax_scos_analysis_status',    [ __CLASS__, 'ajax_status' ] );
	}

	// ──────────────────────────────────────────────────────────────────────
	// AJAX: batch analysis for the CA overview page
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Return per-post-type analysis status (total / analyzed / unanalyzed).
	 */
	public static function ajax_status(): void {
		check_ajax_referer( 'scos_analysis', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$types  = Taxonomies::get_post_types();
		$rows   = [];
		$totals = [ 'total' => 0, 'analyzed' => 0 ];

		foreach ( $types as $type ) {
			$obj = get_post_type_object( $type );
			if ( ! $obj ) continue;

			$all = (int) wp_count_posts( $type )->publish;
			$analyzed = (int) ( new \WP_Query( [
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'meta_query'     => [ [ 'key' => 'scos_ca_last_analyzed', 'compare' => 'EXISTS' ] ],
			] ) )->found_posts;

			$rows[] = [
				'type'       => $type,
				'label'      => $obj->labels->name,
				'total'      => $all,
				'analyzed'   => $analyzed,
				'unanalyzed' => max( 0, $all - $analyzed ),
			];
			$totals['total']    += $all;
			$totals['analyzed'] += $analyzed;
		}

		wp_send_json_success( [ 'rows' => $rows, 'totals' => $totals ] );
	}

	/**
	 * Analyze a batch of posts that haven't been analyzed yet.
	 * Accepts optional $_POST['post_type'] to limit to one type.
	 * Processes 10 posts per call.
	 */
	public static function ajax_run_batch(): void {
		check_ajax_referer( 'scos_analysis', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$type_filter = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : '';
		$post_types  = $type_filter ? [ $type_filter ] : Taxonomies::get_post_types();
		$batch_size  = 10;

		$ids = get_posts( [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => [
				'relation' => 'OR',
				[ 'key' => 'scos_ca_last_analyzed', 'compare' => 'NOT EXISTS' ],
				[ 'key' => 'scos_ca_last_analyzed', 'value' => '', 'compare' => '=' ],
			],
		] );

		$processed = 0;
		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				self::analyze( $post_id, $post, true );
				$processed++;
			}
		}

		// Count remaining unanalyzed across all requested post types.
		$remaining = (int) ( new \WP_Query( [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_query'     => [
				'relation' => 'OR',
				[ 'key' => 'scos_ca_last_analyzed', 'compare' => 'NOT EXISTS' ],
				[ 'key' => 'scos_ca_last_analyzed', 'value' => '', 'compare' => '=' ],
			],
		] ) )->found_posts;

		wp_send_json_success( [
			'processed' => $processed,
			'remaining' => $remaining,
		] );
	}

	/**
	 * Main entry point — triggered on save_post.
	 *
	 * @since 1.0.0
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 * @return void
	 */
	public static function analyze( $post_id, $post, $update ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, Taxonomies::get_post_types(), true ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Skip if content hasn't changed since last analysis.
		$last = get_post_meta( $post_id, 'scos_ca_last_analyzed', true );
		if ( $last && $last === $post->post_modified ) {
			return;
		}

		$raw   = self::get_content( $post_id, $post );
		$clean = self::clean_content( $raw );
		$links = self::analyze_links( $clean );
		$stats = self::calculate_stats( $clean );

		$reading_time = $stats['word_count'] > 0
			? max( 1, (int) ceil( $stats['word_count'] / 200 ) )
			: 0;

		update_post_meta( $post_id, 'scos_ca_word_count',             $stats['word_count'] );
		update_post_meta( $post_id, 'scos_ca_h2_count',               $stats['h2_count'] );
		update_post_meta( $post_id, 'scos_ca_image_count',            $stats['image_count'] );
		update_post_meta( $post_id, 'scos_ca_reading_time',           $reading_time );
		update_post_meta( $post_id, 'scos_ca_reading_time_iso',       $reading_time ? 'PT' . $reading_time . 'M' : '' );
		update_post_meta( $post_id, 'scos_ca_links_to_internal',      $links['internal_count'] );
		update_post_meta( $post_id, 'scos_ca_links_to_external',      $links['external_count'] );
		update_post_meta( $post_id, 'scos_ca_links_to_internal_list', $links['internal_links'] );
		update_post_meta( $post_id, 'scos_ca_links_to_external_list', $links['external_links'] );
		update_post_meta( $post_id, 'scos_ca_last_analyzed',          $post->post_modified );
	}

	/**
	 * Get full post content from all sources (Breakdance, ACF, post_content).
	 * Delegates to BW_Content_Analysis if available.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return string Raw HTML/text.
	 */
	private static function get_content( $post_id, $post ) {
		if ( class_exists( '\BW_Content_Analysis' )
			&& method_exists( '\BW_Content_Analysis', 'get_aggregated_content' ) ) {
			$content = \BW_Content_Analysis::get_aggregated_content( $post_id );
			if ( $content ) {
				return $content;
			}
		}
		return $post->post_content;
	}

	/**
	 * Strip nav / header / footer noise from content HTML.
	 *
	 * @param string $html Raw HTML.
	 * @return string Cleaned HTML.
	 */
	private static function clean_content( $html ) {
		if ( empty( $html ) ) {
			return '';
		}
		// Remove header, footer, nav blocks (common Breakdance/builder patterns).
		$html = preg_replace( '/<(header|footer|nav)[^>]*>.*?<\/\1>/is', '', $html );
		return $html;
	}

	/**
	 * Extract word count, H2 count, and image count from cleaned HTML.
	 *
	 * @param string $html Cleaned HTML.
	 * @return array{word_count: int, h2_count: int, image_count: int}
	 */
	private static function calculate_stats( $html ) {
		$text = wp_strip_all_tags( $html );
		preg_match_all( '/<h2[^>]*>/i', $html, $h2_matches );
		preg_match_all( '/<img[^>]*>/i', $html, $img_matches );

		return [
			'word_count'  => $text ? str_word_count( $text ) : 0,
			'h2_count'    => count( $h2_matches[0] ),
			'image_count' => count( $img_matches[0] ),
		];
	}

	/**
	 * Categorise links in the content as internal or external.
	 *
	 * "Internal" = same host (or relative URL).
	 * "External" = different host.
	 *
	 * @param string $html Cleaned HTML.
	 * @return array{
	 *   internal_count: int,
	 *   external_count: int,
	 *   internal_links: array<array{url:string,text:string}>,
	 *   external_links: array<array{url:string,text:string}>
	 * }
	 */
	private static function analyze_links( $html ) {
		$site_host = (string) parse_url( home_url(), PHP_URL_HOST );
		$internal  = [];
		$external  = [];

		preg_match_all(
			'/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
			$html,
			$matches,
			PREG_SET_ORDER
		);

		foreach ( $matches as $match ) {
			$url  = esc_url_raw( $match[1] );
			$text = wp_strip_all_tags( $match[2] );

			if ( empty( $url ) ) {
				continue;
			}

			// Skip anchors and javascript: links.
			if ( 0 === strpos( $url, '#' ) || 0 === strpos( $url, 'javascript' ) ) {
				continue;
			}

			$host = (string) parse_url( $url, PHP_URL_HOST );

			// Empty host = relative URL = internal.
			if ( empty( $host )
				|| $host === $site_host
				|| ( strlen( $host ) > strlen( $site_host ) && substr( $host, -( strlen( $site_host ) + 1 ) ) === '.' . $site_host )
			) {
				$internal[] = [ 'url' => $url, 'text' => $text ];
			} else {
				$external[] = [ 'url' => $url, 'text' => $text ];
			}
		}

		return [
			'internal_count' => count( $internal ),
			'external_count' => count( $external ),
			'internal_links' => $internal,
			'external_links' => $external,
		];
	}
}
