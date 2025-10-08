
/**
 * Brighter Websites - Unified GA4 Tracking Script
 * Combines all tracking functionality with intelligent defaults
 * Author: Brighter Websites
 * Version: 2.0.1
 * Last Updated: 2025-09-04
 */

window.__BW_GA_VERSION = '2.0.2';





(function () {
  'use strict';
// --- GA4 bootstrap shim (keeps MU script safe even if GA loads late) ---
window.dataLayer = window.dataLayer || [];
if (typeof window.gtag !== 'function') {
  window.gtag = function(){ dataLayer.push(arguments); };
}


  // Config
  const CONFIG = {
    region: new URLSearchParams(location.search).get('region') || 'zone4-remote',
    scrollThreshold: 0.5,           // 50% page depth
    intersectionThreshold: 0.5,     // 50% element visibility
    eventTimeout: 200               // ms for navigation event_callback
  };

  // Set user properties
  gtag('set', 'user_properties', { region_id: CONFIG.region });

  // Selector rules with sensible defaults (override per element via data-* if needed)
  const SELECTOR_RULES = [
    // Meetings
    { selector: '[data-track="meeting"], .ga-cta-meeting',
      attrs: { gaEvent: 'click_meeting', gaCategory: 'Meetings', gaLabel: 'Meeting CTA', value: 25, currency: 'AUD' }},

    // Contact CTAs
    { selector: '.ga-cta-phone, [href^="tel:"]',
      attrs: { gaEvent: 'click_phone', gaCategory: 'Contact', gaLabel: 'Phone CTA', value: 10, currency: 'AUD' }},
    { selector: '.ga-cta-email, [href^="mailto:"]',
      attrs: { gaEvent: 'click_email', gaCategory: 'Contact', gaLabel: 'Email CTA', value: 10, currency: 'AUD' }},

    // Main CTA
    { selector: '.ga-cta-main',
      attrs: { gaEvent: 'click_main_cta', gaCategory: 'Quote', gaLabel: 'Main CTA', value: 30, currency: 'AUD' }},

    // Lead magnet vs generic file downloads
    { selector: '.ga-download-lm',
      attrs: { gaEvent: 'download_lead_magnet', gaCategory: 'Lead Magnet', gaLabel: 'Download LM', value: 20, currency: 'AUD' }},
    { selector: 'a[href$=".pdf"], a[href$=".doc"], a[href$=".docx"], a[href$=".ppt"], a[href$=".pptx"], a[href$=".xls"], a[href$=".xlsx"], a[href$=".zip"]',
      attrs: { gaEvent: 'download', gaCategory: 'Downloads', gaLabel: 'File Download' }},

    // Navigation
    { selector: '.ga-nav-blog, a[href*="/blog"]',
      attrs: { gaEvent: 'nav_blog', gaCategory: 'Navigation', gaLabel: 'Blog', value: 1 }},
    { selector: '.ga-nav-folio',
      attrs: { gaEvent: 'nav_folio', gaCategory: 'Navigation', gaLabel: 'Portfolio', value: 1 }},
    { selector: '.ga-nav-product',
      attrs: { gaEvent: 'click_product', gaCategory: 'Product', gaLabel: 'Product', value: 2 }},
    { selector: '.ga-nav-service',
      attrs: { gaEvent: 'click_service', gaCategory: 'Service', gaLabel: 'Service', value: 2 }},

    // Forms and Subscribe
    { selector: '.ga-form, form',
      attrs: { gaCategory: 'Forms', gaLabel: 'Contact Form', value: 20, currency: 'AUD' }},
    { selector: '.ga-subscribe',
      attrs: { gaEvent: 'subscribe', gaCategory: 'Subscribe', gaLabel: 'Subscribe CTA', value: 25, currency: 'AUD' }}
  ];

  // State
  const state = {
    scrollTracked: false,
    formsStarted: new WeakSet()
  };

  // Apply selector rules and auto-impression tagging
  function applyAutoAttribution(root = document) {
    SELECTOR_RULES.forEach(rule => {
      root.querySelectorAll(rule.selector).forEach(el => {
        Object.entries(rule.attrs).forEach(([k, v]) => {
          if (!el.dataset[k]) el.dataset[k] = v;
        });
        // Auto-add impression marker for interactives (avoid downloads)
        if (rule.attrs.gaEvent && rule.attrs.gaEvent !== 'download' && !el.dataset.gaImpression) {
          el.dataset.gaImpression = '';
        }
      });
    });
  }

  // Build GA4 payload from element dataset
  function buildPayload(element, defaults = {}) {
    const ds = element.dataset || {};
    const payload = {
      region_id: CONFIG.region,
      page_title: document.title,
      page_path: window.location.pathname,
      ...defaults
    };

    // Common UA-style mappings
    if (ds.gaCategory) payload.event_category = ds.gaCategory;
    if (ds.gaLabel)    payload.event_label   = ds.gaLabel;

    // Values
    if (ds.value && !isNaN(ds.value)) payload.value = parseFloat(ds.value);
    if (ds.currency)                   payload.currency = ds.currency;

    // Optional extra fields
    if (ds.productSize)                payload.product_size   = ds.productSize;
    if (ds.installOption)              payload.install_option = ds.installOption;
    if (ds.service)                    payload.service_type   = ds.service;

    // Meeting + overlay fields
    if (ds.meetingType)                payload.meeting_type      = ds.meetingType;
    if (ds.meetingLocation)            payload.meeting_location  = ds.meetingLocation;
    if (ds.bookingType)                payload.booking_type      = ds.bookingType;
    if (ds.programName)                payload.program_name      = ds.programName;
    if (ds.durationMinutes && !isNaN(ds.durationMinutes)) {
      payload.duration_minutes = parseInt(ds.durationMinutes, 10);
    }

    // Fallback label
    if (!payload.event_label) {
      payload.event_label =
        element.getAttribute('aria-label') ||
        element.textContent?.trim() ||
        element.getAttribute('alt') ||
        'unlabeled';
    }

    return payload;
  }

  // Infer event type from element context
  function inferEventType(element) {
    const href = element.getAttribute?.('href') || '';
    let url;
    try { url = href ? new URL(href, location.href) : null; } catch (_) { url = null; }

    if (url && url.protocol === 'tel:')     return 'click_phone';
    if (url && url.protocol === 'mailto:')  return 'click_email';
    if (/\.(pdf|docx?|pptx?|xlsx?|zip)$/i.test(url?.pathname || '')) return 'download';
    if (url && url.hostname !== location.hostname) return 'click_outbound';
    if (element.dataset.track === 'meeting') return 'click_meeting';
    if (element.tagName?.toLowerCase() === 'form') return 'form_interaction';
    return 'click';
  }

  // Handle navigation continuity
  function maybeInterceptNavigation(element, payload) {
    const href = element.getAttribute('href');
    if (!href) return false;

    let url;
    try { url = new URL(href, location.href); } catch (_) { return false; }

    const isNewTab = element.hasAttribute('target') && element.getAttribute('target') !== '_self';
    const isSameHost = url.hostname === location.hostname;
    const isHashOrJS = /^(#|javascript:)/i.test(href);
    const isTelOrMail = /^(tel:|mailto:)/i.test(href);

    if (!isNewTab && isSameHost && !isHashOrJS && !isTelOrMail) {
      payload.event_callback = () => { location.href = href; };
      payload.event_timeout = CONFIG.eventTimeout;
      return true; // caller should preventDefault()
    }
    return false;
  }

// CLICK TRACKING (safe, non-blocking with fallback)
document.addEventListener('click', function (e) {
  const el = e.target.closest('a, button, [data-ga-event], [data-ga-label], [href^="tel:"], [href^="mailto:"]');
  if (!el) return;

  // Let you opt-out per element: <a data-ga-skip="1">
  if (el.dataset.gaSkip === '1') return;

  // Determine event name and href
  let eventName = el.dataset.gaEvent || inferredEvent(el);
  const href   = el.tagName === 'A' ? el.getAttribute('href') : null;
  const newTab = el.hasAttribute('target') && el.getAttribute('target') !== '_self';

  // Build payload once
  const payload = buildPayload(el);

  // Do not ever block hash links or new-tab links
  const isNavigable = href && !newTab && !/^#/.test(href);

  // Optional: do not block file downloads at all
  const isFile = href && /\.(pdf|docx?|pptx?|xlsx?|zip)$/i.test(href);
  if (isFile && typeof gtag === 'function' && eventName) {
    gtag('event', eventName, payload);
    return;
  }

  // Block only when GA is live and we can use a callback
  if (isNavigable && eventName && typeof window.gtag === 'function') {
    e.preventDefault();
    let handedOff = false;

    payload.event_callback = function () { handedOff = true; location.href = href; };
    payload.event_timeout  = 200;

    gtag('event', eventName, payload);

    // Hard fallback if GA never calls back
    setTimeout(function () {
      if (!handedOff) location.href = href;
    }, 300);
    return;
  }

  // Non-blocking fire
  if (eventName && typeof window.gtag === 'function') {
    gtag('event', eventName, payload);
  }
}, true);
  // IMPRESSION TRACKING
  function setupImpressions() {
    if (!('IntersectionObserver' in window)) return;
    const els = document.querySelectorAll('[data-ga-impression]');
    if (!els.length) return;

    const obs = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const el = entry.target;
          const eventName = el.dataset.gaEvent || 'view_component'; // avoid ecommerce 'view_item'
          const payload = buildPayload(el, {
            event_category: (el.dataset.gaCategory || 'Engagement') + ' Impressions'
          });
          gtag('event', eventName, payload);
          obs.unobserve(el);
        }
      });
    }, { threshold: CONFIG.intersectionThreshold });

    els.forEach(el => obs.observe(el));
  }

  // FORM TRACKING (start + submit + generate_lead)
  function setupForms() {
    document.querySelectorAll('form').forEach(form => {
      const formId = form.getAttribute('id') || form.className || 'Unnamed Form';
      const category = form.dataset.gaCategory || 'Forms';
      const label = form.dataset.gaLabel || formId;

      // Start
      form.addEventListener('input', () => {
        if (!state.formsStarted.has(form)) {
          gtag('event', 'form_start', {
            event_category: category,
            event_label: label,
            page_title: document.title,
            page_path: window.location.pathname,
            region_id: CONFIG.region
          });
          state.formsStarted.add(form);
        }
      });

      // Submit (+ generate_lead)
      form.addEventListener('submit', () => {
        const ds = form.dataset || {};
        const extra = { event_category: category, event_label: label };
        if (ds.value && !isNaN(ds.value)) {
          extra.value = parseFloat(ds.value);
          extra.currency = ds.currency || 'AUD';
        }
        gtag('event', 'form_submit', {
          page_title: document.title,
          page_path: window.location.pathname,
          region_id: CONFIG.region,
          ...extra
        });
        gtag('event', 'generate_lead', {
          page_title: document.title,
          page_path: window.location.pathname,
          region_id: CONFIG.region,
          ...extra
        });
      });
    });
  }

  // SCROLL DEPTH (50% once)
  function setupScroll() {
    let ticking = false;

    function check() {
      if (state.scrollTracked) return;
      const docH = Math.max(
        document.body.scrollHeight, document.documentElement.scrollHeight,
        document.body.offsetHeight, document.documentElement.offsetHeight,
        document.body.clientHeight, document.documentElement.clientHeight
      );
      const depth = (window.scrollY + window.innerHeight) / docH;
      if (depth >= CONFIG.scrollThreshold) {
        state.scrollTracked = true;
        gtag('event', 'scroll', {
          event_category: 'Engagement',
          event_label: `Scrolled ${Math.round(CONFIG.scrollThreshold * 100)}%`,
          depth_percent: Math.round(CONFIG.scrollThreshold * 100),
          page_title: document.title,
          page_path: window.location.pathname,
          region_id: CONFIG.region
        });
        window.removeEventListener('scroll', onScroll, { passive: true });
      }
    }

    function onScroll() {
      if (!ticking) {
        requestAnimationFrame(() => { check(); ticking = false; });
        ticking = true;
      }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('load', check);
  }

  // Mutations: keep applying selector rules for dynamic content
  function setupMutations() {
    const mo = new MutationObserver(muts => {
      muts.forEach(m => {
        m.addedNodes && m.addedNodes.forEach(n => {
          if (n.nodeType !== 1) return;
          applyAutoAttribution(n);
        });
      });
    });
    mo.observe(document.documentElement, { childList: true, subtree: true });
  }

  // Init
  function init() {
    applyAutoAttribution();
    //document.addEventListener('click', handleClick, true);
    setupImpressions();
    setupForms();
    setupScroll();
    setupMutations();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
