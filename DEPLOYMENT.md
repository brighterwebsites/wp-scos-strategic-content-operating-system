# Site Essentials - Deployment Guide

## Case Sensitivity Issue

Site Essentials uses **uppercase directory names** (Core, Modules, Views) to match PSR-4 namespace standards. On Linux servers, this is **case-sensitive**.

### The Problem

Git on Mac/Windows is case-insensitive, which can cause deployment issues:
- You might have BOTH `core/` AND `Core/` on the server
- PHP loads from the wrong directory
- Features break even though git shows correct files

### Symptoms

- ✅ Module toggle works visually
- ❌ Page refresh shows module still enabled
- ❌ Database updates work but wrong code loads
- ❌ Export shows old settings

### Solution

**After every git pull/deploy, run the cleanup script:**

```bash
cd /path/to/wp-content/mu-plugins
bash deploy-cleanup.sh
```

Or add to your deploy script:
```bash
# In your deploy script, after git pull:
bash /path/to/wp-content/mu-plugins/deploy-cleanup.sh
```

### Manual Cleanup (One-Time)

If you need to manually fix the issue right now:

```bash
# SSH into your server
cd /path/to/wp-content/mu-plugins

# Check for duplicates
ls -la site-essentials/

# If you see both "core" and "Core" (lowercase and uppercase):
rm -rf site-essentials/core
rm -rf site-essentials/modules
rm -rf site-essentials/views

# Verify only uppercase versions remain
ls -la site-essentials/
# Should see: Core, Modules, Views (uppercase only)
```

### Correct Directory Structure

```
site-essentials/
├── Core/           ← UPPERCASE
│   ├── Admin_UI.php
│   ├── Cache_Helper.php
│   ├── Module_Interface.php
│   ├── Module_Loader.php
│   └── Settings_Manager.php
├── Modules/        ← UPPERCASE
│   └── Tweaks/
│       ├── Tweaks_Module.php
│       └── views/
├── Views/          ← UPPERCASE
│   ├── module-toggle.php
│   └── settings-page.php
└── assets/
    ├── css/
    └── js/
```

### Prevention

The `deploy-cleanup.sh` script automatically removes old lowercase directories after each deployment. Make it part of your deploy process to prevent this issue from recurring.

## Verify Installation

After cleanup, verify everything works:

1. **Go to:** WordPress Admin → Settings → Site Essentials
2. **Toggle module OFF**
3. **Refresh page**
4. **Check:** Module should be OFF and settings hidden
5. **Export settings**
6. **Check JSON:** `enabled_modules` should be empty `[]`

If module toggle now works correctly after cleanup, the case sensitivity issue is resolved! ✓
