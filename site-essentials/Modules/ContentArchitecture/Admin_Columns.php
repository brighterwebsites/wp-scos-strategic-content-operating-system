<?php
/**
 * Content Architecture — Admin List Table Columns
 *
 * Adds Cluster, Intent, Index Status, and Next Step columns to all
 * supported post-type list views. Columns appear after "Title" and render
 * colour-coded badges consistent with the meta box UI.
 *
 * @package    SiteEssentials
 * @subpackage Modules\ContentArchitecture
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\ContentArchitecture;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Columns {

	public static function init() {
		// Register hooks at admin_init so get_post_types() runs after all CPTs
		// are registered (init priority 10).
		add_action( 'admin_init', [ __CLASS__, 'register_hooks' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_column_styles' ] );
	}

	/**
	 * Register column filters / actions for every supported post type.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_hooks() {
		foreach ( Taxonomies::get_post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns",         [ __CLASS__, 'add_columns' ] );
			add_action( "manage_{$post_type}_posts_custom_column",   [ __CLASS__, 'render_column' ], 10, 2 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", [ __CLASS__, 'sortable_columns' ] );
		}
	}

	/**
	 * Inject CA columns immediately after "Title".
	 *
	 * @since 1.0.0
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public static function add_columns( $columns ) {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['scos_ca_cluster']     = __( 'Cluster', 'site-essentials' );
				$new['scos_ca_intent']      = __( 'Intent', 'site-essentials' );
				$new['scos_ca_index']       = __( 'Index', 'site-essentials' );
				$new['scos_ca_next_step']   = __( 'Next Step', 'site-essentials' );
			}
		}
		return $new;
	}

	/**
	 * Render a CA column cell for a given post.
	 *
	 * @since 1.0.0
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_column( $column, $post_id ) {
		switch ( $column ) {

			case 'scos_ca_cluster':
				$terms = wp_get_post_terms( $post_id, 'scos_content_cluster' );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					echo '<span class="scos-col-badge scos-col-badge--cluster">'
						. esc_html( $terms[0]->name ) . '</span>';
				} else {
					echo '<span class="scos-col-empty">—</span>';
				}
				break;

			case 'scos_ca_intent':
				$val  = get_post_meta( $post_id, 'scos_ca_intent', true );
				$opts = Meta_Fields::intent_options();
				if ( $val && isset( $opts[ $val ] ) ) {
					echo '<span class="scos-col-badge">' . esc_html( $opts[ $val ] ) . '</span>';
				} else {
					echo '<span class="scos-col-empty">—</span>';
				}
				break;

			case 'scos_ca_index':
				$val    = get_post_meta( $post_id, 'scos_ca_index_status', true );
				$opts   = Meta_Fields::index_status_options();
				$colors = Meta_Fields::index_status_colors();
				if ( $val && isset( $opts[ $val ] ) ) {
					$c = $colors[ $val ] ?? [ 'color' => '#6b7280', 'bg' => '#f3f4f6' ];
					printf(
						'<span class="scos-col-badge" style="color:%s;background:%s">%s</span>',
						esc_attr( $c['color'] ),
						esc_attr( $c['bg'] ),
						esc_html( $opts[ $val ] )
					);
				} else {
					echo '<span class="scos-col-empty">—</span>';
				}
				break;

			case 'scos_ca_next_step':
				$val  = get_post_meta( $post_id, 'scos_ca_next_step', true );
				$opts = Meta_Fields::next_step_options();
				if ( $val && isset( $opts[ $val ] ) ) {
					$opt = $opts[ $val ];
					printf(
						'<span class="scos-col-badge" style="color:%s;background:%s">%s</span>',
						esc_attr( $opt['color'] ),
						esc_attr( $opt['bg'] ),
						esc_html( $opt['label'] )
					);
				} else {
					echo '<span class="scos-col-empty">—</span>';
				}
				break;
		}
	}

	/**
	 * Mark Intent and Index columns as sortable.
	 *
	 * @since 1.0.0
	 * @param array $columns Current sortable columns.
	 * @return array Modified sortable columns.
	 */
	public static function sortable_columns( $columns ) {
		$columns['scos_ca_intent'] = 'scos_ca_intent';
		$columns['scos_ca_index']  = 'scos_ca_index_status';
		return $columns;
	}

	/**
	 * Enqueue minimal inline styles for column badges.
	 * Only loads on admin list pages.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public static function enqueue_column_styles( $hook ) {
		if ( 'edit.php' !== $hook ) {
			return;
		}
		$css = '
			.scos-col-badge {
				display: inline-block;
				padding: 2px 7px;
				border-radius: 3px;
				font-size: 11px;
				font-weight: 500;
				line-height: 1.5;
				background: #e5e7eb;
				color: #374151;
				white-space: nowrap;
			}
			.scos-col-badge--cluster {
				background: #ede9fe;
				color: #5b21b6;
			}
			.scos-col-empty { color: #9ca3af; }
		';
		wp_add_inline_style( 'list-tables', $css );
	}
}
