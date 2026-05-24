<?php
/**
 * Content Architecture — CPT Default Intent & Purpose Assignment
 *
 * When a new FAQ, Project, or Review post is saved for the first time
 * (and the CA module is active), automatically sets `scos_ca_intent` and
 * `scos_ca_purpose` to sensible defaults so the post appears correctly in
 * the CA admin columns and reporting without the editor needing to fill them in.
 *
 * Defaults (matching plan — see meta-key-prefixes.mdc for slug reference):
 *   faq        → intent: informational_s (Info Solution)
 *                purpose: supporting     (Supporting Topic)
 *   projects   → intent: trust           (Authority Trust)
 *                purpose: case-study     (Success Story)
 *   bw_reviews → intent: trust           (Authority Trust)
 *                purpose: authority-page (Brand Authority)
 *
 * Logic: only writes when `scos_ca_intent` is empty — never overwrites a
 * value the editor has deliberately set. Runs after the Meta_Box save (10)
 * and Content_Analysis (25) so the intent/purpose values are available for
 * the same-save analysis run.
 *
 * v1.0 | 2026-05-19
 *
 * @package    SiteEssentials
 * @subpackage Modules\ContentArchitecture
 */

namespace SiteEssentials\Modules\ContentArchitecture;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Defaults {

	/**
	 * Default intent + purpose per CPT post type slug.
	 *
	 * Values must be valid slugs from Meta_Fields::intent_options() and
	 * Meta_Fields::purpose_options() — they are sent to GA4 / Airtable.
	 *
	 * @var array<string, array{intent: string, purpose: string}>
	 */
	const DEFAULTS = [
		// FAQ — every FAQ answers a question, supporting the pillar it belongs to.
		'faq'       => [
			'intent'  => 'informational_s',  // Info Solution
			'purpose' => 'supporting',        // Supporting Topic
		],
		// Success Stories — proof-of-work content, builds trust via social proof.
		'projects'  => [
			'intent'  => 'trust',             // Authority Trust
			'purpose' => 'case-study',        // Success Story
		],
		// Reviews — brand authority via verified customer voice.
		'bw_reviews' => [
			'intent'  => 'trust',             // Authority Trust
			'purpose' => 'authority-page',    // Brand Authority
		],
	];

	/**
	 * Register hooks.
	 *
	 * Priority 30 — after Meta_Box::save (10) and Content_Analysis::analyze (25).
	 * This means the defaults land in the DB in time for any subsequent logic
	 * that reads scos_ca_intent / scos_ca_purpose on the same request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'save_post', [ self::class, 'maybe_set_defaults' ], 30, 3 );
	}

	/**
	 * Set intent + purpose defaults when they aren't already assigned.
	 *
	 * Conditions for writing:
	 *  1. Not an autosave or revision.
	 *  2. Post status is not 'auto-draft' (only write once the post exists
	 *     in a meaningful state — draft, pending, publish, etc.).
	 *  3. Post type is in our DEFAULTS map.
	 *  4. `scos_ca_intent` is currently empty (never overwrite an existing value).
	 *
	 * The current user can check is intentional omitted: when posts are
	 * created programmatically (WP-CLI, MCP, REST API imports) we still want
	 * the defaults written. The Meta_Box::save() handles capability checks for
	 * UI-submitted forms; we only need the nonce skip to be safe for non-UI paths.
	 *
	 * @since 1.0.0
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  True when updating an existing post.
	 * @return void
	 */
	public static function maybe_set_defaults( int $post_id, \WP_Post $post, bool $update ): void {
		// Skip autosaves and revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Don't write to auto-draft placeholders created by the block editor
		// before the user has even entered content.
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		// Only act on the CPT types we have defaults for.
		if ( ! array_key_exists( $post->post_type, self::DEFAULTS ) ) {
			return;
		}

		// Never overwrite a value the editor has already set.
		$existing_intent = get_post_meta( $post_id, 'scos_ca_intent', true );
		if ( '' !== (string) $existing_intent ) {
			return;
		}

		$defaults = self::DEFAULTS[ $post->post_type ];

		update_post_meta( $post_id, 'scos_ca_intent',  $defaults['intent'] );
		update_post_meta( $post_id, 'scos_ca_purpose', $defaults['purpose'] );
	}
}
