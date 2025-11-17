# Site Essentials - Refactor Plan

**Strategy:** Option 3 - Hybrid Foundation Approach
**Timeline:** 12-20 weeks (flexible, pause/resume friendly)
**Approach:** Build new alongside old, migrate module by module

---

## Table of Contents

1. [Preparation Phase](#phase-0-preparation)
2. [Foundation Phase](#phase-1-foundation-weeks-1-2)
3. [SEO Sitemaps (URGENT)](#phase-2-seo-sitemaps-urgent-weeks-3-4)
4. [High-Value Modules](#phase-3-high-value-modules-weeks-5-8)
5. [Content Strategy](#phase-4-content-strategy-weeks-9-12)
6. [Admin & UX](#phase-5-admin--ux-weeks-13-14)
7. [API & Advanced](#phase-6-api--advanced-weeks-15-16)
8. [Cleanup & Polish](#phase-7-cleanup--polish-weeks-17-20)
9. [Testing Checklist](#testing-checklist)
10. [Rollback Plan](#rollback-plan)

---

## Phase 0: Preparation

### Pre-Refactor Checklist

**✅ Audit Complete**
- [x] AUDIT.md created
- [x] STRATEGY.md created
- [x] REFACTOR_PLAN.md created

**📦 Backup Current State**
- [ ] Export all plugin code to ZIP
- [ ] Export database tables (wp_options, wp_postmeta for all BW fields)
- [ ] Document all active option names
- [ ] List all post meta keys used
- [ ] Screenshot admin pages (before state)

**🧪 Setup Test Environment**
- [ ] Fresh WordPress install for testing
- [ ] Clone of existing site for migration testing
- [ ] Staging environment for real-world testing
- [ ] Query Monitor plugin installed
- [ ] Debug logging enabled

**📝 Document Current State**
- [ ] List all enabled features per site
- [ ] Note any custom modifications
- [ ] Document expected behavior
- [ ] Create test scenarios

**Tools Needed:**
- WP All Import/Export (or custom exporter)
- Git (version control)
- Query Monitor
- Code editor with namespace refactoring support

---

## Phase 1: Foundation (Weeks 1-2)

**Goal:** Build the new modular core structure that will house all future modules

### Step 1.1: Create New Directory Structure

**Create:**
```
wp-content/mu-plugins/
├── site-essentials.php              # NEW: Tiny loader (~20 lines)
└── site-essentials/                 # NEW: Main plugin folder
    ├── core/
    │   ├── Module_Interface.php     # Interface all modules implement
    │   ├── Module_Loader.php        # Loads & manages modules
    │   ├── Settings_Manager.php     # Unified settings system
    │   ├── Cache_Helper.php         # Standardized caching
    │   └── Admin_UI.php             # Main settings page
    ├── modules/                     # Modules go here
    ├── includes/                    # Shared utilities
    │   └── helpers.php
    ├── assets/
    │   ├── css/
    │   │   └── admin.css
    │   └── js/
    │       └── admin.js
    └── README.md
```

**Keep existing:**
```
brighter-core-loader.php             # OLD: Still loading old code
brighter-core/                       # OLD: Legacy code (will phase out)
```

---

### Step 1.2: Build Core Classes

#### A. Module_Interface.php

Define what every module must implement:

```php
<?php
/**
 * Module Interface
 * All modules must implement this interface
 *
 * @package SiteEssentials\Core
 * @version 1.0.0
 */

namespace SiteEssentials\Core;

interface Module_Interface {
    /**
     * Get module ID (slug)
     * @return string
     */
    public static function get_id();

    /**
     * Get module name
     * @return string
     */
    public static function get_name();

    /**
     * Get module description
     * @return string
     */
    public static function get_description();

    /**
     * Get module tier (basic, pro, agency)
     * @return string
     */
    public static function get_tier();

    /**
     * Get module dependencies (array of module IDs)
     * @return array
     */
    public static function get_dependencies();

    /**
     * Initialize module (called only if enabled)
     * @return void
     */
    public function init();

    /**
     * Get module settings page content
     * @return string HTML
     */
    public function render_settings();

    /**
     * Get module version
     * @return string
     */
    public static function get_version();
}
```

---

#### B. Module_Loader.php

Loads modules based on settings:

```php
<?php
/**
 * Module Loader
 * Loads enabled modules and manages dependencies
 *
 * @package SiteEssentials\Core
 * @version 1.0.0
 */

namespace SiteEssentials\Core;

class Module_Loader {
    /**
     * @var array Loaded module instances
     */
    private static $modules = [];

    /**
     * @var array Available modules (slug => class)
     */
    private static $available_modules = [];

    /**
     * Register a module
     */
    public static function register($module_id, $class_name) {
        self::$available_modules[$module_id] = $class_name;
    }

    /**
     * Load enabled modules
     */
    public static function load_modules() {
        $settings = Settings_Manager::instance();

        foreach (self::$available_modules as $module_id => $class_name) {
            // Check if module is enabled
            if (!$settings->is_module_enabled($module_id)) {
                continue;
            }

            // Check dependencies
            if (!self::check_dependencies($class_name)) {
                continue;
            }

            // Load module
            self::$modules[$module_id] = new $class_name();
            self::$modules[$module_id]->init();
        }
    }

    /**
     * Check if module dependencies are met
     */
    private static function check_dependencies($class_name) {
        $dependencies = $class_name::get_dependencies();
        $settings = Settings_Manager::instance();

        foreach ($dependencies as $dep) {
            if (!$settings->is_module_enabled($dep)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get loaded module instance
     */
    public static function get_module($module_id) {
        return isset(self::$modules[$module_id]) ? self::$modules[$module_id] : null;
    }

    /**
     * Get all available modules
     */
    public static function get_available_modules() {
        return self::$available_modules;
    }

    /**
     * Get all loaded modules
     */
    public static function get_loaded_modules() {
        return self::$modules;
    }
}
```

---

#### C. Settings_Manager.php

Unified settings system:

```php
<?php
/**
 * Settings Manager
 * Centralized settings management
 *
 * @package SiteEssentials\Core
 * @version 1.0.0
 */

namespace SiteEssentials\Core;

class Settings_Manager {
    /**
     * @var Settings_Manager Singleton instance
     */
    private static $instance = null;

    /**
     * @var array Settings cache
     */
    private $settings = [];

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_settings();
    }

    /**
     * Load all settings
     */
    private function load_settings() {
        $this->settings = get_option('site_essentials_settings', [
            'enabled_modules' => [],
            'tier' => 'basic', // basic, pro, agency
        ]);
    }

    /**
     * Check if module is enabled
     */
    public function is_module_enabled($module_id) {
        return in_array($module_id, $this->settings['enabled_modules'], true);
    }

    /**
     * Enable module
     */
    public function enable_module($module_id) {
        if (!in_array($module_id, $this->settings['enabled_modules'], true)) {
            $this->settings['enabled_modules'][] = $module_id;
            $this->save_settings();
        }
    }

    /**
     * Disable module
     */
    public function disable_module($module_id) {
        $key = array_search($module_id, $this->settings['enabled_modules'], true);
        if ($key !== false) {
            unset($this->settings['enabled_modules'][$key]);
            $this->settings['enabled_modules'] = array_values($this->settings['enabled_modules']);
            $this->save_settings();
        }
    }

    /**
     * Get setting
     */
    public function get($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Set setting
     */
    public function set($key, $value) {
        $this->settings[$key] = $value;
        $this->save_settings();
    }

    /**
     * Save settings
     */
    private function save_settings() {
        update_option('site_essentials_settings', $this->settings);
    }

    /**
     * Get all settings
     */
    public function get_all() {
        return $this->settings;
    }

    /**
     * Export settings (for migration)
     */
    public function export() {
        $export = [
            'version' => '1.0.0',
            'site_essentials_core' => $this->settings,
        ];

        // Export per-module settings
        $modules = Module_Loader::get_available_modules();
        foreach ($modules as $module_id => $class_name) {
            $module_settings = get_option("site_essentials_{$module_id}", []);
            if (!empty($module_settings)) {
                $export["site_essentials_{$module_id}"] = $module_settings;
            }
        }

        return json_encode($export, JSON_PRETTY_PRINT);
    }

    /**
     * Import settings (for migration)
     */
    public function import($json) {
        $data = json_decode($json, true);

        if (!$data || !isset($data['version'])) {
            return false;
        }

        // Import core settings
        if (isset($data['site_essentials_core'])) {
            $this->settings = $data['site_essentials_core'];
            $this->save_settings();
        }

        // Import per-module settings
        foreach ($data as $key => $value) {
            if (strpos($key, 'site_essentials_') === 0 && $key !== 'site_essentials_core') {
                update_option($key, $value);
            }
        }

        return true;
    }
}
```

---

#### D. Cache_Helper.php

Standardized caching:

```php
<?php
/**
 * Cache Helper
 * Standardized caching across all modules
 *
 * @package SiteEssentials\Core
 * @version 1.0.0
 */

namespace SiteEssentials\Core;

class Cache_Helper {
    /**
     * Cache group prefix
     */
    const GROUP = 'site_essentials';

    /**
     * Default cache duration (1 hour)
     */
    const DEFAULT_DURATION = 3600;

    /**
     * Get from cache
     */
    public static function get($key, $group = '') {
        $group = self::GROUP . ($group ? "_{$group}" : '');
        return wp_cache_get($key, $group);
    }

    /**
     * Set cache
     */
    public static function set($key, $value, $expiration = null, $group = '') {
        $group = self::GROUP . ($group ? "_{$group}" : '');
        $expiration = $expiration ?? self::DEFAULT_DURATION;
        return wp_cache_set($key, $value, $group, $expiration);
    }

    /**
     * Delete from cache
     */
    public static function delete($key, $group = '') {
        $group = self::GROUP . ($group ? "_{$group}" : '');
        return wp_cache_delete($key, $group);
    }

    /**
     * Flush cache group
     */
    public static function flush($group = '') {
        $group = self::GROUP . ($group ? "_{$group}" : '');
        return wp_cache_flush_group($group);
    }

    /**
     * Remember (get or set)
     * Usage: $data = Cache_Helper::remember('my_key', function() { return expensive_query(); });
     */
    public static function remember($key, $callback, $expiration = null, $group = '') {
        $cached = self::get($key, $group);

        if ($cached !== false) {
            return $cached;
        }

        $value = $callback();
        self::set($key, $value, $expiration, $group);

        return $value;
    }
}
```

---

#### E. Admin_UI.php

Main settings page:

```php
<?php
/**
 * Admin UI
 * Main settings page for Site Essentials
 *
 * @package SiteEssentials\Core
 * @version 1.0.0
 */

namespace SiteEssentials\Core;

class Admin_UI {
    /**
     * Initialize admin UI
     */
    public function init() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Add menu page
     */
    public function add_menu_page() {
        add_options_page(
            'Site Essentials Settings',
            'Site Essentials',
            'manage_options',
            'site-essentials',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('site_essentials_settings', 'site_essentials_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'settings_page_site-essentials') {
            return;
        }

        wp_enqueue_style(
            'site-essentials-admin',
            plugins_url('assets/css/admin.css', dirname(__FILE__)),
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'site-essentials-admin',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            ['jquery'],
            '1.0.0',
            true
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings = Settings_Manager::instance();
        $modules = Module_Loader::get_available_modules();
        $enabled_modules = $settings->get('enabled_modules', []);

        include dirname(__FILE__) . '/../views/settings-page.php';
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = [];

        // Sanitize enabled modules
        if (isset($input['enabled_modules']) && is_array($input['enabled_modules'])) {
            $sanitized['enabled_modules'] = array_map('sanitize_key', $input['enabled_modules']);
        } else {
            $sanitized['enabled_modules'] = [];
        }

        // Sanitize tier
        if (isset($input['tier'])) {
            $allowed_tiers = ['basic', 'pro', 'agency'];
            $sanitized['tier'] = in_array($input['tier'], $allowed_tiers, true) ? $input['tier'] : 'basic';
        }

        return $sanitized;
    }
}
```

---

### Step 1.3: Create Main Loader (site-essentials.php)

```php
<?php
/**
 * Plugin Name: Site Essentials
 * Description: Modular site management system (MU Plugin)
 * Version: 1.0.0
 * Author: Your Agency
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

// Define constants
define('SITE_ESSENTIALS_VERSION', '1.0.0');
define('SITE_ESSENTIALS_PATH', __DIR__ . '/site-essentials/');
define('SITE_ESSENTIALS_URL', plugins_url('site-essentials/'));

// PSR-4 Autoloader
spl_autoload_register(function($class) {
    $prefix = 'SiteEssentials\\';
    $base_dir = SITE_ESSENTIALS_PATH;

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize core
add_action('init', function() {
    // Initialize settings manager
    $settings = \SiteEssentials\Core\Settings_Manager::instance();

    // Initialize admin UI
    if (is_admin()) {
        $admin_ui = new \SiteEssentials\Core\Admin_UI();
        $admin_ui->init();
    }

    // Register modules (automate this later with directory scan)
    \SiteEssentials\Core\Module_Loader::register('tweaks', \SiteEssentials\Modules\Tweaks\Tweaks_Module::class);
    // ... more modules as we migrate them

    // Load enabled modules
    \SiteEssentials\Core\Module_Loader::load_modules();
}, 5);
```

---

### Step 1.4: Migrate WordPress Tweaks (Proof of Concept)

**Goal:** Prove the modular system works

**Create:**
```
site-essentials/modules/tweaks/
├── Tweaks_Module.php
├── class-tweaks-settings.php
├── views/
│   └── settings.php
└── README.md
```

**Tweaks_Module.php:**
```php
<?php
/**
 * WordPress Tweaks Module
 *
 * @package SiteEssentials\Modules\Tweaks
 * @version 1.0.0
 */

namespace SiteEssentials\Modules\Tweaks;

use SiteEssentials\Core\Module_Interface;
use SiteEssentials\Core\Cache_Helper;

class Tweaks_Module implements Module_Interface {

    public static function get_id() {
        return 'tweaks';
    }

    public static function get_name() {
        return 'WordPress Tweaks';
    }

    public static function get_description() {
        return 'Performance and cleanup tweaks for WordPress';
    }

    public static function get_tier() {
        return 'basic';
    }

    public static function get_dependencies() {
        return []; // No dependencies
    }

    public static function get_version() {
        return '1.0.0';
    }

    /**
     * Initialize module
     */
    public function init() {
        $settings = get_option('site_essentials_tweaks', []);

        // Disable emojis
        if (isset($settings['disable_emojis']) && $settings['disable_emojis']) {
            remove_action('wp_head', 'print_emoji_detection_script', 7);
            remove_action('wp_print_styles', 'print_emoji_styles');
        }

        // Remove RSD link
        if (isset($settings['remove_rsd']) && $settings['remove_rsd']) {
            remove_action('wp_head', 'rsd_link');
        }

        // Remove Windows Live Writer link
        if (isset($settings['remove_wlw']) && $settings['remove_wlw']) {
            remove_action('wp_head', 'wlwmanifest_link');
        }

        // Remove WP version
        if (isset($settings['remove_wp_version']) && $settings['remove_wp_version']) {
            remove_action('wp_head', 'wp_generator');
        }

        // ... more tweaks
    }

    /**
     * Render settings page
     */
    public function render_settings() {
        $settings = get_option('site_essentials_tweaks', []);
        include __DIR__ . '/views/settings.php';
    }
}
```

**Test:**
1. Enable "WordPress Tweaks" module in Site Essentials settings
2. Configure individual tweaks
3. Verify tweaks apply
4. Disable module → verify tweaks don't apply

---

### Step 1.5: Create Admin Settings Page View

**Create:** `site-essentials/views/settings-page.php`

```php
<div class="wrap">
    <h1>Site Essentials Settings</h1>

    <form method="post" action="options.php">
        <?php settings_fields('site_essentials_settings'); ?>

        <h2>Module Toggles</h2>
        <p>Enable or disable modules. Disabled modules will not load any code.</p>

        <table class="widefat">
            <thead>
                <tr>
                    <th>Module</th>
                    <th>Description</th>
                    <th>Tier</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $module_id => $class_name): ?>
                    <?php
                    $name = $class_name::get_name();
                    $description = $class_name::get_description();
                    $tier = $class_name::get_tier();
                    $enabled = in_array($module_id, $enabled_modules, true);
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($name); ?></strong></td>
                        <td><?php echo esc_html($description); ?></td>
                        <td><span class="badge badge-<?php echo esc_attr($tier); ?>"><?php echo esc_html(ucfirst($tier)); ?></span></td>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="site_essentials_settings[enabled_modules][]"
                                    value="<?php echo esc_attr($module_id); ?>"
                                    <?php checked($enabled); ?>
                                />
                                <?php echo $enabled ? 'Enabled' : 'Disabled'; ?>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php submit_button(); ?>
    </form>

    <hr>

    <h2>Settings Import/Export</h2>
    <p>Export your settings to migrate to another site, or import settings from a backup.</p>

    <button type="button" class="button" id="export-settings">Export Settings</button>

    <h3>Import Settings</h3>
    <textarea id="import-settings" rows="10" cols="80" placeholder="Paste exported JSON here..."></textarea>
    <br>
    <button type="button" class="button button-primary" id="import-settings-btn">Import Settings</button>
</div>

<script>
document.getElementById('export-settings').addEventListener('click', function() {
    fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=se_export_settings')
        .then(res => res.json())
        .then(data => {
            const json = JSON.stringify(data, null, 2);
            const blob = new Blob([json], {type: 'application/json'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'site-essentials-settings.json';
            a.click();
        });
});

document.getElementById('import-settings-btn').addEventListener('click', function() {
    const json = document.getElementById('import-settings').value;

    fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=se_import_settings', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: json
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Settings imported successfully! Refreshing page...');
            location.reload();
        } else {
            alert('Import failed: ' + data.message);
        }
    });
});
</script>
```

---

### Phase 1 Testing Checklist

- [ ] New structure created successfully
- [ ] Core classes load without errors
- [ ] Settings page displays
- [ ] Can enable/disable tweaks module
- [ ] Tweaks module functions work when enabled
- [ ] Tweaks module doesn't load when disabled
- [ ] Settings save correctly
- [ ] Export settings works
- [ ] Import settings works
- [ ] Old code still works (brighter-core still loading)

---

## Phase 2: SEO Sitemaps (URGENT) (Weeks 3-4)

**Goal:** Replace SEOPress sitemap with custom implementation

### Step 2.1: Create SEO Module Structure

```
site-essentials/modules/seo/
├── SEO_Module.php
├── class-sitemap-generator.php
├── class-sitemap-renderer.php
├── class-sitemap-cache.php
├── views/
│   └── settings.php
└── README.md
```

### Step 2.2: Implement Sitemap Generator

**Key Features:**
- XML sitemap for posts/pages
- XML sitemap for custom post types
- Image sitemap
- Sitemap index
- Last modified timestamps
- Priority & frequency
- Exclude specific posts/pages

**Hooks:**
- Rewrite rule: `sitemap.xml` → custom endpoint
- `save_post` → clear sitemap cache
- `delete_post` → clear sitemap cache

### Step 2.3: Test & Deploy

**Test on problem sites:**
1. Disable SEOPress sitemap
2. Enable Site Essentials sitemap
3. Verify sitemap.xml loads
4. Verify all URLs included
5. Submit to Google Search Console
6. Monitor for errors

**Testing Checklist:**
- [ ] Sitemap generates without errors
- [ ] All post types included
- [ ] Images included
- [ ] Last modified correct
- [ ] Cache invalidates on save
- [ ] Performance acceptable (< 500ms generation)
- [ ] Google Search Console accepts sitemap
- [ ] No 404 errors

---

## Phase 3: High-Value Modules (Weeks 5-8)

### Week 5: FAQ System

**Migrate:** `bw-faq.php` (1,276 lines)

**Create:**
```
site-essentials/modules/faq/
├── FAQ_Module.php
├── class-faq-post-type.php
├── class-faq-schema.php
├── class-faq-rest-api.php
├── class-faq-gutenberg-block.php
├── views/
│   └── settings.php
└── README.md
```

**Testing:**
- [ ] FAQ post type registers
- [ ] Parent page relationship works
- [ ] Custom URLs work
- [ ] Schema markup validates
- [ ] Gutenberg block displays
- [ ] Shortcode works
- [ ] REST API endpoints work
- [ ] Analytics tracking fires

---

### Week 6: Image Optimization

**Migrate:** `image-optimisation.php` (290 lines)

**Create:**
```
site-essentials/modules/images/
├── Images_Module.php
├── class-image-resizer.php
├── class-image-sizes.php
├── class-og-image.php
├── views/
│   └── settings.php
└── README.md
```

**Testing:**
- [ ] Images resize on upload
- [ ] Image sizes register correctly
- [ ] OG image injection works
- [ ] Lazy load CSS works
- [ ] Settings save correctly

---

### Week 7: Business Info

**Migrate:** `brighter-business-info.php` (603 lines)

**Create:**
```
site-essentials/modules/business-info/
├── Business_Info_Module.php
├── class-business-data.php
├── class-shortcodes.php
├── views/
│   └── settings.php
└── README.md
```

**Testing:**
- [ ] All 27 fields save correctly
- [ ] Shortcodes work
- [ ] Cache system works
- [ ] SEOPress integration works

---

### Week 8: Analytics (GA4)

**Migrate:**
- `brighter-ga4-tracking.php` (229 lines)
- `js/brighter-ga4-enhanced.js` (616 lines)
- `bw-ga4-seeder.php` (189 lines)

**Create:**
```
site-essentials/modules/analytics/
├── Analytics_Module.php
├── class-ga4-tracker.php
├── class-lead-classifier.php
├── class-event-seeder.php
├── assets/
│   ├── ga4-enhanced.js
│   └── ga4-seeder.js
├── views/
│   └── settings.php
└── README.md
```

**Testing:**
- [ ] GA4 tracking loads
- [ ] Consent management works
- [ ] Enhanced tracking fires events
- [ ] Lead hierarchy detection works
- [ ] Ad tag detection works
- [ ] Seeder works

---

## Phase 4: Content Strategy (Weeks 9-12)

### Week 9-10: Content Strategy Fields

**Migrate:** `bw-content-strategy.php` (1,125 lines)

**Create:**
```
site-essentials/modules/content-strategy/
├── Content_Strategy_Module.php
├── class-content-fields.php
├── class-admin-columns.php
├── class-quick-edit.php
├── class-meta-boxes.php
├── class-ga4-integration.php
├── assets/
│   ├── admin.js
│   └── admin.css
├── views/
│   └── settings.php
└── README.md
```

---

### Week 11: ALTC System

**Migrate:**
- `class-altc-taxonomies.php` (194 lines)
- `class-altc-meta-boxes.php`
- `class-altc-admin-columns.php`
- `class-altc-admin-pages.php`
- `class-altc-ga4-integration.php`
- `class-altc-migration.php`

**Create:**
```
site-essentials/modules/altc/
├── ALTC_Module.php
├── class-altc-taxonomies.php
├── class-altc-meta-boxes.php
├── class-altc-admin-columns.php
├── class-altc-dashboard.php
├── class-altc-migration.php
├── views/
│   ├── settings.php
│   └── dashboard.php
└── README.md
```

---

### Week 12: Content Analysis

**Migrate:**
- `class-content-analysis.php` (379 lines)
- `class-content-stats-page.php`
- `class-content-analysis-seeder.php`

**Create:**
```
site-essentials/modules/content-analysis/
├── Content_Analysis_Module.php
├── class-link-analyzer.php
├── class-stats-calculator.php
├── class-content-scanner.php
├── class-stats-dashboard.php
├── views/
│   └── settings.php
└── README.md
```

---

## Phase 5: Admin & UX (Weeks 13-14)

### Week 13: Support Portal

**Migrate:**
- `brighter-support.php` (399 lines)
- `brighter-admin-branding.php`
- `css/admin-support.css`

**Create:**
```
site-essentials/modules/support/
├── Support_Module.php
├── class-support-page.php
├── class-admin-branding.php
├── assets/
│   ├── admin.css
│   └── brighter-logo.png
├── views/
│   ├── support-page.php
│   └── tabs/
│       ├── support-info.php
│       ├── manuals.php
│       ├── analytics.php
│       ├── business-info.php
│       ├── optimization.php
│       └── tweaks.php
└── README.md
```

---

### Week 14: Login & Branding

**Migrate:**
- `login-styling.php`
- `custom-wpemail.php`

**Create:**
```
site-essentials/modules/branding/
├── Branding_Module.php
├── class-login-customizer.php
├── class-email-customizer.php
├── assets/
│   └── login.css
├── views/
│   └── settings.php
└── README.md
```

---

## Phase 6: API & Advanced (Weeks 15-16)

### Week 15: REST API System

**Migrate:**
- `api/class-brighter-api.php` (162 lines)
- `api/class-brighter-api-auth.php`
- `api/class-brighter-api-endpoints.php`
- `api/class-brighter-api-admin.php`

**Create:**
```
site-essentials/modules/api/
├── API_Module.php
├── class-api-auth.php
├── class-api-endpoints.php
├── class-api-admin.php
├── views/
│   └── settings.php
└── README.md
```

---

### Week 16: Custom Post Types

**Migrate:** `bw-custposts.php`

**Create:**
```
site-essentials/modules/post-types/
├── Post_Types_Module.php
├── class-portfolio-post-type.php
├── class-news-post-type.php
├── class-kb-post-type.php
├── views/
│   └── settings.php
└── README.md
```

---

## Phase 7: Cleanup & Polish (Weeks 17-20)

### Week 17: Remove Old Code

**Once all modules migrated and tested:**

1. **Backup old code**
   ```bash
   mv brighter-core/ brighter-core-BACKUP-2025-XX-XX/
   mv brighter-core-loader.php brighter-core-loader.php.backup
   ```

2. **Verify nothing breaks**
   - Test all features
   - Check error logs
   - Review Query Monitor

3. **Delete old code**
   ```bash
   rm -rf brighter-core-BACKUP-2025-XX-XX/
   rm brighter-core-loader.php.backup
   ```

---

### Week 18: Documentation

**Create:**
- User manual (how to use each module)
- Developer docs (how to extend, create modules)
- Migration guide (for existing sites)
- Troubleshooting guide

**Files:**
```
site-essentials/
├── README.md                    # Main readme
├── docs/
│   ├── user-guide.md
│   ├── developer-guide.md
│   ├── migration-guide.md
│   ├── troubleshooting.md
│   └── changelog.md
└── .claude/
    └── claude-context.md        # For Claude Code AI assistant
```

---

### Week 19: Performance Optimization

**Tasks:**
- [ ] Profile query counts (Query Monitor)
- [ ] Optimize database queries
- [ ] Implement caching where needed
- [ ] Lazy-load admin scripts
- [ ] Minify assets
- [ ] Test on various hosting environments

**Benchmarks:**
- Admin page load: < 500ms
- Frontend overhead: < 50ms
- Query count increase: < 5 queries
- Memory usage: < 10MB increase

---

### Week 20: Final Testing & Launch Prep

**Final Checklist:**
- [ ] All modules functional
- [ ] All old code removed
- [ ] Documentation complete
- [ ] Settings import/export works
- [ ] Performance benchmarks met
- [ ] Security review complete
- [ ] Error logging clean
- [ ] Tested on fresh install
- [ ] Tested on migrated site
- [ ] Tested on staging
- [ ] Ready for production rollout

---

## Testing Checklist

### Per-Module Testing

**Before marking module as "done", test:**

1. **Functionality**
   - [ ] All features work as before
   - [ ] Settings save correctly
   - [ ] Data persists correctly
   - [ ] No PHP errors
   - [ ] No JavaScript errors

2. **Integration**
   - [ ] Works with other modules
   - [ ] Dependency checking works
   - [ ] No conflicts

3. **Performance**
   - [ ] Query count acceptable
   - [ ] Page load time acceptable
   - [ ] Caching works

4. **Admin UX**
   - [ ] Settings page renders
   - [ ] Forms submit correctly
   - [ ] Help text clear
   - [ ] Responsive design

5. **Toggle Behavior**
   - [ ] Module enables correctly
   - [ ] Module disables correctly
   - [ ] No code loads when disabled
   - [ ] No errors when disabled

---

### Site-Wide Testing

**Test entire plugin on:**

1. **Fresh WordPress Install**
   - [ ] Install plugin
   - [ ] Enable modules one by one
   - [ ] Configure settings
   - [ ] Test all features

2. **Cloned Existing Site**
   - [ ] Migrate settings
   - [ ] Verify all data intact
   - [ ] Test all features
   - [ ] Compare before/after

3. **Staging Environment**
   - [ ] Deploy to staging
   - [ ] Real-world testing
   - [ ] Check error logs
   - [ ] Performance testing

4. **Production (Gradual Rollout)**
   - [ ] Deploy to 1 low-traffic site first
   - [ ] Monitor for 1 week
   - [ ] Deploy to more sites gradually
   - [ ] Monitor error rates

---

## Rollback Plan

**If something breaks, rollback immediately:**

### Quick Rollback (< 5 minutes)

**If new plugin causes issues:**

1. Rename folder:
   ```bash
   mv mu-plugins/site-essentials/ mu-plugins/site-essentials-DISABLED/
   ```

2. Restore old code:
   ```bash
   mv mu-plugins/brighter-core-BACKUP/ mu-plugins/brighter-core/
   mv mu-plugins/brighter-core-loader.php.backup mu-plugins/brighter-core-loader.php
   ```

3. Clear all caches

4. Test site

---

### Full Rollback (< 30 minutes)

**If issues persist:**

1. **Database restore** (restore wp_options, wp_postmeta)
   - Import backup of wp_options
   - Import backup of wp_postmeta

2. **Code restore** (already done in quick rollback)

3. **Clear all caches** (object cache, page cache, CDN cache)

4. **Verify functionality**

---

### Prevention

**To minimize rollback risk:**

1. **Always backup before changes**
   - Database export
   - Code ZIP
   - Settings export JSON

2. **Test thoroughly before production**
   - Fresh install test
   - Clone test
   - Staging test

3. **Gradual rollout**
   - 1 site → 5 sites → 10 sites → all sites

4. **Monitor after deployment**
   - Error logs
   - Query Monitor
   - User reports

---

## Migration Checklist (Per Site)

**When migrating an existing site to new plugin:**

### Pre-Migration

- [ ] Backup database
- [ ] Backup wp-content
- [ ] Export settings (JSON)
- [ ] Screenshot admin pages
- [ ] Document enabled features
- [ ] Test on clone first

### Migration

- [ ] Upload new plugin files
- [ ] Enable new plugin
- [ ] Import settings
- [ ] Enable modules one by one
- [ ] Test each feature

### Post-Migration

- [ ] Verify all features work
- [ ] Check error logs
- [ ] Test analytics tracking
- [ ] Test forms
- [ ] Test admin pages
- [ ] Monitor for 24 hours

### Cleanup (After 1 Week)

- [ ] Remove old plugin code
- [ ] Delete old settings (optional)
- [ ] Clear old transients
- [ ] Final verification

---

## Common Issues & Solutions

### Issue: Module won't enable
**Solution:**
- Check dependencies
- Check for PHP errors in error log
- Verify file permissions

### Issue: Settings won't save
**Solution:**
- Check nonce verification
- Check capability checks
- Check sanitization callbacks

### Issue: Old and new code conflict
**Solution:**
- Ensure old code is loading before new
- Use feature flags to control which runs
- Disable old code incrementally

### Issue: Performance degradation
**Solution:**
- Profile with Query Monitor
- Check for N+1 queries
- Verify caching is working
- Disable modules one by one to isolate

### Issue: Missing data after migration
**Solution:**
- Check post meta key mappings
- Verify option name mappings
- Run migration script again
- Restore from backup if needed

---

## Success Criteria

**Plugin is ready for production when:**

✅ **Functionality**
- All existing features work
- No data loss
- Settings preserved

✅ **Performance**
- Query count same or lower
- Page load time same or faster
- Memory usage reasonable

✅ **Code Quality**
- PSR-4 namespacing
- Follows WordPress coding standards
- Well-documented
- Security best practices

✅ **User Experience**
- Easy to configure
- Clear settings
- Help available
- Intuitive UI

✅ **Maintainability**
- Modular architecture
- Easy to extend
- Well-tested
- Good documentation

---

## Next Steps After Refactor

**Once refactor is complete, consider:**

1. **Automated Testing**
   - PHPUnit tests for core classes
   - Integration tests for modules
   - End-to-end tests for critical paths

2. **CI/CD Pipeline**
   - Automated testing on commit
   - Automated deployment to staging
   - Version tagging

3. **Premium Product Development**
   - Licensing system
   - Update system
   - Support portal
   - Marketing site

4. **Module Extraction**
   - Package FAQ system as standalone
   - Package analytics as standalone
   - Sell on WordPress.org or own site

5. **Community**
   - Open source some modules?
   - GitHub repository
   - Documentation site

---

**End of Refactor Plan**

*This plan is flexible - adjust timeline and priorities as needed. The key is the modular approach, not the exact timeline.*
