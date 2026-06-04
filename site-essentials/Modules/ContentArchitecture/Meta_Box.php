<?php
/**
 * Content Architecture — Meta Box Controller
 *
 * Registers the single tabbed "Content Architecture" meta box on all
 * supported post types and handles save_post for:
 *  - Taxonomy assignments via wp_set_post_terms() (scos_content_cluster, scos_topic)
 *  - All scos_ca_* strategy and workflow post meta
 *
 * Also provides the AJAX endpoint for quick-adding new Cluster / Topic terms
 * directly from the meta box without leaving the post editor.
 *
 * @package    SiteEssentials
 * @subpackage Modules\ContentArchitecture
 * @since      1.0.0
 *
 * v1.1 | 2026-05-22 — FAQ intent goal picker: load/save scos_ca_intent_goal_faq_id,
 *                      pending stub creation on save, enriched JS data.
 * v1.2 | 2026-05-25 — Auto-set scos_faq_is_intent_goal on linked FAQ when saved.
 */

namespace SiteEssentials\Modules\ContentArchitecture;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meta_Box {

	public static function init() {
		add_action( 'add_meta_boxes',          [ __CLASS__, 'register' ] );
		// Late pass: strip legacy ALTC taxonomy + Content Management boxes if anything registered them earlier.
		add_action( 'add_meta_boxes',          [ __CLASS__, 'remove_legacy_meta_boxes' ], 999, 1 );
		add_action( 'save_post',               [ __CLASS__, 'save' ], 10, 2 );
		add_action( 'admin_enqueue_scripts',   [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_ajax_scos_ca_add_term', [ __CLASS__, 'ajax_add_term' ] );
	}

	/**
	 * Register the meta box on every supported post type.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		foreach ( Taxonomies::get_post_types() as $post_type ) {
			add_meta_box(
				'scos_content_architecture',
				__( 'Content Architecture', 'site-essentials' ),
				[ __CLASS__, 'render' ],
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Remove legacy brighter-core meta boxes superseded by this module.
	 *
	 * @since 1.0.0
	 * @param string $post_type Current post type on the edit screen.
	 */
	public static function remove_legacy_meta_boxes( string $post_type ): void {
		if ( ! defined( 'SCOS_CA_ACTIVE' ) ) {
			return;
		}
		$contexts = [ 'normal', 'side', 'advanced' ];
		foreach ( [ 'altc_strategic_lensdiv', 'altc_topicdiv' ] as $box_id ) {
			foreach ( $contexts as $ctx ) {
				remove_meta_box( $box_id, $post_type, $ctx );
			}
		}
		remove_meta_box( 'bw_content_strategy', $post_type, 'side' );
	}

	/**
	 * Render the meta box — collects data and delegates to the view.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public static function render( $post ) {
		wp_nonce_field( 'scos_ca_meta_box', 'scos_ca_nonce' );

		// ---- Taxonomy current values (proper term IDs, not post meta) ----
		$cluster_terms   = wp_get_post_terms( $post->ID, 'scos_content_cluster' );
		$current_cluster = ( ! is_wp_error( $cluster_terms ) && ! empty( $cluster_terms ) )
			? $cluster_terms[0]->term_id : 0;

		$topic_terms   = wp_get_post_terms( $post->ID, 'scos_topic' );
		$current_topic = ( ! is_wp_error( $topic_terms ) && ! empty( $topic_terms ) )
			? $topic_terms[0]->term_id : 0;

		// Supporting topics — stored as post meta (not taxonomy) to keep primary separate
		$raw_supporting         = get_post_meta( $post->ID, 'scos_ca_supporting_topics', true );
		$current_supporting_topics = is_array( $raw_supporting ) ? array_map( 'intval', $raw_supporting ) : [];

		// ---- Strategy + Workflow post meta ----
		$fields = [
			'pillar_page_id'         => (int) get_post_meta( $post->ID, 'scos_ca_pillar_page_id', true ),
			'service_pathway_id'     => (int) get_post_meta( $post->ID, 'scos_ca_service_pathway_id', true ),
			'intent_goal_faq_id'     => Intent_Goal_Resolver::get_faq_id( $post->ID ),
			'intent'                 => (string) get_post_meta( $post->ID, 'scos_ca_intent', true ),
			'purpose'                => (string) get_post_meta( $post->ID, 'scos_ca_purpose', true ),
			'maturity'               => (string) get_post_meta( $post->ID, 'scos_ca_maturity', true ),
			'intent_goal'            => (string) get_post_meta( $post->ID, 'scos_ca_intent_goal', true ),
			'index_status'           => (string) get_post_meta( $post->ID, 'scos_ca_index_status', true ),
			'optimization_progress'  => (array)  get_post_meta( $post->ID, 'scos_ca_optimization_progress', true ),
			'next_step'              => (string) get_post_meta( $post->ID, 'scos_ca_next_step', true ),
		];

		// ---- FAQ intent goal summary (for linked-FAQ panel in view) ----
		$intent_goal_faq_summary = null;
		if ( $fields['intent_goal_faq_id'] > 0 ) {
			$intent_goal_faq_summary = Intent_Goal_Resolver::get_faq_summary( $fields['intent_goal_faq_id'] );
		}

		// ---- FAQ module active? (gates picker vs freetext-only display) ----
		$faq_module_active = defined( 'SCOS_FAQ_ACTIVE' );

		// ---- Analysis data (read-only) ----
		$analysis = [
			'word_count'              => (int)    get_post_meta( $post->ID, 'scos_ca_word_count', true ),
			'h2_count'                => (int)    get_post_meta( $post->ID, 'scos_ca_h2_count', true ),
			'image_count'             => (int)    get_post_meta( $post->ID, 'scos_ca_image_count', true ),
			'reading_time'            => (int)    get_post_meta( $post->ID, 'scos_ca_reading_time', true ),
			'links_to_internal'       => (int)    get_post_meta( $post->ID, 'scos_ca_links_to_internal', true ),
			'links_to_external'       => (int)    get_post_meta( $post->ID, 'scos_ca_links_to_external', true ),
			'links_to_internal_list'  => (array)  get_post_meta( $post->ID, 'scos_ca_links_to_internal_list', true ),
			'links_to_external_list'  => (array)  get_post_meta( $post->ID, 'scos_ca_links_to_external_list', true ),
			'last_analyzed'           => (string) get_post_meta( $post->ID, 'scos_ca_last_analyzed', true ),
		];

		// ---- Term lists for dropdowns ----
		$clusters = get_terms( [ 'taxonomy' => 'scos_content_cluster', 'hide_empty' => false, 'orderby' => 'name' ] );
		$topics   = get_terms( [ 'taxonomy' => 'scos_topic',           'hide_empty' => false, 'orderby' => 'name' ] );
		if ( is_wp_error( $clusters ) ) { $clusters = []; }
		if ( is_wp_error( $topics ) )   { $topics   = []; }

		// ---- Pillar pages: posts with purpose = pillar ----
		$pillar_pages = get_posts( [
			'post_type'      => Taxonomies::get_post_types(),
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'exclude'        => [ $post->ID ],
			'meta_query'     => [ [ 'key' => 'scos_ca_purpose', 'value' => 'pillar' ] ],
		] );

		// ---- Service pathway pages: service, product, conversion-hub ----
		$service_pathway_pages = get_posts( [
			'post_type'      => Taxonomies::get_post_types(),
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'exclude'        => [ $post->ID ],
			'meta_query'     => [
				'relation' => 'OR',
				[ 'key' => 'scos_ca_purpose', 'value' => 'service-page' ],
				[ 'key' => 'scos_ca_purpose', 'value' => 'product-page' ],
				[ 'key' => 'scos_ca_purpose', 'value' => 'conversion-hub' ],
			],
		] );

		include __DIR__ . '/views/meta-box.php';
	}

	/**
	 * Save meta box data on post save.
	 *
	 * @since 1.0.0
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['scos_ca_nonce'] )
			|| ! wp_verify_nonce( $_POST['scos_ca_nonce'], 'scos_ca_meta_box' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, Taxonomies::get_post_types(), true ) ) {
			return;
		}

		// ---- Taxonomy assignments (proper term relationships) ----
		if ( isset( $_POST['scos_ca_cluster'] ) ) {
			$cluster_id = absint( $_POST['scos_ca_cluster'] );
			wp_set_post_terms( $post_id, $cluster_id > 0 ? [ $cluster_id ] : [], 'scos_content_cluster' );
		}

		if ( isset( $_POST['scos_ca_topic'] ) ) {
			$topic_id = absint( $_POST['scos_ca_topic'] );
			wp_set_post_terms( $post_id, $topic_id > 0 ? [ $topic_id ] : [], 'scos_topic' );
		}

		// Supporting topics — stored as post meta array of term IDs
		if ( isset( $_POST['scos_ca_supporting_topics'] ) ) {
			$raw        = (array) $_POST['scos_ca_supporting_topics'];
			$valid_ids  = array_map( 'absint', $raw );
			$valid_ids  = array_values( array_filter( $valid_ids ) ); // drop zeroes
			// Exclude the primary topic to avoid duplication
			$primary_id = absint( $_POST['scos_ca_topic'] ?? 0 );
			if ( $primary_id ) {
				$valid_ids = array_values( array_diff( $valid_ids, [ $primary_id ] ) );
			}
			update_post_meta( $post_id, 'scos_ca_supporting_topics', $valid_ids );
		} else {
			update_post_meta( $post_id, 'scos_ca_supporting_topics', [] );
		}

		// ---- String / dropdown fields ----
		$valid_intent   = array_keys( Meta_Fields::intent_options() );
		$valid_purpose  = array_keys( Meta_Fields::purpose_options() );
		$valid_maturity = array_keys( Meta_Fields::maturity_options() );
		$valid_index    = array_keys( Meta_Fields::index_status_options() );
		$valid_nextstep = array_keys( Meta_Fields::next_step_options() );

		$string_fields = [
			'scos_ca_intent'        => [ 'validate' => $valid_intent ],
			'scos_ca_purpose'       => [ 'validate' => $valid_purpose ],
			'scos_ca_maturity'      => [ 'validate' => $valid_maturity ],
			'scos_ca_index_status'  => [ 'validate' => $valid_index ],
			'scos_ca_next_step'     => [ 'validate' => $valid_nextstep ],
		];

		foreach ( $string_fields as $meta_key => $opts ) {
			if ( ! isset( $_POST[ $meta_key ] ) ) {
				continue;
			}
			$val = sanitize_text_field( $_POST[ $meta_key ] );
			if ( in_array( $val, $opts['validate'], true ) ) {
				update_post_meta( $post_id, $meta_key, $val );
			}
		}

		// ---- Intent goal: FAQ link + pending stub creation ----
		// Step 1: if a pending stub title is present, create the FAQ now and use its ID.
		$pending_faq_title = isset( $_POST['scos_ca_intent_goal_pending_faq_title'] )
			? sanitize_text_field( wp_unslash( $_POST['scos_ca_intent_goal_pending_faq_title'] ) )
			: '';

		if ( '' !== $pending_faq_title ) {
			$topic_id = absint( $_POST['scos_ca_topic'] ?? 0 );
			$new_faq  = Intent_Goal_Resolver::create_stub_faq( $pending_faq_title, $topic_id, $post_id );
			if ( ! is_wp_error( $new_faq ) ) {
				update_post_meta( $post_id, 'scos_ca_intent_goal_faq_id', $new_faq );
				// create_stub_faq already sets scos_faq_is_intent_goal = 1 on the new FAQ.
			}
		} else {
			// Step 2: save an explicitly submitted FAQ ID (picker selection or cleared).
			if ( isset( $_POST['scos_ca_intent_goal_faq_id'] ) ) {
				$faq_id = absint( $_POST['scos_ca_intent_goal_faq_id'] );
				if ( $faq_id > 0 ) {
					// Validate that the post exists and is an faq CPT.
					$faq_post = get_post( $faq_id );
					if ( $faq_post && 'faq' === $faq_post->post_type ) {
						update_post_meta( $post_id, 'scos_ca_intent_goal_faq_id', $faq_id );
						// Auto-flag the linked FAQ as an intent goal.
						update_post_meta( $faq_id, \SiteEssentials\Modules\CustomPosts\FAQ\FAQ_Module::META_IS_INTENT_GOAL, '1' );
					}
				} else {
					delete_post_meta( $post_id, 'scos_ca_intent_goal_faq_id' );
				}
			}
		}

		// ---- Textarea: freetext goal (only saved when no FAQ ID is linked) ----
		if ( isset( $_POST['scos_ca_intent_goal'] ) ) {
			$linked_faq = (int) get_post_meta( $post_id, 'scos_ca_intent_goal_faq_id', true );
			if ( 0 === $linked_faq ) {
				update_post_meta( $post_id, 'scos_ca_intent_goal', sanitize_textarea_field( wp_unslash( $_POST['scos_ca_intent_goal'] ) ) );
			}
		}

		// ---- Integer: pillar page + service pathway ----
		foreach ( [ 'scos_ca_pillar_page_id', 'scos_ca_service_pathway_id' ] as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$val = absint( $_POST[ $key ] );
				if ( $val > 0 ) {
					update_post_meta( $post_id, $key, $val );
				} else {
					delete_post_meta( $post_id, $key );
				}
			}
		}

		// ---- Multi-select: optimization progress ----
		$valid_progress = array_keys( Meta_Fields::optimization_progress_options() );
		if ( isset( $_POST['scos_ca_optimization_progress'] ) ) {
			$raw      = (array) $_POST['scos_ca_optimization_progress'];
			$progress = array_values( array_filter( $raw, function ( $v ) use ( $valid_progress ) {
				return in_array( $v, $valid_progress, true );
			} ) );
			update_post_meta( $post_id, 'scos_ca_optimization_progress', $progress );
		} else {
			// All checkboxes unchecked — store empty array.
			update_post_meta( $post_id, 'scos_ca_optimization_progress', [] );
		}
	}

	/**
	 * Enqueue CSS + JS on post edit screens for supported post types.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		global $post;
		if ( ! $post || ! in_array( $post->post_type, Taxonomies::get_post_types(), true ) ) {
			return;
		}

		$css_path = SITE_ESSENTIALS_PATH . 'Modules/ContentArchitecture/assets/meta-box.css';
		$js_path  = SITE_ESSENTIALS_PATH . 'Modules/ContentArchitecture/assets/meta-box.js';

		wp_enqueue_style(
			'scos-ca-meta-box',
			SITE_ESSENTIALS_URL . 'Modules/ContentArchitecture/assets/meta-box.css',
			[],
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0'
		);

		wp_enqueue_script(
			'scos-ca-meta-box',
			SITE_ESSENTIALS_URL . 'Modules/ContentArchitecture/assets/meta-box.js',
			[ 'jquery' ],
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0.0',
			true
		);

		wp_localize_script( 'scos-ca-meta-box', 'scosCA', [
			'nonce'        => wp_create_nonce( 'scos_ca_add_term' ),
			'ajaxurl'      => admin_url( 'admin-ajax.php' ),
			'restUrl'      => rest_url( 'site-essentials/v1/faqs' ),
			'restNonce'    => wp_create_nonce( 'wp_rest' ),
			'faqModuleActive' => defined( 'SCOS_FAQ_ACTIVE' ) ? true : false,
			'i18n'         => [
				'adding'         => __( 'Adding…', 'site-essentials' ),
				'add'            => __( 'Add', 'site-essentials' ),
				'errorEmpty'     => __( 'Please enter a name.', 'site-essentials' ),
				'errorFailed'    => __( 'Could not add term. Please try again.', 'site-essentials' ),
				'faqSearch'      => __( 'Search FAQs…', 'site-essentials' ),
				'faqAddNew'      => __( '+ Add FAQ', 'site-essentials' ),
				'faqModalTitle'  => __( 'Add Search Intent Goal FAQ', 'site-essentials' ),
				'faqQuestion'    => __( 'Question / FAQ title', 'site-essentials' ),
				'faqUseTopic'    => __( 'Assign page topic to new FAQ', 'site-essentials' ),
				'faqAddNow'      => __( 'Add FAQ now', 'site-essentials' ),
				'faqAddOnSave'   => __( 'Create when saving post', 'site-essentials' ),
				'faqCancel'      => __( 'Cancel', 'site-essentials' ),
				'faqCreating'    => __( 'Creating…', 'site-essentials' ),
				'faqCreated'     => __( 'FAQ created as draft — add an answer.', 'site-essentials' ),
				'faqIncomplete'  => __( 'This FAQ needs an answer — ', 'site-essentials' ),
				'faqEditLink'    => __( 'edit FAQ ↗', 'site-essentials' ),
				'faqClear'       => __( '✕ Remove', 'site-essentials' ),
				'faqDraft'       => __( 'Draft', 'site-essentials' ),
				'faqNeedsAnswer' => __( 'Needs answer', 'site-essentials' ),
			],
		] );
	}

	/**
	 * AJAX: quick-add a new Content Cluster or Topic term.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_add_term() {
		check_ajax_referer( 'scos_ca_add_term' );

		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'site-essentials' ) );
		}

		$taxonomy = sanitize_key( isset( $_POST['taxonomy'] ) ? $_POST['taxonomy'] : '' );
		$name     = sanitize_text_field( isset( $_POST['name'] ) ? $_POST['name'] : '' );

		if ( ! in_array( $taxonomy, [ 'scos_content_cluster', 'scos_topic' ], true ) || empty( $name ) ) {
			wp_send_json_error( __( 'Invalid request.', 'site-essentials' ) );
		}

		$result = wp_insert_term( $name, $taxonomy );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		$term = get_term( $result['term_id'], $taxonomy );
		wp_send_json_success( [
			'term_id' => $result['term_id'],
			'name'    => $term->name,
		] );
	}
}
