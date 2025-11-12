/**
 * Brighter GA4 Enhanced Tracking
 * Version: 5.0.0 - Lead Hierarchy System
 * Size Target: <15KB
 *
 * Features:
 * - Selector-based attribution
 * - Content strategy dimensions
 * - Ad tag detection & alerting
 * - Lead tier classification (hot/warm/cold)
 * - CTA attribution tracking
 * - Smart form type detection
 */

(function() {
  'use strict';

  // Universal consent check (same as loader)
  function hasConsent() {
    // Check for SEOPress consent cookie with multiple possible values
    const cookies = document.cookie.split(';');
    for (let cookie of cookies) {
      cookie = cookie.trim();
      // SEOPress - check for various formats
      if (cookie.startsWith('seopress-user-consent-accept=')) {
        const value = cookie.split('=')[1];
        // Accept: "1", "true", or just the presence of the cookie
        if (value === '1' || value === 'true' || value === '\'1\'' || value === '"1"') {
          return true;
        }
      }
      // Other consent plugins
      if (cookie.startsWith('cookie_notice_accepted=true')) return true;
      if (cookie.startsWith('viewed_cookie_policy=yes')) return true;
      if (cookie.startsWith('cmplz_consented_services=')) return true;
      if (cookie.startsWith('cookieyes-consent=yes')) return true;
    }

    // No consent plugin = assume consent (for sites without consent management)
    // Or if brighterGA4 was loaded (means consent was already granted in loader)
    if (window.brighterGA4 && window.brighterGA4.loaded) return true;

    return false;
  }

  // Flag to prevent double initialization
  let enhancedInitialized = false;

  function initializeEnhanced() {
    // Exit if already initialized
    if (enhancedInitialized) return;

    // Exit if no consent
    if (!hasConsent()) {
      console.log('🛑 GA4 Enhanced: Waiting for cookie consent');
      return;
    }

    // Wait for gtag to be loaded
    if (typeof window.gtag !== 'function') {
      console.log('⏳ GA4 Enhanced: Waiting for gtag.js to load...');
      setTimeout(initializeEnhanced, 100);
      return;
    }

    enhancedInitialized = true;
    console.log('✅ GA4 Enhanced v5.0.0: Lead Hierarchy System Active');
  
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
  // LEAD TIER DETECTION SYSTEM
  // ============================================

  // Map form type classes to lead data
  const CLASS_TO_TYPE = {
    // Hot tier
    'ga-quote': 'quote_request',
    'ga-business-enquiry': 'business_enquiry',
    'ga-quiz-quote': 'quiz_quote',

    // Warm tier
    'ga-contact': 'contact_form',
    'ga-quick-contact': 'quick_quote',
    'ga-meeting': 'meeting_request',

    // Cold tier
    'ga-subscribe': 'newsletter',
    'ga-lead_magnet': 'lead_magnet',
    'ga-newsletter': 'newsletter'
  };

  // Map form types to default tiers
  const FORM_TYPE_TO_TIER = {
    // Hot tier (high intent)
    'quote_request': 'hot',
    'business_enquiry': 'hot',
    'quiz_quote': 'hot',

    // Warm tier (medium intent)
    'contact_form': 'warm',
    'quick_quote': 'warm',
    'meeting_request': 'warm',

    // Cold tier (low intent)
    'newsletter': 'cold',
    'lead_magnet': 'cold'
  };

  // Form ID pattern matching (fallback detection)
  const ID_PATTERNS = [
    // Hot
    { pattern: /quote|enquiry|business/i, type: 'quote_request', tier: 'hot' },
    { pattern: /quiz.*quote/i, type: 'quiz_quote', tier: 'hot' },

    // Warm
    { pattern: /contact|inquiry/i, type: 'contact_form', tier: 'warm' },
    { pattern: /quick|express/i, type: 'quick_quote', tier: 'warm' },
    { pattern: /meeting|book|schedule/i, type: 'meeting_request', tier: 'warm' },

    // Cold
    { pattern: /subscribe|newsletter/i, type: 'newsletter', tier: 'cold' },
    { pattern: /download|magnet|guide/i, type: 'lead_magnet', tier: 'cold' }
  ];

  // Field count heuristic (last resort)
  function inferFromFieldCount(count) {
    if (count >= 7) return { tier: 'hot', type: 'quote_request' };
    if (count >= 3) return { tier: 'warm', type: 'contact_form' };
    return { tier: 'cold', type: 'newsletter' };
  }

  // Detect lead tier and type from form
  function detectFormLeadData(form) {
    const wrapper = form.closest('[class*="ga-"]') || form.parentElement;
    const classList = Array.from(wrapper?.classList || []);
    const formId = (form.id || form.getAttribute('name') || '').toLowerCase();

    let tier = null;
    let type = null;

    // 1. Check for explicit tier class (.ga-form-hot, .ga-form-warm, .ga-form-cold)
    classList.forEach(cls => {
      if (cls.startsWith('ga-form-')) {
        tier = cls.replace('ga-form-', '');
      }
    });

    // 2. Check for type class (.ga-quote, .ga-contact, etc)
    classList.forEach(cls => {
      if (CLASS_TO_TYPE[cls]) {
        type = CLASS_TO_TYPE[cls];
        // Auto-infer tier from type if not explicitly set
        if (!tier && FORM_TYPE_TO_TIER[type]) {
          tier = FORM_TYPE_TO_TIER[type];
        }
      }
    });

    // 3. Fallback: Try to detect from form ID pattern
    if (!tier || !type) {
      for (const item of ID_PATTERNS) {
        if (item.pattern.test(formId)) {
          type = type || item.type;
          tier = tier || item.tier;
          break;
        }
      }
    }

    // 4. Final fallback: Use field count heuristic
    if (!tier || !type) {
      const fieldCount = form.querySelectorAll(
        'input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled])'
      ).length;

      const inferred = inferFromFieldCount(fieldCount);
      tier = tier || inferred.tier;
      type = type || inferred.type;
    }

    return { tier, type };
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
    { s: '.ga-cta-menu', e: 'click_menu_cta', c: 'CTA', l: 'Menu CTA', v: 30 },  //Primary Conversion CTA High intent, core business outcome
    { s: '.ga-cta-main', e: 'click_main_cta', c: 'CTA', l: 'Main CTA', v: 30 },  //Primary Conversion CTA High intent, core business outcome
    { s: '.ga-cta-micro', e: 'click_micro_cta', c: 'CTA', l: 'Micro CTA', v: 15 },  //Soft Conversion  Medium intent, lead warming
    { s: '.ga-cta-assist', e: 'click_assist_cta', c: 'CTA', l: 'Assist CTA', v: 15 },  //Assisted Conversion Alternate path to same business goal
    { s: '.ga-cta-phone, [href^="tel:"]', e: 'click_phone', c: 'CTA', l: 'Phone CTA', v: 10 },
    { s: '.ga-cta-email, [href^="mailto:"]', e: 'click_email', c: 'CTA', l: 'Email CTA', v: 10 },

    
    // Downloads & Lead Magnets
    { s: '.ga-lead_magnet', e: 'get_lead_magnet', c: 'Lead Magnet', l: 'Access LM', v: 20 },
    { s: '.ga-lead_magsection', e: 'view_lead_magnet', c: 'Lead Magnet', l: 'View LM Section', v: 5 },
    
    // Navigation - Conversion Pathways
    { s: '.ga-nav-blog, a[href*="/blog"]', e: 'nav_blog', c: 'Navigation', l: 'Blog Path', v: 1 },
    { s: '.ga-nav-project', e: 'nav_project', c: 'Navigation', l: 'Portfolio Path', v: 1 },
    { s: '.ga-nav-product', e: 'nav_product', c: 'Navigation', l: 'Product Path', v: 2 },
    { s: '.ga-nav-service', e: 'nav_service', c: 'Navigation', l: 'Service Path', v: 2 },
    { s: '.ga-nav-pricing', e: 'nav_pricing_detail', c: 'Navigation', l: 'Pricing Path', v: 8 },
    { s: '.ga-nav-compare', e: 'nav_comparison', c: 'Navigation', l: 'Comparison Path', v: 8 },
    { s: '.ga-nav-faq', e: 'nav_faq', c: 'Navigation', l: 'FAQ Path', v: 8 },
    { s: '.ga-nav-path', e: 'nav_path', c: 'Navigation', l: 'Conversion Path', v: 8 }, //Navigational Engagement Low intent, user path continuation

    
    // Specific Trust signals
    { s: '.ga-trust-reviews', e: 'view_reviews', c: 'Trust', l: 'Reviews Viewed', v: 5 },
    { s: '.ga-trust-pricing', e: 'view_pricing', c: 'Trust', l: 'Pricing Viewed', v: 8 },
    { s: '.ga-trust-specs', e: 'view_specs', c: 'Trust', l: 'Specifications Viewed', v: 7 },
    { s: '.ga-trust-case', e: 'view_case', c: 'Trust', l: 'Case Study Excerpt Viewed', v: 6 },
    { s: '.ga-exp-video', e: 'click_video', c: 'Trust', l: 'Expert Video Viewed', v: 2 },
    { s: '.ga-trust-badge', e: 'view_badge', c: 'Trust', l: 'Trust Badge Viewed', v: 7 },

    // Page hierarchy - sections
    { s: '.ga-hrcy-atf', e: 'view_section', c: 'Hierarchy', l: 'ATF Viewed', v: 1 },  //Primary CTA above the fold (main goal)Measure exposure to your most important CTA (e.g. Get a Quote).High intent, core business outcome
    { s: '.ga-hrcy-phs', e: 'view_section', c: 'Hierarchy', l: 'Problem Hook Viewed', v: 2 },  //or main content section of page
    { s: '.ga-hrcy-aut', e: 'view_section', c: 'Hierarchy', l: 'Authority Viewed', v: 3 },  //Specific Authority section ie Case Studies/blog articles
    { s: '.ga-hrcy-tac', e: 'view_section', c: 'Hierarchy', l: 'Trust Anchors Viewed', v: 4 },  // Specific Trust section ie Reviews
    { s: '.ga-hrcy-faq', e: 'view_section', c: 'Hierarchy', l: 'FAQ Viewed', v: 3 },

    { s: '.ga-hrcy-mid', e: 'view_section', c: 'Hierarchy', l: 'MidCTA Reached', v: 3 },  //Secondary Soft CTA (low-friction micro-conversion) Tracks mid-page intent lead warming (e.g. Download Guide, Learn More, Ask a Question).
    { s: '.ga-hrcy-final', e: 'view_section', c: 'Hierarchy', l: 'Final Push Reached', v: 5 },  // Main CTA Repeat / Final Push CTA Reinforced Measures whether re-presenting your main offer at bottom helps lift conversions.
    { s: '.ga-hrcy-assist', e: 'view_section', c: 'Hierarchy', l: 'Assist CTA Reached', v: 5 },  // Assist CTA Section Alternate path to same business goal
    { s: '.ga-hrcy-end', e: 'view_section', c: 'Hierarchy', l: 'EndCTA Reached', v: 5 },  // Convenience / Alternate Path CTAs Captures users preferring contact by phone/chat/specialist rather than a form.

    
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
  
  // ============================================
  // CTA CONTEXT TRACKING
  // ============================================

  // Store CTA context for attribution
  function storeCTAContext(element) {
    try {
      const ctaData = {
        label: element.textContent?.trim() || element.value || 'Unknown',
        location: detectLocation(element),
        type: detectCTAType(element),
        timestamp: Date.now()
      };

      // Get existing CTA history (max 3)
      const history = JSON.parse(sessionStorage.getItem('bw_cta_history') || '[]');
      history.unshift(ctaData);

      // Keep only last 3 CTAs
      if (history.length > 3) history.length = 3;

      sessionStorage.setItem('bw_cta_history', JSON.stringify(history));
    } catch(e) {
      console.warn('Failed to store CTA context:', e);
    }
  }

  // Detect CTA location from page hierarchy
  function detectLocation(element) {
    // Check for ga-hrcy-* classes on ancestor sections
    const section = element.closest('section, [class*="ga-hrcy-"]');
    if (section) {
      const hrchyClass = Array.from(section.classList).find(c => c.startsWith('ga-hrcy-'));
      if (hrchyClass) return hrchyClass.replace('ga-hrcy-', '');
    }

    // Check if in header or footer
    if (element.closest('header')) return 'header';
    if (element.closest('footer')) return 'footer';

    // Use first class of parent section (Breakdance pattern)
    if (section && section.classList.length > 0) {
      return section.classList[0];
    }

    return 'unknown';
  }

  // Detect CTA type from classes
  function detectCTAType(element) {
    const classList = Array.from(element.classList);
    if (classList.some(c => c.includes('ga-cta-main'))) return 'main';
    if (classList.some(c => c.includes('ga-cta-micro'))) return 'micro';
    if (classList.some(c => c.includes('ga-cta-assist'))) return 'assist';
    return 'unknown';
  }

  // Get most recent CTA context
  function getLastCTAContext() {
    try {
      const history = JSON.parse(sessionStorage.getItem('bw_cta_history') || '[]');
      return history[0] || null;
    } catch(e) {
      return null;
    }
  }

  // ============================================
  // CLICK TRACKING
  // ============================================

  // Enhanced click handler
  document.addEventListener('click', function(e) {
    const el = e.target.closest('[data-ga-event]');
    if (!el || el.dataset.gaSkip === '1') return;

    // Store CTA context for later attribution
    if (el.classList.contains('ga-cta-main') ||
        el.classList.contains('ga-cta-micro') ||
        el.classList.contains('ga-cta-assist') ||
        el.tagName === 'BUTTON' ||
        el.tagName === 'A') {
      storeCTAContext(el);
    }
    
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
  
  // ============================================
  // ENHANCED FORM TRACKING WITH LEAD HIERARCHY
  // ============================================

  const started = new WeakSet();

  document.querySelectorAll('form').forEach(form => {
    const id = form.id || form.className || 'Form';
    const cat = form.dataset.gaCategory || 'Forms';
    const label = form.dataset.gaLabel || id;

    // Track form start
    form.addEventListener('input', () => {
      if (!started.has(form)) {
        const payload = getBaseParams();
        payload.event_category = cat;
        payload.event_label = label;

        gtag('event', 'form_start', payload);
        started.add(form);
      }
    }, { once: true });

    // Track form submit with lead hierarchy
    form.addEventListener('submit', () => {
      const leadData = detectFormLeadData(form);
      const lastCTA = getLastCTAContext();

      // Count visible form fields (exclude hidden)
      const fieldCount = form.querySelectorAll(
        'input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled])'
      ).length;

      // Build payload with all parameters
      const payload = getBaseParams();
      payload.event_category = cat;
      payload.event_label = label;

      // Lead classification
      payload.lead_tier = leadData.tier;
      payload.lead_type = leadData.type;

      // Form context
      payload.form_type = leadData.type;
      payload.form_fields = fieldCount;
      payload.form_id = form.id || form.getAttribute('name') || 'unknown';

      // CTA attribution (if available)
      if (lastCTA) {
        payload.cta_label = lastCTA.label;
        payload.cta_location = lastCTA.location;
        payload.cta_type = lastCTA.type;
      }

      // Element location (above/below fold)
      const wrapper = form.closest('[class*="ga-hrcy-"]');
      if (wrapper && wrapper.classList.contains('ga-hrcy-atf')) {
        payload.element_location = 'above_fold';
      } else {
        payload.element_location = 'below_fold';
      }

      // Value assignment by tier
      const val = form.dataset.value;
      if (val && !isNaN(val)) {
        payload.value = parseFloat(val);
      } else {
        // Default values by tier if not manually set
        const tierValues = { hot: 50, warm: 25, cold: 10 };
        payload.value = tierValues[leadData.tier] || 20;
      }
      payload.currency = form.dataset.currency || 'AUD';

      // Fire form_submit event (keep for tracking)
      gtag('event', 'form_submit', payload);

      // Fire generate_lead event (new unified lead event)
      gtag('event', 'generate_lead', payload);

      console.info(
        '%c📊 Lead Generated',
        'background: #4CAF50; color: white; padding: 4px 8px; border-radius: 3px;',
        `\nTier: ${leadData.tier} | Type: ${leadData.type}`,
        `\nFields: ${fieldCount} | Form: ${payload.form_id}`,
        lastCTA ? `\nLast CTA: "${lastCTA.label}" (${lastCTA.location})` : ''
      );
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
}

  // Try to initialize immediately (if consent already granted)
  initializeEnhanced();

  // Listen for SEOPress consent event (fires when user clicks "accept")
  document.addEventListener('seopress_analytics_cookies_accepted', function() {
    console.log('🍪 SEOPress consent granted - initializing enhanced tracking');
    initializeEnhanced();
  });

  // Also listen for generic consent events from other plugins
  document.addEventListener('cookie_consent_accepted', function() {
    console.log('🍪 Cookie consent granted - initializing enhanced tracking');
    initializeEnhanced();
  });

})();
