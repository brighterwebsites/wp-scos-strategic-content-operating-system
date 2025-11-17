# MU Brighter Support - Codebase Audit

**Date:** 2025-11-17
**Version Audited:** 4.3.0
**Purpose:** Complete inventory before modular refactor to "Site Essentials"

---

## Executive Summary

This MU plugin has **evolved organically** from a client support tool into a **comprehensive site management system**. It currently contains **38+ PHP files** across **7 functional areas** with **1,275+ lines** of custom JavaScript for GA4 tracking.

**Key Findings:**
- ✅ **Solid foundation** - Well-structured features with security practices
- ⚠️ **Growing complexity** - Features scattered across multiple files
- ⚠️ **Performance concerns** - Some features always load even when not needed
- 🎯 **High extraction potential** - Most modules are already self-contained
- 🔧 **Technical debt** - File naming inconsistencies, some deprecated code paths

---

## 1. File Structure Inventory

### Root Files (3)
```
brighter-core-loader.php          # MU plugin entry point (3 lines)
brighter-ga4-tracking.php         # GA4 tracking loader (229 lines)
LICENSE                           # GPL v2 license
```

### Main Plugin Structure
```
brighter-core/
├── brighter-core.php             # Core loader & module manager (484 lines)
├── includes/                     # 35 PHP modules
├── css/                          # 3 stylesheets
├── js/                           # 4 JavaScript files
└── assets/                       # Images (brighter-logo.png)
```

---

## 2. Feature Inventory by Module

### MODULE 0: Brighter Websites Support Page 🆕
**Purpose:** Customer-facing support dashboard
**Status:** ✅ Active, Agency tier feature

**Files:**
- `brighter-support.php` (399 lines)
- `brighter-admin-branding.php`
- `css/admin-support.css`

**Features:**
- Support hub menu page
- Tabbed admin interface (Support Info, Manual Links, Analytics, Business Info, Optimization, Tweaks)
- Links to manuals, ranking tools, knowledge base
- GA4 settings management
- Admin-only tool shortcuts

**WordPress Hooks:**
- `admin_menu` - Adds "Support" top-level menu
- `admin_init` - Registers settings
- Custom tabs via filter: `brighter_support_tabs`

**Dependencies:**
- Integrates with Business Info module
- Integrates with Analytics module
- Integrates with Tweaks module

**Extractable:** ⚠️ Medium difficulty - Central hub, but could be standalone dashboard plugin

---

### MODULE 1: Performance Module

#### 1A. WordPress Tweaks (brighter-tweaks.php - 617 lines)
**Purpose:** Site performance optimizations & preloading

**Features:**
- **Per-page asset preloads** - Images, fonts, CSS, JS with `<link rel="preload">`
- **Auto-preload featured images** on singles (uses OG image size 1200x630)
- **Google Fonts removal** - Nuclear option (regex stripping)
- **LCP optimization** - `fetchpriority="high"` on critical images
- **Path normalization** - `/core/storage/` → `/storage/`
- **Theme color setting** - Brand color for admin tools
- **Admin interface** - Paginated table for per-page preload management

**WordPress Hooks:**
- `wp_head` (priority 1) - Output preloads early
- `admin_init` - Register settings
- `add_meta_boxes` - Page-level preload meta box
- `save_post_page` - Save preload meta
- `wp_get_attachment_image_attributes` - Add fetchpriority
- `the_content` - Add fetchpriority to content images
- `template_redirect` - Path normalization via output buffering

**Post Meta:**
- `_bw_preloads` (array) - URLs to preload per page

**Options:**
- `bw_preloads_map` (array) - Global preloads map
- `theme_colour` (string) - Hex color
- `brighter_preload_post_types` (array) - Post types for featured image preload

**Extractable:** ✅ **Easy** - Self-contained, no external dependencies

---

#### 1B. Image Optimization (image-optimisation.php - 290 lines)
**Purpose:** Runtime image management

**Features:**
- **Resize on upload** - Max dimension enforcement (default 2480px)
- **JPEG quality control** - Default 75%
- **Image size management** - Enable/disable: thumbnail, medium, large, og-image (1200x630), etc.
- **OG image injection** - Direct meta tags for featured images (bypasses SEOPress issues)
- **Author meta tags** - For LinkedIn/schema
- **LiteSpeed lazy-load CSS** - Fade-in effect
- **Comment disable** - On attachment pages

**WordPress Hooks:**
- `wp_handle_upload` - Resize images
- `init` - Register/deregister image sizes
- `intermediate_image_sizes` - Filter generated sizes
- `image_size_names_choose` - Add custom sizes to dropdown
- `wp_head` (priority 1) - Inject OG image tags
- `comments_open` - Disable for attachments

**Image Sizes Registered:**
- `thumbnail` (150x150)
- `medium` (300x0)
- `medium_large` (768x0)
- `large` (1200x0)
- `custom_768w` (768x0)
- `custom_1200w` (1200x0)
- `og-image` (1200x630, cropped) ⭐
- `1536x1536`, `2048x2048`

**Options:**
- `enable_image_resize` (yes/no)
- `image_max_dimension` (int)
- `jpeg_quality` (int)
- `enable_size_{size_name}` (bool) - Per-size toggles

**Extractable:** ✅ **Easy** - Standalone image management plugin

---

#### 1C. Cache Dashboard (bw-support-cache-dashbrd.php)
**Purpose:** Object cache monitoring & purging

**Features:**
- Cache status display
- Manual cache purge
- Performance metrics

**WordPress Hooks:**
- Admin page integration
- Cache helpers: `brighter_cache_set()`, `brighter_cache_get()`, `brighter_cache_delete()`

**Extractable:** ✅ **Easy** - Works with any object cache

---

### MODULE 2: Analytics Module - GA4 Tracking

#### 2A. GA4 Core Tracking (brighter-ga4-tracking.php - 229 lines)
**Purpose:** Load gtag.js with consent management

**Features:**
- **Consent-aware loading** - Checks SEOPress, Cookie Notice, Complianz, CookieYes
- **Inline core script** - Basic tracking (clicks, scroll, downloads)
- **Region detection** - From URL param `?region=`
- **Auto-fires events:** `click`, `click_phone`, `click_email`, `download`, `scroll`

**WordPress Hooks:**
- `wp_head` (priority 5) - Inject measurement ID
- `wp_head` (priority 99) - Inline core script
- `wp_enqueue_scripts` (priority 99) - Enhanced script

**Options:**
- `brighter_ga4_measurement_id` (string) - GA4 property ID

**JavaScript Output:**
- `window.brighterGA4.measurementId` - Passed to JS
- Inline consent checking
- Basic event tracking

---

#### 2B. GA4 Enhanced Tracking (js/brighter-ga4-enhanced.js - 616 lines)
**Purpose:** Advanced selector-based attribution & lead hierarchy

**Features:**
- **Selector attribution rules** - 40+ CSS selector-to-event mappings
- **Lead tier classification** - Hot/Warm/Cold based on form type
- **CTA context tracking** - Attribution to last 3 CTAs clicked
- **Form tracking** - Start, submit with field count & tier detection
- **Ad tag detection** - Alerts when Google Ads, Meta Pixel, etc. detected
- **Impression tracking** - IntersectionObserver for viewability
- **Content strategy dimensions** - Auto-included in all events
- **Page hierarchy tracking** - ATF, Problem Hook, Authority, FAQ sections

**Event Taxonomy:**
```
Conversions:
- click_meeting, click_main_cta, click_micro_cta
- form_submit, generate_lead, get_lead_magnet, subscribe

Contact:
- click_phone, click_email

Navigation:
- nav_blog, nav_project, nav_product, nav_service, nav_pricing_detail

Trust Signals:
- view_reviews, view_pricing, view_specs, view_case, click_video

Hierarchy:
- view_section (ATF, Problem Hook, Authority, Trust Anchors, FAQ, MidCTA, Final Push)

System Alerts:
- call_bw_seo_gal_we_shld_wrk_2gether (ad tag detection)
```

**Custom Dimensions Sent:**
- `region_id`, `page_title`, `page_path`
- `content_intent`, `content_purpose`, `content_topic`
- `optimization_status`, `pillar_page`, `pillar_type`, `post_type`
- `lead_tier`, `lead_type`, `form_type`, `form_fields`, `form_id`
- `cta_label`, `cta_location`, `cta_type`
- `element_location` (above/below fold)

**Lead Tier Detection Logic:**
```
1. Check for explicit class: .ga-form-hot, .ga-form-warm, .ga-form-cold
2. Check for type class: .ga-quote, .ga-contact, .ga-subscribe
3. Pattern match form ID: /quote/, /contact/, /subscribe/
4. Field count heuristic: 7+ = hot, 3-6 = warm, <3 = cold
```

**Extractable:** ✅ **Medium** - Requires GA4 setup instructions, but self-contained JS

---

#### 2C. GA4 Seeder (bw-ga4-seeder.php - 189 lines)
**Purpose:** Pre-register events in GA4 for low-traffic sites

**Features:**
- One-time event seeding via `?seedEvents=true`
- Fires all event names with `[SEED]` label
- Allows marking conversions from day 1

**WordPress Hooks:**
- `template_redirect` - Admin-only seeding trigger
- `wp_footer` - Output seeding script

**Transients:**
- `brighter_ga4_events_seeded` (30 days)
- `brighter_ga4_seed_date`

**Extractable:** ✅ **Easy** - Useful for any GA4 setup

---

#### 2D. GA4 Seed Admin (bw-ga4-seed-admin.php)
**Purpose:** Admin UI for seeding management

**Extractable:** ✅ **Easy**

---

### MODULE 3: Images Module
**Covered in Module 1B (Image Optimization)**

---

### MODULE 4: Content Strategy Module

#### 4A. Content Strategy Fields (bw-content-strategy.php - 1,125 lines)
**Purpose:** Editorial workflow & content taxonomy

**Features:**
- **Content metadata:**
  - Topic, Intent, Purpose, Pillar Page, Notes
  - Optimization Status (14 states: Idea → Drafted → SEO Basic → Optimised 80+)
  - Index Status (Crawled, Discovered, Indexed, Requested, Issue)

- **Admin columns** - Inline click-to-edit
- **Quick/Bulk edit** support
- **Meta boxes** in post editor
- **GA4 integration** - Injects content strategy into `window.brighterContentStrategy`

**Post Meta:**
- `bw_notes` (string)
- `bw_page_topic` (string) - **DEPRECATED** - Kept for legacy GA4 data
- `bw_intent` (string) - informational, commercial, transactional, etc.
- `bw_purpose` (string) - pillar, service-page, supporting, case-study, etc.
- `bw_pillar_page_id` (int)
- `_brt_opt_status` (string)
- `bw_index_status` (string)

**Intent Options (11):**
- informational, commercial, transactional, navigational, retention, support, trust, functional
- informational_p (Problem), informational_s (Solution), commercial_ds (Decision Support)

**Purpose Options (12):**
- pillar, service-page, product-page, supporting, case-study, conversion-hub, resource-guide, authority-page, location-page, industry-page, landing-page, terms

**Optimization Status (14 states with color coding):**
- Workflow: idea, draft, cont, seo_basic, cro
- Optimized: op60, op70, op80
- Inventory: attention, urgent, ctr, repurpose

**WordPress Hooks:**
- `admin_init` - Register meta, add columns
- `manage_{$pt}_posts_custom_column` - Display values
- `pre_get_posts` - Sortable columns
- `admin_enqueue_scripts` - Inline editing JS
- `wp_ajax_bw_cs_save_field` - AJAX save handler
- `add_meta_boxes` - Editor sidebar
- `save_post` - Meta box save, quick/bulk edit save
- `wp_head` (priority 5) - Inject content strategy for GA4

**Admin Features:**
- **Inline editing** - Click any field to edit without opening post
- **Color-coded badges** - Visual status indicators
- **Pillar page dropdown** - Auto-populated from posts with pillar/service/product purpose
- **AJAX saving** - Instant updates with nonce verification

**Supported Post Types:** All public post types (pages, posts, custom post types) except attachments, nav_menu_item, wp_block, etc.

**Extractable:** ✅ **Medium** - Requires GA4 integration docs, but mostly self-contained

---

#### 4B. ALTC System (Authority-Led Topic Clusters)

**Files:**
- `class-altc-taxonomies.php` (194 lines)
- `class-altc-meta-boxes.php`
- `class-altc-admin-columns.php`
- `class-altc-admin-pages.php`
- `class-altc-ga4-integration.php`
- `class-altc-migration.php`

**Features:**
- **ALTC Strategic Lens taxonomy** - Hierarchical
- **ALTC Topic taxonomy** - Hierarchical with parent lens relationships
- **Content maturity levels** - Entry → Thought Leader → Industry Authority
- **Cannibalization risk detection** - Multiple posts targeting same topic
- **ALTC Overview Dashboard** - Topic breakdown & risk analysis
- **GA4 integration** - ALTC data as custom dimensions
- **Migration tool** - `bw_page_topic` → ALTC Topic taxonomy

**Taxonomies:**
- `altc_strategic_lens` - High-level content themes
- `altc_topic` - Specific topics within lenses

**Post Meta:**
- `bw_primary_altc_id` (int) - Primary lens term ID
- `bw_primary_topic_id` (int) - Primary topic term ID
- `bw_cont_maturity` (string) - Maturity level

**Term Meta:**
- `topic_serves_altc` (array) - Which ALTC lenses this topic serves

**Content Maturity Levels:**
- entry, learner, professional, expert, thought_leader, industry_authority

**Admin Pages:**
- ALTC Overview Dashboard
- Topic Breakdown
- Content Stats (filters by post type)

**WordPress Hooks:**
- `init` - Register taxonomies, post meta, term meta
- Custom admin pages
- GA4 integration hooks

**Extractable:** ✅ **Medium** - Complex taxonomy system, good documentation needed

---

#### 4C. Content Analysis (class-content-analysis.php - 379 lines)
**Purpose:** Automated content statistics & link tracking

**Features:**
- **Link counting** - Internal/external (excludes header/footer/nav)
- **Content stats** - Word count, images, H2s
- **Multi-source scanning** - post_content, ACF fields, Breakdance content
- **Smart exclusion** - Removes semantic tags & specific classes before analysis
- **On-save analysis** - Only recalculates when content modified

**Post Meta Generated:**
- `bw_internal_link_count` (int)
- `bw_external_link_count` (int)
- `bw_internal_links` (array) - URLs
- `bw_external_links` (array) - URLs
- `bw_word_count` (int)
- `bw_image_count` (int)
- `bw_h2_count` (int)
- `_bw_last_analyzed` (datetime) - Prevents duplicate analysis

**Exclusion Logic:**
- **Tags:** `<header>`, `<footer>`, `<nav>`
- **Classes:** `.ga-hrcy-header`, `.ga-hrcy-footer`, `.site-header`, `.site-footer`, `.main-navigation`, `.site-navigation`
- **Social domains:** facebook.com, linkedin.com, twitter.com, instagram.com, youtube.com, etc.
- **Media files:** .jpg, .pdf, .doc, .zip, etc.

**WordPress Hooks:**
- `save_post` (priority 20) - Analyze content

**Extractable:** ✅ **Easy** - Self-contained content analyzer

---

#### 4D. Content Stats Page (class-content-stats-page.php)
**Purpose:** Admin dashboard for content analysis

**Features:**
- Post type filter dropdown
- "Analyze Now" button
- Batch analysis (5 posts at a time)
- Progress tracking

**WordPress Hooks:**
- Integrates with Support Hub tabs

**Extractable:** ✅ **Easy** - UI for content analysis

---

#### 4E. Content Analysis Seeder (class-content-analysis-seeder.php)
**Purpose:** Bulk content analysis via URL trigger

**Features:**
- `?bw_analyze_now=1` - Triggers batch analysis
- Admin-only capability check
- Nonce verification

**WordPress Hooks:**
- `template_redirect` - Trigger analysis

**Extractable:** ✅ **Easy**

---

### MODULE 5: Privacy/Consent Module
**Status:** 🔄 Not yet implemented (mentioned in strategy)

**Planned Features:**
- Cookie consent management
- Auto-generate privacy policy from business info
- Template system: tick Facebook → tick GA4 → 80% done
- Terms & conditions generator

---

### MODULE 6: SEO Module

#### 6A. FAQ System (bw-faq.php - 1,276 lines)
**Purpose:** Comprehensive FAQ custom post type with schema

**Features:**
- **Custom post type** - `faq`
- **Parent page relationship** - Links FAQs to pages/posts/products
- **Custom URLs** - `site.com/parent-slug/faq/faq-slug`
- **Schema markup:**
  - FAQPage schema (single FAQ)
  - Aggregated schema (parent page with multiple FAQs)
  - Speakable schema for voice search
  - Breadcrumb schema
- **Gutenberg block** - FAQ loop display
- **Shortcode** - `[faq_loop]`
- **Auto-linking** - Links FAQ questions in parent content
- **Bulk actions** - Assign parent page
- **REST API** - `/wp-json/custom/v1/faqs/{page_id}`
- **Export endpoint** - `/wp-json/custom/v1/faqs/export` (for AI training)
- **Analytics tracking** - GA4 events for FAQ views
- **Dashboard widget** - FAQ statistics

**Post Meta:**
- `_faq_parent_page` (int) - Related page ID
- `_faq_tldr` (string) - Ultra-concise answer for voice search

**WordPress Hooks:**
- `init` - Register CPT, rewrite rules
- `add_meta_boxes` - Parent page selector, TL;DR field
- `save_post_faq` - Save meta, auto-generate excerpt
- `post_type_link` - Custom permalink
- `template_redirect` - Validate parent slug, redirect if mismatch
- `wp_head` - Schema markup, meta tags
- `wp_footer` - Analytics tracking
- `the_content` - Auto-link FAQ questions
- Yoast/RankMath integration - Sitemap, breadcrumbs
- `posts_orderby` - Boost FAQs in search results

**Rewrite Rules:**
- Pattern: `^([^/]+)/faq/([^/]+)/?$`
- Matches: `parent-slug/faq/faq-slug`

**Admin Features:**
- **Admin column** - Shows parent page
- **Bulk assign** - Select multiple FAQs, assign parent
- **Dashboard widget** - Total FAQs, orphaned FAQs, recent FAQs

**REST API:**
- `GET /wp-json/custom/v1/faqs/{page_id}` - Get FAQs for a page
- `GET /wp-json/custom/v1/faqs/export` - Export all FAQs (admin only)

**Extractable:** ✅ **Easy** - Completely self-contained, excellent standalone plugin candidate

---

#### 6B. SEO (Sitemaps, Meta, Schema)
**Status:** 🔄 Planned for Phase 2-4

**Planned Features:**
- Phase 1: Sitemaps (replace SEOPress sitemap issues)
- Phase 2: Meta management (titles, descriptions, keywords, robots)
- Phase 3: Schema markup (advanced, site-specific)
- Phase 4: Canonical URLs (proper archive handling)

**Current State:**
- SEOPress is active and handling most SEO
- Some schema is custom (FAQ, OG images)
- Canonical issues with SEOPress noted

---

### MODULE 7: WordPress Tweaks Module
**Covered in Module 1A**

Additional tweaks in `brighter-core.php`:
- Disable emojis
- Remove RSD link
- Remove Windows Live Writer link
- Remove WordPress version meta tag
- Heartbeat optimization (60s interval)

**Extractable:** ✅ **Easy** - Already modular

---

### MODULE 8: Business Info (brighter-business-info.php - 603 lines)
**Purpose:** Centralized business data for schema & privacy policy

**Features:**
- **Business information fields** (27 fields):
  - Info: business_name, contact_name, abn, organisation_type, service_description
  - Contact: phone_number, email, address, city, state, postcode, country, lat, long
  - Hours: business_hours
  - Social: Facebook, Twitter, Instagram, YouTube, LinkedIn, Google Review links
  - Service: area_served, provider_mobility, price_tier

- **SEOPress schema mapping** - Allows SEOPress to use these fields instead of duplicating data
- **Shortcodes:**
  - `[business_info setting="phone_number"]` - Output any field
  - `[site_copyright]` - Auto-generated copyright with ABN

- **Caching system:**
  - Runtime cache (static)
  - Object cache (1 hour)
  - Single-query batch loading
  - Auto-clear on save

- **Admin UI** - Settings page with sections

**WordPress Hooks:**
- `admin_init` - Register settings
- `update_option` - Clear cache on save
- `seopress_schemas_mapping_select` - Add fields to SEOPress dropdown

**Options (with prefix `brighter_`):**
- All 27 business fields stored individually

**Security:**
- Field-specific sanitization (email, URL, textarea, text)
- Whitelist validation
- Capability checks
- SQL injection prevention with prepared statements

**Extractable:** ✅ **Easy** - Self-contained business data manager

---

### MODULE 9: Custom Post Types (bw-custposts.php)
**Purpose:** Register custom post types

**Post Types (likely):**
- Projects/Portfolio
- News
- Knowledge Base
- Others?

**WordPress Hooks:**
- `init` - Register post types

**Extractable:** ✅ **Easy** - Can be separated by post type

---

### MODULE 10: REST API System (Phase 1 MVP)

**Files:**
- `class-brighter-api.php` (162 lines) - Main orchestrator
- `class-brighter-api-auth.php` - Authentication
- `class-brighter-api-endpoints.php` - Endpoints
- `class-brighter-api-admin.php` - Admin UI

**Purpose:** Custom GPT integration & external data access

**Features:**
- REST API endpoints for CustomGPT
- API key authentication
- Admin interface for API management
- Cache management for API responses

**WordPress Hooks:**
- `rest_api_init` - Register routes
- `init` (priority 5) - Initialize API
- `save_post`, `delete_post` - Clear cache

**Extractable:** ✅ **Medium** - Needs documentation for setup

---

### MODULE 11: Login & Branding

**Files:**
- `login-styling.php` - Custom login page
- `brighter-admin-branding.php` - Admin interface branding
- `custom-wpemail.php` - Email customization

**Features:**
- Custom login page styling
- Admin bar customization
- Footer credits
- Email templates

**Extractable:** ✅ **Easy** - White-label plugin potential

---

### MODULE 12: Other Utilities

#### Field Tooltips (class-field-tooltips.php)
**Purpose:** Add help tooltips to admin fields

**WordPress Hooks:**
- `admin_enqueue_scripts`
- Custom tooltip rendering

**Extractable:** ✅ **Easy**

---

#### Column Toggles (class-column-toggles.php)
**Purpose:** Show/hide admin columns

**JavaScript:** `js/column-toggles.js`

**Extractable:** ✅ **Easy**

---

#### Frontend (brighter-frontend.php)
**Purpose:** Frontend-specific features

**WordPress Hooks:**
- Frontend-only hooks

**Extractable:** ✅ **Easy**

---

#### Admin Tweaks (bw-admin-tweaks.php)
**Purpose:** Backend UX improvements

**Extractable:** ✅ **Easy**

---

#### Technical Settings (technical-settings.php)
**Purpose:** Advanced technical configuration

**Extractable:** ✅ **Easy**

---

#### Privacy Policy Style (privacy-policy-style.php)
**Purpose:** Style the privacy policy page

**Extractable:** ✅ **Easy**

---

#### Helpers (helpers.php)
**Purpose:** Shared utility functions

**Note:** May be needed across modules

---

#### PHP Limits (php-limits.php)
**Purpose:** Display PHP configuration limits

**Extractable:** ✅ **Easy**

---

## 3. JavaScript/CSS Assets

### JavaScript Files (4)

1. **brighter-ga4-enhanced.js** (616 lines, ~18KB)
   - Advanced GA4 tracking
   - Lead hierarchy system
   - Ad tag detection
   - Form tracking
   - Impression tracking

2. **cache-purge.js**
   - Cache purge UI interactions

3. **column-toggles.js**
   - Admin column visibility toggles

4. **field-tooltips.js**
   - Tooltip functionality

### CSS Files (3)

1. **admin.css**
   - General admin styling

2. **admin-support.css**
   - Support Hub specific styles

3. **frontend.css**
   - Frontend styles (if any)

---

## 4. WordPress Hooks Inventory

### Actions (50+)
```
init                          - Register post types, taxonomies, meta, rewrite rules
admin_init                    - Register settings, add columns
admin_menu                    - Add menu pages
admin_enqueue_scripts         - Load admin scripts/styles
wp_enqueue_scripts            - Load frontend scripts
wp_head                       - GA4 tracking, schema, meta tags, preloads
wp_footer                     - Analytics tracking, seeding script
save_post                     - Content analysis, meta saves
delete_post                   - Cache clearing
rest_api_init                 - Register REST routes
template_redirect             - Path normalization, FAQ validation, seeding trigger
add_meta_boxes                - Custom meta boxes
manage_{$pt}_posts_custom_column - Admin column content
wp_ajax_*                     - AJAX handlers
update_option                 - Cache clearing
wp_dashboard_setup            - Dashboard widgets
heartbeat_settings            - Heartbeat optimization
```

### Filters (30+)
```
wp_handle_upload              - Image resizing
intermediate_image_sizes      - Image size filtering
image_size_names_choose       - Image size dropdown
post_type_link                - Custom permalinks (FAQ)
query_vars                    - Custom query vars (parent_slug)
manage_edit-{$pt}_columns     - Admin column headers
manage_edit-{$pt}_sortable_columns - Sortable columns
pre_get_posts                 - Query modifications
the_content                   - Auto-linking, fetchpriority
wp_get_attachment_image_attributes - Image attributes
style_loader_tag              - Remove Google Fonts
comments_open                 - Disable on attachments
seopress_schemas_mapping_select - Business info integration
seopress_social_og_image      - OG image override
wpseo_breadcrumb_links        - Breadcrumb integration
posts_orderby                 - Search result ordering
template_include              - Template hierarchy
bulk_actions-edit-{$pt}       - Bulk actions
handle_bulk_actions-edit-{$pt} - Bulk action handlers
brighter_support_tabs         - Custom tabs (extensible)
brighter_support_tab_content  - Custom tab content
```

---

## 5. Dependencies & Integrations

### WordPress Core
- ✅ Custom post types
- ✅ Taxonomies
- ✅ Post meta
- ✅ Term meta
- ✅ Settings API
- ✅ REST API
- ✅ Transients API
- ✅ Object Cache API

### Third-Party Plugin Integrations

**SEOPress** (detected)
- Schema mapping (`seopress_schemas_mapping_select`)
- OG image override (`seopress_social_og_image`, `seopress_social_og_default_image`)
- Consent cookie detection
- Breadcrumb integration (`wpseo_breadcrumb_links`)

**LiteSpeed Cache** (optional)
- Lazy-load CSS detection
- Cache exclusion instructions in comments

**ACF (Advanced Custom Fields)** (optional)
- Content analysis scans ACF fields
- `get_fields()` check

**Breakdance** (optional)
- Content analysis scans Breakdance content
- `class_exists('Breakdance\PluginBootstrap')` check

**Consent Management Plugins** (multi-support)
- SEOPress consent cookie
- Cookie Notice
- Complianz
- CookieYes

**Google Analytics 4** (external)
- Measurement ID configuration
- Custom dimensions setup required

**CustomGPT** (external API)
- REST API endpoints for data access

---

## 6. Hardcoded Elements & Site-Specific Code

### Hardcoded Values

**Email addresses:**
- `support@brighterwebsites.com.au` (brighter-support.php:348)
- `team@brighterwebsites.com.au` (brighter-support.php:128)

**URLs:**
- `https://brighterwebsites.com.au/kb/` (brighter-support.php:349)
- Knowledge base links

**Business name:**
- "Brighter Websites" in various places

**Logo:**
- `brighter-core/assets/brighter-logo.png`

**GA4 Region:**
- Default: `zone4-remote` (can be overridden via URL param)

**Currency:**
- Default: `AUD` (Australian Dollars)

### Admin Capability Checks
- `manage_options` - For settings pages
- `edit_posts` - For content editing
- `read` - For support page access

### Admin Email Checks
- `team@brighterwebsites.com.au`
- `support@brighterwebsites.com.au`

**Action Required:** Replace with option or constant for white-labeling

---

## 7. Performance Concerns

### Query Load
- ✅ Object cache implementation for business info
- ⚠️ Content analysis runs on every post save (mitigated: only if modified)
- ⚠️ Multiple `get_post_meta()` calls in admin columns (disabled some for performance)
- ✅ Batch option loading via prepared statements
- ✅ Transient caching for FAQ stats, module checks

### Always-Loading Code
- ⚠️ All modules load on every request (no conditional loading)
- ⚠️ GA4 tracking always loads (consent-gated, but script present)
- ✅ Admin-only modules check `is_admin()`
- ✅ Frontend CSS/JS only loads when needed

### Database Queries
- ⚠️ Admin columns can trigger N+1 queries
- ✅ Business info uses single query for all fields
- ✅ Content analysis checks last modified before re-running

### Recommendations
1. Implement module on/off toggles
2. Lazy-load admin scripts
3. Prime meta cache for admin columns
4. Consider disabling content analysis auto-run (manual trigger only)

---

## 8. Security Audit

### ✅ Good Practices Found
- **Nonce verification** on all forms
- **Capability checks** before sensitive operations
- **Prepared statements** for SQL queries
- **Input sanitization** (sanitize_text_field, esc_url_raw, sanitize_email)
- **Output escaping** (esc_html, esc_attr, esc_url, wp_kses_post)
- **AJAX nonce checks** (`check_ajax_referer`)
- **Whitelist validation** for meta fields
- **Path traversal prevention** in module loader
- **ABSPATH checks** in all files

### ⚠️ Potential Issues
- Some user input in GA4 tracking (mitigated: esc_js())
- Transient keys from user input (mitigated: sanitize_key())
- Output buffering for path normalization (could affect performance)

### 🔒 Recommendations
- ✅ Security practices are solid
- Consider CSP headers for GA4 script
- Rate limiting for seeding endpoints

---

## 9. Testing Recommendations

### Critical Tests Before Refactor

**Module Independence:**
- [ ] Disable each module individually
- [ ] Check for fatal errors
- [ ] Verify dependencies

**Data Integrity:**
- [ ] Export all post meta (WP All Import)
- [ ] Document all option names
- [ ] Test migration scripts

**Performance:**
- [ ] Baseline query count (Query Monitor)
- [ ] Measure page load time
- [ ] Test with object cache on/off

**Features:**
- [ ] Test each GA4 event fires
- [ ] Test form submissions (all tiers)
- [ ] Test FAQ schema validates
- [ ] Test content analysis accuracy
- [ ] Test ALTC taxonomy relationships
- [ ] Test image resize on upload
- [ ] Test preload tags output

---

## 10. Migration Priority Order

Based on **ease of extraction** and **value**:

### Phase 1: Foundation (Week 1-2)
1. ✅ **Create new modular structure** (`site-essentials/`)
2. ✅ **Build module loader** with on/off toggles
3. ✅ **Build unified settings UI**
4. ✅ **Migrate WordPress Tweaks** (proof of concept)

### Phase 2: High-Value Standalone Modules (Week 3-4)
5. **FAQ System** - Self-contained, high reuse potential
6. **Image Optimization** - Standalone image manager
7. **Business Info** - Reusable across sites

### Phase 3: Analytics & Tracking (Week 5-6)
8. **GA4 Tracking** (Core + Enhanced)
9. **GA4 Seeder**
10. **Content Analysis**

### Phase 4: Content Strategy (Week 7-8)
11. **Content Strategy Fields**
12. **ALTC System**
13. **Content Stats Dashboard**

### Phase 5: Admin & UX (Week 9-10)
14. **Support Hub**
15. **Login & Branding**
16. **Admin Tweaks**
17. **Field Tooltips & Column Toggles**

### Phase 6: API & Advanced (Week 11-12)
18. **REST API System**
19. **Custom Post Types**

---

## 11. Known Issues to Fix

### Critical
- ❌ **Permission error:** `bw_analyze_now=1` - "Sorry, you are not allowed to access this page"
- ⚠️ **Background processing unclear:** Is it 5 per post or 5 total sitewide?

### Medium
- ⚠️ FAQ rewrite rules breaking permalinks (mentioned in commit history)
- ⚠️ SEOPress OG image not using og-image size (workaround: direct injection)
- ⚠️ Query count jumping (needs profiling)

### Low
- Some file naming inconsistencies (`brighter-buinessinfo.php` typo in old paths)
- Deprecated `bw_page_topic` field still in code (kept for GA4 legacy data)
- Admin column stats disabled for performance (could re-enable with caching)

---

## 12. Code Quality Metrics

### File Size Distribution
```
Small (<100 lines):     8 files   - Simple utilities
Medium (100-500 lines): 18 files  - Standard modules
Large (500-1000 lines): 7 files   - Complex features
Very Large (1000+ lines): 3 files - FAQ, Content Strategy, GA4 Enhanced
```

### Code Maturity
- ✅ **Well-documented:** Header comments with version, changelog, purpose
- ✅ **Security-conscious:** Nonces, capability checks, escaping
- ⚠️ **Some technical debt:** Deprecated fields, commented-out code
- ✅ **Modern WordPress:** Uses Settings API, REST API, post meta registration

### Documentation
- ✅ Inline comments explaining complex logic
- ✅ Header blocks with file purpose
- ⚠️ No user-facing documentation (README)
- ⚠️ No developer documentation (API docs)

---

## 13. Refactor Recommendations

### Immediate Actions
1. **Fix permission error** for `bw_analyze_now`
2. **Clarify background processing** limits
3. **Create module toggles** in settings
4. **Document all options** and meta fields

### Architectural Improvements
1. **Namespace all code** - `SiteEssentials\Modules\{ModuleName}`
2. **Autoloader** - PSR-4 autoloading
3. **Dependency injection** - Pass dependencies instead of global functions
4. **Interface-based** - Define module interfaces
5. **Settings manager** - Centralized option management
6. **Cache layer** - Unified caching strategy

### Module Structure Template
```
site-essentials/
├── modules/
│   └── analytics/
│       ├── Analytics_Module.php     # Main class implementing Module_Interface
│       ├── GA4_Tracker.php          # Core tracking
│       ├── Lead_Classifier.php      # Lead hierarchy
│       ├── Event_Seeder.php         # Seeding system
│       ├── assets/
│       │   ├── ga4-enhanced.js
│       │   └── ga4-seeder.js
│       ├── views/
│       │   └── admin-settings.php
│       └── README.md                # Module documentation
```

### Data Migration Strategy
1. **Export current data** (WP All Import or custom script)
2. **Test on fresh site**
3. **Clone existing site** for migration test
4. **Gradual rollout** - Enable one module at a time
5. **Rollback plan** - Keep old files until verified

---

## 14. Summary & Next Steps

### What You Have
- 🎯 **A solid foundation** - Well-structured, secure code
- 📦 **38 PHP files** across 7 major functional areas
- 🔥 **High-value features** - GA4 tracking, content strategy, FAQ system
- 💎 **Extraction-ready** - Most modules already self-contained
- 🚀 **Growth potential** - Clear path to premium product

### What Needs Work
- 🔧 **Modularity** - No on/off toggles, everything always loads
- 📊 **Performance** - Some query optimization needed
- 📝 **Documentation** - User and developer docs missing
- 🏷️ **Branding** - Hardcoded "Brighter" references
- 🧪 **Testing** - No automated tests

### Recommended Path Forward
1. ✅ **Start with Option 3** (Hybrid Foundation Approach)
2. ✅ **Fix known bugs** as part of refactor (permission error, etc.)
3. ✅ **Build module toggles** first (biggest UX win)
4. ✅ **Migrate WordPress Tweaks** as proof-of-concept
5. ✅ **Extract FAQ system** next (highest standalone value)
6. 📋 **Continue with migration plan** from Section 10

### Success Criteria
- ✅ All existing features work
- ✅ Disabled modules don't load code
- ✅ Settings are exportable/importable
- ✅ Each module can be extracted as standalone plugin
- ✅ Documentation exists for setup
- ✅ Performance is same or better

---

## Appendix A: Complete File List

```
brighter-core-loader.php (3 lines)
brighter-ga4-tracking.php (229 lines)

brighter-core/
├── brighter-core.php (484 lines)
├── includes/
│   ├── api/
│   │   ├── class-brighter-api.php (162 lines)
│   │   ├── class-brighter-api-auth.php
│   │   ├── class-brighter-api-endpoints.php
│   │   └── class-brighter-api-admin.php
│   ├── brighter-business-info.php (603 lines)
│   ├── brighter-support.php (399 lines)
│   ├── brighter-frontend.php
│   ├── brighter-admin-branding.php
│   ├── brighter-support-image-settings.php
│   ├── brighter-tweaks.php (617 lines)
│   ├── brighter-settings.php
│   ├── bw-admin-tweaks.php
│   ├── bw-content-strategy.php (1,125 lines)
│   ├── bw-custposts.php
│   ├── bw-faq.php (1,276 lines)
│   ├── bw-ga4-seeder.php (189 lines)
│   ├── bw-ga4-seed-admin.php
│   ├── bw-support-cache-dashbrd.php
│   ├── class-altc-admin-columns.php
│   ├── class-altc-admin-pages.php
│   ├── class-altc-ga4-integration.php
│   ├── class-altc-meta-boxes.php
│   ├── class-altc-migration.php
│   ├── class-altc-taxonomies.php (194 lines)
│   ├── class-column-toggles.php
│   ├── class-content-analysis.php (379 lines)
│   ├── class-content-analysis-seeder.php
│   ├── class-content-stats-page.php
│   ├── class-field-tooltips.php
│   ├── custom-wpemail.php
│   ├── helpers.php
│   ├── image-optimisation.php (290 lines)
│   ├── login-styling.php
│   ├── php-limits.php
│   ├── privacy-policy-style.php
│   └── technical-settings.php
├── css/
│   ├── admin.css
│   ├── admin-support.css
│   └── frontend.css
├── js/
│   ├── brighter-ga4-enhanced.js (616 lines)
│   ├── cache-purge.js
│   ├── column-toggles.js
│   └── field-tooltips.js
└── assets/
    └── brighter-logo.png
```

**Total PHP Lines:** ~7,500+ lines (estimated)
**Total JS Lines:** ~1,300+ lines
**Total Files:** 40+ PHP, 4 JS, 3 CSS

---

**End of Audit**

*This audit provides a comprehensive snapshot of the current codebase. Use this as a reference during the refactor to ensure nothing is missed.*
