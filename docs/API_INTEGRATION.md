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

### WordPress Configuration

Add these constants to each WordPress site's `wp-config.php`:

```php
/**
 * WP Speakeasy API Configuration
 *
 * SPEAKEASY_API_ENDPOINT: Your backend monitoring API base URL
 * SPEAKEASY_API_TOKEN: Authentication token for this site
 */
define( 'SPEAKEASY_API_ENDPOINT', 'https://api.speakeasy.com/wp-plugin' );
define( 'SPEAKEASY_API_TOKEN', 'spk_xxxxxxxxxxxxxxxxxxxx' );
```

### Environment Variables (Alternative)

For containerized deployments:

```bash
# .env file
SPEAKEASY_API_ENDPOINT=https://api.speakeasy.com/wp-plugin
SPEAKEASY_API_TOKEN=spk_xxxxxxxxxxxxxxxxxxxx
```

Then in `wp-config.php`:

```php
define( 'SPEAKEASY_API_ENDPOINT', getenv('SPEAKEASY_API_ENDPOINT') );
define( 'SPEAKEASY_API_TOKEN', getenv('SPEAKEASY_API_TOKEN') );
```

### Per-Site Tokens (Recommended)

Generate unique tokens for each WordPress site to:
- Track which site sent each request
- Revoke access per site if needed
- Prevent token leakage across sites

```php
// Site 1: www.lawfirm-a.com
define( 'SPEAKEASY_API_TOKEN', 'spk_site1_aaabbbccc' );

// Site 2: www.lawfirm-b.com
define( 'SPEAKEASY_API_TOKEN', 'spk_site2_dddeeefff' );
```

---

## API Endpoints

The plugin sends requests to four endpoints:

### 1. Activation Report

**Endpoint:** `POST /activation`

**When:** Plugin is activated on a WordPress site

**Frequency:** Once per activation (or reactivation)

**Request Body:**
```json
{
  "site": "https://www.example.com",
  "plugin_version": "1.0.0",
  "wordpress_version": "6.5.0",
  "php_version": "8.1.0",
  "timestamp": "2026-06-20 12:00:00"
}
```

**Use Case:** Track new plugin installations across your fleet.

---

### 2. Update Report

**Endpoint:** `POST /update`

**When:** Plugin successfully auto-updates to a new version

**Frequency:** Once per successful update

**Request Body:**
```json
{
  "site": "https://www.example.com",
  "plugin_version": "1.1.0",
  "status": "success",
  "timestamp": "2026-06-20 14:30:00"
}
```

**Status Values:**
- `success` - Update completed successfully
- `failed` - Update encountered an error (future enhancement)

**Use Case:** Monitor update rollout progress after releasing a new version.

---

### 3. Daily Health Check

**Endpoint:** `POST /health`

**When:** Every 24 hours via WordPress cron

**Frequency:** Daily (scheduled via `wp_schedule_event`)

**Request Body:**
```json
{
  "site": "https://www.example.com",
  "plugin_version": "1.0.0",
  "wordpress_version": "6.5.0",
  "php_version": "8.1.0",
  "active_modules": [
    "app-passwords",
    "lap-meta"
  ],
  "module_status": {
    "app-passwords": {
      "enabled": true,
      "version": "1.0.0",
      "name": "Application Passwords Enabler"
    },
    "lap-meta": {
      "enabled": true,
      "version": "1.0.0",
      "name": "LAP Meta Fields",
      "fields": 7
    }
  },
  "timestamp": "2026-06-20 09:00:00"
}
```

**Use Case:** Daily heartbeat to confirm sites are healthy and detect issues.

---

### 4. Error Report

**Endpoint:** `POST /error`

**When:** Plugin encounters an error

**Frequency:** As needed (currently used for critical errors only)

**Request Body:**
```json
{
  "site": "https://www.example.com",
  "plugin_version": "1.0.0",
  "error_type": "update_failed",
  "error_message": "Failed to download update from GitHub",
  "stack_trace": "...",
  "timestamp": "2026-06-20 15:45:00"
}
```

**Error Types:**
- `update_failed` - Auto-update failed
- `module_error` - Module initialization failed
- `rest_api_error` - REST API registration failed
- Custom types as needed

**Use Case:** Get immediate alerts when something goes wrong.

---

## Request Format

### HTTP Headers

All requests include:

```http
POST /endpoint HTTP/1.1
Host: api.speakeasy.com
Content-Type: application/json
Authorization: Bearer spk_xxxxxxxxxxxxxxxxxxxx
User-Agent: WordPress/6.5; https://www.example.com
```

### Authentication

**Method:** Bearer Token Authentication

The `SPEAKEASY_API_TOKEN` is sent in the `Authorization` header:

```
Authorization: Bearer spk_xxxxxxxxxxxxxxxxxxxx
```

Your backend should:
1. Verify the token exists
2. Validate it against your token database
3. Return `401 Unauthorized` if invalid
4. Return `200 OK` if valid (even if you don't process the data)

### Request Properties

All requests are:
- **Non-blocking** (`blocking: false`) - WordPress doesn't wait for response
- **5-second timeout** - Request aborts after 5 seconds
- **JSON encoded** - Body is `wp_json_encode()`d
- **Fail-silent** - Errors logged to PHP error log, not shown to users

---

## Response Handling

### Expected Responses

The plugin **does not wait for or process responses** because all requests are non-blocking.

Your API should return:

```http
HTTP/1.1 200 OK
Content-Type: application/json

{
  "status": "received"
}
```

### Error Responses

If your API returns an error, the plugin logs it locally but **continues working normally**.

Example error log entry:
```
[20-Jun-2026 12:00:00 UTC] WP Speakeasy: API request failed: HTTP request failed! 500 Internal Server Error
```

**The WordPress site is never affected by API failures.**

---

## Backend Implementation Examples

### Example 1: Simple Node.js/Express Endpoint

```javascript
const express = require('express');
const app = express();

app.use(express.json());

// Verify API token
function verifyToken(req, res, next) {
  const token = req.headers.authorization?.replace('Bearer ', '');

  if (!token || !isValidToken(token)) {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  req.siteToken = token;
  next();
}

// Health check endpoint
app.post('/wp-plugin/health', verifyToken, async (req, res) => {
  const { site, plugin_version, active_modules, timestamp } = req.body;

  // Save to database
  await db.healthChecks.create({
    site,
    plugin_version,
    active_modules,
    received_at: new Date(timestamp)
  });

  // Log for monitoring
  console.log(`Health check from ${site}: v${plugin_version}`);

  // Return success (site won't see this response)
  res.json({ status: 'received' });
});

// Update report endpoint
app.post('/wp-plugin/update', verifyToken, async (req, res) => {
  const { site, plugin_version, status } = req.body;

  await db.updates.create({
    site,
    version: plugin_version,
    status,
    received_at: new Date()
  });

  // Send alert if update failed
  if (status === 'failed') {
    await sendAlert(`Update failed on ${site}`);
  }

  res.json({ status: 'received' });
});

app.listen(3000);
```

### Example 2: Laravel API Route

```php
<?php
// routes/api.php

use Illuminate\Http\Request;
use App\Models\SiteHealth;
use App\Models\SiteUpdate;

Route::middleware('auth.token')->prefix('wp-plugin')->group(function () {

    // Health check
    Route::post('/health', function (Request $request) {
        SiteHealth::create([
            'site' => $request->site,
            'plugin_version' => $request->plugin_version,
            'wordpress_version' => $request->wordpress_version,
            'php_version' => $request->php_version,
            'active_modules' => $request->active_modules,
            'module_status' => $request->module_status,
            'timestamp' => $request->timestamp,
        ]);

        return response()->json(['status' => 'received']);
    });

    // Update report
    Route::post('/update', function (Request $request) {
        SiteUpdate::create([
            'site' => $request->site,
            'plugin_version' => $request->plugin_version,
            'status' => $request->status,
            'timestamp' => $request->timestamp,
        ]);

        return response()->json(['status' => 'received']);
    });

    // Activation report
    Route::post('/activation', function (Request $request) {
        // Track new installations
        return response()->json(['status' => 'received']);
    });

    // Error report
    Route::post('/error', function (Request $request) {
        // Send alert to Slack/Email
        Notification::send(new PluginErrorAlert($request->all()));
        return response()->json(['status' => 'received']);
    });
});
```

### Example 3: Google Cloud Function (Serverless)

```javascript
// index.js
const {Firestore} = require('@google-cloud/firestore');
const firestore = new Firestore();

exports.wpPluginHealth = async (req, res) => {
  // Verify token
  const token = req.headers.authorization?.replace('Bearer ', '');
  if (!verifyToken(token)) {
    return res.status(401).send('Unauthorized');
  }

  // Save to Firestore
  await firestore.collection('health_checks').add({
    ...req.body,
    received_at: new Date()
  });

  res.json({ status: 'received' });
};
```

---

## Security Considerations

### Token Security

**DO:**
- ✅ Use long, random tokens (min 32 characters)
- ✅ Store tokens in environment variables
- ✅ Use HTTPS for all API endpoints
- ✅ Rotate tokens periodically
- ✅ Use per-site tokens for better tracking

**DON'T:**
- ❌ Commit tokens to git repositories
- ❌ Use predictable tokens (e.g., "token123")
- ❌ Share tokens across multiple sites
- ❌ Send tokens via email or chat

### API Endpoint Security

**Recommendations:**
- Rate limiting (e.g., 10 requests/minute per site)
- IP allowlisting (if sites have static IPs)
- Request signature validation
- Token rotation mechanism
- Audit logging

### Data Privacy

The plugin **never sends**:
- User passwords or credentials
- User email addresses or PII
- Post content or page data
- Database credentials
- API keys or secrets

The plugin **only sends**:
- Site URL (public information)
- Plugin version (public information)
- WordPress/PHP versions (public information)
- Module status (configuration data)
- Error messages (diagnostic data)

---

## Troubleshooting

### API Not Receiving Requests

**Check WordPress configuration:**
```bash
# Via WP-CLI
wp eval "echo defined('SPEAKEASY_API_ENDPOINT') ? 'Configured' : 'Not configured';"
```

**Check error logs:**
```bash
# WordPress error log
tail -f /path/to/wordpress/wp-content/debug.log | grep "WP Speakeasy"
```

**Test API manually:**
```bash
curl -X POST https://api.speakeasy.com/wp-plugin/health \
  -H "Authorization: Bearer spk_xxxxxxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "site": "https://test.com",
    "plugin_version": "1.0.0",
    "timestamp": "2026-06-20 12:00:00"
  }'
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

### Authentication Failures

**Common issues:**
- Token mismatch (check wp-config.php)
- Bearer prefix missing in backend
- Token expired or revoked
- HTTPS required but using HTTP

**Debug:**
```php
// Add to wp-config.php temporarily
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

---

## Database Schema Examples

### MySQL/PostgreSQL

```sql
CREATE TABLE site_health_checks (
  id BIGSERIAL PRIMARY KEY,
  site VARCHAR(255) NOT NULL,
  plugin_version VARCHAR(20),
  wordpress_version VARCHAR(20),
  php_version VARCHAR(20),
  active_modules JSONB,
  module_status JSONB,
  timestamp TIMESTAMP,
  received_at TIMESTAMP DEFAULT NOW(),
  INDEX idx_site (site),
  INDEX idx_timestamp (timestamp)
);

CREATE TABLE site_updates (
  id BIGSERIAL PRIMARY KEY,
  site VARCHAR(255) NOT NULL,
  plugin_version VARCHAR(20),
  status VARCHAR(20),
  timestamp TIMESTAMP,
  received_at TIMESTAMP DEFAULT NOW(),
  INDEX idx_site_status (site, status)
);
```

### MongoDB

```javascript
db.createCollection("health_checks", {
  validator: {
    $jsonSchema: {
      required: ["site", "plugin_version", "timestamp"],
      properties: {
        site: { type: "string" },
        plugin_version: { type: "string" },
        active_modules: { type: "array" },
        module_status: { type: "object" }
      }
    }
  }
});

db.health_checks.createIndex({ "site": 1, "timestamp": -1 });
```

---

## Advanced: Building a Monitoring Dashboard

### Required Features

1. **Site List View**
   - Total sites with plugin installed
   - Current version distribution
   - Sites pending updates
   - Sites with errors

2. **Site Detail View**
   - Current plugin version
   - Last health check timestamp
   - Active modules
   - Update history
   - Error history

3. **Alerts**
   - Email/Slack when update fails
   - Daily digest of site statuses
   - Alert when site stops reporting (>48 hours)

4. **Analytics**
   - Update success rate over time
   - Average time to update after release
   - Module adoption rates
   - PHP/WordPress version distribution

### Technology Recommendations

**Backend:**
- Laravel (PHP) + MySQL
- Express.js (Node) + PostgreSQL
- Django (Python) + PostgreSQL
- Rails (Ruby) + PostgreSQL

**Frontend:**
- React + Chart.js
- Vue.js + Vuetify
- Next.js + Tailwind CSS

**Infrastructure:**
- Vercel/Netlify (frontend)
- Heroku/Render (backend)
- AWS Lambda + DynamoDB (serverless)
- Google Cloud Run + Firestore

---

## Support

For questions or issues with API integration:

- **GitHub Issues:** https://github.com/speakeasy/wp-speakeasy/issues
- **Email:** dev@speakeasy.com
- **Documentation:** https://docs.speakeasy.com/wp-plugin

---

## Changelog

### v1.0.0 (2026-06-20)
- Initial API integration documentation
- Four endpoints: activation, update, health, error
- Bearer token authentication
- Non-blocking requests
