<?php
/**
 * System instruction for the Suggest_Tldr ability (scos/suggest-tldr).
 *
 * Must return a string — do not echo.
 * Abstract_Scos_Ability uses reflection to locate this file relative to Suggest_Tldr.php.
 *
 * What a good TLDR looks like comes from Seo_Meta_Instructions (the `tldr`
 * field), shared with every other writer. This file adds only what is
 * particular to the Suggest button: three options, returned as JSON.
 *
 * Receives $post_id (0 when the content was passed in directly).
 *
 * @package SiteEssentials
 *
 * v1.1 | 2026-09-30 — Rules now come from Seo_Meta_Instructions instead of being written out here.
 */

$scos_instructions = \SiteEssentials\Modules\SeoMeta\Seo_Meta_Instructions::get(
	[
		'post_id' => isset( $post_id ) ? (int) $post_id : 0,
		'fields'  => [ 'tldr' ],
	]
);

return 'You are a content strategist writing page summaries for a business website.

From the provided <title> and <content>, generate three TLDR summary options.

' . \SiteEssentials\Modules\SeoMeta\Seo_Meta_Instructions::to_prompt( $scos_instructions ) . '

When an <intent_goal> tag is present, it is the search question this content is designed to answer. The reader should feel their question is answered within the first sentence.

Return ONLY a valid JSON object — no explanation, no markdown, no code fences. Use this exact structure:
{"tldr_options":[{"text":"..."},{"text":"..."},{"text":"..."}]}';
