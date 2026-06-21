## FEATURE: REST API Endpoint for Creating Application Passwords

## OBJECTIVE

Create a REST API endpoint that allows the Speakeasy backend to programmatically create WordPress Application Passwords by authenticating with the plugin API key. This solves the chicken-and-egg problem of needing Application Passwords to access the REST API in the first place.

## CONTEXT

- **Starting state:** Plugin has Application Password enabler module that forces the feature to be available, but no way to create passwords programmatically. Users must manually visit wp-admin/profile.php to create Application Passwords.
- **Ending state:** REST API endpoint at `/wp-json/speakeasy/v1/application-passwords` that accepts plugin API key authentication and creates Application Passwords for specified users.
- **Related existing code:**
  - `modules/app-passwords/class-app-passwords-module.php` - Forces Application Passwords to be available
  - `includes/class-api-reporter.php` - Shows pattern for using plugin API key
  - Plugin API key stored in `speakeasy_api_key` option (auto-generated on activation)
- **Open decisions that must be resolved first:** None (see DECISIONS.md)

## IMPLEMENTATION REQUIREMENTS

### Must Do

- Create new REST API endpoint class: `includes/class-rest-api.php`
- Register REST route: `POST /wp-json/speakeasy/v1/application-passwords`
- Validate API key from `X-Speakeasy-API-Key` request header
- Accept JSON request body with required `username` field and optional `name` field
- Validate that username exists in WordPress
- Check that target user can use Application Passwords (not disabled for that specific user)
- If Application Password with same name exists for user, revoke it first using `WP_Application_Passwords::delete_application_password()`
- Create new Application Password using `WP_Application_Passwords::create_new_application_password()`
- Default name to "Speakeasy Automation - {Y-m-d H:i:s}" if not provided
- Return JSON response with password (plaintext, only shown this once), username, user_id, and name
- Initialize REST API in main plugin file on `rest_api_init` hook
- Follow WordPress REST API best practices (use `WP_REST_Request`, `WP_REST_Response`)
- Log all authentication failures and errors using `Speakeasy_Error_Logger` if available
- Return proper HTTP status codes (200, 401, 403, 404, 500)

### Must NOT Do

- Do not allow endpoint access without valid API key
- Do not store or log the generated Application Password (only return it once)
- Do not allow creating passwords for users who have Application Passwords disabled
- Do not expose internal error details in API responses (log them instead)
- Do not create multiple passwords with same name (revoke old one first)
- Do not exceed 500 lines in any single file
- Do not skip writing tests

## ERROR HANDLING REQUIREMENTS

- Missing API key header: Return `WP_Error` with code `missing_api_key`, HTTP 401
- Invalid API key: Return `WP_Error` with code `invalid_api_key`, HTTP 401
- Missing username in request: Return `WP_Error` with code `missing_username`, HTTP 400
- User not found: Return `WP_Error` with code `user_not_found`, HTTP 404
- User cannot use Application Passwords: Return `WP_Error` with code `app_passwords_disabled`, HTTP 403
- Application Passwords not available globally: Return `WP_Error` with code `app_passwords_unavailable`, HTTP 503
- Failed to create password: Return `WP_Error` with code `creation_failed`, HTTP 500
- All errors must be logged using `error_log()` and `Speakeasy_Error_Logger` if available
- Never expose sensitive information (API keys, user passwords) in error messages

## SECURITY CONSIDERATIONS

- **Authentication:** API key must match value stored in `speakeasy_api_key` option
- **Timing attack protection:** Use `hash_equals()` for API key comparison
- **Input validation:**
  - Username must be sanitized with `sanitize_user()`
  - Name must be sanitized with `sanitize_text_field()`
  - Validate username length (max 60 characters per WordPress standards)
  - Validate name length (max 255 characters)
- **Rate limiting:** Consider adding rate limiting to prevent brute force attacks (not in v1, but document as future enhancement)
- **Capability checks:** Target user must pass `wp_is_application_passwords_available_for_user()` check
- **Output escaping:** Not applicable (JSON responses via `wp_send_json()`)
- **Audit logging:** Log all successful password creations (username, timestamp, IP) using `Speakeasy_Error_Logger` with severity 'notice'
- **Note:** This endpoint handles authentication provisioning and requires security review before production deployment

## WORDPRESS INTEGRATION

- **Hooks used:**
  - `rest_api_init` - Register REST routes and endpoints
- **WordPress APIs:**
  - `register_rest_route()` - Register custom endpoint
  - `WP_Application_Passwords::create_new_application_password()` - Create password
  - `WP_Application_Passwords::delete_application_password()` - Revoke existing password
  - `WP_Application_Passwords::get_user_application_passwords()` - List existing passwords
  - `get_user_by()` - Fetch user by username
  - `wp_is_application_passwords_available()` - Check global availability
  - `wp_is_application_passwords_available_for_user()` - Check user-specific availability
  - `wp_send_json_success()` - Send success response
  - `wp_send_json_error()` - Send error response
  - `rest_ensure_response()` - Ensure proper REST response format
- **Database:** No direct database access (use WordPress APIs only)

## TEST CASES

### Test: Valid request creates Application Password
- Setup: Valid API key, existing username "admin", name "Test Password"
- Action: POST to endpoint with valid credentials
- Expected: Returns 200 with password, username, user_id, name
- Expected: Application Password exists in WordPress database for user

### Test: Revokes existing password with same name
- Setup: User already has Application Password named "Test Password"
- Action: POST to endpoint with same name
- Expected: Old password is revoked, new password created
- Expected: Only one password with that name exists

### Test: Invalid API key rejected
- Setup: Wrong API key in header
- Action: POST to endpoint
- Expected: Returns 401 with error code `invalid_api_key`

### Test: Missing API key rejected
- Setup: No API key header
- Action: POST to endpoint
- Expected: Returns 401 with error code `missing_api_key`

### Test: User not found
- Setup: Valid API key, username "nonexistent"
- Action: POST to endpoint
- Expected: Returns 404 with error code `user_not_found`

### Test: Missing username in request
- Setup: Valid API key, empty request body
- Action: POST to endpoint
- Expected: Returns 400 with error code `missing_username`

### Test: Default name generated
- Setup: Valid API key, username without name field
- Action: POST to endpoint
- Expected: Password created with name like "Speakeasy Automation - 2026-06-21 13:45:30"

### Test: User with Application Passwords disabled
- Setup: User has Application Passwords disabled via filter
- Action: POST to endpoint
- Expected: Returns 403 with error code `app_passwords_disabled`

### Test: Timing attack protection
- Setup: Two different invalid API keys
- Action: Time comparison for both requests
- Expected: Response times are consistent (hash_equals used)

## API SPECIFICATION

### Endpoint

```
POST /wp-json/speakeasy/v1/application-passwords
```

### Request Headers

```
X-Speakeasy-API-Key: {plugin_api_key}
Content-Type: application/json
```

### Request Body

```json
{
  "username": "admin",
  "name": "Speakeasy Automation"  // Optional, defaults to "Speakeasy Automation - {timestamp}"
}
```

### Success Response (200 OK)

```json
{
  "success": true,
  "password": "abcd 1234 efgh 5678 ijkl 9012",
  "username": "admin",
  "user_id": 1,
  "name": "Speakeasy Automation"
}
```

### Error Responses

**401 Unauthorized - Missing API Key**
```json
{
  "code": "missing_api_key",
  "message": "API key is required",
  "data": {
    "status": 401
  }
}
```

**401 Unauthorized - Invalid API Key**
```json
{
  "code": "invalid_api_key",
  "message": "Invalid API key",
  "data": {
    "status": 401
  }
}
```

**400 Bad Request - Missing Username**
```json
{
  "code": "missing_username",
  "message": "Username is required",
  "data": {
    "status": 400
  }
}
```

**404 Not Found - User Not Found**
```json
{
  "code": "user_not_found",
  "message": "User not found",
  "data": {
    "status": 404
  }
}
```

**403 Forbidden - Application Passwords Disabled**
```json
{
  "code": "app_passwords_disabled",
  "message": "Application Passwords are not available for this user",
  "data": {
    "status": 403
  }
}
```

**503 Service Unavailable - Application Passwords Not Available**
```json
{
  "code": "app_passwords_unavailable",
  "message": "Application Passwords are not available on this site",
  "data": {
    "status": 503
  }
}
```

**500 Internal Server Error - Creation Failed**
```json
{
  "code": "creation_failed",
  "message": "Failed to create Application Password",
  "data": {
    "status": 500
  }
}
```

## USAGE EXAMPLE

### cURL Request

```bash
curl -X POST https://example.com/wp-json/speakeasy/v1/application-passwords \
  -H "X-Speakeasy-API-Key: spk_1234567890abcdef" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "name": "Speakeasy Backend Access"
  }'
```

### JavaScript (Node.js)

```javascript
const response = await fetch('https://example.com/wp-json/speakeasy/v1/application-passwords', {
  method: 'POST',
  headers: {
    'X-Speakeasy-API-Key': 'spk_1234567890abcdef',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    username: 'admin',
    name: 'Speakeasy Backend Access'
  })
});

const data = await response.json();
console.log('Application Password:', data.password);
```

## FUTURE ENHANCEMENTS (NOT IN v1)

- List Application Passwords for a user
- Revoke specific Application Password by UUID
- Revoke all Application Passwords for a user
- Rate limiting to prevent brute force attacks
- IP whitelist for additional security
- Webhook notification when password is created
- Automatic expiration of Application Passwords after N days
- Audit log of all password creations/revocations

## FILES TO CREATE/MODIFY

### New Files
- `includes/class-rest-api.php` - REST API endpoint handler
- `tests/test-rest-api.php` - Test suite for REST API endpoints

### Modified Files
- `wp-speakeasy.php` - Initialize REST API class
- `CHANGELOG.md` - Document new feature
- `README.md` - Update feature list and usage examples

## ACCEPTANCE CRITERIA

- [ ] REST API endpoint responds at `/wp-json/speakeasy/v1/application-passwords`
- [ ] Valid API key + username creates Application Password successfully
- [ ] Invalid API key returns 401 error
- [ ] Non-existent username returns 404 error
- [ ] Existing password with same name is revoked before creating new one
- [ ] Default name includes timestamp when name not provided
- [ ] All error cases return proper HTTP status codes
- [ ] All errors are logged using Error Logger
- [ ] Password is only returned once (not stored/logged)
- [ ] Test suite has 100% coverage of endpoint logic
- [ ] Code passes PHPStan and PHPCS checks
- [ ] Documentation is updated in README.md
