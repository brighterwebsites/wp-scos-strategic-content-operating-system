# SEO Meta — Spec
<!-- v1.0 | 2026-11-26 -->

Contracts in this module that aren't obvious from the code.

## Sitemaps: what's listed where and why

One function decides: `Url_Seo_State::sitemap_eligibility( $ctx, 'xml'|'html', $settings )`.
The sitemap generators (`Seo_Module`), the `[site_essentials_sitemap]` shortcode,
`scos/get-url-seo-state` and `wp scos url-seo-state` all go through it, so they can't disagree.
Per-post entries in a sitemap build use `Url_Seo_State::post_sitemap_decision()`, the same rules
without building a full request context per post.

The two sitemaps differ on purpose. The XML sitemap is for search engines. The HTML sitemap is for
people (another way to find a page), and Google barely indexes it.

| Condition | XML | HTML | Reason code |
|---|---|---|---|
| Redirect / 410 / single-URL guard / attachment redirect | out | out | `redirects:<reason>` |
| Not published, in `exclude_ids`, post type or taxonomy not in the sitemap | out | out | `not_published`, `excluded_id`, `type_not_in_sitemap`, `taxonomy_not_in_sitemap` |
| Per-post exclude (`scos_seo_sitemap_exclude` xml / html) | its own channel only | its own channel only | `excluded_by_post` |
| Post noindex | out, unless the XML override is on (`override`) | out; **in** when "HTML sitemap: list for visitors" is ticked | `noindex`, `listed_for_visitors` |
| Non-self canonical | out, unless the XML override is on | out (a duplicate; its target is listed) | `non_self_canonical` |
| Archive or taxonomy noindex (Archive Settings robots) | out | out | `archive_noindex` |
| Archive's own "Exclude from XML/HTML sitemap" (CPT and posts archives) | its own channel only | its own channel only | `excluded_by_archive` |
| Settings › Reading › Discourage search engines | out (every XML sitemap served empty) | in | `blog_public` |

Notes:

- **Same URL** (canonical comparisons): `Url_Seo_State::same_url()` ignores the trailing slash,
  scheme, host letter case and `#fragment`. A query string or `www` makes it a different URL.
  Mirrored in `assets/meta-box.js` (`scosSameUrl`); cases in `scripts/tests/fixtures/same-url-cases.json`.
- **Posts page and shop page** are served as archives, so they are listed by their Archive Settings
  (robots, sitemap exclude, redirects), not the Page's own meta. The URL is listed once, in the page
  sitemap. Their meta box hides the fields Archive Settings own.
- **Meta keys:** `scos_seo_sitemap_noindex_override` (XML only), `scos_seo_sitemap_html_noindex_include`
  (HTML only). `scos_seo_sitemap_noindex_auto` is legacy: no longer written; a save clears it.
- **One-off WP-CLI, dry run unless `--apply`:** `wp scos sitemap-noindex-cleanup` (auto-added 'xml'
  exclusions), `wp scos archive-page-meta` (copy posts page / shop title and description into empty
  Archive Settings fields).
- `warnings` in the URL report keeps checks for each rule above. They should never fire; one that
  does is a regression.
