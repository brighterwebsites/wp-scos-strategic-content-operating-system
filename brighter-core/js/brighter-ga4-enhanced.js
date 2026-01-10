/**
 * Brighter GA4 Enhanced v5.0.2
 * Lead Hierarchy + Selector Attribution + CTA Tracking
 */
(function() {
  'use strict';

  function hasConsent() {
    var c = document.cookie.split(';');
    for (var i = 0; i < c.length; i++) {
      var ck = c[i].trim();
      if (ck.startsWith('seopress-user-consent-accept=')) {
        var v = ck.split('=')[1];
        if (v === '1' || v === 'true' || v === "'1'" || v === '"1"') return true;
      }
    }
    if (window.brighterGA4 && window.brighterGA4.loaded) return true;
    return false;
  }

  var enhancedInitialized = false;

  function initializeEnhanced() {
    if (enhancedInitialized) return;
    if (!hasConsent()) return;
    if (typeof window.gtag !== 'function') { setTimeout(initializeEnhanced, 100); return; }
    enhancedInitialized = true;
  
  const contentStrategy = window.brighterContentStrategy || {};
  
  function getBaseParams() {
    return {
      page_title: document.title,
      page_path: location.pathname,
      content_intent: contentStrategy.content_intent || 'not_set',
      content_purpose: contentStrategy.content_purpose || 'not_set',
      content_topic: contentStrategy.content_topic || 'not_set',
      optimization_status: contentStrategy.optimization_status || 'not_set',
      pillar_page: contentStrategy.pillar_page || document.title,
      pillar_type: contentStrategy.pillar_type || 'none',
      post_type: contentStrategy.post_type || 'page'
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
    { pattern: /quote|enquiry|business/i, type: 'quote_request', tier: 'hot' },
    { pattern: /quiz.*quote/i, type: 'quiz_quote', tier: 'hot' },
    { pattern: /contact|inquiry/i, type: 'contact_form', tier: 'warm' },
    { pattern: /quick|express/i, type: 'quick_quote', tier: 'warm' },
    { pattern: /meeting|book|schedule/i, type: 'meeting_request', tier: 'warm' },
    { pattern: /subscribe|newsletter/i, type: 'newsletter', tier: 'cold' },
    { pattern: /download|magnet|guide/i, type: 'lead_magnet', tier: 'cold' }
  ];

  function inferFromFieldCount(count) {
    if (count >= 7) return { tier: 'hot', type: 'quote_request' };
    if (count >= 3) return { tier: 'warm', type: 'contact_form' };
    return { tier: 'cold', type: 'newsletter' };
  }

  function detectFormLeadData(form) {
    const wrapper = form.closest('[class*="ga-"]') || form.parentElement;
    const classList = Array.from(wrapper?.classList || []);
    const formId = (form.id || form.getAttribute('name') || '').toLowerCase();
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
    return { tier, type };
  }

  // Selector attribution rules
  const RULES = [
    { s: '[data-track="meeting"], .ga-cta-meeting', e: 'click_meeting', c: 'Meetings', l: 'Meeting CTA', v: 25 },
    { s: '.ga-cta-menu', e: 'click_menu_cta', c: 'CTA', l: 'Menu CTA', v: 30 },
    { s: '.ga-cta-main', e: 'click_main_cta', c: 'CTA', l: 'Main CTA', v: 30 },
    { s: '.ga-cta-micro', e: 'click_micro_cta', c: 'CTA', l: 'Micro CTA', v: 15 },
    { s: '.ga-cta-assist', e: 'click_assist_cta', c: 'CTA', l: 'Assist CTA', v: 15 },
    { s: '.ga-cta-phone, [href^="tel:"]', e: 'CTA', c: 'CTA', l: 'Phone CTA', v: 10 },
    { s: '.ga-cta-email, [href^="mailto:"]', e: 'CTA', c: 'CTA', l: 'Email CTA', v: 10 },
    { s: '.ga-cta-end', e: 'click_main_cta', c: 'CTA', l: 'Final CTA', v: 35 },
    { s: '.ga-lead_magnet', e: 'get_lead_magnet', c: 'Lead Magnet', l: 'Access LM', v: 20 },
    { s: '.ga-lead_magsection', e: 'view_lead_magnet', c: 'Lead Magnet', l: 'View LM Section', v: 5 },
    { s: '.ga-nav-blog, a[href*="/blog"]', e: 'nav_blog', c: 'Navigation', l: 'Blog Path', v: 1 },
    { s: '.ga-nav-project', e: 'nav_project', c: 'Navigation', l: 'Portfolio Path', v: 1 },
    { s: '.ga-nav-product', e: 'nav_product', c: 'Navigation', l: 'Product Path', v: 2 },
    { s: '.ga-nav-service', e: 'nav_service', c: 'Navigation', l: 'Service Path', v: 2 },
    { s: '.ga-nav-pricing', e: 'nav_pricing_detail', c: 'Navigation', l: 'Pricing Path', v: 8 },
    { s: '.ga-nav-compare', e: 'nav_comparison', c: 'Navigation', l: 'Comparison Path', v: 8 },
    { s: '.ga-nav-faq', e: 'nav_faq', c: 'Navigation', l: 'FAQ Path', v: 8 },
    { s: '.ga-nav-path', e: 'nav_path', c: 'Navigation', l: 'Conversion Path', v: 8 },
    { s: '.ga-trust-reviews', e: 'view_reviews', c: 'Trust', l: 'Reviews Viewed', v: 5 },
    { s: '.ga-trust-pricing', e: 'view_pricing', c: 'Trust', l: 'Pricing Viewed', v: 8 },
    { s: '.ga-trust-specs', e: 'view_specs', c: 'Trust', l: 'Specifications Viewed', v: 7 },
    { s: '.ga-trust-case', e: 'view_case', c: 'Trust', l: 'Case Study Excerpt Viewed', v: 6 },
    { s: '.ga-exp-video', e: 'click_video', c: 'Trust', l: 'Expert Video Viewed', v: 2 },
    { s: '.ga-trust-badge', e: 'view_badge', c: 'Trust', l: 'Trust Badge Viewed', v: 7 },
    { s: '.ga-hrcy-atf', e: 'view_section', c: 'Hierarchy', l: 'ATF Viewed', v: 1 },
    { s: '.ga-hrcy-phs', e: 'view_section', c: 'Hierarchy', l: 'Problem Hook Viewed', v: 2 },
    { s: '.ga-hrcy-ppd', e: 'view_section', c: 'Hierarchy', l: 'Position Promise Dif Viewed', v: 3 },
    { s: '.ga-hrcy-method', e: 'view_section', c: 'Hierarchy', l: 'Method Viewed', v: 3 },
    { s: '.ga-hrcy-specs', e: 'view_section', c: 'Hierarchy', l: 'Specifications Viewed', v: 7 },
    { s: '.ga-hrcy-pricing', e: 'view_section', c: 'Hierarchy', l: 'Pricing Viewed', v: 8 },
    { s: '.ga-hrcy-aut', e: 'view_section', c: 'Hierarchy', l: 'Authority Viewed', v: 3 },
    { s: '.ga-hrcy-tac', e: 'view_section', c: 'Hierarchy', l: 'Trust Anchors Viewed', v: 4 },
    { s: '.ga-hrcy-faq', e: 'view_section', c: 'Hierarchy', l: 'FAQ Viewed', v: 3 },
    { s: '.ga-hrcy-mid', e: 'view_section', c: 'Hierarchy', l: 'MidCTA Reached', v: 3 },
    { s: '.ga-hrcy-final', e: 'view_section', c: 'Hierarchy', l: 'Final Push Reached', v: 5 },
    { s: '.ga-hrcy-assist', e: 'view_section', c: 'Hierarchy', l: 'Assist CTA Reached', v: 5 },
    { s: '.ga-hrcy-end', e: 'view_section', c: 'Hierarchy', l: 'EndCTA Reached', v: 5 },
    { s: '.ga-form', e: 'form', c: 'Forms', l: 'General Form', v: 20 },
    { s: '.ga-subscribe', e: 'subscribe', c: 'Forms', l: 'Subscribed', v: 20 },
    { s: '.ga-quote', e: 'quote_form', c: 'Forms', l: 'Quote Form', v: 30 },
    { s: '.ga-vsubscribe', e: 'view_sub_form', c: 'Forms', l: 'View Subscribe Form', v: 2 },
    { s: '.ga-vquote', e: 'view_quote_form', c: 'Forms', l: 'View Quote Form', v: 5 },
    { s: '.ga-vcontact', e: 'view_contact_form', c: 'Forms', l: 'View Contact Form', v: 4 }
  ];
  
  function tag(root = document) {
    RULES.forEach(r => {
      root.querySelectorAll(r.s).forEach(el => {
        if (!el.dataset.gaEvent && r.e) el.dataset.gaEvent = r.e;
        if (!el.dataset.gaCategory) el.dataset.gaCategory = r.c;
        if (!el.dataset.gaLabel) el.dataset.gaLabel = r.l;
        if (!el.dataset.value && r.v) el.dataset.value = r.v;
        if (r.v && !el.dataset.currency) el.dataset.currency = 'AUD';
        if (r.e && !r.e.includes('download') && !el.dataset.gaImpression) el.dataset.gaImpression = '';
      });
    });
  }

  // CTA context tracking
  function storeCTAContext(element) {
    try {
      const ctaData = {
        label: element.textContent?.trim() || element.value || 'Unknown',
        location: detectLocation(element),
        type: detectCTAType(element),
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

  function detectCTAType(element) {
    const classList = Array.from(element.classList);
    if (classList.some(c => c.includes('ga-cta-main'))) return 'main';
    if (classList.some(c => c.includes('ga-cta-micro'))) return 'micro';
    if (classList.some(c => c.includes('ga-cta-assist'))) return 'assist';
    return 'unknown';
  }

  function getLastCTAContext() {
    try { return JSON.parse(sessionStorage.getItem('bw_cta_history') || '[]')[0] || null; }
    catch(e) { return null; }
  }

  // Click tracking
  document.addEventListener('click', function(e) {
    const el = e.target.closest('[data-ga-event]');
    if (!el || el.dataset.gaSkip === '1') return;
    if (el.classList.contains('ga-cta-main') || el.classList.contains('ga-cta-micro') || 
        el.classList.contains('ga-cta-assist') || el.tagName === 'BUTTON' || el.tagName === 'A') {
      storeCTAContext(el);
    }
    const ds = el.dataset;
    const payload = getBaseParams();
    if (ds.gaCategory) payload.event_category = ds.gaCategory;
    if (ds.gaLabel) payload.event_label = ds.gaLabel;
    if (ds.value && !isNaN(ds.value)) { payload.value = parseFloat(ds.value); payload.currency = ds.currency || 'AUD'; }
    if (ds.meetingType) payload.meeting_type = ds.meetingType;
    if (ds.meetingLocation) payload.meeting_location = ds.meetingLocation;
    if (ds.service) payload.service_type = ds.service;
    gtag('event', ds.gaEvent, payload);
  }, true);
  
  // Impression tracking
  if ('IntersectionObserver' in window) {
    const obs = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const el = entry.target, ds = el.dataset;
          const payload = getBaseParams();
          payload.event_category = (ds.gaCategory || 'Engagement') + ' Impressions';
          payload.event_label = ds.gaLabel || el.textContent?.trim() || 'Unknown';
          gtag('event', ds.gaEvent || 'view_component', payload);
          obs.unobserve(el);
        }
      });
    }, { threshold: 0.5 });
    document.querySelectorAll('[data-ga-impression]').forEach(el => obs.observe(el));
  }
  
  // Form tracking with lead hierarchy
  const started = new WeakSet();
  document.querySelectorAll('form').forEach(form => {
    const id = form.id || form.className || 'Form';
    const cat = form.dataset.gaCategory || 'Forms';
    const label = form.dataset.gaLabel || id;

    form.addEventListener('input', () => {
      if (!started.has(form)) {
        const payload = getBaseParams();
        payload.event_category = cat;
        payload.event_label = label;
        gtag('event', 'form_start', payload);
        started.add(form);
      }
    }, { once: true });

    form.addEventListener('submit', () => {
      const leadData = detectFormLeadData(form);
      const lastCTA = getLastCTAContext();
      const fieldCount = form.querySelectorAll('input:not([type="hidden"]):not([disabled]),textarea:not([disabled]),select:not([disabled])').length;

      const payload = getBaseParams();
      payload.event_category = cat;
      payload.event_label = label;
      payload.lead_tier = leadData.tier;
      payload.lead_type = leadData.type;
      payload.form_type = leadData.type;
      payload.form_fields = fieldCount;
      payload.form_id = form.id || form.getAttribute('name') || 'unknown';

      if (lastCTA) {
        payload.cta_label = lastCTA.label;
        payload.cta_location = lastCTA.location;
        payload.cta_type = lastCTA.type;
      }

      const wrapper = form.closest('[class*="ga-hrcy-"]');
      payload.element_location = (wrapper && wrapper.classList.contains('ga-hrcy-atf')) ? 'above_fold' : 'below_fold';

      const val = form.dataset.value;
      if (val && !isNaN(val)) payload.value = parseFloat(val);
      else payload.value = { hot: 50, warm: 25, cold: 10 }[leadData.tier] || 20;
      payload.currency = form.dataset.currency || 'AUD';

      gtag('event', 'form_submit', payload);
      gtag('event', 'generate_lead', payload);
    });
  });
  
  gtag('event', 'page_view', getBaseParams());
  
  // Watch for dynamic content
  const contentObserver = new MutationObserver(mutations => {
    mutations.forEach(mutation => {
      mutation.addedNodes.forEach(node => { if (node.nodeType === 1) tag(node); });
    });
  });
  contentObserver.observe(document.body, { childList: true, subtree: true });
  tag();
}

  initializeEnhanced();
  document.addEventListener('seopress_analytics_cookies_accepted', initializeEnhanced);
})();
