## FEATURE: Modular WordPress automation plugin with REST API enhancements

## OBJECTIVE

Build a modular WordPress plugin (WP Speakeasy) that enables REST API automation for multiple WordPress sites. The plugin provides force-enabled Application Passwords (bypassing theme/security restrictions), custom meta field exposure to REST API, auto-update mechanism via GitHub releases, and centralized reporting capabilities. When complete, the plugin can be deployed to multiple sites, will auto-update itself, and requires zero user interaction after installation.

## CONTEXT

- **Starting state:** Empty plugin directory with context engineering system (CLAUDE.md, MEMORY.md, etc.) but no PHP code
- **Ending state:** Fully functional WordPress plugin with:
  - Main plugin file (wp-speakeasy.php)
  - Module system (includes/class-module-manager.php, includes/interface-module.php)
  - Auto-updater (includes/class-auto-updater.php)
  - API reporter (includes/class-api-reporter.php)
  - Two initial modules: Application Passwords enabler and LAP meta fields exposer
  - Admin interface (admin/class-admin-page.php and views)
  - Complete test suite
- **Related existing code:** None - this is a greenfield implementation
- **Open decisions that must be resolved first:** None (see DECISIONS.md)

## IMPLEMENTATION REQUIREMENTS

### Must Do

- Create modular architecture with plugin interface for extensibility
- Implement singleton pattern for Module Manager
- Force-enable WordPress Application Passwords with priority 999 filters to override restrictions
- Expose custom meta fields to REST API using `register_meta()` with proper schemas
- Integrate GitHub auto-updater using Plugin Update Checker library (YahnisElsts/plugin-update-checker)
- Report activation, updates, health checks, and errors to external API endpoint
- Create admin settings page under Settings → WP Speakeasy
- Follow WordPress plugin directory structure (includes/, admin/, public/, assets/)
- Namespace all classes with `Speakeasy_` prefix
- Prefix all functions with `speakeasy_`
- Support WordPress 5.6+ (when Application Passwords were introduced)
- Require PHP 7.4+
- Include proper plugin header with version, author, license
- Create activation/deactivation hooks
- Load modules based on priority (lower = earlier execution)
- Allow modules to be enabled/disabled
- Detect LAP templates automatically from database queries
- Load appropriate field schemas based on detected templates

### Must NOT Do

- Do not modify WordPress core files - use hooks and filters exclusively
- Do not hardcode API endpoints or tokens - use wp-config.php constants
- Do not access $_GET, $_POST, or $_REQUEST directly - use WordPress sanitization functions
- Do not use `extract()` on any data
- Do not execute SQL without $wpdb->prepare()
- Do not create global variables without `speakeasy_` prefix
- Do not exceed 500 lines per file (follow FILE SIZE RULE)
- Do not implement without tests (TESTING RULE)
- Do not use eval(), system(), exec(), or similar functions
- Do not commit secrets or credentials to repository
- Do not create documentation files yet - focus on code implementation

## ERROR HANDLING REQUIREMENTS

- Use WordPress WP_Error class for all expected failures
- Module registration failures: Return WP_Error with code 'module_registration_failed' and descriptive message
- Module initialization failures: Log error with error_log() and return WP_Error
- API reporter failures: Silent fail (log but don't break site functionality) - use error_log()
- GitHub update check failures: Silent fail with error_log(), retry on next scheduled check
- Meta field registration failures: Return WP_Error with code 'meta_registration_failed'
- File not found (schemas): Log warning and continue with empty schema
- Database query failures: Return WP_Error from functions that query database
- All WP_Error objects must include: error code (string), error message (string), and optional error data (array)
- Never expose internal errors to frontend users - log them instead

## SECURITY CONSIDERATIONS

- **Input validation:** All module IDs must be validated as alphanumeric with hyphens only
- **Nonce verification:** Admin settings page form must verify nonces before saving
- **Capability checks:**
  - Admin interface requires `manage_options` capability
  - REST API meta field access requires `edit_posts` capability
  - Module enable/disable requires `manage_options`
- **Output escaping:**
  - Admin page: Use `esc_html()` for text, `esc_attr()` for attributes, `esc_url()` for URLs
  - API responses: Use `wp_send_json()` and `wp_send_json_error()`
- **Data exposure risks:**
  - Never log API tokens, GitHub tokens, or site credentials
  - Never include sensitive data in API reports (only metadata: versions, status)
  - Never expose error stack traces to non-admin users
- **Note:** This plugin handles authentication (Application Passwords) and requires explicit human review before deployment

## WORDPRESS INTEGRATION

- **Hooks used:**
  - `plugins_loaded` - Initialize plugin and load modules
  - `rest_api_init` - Register meta fields for REST API
  - `auto_update_plugin` - Enable auto-updates for this plugin
  - `upgrader_process_complete` - Report successful updates
  - `admin_menu` - Add admin settings page
  - `admin_init` - Register settings
  - `wp_is_application_passwords_available` (filter, priority 999) - Force enable app passwords
  - `wp_is_application_passwords_available_for_user` (filter, priority 999) - Force enable for all users
- **WordPress APIs:**
  - `register_meta()` - Register custom meta fields for REST
  - `get_option()` / `update_option()` - Store plugin settings
  - `wp_remote_post()` - Send reports to external API
  - `$wpdb->prepare()` and `$wpdb->get_col()` - Query for LAP templates
  - `wp_send_json()` / `wp_send_json_error()` - AJAX responses
  - `add_settings_section()` / `add_settings_field()` - Settings API
  - `current_user_can()` - Capability checks
- **Database tables:** Use existing WordPress tables only (wp_postmeta, wp_options)
- **Admin UI:** Settings → WP Speakeasy (submenu under Settings)
- **Frontend:** No frontend output - this is a backend automation plugin

## TESTS TO WRITE

Write tests BEFORE implementation (TESTING RULE):

- [ ] Module Interface: Test that interface methods are properly defined
- [ ] Module Manager: Test singleton pattern returns same instance
- [ ] Module Manager: Test module registration with valid module
- [ ] Module Manager: Test module registration fails with invalid module (no interface)
- [ ] Module Manager: Test get_module() returns registered module
- [ ] Module Manager: Test get_module() returns null for unregistered module
- [ ] Module Manager: Test init_modules() initializes enabled modules in priority order
- [ ] Module Manager: Test enable_module() and disable_module()
- [ ] App Passwords Module: Test wp_is_application_passwords_available() returns true after init
- [ ] App Passwords Module: Test wp_is_application_passwords_available_for_user() returns true after init
- [ ] App Passwords Module: Test module metadata (name, description, version)
- [ ] LAP Meta Module: Test schema loading from file
- [ ] LAP Meta Module: Test meta fields registered via rest_api_init action
- [ ] LAP Meta Module: Test REST API returns meta fields for page with LAP template
- [ ] LAP Meta Module: Test template detection from database
- [ ] API Reporter: Test activation report sent with correct data format
- [ ] API Reporter: Test failure is silent (doesn't break site)
- [ ] Auto Updater: Test GitHub checker initialized with correct repository
- [ ] Admin Page: Test settings page requires manage_options capability
- [ ] Admin Page: Test non-admin users cannot access settings
- [ ] Integration: Test plugin activation without errors
- [ ] Integration: Test plugin deactivation without errors

## ROLLBACK PLAN

If this feature needs to be abandoned mid-implementation:
- **Branch to return to:** main (current branch)
- **Database changes to reverse:** Only wp_options entries with prefix `speakeasy_*` - delete with `delete_option()`
- **State the codebase should be in:** Remove all .php files created, keep only context engineering files (CLAUDE.md, MEMORY.md, etc.)

## ACCEPTANCE CRITERIA

- [ ] Plugin activates without errors on WordPress 5.6+ with PHP 7.4+
- [ ] Application Passwords section visible in wp-admin/profile.php
- [ ] Can generate Application Password successfully
- [ ] Custom meta fields appear in REST API response: `/wp/v2/pages/{id}?context=edit`
- [ ] Admin settings page accessible at Settings → WP Speakeasy
- [ ] Admin page shows module status (enabled/disabled)
- [ ] Non-admin users cannot access admin settings page
- [ ] Auto-updater checks GitHub for new releases
- [ ] Activation report sent to API (or logged if API unavailable)
- [ ] All existing tests pass (N/A - no existing tests)
- [ ] New tests written and passing (see TESTS TO WRITE section)
- [ ] PHP CodeSniffer passes (WordPress Coding Standards)
- [ ] PHPStan static analysis passes
- [ ] No undocumented exports (every function/class has PHPDoc block per CODE_STYLE.md)
- [ ] CHANGELOG.md updated with version 1.0.0 entry
- [ ] No files exceed 500 lines (FILE SIZE RULE)
- [ ] Plugin works with security plugins (Wordfence, etc.) - App Passwords stay enabled

## VALIDATION

Run these commands to verify completion:

```bash
# Code quality
composer phpcs              # Check WordPress coding standards
composer phpstan            # Run static analysis
composer test               # Run PHPUnit tests with coverage

# WordPress integration
wp plugin activate wp-speakeasy           # Verify activation
wp plugin list --status=active            # Confirm active
wp eval "var_dump(wp_is_application_passwords_available());"  # Should output: bool(true)

# REST API test (requires Application Password)
curl -X GET "http://localhost/wp-json/wp/v2/pages/1?context=edit" \
  -u "admin:xxxx xxxx xxxx xxxx" | jq .meta

# Admin access test
wp admin login admin  # Should see Settings → WP Speakeasy menu
```

## PHASED IMPLEMENTATION

Due to complexity, implement in phases:

### Phase 1: Core Architecture (Bootstrap)
- Main plugin file with header and constants
- Module interface
- Module manager (singleton, registration, initialization)
- Basic activation/deactivation hooks
- Tests for module system

### Phase 2: Application Passwords Module
- Implement Speakeasy_App_Passwords_Module
- Force-enable filters with priority 999
- Remove known blockers (Wordfence, etc.)
- Tests for App Passwords functionality

### Phase 3: LAP Meta Fields Module
- Implement Speakeasy_LAP_Meta_Module
- Schema file structure (modules/lap-meta/schemas/)
- Template detection logic
- Meta field registration
- Tests for meta field exposure

### Phase 4: Auto-Updater Integration
- Add Plugin Update Checker library via Composer
- Implement Speakeasy_Auto_Updater class
- GitHub repository configuration
- Update reporting
- Tests for updater (mock GitHub API)

### Phase 5: API Reporter
- Implement Speakeasy_API_Reporter class
- Activation, update, health, and error endpoints
- Silent failure handling
- Tests for reporter (mock API)

### Phase 6: Admin Interface
- Settings page registration
- Dashboard view (module status, diagnostics)
- Settings view (enable/disable modules)
- Nonce verification and capability checks
- Tests for admin access control

## DEPENDENCIES

- **Composer packages:**
  - `yahnis-elsts/plugin-update-checker` (^5.0) - GitHub auto-updater
  - `phpunit/phpunit` (^9.0) - Testing framework (dev)
  - `squizlabs/php_codesniffer` (^3.7) - Coding standards (dev)
  - `wp-coding-standards/wpcs` (^3.0) - WordPress standards (dev)
  - `phpstan/phpstan` (^1.10) - Static analysis (dev)
  - `yoast/phpunit-polyfills` (^2.0) - PHPUnit compatibility (dev)

- **WordPress requirements:**
  - WordPress 5.6+ (Application Passwords feature)
  - PHP 7.4+
  - Meta Box plugin (external dependency - assumed installed)

## NOTES

- This PRP adapted from comprehensive specification in docs/specs/wordpress.md
- Original spec was for "Speakeasy Automation Suite" - adapted for "WP Speakeasy" project
- Focus on building reusable architecture that can be extended with additional modules in future
- GitHub repository and API endpoints will need to be configured in wp-config.php
- Initial deployment will be manual; auto-updates handle subsequent releases
