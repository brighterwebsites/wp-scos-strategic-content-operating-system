# How to Find Logs in CyberPanel + OpenLiteSpeed

## FIXED: Fatal Error in Settings_Manager.php

**Commit:** `1d7ac75` - **Deploy this first!**

The fatal error you saw was caused by a safety check trying to write to the database during WP-CLI execution. This is now fixed.

---

## Finding Error Logs in CyberPanel

### Method 1: Check OpenLiteSpeed Virtual Host Logs

```bash
# Find your site's virtual host log directory
ls -la /usr/local/lsws/

# Look for logs directory
find /usr/local/lsws -name "*error*log" 2>/dev/null

# Check site-specific logs (replace with your domain)
tail -f /usr/local/lsws/logs/error.log

# Or check all recent errors
grep -r "Settings_Manager" /usr/local/lsws/logs/ 2>/dev/null
```

### Method 2: Create WordPress debug.log

Since `WP_DEBUG_LOG` is enabled but no file exists, create it manually:

```bash
# SSH into test.brighterwebsites.com.au
cd /home/test.brighterwebsites.com.au/public_html/wp-content/

# Create debug.log with proper permissions
touch debug.log
chmod 664 debug.log
chown [your-user]:  [your-group] debug.log

# Watch logs in real-time
tail -f debug.log
```

### Method 3: Use CyberPanel Interface

1. Log into CyberPanel web interface
2. Go to **Websites** → **List Websites**
3. Click on `test.brighterwebsites.com.au`
4. Click **Error Logs** or **Access Logs**
5. View logs in browser

### Method 4: Check System Logs

```bash
# Check system-wide error logs
journalctl -xe | grep -i "site-essentials"

# Or check syslog
tail -f /var/log/messages | grep -i error
```

---

## How to Test After Deploying `1d7ac75`

### Step 1: Deploy Latest Code

```bash
# Run your deploy script
bash /root/scripts/deploy-test.sh

# OR manually deploy:
cd /home/test.brighterwebsites.com.au/public_html/wp-content/mu-plugins/
# Download and extract latest code...
```

### Step 2: Verify WP-CLI Works Again

```bash
cd /home/test.brighterwebsites.com.au/public_html

# This should work now (no fatal error)
wp option get site_essentials_core --format=json --allow-root

# Check current enabled modules
wp option get site_essentials_core --format=json --allow-root | grep enabled_modules
```

### Step 3: Start Watching Logs

**Terminal 1 - Watch debug.log:**
```bash
tail -f /home/test.brighterwebsites.com.au/public_html/wp-content/debug.log
```

**Terminal 2 - Perform toggle test (in browser):**
1. Go to Site Essentials → Settings
2. Toggle SEO module OFF
3. Watch Terminal 1 for log output

---

## What to Look For in Logs

After deploying `1d7ac75` and toggling a module, you should see:

```
========== AJAX TOGGLE START ==========
[Admin_UI] ajax_toggle_module() called
[Admin_UI] POST data: {"action":"site_essentials_toggle_module","module_id":"seo","enabled":false,...}
[Admin_UI] Module: seo, Action: DISABLE
[Admin_UI] State BEFORE toggle - Memory: ["tweaks","seo"]
[Admin_UI] State BEFORE toggle - DB: ["tweaks","seo"]
[Admin_UI] Calling disable_module(seo)

[Settings_Manager] disable_module() called for: seo
[Settings_Manager] Current enabled_modules BEFORE: ["tweaks","seo"]
[Settings_Manager] Removing seo from enabled_modules: ["tweaks"]
[Settings_Manager] update_option() result: SUCCESS   ← KEY!
[Settings_Manager] DB verification after disable: ["tweaks"]

[Admin_UI] State AFTER toggle - Memory: ["tweaks"]
[Admin_UI] State AFTER toggle - DB (get_option): ["tweaks"]
[Admin_UI] State AFTER toggle - DB (direct query): ["tweaks"]
[Admin_UI] Verification: PASSED   ← KEY!
[Admin_UI] Expected: seo should be DISABLED
[Admin_UI] Actual in DB: seo is DISABLED
[Admin_UI] Cache mismatch: NO
========== AJAX TOGGLE END ==========
```

**Then on page reload:**

```
========== MODULE_LOADER START ==========
[Module_Loader] Enabled modules from settings: ["tweaks"]   ← Should NOT have "seo"!
[Module_Loader] Available modules: ["tweaks","seo"]
[Module_Loader] Checking tweaks: ENABLED
[Module_Loader] Loading tweaks - all checks passed
[Module_Loader] Checking seo: DISABLED   ← Should say DISABLED!
[Module_Loader] Skipping seo - not enabled
[Module_Loader] Loaded modules: ["tweaks"]
========== MODULE_LOADER END ==========
```

---

## Troubleshooting

### If debug.log Still Doesn't Appear

Check wp-config.php has these lines:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );  // Don't show errors on screen
@ini_set( 'display_errors', 0 );
```

### If Logs Show "update_option() result: FAILED"

Check database permissions:

```bash
wp db check --allow-root
```

### If Module Still Reverts After Toggle

Check the logs to see where the state is lost:
1. Does "update_option() result: SUCCESS"? → YES = DB write worked
2. Does "Verification: PASSED"? → YES = DB contains correct data after AJAX
3. What does Module_Loader see on reload? → This tells us if something overwrote it

---

## Quick Reference: Log Locations by System

| System | Log Location |
|--------|--------------|
| **OpenLiteSpeed** | `/usr/local/lsws/logs/error.log` |
| **WordPress** | `/path/to/wp-content/debug.log` |
| **CyberPanel** | Web interface → Websites → Error Logs |
| **System** | `/var/log/messages` or `journalctl -xe` |
| **PHP** | Configured in `php.ini` - check with `php -i \| grep error_log` |

---

## Next Steps

1. ✅ Deploy commit `1d7ac75` (fixes fatal error)
2. ✅ Verify WP-CLI works: `wp option get site_essentials_core --allow-root`
3. ✅ Create debug.log if it doesn't exist
4. ✅ Open terminal and run `tail -f debug.log`
5. ✅ Toggle a module in browser
6. ✅ Read the logs to see exactly what's happening
7. ✅ Paste relevant log section here for analysis

The logs will reveal the exact point where the toggle state is lost!
