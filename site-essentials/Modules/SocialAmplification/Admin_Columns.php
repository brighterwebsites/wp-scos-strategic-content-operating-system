<?php
/**
 * Social Amplification — Projects admin columns.
 *
 * Adds an "Amplified" column to the Projects list screen, with simple sorting
 * and a quick filter for posts not yet amplified.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification
 */

namespace SiteEssentials\Modules\SocialAmplification;

defined( 'ABSPATH' ) || exit;

class Admin_Columns {

	public static function init(): void {
		add_filter( 'manage_projects_posts_columns', [ __CLASS__, 'add_column' ] );
		add_action( 'manage_projects_posts_custom_column', [ __CLASS__, 'render_column' ], 10, 2 );
		add_filter( 'manage_edit-projects_sortable_columns', [ __CLASS__, 'sortable_columns' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'sort_query' ] );
		add_action( 'restrict_manage_posts', [ __CLASS__, 'render_filter_hint' ] );
	}

	public static function add_column( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'date' === $key ) {
				$new['scos_sa_amplified'] = __( 'Amplified', 'site-essentials' );
			}
		}
		if ( ! isset( $new['scos_sa_amplified'] ) ) {
			$new['scos_sa_amplified'] = __( 'Amplified', 'site-essentials' );
		}
		return $new;
	}

	public static function render_column( string $column, int $post_id ): void {
		if ( 'scos_sa_amplified' !== $column ) {
			return;
		}

		$is_amplified = get_post_meta( $post_id, Publish_Hook::AMPLIFIED_META, true ) === '1';
		if ( ! $is_amplified ) {
			echo '—';
			return;
		}

		$log   = get_option( Amplification\Amplification_Engine::LOG_OPTION, [] );
		$entry = is_array( $log ) ? ( $log[ $post_id ] ?? [] ) : [];
		$ran   = (string) ( $entry['ran_at'] ?? '' );
		$when  = $ran ? mysql2date( 'j M Y', $ran ) : __( 'date unknown', 'site-essentials' );

		printf(
			'%s <span style="color:#6b7280;">%s %s</span>',
			esc_html__( 'Yes', 'site-essentials' ),
			esc_html__( 'ran', 'site-essentials' ),
			esc_html( $when )
		);
	}

	public static function sortable_columns( array $columns ): array {
		$columns['scos_sa_amplified'] = 'scos_sa_amplified';
		return $columns;
	}

	public static function sort_query( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$post_type = (string) $query->get( 'post_type' );
		if ( 'projects' !== $post_type ) {
			return;
		}

		$orderby = (string) $query->get( 'orderby' );
		if ( 'scos_sa_amplified' === $orderby ) {
			$query->set( 'meta_key', Publish_Hook::AMPLIFIED_META );
			$query->set( 'orderby', 'meta_value' );
		}

		$filter = isset( $_GET['amplified_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['amplified_filter'] ) ) : '';
		if ( '0' === $filter ) {
			$meta_query   = (array) ( $query->get( 'meta_query' ) ?: [] );
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => Publish_Hook::AMPLIFIED_META,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => Publish_Hook::AMPLIFIED_META,
					'value'   => '1',
					'compare' => '!=',
				],
			];
			$query->set( 'meta_query', $meta_query );
		}
	}

	public static function render_filter_hint( string $post_type ): void {
		if ( 'projects' !== $post_type ) {
			return;
		}

		$current = isset( $_GET['amplified_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['amplified_filter'] ) ) : '';
		$url     = add_query_arg(
			[
				'post_type'        => 'projects',
				'amplified_filter' => '0',
			],
			admin_url( 'edit.php' )
		);

		printf(
			'<a href="%s" class="button button-secondary" style="margin-left:8px;%s">%s</a>',
			esc_url( $url ),
			'0' === $current ? 'background:#eef2ff;border-color:#c7d2fe;' : '',
			esc_html__( 'Not yet amplified', 'site-essentials' )
		);
	}
}

