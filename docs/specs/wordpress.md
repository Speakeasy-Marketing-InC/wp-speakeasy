# Speakeasy Automation Suite - WordPress Plugin Specification

**Version:** 1.0.0
**Date:** June 20, 2026
**Status:** 🔵 Specification Phase

---

## Executive Summary

A modular WordPress plugin that enables REST API automation for 38 law firm websites. The plugin provides:
1. Force-enabled Application Passwords (bypasses theme/security restrictions)
2. LAP (Local Area Page) meta field exposure to REST API
3. Auto-update mechanism via GitHub releases
4. Centralized reporting to Speakeasy backend

---

## Problem Statement

### Current Issues
1. **Application Passwords Disabled** - Some sites have `functions.php` code disabling WordPress Application Passwords
2. **Meta Fields Not REST-Accessible** - LAP custom fields (Meta Box plugin) not exposed to REST API
3. **Manual Deployment** - 38 sites require individual configuration
4. **No Update Mechanism** - Changes require re-deployment to all sites

### Solution
Single WordPress plugin with modular architecture:
- Self-contained modules for each automation need
- Auto-update via GitHub releases
- Zero user interaction required after initial install
- Reports status to Speakeasy backend

---

## Technical Requirements

### WordPress Environment
- **WordPress Version:** 5.6+ (Application Passwords introduced in 5.6)
- **PHP Version:** 7.4+
- **Required Plugins:** Meta Box (already installed on all sites)
- **Hosting:** Varies by site (shared hosting, VPS, managed WordPress)

### Compatibility
- ✅ Must work with existing themes (various custom themes)
- ✅ Must not conflict with security plugins (Wordfence, etc.)
- ✅ Must survive theme updates
- ✅ Must work on sites with ModSecurity enabled

### Performance
- ⚡ Minimal overhead (<0.1s page load impact)
- 📦 Small footprint (<500KB total)
- 🔄 Update checks max once per 12 hours

---

## Plugin Architecture

### File Structure

```
speakeasy-automation/
├── speakeasy-automation.php          # Main plugin file
├── README.md                         # User-facing documentation
├── CHANGELOG.md                      # Version history
├── includes/
│   ├── class-module-manager.php      # Module loader & registry
│   ├── class-auto-updater.php        # GitHub update checker
│   ├── class-api-reporter.php        # Reports to Speakeasy API
│   └── interface-module.php          # Module interface
├── modules/
│   ├── app-passwords/
│   │   ├── class-app-passwords-module.php
│   │   └── README.md
│   ├── lap-meta/
│   │   ├── class-lap-meta-module.php
│   │   ├── schemas/
│   │   │   ├── localareapage.php     # Generated from scanner
│   │   │   └── [other-templates].php
│   │   └── README.md
│   └── [future-modules]/
├── admin/
│   ├── class-admin-page.php          # Settings UI
│   ├── views/
│   │   ├── dashboard.php
│   │   └── settings.php
│   └── assets/
│       ├── css/
│       │   └── admin.css
│       └── js/
│           └── admin.js
├── vendor/
│   └── plugin-update-checker/        # GitHub updater library
└── assets/
    └── icon.png                       # Plugin icon
```

---

## Core Components

### 1. Main Plugin File

**File:** `speakeasy-automation.php`

**Responsibilities:**
- Plugin header (name, version, description)
- Define constants (`SPEAKEASY_VERSION`, `SPEAKEASY_PATH`, `SPEAKEASY_URL`)
- Load dependencies (autoloader, module manager)
- Initialize auto-updater
- Activation/deactivation hooks

**Key Code:**
```php
<?php
/**
 * Plugin Name: Speakeasy Automation Suite
 * Plugin URI: https://github.com/speakeasy/wordpress-automation
 * Description: Automation toolkit for Speakeasy-managed WordPress sites. Enables Application Passwords, exposes LAP meta fields to REST API, and provides auto-update capability.
 * Version: 1.0.0
 * Author: Speakeasy
 * Author URI: https://speakeasy.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * GitHub Plugin URI: speakeasy/wordpress-automation
 */

if (!defined('ABSPATH')) exit;

define('SPEAKEASY_VERSION', '1.0.0');
define('SPEAKEASY_PATH', plugin_dir_path(__FILE__));
define('SPEAKEASY_URL', plugin_dir_url(__FILE__));

// Autoloader
require_once SPEAKEASY_PATH . 'includes/class-module-manager.php';
require_once SPEAKEASY_PATH . 'includes/class-auto-updater.php';
require_once SPEAKEASY_PATH . 'includes/class-api-reporter.php';

// Initialize
function speakeasy_automation_init() {
    $manager = Speakeasy_Module_Manager::instance();

    // Register modules
    $manager->register_module('app-passwords', new Speakeasy_App_Passwords_Module());
    $manager->register_module('lap-meta', new Speakeasy_LAP_Meta_Module());

    // Init enabled modules
    $manager->init_modules();

    // Init auto-updater
    if (class_exists('Speakeasy_Auto_Updater')) {
        new Speakeasy_Auto_Updater();
    }

    // Init API reporter
    if (class_exists('Speakeasy_API_Reporter')) {
        new Speakeasy_API_Reporter();
    }
}
add_action('plugins_loaded', 'speakeasy_automation_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Report activation to Speakeasy API
    wp_remote_post('https://api.speakeasy.com/wp-plugin/activation', [
        'body' => [
            'site' => home_url(),
            'version' => SPEAKEASY_VERSION,
            'timestamp' => current_time('mysql')
        ]
    ]);
});
```

---

### 2. Module Interface

**File:** `includes/interface-module.php`

```php
<?php
interface Speakeasy_Module {
    /**
     * Get module name (human-readable)
     */
    public function get_name(): string;

    /**
     * Get module description
     */
    public function get_description(): string;

    /**
     * Get module version
     */
    public function get_version(): string;

    /**
     * Initialize module (register hooks)
     */
    public function init(): void;

    /**
     * Check if module is enabled
     */
    public function is_enabled(): bool;

    /**
     * Module priority (lower = earlier execution)
     */
    public function get_priority(): int;
}
```

---

### 3. Module Manager

**File:** `includes/class-module-manager.php`

**Responsibilities:**
- Singleton pattern (one instance per request)
- Module registration
- Module initialization (respects priority)
- Enable/disable modules
- Module status tracking

**Key Features:**
```php
class Speakeasy_Module_Manager {
    private static $instance = null;
    private array $modules = [];
    private array $enabled_modules = [];

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register_module(string $id, Speakeasy_Module $module): void;
    public function init_modules(): void;
    public function get_module(string $id): ?Speakeasy_Module;
    public function get_all_modules(): array;
    public function is_module_enabled(string $id): bool;
    public function enable_module(string $id): bool;
    public function disable_module(string $id): bool;
}
```

---

### 4. Auto-Updater

**File:** `includes/class-auto-updater.php`

**Responsibilities:**
- Check GitHub releases every 12 hours
- Download and install updates automatically
- Report update status to Speakeasy API
- Handle update failures gracefully

**Integration:**
```php
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class Speakeasy_Auto_Updater {
    private $update_checker;

    public function __construct() {
        $this->init_github_updater();
        $this->init_auto_update();
        $this->init_reporting();
    }

    private function init_github_updater(): void {
        $this->update_checker = PucFactory::buildUpdateChecker(
            'https://github.com/speakeasy/wordpress-automation/',
            SPEAKEASY_PATH . 'speakeasy-automation.php',
            'speakeasy-automation'
        );

        $this->update_checker->setBranch('main');
        $this->update_checker->setAuthentication(SPEAKEASY_GITHUB_TOKEN);
    }

    private function init_auto_update(): void {
        add_filter('auto_update_plugin', [$this, 'enable_auto_update'], 10, 2);
    }

    private function init_reporting(): void {
        add_action('upgrader_process_complete', [$this, 'report_update'], 10, 2);
    }
}
```

---

### 5. API Reporter

**File:** `includes/class-api-reporter.php`

**Responsibilities:**
- Report plugin activation
- Report plugin updates
- Report module status
- Daily health check

**Endpoints:**
- `POST /wp-plugin/activation` - Initial install
- `POST /wp-plugin/update` - Version update
- `POST /wp-plugin/health` - Daily status
- `POST /wp-plugin/error` - Error reporting

**Data Format:**
```json
{
  "site": "https://www.chrisrussolaw.com",
  "plugin_version": "1.0.0",
  "wordpress_version": "6.5.0",
  "php_version": "8.1.0",
  "active_modules": ["app-passwords", "lap-meta"],
  "timestamp": "2026-06-20 12:00:00"
}
```

---

## Module Specifications

### Module 1: Application Passwords Enabler

**Purpose:** Force-enable WordPress Application Passwords feature

**File:** `modules/app-passwords/class-app-passwords-module.php`

**Implementation:**
```php
class Speakeasy_App_Passwords_Module implements Speakeasy_Module {

    public function get_name(): string {
        return 'Application Passwords Enabler';
    }

    public function get_description(): string {
        return 'Force-enables WordPress Application Passwords, overriding theme/plugin restrictions.';
    }

    public function get_version(): string {
        return '1.0.0';
    }

    public function get_priority(): int {
        return 999; // Run late to override other filters
    }

    public function init(): void {
        add_filter('wp_is_application_passwords_available', '__return_true', 999);
        add_filter('wp_is_application_passwords_available_for_user', '__return_true', 999);

        // Remove known blockers
        $this->remove_known_blockers();
    }

    private function remove_known_blockers(): void {
        // Wordfence
        remove_filter('wp_is_application_passwords_available',
                     'wordfence_disable_app_passwords', 10);

        // Theme-based blockers (add as discovered)
        remove_all_filters('wp_is_application_passwords_available_for_user');
        add_filter('wp_is_application_passwords_available_for_user', '__return_true', 999);
    }

    public function is_enabled(): bool {
        return true; // Always enabled
    }
}
```

**Testing:**
- Navigate to `wp-admin/profile.php`
- "Application Passwords" section should be visible
- Should work even if theme has blocking code

---

### Module 2: LAP Meta Fields

**Purpose:** Expose LAP meta fields to REST API

**File:** `modules/lap-meta/class-lap-meta-module.php`

**Implementation:**
```php
class Speakeasy_LAP_Meta_Module implements Speakeasy_Module {

    private array $field_schemas = [];

    public function __construct() {
        $this->load_schemas();
    }

    public function get_name(): string {
        return 'LAP Meta Fields';
    }

    public function get_description(): string {
        return 'Exposes Local Area Page meta fields to WordPress REST API.';
    }

    public function get_version(): string {
        return '1.0.0';
    }

    public function get_priority(): int {
        return 100;
    }

    public function init(): void {
        add_action('rest_api_init', [$this, 'register_meta_fields']);
    }

    private function load_schemas(): void {
        // Detect which template(s) this site uses
        $templates = $this->detect_lap_templates();

        foreach ($templates as $template) {
            $schema_file = __DIR__ . "/schemas/{$template}.php";
            if (file_exists($schema_file)) {
                $schema = require $schema_file;
                $this->field_schemas = array_merge($this->field_schemas, $schema);
            }
        }

        // Fallback: Load default schema if no templates detected
        if (empty($this->field_schemas)) {
            $default_schema = __DIR__ . '/schemas/localareapage.php';
            if (file_exists($default_schema)) {
                $this->field_schemas = require $default_schema;
            }
        }
    }

    private function detect_lap_templates(): array {
        global $wpdb;

        // Query pages using LAP templates
        $results = $wpdb->get_col("
            SELECT DISTINCT meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_wp_page_template'
            AND meta_value LIKE '%local%area%'
        ");

        return array_map(function($template) {
            // Convert template filename to schema filename
            // localareapage.php -> localareapage.php
            return basename($template);
        }, $results);
    }

    public function register_meta_fields(): void {
        foreach ($this->field_schemas as $field_key => $args) {
            register_meta('post', $field_key, $args);
        }
    }

    public function is_enabled(): bool {
        return true; // Always enabled
    }
}
```

**Schema File Example:**

**File:** `modules/lap-meta/schemas/localareapage.php`

```php
<?php
/**
 * LAP Meta Field Schema for template: localareapage.php
 * Auto-generated from scan
 */

return [
    'spk_main_heading' => [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ],

    'spk_video_section_left_text' => [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ],

    'spk_gridbox_repeater' => [
        'type' => 'array',
        'single' => true,
        'show_in_rest' => [
            'schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'spk_heading' => ['type' => 'string'],
                        'spk_image' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer']
                        ],
                        'spk_content' => ['type' => 'string']
                    ]
                ]
            ]
        ],
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ],

    'spk_cta_bg_color' => [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ],

    'spk_cta_bg_hvr_color' => [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ],

    'spk_call_to_action_box_text' => [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ],

    'spk_add_phone_number' => [
        'type' => 'array',
        'single' => true,
        'show_in_rest' => [
            'schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'spk_call_to_action_phone_number' => ['type' => 'string']
                    ]
                ]
            ]
        ],
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ],

    // Add more fields as discovered by scanner
];
```

**Testing:**
```bash
# Test REST API access
curl -X GET "https://www.chrisrussolaw.com/wp-json/wp/v2/pages/123?context=edit" \
  -u "spk-admin:app-password" \
  | jq .meta

# Should show all LAP fields
```

---

## Admin Interface

### Settings Page

**Location:** Settings → Speakeasy Automation

**Features:**
1. **Module Status Dashboard**
   - Shows all modules and their status (active/inactive)
   - Displays module versions
   - Allows enable/disable per module

2. **Update Information**
   - Current plugin version
   - Latest available version
   - Last update check time
   - Manual "Check for Updates" button

3. **System Information**
   - WordPress version
   - PHP version
   - Active plugins (conflicts check)
   - REST API accessibility test

4. **Diagnostics**
   - Test Application Passwords (shows if enabled)
   - Test REST API meta field access
   - Export configuration for support

**UI Screenshot (Mockup):**

```
┌─────────────────────────────────────────────────────────┐
│ Speakeasy Automation Suite                             │
├─────────────────────────────────────────────────────────┤
│ Status: ✅ All systems operational                      │
│ Version: 1.0.0 (Latest: 1.0.0)                          │
│ Last Update Check: 2 hours ago                          │
│                                                          │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ Modules                                             │ │
│ ├─────────────────────────────────────────────────────┤ │
│ │ ✅ Application Passwords Enabler v1.0.0            │ │
│ │    Force-enables WordPress Application Passwords    │ │
│ │    Status: Active | Priority: 999                   │ │
│ │                                                      │ │
│ │ ✅ LAP Meta Fields v1.0.0                          │ │
│ │    Exposes LAP meta fields to REST API              │ │
│ │    Status: Active | Fields: 8 | Priority: 100       │ │
│ │    Template: localareapage.php                      │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                          │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ Diagnostics                                         │ │
│ ├─────────────────────────────────────────────────────┤ │
│ │ ✅ Application Passwords: Available                │ │
│ │ ✅ REST API: Accessible                            │ │
│ │ ✅ Meta Fields: 8 registered                       │ │
│ │ ℹ️  WordPress: 6.5.0                               │ │
│ │ ℹ️  PHP: 8.1.0                                     │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                          │
│ [Check for Updates]  [Run Diagnostics]  [Export Config] │
└─────────────────────────────────────────────────────────┘
```

---

## Auto-Update Workflow

### GitHub Release Process

```bash
# 1. Developer makes changes locally
git checkout -b feature/new-lap-fields
# ... make changes ...
git commit -m "Add support for new LAP template"
git push origin feature/new-lap-fields

# 2. Create PR and merge to main
gh pr create
gh pr merge

# 3. Create GitHub release
git tag v1.1.0
git push origin v1.1.0
gh release create v1.1.0 \
  --title "v1.1.0 - New LAP Template Support" \
  --notes "Added support for local-area-page.php template variant"

# 4. Plugin auto-update kicks in (on all 38 sites)
# - Within 12 hours, all sites check for updates
# - Auto-download and install v1.1.0
# - Report status to Speakeasy API
```

### Update Check Flow

```
┌─────────────────────────────────────────────────────────┐
│ WordPress Site (every 12 hours)                         │
├─────────────────────────────────────────────────────────┤
│ 1. Plugin checks GitHub API                             │
│    GET https://api.github.com/repos/speakeasy/          │
│        wordpress-automation/releases/latest             │
│                                                          │
│ 2. Compare versions                                     │
│    Current: 1.0.0                                       │
│    Latest: 1.1.0                                        │
│    Result: Update available ✅                          │
│                                                          │
│ 3. Download new version                                 │
│    GET https://github.com/speakeasy/                    │
│        wordpress-automation/archive/v1.1.0.zip          │
│                                                          │
│ 4. Install update                                       │
│    - Backup current version                             │
│    - Extract new version                                │
│    - Replace files                                      │
│    - Clear caches                                       │
│                                                          │
│ 5. Report success                                       │
│    POST https://api.speakeasy.com/wp-plugin/update      │
│    {                                                     │
│      "site": "https://www.chrisrussolaw.com",          │
│      "from_version": "1.0.0",                          │
│      "to_version": "1.1.0",                            │
│      "status": "success",                              │
│      "timestamp": "2026-06-20T12:00:00Z"               │
│    }                                                     │
└─────────────────────────────────────────────────────────┘
```

---

## Deployment Strategy

### Initial Deployment

**Phase 1: Build & Test** (1 day)
1. Build plugin with all modules
2. Generate LAP schemas from scanner results
3. Test on local WordPress instance
4. Test on 1 staging site

**Phase 2: Pilot** (1 day)
1. Deploy to 3 production sites manually (FTP/SFTP)
2. Verify Application Passwords work
3. Verify REST API meta field access
4. Monitor for 24 hours

**Phase 3: Rollout** (1 day)
1. Create GitHub release v1.0.0
2. Deploy to remaining 35 sites via Playwright automation
3. Monitor Speakeasy API for activation reports
4. Verify all sites report healthy status

### Playwright Deployment Script

```javascript
// deploy-plugin-to-sites.js
async function deployPlugin(site, pluginZipPath) {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  // 1. Login to WordPress admin
  await page.goto(`${site.url}/wp-admin`);
  await page.fill('#user_login', site.username);
  await page.fill('#user_pass', site.password);
  await page.click('#wp-submit');

  // 2. Navigate to Plugins > Add New
  await page.goto(`${site.url}/wp-admin/plugin-install.php?tab=upload`);

  // 3. Upload plugin ZIP
  await page.setInputFiles('input[type="file"]', pluginZipPath);
  await page.click('#install-plugin-submit');

  // 4. Wait for installation
  await page.waitForSelector('.button.button-primary');

  // 5. Activate plugin
  await page.click('a:has-text("Activate Plugin")');

  // 6. Verify activation
  const pluginActive = await page.isVisible('text=Speakeasy Automation Suite');

  await browser.close();

  return { site: site.url, success: pluginActive };
}
```

---

## Monitoring & Reporting

### Speakeasy Backend API Endpoints

**Base URL:** `https://api.speakeasy.com/wp-plugin`

#### 1. Activation Report
```
POST /activation
Body: {
  "site": "https://www.chrisrussolaw.com",
  "plugin_version": "1.0.0",
  "wordpress_version": "6.5.0",
  "php_version": "8.1.0",
  "timestamp": "2026-06-20T12:00:00Z"
}

Response: 200 OK
```

#### 2. Update Report
```
POST /update
Body: {
  "site": "https://www.chrisrussolaw.com",
  "from_version": "1.0.0",
  "to_version": "1.1.0",
  "status": "success|failed",
  "error": "Error message if failed",
  "timestamp": "2026-06-20T12:00:00Z"
}

Response: 200 OK
```

#### 3. Health Check
```
POST /health
Body: {
  "site": "https://www.chrisrussolaw.com",
  "plugin_version": "1.0.0",
  "active_modules": ["app-passwords", "lap-meta"],
  "module_status": {
    "app-passwords": {"enabled": true, "version": "1.0.0"},
    "lap-meta": {"enabled": true, "version": "1.0.0", "fields": 8}
  },
  "timestamp": "2026-06-20T12:00:00Z"
}

Response: 200 OK
```

#### 4. Error Report
```
POST /error
Body: {
  "site": "https://www.chrisrussolaw.com",
  "plugin_version": "1.0.0",
  "error_type": "update_failed|module_error|rest_api_error",
  "error_message": "Failed to update from 1.0.0 to 1.1.0",
  "stack_trace": "...",
  "timestamp": "2026-06-20T12:00:00Z"
}

Response: 200 OK
```

### Dashboard View (Speakeasy Backend)

```
┌──────────────────────────────────────────────────────────┐
│ WordPress Plugin Status Dashboard                        │
├──────────────────────────────────────────────────────────┤
│ Total Sites: 38                                          │
│ Plugin Installed: 38 (100%)                              │
│ Current Version: v1.1.0                                  │
│ Latest Version: v1.1.0                                   │
│                                                           │
│ ┌────────────────────────────────────────────────────┐   │
│ │ Version Distribution                               │   │
│ ├────────────────────────────────────────────────────┤   │
│ │ ✅ v1.1.0: 35 sites (92%)                         │   │
│ │ ⏳ v1.0.0: 2 sites (5%) - pending update          │   │
│ │ ❌ v0.9.0: 1 site (3%) - update failed            │   │
│ └────────────────────────────────────────────────────┘   │
│                                                           │
│ ┌────────────────────────────────────────────────────┐   │
│ │ Module Status                                      │   │
│ ├────────────────────────────────────────────────────┤   │
│ │ Application Passwords: 38/38 active (100%)        │   │
│ │ LAP Meta Fields: 38/38 active (100%)              │   │
│ └────────────────────────────────────────────────────┘   │
│                                                           │
│ ┌────────────────────────────────────────────────────┐   │
│ │ Recent Activity                                    │   │
│ ├────────────────────────────────────────────────────┤   │
│ │ 2h ago: www.chrisrussolaw.com updated to v1.1.0  │   │
│ │ 3h ago: www.bortelduidefense.com updated to v1.1.0│   │
│ │ 5h ago: www.nicolettelaw.com updated to v1.1.0    │   │
│ └────────────────────────────────────────────────────┘   │
│                                                           │
│ ┌────────────────────────────────────────────────────┐   │
│ │ Errors & Warnings                                  │   │
│ ├────────────────────────────────────────────────────┤   │
│ │ ❌ www.robichauxlaw.com: Update failed (timeout)  │   │
│ │    Action: Retry update manually                   │   │
│ └────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────┘
```

---

## Security Considerations

### 1. Authentication
- ✅ GitHub token stored as PHP constant (not in database)
- ✅ API reporter uses site-specific authentication token
- ✅ REST API meta field access requires `edit_posts` capability
- ✅ Admin interface requires `manage_options` capability

### 2. Update Verification
- ✅ GitHub releases are signed (verify integrity)
- ✅ Plugin checks version before updating (no downgrades)
- ✅ Backups created before update
- ✅ Rollback mechanism if update fails

### 3. Data Privacy
- ✅ No sensitive data sent to Speakeasy API
- ✅ Only metadata reported (versions, status)
- ✅ No user data or content transmitted
- ✅ API communication over HTTPS only

### 4. Plugin Isolation
- ✅ No conflicts with other plugins
- ✅ Namespaced classes (`Speakeasy_*`)
- ✅ Prefixed functions (`speakeasy_*`)
- ✅ No global variable pollution

---

## Testing Strategy

### Unit Tests (PHP)

```php
// tests/test-app-passwords-module.php
class Test_App_Passwords_Module extends WP_UnitTestCase {

    public function test_application_passwords_enabled() {
        $module = new Speakeasy_App_Passwords_Module();
        $module->init();

        $this->assertTrue(wp_is_application_passwords_available());
        $this->assertTrue(wp_is_application_passwords_available_for_user(1));
    }

    public function test_module_metadata() {
        $module = new Speakeasy_App_Passwords_Module();

        $this->assertEquals('Application Passwords Enabler', $module->get_name());
        $this->assertEquals(999, $module->get_priority());
        $this->assertTrue($module->is_enabled());
    }
}

// tests/test-lap-meta-module.php
class Test_LAP_Meta_Module extends WP_UnitTestCase {

    public function test_meta_fields_registered() {
        $module = new Speakeasy_LAP_Meta_Module();
        $module->init();
        do_action('rest_api_init');

        $registered_meta = get_registered_meta_keys('post');

        $this->assertArrayHasKey('spk_main_heading', $registered_meta);
        $this->assertArrayHasKey('spk_gridbox_repeater', $registered_meta);
    }

    public function test_rest_api_access() {
        // Create test page with LAP template
        $page_id = $this->factory->post->create([
            'post_type' => 'page',
            'meta_input' => [
                '_wp_page_template' => 'localareapage.php',
                'spk_main_heading' => 'Test Heading'
            ]
        ]);

        // Test REST API response
        $request = new WP_REST_Request('GET', '/wp/v2/pages/' . $page_id);
        $request->set_param('context', 'edit');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Test Heading', $response->data['meta']['spk_main_heading']);
    }
}
```

### Integration Tests

**Test Site:** Local WordPress installation with:
- WordPress 6.5
- Meta Box plugin
- Sample LAP pages
- Security plugins (Wordfence)

**Test Scenarios:**
1. ✅ Install plugin → Application Passwords appear
2. ✅ Create LAP page → Meta fields accessible via REST API
3. ✅ Update plugin → Auto-update works
4. ✅ Disable module → Feature disabled
5. ✅ Conflict test → Works with security plugins

### Production Testing

**Pilot Sites (3):**
1. www.chrisrussolaw.com (known to have LAPs)
2. www.bortelduidefense.com (11 LAPs)
3. www.nicolettelaw.com (10 LAPs)

**Test Checklist:**
- [ ] Plugin activates without errors
- [ ] Application Passwords visible in profile
- [ ] Can generate app password successfully
- [ ] REST API returns LAP meta fields
- [ ] Admin dashboard shows correct status
- [ ] Auto-update check runs successfully
- [ ] No conflicts with existing plugins
- [ ] No performance degradation

---

## Documentation

### User Documentation

**File:** `README.md` (included in plugin)

```markdown
# Speakeasy Automation Suite

WordPress plugin for Speakeasy-managed law firm websites.

## Features

- ✅ Force-enables Application Passwords (bypasses restrictions)
- ✅ Exposes LAP meta fields to REST API
- ✅ Auto-updates via GitHub releases
- ✅ Reports status to Speakeasy backend

## Installation

1. Upload `speakeasy-automation.zip` to WordPress
2. Activate plugin
3. Verify in Settings → Speakeasy Automation

## Support

Contact: support@speakeasy.com
```

### Developer Documentation

**File:** `CONTRIBUTING.md`

```markdown
# Developer Guide

## Local Development

1. Clone repository
2. Run `composer install`
3. Symlink to WordPress plugins directory
4. Activate in WordPress

## Adding a New Module

1. Create `modules/your-module/class-your-module.php`
2. Implement `Speakeasy_Module` interface
3. Register in `speakeasy-automation.php`

## Testing

Run PHPUnit tests:
```bash
composer test
```

## Release Process

1. Update version in `speakeasy-automation.php`
2. Update `CHANGELOG.md`
3. Create GitHub release
4. All sites auto-update within 12 hours
```

---

## Version History

### v1.0.0 (Initial Release)
- ✅ Application Passwords enabler module
- ✅ LAP meta fields module
- ✅ GitHub auto-updater
- ✅ Speakeasy API reporter
- ✅ Admin dashboard

### v1.1.0 (Planned)
- 🔜 Support for additional LAP templates
- 🔜 Performance optimizations
- 🔜 Enhanced diagnostics

### v2.0.0 (Future)
- 🔮 Image optimization module
- 🔮 SEO automation module
- 🔮 Analytics integration module

---

## Success Metrics

### Installation Success
- Target: 38/38 sites (100%)
- Timeline: 3 days from release

### Feature Adoption
- Application Passwords: 38/38 sites
- LAP Meta Fields: ~25/38 sites (sites with LAPs)

### Update Performance
- Target: 95% of sites update within 24 hours
- Fallback: Manual update for stragglers

### Error Rate
- Target: <5% error rate during updates
- Monitoring: Real-time alerts for failures

---

## Risks & Mitigation

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Plugin conflicts | High | Low | Test on pilot sites first |
| Auto-update fails | Medium | Medium | Rollback mechanism + manual fallback |
| GitHub API rate limits | Low | Low | Cache update checks |
| Security plugin blocks | Medium | Low | High priority filters (999) |
| Theme update overwrites | Low | Very Low | Plugin-based (not functions.php) |

---

## Budget & Timeline

### Development
- Plugin architecture: 4 hours
- Module development: 6 hours
- Auto-updater integration: 2 hours
- Admin interface: 4 hours
- Testing: 4 hours
- **Total:** 20 hours (2.5 days)

### Deployment
- Build & local testing: 4 hours
- Pilot deployment (3 sites): 2 hours
- Full rollout (35 sites): 4 hours
- Monitoring & fixes: 4 hours
- **Total:** 14 hours (1.75 days)

### **Grand Total:** 34 hours (~4 days)

---

## Next Steps

1. ✅ Review and approve specification
2. 🔜 Set up GitHub repository
3. 🔜 Build plugin skeleton
4. 🔜 Implement modules
5. 🔜 Deploy to pilot sites
6. 🔜 Full rollout

---

## Appendix

### A. GitHub Token Setup

```bash
# Create GitHub personal access token
# Scopes required: repo, workflow

# Add to wp-config.php (each site)
define('SPEAKEASY_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxx');
```

### B. Speakeasy API Authentication

```php
// Add to wp-config.php (each site)
define('SPEAKEASY_API_TOKEN', 'spk_xxxxxxxxxxxx');
```

### C. Useful Commands

```bash
# Check plugin version on site
wp plugin list --field=name,version | grep speakeasy

# Manually trigger update check
wp speakeasy update-check

# View module status
wp speakeasy module status

# Export diagnostics
wp speakeasy diagnostics > diagnostics.json
```

---

**Document Version:** 1.0
**Last Updated:** June 20, 2026
**Prepared By:** Claude (Speakeasy AI Assistant)
**Status:** 🟢 Ready for Development
