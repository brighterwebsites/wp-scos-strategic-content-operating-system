/**
 * Brighter GA4 Enhanced v5.5.0
 * Lead Hierarchy + Selector Attribution + CTA Tracking
 *
 * v5.5.0 | 2026-10-21: parameter clean-up (#98). altc_primary → cluster,
 * altc_topic → topic (no dual-fire). content_topic, pillar_page and
 * pillar_type are no longer sent. Click event_label is the link/button text
 * (then aria-label/title, then image alt), falling back to the rule's role
 * label only for empty icon links; an explicit data-ga-label still wins.
 *
 * v5.4.0 | 2026-10-21: trimmed to the events in use (#159). Cut nav_*, trust
 * views (incl. click_video), form-view events and the content-section
 * view_section labels; the CTA milestone view_sections (mid, final, assist,
 * end) stay. ga-hrcy-* classes still set cta_location / element_location.
 * cta_type now knows menu, end, meeting, phone and email CTAs, not only
 * main/micro/assist. content_plan is no longer sent (#97).
 *
 * v5.3.0 | 2026-10-21: stop event inflation (#158). Only view_* rules are
 * observed for impressions — click events no longer fire when an element
 * scrolls into view. Clicks inside view sections and form wrappers no longer
 * fire. Form wrapper events (form_quote etc.) fire once, on submit.
 * form_start renamed lead_form_start; our duplicate form_submit dropped
 * (generate_lead carries the same payload) — both collided with GA4
 * enhanced-measurement form interactions.
 *
 * v5.2.0 | 2026-10-20: read CAR v1.2 keys (known-for-goal, topic-maturity,
 * commercial-end-target). altc_primary, content_maturity and service_pathway
 * were always not_set/none since the CAR restructure.
 *
 * v5.1.0: Live RULES lookup for click tracking — fires correctly before tag() runs,
 * eliminating early-click gaps. Deferred tag() + IntersectionObserver to
 * requestIdleCallback, reducing TBT. Fixed IntersectionObserver setup order
 * so it queries [data-ga-impression] after tag() has stamped the attributes.
 */
(function() {
  'use strict';

  var enhancedInitialized = false;

  function initializeEnhanced() {
    if (enhancedInitialized) return;
    if (window.brighterGA4 && window.brighterGA4.skipTracking === true) return;
    if (typeof window.gtag !== 'function') { setTimeout(initializeEnhanced, 100); return; }
    enhancedInitialized = true;

    const scos = window.scosCAR || {};
    const car = scos.car || {};
    const meta = scos.meta || {};
    // CAR v1.2 keys (scos-car-injection.php). GA4 param names are unchanged so
    // existing custom dimensions keep collecting.
    const knownFor = car['known-for-goal'] || {};
    const endTarget = car['commercial-end-target'] || null;

    function getBaseParams() {
      const breadcrumb = scos.breadcrumb_schema || '';
      const pageTitle = (breadcrumb && breadcrumb.trim()) ? breadcrumb.trim() : document.title;
      return {
        page_title:       pageTitle,
        content_intent:   car.intent    || 'not_set',
        content_purpose:  car.purpose   || 'not_set',
        cluster:          knownFor.title || 'not_set',
        topic:            car.topic     || 'not_set',
        content_maturity: car['topic-maturity'] || 'not_set',
        service_pathway:  (endTarget && endTarget.title) ? endTarget.title : 'none',
        post_type:        meta.post_type || 'page'
      };
    }

    // Lead tier mappings
    const CLASS_TO_TYPE = {
      'ga-quote': 'quote_request', 'ga-business-enquiry': 'business_enquiry', 'ga-quiz-quote': 'quiz_quote',
      'ga-contact': 'contact_form', 'ga-quick-contact': 'quick_quote', 'ga-meeting': 'meeting_request',
      'ga-subscribe': 'newsletter', 'ga-lead_magnet': 'lead_magnet', 'ga-newsletter': 'newsletter'
    };

    const FORM_TYPE_TO_TIER = {
      'quote_request': 'hot', 'business_enquiry': 'hot', 'quiz_quote': 'hot',
      'contact_form': 'warm', 'quick_quote': 'warm', 'meeting_request': 'warm',
      'newsletter': 'cold', 'lead_magnet': 'cold'
    };

    const ID_PATTERNS = [
      { pattern: /quote|enquiry|business/i, type: 'quote_request',   tier: 'hot'  },
      { pattern: /quiz.*quote/i,            type: 'quiz_quote',       tier: 'hot'  },
      { pattern: /contact|inquiry/i,        type: 'contact_form',     tier: 'warm' },
      { pattern: /quick|express/i,          type: 'quick_quote',      tier: 'warm' },
      { pattern: /meeting|book|schedule/i,  type: 'meeting_request',  tier: 'warm' },
      { pattern: /subscribe|newsletter/i,   type: 'newsletter',       tier: 'cold' },
      { pattern: /download|magnet|guide/i,  type: 'lead_magnet',      tier: 'cold' }
    ];

    function inferFromFieldCount(count) {
      if (count >= 7) return { tier: 'hot',  type: 'quote_request' };
      if (count >= 3) return { tier: 'warm', type: 'contact_form'  };
      return              { tier: 'cold', type: 'newsletter'    };
    }

    function detectFormLeadData(form) {
      const wrapper  = form.closest('[class*="ga-"]') || form.parentElement;
      const classList = Array.from(wrapper?.classList || []);
      const formId   = (form.id || form.getAttribute('name') || '').toLowerCase();
      let tier = null, type = null;

      classList.forEach(cls => { if (cls.startsWith('ga-form-')) tier = cls.replace('ga-form-', ''); });
      classList.forEach(cls => {
        if (CLASS_TO_TYPE[cls]) {
          type = CLASS_TO_TYPE[cls];
          if (!tier && FORM_TYPE_TO_TIER[type]) tier = FORM_TYPE_TO_TIER[type];
        }
      });

      if (!tier || !type) {
        for (const item of ID_PATTERNS) {
          if (item.pattern.test(formId)) { type = type || item.type; tier = tier || item.tier; break; }
        }
      }

      if (!tier || !type) {
        const fieldCount = form.querySelectorAll('input:not([type="hidden"]):not([disabled]),textarea:not([disabled]),select:not([disabled])').length;
        const inferred = inferFromFieldCount(fieldCount);
        tier = tier || inferred.tier;
        type = type || inferred.type;
      }

      const validTiers = ['hot', 'warm', 'cold'];
      if (!validTiers.includes(tier)) tier = 'unknown';
      type = type || 'unknown';
      return { tier, type };
    }

    // Selector attribution rules
    const RULES = [
      // CTA — label falls back to link/button text; use label only for image/icon links
      { s: '[data-track="meeting"], .ga-cta-meeting', e: 'click_meeting',   c: 'CTA',         l: 'Meeting CTA',              v: 5  },
      { s: '.ga-cta-menu',                            e: 'click_menu_cta',  c: 'CTA',         l: 'Menu CTA',                 v: 5  },
      { s: '.ga-cta-main',                            e: 'click_main_cta',  c: 'CTA',         l: 'Main CTA',                 v: 5  },
      { s: '.ga-cta-micro',                           e: 'click_micro_cta', c: 'CTA',         l: 'Micro CTA',                v: 3  },
      { s: '.ga-cta-assist',                          e: 'click_assist_cta',c: 'CTA',         l: 'Assist CTA',               v: 2  },
      { s: '.ga-cta-end',                             e: 'click_end_cta',   c: 'CTA',         l: 'Final CTA',                v: 5  },

      { s: '.ga-cta-phone, [href^="tel:"]',           e: 'click_phone',     c: 'Contact',     l: 'Phone CTA',                v: 20 },
      { s: '.ga-cta-email, [href^="mailto:"]',        e: 'click_email',     c: 'Contact',     l: 'Email CTA',                v: 20 },

      // Forms — label should be form id; fall back to label here
      { s: '.ga-form',        e: 'form_enquiry',    c: 'Forms',       l: 'Specific Enquiry', v: 15 },
      { s: '.ga-quote',       e: 'form_quote',      c: 'Forms',       l: 'Quote Form',       v: 30 },
      { s: '.ga-subscribe',   e: 'form_subscribe',  c: 'Forms',       l: 'Subscribed',       v: 1  },
      { s: '.ga-lead_magnet', e: 'form_lead_magnet',c: 'Forms',       l: 'Lead Magnet',      v: 5  },


      { s: '.ga-hrcy-mid',    e: 'view_section', c: 'Hierarchy', l: 'MidCTA Reached',                v: 1 },
      { s: '.ga-hrcy-final',  e: 'view_section', c: 'Hierarchy', l: 'Final Push Reached',            v: 2 },
      { s: '.ga-hrcy-assist', e: 'view_section', c: 'Hierarchy', l: 'Assist CTA Reached',            v: 2 },
      { s: '.ga-hrcy-end',    e: 'view_section', c: 'Hierarchy', l: 'EndCTA Reached',                v: 3 },
    ];

    // Three kinds of rule, each with exactly one trigger:
    //   view_*  → impression only (IntersectionObserver)
    //   Forms   → form wrapper, fires on submit only
    //   others  → click only
    const isViewRule  = r => r.e.indexOf('view_') === 0;
    const isFormRule  = r => r.c === 'Forms';
    const CLICK_RULES = RULES.filter(r => !isViewRule(r) && !isFormRule(r));
    const FORM_RULES  = RULES.filter(isFormRule);

    // Stamps data-ga-* attributes for DevTools inspection. Click rules stamp the
    // click attributes; view rules only stamp data-ga-impression (the rule index),
    // so an element can never fire its click event on impression.
    function tag(root) {
      root = root || document;
      RULES.forEach((r, i) => {
        root.querySelectorAll(r.s).forEach(el => {
          if (isViewRule(r)) {
            if (!el.dataset.gaImpression) el.dataset.gaImpression = String(i);
            return;
          }
          if (isFormRule(r)) return;
          if (!el.dataset.gaEvent) el.dataset.gaEvent = r.e;
          if (!el.dataset.gaCategory) el.dataset.gaCategory = r.c;
          if (!el.dataset.value && r.v) el.dataset.value = r.v;
          if (r.v && !el.dataset.currency) el.dataset.currency = 'AUD';
        });
      });
    }

    // Visible copy of a link/button: text, then aria-label/title, then image alt. GA4 caps values at 100 chars.
    function ctaText(element) {
      const text = (element.textContent || '').replace(/\s+/g, ' ').trim()
        || (element.getAttribute('aria-label') || element.getAttribute('title') || '').trim()
        || ((element.querySelector('img[alt]') || {}).alt || '').trim();
      return text.slice(0, 100);
    }

    // CTA context tracking
    function storeCTAContext(element) {
      try {
        const ctaData = {
          label:     ctaText(element) || element.value || 'Unknown',
          location:  detectLocation(element),
          type:      detectCTAType(element),
          timestamp: Date.now()
        };
        const history = JSON.parse(sessionStorage.getItem('bw_cta_history') || '[]');
        history.unshift(ctaData);
        if (history.length > 3) history.length = 3;
        sessionStorage.setItem('bw_cta_history', JSON.stringify(history));
      } catch(e) {}
    }

    function detectLocation(element) {
      const section = element.closest('section, [class*="ga-hrcy-"]');
      if (section) {
        const hrchyClass = Array.from(section.classList).find(c => c.startsWith('ga-hrcy-'));
        if (hrchyClass) return hrchyClass.replace('ga-hrcy-', '');
      }
      if (element.closest('header')) return 'header';
      if (element.closest('footer')) return 'footer';
      if (section && section.classList.length > 0) return section.classList[0];
      return 'unknown';
    }

    // Mirrors the CTA click rules: ga-cta-<type> class, then the meeting/tel/mailto fallbacks.
    const CTA_TYPES = ['menu', 'main', 'micro', 'assist', 'end', 'meeting', 'phone', 'email'];
    function detectCTAType(element) {
      const classList = Array.from(element.classList);
      const type = CTA_TYPES.find(t => classList.some(c => c.indexOf('ga-cta-' + t) === 0));
      if (type) return type;
      if (element.matches('[data-track="meeting"]')) return 'meeting';
      if (element.matches('[href^="tel:"]'))         return 'phone';
      if (element.matches('[href^="mailto:"]'))      return 'email';
      return 'unknown';
    }

    function getLastCTAContext() {
      try { return JSON.parse(sessionStorage.getItem('bw_cta_history') || '[]')[0] || null; }
      catch(e) { return null; }
    }

    // Click tracking — live CLICK_RULES lookup ensures events fire even before tag() has run.
    // View and form rules are excluded: a click inside a section or form wrapper is not an event.
    // Uses el.contains() to prefer the deepest (most specific) match when multiple rules
    // match ancestors of the click target.
    document.addEventListener('click', function(e) {
      if (window.brighterGA4 && window.brighterGA4.skipTracking === true) return;

      let el = null, rule = null;
      for (const r of CLICK_RULES) {
        try {
          const match = e.target.closest(r.s);
          if (match && (!el || el.contains(match))) { el = match; rule = r; }
        } catch(err) {}
      }

      if (!el || el.dataset.gaSkip === '1') return;

      if (detectCTAType(el) !== 'unknown' || el.tagName === 'BUTTON' || el.tagName === 'A') {
        storeCTAContext(el);
      }

      const ds        = el.dataset;
      const eventName = ds.gaEvent    || (rule && rule.e);
      const category  = ds.gaCategory || (rule && rule.c);
      const label     = ds.gaLabel    || ctaText(el) || (rule && rule.l);
      const value     = ds.value      || (rule && rule.v);

      if (!eventName) return;

      const payload = getBaseParams();
      if (category) payload.event_category = category;
      if (label)    payload.event_label    = label;
      if (value && !isNaN(value)) { payload.value = parseFloat(value); payload.currency = ds.currency || 'AUD'; }
      if (ds.meetingType)     payload.meeting_type     = ds.meetingType;
      if (ds.meetingLocation) payload.meeting_location = ds.meetingLocation;
      if (ds.service)         payload.service_type     = ds.service;

      gtag('event', eventName, payload);
    }, true);

    // Form tracking with lead hierarchy
    const started = new WeakSet();
    document.querySelectorAll('form').forEach(form => {
      const id    = form.id || form.className || 'Form';
      const cat   = form.dataset.gaCategory || 'Forms';
      const label = form.dataset.gaLabel || id;

      form.addEventListener('input', () => {
        if (!started.has(form)) {
          const leadData = detectFormLeadData(form);
          const payload  = getBaseParams();
          payload.event_category = cat;
          payload.event_label    = label;
          payload.lead_tier      = leadData.tier || 'unknown';
          payload.lead_type      = leadData.type || 'unknown';
          gtag('event', 'lead_form_start', payload); // not form_start — that's enhanced measurement's name
          started.add(form);
        }
      }, { once: true });

      form.addEventListener('submit', () => {
        const leadData   = detectFormLeadData(form);
        const lastCTA    = getLastCTAContext();
        const fieldCount = form.querySelectorAll('input:not([type="hidden"]):not([disabled]),textarea:not([disabled]),select:not([disabled])').length;

        const payload = getBaseParams();
        payload.event_category = cat;
        payload.event_label    = label;
        payload.lead_tier      = leadData.tier || 'unknown';
        payload.lead_type      = leadData.type || 'unknown';
        payload.form_type      = leadData.type || 'unknown';
        payload.form_fields    = fieldCount;
        payload.form_id        = form.id || form.getAttribute('name') || 'unknown';

        if (lastCTA) {
          payload.cta_label    = lastCTA.label;
          payload.cta_location = lastCTA.location;
          payload.cta_type     = lastCTA.type;
        }

        const wrapper = form.closest('[class*="ga-hrcy-"]');
        payload.element_location = (wrapper && wrapper.classList.contains('ga-hrcy-atf')) ? 'above_fold' : 'below_fold';

        const val = form.dataset.value;
        if (val && !isNaN(val)) payload.value = parseFloat(val);
        else payload.value = { hot: 50, warm: 25, cold: 10 }[leadData.tier] || 20;
        payload.currency = form.dataset.currency || 'AUD';

        gtag('event', 'generate_lead', payload);

        // Wrapper-specific lead event (form_quote etc.), once per submit — never on click
        const formRule = FORM_RULES.find(r => form.closest(r.s));
        if (formRule) {
          const rulePayload = Object.assign({}, payload, { event_category: formRule.c, event_label: formRule.l });
          if (formRule.v) rulePayload.value = formRule.v;
          gtag('event', formRule.e, rulePayload);
        }
      });
    });

    // page_view
    gtag('event', 'page_view', getBaseParams());

    // Defer DOM-heavy work to idle time to reduce TBT.
    // tag() runs first so IntersectionObserver can query [data-ga-impression] correctly.
    function deferredSetup() {
      tag();

      if ('IntersectionObserver' in window) {
        const obs = new IntersectionObserver(entries => {
          entries.forEach(entry => {
            if (entry.isIntersecting) {
              const el = entry.target, rule = RULES[+el.dataset.gaImpression];
              obs.unobserve(el);
              if (!rule) return;
              const payload = getBaseParams();
              payload.event_category = rule.c + ' Impressions';
              payload.event_label    = rule.l;
              gtag('event', rule.e, payload);
            }
          });
        }, { threshold: 0.5 });
        // Query after tag() so [data-ga-impression] attributes exist
        document.querySelectorAll('[data-ga-impression]').forEach(el => obs.observe(el));
      }

      // Watch for dynamic content added after initial load
      const contentObserver = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
          mutation.addedNodes.forEach(node => { if (node.nodeType === 1) tag(node); });
        });
      });
      contentObserver.observe(document.body, { childList: true, subtree: true });
    }

    if ('requestIdleCallback' in window) {
      requestIdleCallback(deferredSetup, { timeout: 2000 });
    } else {
      setTimeout(deferredSetup, 200);
    }
  }

  initializeEnhanced();
})();
