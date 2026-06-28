# REST API Documentation

## Application Password Management

### Create Application Password

Programmatically create WordPress Application Passwords using the plugin API key.

**Endpoint:** `POST /wp-json/speakeasy/v1/application-passwords`

**Authentication:** Plugin API key via `X-Speakeasy-API-Key` header

#### Request

```http
POST /wp-json/speakeasy/v1/application-passwords HTTP/1.1
Host: yoursite.com
X-Speakeasy-API-Key: your_plugin_api_key_here
Content-Type: application/json

{
  "username": "admin",
  "name": "Speakeasy Backend Access"
}
```

#### Request Parameters

| Parameter | Type   | Required | Description                                                |
|-----------|--------|----------|------------------------------------------------------------|
| username  | string | Yes      | WordPress username to create Application Password for      |
| name      | string | No       | Name for the Application Password (auto-generated if omitted) |

#### Success Response (200 OK)

```json
{
  "success": true,
  "password": "abcd 1234 efgh 5678 ijkl 9012",
  "username": "admin",
  "user_id": 1,
  "name": "Speakeasy Backend Access"
}
```

#### Error Responses

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

**503 Service Unavailable**
```json
{
  "code": "app_passwords_unavailable",
  "message": "Application Passwords are not available on this site",
  "data": {
    "status": 503
  }
}
```

**500 Internal Server Error**
```json
{
  "code": "creation_failed",
  "message": "Failed to create Application Password",
  "data": {
    "status": 500
  }
}
```

#### Behavior

1. Validates API key matches the plugin's stored key
2. Checks if the specified user exists
3. Verifies Application Passwords are available for the user
4. **Revokes any existing Application Password with the same name**
5. Creates a new Application Password
6. Returns the password (shown only once - not stored or logged)
7. Logs the creation for audit purposes

#### Examples

**cURL**
```bash
curl -X POST https://example.com/wp-json/speakeasy/v1/application-passwords \
  -H "X-Speakeasy-API-Key: spk_1234567890abcdef" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "name": "Speakeasy Backend Access"
  }'
```

**JavaScript (Node.js)**
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

if (data.success) {
  console.log('Application Password:', data.password);
  console.log('Username:', data.username);
  console.log('User ID:', data.user_id);
}
```

**Python**
```python
import requests

url = 'https://example.com/wp-json/speakeasy/v1/application-passwords'
headers = {
    'X-Speakeasy-API-Key': 'spk_1234567890abcdef',
    'Content-Type': 'application/json'
}
payload = {
    'username': 'admin',
    'name': 'Speakeasy Backend Access'
}

response = requests.post(url, json=payload, headers=headers)
data = response.json()

if data.get('success'):
    print(f"Application Password: {data['password']}")
    print(f"Username: {data['username']}")
    print(f"User ID: {data['user_id']}")
```

**PHP**
```php
<?php
$url = 'https://example.com/wp-json/speakeasy/v1/application-passwords';
$api_key = 'spk_1234567890abcdef';

$response = wp_remote_post(
    $url,
    array(
        'body'    => wp_json_encode(
            array(
                'username' => 'admin',
                'name'     => 'Speakeasy Backend Access',
            )
        ),
        'headers' => array(
            'X-Speakeasy-API-Key' => $api_key,
            'Content-Type'        => 'application/json',
        ),
        'timeout' => 15,
    )
);

if ( ! is_wp_error( $response ) ) {
    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $data['success'] ) {
        echo 'Application Password: ' . $data['password'] . "\n";
        echo 'Username: ' . $data['username'] . "\n";
        echo 'User ID: ' . $data['user_id'] . "\n";
    }
}
```

---

## LAP Meta Fields

Read and write the custom meta fields on Local Area Pages (pages using the `localareapage.php` template). The endpoint talks directly to the Meta Box plugin API so it handles the internal storage format of group/clone fields correctly — you send and receive clean JSON without needing to know how Meta Box serialises data internally.

### How it fits together

```
Your request
    │
    ▼
speakeasy/v1/lap-meta/{page_id}  ← WP REST API route
    │
    ├── validates API key (X-Speakeasy-API-Key header)
    ├── confirms page exists and uses localareapage.php template
    ├── confirms Meta Box plugin is active
    │
    ├── GET  → rwmb_meta()     → returns current field values
    └── POST → rwmb_set_meta() → writes only the fields you send
```

The fields are defined by a Meta Box generator registration (prefix `spk_`) on the client's theme. This plugin does not own or create those fields — it exposes them over a secure API.

---

### GET — Read all field values

**Endpoint:** `GET /wp-json/speakeasy/v1/lap-meta/{page_id}`

**Authentication:** Plugin API key via `X-Speakeasy-API-Key` header

#### Request

```http
GET /wp-json/speakeasy/v1/lap-meta/42 HTTP/1.1
Host: yoursite.com
X-Speakeasy-API-Key: your_plugin_api_key_here
```

#### Success Response (200 OK)

```json
{
  "page_id": 42,
  "fields": {
    "spk_main_heading": "Welcome to Austin",
    "spk_upload_video_image": [123],
    "spk_hide_video_image": false,
    "spk_video_section_left_text": "<p>Some rich text...</p>",
    "spk_video_code": "dQw4w9WgXcQ",
    "spk_select_video": "Youtube",
    "spk_gridbox_repeater": [
      {
        "spk_heading": "Why Austin",
        "spk_image": [456],
        "spk_content": "<p>Body copy...</p>"
      }
    ],
    "spk_upload_call_to_action_phone_image": [789],
    "spk_call_to_action_box_text": "Call us today",
    "spk_add_phone_number": [
      { "spk_call_to_action_phone_number": "512-555-0100" }
    ],
    "spk_show_map_section": true,
    "spk_cta_bg_color": "#1a73e8",
    "spk_cta_bg_hvr_color": "#1557b0",
    "spk_heading_hide": false,
    "spk_hide_banner_image": false
  }
}
```

Image fields (`spk_upload_video_image`, `spk_upload_call_to_action_phone_image`, and image sub-fields inside repeaters) return arrays of WordPress attachment IDs.

---

### POST — Update fields (partial)

Only the fields you include in the request body are written. Fields you omit are left exactly as they are.

**Endpoint:** `POST /wp-json/speakeasy/v1/lap-meta/{page_id}`

**Authentication:** Plugin API key via `X-Speakeasy-API-Key` header

#### Request

```http
POST /wp-json/speakeasy/v1/lap-meta/42 HTTP/1.1
Host: yoursite.com
X-Speakeasy-API-Key: your_plugin_api_key_here
Content-Type: application/json

{
  "spk_main_heading": "Welcome to Austin",
  "spk_cta_bg_color": "#1a73e8"
}
```

#### Success Response (200 OK)

```json
{
  "page_id": 42,
  "updated": ["spk_main_heading", "spk_cta_bg_color"]
}
```

---

### Field reference

All fields use the `spk_` prefix. The table below shows the field key, the value type you send/receive over the API, and what it controls on the page.

| Field | Value type | Description |
|---|---|---|
| `spk_main_heading` | string | Main heading text |
| `spk_upload_video_image` | array of integers | Attachment IDs for the video section background image |
| `spk_hide_video_image` | boolean | When `true`, hides the video and background image entirely |
| `spk_video_section_left_text` | string (HTML) | Rich text displayed to the left of the video |
| `spk_video_code` | string | YouTube or Vimeo video ID |
| `spk_select_video` | string enum | Video platform — must be one of: `Youtube`, `Vimeo`, `Image` |
| `spk_gridbox_repeater` | array of objects | Two-column grid content blocks (see below) |
| `spk_upload_call_to_action_phone_image` | array of integers | Attachment IDs for the CTA phone icon image |
| `spk_call_to_action_box_text` | string | Call-to-action box label text |
| `spk_add_phone_number` | array of objects | Phone number entries (see below) |
| `spk_show_map_section` | boolean | When `true`, renders the map section |
| `spk_cta_bg_color` | string | CTA button background colour (any CSS colour value) |
| `spk_cta_bg_hvr_color` | string | CTA button hover background colour |
| `spk_heading_hide` | boolean | When `true`, hides the main heading |
| `spk_hide_banner_image` | boolean | When `true`, hides the default banner image |

#### spk_gridbox_repeater items

Each item in the array is an object with:

| Property | Type | Description |
|---|---|---|
| `spk_heading` | string | Block heading text |
| `spk_image` | array of integers | Attachment IDs for the block image |
| `spk_content` | string (HTML) | Block body copy (rich text) |

#### spk_add_phone_number items

Each item in the array is an object with:

| Property | Type | Description |
|---|---|---|
| `spk_call_to_action_phone_number` | string | Phone number to display |

---

### Error responses

All errors follow the standard WordPress REST API error envelope.

| Status | Code | Cause |
|---|---|---|
| 401 | `missing_api_key` | `X-Speakeasy-API-Key` header not sent |
| 401 | `invalid_api_key` | Key sent but does not match stored key |
| 500 | `api_key_not_configured` | Plugin API key has not been set on this site |
| 404 | `page_not_found` | No page exists with the given ID |
| 400 | `not_lap_page` | Page exists but does not use the `localareapage.php` template |
| 400 | `unknown_field` | POST body contains a key not in the allowed field list |
| 400 | `invalid_field_value` | `spk_select_video` value is not `Youtube`, `Vimeo`, or `Image` |
| 503 | `metabox_unavailable` | Meta Box plugin is not active on this site |

Example error response:

```json
{
  "code": "not_lap_page",
  "message": "This page does not use the localareapage.php template",
  "data": { "status": 400 }
}
```

---

### Examples

**cURL — read fields**
```bash
curl https://example.com/wp-json/speakeasy/v1/lap-meta/42 \
  -H "X-Speakeasy-API-Key: spk_1234567890abcdef"
```

**cURL — update two text fields**
```bash
curl -X POST https://example.com/wp-json/speakeasy/v1/lap-meta/42 \
  -H "X-Speakeasy-API-Key: spk_1234567890abcdef" \
  -H "Content-Type: application/json" \
  -d '{
    "spk_main_heading": "Welcome to Austin",
    "spk_cta_bg_color": "#1a73e8"
  }'
```

**cURL — update a repeater field**
```bash
curl -X POST https://example.com/wp-json/speakeasy/v1/lap-meta/42 \
  -H "X-Speakeasy-API-Key: spk_1234567890abcdef" \
  -H "Content-Type: application/json" \
  -d '{
    "spk_gridbox_repeater": [
      {
        "spk_heading": "Why Austin",
        "spk_image": [456],
        "spk_content": "<p>Austin is the capital of Texas.</p>"
      },
      {
        "spk_heading": "Local Experts",
        "spk_image": [457],
        "spk_content": "<p>Our team has 20 years of local experience.</p>"
      }
    ]
  }'
```

**JavaScript (Node.js)**
```javascript
const BASE = 'https://example.com/wp-json/speakeasy/v1';
const KEY  = 'spk_1234567890abcdef';
const PAGE = 42;

// Read
const get = await fetch(`${BASE}/lap-meta/${PAGE}`, {
  headers: { 'X-Speakeasy-API-Key': KEY }
});
const { fields } = await get.json();
console.log(fields.spk_main_heading);

// Partial update
const post = await fetch(`${BASE}/lap-meta/${PAGE}`, {
  method: 'POST',
  headers: {
    'X-Speakeasy-API-Key': KEY,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    spk_main_heading: 'Welcome to Austin',
    spk_show_map_section: true
  })
});
const { updated } = await post.json();
console.log('Updated:', updated);
```

**Python**
```python
import requests

BASE = 'https://example.com/wp-json/speakeasy/v1'
HEADERS = {
    'X-Speakeasy-API-Key': 'spk_1234567890abcdef',
    'Content-Type': 'application/json'
}
PAGE = 42

# Read
r = requests.get(f'{BASE}/lap-meta/{PAGE}', headers=HEADERS)
fields = r.json()['fields']
print(fields['spk_main_heading'])

# Partial update
r = requests.post(f'{BASE}/lap-meta/{PAGE}', json={
    'spk_main_heading': 'Welcome to Austin',
    'spk_show_map_section': True
}, headers=HEADERS)
print('Updated:', r.json()['updated'])
```

---

### Prerequisites

- Meta Box plugin must be **active** on the site
- The page must use the **`localareapage.php`** page template
- The plugin API key must be configured (Settings → WP Speakeasy)

### Troubleshooting

**503 metabox_unavailable**
Meta Box is not active. Install and activate the Meta Box plugin on the site.

**400 not_lap_page**
The page ID is correct but the page is not using the `localareapage.php` template. Check the page's template setting in WordPress admin (Page Attributes → Template).

**400 unknown_field**
The POST body contains a field key that is not in the allowed list. Check for typos — all keys must start with `spk_`.

**400 invalid_field_value**
`spk_select_video` was set to a value other than `Youtube`, `Vimeo`, or `Image`. The value is case-sensitive.

---

## SEO Meta Fields

Set SEO title and meta description for any WordPress page or post. This endpoint writes meta fields for all major SEO plugins (Yoast SEO, RankMath, AIOSEO, SEOPress) simultaneously, ensuring compatibility regardless of which plugin is active.

### POST — Update SEO Meta

**Endpoint:** `POST /wp-json/speakeasy/v1/seo-meta/{page_id}`

**Authentication:** Plugin API key via `X-Speakeasy-API-Key` header

#### Request

```http
POST /wp-json/speakeasy/v1/seo-meta/123 HTTP/1.1
Host: yoursite.com
X-Speakeasy-API-Key: your_plugin_api_key_here
Content-Type: application/json

{
  "seo_title": "Best Coffee Shops in Austin | Local Guide 2026",
  "seo_description": "Discover the top coffee shops in Austin, Texas. Expert reviews, locations, and insider tips from local coffee enthusiasts."
}
```

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| page_id | integer | Yes | WordPress page or post ID |
| seo_title | string | No* | SEO title (sanitized automatically) |
| seo_description | string | No* | SEO meta description (sanitized automatically) |

*At least one of `seo_title` or `seo_description` is required.

#### Success Response (200 OK)

```json
{
  "page_id": 123,
  "updated": ["seo_title", "seo_description"]
}
```

#### SEO Plugins Supported

This endpoint writes to meta keys for all four major SEO plugins:

| Plugin | Title Meta Key | Description Meta Key |
|--------|----------------|----------------------|
| **Yoast SEO** | `_yoast_wpseo_title` | `_yoast_wpseo_metadesc` |
| **RankMath** | `rank_math_title` | `rank_math_description` |
| **AIOSEO** | `_aioseo_title` | `_aioseo_description` |
| **SEOPress** | `_seopress_titles_title` | `_seopress_titles_desc` |

**Note:** AIOSEO meta is stored as JSON objects internally. The endpoint handles this automatically.

#### Error Responses

| Status | Code | Cause |
|--------|------|-------|
| 401 | `missing_api_key` | `X-Speakeasy-API-Key` header not sent |
| 401 | `invalid_api_key` | Key sent but does not match stored key |
| 500 | `api_key_not_configured` | Plugin API key has not been set on this site |
| 404 | `page_not_found` | No page or post exists with the given ID |
| 400 | `missing_fields` | Neither `seo_title` nor `seo_description` provided |

Example error response:

```json
{
  "code": "page_not_found",
  "message": "Page not found",
  "data": { "status": 404 }
}
```

#### Examples

**cURL — Set both title and description**
```bash
curl -X POST https://example.com/wp-json/speakeasy/v1/seo-meta/123 \
  -H "X-Speakeasy-API-Key: spk_1234567890abcdef" \
  -H "Content-Type: application/json" \
  -d '{
    "seo_title": "Best Coffee Shops in Austin | Local Guide 2026",
    "seo_description": "Discover the top coffee shops in Austin, Texas."
  }'
```

**cURL — Update only title**
```bash
curl -X POST https://example.com/wp-json/speakeasy/v1/seo-meta/123 \
  -H "X-Speakeasy-API-Key: spk_1234567890abcdef" \
  -H "Content-Type: application/json" \
  -d '{"seo_title": "Updated SEO Title"}'
```

**JavaScript (Node.js)**
```javascript
const response = await fetch('https://example.com/wp-json/speakeasy/v1/seo-meta/123', {
  method: 'POST',
  headers: {
    'X-Speakeasy-API-Key': 'spk_1234567890abcdef',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    seo_title: 'Best Coffee Shops in Austin | Local Guide 2026',
    seo_description: 'Discover the top coffee shops in Austin, Texas.'
  })
});

const data = await response.json();
console.log('Updated fields:', data.updated);
```

**Python**
```python
import requests

url = 'https://example.com/wp-json/speakeasy/v1/seo-meta/123'
headers = {
    'X-Speakeasy-API-Key': 'spk_1234567890abcdef',
    'Content-Type': 'application/json'
}
payload = {
    'seo_title': 'Best Coffee Shops in Austin | Local Guide 2026',
    'seo_description': 'Discover the top coffee shops in Austin, Texas.'
}

response = requests.post(url, json=payload, headers=headers)
data = response.json()
print(f"Updated fields: {data['updated']}")
```

**PHP**
```php
<?php
$url = 'https://example.com/wp-json/speakeasy/v1/seo-meta/123';
$api_key = 'spk_1234567890abcdef';

$response = wp_remote_post(
    $url,
    array(
        'body'    => wp_json_encode(
            array(
                'seo_title'       => 'Best Coffee Shops in Austin | Local Guide 2026',
                'seo_description' => 'Discover the top coffee shops in Austin, Texas.',
            )
        ),
        'headers' => array(
            'X-Speakeasy-API-Key' => $api_key,
            'Content-Type'        => 'application/json',
        ),
        'timeout' => 15,
    )
);

if ( ! is_wp_error( $response ) ) {
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    echo 'Updated: ' . implode( ', ', $data['updated'] ) . "\n";
}
```

#### Compatibility Notes

- **Works on any post type**: Pages, posts, and custom post types are all supported
- **No template restriction**: Unlike the LAP Meta endpoint, this works on any page regardless of template
- **Plugin-agnostic**: Writes meta for all SEO plugins simultaneously, so it works no matter which one is installed
- **WordPress ignores inactive plugins**: If a user doesn't have Yoast installed, the `_yoast_wpseo_*` meta is harmlessly stored but never used
- **Sanitization**: Title is sanitized with `sanitize_text_field()`, description with `sanitize_textarea_field()`

#### Common Use Cases

1. **Bulk SEO updates**: Programmatically set SEO meta when creating pages via API
2. **Integration with external tools**: Allow SEO platforms to update WordPress meta directly
3. **Migration scripts**: Transfer SEO data from other systems
4. **Automated content pipelines**: Set SEO fields as part of content generation workflows

---

## Finding Your Plugin API Key

1. Log in to WordPress admin
2. Navigate to **Settings → WP Speakeasy**
3. Find the "Backend Registration" section
4. Click **"Show Full Key"** to reveal the complete API key
5. Copy the key for use in API requests

## Security Considerations

- **Keep API key secret**: The API key allows creating Application Passwords for any user
- **Use HTTPS**: Always use HTTPS to protect API key in transit
- **Audit logging**: All password creations are logged with username, timestamp, and IP address
- **One-time display**: The generated password is returned only once - store it securely
- **Automatic revocation**: Creating a password with an existing name revokes the old one
- **Rate limiting**: Consider implementing rate limiting on your firewall/proxy

## Using the Generated Application Password

Once you have an Application Password, use it to authenticate REST API requests:

```bash
# Use with WordPress REST API
curl -X GET "https://example.com/wp-json/wp/v2/posts" \
  -u "admin:abcd 1234 efgh 5678 ijkl 9012"
```

The format is: `username:application_password`

## Troubleshooting

### 401 Unauthorized

- Verify you're using the correct plugin API key
- Check the key hasn't been regenerated (this happens on plugin reinstall)
- Ensure the `X-Speakeasy-API-Key` header is being sent

### 403 Forbidden

- User may have Application Passwords disabled
- Check user capabilities
- Verify the user account is active

### 503 Service Unavailable

- Application Passwords may not be available globally
- Ensure WordPress version is 5.6 or higher
- Check if HTTPS is enabled
- Verify the Application Passwords Enabler module is active

### Password Not Working

- Application Passwords are different from login passwords
- Ensure you're using the exact password returned by the API (spaces included)
- Check if the password was revoked
- Verify you're using it for REST API authentication, not admin login
