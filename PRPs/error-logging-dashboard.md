## FEATURE: Error Logging and Dashboard Display

## OBJECTIVE
Enable administrators to view plugin-specific errors, warnings, and failures directly in the WordPress admin dashboard without needing to access server error logs. The system captures errors from modules, API calls, auto-updater operations, and PHP runtime errors, displays them in a dedicated dashboard section with timestamps and details, and provides a one-click clear function.

## CONTEXT

- Starting state:
  - `wp-speakeasy.php` - Main plugin file with activation hooks
  - `includes/class-api-reporter.php` - Uses error_log() for API failures
  - `includes/class-auto-updater.php` - Uses error_log() for update failures
  - `includes/class-module-manager.php` - Module initialization
  - `admin/class-admin-page.php` - Admin dashboard controller
  - `admin/views/dashboard.php` - Admin dashboard template
  - Errors currently go to PHP error_log() only, not visible to admins

- Ending state:
  - New file: `includes/class-error-logger.php` - Error capture and storage
  - Modified: `admin/class-admin-page.php` - Add error retrieval and AJAX handlers
  - Modified: `admin/views/dashboard.php` - Add error log display section
  - Modified: `includes/class-api-reporter.php` - Log errors to Error Logger
  - Modified: `includes/class-auto-updater.php` - Log errors to Error Logger
  - Modified: `includes/class-module-manager.php` - Log module errors to Error Logger
  - Modified: `wp-speakeasy.php` - Initialize Error Logger early
  - New file: `tests/test-error-logger.php` - Error logger tests

- Related existing code:
  - `includes/class-api-reporter.php` - Current error_log() usage patterns
  - `includes/class-auto-updater.php` - Current error_log() usage patterns
  - `admin/class-admin-page.php` - AJAX handler patterns for reference
  - `admin/views/dashboard.php` - Dashboard card UI patterns

- Open decisions that must be resolved first: None

## IMPLEMENTATION REQUIREMENTS

### Must Do
- Create `Speakeasy_Error_Logger` singleton class to capture and store errors
- Store errors in WordPress options table (`speakeasy_error_log`) as JSON array
- Limit storage to last 50 errors, auto-prune older entries
- Capture error type, message, file, line number, timestamp, and context
- Register a custom PHP error handler for plugin-specific errors only (errors in wp-speakeasy directory)
- Add "Error Log" section to admin dashboard showing recent errors in a table
- Display error severity (error, warning, notice), timestamp, message, and file:line
- Add expandable details (stack trace, context data) for each error entry
- Provide "Clear Error Log" button with AJAX handler
- Integrate error logging into existing error_log() calls in API reporter and auto-updater
- Log module initialization failures in Module Manager
- Ensure error logger never throws exceptions (fail silently if it can't log)
- Add visual indicator on dashboard if errors exist (e.g., error count badge)

### Must NOT Do
- Do NOT capture WordPress core errors (only wp-speakeasy plugin errors)
- Do NOT log sensitive data (API keys, tokens, passwords, user PII)
- Do NOT expose full file paths to users (strip ABSPATH for security)
- Do NOT break the site if error logging fails (always degrade gracefully)
- Do NOT use database tables (use wp_options only)
- Do NOT capture errors from other plugins
- Do NOT register a global error handler (scope to plugin directory only)
- Do NOT log more than 50 errors (auto-prune to prevent bloat)

## ERROR HANDLING REQUIREMENTS

- Error Logger itself must never throw exceptions or trigger errors (silent failure)
- If wp_options storage fails, log to PHP error_log() as fallback but continue
- If JSON encoding fails, store simplified error message without context
- Return early from logging if WordPress is not loaded (ABSPATH not defined)
- All AJAX handlers must use WP_Error pattern:
  - Success: `wp_send_json_success( array( 'errors' => $errors ) )`
  - Failure: `wp_send_json_error( array( 'message' => 'Error message' ) )`
- Callers logging errors should continue execution (non-blocking)

## SECURITY CONSIDERATIONS

- Input validation:
  - Validate error type is one of: 'error', 'warning', 'notice', 'exception'
  - Sanitize all error messages before storage (strip tags, limit length to 500 chars)
  - Strip ABSPATH from file paths before storing
  - Validate context data is serializable and doesn't contain objects with secrets

- Nonce verification:
  - AJAX action `speakeasy_clear_errors` requires nonce `speakeasy_errors_nonce`
  - AJAX action `speakeasy_get_errors` requires nonce `speakeasy_errors_nonce`

- Capability checks:
  - Only users with `manage_options` capability can view errors
  - Only users with `manage_options` capability can clear errors
  - Check capability in both admin page render and AJAX handlers

- Output escaping:
  - Use `esc_html()` for error messages in dashboard
  - Use `esc_attr()` for data attributes
  - Use `wp_kses_post()` for stack traces (allow <br>, <code> tags only)

- Data exposure risks:
  - Never log API keys, tokens, passwords, or credentials
  - Never log user email addresses or PII
  - Strip ABSPATH from file paths (show relative paths only)
  - Sanitize error messages to remove potential secret values
  - Do not expose error details to non-admin users

- Human review required: NO (this is a defensive monitoring tool, not auth/crypto/payment)

## WORDPRESS INTEGRATION

- Hooks (actions/filters):
  - `plugins_loaded` (priority 5) - Initialize Error Logger early
  - `wp_ajax_speakeasy_get_errors` - AJAX handler for fetching errors
  - `wp_ajax_speakeasy_clear_errors` - AJAX handler for clearing errors
  - Custom PHP error handler via `set_error_handler()` scoped to plugin directory
  - Custom exception handler via `set_exception_handler()` for uncaught exceptions

- WordPress APIs:
  - `get_option( 'speakeasy_error_log' )` - Retrieve error log
  - `update_option( 'speakeasy_error_log', $errors )` - Store error log
  - `delete_option( 'speakeasy_error_log' )` - Clear error log
  - `current_user_can( 'manage_options' )` - Capability check
  - `wp_send_json_success()` / `wp_send_json_error()` - AJAX responses
  - `check_ajax_referer()` - Nonce verification
  - `wp_create_nonce()` - Nonce generation for AJAX

- Database tables:
  - Use existing `wp_options` table (no custom tables)
  - Option name: `speakeasy_error_log`
  - Value: JSON-encoded array of error objects

- Admin UI integration:
  - Add new card to existing dashboard in `admin/views/dashboard.php`
  - Position: After "Backend Registration" section, before "System Information"
  - Card title: "Error Log"
  - Include error count badge in card title if errors exist

- Frontend integration: None (admin-only feature)

## TESTS TO WRITE

List the specific test cases before any implementation begins:
- [ ] Happy path: Log an error and retrieve it from storage
- [ ] Happy path: Log multiple errors and verify limit (50 max)
- [ ] Happy path: Clear error log successfully
- [ ] Error path: Attempt to log error when wp_options fails (fallback to error_log)
- [ ] Error path: Attempt to clear errors without manage_options capability (WP_Error)
- [ ] Edge case: Log 51 errors and verify oldest is pruned
- [ ] Edge case: Log error with very long message (verify 500 char limit)
- [ ] Edge case: Log error with sensitive data (verify sanitization strips it)
- [ ] Edge case: Attempt to log error before WordPress is loaded (verify graceful skip)
- [ ] WordPress integration: Verify error handler only captures plugin errors (not core)
- [ ] WordPress integration: Verify AJAX nonce validation works
- [ ] WordPress integration: Verify capability checks prevent non-admin access

## ROLLBACK PLAN

If this feature needs to be abandoned mid-implementation:
- Branch to return to: main
- Database changes to reverse:
  - Delete option: `speakeasy_error_log`
  - Run: `wp option delete speakeasy_error_log`
- State the codebase should be in:
  - Remove `includes/class-error-logger.php`
  - Revert changes to `wp-speakeasy.php`, `admin/class-admin-page.php`, `admin/views/dashboard.php`
  - Revert integration changes in API reporter, auto-updater, module manager
  - Remove `tests/test-error-logger.php`
  - Remove this PRP file

## ACCEPTANCE CRITERIA
- [ ] Error Logger class exists and follows singleton pattern
- [ ] Errors are logged to wp_options and retrievable
- [ ] Dashboard displays errors in a table with severity, timestamp, message, file:line
- [ ] Stack traces and context are expandable per error
- [ ] "Clear Error Log" button works via AJAX
- [ ] Error count badge shows in dashboard when errors exist
- [ ] Only users with manage_options can view/clear errors
- [ ] API reporter errors appear in dashboard
- [ ] Auto-updater errors appear in dashboard
- [ ] Module initialization errors appear in dashboard
- [ ] Sensitive data (API keys, paths) is sanitized from error messages
- [ ] Maximum 50 errors stored (auto-pruning works)
- [ ] All existing tests pass
- [ ] New tests written and passing (12 test cases minimum)
- [ ] PHP CodeSniffer passes (WordPress Coding Standards)
- [ ] PHPStan static analysis passes
- [ ] No undocumented exports (every function/class has a PHPDoc block)
- [ ] CHANGELOG.md updated with new feature

## VALIDATION
Run these commands to verify completion:
```bash
composer phpcs              # Check coding standards
composer phpstan            # Run static analysis
composer test               # Run PHPUnit tests
wp plugin activate wp-speakeasy  # Verify plugin activates without errors
wp option get speakeasy_error_log  # Verify error log option exists
```

Manual testing:
1. Activate plugin and verify "Error Log" section appears in dashboard
2. Trigger an API error (disable network) and verify error appears in dashboard
3. Click "Clear Error Log" and verify errors are removed
4. Log out and verify non-admin users cannot see error log
5. Verify file paths show relative paths (not absolute with ABSPATH)
