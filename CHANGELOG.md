# CHANGELOG — WP Speakeasy

Follows [Keep a Changelog](https://keepachangelog.com) format.
Updated at the end of every session when something is completed and merged.
Never deleted. Older entries are never modified.

---

## [Unreleased]

---

## [1.1.0] — 2026-06-21

### Added
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
