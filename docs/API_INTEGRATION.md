# API Integration Guide

**WP Speakeasy Plugin - Centralized Monitoring & Reporting**

This guide explains how to integrate WP Speakeasy with your backend monitoring system to track plugin status across multiple WordPress sites.

---

## Table of Contents

- [Overview](#overview)
- [Configuration](#configuration)
- [API Endpoints](#api-endpoints)
- [Request Format](#request-format)
- [Response Handling](#response-handling)
- [Backend Implementation Examples](#backend-implementation-examples)
- [Security Considerations](#security-considerations)
- [Troubleshooting](#troubleshooting)

---

## Overview

The API reporting feature allows you to monitor WP Speakeasy across multiple WordPress installations from a centralized dashboard.

### Key Features

- **Non-blocking requests**: API calls don't slow down WordPress sites
- **Fail-silent**: Errors are logged locally, never shown to users
- **Authenticated**: Bearer token authentication for security
- **Optional**: Plugin works perfectly without API configuration

### Use Cases

#### Single Site
No API configuration needed - plugin works standalone.

#### Multiple Sites (5-50)
Build a simple monitoring endpoint to track:
- Which sites have the plugin installed
- Current version on each site
- Update success/failure rates

#### Enterprise Fleet (50+)
Build a full dashboard with:
- Real-time status monitoring
- Automated alerts for failed updates
- Compliance reporting
- Historical analytics

---

## Configuration

### Zero Configuration Required

**The plugin works out of the box - no configuration needed!**

All Speakeasy reporting endpoints are publicly accessible (no authentication required), so you can simply install and activate the plugin on any WordPress site. The site will automatically register with the Speakeasy backend.

**What happens on plugin activation:**
1. Plugin generates a unique 64-character API key for site identification
2. Sends `POST /wordpress-sites/register` to Speakeasy backend
3. No authentication required - endpoint is publicly accessible
4. Sends site URL, plugin API key, versions, and module status
5. Retries hourly until backend confirms registration (HTTP 200/201)
6. Backend stores the site in the `wordpress_sites` table

**That's it!** No API keys to configure, no authentication to manage - just install and activate.

### Custom API Endpoint (Optional)

To use a different Speakeasy backend (staging/development):

```php
// wp-config.php
define( 'SPEAKEASY_API_ENDPOINT', 'https://staging.speakeasymarketinginc.com/api/wordpress-sites' );
```

If not defined, defaults to: `https://server.speakeasymarketinginc.com/api/wordpress-sites`

### How API Keys Work

**Two Types of API Keys:**

1. **Speakeasy User API Key** (`SPEAKEASY_USER_API_KEY`)
   - Your personal Speakeasy account API key
   - Starts with `spk_`
   - Configured in wp-config.php
   - Used to authenticate plugin API requests to the backend
   - Requires `wordpress:plugin:report` permission
   - Same key can be used across hundreds of WordPress sites

2. **Plugin-Generated API Key** (`speakeasy_api_key` option)
   - Automatically generated per WordPress site (64 random hex characters)
   - Stored in WordPress database
   - Sent to backend during registration
   - Backend stores it in `wordpress_sites.pluginApiKey` column
   - Used for identifying the specific WordPress site (not for auth)

**View a site's plugin-generated API key:**
```bash
wp option get speakeasy_api_key
# Returns: a1b2c3d4e5f6...
```

---

## API Endpoints

The plugin sends requests to four Speakeasy backend endpoints:

### 1. Site Registration

**Endpoint:** `POST /wordpress-sites/register`

**When:** Plugin is activated on a WordPress site

**Frequency:** Retries hourly until confirmed by server (HTTP 200 or 201)

**Authentication:** None (publicly accessible)

**Request Body:**
```json
{
  "siteUrl": "https://www.example.com",
  "pluginApiKey": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2",
  "pluginVersion": "1.0.0",
  "wordpressVersion": "6.5.0",
  "phpVersion": "8.1.0",
  "activeModules": ["app-passwords", "lap-meta"],
  "moduleStatus": {
    "app-passwords": {
      "enabled": true,
      "version": "1.0.0"
    },
    "lap-meta": {
      "enabled": true,
      "version": "1.0.0"
    }
  }
}
```

**Success Response:** HTTP 200 or 201 stops retries

**Use Case:**
- Automatically register WordPress sites with Speakeasy backend
- Track new plugin installations across your fleet
- Collect plugin-generated API keys for site identification
- Self-healing: survives network issues and server downtime

---

### 2. Update Report

**Endpoint:** `POST /wordpress-sites/update`

**When:** Plugin auto-updates to a new version

**Frequency:** Once per update attempt

**Authentication:** None (publicly accessible)

**Request Body:**
```json
{
  "siteUrl": "https://www.example.com",
  "pluginVersion": "1.1.0",
  "status": "success"
}
```

**Status Values:**
- `success` - Update completed successfully
- `failed` - Update encountered an error

**Use Case:** Monitor update rollout progress after releasing a new version across your fleet.

---

### 3. Daily Health Check

**Endpoint:** `POST /wordpress-sites/heartbeat`

**When:** Every 24 hours via WordPress cron

**Frequency:** Daily (scheduled via `wp_schedule_event`)

**Authentication:** None (publicly accessible)

**Request Body:**
```json
{
  "siteUrl": "https://www.example.com",
  "pluginVersion": "1.0.0",
  "wordpressVersion": "6.5.0",
  "phpVersion": "8.1.0",
  "activeModules": [
    "app-passwords",
    "lap-meta"
  ],
  "moduleStatus": {
    "app-passwords": {
      "enabled": true,
      "version": "1.0.0"
    },
    "lap-meta": {
      "enabled": true,
      "version": "1.0.0"
    }
  }
}
```

**Use Case:** Daily heartbeat to confirm sites are healthy, detect stale sites, and track version drift.

---

### 4. Error Report

**Endpoint:** `POST /wordpress-sites/error`

**When:** Plugin encounters an error

**Frequency:** As needed (currently used for critical errors only)

**Authentication:** None (publicly accessible)

**Request Body:**
```json
{
  "siteUrl": "https://www.example.com",
  "pluginVersion": "1.0.0",
  "errorType": "update_failed",
  "errorMessage": "Failed to download update from GitHub",
  "stackTrace": "..."
}
```

**Error Types:**
- `update_failed` - Auto-update failed
- `module_error` - Module initialization failed
- `rest_api_error` - REST API registration failed
- Custom types as needed

**Use Case:** Get immediate alerts when something goes wrong, track error rates, detect patterns.

---

## Request Format

### HTTP Headers

All requests include:

```http
POST /wordpress-sites/endpoint HTTP/1.1
Host: server.speakeasymarketinginc.com
Content-Type: application/json
User-Agent: WordPress/6.5; https://www.example.com
```

### Authentication

**No authentication required!**

All WordPress plugin reporting endpoints (`/register`, `/heartbeat`, `/update`, `/error`) are publicly accessible. This allows:
- WordPress plugins to report without API keys
- Automation scripts to run without credentials
- Simple, zero-configuration deployment

The plugin sends a `pluginApiKey` in the request body for site identification purposes, but this is NOT used for authentication - it's just a reference field stored in the database.

### Request Properties

- **Non-blocking** (except registration) - WordPress doesn't wait for response
- **5-15 second timeout** - Requests abort after timeout
- **JSON encoded** - Body is `wp_json_encode()`d
- **Fail-silent** - Errors logged to PHP error log, never shown to users
- **No authentication** - All reporting endpoints are publicly accessible

---

## Response Handling

### Expected Responses

**Registration endpoint (`/register`):**
- This is blocking - plugin waits for confirmation
- Must return HTTP 200 or 201 to stop retries
- Any other status code triggers hourly retry

**Other endpoints (`/heartbeat`, `/update`, `/error`):**
- These are non-blocking - plugin doesn't wait for response
- Any HTTP response is acceptable (plugin doesn't check it)
- Backend should return HTTP 200 OK for successful processing

### Error Responses

If the API is unreachable or returns an error, the plugin logs it locally but **continues working normally**.

Example error log entries:
```
[20-Jun-2026 12:00:00 UTC] WP Speakeasy: Registration failed with status 401 - {"error":"Invalid API key"}
[20-Jun-2026 12:05:00 UTC] WP Speakeasy: API request failed: Connection timed out
```

**The WordPress site is never affected by API failures.**

---

## Backend Implementation

**The Speakeasy backend is already implemented and ready to receive plugin reports.**

- **Production Backend:** `https://server.speakeasymarketinginc.com/api/wordpress-sites`
- **Backend Documentation:** See the WordPress Plugin Integration Guide provided separately
- **Database Schema:** `wordpress_sites` table (see backend docs)
- **Authentication:** FlexAuth with `X-API-Key` header
- **Required Permission:** `wordpress:plugin:report`

### Getting Your API Key

1. Log into Speakeasy: https://server.speakeasymarketinginc.com
2. Navigate to API Keys page
3. Create a new API key (starts with `spk_`)
4. Ensure your user/role has `wordpress:plugin:report` permission
5. Add the key to each WordPress site's `wp-config.php`:

```php
define( 'SPEAKEASY_USER_API_KEY', 'spk_your_api_key_here' );
```

### Testing the Integration

```bash
# Test registration (replace with your actual API key)
curl -X POST https://server.speakeasymarketinginc.com/api/wordpress-sites/register \
  -H "X-API-Key: spk_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "siteUrl": "https://test-site.com",
    "pluginApiKey": "test_key_64_chars",
    "pluginVersion": "1.0.0",
    "wordpressVersion": "6.5.0",
    "phpVersion": "8.1.0",
    "activeModules": ["app-passwords"],
    "moduleStatus": {
      "app-passwords": {
        "enabled": true,
        "version": "1.0.0"
      }
    }
  }'

# Should return HTTP 200/201 with the created wordpress_sites record
```

---

## Security Considerations

### API Key Security

**DO:**
- ✅ Keep your `SPEAKEASY_USER_API_KEY` secret
- ✅ Store it in `wp-config.php` (outside web root)
- ✅ Use HTTPS for all API communication
- ✅ Use the same API key across all your WordPress sites (simplifies management)
- ✅ Rotate API keys periodically via Speakeasy dashboard

**DON'T:**
- ❌ Commit wp-config.php to public git repositories
- ❌ Share API keys via email or chat
- ❌ Hardcode API keys in plugin code
- ❌ Use API keys without the `wordpress:plugin:report` permission

### Plugin-Generated API Keys

Each WordPress site generates its own 64-character random API key for identification purposes. This key:
- Is stored in the WordPress database (`speakeasy_api_key` option)
- Is sent to the backend during registration
- Is NOT used for authentication (authentication uses `SPEAKEASY_USER_API_KEY`)
- Uniquely identifies the WordPress site in the `wordpress_sites` table

### Data Privacy

The plugin **never sends**:
- User passwords or credentials
- User email addresses or PII
- Post content or page data
- Database credentials
- WordPress admin passwords

The plugin **only sends**:
- Site URL (public information)
- Plugin version (public information)
- WordPress/PHP versions (public information)
- Active modules list (configuration data)
- Module status (configuration data)
- Error messages (diagnostic data only)

---

## Troubleshooting

### Site Not Registering with Backend

**Check registration status:**
```bash
wp option get speakeasy_activation_reported
# Returns: "yes" (registered) or empty (pending/failed)

wp option get speakeasy_api_key
# Returns: 64-character hex string (plugin's generated key)
```

**Check error logs:**
```bash
# WordPress error log
tail -f /path/to/wordpress/wp-content/debug.log | grep "WP Speakeasy"

# Common errors:
# - "Registration failed with status 401" (backend API key issue)
# - "Registration failed with status 403" (missing permission)
# - Connection timeout errors
```

**Manually trigger registration:**
```bash
# Clear registration flag to force retry
wp option delete speakeasy_activation_reported

# Trigger retry immediately
wp cron event run speakeasy_retry_activation_report
```

### Health Checks Not Running

**Check WordPress cron:**
```bash
# List scheduled events
wp cron event list

# Look for: speakeasy_daily_health_check
```

**Force health check:**
```bash
# Trigger health check manually
wp cron event run speakeasy_daily_health_check
```

**Check if health check ran:**
```bash
# Check error log for confirmation
tail /path/to/debug.log | grep "health"
```

### HTTP Errors (401/403/500)

**If you see HTTP errors in the logs:**

Since the endpoints are publicly accessible, 401/403 errors shouldn't occur. If they do:

1. **Check that you're using the correct endpoint:**
   - Should be: `https://server.speakeasymarketinginc.com/api/wordpress-sites/register`
   - NOT: `https://server.speakeasymarketinginc.com/wordpress-sites/register`

2. **Check backend status:**
   - Visit: https://server.speakeasymarketinginc.com/health
   - Ensure the backend is running

3. **Check WordPress can make outbound requests:**
   ```bash
   wp eval "var_dump(wp_remote_get('https://server.speakeasymarketinginc.com'));"
   ```

**Enable debug mode:**
```php
// Add to wp-config.php temporarily
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

---

## Database Schema

The Speakeasy backend stores WordPress sites in the `wordpress_sites` table.

**Key fields:**
- `siteUrl` - WordPress site URL (unique)
- `pluginApiKey` - Plugin's auto-generated 64-char key
- `pluginVersion`, `wordpressVersion`, `phpVersion` - Version tracking
- `activeModules`, `moduleStatus` - Module configuration (JSONB)
- `status` - Site status: active, stale, error, no-plugin, inactive
- `lastHeartbeat` - Last health check timestamp
- `errorCount`, `errorHistory` - Error tracking

See the WordPress Plugin Integration Guide (backend documentation) for full schema details.

---

## Monitoring Dashboard

The Speakeasy backend provides a dashboard for monitoring all WordPress sites:

1. **Site List View**
   - View all registered WordPress sites
   - Filter by status (active, stale, error)
   - See current plugin versions

2. **Site Detail View**
   - Last heartbeat timestamp
   - Active modules
   - Error history
   - Version tracking

3. **Automatic Alerts**
   - Sites marked "stale" after 48 hours without heartbeat
   - Error reports tracked in `errorHistory` field

Access the dashboard at: https://server.speakeasymarketinginc.com

(Requires login with `system:wordpress:view` or `system:wordpress:manage` permission)

---

## Quick Start Guide

**To integrate a WordPress site with Speakeasy monitoring:**

1. **Install WP Speakeasy plugin** on WordPress site
   ```bash
   wp plugin install /path/to/wp-speakeasy.zip
   ```

2. **Activate the plugin:**
   ```bash
   wp plugin activate wp-speakeasy
   ```

3. **Verify registration:**
   ```bash
   # Wait a few seconds, then check
   wp option get speakeasy_activation_reported
   # Should return: yes

   # Check the Speakeasy dashboard
   # Visit: https://server.speakeasymarketinginc.com
   # Site should appear in WordPress Sites list
   ```

**That's it!** The plugin will now:
- ✅ Send daily heartbeats automatically
- ✅ Report errors automatically
- ✅ Report version updates automatically
- ✅ Auto-register with zero configuration

No API keys to manage, no wp-config.php changes needed!

---

## Support

For issues or questions:

- **GitHub Issues:** https://github.com/speakeasy/wp-speakeasy/issues
- **Speakeasy Dashboard:** https://server.speakeasymarketinginc.com
- **Backend Documentation:** See WordPress Plugin Integration Guide

---

## Changelog

### v1.0.1 (2026-06-20)
- Updated to match Speakeasy production backend
- Changed authentication from Bearer tokens to X-API-Key header
- Updated field names to camelCase (siteUrl, pluginVersion, etc.)
- Endpoints now: /register, /heartbeat, /update, /error
- Requires `SPEAKEASY_USER_API_KEY` configuration

### v1.0.0 (2026-06-20)
- Initial release with auto-registration
- Daily health checks
- Error reporting
- Update tracking
