# Content & Analytics Automation - Module Overview

**For:** Claude AI Agent Integration
**Last Updated:** 2025-12-01
**Status:** REST API ✅ Production | MCP Server 🚧 Planned

---

## 🎯 What We're Building

A **WordPress MU Plugin** that enables **AI-powered content strategy automation** through:
- Content metadata tracking (ALTC framework)
- Analytics integration (GA4)
- REST API for current needs
- MCP Server for future AI agent workflows

---

## 📦 Current State (Production)

### 1. **REST API Module** ✅
**Status:** Live on BW + GS sites
**Purpose:** Expose WordPress content + metadata to AI tools

**Endpoints:**
- `/wp-json/brighter-core/v1/posts` - Blog posts
- `/wp-json/brighter-core/v1/project` - Projects (GS)
- `/wp-json/brighter-core/v1/page` - Pages (GS)
- `/wp-json/brighter-core/v1/faq` - FAQs

**Authentication:** Custom header `X-Brighter-Token`

**Returns:** Post content + custom fields (ALTC, optimization status, metrics)

**Current Use:** Custom GPT can read site content for analysis

---

### 2. **ALTC Module** (Authority-Led Topic Clusters) ✅
**Status:** Active in WordPress
**Purpose:** Content strategy taxonomy

**What it tracks:**
- **Strategic Lens:** High-level content themes (e.g., "SEO Basics", "Technical SEO")
- **Topics:** Specific subjects within lenses
- **Content Maturity:** Entry → Learner → Expert → Thought Leader → Industry Authority
- **Primary assignments:** Each post gets 1 primary lens + 1 primary topic

**Stored as:**
- Custom taxonomies: `altc_strategic_lens`, `altc_topic`
- Post meta: `bw_primary_altc_id`, `bw_primary_topic_id`, `bw_cont_maturity`

**Use case:** Organize content into strategic clusters, detect cannibalization risks, identify gaps

---

### 3. **Content Strategy Module** ✅
**Status:** Active in WordPress
**Purpose:** Editorial workflow tracking

**What it tracks:**
- **Intent:** informational, commercial, transactional, etc. (11 types)
- **Purpose:** pillar, service-page, supporting, case-study, etc. (12 types)
- **Optimization Status:** idea → draft → seo_basic → op60 → op70 → op80 (14 states)
- **Index Status:** crawled, discovered, indexed (5 states)
- **Pillar Page:** Parent-child content relationships

**Stored as:**
- Post meta: `bw_intent`, `bw_purpose`, `_brt_opt_status`, `bw_index_status`, `bw_pillar_page_id`

**Admin UI:**
- Inline editing in post list
- Color-coded status badges
- Sortable columns

**Use case:** Track content through production pipeline, identify what needs work

---

### 4. **Analytics Module** (GA4 Integration) ✅
**Status:** Active, tracking live
**Purpose:** Enhanced GA4 event tracking

**Features:**
- **40+ selector-based events** (clicks, form submissions, video plays)
- **Lead hierarchy:** Hot/Warm/Cold based on form type
- **Content strategy dimensions:** Injects ALTC + optimization data into GA4
- **Auto-attribution:** Last 3 CTAs clicked tracked with conversions
- **Section tracking:** ATF, Problem Hook, Authority, FAQ, etc.

**GA4 Custom Dimensions Sent:**
```javascript
{
  altc_cluster: "SEO Basics",
  altc_topic: "Technical SEO",
  content_maturity: "thought_leader",
  optimization_status: "op80",
  intent: "informational",
  purpose: "pillar",
  lead_tier: "hot",
  // + standard GA4 dimensions
}
```

**Use case:** Measure content performance by strategy, not just page views

---

### 5. **Content Analysis Module** ✅
**Status:** Active
**Purpose:** Automated content metrics

**Analyzes:**
- Word count
- Internal/external link count (with URLs)
- H2 count
- Image count

**Stored as:**
- Post meta: `bw_word_count`, `bw_internal_link_count`, `bw_external_link_count`, etc.

**Triggers:** On post save (only if content changed)

**Use case:** Content quality scoring, identify thin content

---

## 🚀 Future State (Planned)

### **MCP Server Module** 🚧
**Status:** Planned (20-40 hours development)
**Purpose:** Enable AI agents to **perform actions**, not just read data

**Why MCP over REST API?**
- REST API = Read-only, one-way
- MCP = Bidirectional, action-based, context-aware

**Planned MCP Tools:**

#### Content Strategy Tools
```typescript
audit_content(post_id) → {verdict, issues, suggestions}
analyze_altc_clusters(cluster_id) → {coverage, gaps, risks}
detect_cannibalization() → {conflicts, recommendations}
suggest_content_gaps() → {missing_topics, article_ideas}
```

#### Content Lifecycle Tools
```typescript
create_draft(topic, cluster, talking_points) → {post_id, url}
update_optimization_status(post_id, status) → {success}
update_maturity_level(post_id, level) → {success}
```

#### Social Media Automation
```typescript
generate_social_posts(post_id, platforms) → {posts[]}
generate_talking_points(content_type) → {talking_points[]}
queue_for_amplification(post_id) → {queued}
```

#### Integration Tools
```typescript
trigger_make_scenario(scenario_id, data) → {triggered}
trigger_n8n_workflow(workflow_id, data) → {triggered}
log_automation_event(event_type, data) → {logged}
```

**Integration Pattern:**
```
Claude AI Agent (via MCP)
    ↕
WordPress MCP Server
    ↕
Make.com/N8N (orchestration)
    ↕
Google Sheets (data tracking)
    ↕
Social Media Platforms
```

---

## 🔄 How Modules Work Together

### **Content Creation Workflow:**
1. **ALTC Module** defines strategy → What topics/clusters to cover
2. **Content Strategy Module** tracks production → idea → draft → published
3. **Content Analysis Module** measures quality → word count, links, structure
4. **Analytics Module** tracks performance → views, engagement, conversions
5. **REST API/MCP** exposes all data → AI can analyze and suggest next steps

### **AI Automation Example (Current):**
```
1. Custom GPT calls REST API → Get all posts in "SEO Basics" cluster
2. AI analyzes → Identifies gaps, outdated content, cannibalization
3. Human acts → Creates/updates content based on suggestions
```

### **AI Automation Example (Future with MCP):**
```
1. Claude via MCP: analyze_altc_clusters("SEO Basics")
2. AI finds: "Missing topic: Core Web Vitals, 3 posts competing for 'meta tags'"
3. Claude: create_draft("Core Web Vitals Guide", cluster_id, talking_points)
4. Claude: generate_social_posts(new_post_id, ["linkedin", "twitter"])
5. Claude: trigger_make_scenario("social_queue", {posts, schedule})
6. Make.com: Posts social content over next 2 weeks
7. Claude: update_optimization_status(competing_posts, "needs_consolidation")
```

---

## 📊 Data Flow

```
WordPress Content
    ↓
Custom Fields (ALTC, Strategy, Metrics)
    ↓
REST API / MCP Server
    ↓
AI Agent (Claude, Custom GPT)
    ↓
Analysis / Actions
    ↓
Make.com/N8N Orchestration
    ↓
Google Sheets (tracking) + Social Media (distribution)
    ↓
GA4 Analytics (performance measurement)
    ↓
Looker Studio (reporting)
```

---

## 🎯 Current Use Cases

1. **Content Audits:** "Analyze my SEO cluster and tell me what's missing"
2. **Social Media:** Generate posts from existing content + talking points
3. **Strategy Review:** Check content maturity distribution, find gaps
4. **Performance Analysis:** Which content performs best by cluster/topic?

---

## 🚀 Future Use Cases (with MCP)

1. **Automated Content Planning:** AI suggests next 10 articles based on gaps + performance
2. **Content Lifecycle Management:** Auto-flag outdated content, suggest rewrites
3. **Social Amplification:** Auto-generate + queue social posts for high-performers
4. **Cannibalization Fixes:** Auto-consolidate competing posts, create redirects
5. **Content Maturity Progression:** Guide content from entry → thought leader with AI rewrites

---

## 🔑 Key Endpoints (REST API)

**Base URL:** `https://yourdomain.com/wp-json/brighter-core/v1/`

**Authentication:** Header `X-Brighter-Token: [your-api-key]`

**Get Posts:**
```http
GET /posts?per_page=50&status=publish
```

**Response includes:**
```json
{
  "items": [{
    "id": 123,
    "title": "...",
    "content": "...",
    "altc_cluster": "SEO Basics",
    "altc_topic": "Technical SEO",
    "content_maturity": "thought_leader",
    "optimization_status": "op80",
    "intent": "informational",
    "purpose": "pillar",
    "word_count": 2500,
    "internal_link_count": 15,
    "external_link_count": 8
  }],
  "pagination": {...}
}
```

---

## 📝 Quick Reference

**Existing Documentation:**
- `AUDIT.md` - Complete codebase inventory
- `STRATEGY.md` - Long-term product vision
- `REFACTOR_PLAN.md` - Migration roadmap

**Key Files:**
- `brighter-core/includes/api/` - REST API implementation
- `brighter-core/includes/class-altc-*.php` - ALTC taxonomy system
- `brighter-core/includes/bw-content-strategy.php` - Content strategy fields
- `brighter-core/js/brighter-ga4-enhanced.js` - Analytics tracking

---

## 🤖 For Claude AI Agents

**What you can do NOW (via REST API):**
- ✅ Read all posts with full metadata
- ✅ Analyze content strategy alignment
- ✅ Identify gaps, cannibalization risks
- ✅ Suggest content improvements
- ⚠️ **Cannot:** Create/update posts (read-only)

**What you'll be able to do (via MCP):**
- ✅ Everything above PLUS:
- ✅ Create draft posts
- ✅ Update optimization status
- ✅ Generate social media content
- ✅ Trigger workflows in Make.com/N8N
- ✅ Perform multi-step automated workflows

---

**End of Overview**
