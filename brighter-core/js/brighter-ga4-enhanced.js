/**
 * Brighter GA4 Enhanced Tracking
 * Version: 4.3.0 - Added Ad Tag Detection
 * Size Target: <10KB
 * 
 * Features:
 * - Selector-based attribution
 * - Content strategy dimensions
 * - Ad tag detection & alerting
 */

(function() {
  'use strict';
  
  // Exit if no consent or gtag not loaded
	if (document.cookie.indexOf('seopress-user-consent-accept=1') === -1 && 
    document.cookie.indexOf('seopress-user-consent-accept=true') === -1) return;
  if (typeof window.gtag !== 'function') return;
  
  const region = new URLSearchParams(location.search).get('region') || 'zone4-remote';
  
  // Get content strategy metadata (injected by PHP)
  const contentStrategy = window.brighterContentStrategy || {};
  
  // Base parameters to include in ALL events
  function getBaseParams() {
    return {
      region_id: region,
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
  
  // ============================================
  // AD TAG DETECTION SYSTEM
  // ============================================
  
  const knownAdTags = [
    // Tag Managers
    { pattern: /googletagmanager\.com\/gtm\.js/, name: 'Google Tag Manager', type: 'container' },
    { pattern: /googletagmanager\.com\/gtag\/js/, name: 'Google Ads (gtag)', type: 'ads' },
    
    // Ad Networks
    { pattern: /doubleclick\.net/, name: 'Google Display Ads', type: 'ads' },
    { pattern: /adservice\.google/, name: 'Google AdSense', type: 'ads' },
    { pattern: /googlesyndication\.com/, name: 'Google AdSense', type: 'ads' },
    { pattern: /facebook\.net\/.*\/fbevents\.js/, name: 'Meta Pixel', type: 'ads' },
    { pattern: /connect\.facebook\.net/, name: 'Meta SDK', type: 'ads' },
    { pattern: /snap\.licdn\.com/, name: 'LinkedIn Insight', type: 'ads' },
    { pattern: /static\.ads-twitter\.com/, name: 'Twitter Ads', type: 'ads' },
    { pattern: /sc-static\.net/, name: 'Snapchat Pixel', type: 'ads' },
    { pattern: /bat\.bing\.com/, name: 'Microsoft Ads', type: 'ads' },
    { pattern: /cdn\.taboola\.com/, name: 'Taboola', type: 'ads' },
    { pattern: /cdn\.outbrain\.com/, name: 'Outbrain', type: 'ads' }
  ];
  
  const detectedTags = new Set();
  let alertSent = false;
  
  function detectAdTags() {
    // Check all script tags
    document.querySelectorAll('script[src]').forEach(script => {
      const src = script.src;
      
      knownAdTags.forEach(tag => {
        if (tag.pattern.test(src) && !detectedTags.has(tag.name)) {
          detectedTags.add(tag.name);
          
          console.warn(
            '%c?? Ad Tag Detected',
            'background: #ff6b6b; color: white; padding: 4px 8px; border-radius: 3px; font-weight: bold;',
            `\n${tag.name} (${tag.type})`,
            `\nPlease coordinate with SEO before adding paid tracking tags.`,
            `\nScript: ${src}`
          );
        }
      });
    });
    
    // Fire GA4 alert event once if tags detected
    if (detectedTags.size > 0 && !alertSent) {
      alertSent = true;
      
      const payload = getBaseParams();
      payload.event_category = 'System Alert';
      payload.event_label = Array.from(detectedTags).join(', ');
      payload.ad_tag_count = detectedTags.size;
      payload.ad_tag_types = Array.from(detectedTags).join('|');
      
      gtag('event', 'call_bw_seo_gal_we_shld_wrk_2gether', payload);
      
      console.info(
        '%c?? GA4 Alert Sent',
        'background: #4CAF50; color: white; padding: 4px 8px; border-radius: 3px;',
        '\nEvent: call_bw_seo_gal_we_shld_wrk_2gether',
        '\nCheck GA4 ? Realtime ? Events'
      );
    }
  }
  
  // Run detection on load and watch for new scripts
  detectAdTags();
  
  const scriptObserver = new MutationObserver(mutations => {
    mutations.forEach(mutation => {
      mutation.addedNodes.forEach(node => {
        if (node.tagName === 'SCRIPT' && node.src) {
          detectAdTags();
        }
      });
    });
  });
  
  scriptObserver.observe(document.documentElement, {
    childList: true,
    subtree: true
  });
  
  // ============================================
  // SELECTOR ATTRIBUTION RULES
  // ============================================
  
  const RULES = [
    // Meetings
    { s: '[data-track="meeting"], .ga-cta-meeting', e: 'click_meeting', c: 'Meetings', l: 'Meeting CTA', v: 25 },
    
    // CTAs
    { s: '.ga-cta-phone, [href^="tel:"]', e: 'click_phone', c: 'Contact', l: 'Phone CTA', v: 10 },
    { s: '.ga-cta-email, [href^="mailto:"]', e: 'click_email', c: 'Contact', l: 'Email CTA', v: 10 },
    { s: '.ga-cta-menu', e: 'click_menu_cta', c: 'Quote', l: 'Menu CTA', v: 30 },
    { s: '.ga-cta-main', e: 'click_main_cta', c: 'Quote', l: 'Main CTA', v: 30 },
    { s: '.ga-cta-micro', e: 'click_micro_cta', c: 'Quote', l: 'Micro CTA', v: 15 },
    
    // Downloads & Lead Magnets
    { s: '.ga-lead_magnet', e: 'get_lead_magnet', c: 'Lead Magnet', l: 'Access LM', v: 20 },
    { s: '.ga-lead_magsection', e: 'view_lead_magnet', c: 'Lead Magnet', l: 'View LM Section', v: 5 },
    
    // Navigation
    { s: '.ga-nav-blog, a[href*="/blog"]', e: 'nav_blog', c: 'Navigation', l: 'Blog', v: 1 },
    { s: '.ga-nav-project', e: 'nav_project', c: 'Navigation', l: 'Portfolio', v: 1 },
    { s: '.ga-nav-product', e: 'click_product', c: 'Product', l: 'Product', v: 2 },
    { s: '.ga-nav-service', e: 'click_service', c: 'Service', l: 'Service', v: 2 },
    { s: '.ga-click-pricing', e: 'click_pricing_detail', c: 'Navigation', l: 'Pricing Detail Click', v: 8 },
    { s: '.ga-click-compare', e: 'click_comparison', c: 'Navigation', l: 'Comparison Page Click', v: 8 },
    
    // Trust signals
    { s: '.ga-trust-reviews', e: 'view_reviews', c: 'Trust', l: 'Reviews Viewed', v: 5 },
    { s: '.ga-trust-pricing', e: 'view_pricing', c: 'Trust', l: 'Pricing Viewed', v: 8 },
    { s: '.ga-trust-specs', e: 'view_specs', c: 'Trust', l: 'Specifications Viewed', v: 7 },
    { s: '.ga-trust-case', e: 'view_case', c: 'Trust', l: 'Case Study Excerpt Viewed', v: 6 },
    { s: '.ga-exp-video', e: 'click_video', c: 'Trust', l: 'Expert Video Viewed', v: 2 },
    
    // Page hierarchy
    { s: '.ga-hrcy-atf', e: 'view_section', c: 'Hierarchy', l: 'ATF Viewed', v: 1 },
    { s: '.ga-hrcy-phs', e: 'view_section', c: 'Hierarchy', l: 'Problem Hook Viewed', v: 2 },
    { s: '.ga-hrcy-add', e: 'view_section', c: 'Hierarchy', l: 'Authority Viewed', v: 3 },
    { s: '.ga-hrcy-tac', e: 'view_section', c: 'Hierarchy', l: 'Trust Anchors Viewed', v: 4 },
    { s: '.ga-hrcy-final', e: 'view_section', c: 'Hierarchy', l: 'Final Push Reached', v: 5 },
    { s: '.ga-hrcy-mid', e: 'view_section', c: 'Hierarchy', l: 'MidCTA Reached', v: 3 },
    { s: '.ga-hrcy-end', e: 'view_section', c: 'Hierarchy', l: 'EndCTA Reached', v: 5 },

    
    // Forms
    { s: '.ga-form', e: 'form', c: 'Forms', l: 'General Form', v: 20 },
    { s: '.ga-subscribe', e: 'subscribe', c: 'Forms', l: 'Subscribed', v: 20 },
    { s: '.ga-quote', e: 'quote_form', c: 'Forms', l: 'Quote Form', v: 30 },

    { s: '.ga-vsubscribe', e: 'view_sub_form', c: 'Forms', l: 'View Subscribe Form', v: 2 },
    { s: '.ga-vquote', e: 'view_quote_form', c: 'Forms', l: 'View Quote Form', v: 5 },
    { s: '.ga-vcontact', e: 'view_contact_form', c: 'Forms', l: 'View Contact Form', v: 4 }
  ];
  
  // Apply data attributes to matching elements
  function tag(root = document) {
    RULES.forEach(r => {
      root.querySelectorAll(r.s).forEach(el => {
        if (!el.dataset.gaEvent && r.e) el.dataset.gaEvent = r.e;
        if (!el.dataset.gaCategory) el.dataset.gaCategory = r.c;
        if (!el.dataset.gaLabel) el.dataset.gaLabel = r.l;
        if (!el.dataset.value && r.v) el.dataset.value = r.v;
        if (r.v && !el.dataset.currency) el.dataset.currency = 'AUD';
        
        // Auto-impression tracking for non-download elements
        if (r.e && !r.e.includes('download') && !el.dataset.gaImpression) {
          el.dataset.gaImpression = '';
        }
      });
    });
  }
  
  // Enhanced click handler
  document.addEventListener('click', function(e) {
    const el = e.target.closest('[data-ga-event]');
    if (!el || el.dataset.gaSkip === '1') return;
    
    const ds = el.dataset;
    const payload = getBaseParams();
    
    if (ds.gaCategory) payload.event_category = ds.gaCategory;
    if (ds.gaLabel) payload.event_label = ds.gaLabel;
    if (ds.value && !isNaN(ds.value)) {
      payload.value = parseFloat(ds.value);
      payload.currency = ds.currency || 'AUD';
    }
    
    // Optional metadata
    if (ds.meetingType) payload.meeting_type = ds.meetingType;
    if (ds.meetingLocation) payload.meeting_location = ds.meetingLocation;
    if (ds.service) payload.service_type = ds.service;
    
    gtag('event', ds.gaEvent, payload);
  }, true);
  
  // Impression tracking (IntersectionObserver)
  if ('IntersectionObserver' in window) {
    const obs = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const el = entry.target;
          const ds = el.dataset;
          
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
  
  // Form tracking
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
      const val = form.dataset.value;
      const payload = getBaseParams();
      payload.event_category = cat;
      payload.event_label = label;
      
      if (val && !isNaN(val)) {
        payload.value = parseFloat(val);
        payload.currency = form.dataset.currency || 'AUD';
      }
      
      gtag('event', 'form_submit', payload);
      gtag('event', 'generate_lead', payload);
    });
  });
  
  // Page view with content strategy
  gtag('event', 'page_view', getBaseParams());
  
  // Watch for dynamic content
  const contentObserver = new MutationObserver(mutations => {
    mutations.forEach(mutation => {
      mutation.addedNodes.forEach(node => {
        if (node.nodeType === 1) tag(node);
      });
    });
  });
  contentObserver.observe(document.body, { childList: true, subtree: true });
  
  // Initialize tagging
  tag();
})();