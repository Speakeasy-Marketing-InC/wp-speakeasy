# CHANGELOG — WP Speakeasy

Follows [Keep a Changelog](https://keepachangelog.com) format.
Updated at the end of every session when something is completed and merged.
Never deleted. Older entries are never modified.

---

## [Unreleased]

### Added
- **Legacy LAP variant support**: sites running the legacy LAP plugin store their page content under
  a completely different set of meta keys (`spk_mainheading`) than the modern plugin
  (`spk_main_heading`), with no overlap. Both ship a template named `localareapage.php`, so the
  variant could not be told apart by filename and legacy sites silently got the modern field set.
  - `GET /wp-json/speakeasy/v1/lap-variant` — site-level variant verdict, with a `mixed` flag and
    per-variant page counts. Fixed query cost regardless of how many LAP pages the site has.
  - `GET /wp-json/speakeasy/v1/lap-variant/{page_id}` — per-page verdict, plus the marker keys
    behind it.
  - `GET|POST /wp-json/speakeasy/v1/lap-meta/legacy_v1/{page_id}` — reads and writes the 26 legacy
    fields using the legacy plugin's own key names and value shapes. Image fields are read and
    written as bare attachment IDs via `get_post_meta()`/`update_post_meta()`, mirroring how the
    legacy template consumes them.
  - Refuses to guess rather than write silently-ineffective meta: `variant_mismatch` when a page's
    variant does not match the route, `ambiguous_field_variant` when a page carries both key styles.

### Changed
- Auth, LAP page validation and Meta Box availability checks moved from
  `Speakeasy_LAP_Meta_Endpoint` to a shared `Speakeasy_LAP_Endpoint_Base` that every variant
  endpoint extends.
- **Breaking — every LAP route now enforces the same variant guard.** Previously only the legacy_v1
  route checked, so a legacy page addressed on the modern route accepted writes that persisted under
  keys its template never reads and returned `200`. That case now returns `400 variant_mismatch`, and
  a page carrying both key styles returns `400 ambiguous_field_variant`. Callers pointing at the
  modern route for legacy pages will start seeing 400s; those calls were already having no effect.
- Both LAP routes now include `variant` in their responses.
- A page with no LAP meta can now be populated on either route, making create-and-populate work in
  one pass. The route is treated as the caller's declaration of variant; the write is refused only
  when the site's own variant unambiguously contradicts it. `variant_undetermined` is removed — it is
  no longer reachable.
- `composer phpcs` / `phpcbf` now read `phpcs.xml.dist` instead of passing flags inline. The ruleset
  is equivalent, plus a `custom_test_classes` declaration so shared abstract test cases in `tests/`
  keep WPCS's test-class exemption.

### Fixed
- **LAP Meta write verification**: `POST /wp-json/speakeasy/v1/lap-meta/{page_id}` now reads each
  written field back before responding and reports it under a new `failed` array if a non-empty
  value didn't actually persist, instead of always reporting success. Surfaces Meta Box field-config
  mismatches (e.g. gridbox image sub-fields) that previously looked like successful writes.

---

## [1.1.0] — 2026-06-21

### Added
- **REST API for Application Password Creation**: New endpoint for programmatic Application Password management
  - `POST /wp-json/speakeasy/v1/application-passwords` endpoint
  - Authenticates using plugin API key via `X-Speakeasy-API-Key` header
  - Creates Application Passwords for specified WordPress users
  - Automatically revokes existing passwords with same name before creating new one
  - Returns password only once (not stored or logged)
  - Full error handling with proper HTTP status codes (400, 401, 403, 404, 500, 503)
  - Timing-safe API key comparison using `hash_equals()` to prevent timing attacks
  - Input validation and sanitization for username and password name
  - Comprehensive audit logging using Error Logger
  - Client IP tracking for security monitoring
  - Supports custom password names or auto-generates timestamped names
  - Complete test coverage with 10+ test cases

- **Error Logger System**: Comprehensive error tracking and dashboard display
  - `Speakeasy_Error_Logger` singleton class for capturing plugin errors
  - Error Log dashboard widget with severity badges and timestamps
  - Stores up to 50 most recent errors in WordPress options
  - Show/hide toggle for detailed error information (stack traces, context)
  - AJAX-powered "Clear Error Log" functionality
  - Automatic sanitization of sensitive data (API keys, tokens, passwords)
  - File path sanitization (strips ABSPATH for security)
  - Integration with existing components (API Reporter, Auto Updater, Module Manager)
  - Error severity levels: error, warning, notice, exception
  - Helper methods for logging WP_Error and Exception objects
  - Admin-only access with `manage_options` capability check
  - Never breaks site (graceful degradation if logging fails)

- **API Key Toggle**: Show/hide button for full API key display in admin dashboard

### Changed
- Error Logger initialized early (priority 5) to capture initialization errors
- All error_log() calls now also log to Error Logger for dashboard visibility
- Dashboard UI reorganized with error log section after backend registration

### Security
- Error messages sanitized to remove sensitive patterns (keys, tokens, emails, credit cards)
- Only administrators can view and clear error logs
- AJAX actions protected with nonce verification
- File paths shown as relative (ABSPATH stripped)

---

## [1.0.0] — 2026-06-20

### Added
- Complete WordPress plugin architecture with modular system
- Module interface and Module Manager (singleton pattern)
- Application Passwords Enabler module (force-enables App Passwords with priority 999)
- LAP Meta Fields module (exposes custom meta fields to REST API)
- Auto-Updater integration with GitHub releases (using Plugin Update Checker library)
- API Reporter for health checks, activation, and update reporting
- Admin settings page under Settings → WP Speakeasy
- Comprehensive PHPUnit test suite (90+ test cases)
- Composer configuration with all dependencies
- PHPStan and PHP CodeSniffer integration for code quality
- Schema system for LAP template field definitions
- Default localareapage.php schema with 7 meta fields
- WordPress 5.6+ and PHP 7.4+ compatibility
- Non-blocking API communication (all external calls fail silently)
- Activation hook with default module enablement
- Complete PHPDoc documentation for all classes and methods

### Technical Details
- Singleton pattern for Module Manager
- Priority-based module initialization
- WordPress WP_Error for error handling
- Security: nonce verification, capability checks, input sanitization
- Follows WordPress Coding Standards
- File structure: includes/, modules/, admin/, tests/
- All classes namespaced with Speakeasy_ prefix

---

## [0.1.0] — 2026-06-20

### Added
- Initial project setup
