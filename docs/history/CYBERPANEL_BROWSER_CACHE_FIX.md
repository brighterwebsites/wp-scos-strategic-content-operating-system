# CyberPanel Browser Cache & Expires Headers - Complete Guide

**Server:** host.bweb1.com.au & host2.bweb1.com.au
**Issue:** PageSpeed Insights showing "None" for cache TTL
**Root Cause:** Server-level `enableExpires 0` blocks all expires headers
**Previous Issue:** Sitemap caching problems with SEOPress + MU sitemap plugin
**Current Need:** Enable browser caching WITHOUT breaking sitemaps

---

## Understanding the Problem

### Current State:
```
Server Level (/usr/local/lsws/conf/httpd_config.conf):
  enableExpires 0  ❌ BLOCKS everything

VHost Level (/usr/local/lsws/conf/vhosts/*/vhconf.conf):
  enableExpires 1  ❌ OVERRIDDEN by server level

.htaccess (WordPress root):
  LiteSpeed Cache rules  ⚠️ May or may not work

Result: PageSpeed shows "None" for cache headers
```

### Why Was It Disabled?

You previously disabled `enableExpires` because:
1. **SEOPress sitemaps** were being cached
2. Cached sitemaps got **noindex** headers added
3. **MU sitemap plugin** had odd behavior with caching

---

## Configuration Hierarchy

**Priority Order (highest to lowest):**
```
1. Server-level config (/usr/local/lsws/conf/httpd_config.conf)
   ↓ Overrides everything below

2. VHost-level config (/usr/local/lsws/conf/vhosts/*/vhconf.conf)
   ↓ Overrides .htaccess

3. .htaccess (WordPress root)
   ↓ Only applies if above levels allow it

4. LiteSpeed Cache Plugin
   ↓ Works within .htaccess context
```

**IMPORTANT:**
- Server-level `enableExpires 0` **disables ALL expires**, even if vhost/htaccess try to set them
- You MUST set server-level to `1` for any caching to work
- Then use **context rules** or **rewrite rules** to EXCLUDE sitemaps

---

## THE SAFE SOLUTION

### Strategy:
1. ✅ Enable `enableExpires 1` at **server level** (VPS-wide)
2. ✅ Set **default expires** for static assets (images, CSS, JS, fonts)
3. ✅ Add **sitemap exclusions** at server level to prevent caching
4. ✅ Let **LiteSpeed Cache plugin** handle WordPress-specific caching
5. ✅ Verify sitemaps DON'T get cached

---

## STEP 1: Enable Expires Server-Wide with Sitemap Exclusions

### Backup First

```bash
# SSH into server
ssh root@host.bweb1.com.au

# Backup current config
cp /usr/local/lsws/conf/httpd_config.conf /usr/local/lsws/conf/httpd_config.conf.backup_$(date +%Y%m%d)
```

### Edit Server Config

```bash
nano /usr/local/lsws/conf/httpd_config.conf
```

**Find the `expires` block** (search for `expires {`):

**REPLACE THIS:**
```
expires {
    enableExpires 0
}
```

**WITH THIS:**
```
expires {
    enableExpires 1
    expiresByType image/*=A31536000, text/css=A31536000, application/javascript=A31536000, application/x-javascript=A31536000, font/*=A31536000, application/font-woff=A31536000, application/font-woff2=A31536000, application/vnd.ms-fontobject=A31536000, image/svg+xml=A31536000
}
```

**What this does:**
- `A31536000` = 1 year cache (31536000 seconds)
- Only caches: images, CSS, JS, fonts
- Does NOT cache: HTML, XML, sitemaps (no expiresByType for those)

---

## STEP 2: Add Sitemap Exclusion Rules

**In the SAME file** (`httpd_config.conf`), find the `context` section or add a new one:

**Add this AFTER the expires block:**

```
context /.well-known/ {
    location /usr/local/lsws/Example/html/.well-known/
    allowBrowse 1
}

context /sitemap {
    location $DOC_ROOT/
    allowBrowse 1

    extraHeaders <<<END_extraHeaders
    unset Cache-Control
    unset Expires
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    Header set Pragma "no-cache"
    Header set Expires "0"
    END_extraHeaders
}

context ~ ".*\.(xml|xsl)$" {
    allowBrowse 1

    extraHeaders <<<END_extraHeaders
    unset Cache-Control
    unset Expires
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    Header set Pragma "no-cache"
    Header set Expires "0"
    END_extraHeaders
}
```

**What this does:**
- `/sitemap*` URLs get NO caching headers
- All `.xml` and `.xsl` files get NO caching headers
- Ensures your MU sitemap plugin and SEOPress sitemaps never cache

---

## STEP 3: Save and Restart

```bash
# Save the file (Ctrl+O, Enter, Ctrl+X)

# Test config syntax
/usr/local/lsws/bin/lswsctrl configtest

# If OK, restart OpenLiteSpeed
systemctl restart lsws

# Verify it restarted
systemctl status lsws
```

---

## STEP 4: VHost-Level Configuration (Optional Enhancement)

**For per-site control**, you can add expires at vhost level:

```bash
# Example: pawsforsupport.com.au
nano /usr/local/lsws/conf/vhosts/pawsforsupport.com.au/vhconf.conf
```

**Add this inside the vhost block:**

```
expires {
    enableExpires 1
    expiresByType image/*=A31536000, text/css=A31536000, application/javascript=A31536000, font/*=A31536000

    # Or for more aggressive caching:
    # expiresByType image/*=A63072000  # 2 years for images
}
```

**This allows you to:**
- Override server defaults per site
- Set longer/shorter cache times for specific sites
- Keep server-level as baseline, customize per site

---

## STEP 5: LiteSpeed Cache Plugin Configuration

**In WordPress Admin:**

1. **LiteSpeed Cache → Cache → Browser**
   - Browser Cache: **ON**
   - Browser Cache TTL: **31536000** (1 year)

2. **LiteSpeed Cache → Cache → Excludes**
   - Add to "Do Not Cache URIs":
     ```
     /sitemap
     /sitemap.xml
     /sitemap_index.xml
     /post-sitemap.xml
     /page-sitemap.xml
     /*.xml$
     ```

3. **LiteSpeed Cache → CDN → QUIC.cloud CDN**
   - Ensure CDN is enabled
   - QUIC.cloud will respect cache headers we just set

---

## VERIFICATION CHECKLIST

### Test 1: Check Expires Headers on Static Assets

```bash
# Test image cache header
curl -I https://pawsforsupport.com.au/wp-content/uploads/2024/01/some-image.jpg

# Look for:
# Cache-Control: max-age=31536000
# Expires: [date 1 year in future]
```

### Test 2: Check Sitemap is NOT Cached

```bash
# Test sitemap (should have no-cache)
curl -I https://pawsforsupport.com.au/sitemap.xml

# Look for:
# Cache-Control: no-cache, no-store, must-revalidate
# Pragma: no-cache
# Expires: 0
```

### Test 3: Check CSS/JS Caching

```bash
# Test CSS
curl -I https://pawsforsupport.com.au/wp-content/themes/*/style.css

# Test JS
curl -I https://pawsforsupport.com.au/wp-includes/js/jquery/jquery.min.js

# Both should show:
# Cache-Control: max-age=31536000
```

### Test 4: PageSpeed Insights

1. Go to: https://pagespeed.web.dev/
2. Test: https://pawsforsupport.com.au
3. Check **"Serve static assets with an efficient cache policy"**
4. Should now show: **1 year cache** for images/CSS/JS ✅

### Test 5: Verify Sitemaps Still Work

```bash
# Check your MU sitemap
curl https://pawsforsupport.com.au/sitemap.xml | head -20

# Should show XML content, not cached version
# Try twice to ensure it's dynamic, not cached
```

---

## TROUBLESHOOTING

### Issue 1: PageSpeed Still Shows "None"

**Possible causes:**
```bash
# Check if server config was actually updated
grep "enableExpires" /usr/local/lsws/conf/httpd_config.conf
# Should show: enableExpires 1

# Check if LiteSpeed restarted properly
systemctl status lsws

# Check actual headers being sent
curl -I https://pawsforsupport.com.au/wp-content/uploads/2024/01/test.jpg | grep -i "cache\|expires"
```

### Issue 2: Sitemaps Getting Cached Again

**If sitemaps get cached:**

```bash
# Add sitemap exclusion to vhost context
nano /usr/local/lsws/conf/vhosts/pawsforsupport.com.au/vhconf.conf

# Add:
context /sitemap {
    allowBrowse 1
    extraHeaders <<<END_extraHeaders
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    END_extraHeaders
}

# Restart
systemctl restart lsws
```

### Issue 3: LiteSpeed Cache Plugin Conflicts

**If plugin cache conflicts with server cache:**

1. **LiteSpeed Cache → Cache → Browser Cache: OFF**
2. Let server-level expires handle it
3. Keep page cache ON, just disable browser cache in plugin

### Issue 4: QUIC.cloud CDN Not Respecting Headers

**In QUIC.cloud Dashboard:**
- **CDN → Cache Settings**
- Ensure "Respect Origin Cache Headers" is **ON**
- Browser Cache TTL: **Respect Origin** (not "Override")

---

## RECOMMENDED CONFIGURATION

### For Most Sites:

**Server Level (httpd_config.conf):**
- `enableExpires 1` ✅
- Default 1 year for images/CSS/JS/fonts
- Exclude sitemaps via context rules

**VHost Level (vhconf.conf):**
- Only customize if site needs different cache times
- Otherwise inherit server defaults

**.htaccess:**
- Let LiteSpeed Cache plugin manage
- Don't add manual cache headers (conflicts)

**LiteSpeed Cache Plugin:**
- Page Cache: **ON**
- Browser Cache: **ON** (or let server handle it)
- CDN: **ON** (QUIC.cloud)
- Excludes: Add sitemap URLs

---

## TESTING COMMANDS - QUICK REFERENCE

```bash
# Test image cache
curl -I https://pawsforsupport.com.au/wp-content/uploads/2024/01/image.jpg | grep -i "cache\|expires"

# Test CSS cache
curl -I https://pawsforsupport.com.au/wp-content/themes/*/style.css | grep -i "cache\|expires"

# Test sitemap NOT cached
curl -I https://pawsforsupport.com.au/sitemap.xml | grep -i "cache\|expires"

# Test twice to verify dynamic
curl -I https://pawsforsupport.com.au/sitemap.xml
sleep 2
curl -I https://pawsforsupport.com.au/sitemap.xml

# Check server config
grep -A5 "enableExpires" /usr/local/lsws/conf/httpd_config.conf

# Restart LiteSpeed
systemctl restart lsws && systemctl status lsws
```

---

## ANSWERS TO YOUR QUESTIONS

### Q1: Safe to enable enableExpires 1 server-wide now?

**YES, with proper exclusions!** ✅

The sitemap issues were caused by:
- No exclusion rules for XML files
- SEOPress + caching = bad combo

**Now with:**
- Context rules excluding `/sitemap*` and `*.xml`
- MU plugin handling sitemaps (better than SEOPress)
- LiteSpeed Cache exclusions for sitemap URIs

**You're safe to enable it!**

---

### Q2: Do we still need sitemap exclusion rules?

**YES! Always exclude sitemaps from caching.** ✅

**Why:**
- Sitemaps are dynamic (new posts = new URLs)
- Search engines expect fresh sitemaps
- Cached sitemaps = stale data = SEO issues

**Keep the exclusions:**
- Server-level context for `*.xml` and `/sitemap*`
- Plugin-level excludes in LiteSpeed Cache
- Belt and suspenders approach

---

### Q3: Handle expires at server vs vhost vs .htaccess?

**Recommended approach:** ✅

```
Server Level (httpd_config.conf):
  - Set sensible defaults for ALL sites
  - 1 year cache for static assets
  - Exclude sitemaps globally

VHost Level (vhconf.conf):
  - Only customize for specific sites
  - Example: Client site needs 2 year cache for branding images
  - Example: Dev site needs shorter cache for testing

.htaccess:
  - Let LiteSpeed Cache plugin manage
  - Don't add manual expires (causes conflicts)
  - Plugin knows WordPress best practices
```

**Benefits:**
- Server defaults = consistent baseline
- VHost overrides = site-specific needs
- Plugin handles WordPress-specific caching

---

### Q4: Conflicts between LiteSpeed Cache, QUIC CDN, and vhost expires?

**Potential conflicts and how to avoid:** ✅

**Conflict 1: Duplicate Cache Headers**
- **Problem:** Server sets expires, plugin sets cache-control, both appear
- **Solution:** Choose one source of truth
  - **Option A:** Server handles browser cache, plugin handles page cache
  - **Option B:** Plugin handles all cache, server just enables it

**Conflict 2: QUIC.cloud Overriding Origin Headers**
- **Problem:** CDN ignores your cache headers, sets its own
- **Solution:** In QUIC.cloud dashboard:
  - Cache Settings → "Respect Origin Cache Headers" = **ON**
  - Browser Cache TTL = **Respect Origin**

**Conflict 3: Sitemap Caching at CDN Level**
- **Problem:** Even if origin doesn't cache, CDN might
- **Solution:** In QUIC.cloud:
  - Exclude `/sitemap*` and `*.xml` from CDN cache
  - Or set very short TTL (5 minutes) for XML files

**Best Practice Stack:**
```
Browser Request
  ↓
QUIC.cloud CDN (respects origin cache headers)
  ↓
OpenLiteSpeed (server-level expires enabled, sitemap excluded)
  ↓
LiteSpeed Cache Plugin (page cache, browser cache, excludes sitemaps)
  ↓
WordPress (generates content)
```

---

## FINAL CONFIGURATION SUMMARY

### Server Level (`/usr/local/lsws/conf/httpd_config.conf`)

```
expires {
    enableExpires 1
    expiresByType image/*=A31536000, text/css=A31536000, application/javascript=A31536000, application/x-javascript=A31536000, font/*=A31536000
}

context /sitemap {
    allowBrowse 1
    extraHeaders <<<END_extraHeaders
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    END_extraHeaders
}

context ~ ".*\.(xml|xsl)$" {
    allowBrowse 1
    extraHeaders <<<END_extraHeaders
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    END_extraHeaders
}
```

### VHost Level (Optional - per site customization)

```
# /usr/local/lsws/conf/vhosts/pawsforsupport.com.au/vhconf.conf

expires {
    enableExpires 1
    # Inherit server defaults or customize:
    # expiresByType image/*=A63072000  # 2 years for images
}
```

### LiteSpeed Cache Plugin

- **Cache → Browser Cache:** ON
- **Cache → Browser Cache TTL:** 31536000
- **Cache → Excludes → URI:** `/sitemap*, /*.xml$`
- **CDN → QUIC.cloud:** ON
- **CDN → CDN Mapping:** Your QUIC.cloud domain

### QUIC.cloud Dashboard

- **Cache Settings → Respect Origin Headers:** ON
- **Browser Cache TTL:** Respect Origin
- **Exclude from Cache:** `/sitemap*, *.xml` (optional)

---

## CLIENT HANDOVER - pawsforsupport.com.au

**Before sending credentials, verify:**

- [ ] Browser cache headers working (PageSpeed Insights shows 1 year)
- [ ] Sitemap.xml NOT cached (curl test shows no-cache)
- [ ] HTTPS/SSL working
- [ ] QUIC.cloud CDN active
- [ ] LiteSpeed Cache optimizations active
- [ ] All images/CSS/JS loading correctly
- [ ] WordPress admin accessible
- [ ] Client user account created with correct permissions
- [ ] 2FA enabled (if required)
- [ ] Backup schedule configured
- [ ] Monitor first 24 hours after handover

---

## POST-IMPLEMENTATION MONITORING

**Day 1 After Changes:**
```bash
# Check error logs for any issues
tail -100 /usr/local/lsws/logs/error.log | grep -i "cache\|expires"

# Verify sitemaps still accessible
curl https://pawsforsupport.com.au/sitemap.xml | head -10

# Check PageSpeed score
# https://pagespeed.web.dev/
```

**Week 1:**
- Monitor Google Search Console for indexing issues
- Check sitemap submission status
- Verify no cache-related errors in WordPress

**Month 1:**
- Review CDN bandwidth (should be optimized)
- Check PageSpeed scores (should improve)
- Verify no sitemap staleness issues

---

**Created:** 2025-11-29
**Servers:** host.bweb1.com.au, host2.bweb1.com.au
**Status:** Ready to implement
**Risk Level:** Low (with proper exclusions)
