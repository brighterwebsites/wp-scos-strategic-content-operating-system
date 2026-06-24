<?php
/**
 * Content Architecture meta box — 3-tab UI view.
 *
 * Variables available from Meta_Box::render():
 *   $post                    WP_Post
 *   $current_cluster         int   term_id or 0
 *   $current_topic           int   term_id or 0
 *   $clusters                WP_Term[]
 *   $topics                  WP_Term[]
 *   $pillar_pages            WP_Post[]
 *   $service_pathway_pages   WP_Post[]
 *   $fields                  array  strategy + workflow post meta
 *                              (includes 'intent_goal_faq_id')
 *   $analysis                array  content analysis read-only data
 *   $intent_goal_faq_summary array|null  from Intent_Goal_Resolver::get_faq_summary()
 *   $faq_module_active       bool  true when SCOS_FAQ_ACTIVE is defined
 *
 * @package SiteEssentials
 */

defined( 'ABSPATH' ) || exit;

$purpose_type_labels = [
	'service-page'   => '[Service]',
	'product-page'   => '[Product]',
	'conversion-hub' => '[Hub]',
];
?>

<div class="scos-ca-wrap">

	<!-- ───────────────── TAB NAV ───────────────── -->
	<nav class="scos-ca-tabs" role="tablist">
		<button type="button" class="scos-ca-tab-btn is-active" data-tab="strategy"
			role="tab" aria-selected="true" aria-controls="scos-tab-strategy">
			<?php esc_html_e( 'Strategy', 'site-essentials' ); ?>
		</button>
		<button type="button" class="scos-ca-tab-btn" data-tab="analysis"
			role="tab" aria-selected="false" aria-controls="scos-tab-analysis">
			<?php esc_html_e( 'Analysis', 'site-essentials' ); ?>
			<?php if ( $analysis['last_analyzed'] ) : ?>
				<span class="scos-ca-tab-dot" title="<?php esc_attr_e( 'Analysis available', 'site-essentials' ); ?>"></span>
			<?php endif; ?>
		</button>
		<button type="button" class="scos-ca-tab-btn" data-tab="workflow"
			role="tab" aria-selected="false" aria-controls="scos-tab-workflow">
			<?php esc_html_e( 'Workflow', 'site-essentials' ); ?>
		</button>
	</nav>

	<!-- ═══════════════ STRATEGY TAB ═══════════════ -->
	<div class="scos-ca-tab-panel is-active" id="scos-tab-strategy" role="tabpanel">

		<!-- Classification -->
		<div class="scos-ca-section">
			<h4 class="scos-ca-section-title"><?php esc_html_e( 'Classification', 'site-essentials' ); ?></h4>

			<div class="scos-ca-field-row">

				<!-- Content Cluster -->
				<div class="scos-ca-field">
					<label for="scos_ca_cluster"><?php esc_html_e( 'Cluster', 'site-essentials' ); ?></label>
					<select name="scos_ca_cluster" id="scos_ca_cluster">
						<option value="0"><?php esc_html_e( '— None —', 'site-essentials' ); ?></option>
						<?php foreach ( $clusters as $term ) : ?>
							<option value="<?php echo esc_attr( $term->term_id ); ?>"
								<?php selected( $current_cluster, $term->term_id ); ?>>
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php /* Add Cluster button intentionally omitted — new clusters should be created via Content Architecture > Clusters menu, not per-post */ ?>
					<p class="scos-ca-help"><?php esc_html_e( 'Select the Strategic Content Cluster this content belongs to.', 'site-essentials' ); ?></p>
				</div>

				<!-- Primary Topic -->
				<div class="scos-ca-field">
					<label for="scos_ca_topic"><?php esc_html_e( 'Primary Topic', 'site-essentials' ); ?></label>
					<select name="scos_ca_topic" id="scos_ca_topic">
						<option value="0"><?php esc_html_e( '— None —', 'site-essentials' ); ?></option>
						<?php foreach ( $topics as $term ) : ?>
							<option value="<?php echo esc_attr( $term->term_id ); ?>"
								<?php selected( $current_topic, $term->term_id ); ?>>
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="scos-ca-add-term button-link"
						data-taxonomy="scos_topic"
						data-target="scos_ca_topic"
						data-label="<?php esc_attr_e( 'New Topic', 'site-essentials' ); ?>">
						+ <?php esc_html_e( 'Add Topic', 'site-essentials' ); ?>
					</button>
					<p class="scos-ca-help"><?php esc_html_e( 'Select or create the Primary Topic for this content.', 'site-essentials' ); ?></p>
				</div>

			</div>

			<!-- Supporting Topics (full-width, below cluster + primary row) -->
			<div class="scos-ca-field scos-ca-field--full" style="margin-top:10px">
				<label><?php esc_html_e( 'Supporting Topics', 'site-essentials' ); ?></label>
				<select name="scos_ca_supporting_topics[]"
				        id="scos_ca_supporting_topics"
				        multiple
				        size="<?php echo esc_attr( min( 6, max( 3, count( $topics ) ) ) ); ?>"
				        class="scos-ca-multi-select">
					<?php foreach ( $topics as $term ) :
						$indent = $term->parent ? '&nbsp;&nbsp;&nbsp;' : '';
					?>
						<option value="<?php echo esc_attr( $term->term_id ); ?>"
							<?php echo in_array( (int) $term->term_id, $current_supporting_topics, true ) ? 'selected' : ''; ?>>
							<?php echo $indent . esc_html( $term->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="scos-ca-help">
					<?php esc_html_e( 'Internal use only — not sent to CAR or GA4. Used for topical coverage reporting and internal link suggestions. Ctrl/Cmd+click to select multiple.', 'site-essentials' ); ?>
					<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=scos_topic&post_type=page' ) ); ?>"
					   target="_blank" rel="noopener" style="margin-left:6px">
						<?php esc_html_e( 'Manage topics ↗', 'site-essentials' ); ?>
					</a>
				</p>
			</div>

		</div>

		<!-- Structural Context -->
		<div class="scos-ca-section">
			<h4 class="scos-ca-section-title"><?php esc_html_e( 'Structural Context', 'site-essentials' ); ?></h4>

			<div class="scos-ca-field-row">

				<!-- Pillar Page -->
				<div class="scos-ca-field">
					<label for="scos_ca_pillar_page_id"><?php esc_html_e( 'Pillar Page', 'site-essentials' ); ?></label>
					<select name="scos_ca_pillar_page_id" id="scos_ca_pillar_page_id">
						<option value="0"><?php esc_html_e( '— None —', 'site-essentials' ); ?></option>
						<?php foreach ( $pillar_pages as $p ) : ?>
							<option value="<?php echo esc_attr( $p->ID ); ?>"
								<?php selected( $fields['pillar_page_id'], $p->ID ); ?>>
								<?php echo esc_html( get_the_title( $p ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="scos-ca-help"><?php esc_html_e( 'Link to the parent Pillar page this content supports.', 'site-essentials' ); ?></p>
				</div>

				<!-- Service Pathway -->
				<div class="scos-ca-field">
					<label for="scos_ca_service_pathway_id"><?php esc_html_e( 'Service Pathway', 'site-essentials' ); ?></label>
					<select name="scos_ca_service_pathway_id" id="scos_ca_service_pathway_id">
						<option value="0"><?php esc_html_e( '— None —', 'site-essentials' ); ?></option>
						<?php foreach ( $service_pathway_pages as $p ) :
							$p_purpose = get_post_meta( $p->ID, 'scos_ca_purpose', true );
							$type_suffix = isset( $purpose_type_labels[ $p_purpose ] )
								? ' ' . $purpose_type_labels[ $p_purpose ] : '';
						?>
							<option value="<?php echo esc_attr( $p->ID ); ?>"
								<?php selected( $fields['service_pathway_id'], $p->ID ); ?>>
								<?php echo esc_html( get_the_title( $p ) . $type_suffix ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="scos-ca-help"><?php esc_html_e( 'Link to the related Service, Product, or Conversion Hub this content drives towards.', 'site-essentials' ); ?></p>
				</div>

			</div>
		</div>

		<!-- Strategic Intent -->
		<div class="scos-ca-section">
			<h4 class="scos-ca-section-title"><?php esc_html_e( 'Strategic Intent', 'site-essentials' ); ?></h4>

			<div class="scos-ca-field-row scos-ca-field-row--3">

				<!-- Intent -->
				<div class="scos-ca-field">
					<label for="scos_ca_intent"><?php esc_html_e( 'Intent', 'site-essentials' ); ?></label>
					<select name="scos_ca_intent" id="scos_ca_intent">
						<?php foreach ( \SiteEssentials\Modules\ContentArchitecture\Meta_Fields::intent_options() as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>"
								<?php selected( $fields['intent'], $val ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="scos-ca-help"><?php esc_html_e( 'User search intent for this content.', 'site-essentials' ); ?></p>
				</div>

				<!-- Purpose -->
				<div class="scos-ca-field">
					<label for="scos_ca_purpose"><?php esc_html_e( 'Purpose', 'site-essentials' ); ?></label>
					<select name="scos_ca_purpose" id="scos_ca_purpose">
						<?php foreach ( \SiteEssentials\Modules\ContentArchitecture\Meta_Fields::purpose_options() as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>"
								<?php selected( $fields['purpose'], $val ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="scos-ca-help"><?php esc_html_e( 'Content purpose in strategy.', 'site-essentials' ); ?></p>
				</div>

				<!-- Content Maturity -->
				<div class="scos-ca-field">
					<label for="scos_ca_maturity"><?php esc_html_e( 'Content Maturity', 'site-essentials' ); ?></label>
					<select name="scos_ca_maturity" id="scos_ca_maturity">
						<?php foreach ( \SiteEssentials\Modules\ContentArchitecture\Meta_Fields::maturity_options() as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>"
								<?php selected( $fields['maturity'], $val ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

			</div>

			<!-- Search Intent Goal -->
			<div class="scos-ca-field scos-ca-field--full" id="scos-intent-goal-wrap">
				<label><?php esc_html_e( 'Search Intent Goal', 'site-essentials' ); ?></label>

				<?php if ( ! empty( $faq_module_active ) ) : ?>

					<!-- Hidden field carries the linked FAQ ID on form submit -->
					<input type="hidden"
						name="scos_ca_intent_goal_faq_id"
						id="scos_ca_intent_goal_faq_id"
						value="<?php echo esc_attr( $fields['intent_goal_faq_id'] ); ?>">

					<!-- Pending stub title (on-save create path) -->
					<input type="hidden"
						name="scos_ca_intent_goal_pending_faq_title"
						id="scos_ca_intent_goal_pending_faq_title"
						value="">

					<?php if ( $intent_goal_faq_summary ) : ?>
						<!-- ── Linked FAQ panel ── -->
						<div class="scos-ca-intent-faq-panel" id="scos-intent-faq-panel">
							<div class="scos-ca-intent-faq-question">
								<?php echo esc_html( $intent_goal_faq_summary['title'] ); ?>
								<?php if ( ! empty( $intent_goal_faq_summary['topic'] ) ) : ?>
									<span class="scos-ca-intent-faq-topic"><?php echo esc_html( $intent_goal_faq_summary['topic'] ); ?></span>
								<?php endif; ?>
							</div>
							<div class="scos-ca-intent-faq-actions">
								<a href="<?php echo esc_url( $intent_goal_faq_summary['edit_url'] ); ?>"
									target="_blank" rel="noopener" class="scos-ca-intent-faq-edit">
									<?php esc_html_e( 'Edit FAQ ↗', 'site-essentials' ); ?>
								</a>
								<button type="button" class="scos-ca-intent-faq-clear button-link">
									<?php esc_html_e( '✕ Remove', 'site-essentials' ); ?>
								</button>
							</div>
							<?php if ( $intent_goal_faq_summary['incomplete'] ) : ?>
								<div class="scos-ca-intent-faq-incomplete">
									<?php esc_html_e( 'This FAQ needs an answer — ', 'site-essentials' ); ?>
									<a href="<?php echo esc_url( $intent_goal_faq_summary['edit_url'] ); ?>"
										target="_blank" rel="noopener">
										<?php esc_html_e( 'edit FAQ ↗', 'site-essentials' ); ?>
									</a>
								</div>
							<?php endif; ?>
						</div><!-- /faq-panel -->

					<?php else : ?>
						<!-- ── Picker (no FAQ linked yet) ── -->
						<div class="scos-ca-intent-faq-picker" id="scos-intent-faq-picker">
							<div class="scos-ca-intent-faq-search-row">
								<input type="text"
									id="scos-intent-faq-search"
									class="scos-ca-intent-faq-search"
									placeholder="<?php esc_attr_e( 'Search FAQs…', 'site-essentials' ); ?>"
									autocomplete="off">
								<button type="button" class="button scos-ca-intent-faq-add-btn" id="scos-intent-faq-add-btn">
									<?php esc_html_e( '+ Add FAQ', 'site-essentials' ); ?>
								</button>
							</div>
							<ul class="scos-ca-intent-faq-results" id="scos-intent-faq-results" hidden></ul>
						</div><!-- /picker -->

					<?php endif; // intent_goal_faq_summary ?>

					<!-- ── Add FAQ modal ── -->
					<div class="scos-ca-intent-faq-modal" id="scos-intent-faq-modal" hidden role="dialog"
						aria-modal="true" aria-labelledby="scos-intent-faq-modal-title">
						<div class="scos-ca-intent-faq-modal-inner">
							<h3 class="scos-ca-intent-faq-modal-title" id="scos-intent-faq-modal-title">
								<?php esc_html_e( 'Add Search Intent Goal FAQ', 'site-essentials' ); ?>
							</h3>
							<label for="scos-intent-faq-new-title"><?php esc_html_e( 'Question / FAQ title', 'site-essentials' ); ?></label>
							<input type="text" id="scos-intent-faq-new-title" class="scos-ca-intent-faq-new-title"
								placeholder="<?php esc_attr_e( 'e.g. How do I choose a stable builder?', 'site-essentials' ); ?>">
							<label class="scos-ca-intent-faq-use-topic">
								<input type="checkbox" id="scos-intent-faq-use-topic" checked>
								<?php esc_html_e( 'Assign page topic to new FAQ', 'site-essentials' ); ?>
							</label>
							<div class="scos-ca-intent-faq-modal-actions">
								<button type="button" class="button button-primary" id="scos-intent-faq-create-now">
									<?php esc_html_e( 'Add FAQ now', 'site-essentials' ); ?>
								</button>
								<button type="button" class="button" id="scos-intent-faq-create-on-save">
									<?php esc_html_e( 'Create when saving post', 'site-essentials' ); ?>
								</button>
								<button type="button" class="button-link scos-ca-intent-faq-modal-cancel" id="scos-intent-faq-modal-cancel">
									<?php esc_html_e( 'Cancel', 'site-essentials' ); ?>
								</button>
							</div>
							<p class="scos-ca-intent-faq-modal-status" id="scos-intent-faq-modal-status" hidden></p>
						</div>
					</div><!-- /modal -->

				<?php else : ?>
					<!-- FAQ module not active — plain freetext only -->
					<textarea name="scos_ca_intent_goal" id="scos_ca_intent_goal" rows="2"
						placeholder="<?php esc_attr_e( 'e.g. "How to choose a stable builder"', 'site-essentials' ); ?>"><?php echo esc_textarea( $fields['intent_goal'] ); ?></textarea>
				<?php endif; // faq_module_active ?>

				<?php if ( ! empty( $faq_module_active ) && 0 === $fields['intent_goal_faq_id'] ) : ?>
					<!-- Legacy freetext (collapsed when no FAQ linked) -->
					<details class="scos-ca-intent-goal-legacy">
						<summary><?php esc_html_e( 'Free-text goal (legacy)', 'site-essentials' ); ?></summary>
						<textarea name="scos_ca_intent_goal" id="scos_ca_intent_goal" rows="2"
							placeholder="<?php esc_attr_e( 'e.g. "How to choose a stable builder"', 'site-essentials' ); ?>"><?php echo esc_textarea( $fields['intent_goal'] ); ?></textarea>
					</details>
				<?php endif; ?>

				<p class="scos-ca-help"><?php esc_html_e( 'The primary question this content answers. Link an FAQ to make it machine-readable and trackable.', 'site-essentials' ); ?></p>
			</div>

			<?php if ( class_exists( 'WordPress\AI\Abstracts\Abstract_Ability' ) ) : ?>
			<div class="scos-ca-suggest-wrap">
				<button type="button" id="scos-ca-suggest-btn" class="button">
					<?php esc_html_e( 'Suggest with AI', 'site-essentials' ); ?>
				</button>
				<span class="scos-ca-suggest-spinner spinner" style="float:none;vertical-align:middle;margin-left:4px;"></span>
				<p class="scos-ca-suggest-error" id="scos-ca-suggest-error" style="display:none;color:#cc0000;font-size:12px;margin:4px 0 0;"></p>
			</div>
			<div id="scos-ca-suggest-modal" style="display:none;"></div>
			<?php endif; ?>

		</div>
	</div><!-- /strategy -->

	<!-- ═══════════════ ANALYSIS TAB ═══════════════ -->
	<div class="scos-ca-tab-panel" id="scos-tab-analysis" role="tabpanel" hidden>

		<?php if ( ! $analysis['last_analyzed'] ) : ?>

			<div class="scos-ca-empty-state">
				<p><?php esc_html_e( 'Analysis will run automatically the first time this post is saved.', 'site-essentials' ); ?></p>
			</div>

		<?php else : ?>

			<!-- Content Stats -->
			<div class="scos-ca-section">
				<h4 class="scos-ca-section-title"><?php esc_html_e( 'Content Stats', 'site-essentials' ); ?></h4>
				<div class="scos-ca-stats-grid">
					<div class="scos-ca-stat">
						<span class="scos-ca-stat-value"><?php echo number_format( $analysis['word_count'] ); ?></span>
						<span class="scos-ca-stat-label"><?php esc_html_e( 'Words', 'site-essentials' ); ?></span>
					</div>
					<div class="scos-ca-stat">
						<span class="scos-ca-stat-value">
							<?php
							if ( $analysis['reading_time'] ) {
								echo esc_html( $analysis['reading_time'] ) . '<small> min</small>';
							} else {
								echo '—';
							}
							?>
						</span>
						<span class="scos-ca-stat-label"><?php esc_html_e( 'Read Time', 'site-essentials' ); ?></span>
					</div>
					<div class="scos-ca-stat">
						<span class="scos-ca-stat-value"><?php echo esc_html( $analysis['h2_count'] ); ?></span>
						<span class="scos-ca-stat-label"><?php esc_html_e( 'H2s', 'site-essentials' ); ?></span>
					</div>
					<div class="scos-ca-stat">
						<span class="scos-ca-stat-value"><?php echo esc_html( $analysis['image_count'] ); ?></span>
						<span class="scos-ca-stat-label"><?php esc_html_e( 'Images', 'site-essentials' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Internal Linking (from this page to others) -->
			<div class="scos-ca-section">
				<h4 class="scos-ca-section-title"><?php esc_html_e( 'Links from This Page', 'site-essentials' ); ?></h4>
				<div class="scos-ca-stats-grid scos-ca-stats-grid--2">
					<div class="scos-ca-stat">
						<span class="scos-ca-stat-value"><?php echo esc_html( $analysis['links_to_internal'] ); ?></span>
						<span class="scos-ca-stat-label"><?php esc_html_e( 'Internal', 'site-essentials' ); ?></span>
					</div>
					<div class="scos-ca-stat">
						<span class="scos-ca-stat-value"><?php echo esc_html( $analysis['links_to_external'] ); ?></span>
						<span class="scos-ca-stat-label"><?php esc_html_e( 'External', 'site-essentials' ); ?></span>
					</div>
				</div>

				<?php if ( ! empty( $analysis['links_to_internal_list'] ) ) : ?>
					<details class="scos-ca-link-list">
						<summary><?php printf( esc_html__( 'View %d internal links', 'site-essentials' ), count( $analysis['links_to_internal_list'] ) ); ?></summary>
						<ul>
							<?php foreach ( $analysis['links_to_internal_list'] as $link ) :
								$url  = is_array( $link ) ? ( $link['url'] ?? '' ) : $link;
								$text = is_array( $link ) ? ( $link['text'] ?? $url ) : $link;
							?>
								<li><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $text ?: $url ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endif; ?>

				<?php if ( ! empty( $analysis['links_to_external_list'] ) ) : ?>
					<details class="scos-ca-link-list">
						<summary><?php printf( esc_html__( 'View %d external links', 'site-essentials' ), count( $analysis['links_to_external_list'] ) ); ?></summary>
						<ul>
							<?php foreach ( $analysis['links_to_external_list'] as $link ) :
								$url  = is_array( $link ) ? ( $link['url'] ?? '' ) : $link;
								$text = is_array( $link ) ? ( $link['text'] ?? $url ) : $link;
							?>
								<li><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $text ?: $url ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endif; ?>
			</div>

			<p class="scos-ca-last-analyzed">
				<?php
				printf(
					/* translators: %s = human-readable time ago */
					esc_html__( 'Last analysed: %s ago', 'site-essentials' ),
					esc_html( human_time_diff( strtotime( $analysis['last_analyzed'] ), current_time( 'timestamp' ) ) )
				);
				?>
			</p>

		<?php endif; ?>

	</div><!-- /analysis -->

	<!-- ═══════════════ WORKFLOW TAB ═══════════════ -->
	<div class="scos-ca-tab-panel" id="scos-tab-workflow" role="tabpanel" hidden>

		<!-- Index Status -->
		<div class="scos-ca-section">
			<h4 class="scos-ca-section-title"><?php esc_html_e( 'Index Status', 'site-essentials' ); ?></h4>
			<div class="scos-ca-field scos-ca-field--inline">
				<label for="scos_ca_index_status"><?php esc_html_e( 'Current Status', 'site-essentials' ); ?></label>
				<select name="scos_ca_index_status" id="scos_ca_index_status" class="scos-ca-index-select">
					<?php
					$status_colors = \SiteEssentials\Modules\ContentArchitecture\Meta_Fields::index_status_colors();
					foreach ( \SiteEssentials\Modules\ContentArchitecture\Meta_Fields::index_status_options() as $val => $label ) :
						$c = $status_colors[ $val ] ?? [];
					?>
						<option value="<?php echo esc_attr( $val ); ?>"
							<?php selected( $fields['index_status'], $val ); ?>
							<?php if ( $c ) : ?>
								data-color="<?php echo esc_attr( $c['color'] ); ?>"
								data-bg="<?php echo esc_attr( $c['bg'] ); ?>"
							<?php endif; ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="scos-ca-help"><?php esc_html_e( 'Actual Google Search Console index status for this page.', 'site-essentials' ); ?></p>
			</div>
		</div>

		<!-- Optimization Progress -->
		<div class="scos-ca-section">
			<h4 class="scos-ca-section-title"><?php esc_html_e( 'Optimization Progress', 'site-essentials' ); ?></h4>
			<div class="scos-ca-progress-tags">
				<?php foreach ( \SiteEssentials\Modules\ContentArchitecture\Meta_Fields::optimization_progress_options() as $val => $opt ) :
					$checked = in_array( $val, $fields['optimization_progress'], true );
				?>
					<label class="scos-ca-progress-tag<?php echo $checked ? ' is-selected' : ''; ?>"
						style="--tag-color:<?php echo esc_attr( $opt['color'] ); ?>;--tag-bg:<?php echo esc_attr( $opt['bg'] ); ?>">
						<input type="checkbox"
							name="scos_ca_optimization_progress[]"
							value="<?php echo esc_attr( $val ); ?>"
							<?php checked( $checked ); ?>>
						<?php echo esc_html( $opt['label'] ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Next Step -->
		<div class="scos-ca-section">
			<h4 class="scos-ca-section-title"><?php esc_html_e( 'Next Step', 'site-essentials' ); ?></h4>
			<div class="scos-ca-field scos-ca-field--inline">
				<label for="scos_ca_next_step"><?php esc_html_e( 'Planned Action', 'site-essentials' ); ?></label>
				<select name="scos_ca_next_step" id="scos_ca_next_step" class="scos-ca-next-step-select">
					<?php foreach ( \SiteEssentials\Modules\ContentArchitecture\Meta_Fields::next_step_options() as $val => $opt ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>"
							data-color="<?php echo esc_attr( $opt['color'] ); ?>"
							data-bg="<?php echo esc_attr( $opt['bg'] ); ?>"
							<?php selected( $fields['next_step'], $val ); ?>>
							<?php echo esc_html( $opt['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

	</div><!-- /workflow -->

</div><!-- /scos-ca-wrap -->

<!-- Inline quick-add term panel (shown below trigger button) -->
<div class="scos-ca-quick-add" id="scos-ca-quick-add" hidden>
	<label class="scos-ca-quick-add__label" id="scos-ca-quick-add-label"></label>
	<div class="scos-ca-quick-add__row">
		<input type="text" id="scos-ca-quick-add-name"
			placeholder="<?php esc_attr_e( 'Name', 'site-essentials' ); ?>"
			autocomplete="off">
		<button type="button" class="button button-primary" id="scos-ca-quick-add-save">
			<?php esc_html_e( 'Add', 'site-essentials' ); ?>
		</button>
		<button type="button" class="button" id="scos-ca-quick-add-cancel">
			<?php esc_html_e( 'Cancel', 'site-essentials' ); ?>
		</button>
	</div>
</div>
