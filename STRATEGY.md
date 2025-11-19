# Site Essentials - Long-Term Strategy

**Last Updated:** 2025-11-17
**Product Name:** Site Essentials (formerly "Brighter Core")
**Vision:** Modular MU Plugin → Premium Product

---

## Core Philosophy

**MU Plugin Base** - Can't be disabled by clients
**Modular Architecture** - Each feature can be toggled on/off for performance
**Premium Tiers** - Basic → Pro → Agency features
**Extractable Modules** - Module groups can become standalone plugins

---

## Product Tiers

### Tier 1: Basic (Free)
**Target:** All client sites by default

**Included Modules:**
- Performance basics (WordPress tweaks, image optimization)
- Basic analytics (GA4 tracking)
- Basic SEO (sitemaps once implemented)

**Philosophy:** Foundation features that improve every site

---

### Tier 2: Pro (Paid)
**Target:** Clients with active content strategy

**Included Modules:**
- All Basic features
- Full analytics (enhanced tracking, lead hierarchy)
- Content strategy (ALTC, optimization tracking)
- Advanced SEO (meta management, schema)

**Philosophy:** Tools for businesses that create content regularly

---

### Tier 3: Agency (Paid - Premium)
**Target:** White-label for agencies, or your managed clients

**Included Modules:**
- All Pro features
- Support portal (branded for agency)
- Site monitoring
- White-label options

**Philosophy:** Client management tools for agencies

**Special Features:**
- Brighter Support tab (with your branding for clients)
- Links to tools, manuals, ranking reports
- Can be toggled off for white-label

---

## Module Categories

### Module 0: Brighter Websites Support Page
**Current State:** ✅ Active
**Tier:** Agency only (or toggleable)

**Purpose:**
- Customer-facing support dashboard
- Reminders to back up
- Links to manuals, tools, ranking reports
- Help resources

**Future:**
- Toggle: Display for agency clients or hide for white-label
- Customizable branding (logo, company name, support email)
- Client-specific manual links

---

### Module 1: Performance Module
**Current State:** ✅ Active
**Tier:** Basic (always on, sub-features toggleable)

**Sub-Features:**
1. **WordPress Tweaks**
   - Disable emojis, jQuery migrate, RSD, etc.
   - Heartbeat optimization
   - Query string removal
   - Toggle: Each tweak individually

2. **Asset Preloading**
   - Per-page preloads (images, fonts, CSS, JS)
   - Auto-preload featured images
   - LCP optimization (fetchpriority)
   - Toggle: On/Off per site

3. **Image Optimization**
   - Max upload size enforcement
   - JPEG quality control
   - Image size management (enable/disable sizes)
   - WebP conversion (future, or integrate with LiteSpeed/ShortPixel)
   - Image meta (geo tags, copyright, creator)
   - Toggle: On/Off based on need

4. **LiteSpeed/CyberPanel Integration**
   - Object cache compatibility
   - Font preloading
   - CSS "font agreement" generation → copy into Breakdance global settings
   - Plugin feature optimization cache
   - Toggle: Auto-detect LiteSpeed, enable optimizations

**Future Ideas:**
- Media upload max size selector (field in upload screen: "Upload at 200x200")
- Default lazy load triggers via analytics selectors (AFT not lazy, BTF lazy)

---

### Module 2: Images Module
**Current State:** ✅ Active (part of Performance)
**Tier:** Basic

**Features (Current):**
- Image resize on upload
- Image size management
- OG image size (1200x630)
- Image meta stripping
- Lazy-load CSS for LiteSpeed

**Features (Future):**
- **Geo tags, copyright, creator info** on upload
  - Default: Business location from Business Info module
  - Ability to change per upload (lat/long or location name)
- **Strip all meta, add back minimal** for smallest file size
- **WebP conversion** (integrate with LiteSpeed or ShortPixel, don't reinvent)
- **Max upload size field** in media uploader (on-the-fly resize)

**Toggle:** On/Off per site based on need

---

### Module 3: Analytics Module
**Current State:** ✅ Active
**Tier:** Basic tracking, Pro for enhanced

**Features (Current):**
- GA4 tracking with consent management
- Enhanced tracking with selectors (40+ event rules)
- Lead scoring (Hot/Warm/Cold)
- Custom event attribution
- Content strategy dimensions
- Ad tag detection & alerting

**Features (Future):**
- **Custom GPT integration** (keep off-site for performance)
- **AI content analysis** (via API, not on-site)
- **Engagement scoring** (time on page, scroll depth, clicks)

**Toggle:**
- Basic analytics: Always on
- Enhanced features: Optional (Pro tier)
- Content strategy dimensions: Optional (requires Content Strategy module)

---

### Module 4: Content Strategy Module
**Current State:** ✅ Active
**Tier:** Pro

**Features (Current):**
- ALTC (Authority-Led Topic Clusters) management
- Custom fields: `bw_page_topic`, `bw_intent`, `bw_purpose`, `bw_pillar_page_id`
- Content optimization tracking (14 statuses)
- Content maturity levels
- Cannibalization risk detection
- Admin columns with inline editing
- ALTC Overview Dashboard

**Features (Future):**
- **AI content analysis** (CustomGPT integration via API)
- **Content gap detection** (topics covered vs. not covered)
- **Competitor content tracking** (via API)
- **Content calendar** (editorial planning)

**Toggle:** On/Off - Can disable when not actively creating content (performance)

**Performance Note:** Analysis should run once, then turn itself off. Maybe reminder to run if:
- Analysis hasn't run in X days
- Page last modified > last analysis

---

### Module 5: Privacy/Consent Module
**Current State:** 🔄 Future
**Tier:** Pro

**Features (Planned):**
- **Cookie consent management**
  - Pre-built templates for common tools
  - Auto-generate privacy policy from business info
  - Template system: Tick Facebook → Tick GA4 → Tick HubSpot → 80% done
- **Terms & conditions generator**
  - Fill in business info → Generate T&C
- **GDPR compliance tools**
  - Data export, data deletion

**Toggle:** On/Off, integrates with Analytics module

---

### Module 6: SEO Module (Multi-Phase)
**Current State:** 🔄 Partial (FAQ system active)
**Tier:** Basic → Pro features

**Phase 1: Sitemaps (URGENT)**
**Status:** Not started
**Tier:** Basic
**Purpose:** Fix SEOPress sitemap issues on a few sites

**Features:**
- XML sitemap generation
- Image sitemap
- Video sitemap (if applicable)
- Sitemap index
- Submit to Google Search Console via API
- Toggle: On/Off

---

**Phase 2: Meta Management**
**Status:** Planned
**Tier:** Pro

**Features:**
- Title tags
- Meta descriptions
- Meta keywords (optional, mostly deprecated)
- Robots meta (index/noindex, follow/nofollow)
- Canonical URLs
- OG tags (already partially implemented)
- Twitter cards
- Toggle: On/Off

---

**Phase 3: Schema Markup (Advanced)**
**Status:** Partial (FAQ schema active)
**Tier:** Pro

**Features:**
- Automatic schema from Business Info
- FAQ schema (already implemented)
- Article schema
- LocalBusiness schema
- Product schema
- Service schema
- Breadcrumb schema
- Site-specific custom schemas
- Toggle: On/Off per schema type

---

**Phase 4: Canonical URLs**
**Status:** Planned
**Tier:** Basic

**Features:**
- Proper archive handling
- Pagination canonical
- Fix SEOPress canonical issues
- Self-referential canonical on singles
- Cross-domain canonical support
- Toggle: On/Off

---

**Goal:** Eventually replace SEOPress entirely

**Why?**
- SEOPress doing "junky" things
- Not advanced enough for your needs
- No plans for LLM.txt support
- Canonical archive links not best practice

**Timeline:** Phase 1 (sitemaps) ASAP, then gradual feature parity

---

### Module 7: WordPress Tweaks Module
**Current State:** ✅ Active
**Tier:** Basic

**Features (Current):**
- Scattered optimizations across files
- Some tweaks always on
- No individual control

**Features (Refactored):**
**Settings to enable/disable individual tweaks:**
- Disable emojis
- Remove jQuery migrate
- Disable REST API for non-logged users
- Remove query strings from static resources
- Disable XML-RPC
- Remove RSD link
- Remove Windows Live Writer link
- Remove WordPress version meta tag
- Heartbeat optimization (15s → 60s)
- Disable RSS feeds (optional)
- Disable trackbacks/pingbacks
- Etc.

**Toggle:** Each tweak individually toggleable

**Admin UI:** Checklist with descriptions

---

### Module 8: Site Monitoring Module (NEW - Urgent Later)
**Current State:** 🔄 Not started
**Tier:** Agency

**Purpose:** External + MU plugin hybrid monitoring

**Features:**
- **External monitoring** (Make.com or similar)
  - Check 200 responses on key pages
  - Business hours: 8am-8pm AEST
  - Escalation logic (email, SMS, Slack)
- **Internal monitoring** (MU plugin component)
  - Server health checks
  - Database health
  - Disk space
  - PHP errors
  - Plugin conflicts
- **Make.com integration**
  - Webhook endpoint
  - Alert routing
- **Dashboard widget**
  - Uptime status
  - Last check time
  - Response times

**Toggle:** On/Off per site

**Priority:** Needed soon, but not urgent right now

---

### Module 9: Business Info
**Current State:** ✅ Active
**Tier:** Basic

**Features (Current):**
- 27 business information fields
- SEOPress schema integration
- Shortcodes for output
- Caching system

**Features (Future):**
- **Multi-location support** (for businesses with multiple locations)
- **Custom fields** (extensible field system)
- **Import/export** business info

**Toggle:** Always on (foundation data)

---

### Module 10: FAQ System
**Current State:** ✅ Active
**Tier:** Pro

**Features (Current):**
- Custom post type
- Parent page relationships
- Custom URLs
- Schema markup (FAQ, speakable, breadcrumb)
- Gutenberg block
- Shortcode
- Auto-linking
- REST API
- Export for AI training
- Analytics tracking

**Features (Future):**
- **AI-generated FAQ suggestions** (from CustomGPT)
- **FAQ search** (dedicated search widget)
- **FAQ categories/tags**

**Toggle:** On/Off

**Standalone Potential:** ⭐⭐⭐⭐⭐ (Highest - completely self-contained)

---

### Module 11: Custom Post Types
**Current State:** ✅ Partial
**Tier:** Pro (depends on post type)

**Post Types (Current/Planned):**
- Portfolio/Projects
- News
- Knowledge Base
- Testimonials
- Team Members
- Services
- Products

**Toggle:** Enable/disable per post type

**Future:** CPT builder UI (create custom post types from admin)

---

### Module 12: API System
**Current State:** ✅ Active (Phase 1 MVP)
**Tier:** Pro

**Features (Current):**
- REST API endpoints
- API key authentication
- CustomGPT integration
- Cache management

**Features (Future):**
- **Webhook support**
- **OAuth 2.0** authentication
- **Rate limiting**
- **API usage analytics**
- **Third-party integrations** (Zapier, Make.com, etc.)

**Toggle:** On/Off

---

## Technical Requirements

### 1. Performance First
**Disabled modules don't load their code at all**

**Implementation:**
```php
// In module loader
if (!$settings->is_enabled('analytics')) {
    return; // Don't even require the file
}
```

**Analysis Features:**
- Run once, then turn off
- Reminder if not run in X days
- Reminder if page modified > last analysis

---

### 2. Extractable Modules
**Each main module folder = potential standalone plugin**

**Structure:**
```
site-essentials/
└── modules/
    └── faq-system/
        ├── FAQ_Module.php
        ├── class-faq-post-type.php
        ├── class-faq-schema.php
        ├── assets/
        └── README.md (standalone docs)
```

**Extraction Process:**
1. Copy module folder
2. Add plugin header to main file
3. Update namespace if needed
4. Distribute as standalone plugin

---

### 3. Separation of Concerns
**Interface code separate from execution code**

**Example:**
```
analytics/
├── Analytics_Module.php      # Admin UI, settings
├── GA4_Tracker.php           # Core tracking logic
├── Lead_Classifier.php       # Business logic
└── Event_Repository.php      # Data access
```

---

### 4. Version Control
**All files have version numbers in headers**

```php
/**
 * Module: Analytics
 * Version: 2.1.0
 * Requires: Site Essentials Core 1.0.0
 */
```

---

### 5. Settings UI
**Single admin page: Settings → Site Essentials**

**Tabs:**
- General (module toggles)
- Performance
- Analytics
- Content Strategy
- SEO
- Business Info
- Advanced

**Per-Module Settings:**
- Each module has its own settings section
- Settings saved per-module (e.g., `site_essentials_analytics`)
- Settings import/export

---

## Premium Tier Features

### Basic (Free)
**Included by Default:**
- Performance tweaks
- Image optimization
- Basic analytics (GA4)
- Basic SEO (sitemaps)
- Business info
- WordPress tweaks

**Value:** Foundation features for every site

---

### Pro (Paid)
**Additional Features:**
- Full analytics (enhanced tracking, lead hierarchy)
- Content strategy (ALTC, optimization tracking)
- Advanced SEO (meta, schema, canonical)
- FAQ system
- Content analysis
- Custom post types
- API access

**Value:** For content-driven businesses

**Pricing:** $XX/month per site or $XXX/year

---

### Agency (Paid - Premium)
**Additional Features:**
- Support portal (branded)
- Site monitoring
- White-label options
- Priority support
- Multi-site license
- Custom development

**Value:** For agencies managing multiple clients

**Pricing:** $XXX/month or $X,XXX/year

---

## Settings Import/Export

**Purpose:** Migrate settings between sites easily

**Export Format:** JSON

```json
{
  "version": "1.0.0",
  "site_essentials_core": {
    "enabled_modules": ["performance", "analytics", "seo"],
    "version": "1.2.3"
  },
  "site_essentials_analytics": {
    "ga4_measurement_id": "G-XXXXXXXXXX",
    "enhanced_tracking": true,
    "lead_scoring": true
  },
  "site_essentials_business_info": {
    "business_name": "Example Business",
    "phone_number": "555-1234",
    ...
  }
}
```

**Import Logic:**
- Validate JSON structure
- Check version compatibility
- Allow selective import (checkboxes for each module)
- Preview before import
- Backup existing settings before import

---

## Current State vs. Future State

### Current Issues

1. **Scattered Features**
   - WordPress tweaks in multiple files
   - No central organization
   - Hard to find things

2. **No Module Testing Checklist**
   - If files aren't touched, still have to test everything
   - No way to know what's affected by changes

3. **No Central Settings Management**
   - Settings scattered across files
   - No unified UI

4. **Features Can't Be Disabled**
   - Everything always loads
   - Performance hit when not needed

5. **SEOPress Limitations**
   - Doing "junky" things
   - Not advanced enough
   - No LLM.txt support
   - Canonical issues

6. **Admin Style Basic**
   - CSS needs refinement
   - Hard for you to tweak easily

7. **Limited Help**
   - Few tooltips
   - No "How to use" guides

8. **Performance Concerns**
   - Queries jumping around
   - Content analysis if always running (performance hit)

---

### Future State (After Refactor)

1. **Organized & Modular**
   - Each module in its own folder
   - Clear separation of concerns
   - Easy to find and modify

2. **Module Testing**
   - Checklist per module
   - If module not touched, skip testing
   - Automated tests for critical paths

3. **Unified Settings**
   - Single admin page
   - All module toggles in one place
   - Settings import/export

4. **Performance Optimized**
   - Disabled modules don't load
   - Content analysis runs once, then stops
   - Object cache for everything
   - Query optimization

5. **SEO Independence**
   - Own sitemap system
   - Own meta management
   - Own schema
   - Can eventually remove SEOPress

6. **Beautiful Admin**
   - Refined CSS
   - Easy for you to customize
   - Branded per tier

7. **Helpful UI**
   - Tooltips everywhere
   - Help docs linked
   - Inline guides

8. **Stable Performance**
   - Query count consistent
   - Performance monitoring built-in
   - Optimization recommendations

---

## Migration Strategy

### Testing & Rollout

**Fresh Site Test:**
- Install on brand new site
- Verify all features work
- Test module toggles

**Clone Test:**
- Clone existing site
- Test migration/coexistence
- Verify data integrity

**Staging Test:**
- Test on staging for each client site
- Gradual rollout per site
- Monitor for issues

**Data Migration:**
- WP All Import/Export (or custom tool)
- One-click migrator (built into plugin)
- Backup before migration
- Rollback capability

---

## Success Metrics

**Performance:**
- Query count same or lower
- Page load time same or faster
- Admin dashboard responsive

**Functionality:**
- All existing features work
- No data loss
- Settings preserved

**User Experience:**
- Easier to configure
- Settings are clear
- Help is available

**Development:**
- Code is maintainable
- Modules are testable
- Documentation exists

---

## Roadmap

### Phase 1: Foundation (Weeks 1-2)
- ✅ Audit complete
- ✅ Strategy documented
- Build new modular core
- Module loader with toggles
- Unified settings UI
- Migrate WordPress Tweaks (proof of concept)

### Phase 2: SEO Module Phase 1 (Weeks 3-4)
- **URGENT:** Sitemap generation
- Fix SEOPress sitemap issues
- Test on problem sites
- Document setup

### Phase 3: High-Value Extracts (Weeks 5-8)
- FAQ System
- Image Optimization
- Business Info
- Analytics (GA4)

### Phase 4: Content Strategy (Weeks 9-12)
- Content Strategy fields
- ALTC system
- Content analysis

### Phase 5: Polish & Premium (Weeks 13-16)
- Support portal
- White-label options
- Settings import/export
- Documentation
- Premium tier setup

### Phase 6: SEO Module Completion (Weeks 17-20)
- Meta management
- Schema markup
- Canonical URLs
- SEOPress replacement complete

---

## Confirmed Decisions

### 1. Support Portal
**Decision:** Keep it (yes)
- Toggle: Show for agency clients, hide for white-label
- Tier: Agency

### 2. FAQ System Tier
**Decision:** Pro tier
- Confirmed as content tool
- Not included in Basic tier

### 3. Performance Monitoring Approach
**Tools:**
- Query Monitor (installed and actively used)
- Chrome DevTools
- PageSpeed Insights

**Approach:**
- Skip building custom performance dashboard
- Prefer performance checklist with best practices and recommendations
- Consider building CustomGPT tool off-site for performance analysis
- Keep monitoring simple and actionable

### 4. Object Cache Standardization
**Decision:** Yes! Standardize Cache_Helper across all modules

**Implementation:**
- Single Cache_Helper class all modules use
- Consistent "remember" pattern for all caching
- Cache invalidation hooks standardized across modules (clear cache when data changes)
- Per-module cache toggles: Start with global on/off, add per-module toggles later if needed for debugging

**Cache Invalidation Example:**
```php
// When business info changes, clear cache immediately
add_action('update_option', function($option_name) {
    if (strpos($option_name, 'site_essentials_business_info') === 0) {
        Cache_Helper::flush('business_info');
    }
});
```

### 5. Module Dependencies
**Approach:** Handle as we encounter them during refactor

**Guidelines:**
- Avoid duplication where possible
- Related modules may be packaged together but individually toggleable
- Example: Analytics, Content Strategy, Privacy, and Cookie modules are related but can toggle on/off independently
- For standalone extraction: Modules should be 80% standalone to minimize additional work for full extraction
- Check dependencies at load time, show clear error if missing required module

**Example Dependency Chain:**
```
Analytics Module
├── Can use Business Info (optional, for region data)
├── Can use Content Strategy (optional, for dimensions)
└── Works standalone with reduced features
```

---

## Final Notes

**This is a living document.** Update as strategy evolves.

**Key Principles:**
1. Performance first - disabled = not loaded
2. Modular - each feature independent
3. Extractable - modules become products (80% standalone)
4. User-friendly - settings are clear
5. Well-documented - for users and developers
6. Admin design - Material Design, professional, accessibility-friendly colors

**Success = Client sites run better + You have a product to sell**

---

**End of Strategy Document**
