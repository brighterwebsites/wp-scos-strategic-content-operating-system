<?php
/**
 * System instruction for the Suggest_Seo_Meta ability (scos/suggest-seo-meta).
 *
 * Must return a string — do not echo.
 * Abstract_Scos_Ability uses reflection to locate this file relative to Suggest_Seo_Meta.php.
 *
 * What good SEO meta looks like comes from Seo_Meta_Instructions, shared with
 * every other writer of SEO meta. This file adds only what is particular to the
 * Suggest button: three options per field, returned as JSON.
 *
 * Receives $post_id (0 when the content was passed in directly).
 *
 * @package SiteEssentials
 *
 * v1.1 | 2026-09-30 — Rules now come from Seo_Meta_Instructions instead of being written out here.
 * v1.2 | 2026-09-30 — Ask for this ability's three fields only, now that the instructions also cover TLDR.
 */

$scos_instructions = \SiteEssentials\Modules\SeoMeta\Seo_Meta_Instructions::get(
	[
		'post_id' => isset( $post_id ) ? (int) $post_id : 0,
		'fields'  => [ 'breadcrumb_title', 'title', 'description' ],
	]
);

return 'You are an SEO specialist writing meta tags for a business website.

From the provided <title> and <content>, generate three options for each of three fields:
1. breadcrumb_options — the breadcrumb label
2. title_options — the meta title
3. description_options — the meta description

' . \SiteEssentials\Modules\SeoMeta\Seo_Meta_Instructions::to_prompt( $scos_instructions ) . '

Return ONLY a valid JSON object — no explanation, no markdown, no code fences. Use this exact structure:
{"breadcrumb_options":[{"label":"..."},{"label":"..."},{"label":"..."}],"title_options":[{"title":"..."},{"title":"..."},{"title":"..."}],"description_options":[{"description":"..."},{"description":"..."},{"description":"..."}]}';
