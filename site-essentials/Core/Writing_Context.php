<?php
/**
 * Writing Context — what every piece of writing on this site has in common.
 *
 * The instructions classes (Seo_Meta_Instructions and the ones that follow it)
 * each own the rules for one kind of content. This class holds the parts they
 * share: who the business is, the brand voice, the vocabulary to avoid, the
 * page's purpose and the content type its rules are picked by.
 *
 * Nothing here is written for one site. Business details come from the
 * Business Info options, voice from wp-content/ai-knowledge/, purpose from
 * Content Architecture — and every one of them may be absent.
 *
 * @package    SiteEssentials
 * @subpackage Core
 *
 * v1.0 | 2026-09-30
 * v1.1 | 2026-09-30 — intent_goal(); field rules rendering shared by the instructions classes.
 */

declare( strict_types=1 );

namespace SiteEssentials\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Writing_Context {

	/** Optional per-site brand voice, in wp-content/ai-knowledge/. */
	const VOICE_FILE = '205-brand-voice.md';

	/** Content type used when neither purpose nor post type gives one. */
	const DEFAULT_CONTENT_TYPE = 'page';

	/**
	 * Who the business is, from the Business Info options.
	 *
	 * @return array{name:string,category:string,location:string,offering:string,description:string}
	 */
	public static function business(): array {
		return [
			'name'        => trim( (string) get_option( 'scos_biz_business_name', '' ) ) ?: (string) get_bloginfo( 'name' ),
			'category'    => trim( (string) get_option( 'scos_biz_business_category', '' ) ),
			'location'    => trim( (string) get_option( 'scos_biz_city', '' ) ),
			'offering'    => trim( (string) get_option( 'scos_biz_business_offering', '' ) ),
			'description' => trim( (string) get_option( 'scos_biz_service_description', '' ) ),
		];
	}

	/**
	 * The site's brand voice, when it has one.
	 *
	 * A site without the file is normal: `found` is false and `text` is empty.
	 *
	 * @return array{found:bool,source:string,text:string}
	 */
	public static function voice(): array {
		$text = Ai_Knowledge::read( self::VOICE_FILE );

		return [
			'found'  => '' !== $text,
			'source' => Ai_Knowledge::DIR . '/' . self::VOICE_FILE,
			'text'   => $text,
		];
	}

	/**
	 * The note to return when the site has no brand voice file.
	 *
	 * @return string
	 */
	public static function missing_voice_note(): string {
		return sprintf(
			'No brand voice file on this site (wp-content/%1$s/%2$s). Write in plain, specific language and follow the rules as given.',
			Ai_Knowledge::DIR,
			self::VOICE_FILE
		);
	}

	/**
	 * Words and phrases that must not appear in anything written for the site.
	 *
	 * @return string[]
	 */
	public static function avoid(): array {
		return [
			'learn more',
			'click here',
			'discover',
			'solutions',
			'leverage',
			'cutting-edge',
			'game-changing',
			'synergy',
			'next-level',
			'seamless',
			'robust',
			'empower',
		];
	}

	/**
	 * The page's purpose, as set in Content Architecture.
	 *
	 * @param int $post_id Post ID.
	 * @return array{key:string,label:string} Empty strings when not set.
	 */
	public static function purpose( int $post_id ): array {
		$key = $post_id ? sanitize_key( (string) get_post_meta( $post_id, 'scos_ca_purpose', true ) ) : '';
		if ( '' === $key ) {
			return [
				'key'   => '',
				'label' => '',
			];
		}

		$label   = $key;
		$options = '\SiteEssentials\Modules\ContentArchitecture\Meta_Fields';
		if ( class_exists( $options ) ) {
			$labels = (array) $options::purpose_options();
			$label  = (string) ( $labels[ $key ] ?? $key );
		}

		return [
			'key'   => $key,
			'label' => $label,
		];
	}

	/**
	 * The search question a post is written to answer, from Content Architecture.
	 *
	 * The linked FAQ's title when there is one, otherwise the freetext goal.
	 *
	 * @param int $post_id Post ID.
	 * @return string Empty when the post has none.
	 */
	public static function intent_goal( int $post_id ): string {
		if ( ! $post_id ) {
			return '';
		}

		$faq_id = (int) get_post_meta( $post_id, 'scos_ca_intent_goal_faq_id', true );
		if ( $faq_id > 0 ) {
			$faq = get_post( $faq_id );
			if ( $faq instanceof \WP_Post && '' !== $faq->post_title ) {
				return (string) $faq->post_title;
			}
		}

		return trim( (string) get_post_meta( $post_id, 'scos_ca_intent_goal', true ) );
	}

	/**
	 * The content type that decides which type rules apply.
	 *
	 * The page's purpose wins when it is set — on a builder site almost
	 * everything is post type `page`, and purpose is what tells a service page
	 * from a pillar. The post type is the fallback.
	 *
	 * @param string $post_type   Post type slug. May be empty.
	 * @param string $purpose_key scos_ca_purpose value. May be empty.
	 * @return string One of: article, page, product, service, case-study (or a filtered value).
	 */
	public static function content_type( string $post_type, string $purpose_key = '' ): string {
		$by_purpose = [
			'service-page'   => 'service',
			'product-page'   => 'product',
			'case-study'     => 'case-study',
			'pillar'         => 'article',
			'supporting'     => 'article',
			'resource-guide' => 'article',
		];

		$by_post_type = [
			'post'     => 'article',
			'page'     => 'page',
			'product'  => 'product',
			'service'  => 'service',
			'services' => 'service',
			'project'  => 'case-study',
			'projects' => 'case-study',
		];

		$content_type = $by_purpose[ $purpose_key ] ?? $by_post_type[ $post_type ] ?? self::DEFAULT_CONTENT_TYPE;

		/**
		 * Filters the content type used to pick writing rules.
		 *
		 * @param string $content_type The resolved content type.
		 * @param string $post_type    The post type slug.
		 * @param string $purpose_key  The page's scos_ca_purpose value, or an empty string.
		 */
		return (string) apply_filters( 'scos_writing_content_type', $content_type, $post_type, $purpose_key );
	}

	/**
	 * Render each field's rules as plain text for a system prompt.
	 *
	 * A field has label, unit, max, rules and optionally min, target and
	 * type_rules. A field with no min reads "up to {max}".
	 *
	 * @param array<string,array<string,mixed>> $fields       Fields from an instructions class.
	 * @param string                            $content_type Content type the type_rules were chosen for.
	 * @return string Ends with a blank line when there is at least one field.
	 */
	public static function fields_to_prompt( array $fields, string $content_type = '' ): string {
		$lines = [];

		foreach ( $fields as $field ) {
			$limit = empty( $field['min'] )
				? sprintf( 'up to %d %s', (int) $field['max'], (string) $field['unit'] )
				: sprintf( '%d–%d %s', (int) $field['min'], (int) $field['max'], (string) $field['unit'] );
			if ( ! empty( $field['target'] ) ) {
				$limit .= sprintf( ', aim for %d', (int) $field['target'] );
			}

			$lines[] = sprintf( '%s — %s:', (string) $field['label'], $limit );
			foreach ( (array) ( $field['rules'] ?? [] ) as $rule ) {
				$lines[] = '- ' . $rule;
			}
			foreach ( (array) ( $field['type_rules'] ?? [] ) as $rule ) {
				$lines[] = sprintf( '- For this content type (%s): %s', $content_type, $rule );
			}
			$lines[] = '';
		}

		return $lines ? implode( "\n", $lines ) . "\n" : '';
	}

	/**
	 * Render the shared context as plain text for a system prompt.
	 *
	 * Reads the keys the instructions classes all return: business, purpose,
	 * intent_goal, general_rules, avoid, facts and voice. Anything absent is
	 * left out.
	 *
	 * @param array<string,mixed> $instructions Result of an instructions class's get().
	 * @return string
	 */
	public static function to_prompt( array $instructions ): string {
		$lines = [];

		$business = (array) ( $instructions['business'] ?? [] );
		$about    = array_filter(
			[
				'Business'      => (string) ( $business['name'] ?? '' ),
				'Category'      => (string) ( $business['category'] ?? '' ),
				'Location'      => (string) ( $business['location'] ?? '' ),
				'Main offering' => (string) ( $business['offering'] ?? '' ),
			]
		);
		if ( $about ) {
			$lines[] = 'The business this is written for:';
			foreach ( $about as $label => $value ) {
				$lines[] = '- ' . $label . ': ' . $value;
			}
		}

		$purpose = (string) ( $instructions['purpose']['label'] ?? '' );
		if ( '' !== $purpose ) {
			$lines[] = '- Purpose of this page: ' . $purpose;
		}

		$intent_goal = (string) ( $instructions['intent_goal'] ?? '' );
		if ( '' !== $intent_goal ) {
			$lines[] = '- Search question this page answers: ' . $intent_goal;
		}

		if ( $lines ) {
			$lines[] = '';
		}

		$lines[] = 'General rules:';
		foreach ( (array) ( $instructions['general_rules'] ?? [] ) as $rule ) {
			$lines[] = '- ' . $rule;
		}

		$avoid = (array) ( $instructions['avoid'] ?? [] );
		if ( $avoid ) {
			$lines[] = '- Never use these words or phrases: "' . implode( '", "', $avoid ) . '"';
		}

		$facts = (array) ( $instructions['facts'] ?? [] );
		if ( $facts ) {
			$lines[] = '';
			$lines[] = 'Facts about this post — use them, do not contradict them:';
			foreach ( $facts as $fact ) {
				$lines[] = '- ' . (string) $fact;
			}
		}

		$voice = (string) ( $instructions['voice']['text'] ?? '' );
		if ( '' !== $voice ) {
			$lines[] = '';
			$lines[] = 'Brand voice for this site — match it:';
			$lines[] = $voice;
		}

		return implode( "\n", $lines );
	}
}
