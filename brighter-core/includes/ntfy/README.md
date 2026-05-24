# ntfy Notification System - Complete Setup Guide

**Version:** 1.0.0  
**Status:** ✅ Phase 1 & 2 Complete - Production Ready  
**Created:** 2026-02-22

---

## 📦 What's Included

### ✅ Core System (Phase 1)
- **ntfy Client** - HTTP API wrapper with Basic Auth
- **Main Controller** - Manages monitors and configuration
- **Admin UI** - Monitoring tab in Agency Settings

### ✅ Implemented Monitors (Phase 2)
1. **SMTP Monitor** 🟢 ACTIVE - Catches email failures immediately
2. **Downtime Monitor** 🟢 ACTIVE - 5-minute health checks (HTTP, DB, filesystem)
3. **robots.txt Monitor** 🟢 ACTIVE - Daily verification
4. **Sitemap Monitor** 🟢 ACTIVE - Daily verification  
5. **Form Monitor** 🟡 OPT-IN - Breakdance, CF7, Gravity Forms support

### ⏳ Stubbed Monitors (Phase 3)
6. **WP Cron Monitor** 🟡 OPT-IN / TODO - Off by default; needs missed event detection logic (`check_missed_events()`). Set `NTFY_MONITOR_CRON` true only when testing the stub.

---

## ⚙️ Installation

### 1. Add to wp-config.php

```php
// ntfy Notification System
define('NTFY_ENABLED', true);
define('NTFY_SERVER_URL', 'https://ntfy.bweb1.com.au');
define('NTFY_USERNAME', 'vanessa');
define('NTFY_PASSWORD', 'your-password-here');

// Optional: Custom topic prefix (default: 'bw-agency')
define('NTFY_TOPIC_PREFIX', 'bw-agency');

// Optional: Disable specific monitors
define('NTFY_MONITOR_SMTP', true);      // Email failures (RECOMMENDED)
define('NTFY_MONITOR_ROBOTS', true);    // robots.txt checks (RECOMMENDED)
define('NTFY_MONITOR_SITEMAP', true);   // Sitemap checks (RECOMMENDED)
define('NTFY_MONITOR_DOWNTIME', true);  // Site health (~5 min checks)
define('NTFY_MONITOR_CRON', false);     // WP Cron stub (OPT-IN until implemented)
define('NTFY_MONITOR_FORMS', false);    // Form submissions (OPT-IN)
```

### 2. Deploy to Site

```bash
git pull origin intent-schema
```

### 3. Subscribe to Topics in ntfy

Open your ntfy app/web UI and subscribe to:

- `bw-agency-smtp` - Email failures
- `bw-agency-robots` - robots.txt errors
- `bw-agency-sitemap` - Sitemap issues
- `{site-name}-forms` - Form submissions (if enabled)

**Authentication:** Use the same username/password from wp-config.php

---

## 🎯 Topic Reference

| Monitor | Topic Pattern | When It Fires | Priority |
|---------|---------------|---------------|----------|
| SMTP | `{prefix}-smtp` | Email send failure | 🔴 Urgent |
| Downtime | `{prefix}-downtime` | Health check issues | 🟠 High |
| robots.txt | `{prefix}-robots` | robots.txt returns error | 🟡 Default |
| Sitemap | `{prefix}-sitemap` | sitemap.xml returns error | 🟡 Default |
| Forms | `{site-slug}-forms` | Form submission | 🟡 Default |

**Default prefix:** `bw-agency`  
**Example:** `bw-agency-smtp`

---

## 🎨 Admin Interface

**Location:** `wp-admin/admin.php?page=brighter_support&tab=monitoring`

**Access:** Restricted to `@brighterwebsites.com.au` emails only

**Features:**
- ✅ Connection status indicator
- ✅ Active monitors list with topics
- ✅ Send test notification button
- ✅ Quick reference for wp-config constants

---

## 🧪 Testing

### Test Connection
Visit: `yourdomain.com/?test_ntfy=1` (as admin)

### Test via Admin UI
1. Go to **Support Hub → Monitoring**
2. Click **"Send Test Notification"** button
3. Check your ntfy app for message on `bw-test` topic

### Test Downtime Monitor
Temporarily cause an issue:
- **Slow response:** Install a plugin that slows down the site
- **Database issue:** Won't be easy to test without breaking things
- **Filesystem:** Temporarily change uploads directory permissions

Or wait 5 minutes for the cron to run and check debug.log

### Test SMTP Monitor
Temporarily break SMTP settings and try sending an email

### Test robots.txt Monitor
Temporarily rename `robots.txt` file and wait for daily cron

### Test Sitemap Monitor
Temporarily rename `sitemap.xml` file and wait for daily cron

### Test Form Monitor
Enable with `NTFY_MONITOR_FORMS` and submit a form

---

## 📊 Monitor Status

### 🟢 Production Ready
- **SMTP Monitor** - Hooks into `wp_mail_failed`, rate limited to 1 alert per 5 min
- **Downtime Monitor** - 5-minute WP Cron health checks: HTTP response, DB connectivity, filesystem write
- **robots.txt Monitor** - Daily cron check, alerts on non-200 status
- **Sitemap Monitor** - Daily cron check, validates XML content type
- **Form Monitor** - Supports Breakdance, CF7, Gravity Forms

### 🟡 Needs Implementation (opt-in)
- **WP Cron Monitor** - Stubbed, off unless `NTFY_MONITOR_CRON` is true; needs missed event detection logic

---

## 🔧 Rate Limiting

All monitors implement rate limiting to prevent notification spam:

| Monitor | Rate Limit |
|---------|------------|
| SMTP | 1 alert per 5 minutes |
| Downtime | 1 alert per 15 minutes |
| robots.txt | 1 alert per 24 hours |
| Sitemap | 1 alert per 24 hours |
| Forms | No limit (can be noisy) |

---

## 🚀 Next Steps

### Phase 3: Implement Remaining Monitors

**Downtime Monitor:**
- Challenge: Internal checks won't detect PHP/Apache failures
- Solution: Needs external monitoring service or cross-site ping
- Options: UptimeRobot API integration, peer site monitoring

**WP Cron Monitor:**
- Challenge: WordPress has no built-in "missed event" hook
- Solution: Periodic check of `_get_cron_array()` for overdue events
- Consider: Only monitor critical cron jobs (backups, updates)

---

## 📝 Notes

- All monitors respect `NTFY_ENABLED` master toggle
- Individual monitors can be disabled via wp-config constants
- Notifications include clickable links back to admin
- All errors logged to debug.log when `WP_DEBUG_LOG` enabled
- Test file (`test-ntfy.php`) can be removed after confirming setup

---

## 🐛 Troubleshooting

**No notifications arriving:**
1. Check wp-config constants are defined
2. Verify ntfy credentials are correct
3. Check debug.log for errors
4. Test connection via `?test_ntfy=1`

**Admin tab not showing:**
1. Verify you're logged in with `@brighterwebsites.com.au` email
2. Check Agency Settings tab exists
3. Clear browser cache

**Monitors not firing:**
1. Check individual monitor constants
2. Verify WP Cron is running (`wp cron event list`)
3. Check rate limiting hasn't suppressed alerts

---

**Need Help?** Contact support@brighterwebsites.com.au
