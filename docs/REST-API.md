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

## LAP Plugin Variants

**Start here before calling any LAP endpoint.**

The LAP plugin exists in two versions, and they store the same content under different meta keys. The rename was wholesale — squashed lowercase in the legacy version, underscore-separated in the modern one — and the two sets do not overlap:

| Content | Legacy key | Modern key |
|---|---|---|
| Main heading | `spk_mainheading` | `spk_main_heading` |
| Video code | `spk_videocode` | `spk_video_code` |
| CTA text | `spk_calltoactiontext` | `spk_call_to_action_box_text` |

Both versions ship a page template named `localareapage.php`, so the variant **cannot** be identified by template name. The only reliable signal is which meta keys are actually present on the page.

Each variant has its own route, speaking its own key names:

| Variant | Route |
|---|---|
| modern | `speakeasy/v1/lap-meta/{page_id}` |
| legacy_v1 | `speakeasy/v1/lap-meta/legacy_v1/{page_id}` |

They are deliberately not unified behind one translating endpoint. The variants differ in **shape** as well as spelling — the legacy phone number is a plain string where the modern one is a repeater of objects, and legacy's three fixed content blocks have no counterpart to the modern `spk_gridbox_repeater` — so translation would need per-field conversion with gaps in both directions.

> **Why this matters:** at the storage layer, writing modern keys to a legacy page does not fail. WordPress stores the value under the new key perfectly happily and the legacy template — which only ever reads the old key — renders exactly as before. The write succeeds and the page never changes.
>
> Every route therefore guards its own variant and returns `400 variant_mismatch` rather than accepting a write that would do nothing. You still want to confirm the variant first, but getting it wrong is now a loud error rather than a silent no-op.

Both routes apply the same rule:

> Refuse when the page's own variant contradicts the route. When the page has no variant of its own, trust the route unless the **site's** variant contradicts it.

The second half is what lets a freshly created page be populated in one pass: a page with no LAP meta has no variant to contradict the route, so the write is allowed — unless the rest of the site is plainly the other variant, in which case the caller is on the wrong route and is told so.

---

### GET — Site variant

**Endpoint:** `GET /wp-json/speakeasy/v1/lap-variant`

**Authentication:** Plugin API key via `X-Speakeasy-API-Key` header

Returns the dominant variant across every LAP page on the site. Cost is a fixed two queries regardless of how many LAP pages exist.

#### Request

```http
GET /wp-json/speakeasy/v1/lap-variant HTTP/1.1
Host: yoursite.com
X-Speakeasy-API-Key: your_plugin_api_key_here
```

#### Success Response (200 OK)

```json
{
  "variant": "legacy_v1",
  "mixed": false,
  "counts": {
    "legacy_v1": 12,
    "modern": 0,
    "ambiguous": 0,
    "undetermined": 1
  },
  "total_lap_pages": 13
}
```

| Field | Meaning |
|---|---|
| `variant` | Dominant determinate variant — `legacy_v1`, `modern`, or `undetermined` if the site has no identifiable LAP pages. Ties resolve to `legacy_v1`. |
| `mixed` | `true` when both variants are present. **Do not trust `variant` for individual pages when this is `true`** — resolve each page with the per-page call. |
| `counts.ambiguous` | Pages carrying both key styles. These need manual cleanup; the write endpoints refuse them. |
| `counts.undetermined` | Pages with no LAP meta yet — usually newly created and not yet filled in. |
| `total_lap_pages` | Pages using the `localareapage.php` template. |

---

### GET — Page variant

**Endpoint:** `GET /wp-json/speakeasy/v1/lap-variant/{page_id}`

**Authentication:** Plugin API key via `X-Speakeasy-API-Key` header

#### Request

```http
GET /wp-json/speakeasy/v1/lap-variant/4043 HTTP/1.1
Host: yoursite.com
X-Speakeasy-API-Key: your_plugin_api_key_here
```

#### Success Response (200 OK)

```json
{
  "page_id": 4043,
  "variant": "legacy_v1",
  "markers": {
    "legacy_v1": ["spk_mainheading", "spk_calltoactiontext"],
    "modern": []
  }
}
```

`markers` lists the keys the verdict was based on. On an `ambiguous` page it names both sides of the conflict, which is what you need to clean it up.

| Verdict | Meaning | What to do |
|---|---|---|
| `legacy_v1` | Only legacy keys present | Use the `legacy_v1` route |
| `modern` | Only modern keys present | Use the modern route |
| `ambiguous` | Both key styles present | Resolve manually — both write routes refuse this page |
| `undetermined` | No LAP meta at all | Readable but not writable; populate the page in wp-admin first |

#### Error responses

| Status | Code | Cause |
|---|---|---|
| 401 | `missing_api_key` | `X-Speakeasy-API-Key` header not sent |
| 401 | `invalid_api_key` | Key sent but does not match stored key |
| 404 | `page_not_found` | No page exists with the given ID |
| 400 | `not_lap_page` | Page exists but does not use the `localareapage.php` template |

---

### Choosing a route

```javascript
const BASE = 'https://example.com/wp-json/speakeasy/v1';
const KEY  = 'spk_1234567890abcdef';

const routes = {
  modern:    pageId => `${BASE}/lap-meta/${pageId}`,
  legacy_v1: pageId => `${BASE}/lap-meta/legacy_v1/${pageId}`
};

// `intended` is the variant you mean to write, used only when the page is new
// and has nothing to identify it by.
async function routeFor(pageId, intended = 'legacy_v1') {
  const res = await fetch(`${BASE}/lap-variant/${pageId}`, {
    headers: { 'X-Speakeasy-API-Key': KEY }
  });
  const { variant } = await res.json();

  // The page carries both key styles — needs a human, not a guess.
  if (variant === 'ambiguous') {
    throw new Error(`Page ${pageId} carries both legacy and modern keys`);
  }

  // 'undetermined' just means the page is empty, which is normal for one you
  // created a moment ago. The endpoint accepts the write unless the site
  // contradicts the route, so there is nothing to resolve here.
  return routes[variant === 'undetermined' ? intended : variant](pageId);
}
```

On a site where `mixed` is `false`, call `/lap-variant` once and reuse the answer for every page instead of checking each one.

---

## LAP Meta Fields (modern variant)

> Serves pages using the **modern** LAP field set. If `/lap-variant` reports `legacy_v1` for the site or page, use [LAP Meta Fields (legacy_v1)](#lap-meta-fields-legacy_v1) instead — this route refuses legacy pages with `400 variant_mismatch`, because its keys do not exist on them and writing them would change nothing on the rendered page.

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
    ├── confirms the page's variant matches this route
    │      ├── legacy page      → 400 variant_mismatch
    │      ├── both key styles  → 400 ambiguous_field_variant
    │      └── no LAP meta      → allowed, unless the SITE is plainly legacy
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
  "variant": "modern",
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
  "variant": "modern",
  "updated": ["spk_main_heading", "spk_cta_bg_color"],
  "failed": []
}
```

`updated` lists fields that were written and confirmed by reading them back. `failed` lists fields
that reported success but did not actually persist — this happens when a field's Meta Box
configuration on this site doesn't match the shape being sent (for example, an image sub-field that
isn't set up to store an array of attachment IDs). A field only appears in `failed` if a non-empty
value was sent for it; empty values are always treated as `updated` since there's nothing to verify
a round trip against. A non-empty `failed` array means the write needs attention in this site's Meta
Box field group configuration, not in the caller.

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
| 400 | `variant_mismatch` | Page uses the legacy field set, or has no LAP meta and sits on a plainly legacy site — use the legacy_v1 route |
| 400 | `ambiguous_field_variant` | Page carries both legacy and modern keys |
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
- The page must not identify as `legacy_v1` or `ambiguous` — a page with no LAP meta yet is accepted
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

**400 variant_mismatch**
The page uses legacy keys, or it has no LAP meta at all and the rest of the site is plainly legacy. Send it to `speakeasy/v1/lap-meta/legacy_v1/{page_id}` instead. The error body's `detected_from` says whether the page itself or the surrounding site decided it.

**400 ambiguous_field_variant**
The page carries both key styles. The `markers` object names the conflicting keys; resolve it in wp-admin before writing via the API.

---

## LAP Meta Fields (legacy_v1)

> Serves pages using the **legacy** LAP field set. Confirm the variant with [`/lap-variant`](#lap-plugin-variants) before calling this route.

Same purpose as the modern LAP endpoint, but speaking the legacy plugin's own key names and value shapes. Nothing is translated — what you send and receive is exactly what is stored, and exactly what the legacy template reads.

### How it fits together

```
Your request
    │
    ▼
speakeasy/v1/lap-meta/legacy_v1/{page_id}
    │
    ├── validates API key (X-Speakeasy-API-Key header)
    ├── confirms page exists and uses localareapage.php template
    ├── confirms Meta Box plugin is active
    ├── confirms the page's variant matches this route
    │      ├── modern page      → 400 variant_mismatch
    │      ├── both key styles  → 400 ambiguous_field_variant
    │      └── no LAP meta      → allowed, unless the SITE is plainly modern
    │
    ├── GET  → text fields via rwmb_meta(),     images via get_post_meta()
    └── POST → text fields via rwmb_set_meta(), images via update_post_meta()
```

**Why images take a different path:** the legacy template reads every image with `get_post_meta( $post->ID, 'spk_bannerbgimg', true )` and passes the result straight to `wp_get_attachment_url()`. It expects a bare attachment ID, not the array Meta Box would store. Writing these through Meta Box would persist a value the template cannot read — a write that reports success and renders nothing. So image fields go through `update_post_meta()` and are sent and returned as plain integers.

---

### GET — Read all field values

**Endpoint:** `GET /wp-json/speakeasy/v1/lap-meta/legacy_v1/{page_id}`

**Authentication:** Plugin API key via `X-Speakeasy-API-Key` header

#### Request

```http
GET /wp-json/speakeasy/v1/lap-meta/legacy_v1/4043 HTTP/1.1
Host: yoursite.com
X-Speakeasy-API-Key: your_plugin_api_key_here
```

#### Success Response (200 OK)

```json
{
  "page_id": 4043,
  "variant": "legacy_v1",
  "fields": {
    "spk_mainheading": "Hartford DUI Attorney",
    "spk_videolefttext": "<p>Some rich text...</p>",
    "spk_videocode": "dQw4w9WgXcQ",
    "spk_selectvideo": "Youtube",
    "spk_calltoactiontext": "Call us today ",
    "spk_calltoactionnumber": "860-555-0100",
    "spk_bottomsectionheading": "Local Experience",
    "spk_bottomsectioncontent": "<p>Body copy...</p>",
    "spk_bottomsectioncall2": 1,
    "spk_mapsection": 1,
    "spk_mapheading": "Find Our Office",
    "spk_bannerbgimg": 4321,
    "spk_calltoactionimg": 987
  }
}
```

All 26 fields are always present in the response; the example above is abridged. `variant` is always `legacy_v1` — assert on it if you want a guard against calling the wrong route.

---

### POST — Update fields (partial)

Only the fields you include in the request body are written. Fields you omit are left exactly as they are.

**Endpoint:** `POST /wp-json/speakeasy/v1/lap-meta/legacy_v1/{page_id}`

**Authentication:** Plugin API key via `X-Speakeasy-API-Key` header

#### Request

```http
POST /wp-json/speakeasy/v1/lap-meta/legacy_v1/4043 HTTP/1.1
Host: yoursite.com
X-Speakeasy-API-Key: your_plugin_api_key_here
Content-Type: application/json

{
  "spk_mainheading": "Hartford DUI Attorney",
  "spk_calltoactionnumber": "860-555-0100"
}
```

#### Success Response (200 OK)

```json
{
  "page_id": 4043,
  "variant": "legacy_v1",
  "updated": ["spk_mainheading", "spk_calltoactionnumber"],
  "failed": []
}
```

`updated` and `failed` behave exactly as on the modern endpoint: every non-empty write is read back through the same path the template uses, and anything that did not persist is reported in `failed` rather than counted as success. Empty values are always reported as `updated`, since there is no round trip to verify.

---

### Field reference

26 fields. Booleans are stored and returned as `1`/`0`, matching what the template truthiness-checks. Image fields are bare attachment IDs — send an integer, receive an integer, `0` when unset.

| Field | Value type | Description |
|---|---|---|
| `spk_mainheading` | string | Main heading above the video section |
| `spk_videolefttext` | string (HTML) | Rich text beside the video |
| `spk_videocode` | string | YouTube or Vimeo video ID |
| `spk_selectvideo` | string enum | Video platform — must be `Youtube` or `Vimeo` |
| `spk_videoimg` | integer | Attachment ID, shown when no video code is set |
| `spk_bannerbgimg` | integer | Attachment ID for the page banner background |
| `spk_calltoactiontext` | string | Text in the call-to-action band |
| `spk_calltoactionnumber` | string | Phone number in the call-to-action band |
| `spk_calltoactionimg` | integer | Attachment ID for the phone icon |
| `spk_bottomsectionheading` | string | Heading for content block 1 |
| `spk_bottomsectioncontent` | string (HTML) | Body of content block 1 |
| `spk_bottomsectioncontentimg` | integer | Attachment ID for content block 1 |
| `spk_bottomsectionheading2` | string | Heading for content block 2 |
| `spk_bottomsectioncontent2` | string (HTML) | Body of content block 2 |
| `spk_bottomsectioncontentimg2` | integer | Attachment ID for content block 2 |
| `spk_bottomsectioncall2` | boolean | Show a second call-to-action band after block 2 |
| `spk_bottomsectionheading3` | string | Heading for content block 3 |
| `spk_bottomsectioncontent3` | string (HTML) | Body of content block 3 |
| `spk_bottomsectioncontentimg3` | integer | Attachment ID for content block 3 |
| `spk_mapsection` | boolean | Show the map section |
| `spk_mapheading` | string | Map section heading |
| `spk_mapaddress` | string | Address shown beside the map |
| `spk_mapphone` | string | Phone number shown beside the map |
| `spk_mapfax` | string | Fax number shown beside the map |
| `spk_mapiframe` | string (HTML) | Map embed markup |
| `spk_mapimg` | integer | Attachment ID for the map image |

**No equivalent to the modern `spk_gridbox_repeater`.** Legacy pages use the three fixed `spk_bottomsectioncontent*` blocks instead. Sending `spk_gridbox_repeater` to this route returns `unknown_field`.

---

### Error responses

| Status | Code | Cause |
|---|---|---|
| 401 | `missing_api_key` | `X-Speakeasy-API-Key` header not sent |
| 401 | `invalid_api_key` | Key sent but does not match stored key |
| 500 | `api_key_not_configured` | Plugin API key has not been set on this site |
| 404 | `page_not_found` | No page exists with the given ID |
| 400 | `not_lap_page` | Page exists but does not use the `localareapage.php` template |
| 400 | `variant_mismatch` | Page uses the modern field set — use the modern route |
| 400 | `ambiguous_field_variant` | Page carries both legacy and modern keys |
| 400 | `unknown_field` | POST body contains a key not in the legacy field list |
| 400 | `invalid_field_value` | `spk_selectvideo` is not `Youtube` or `Vimeo` |
| 503 | `metabox_unavailable` | Meta Box plugin is not active on this site |

Ambiguity errors name the conflict rather than just reporting one:

```json
{
  "code": "ambiguous_field_variant",
  "message": "This page carries both legacy and modern LAP meta. Resolve it manually before writing via the API.",
  "data": {
    "status": 400,
    "markers": {
      "legacy_v1": ["spk_mainheading"],
      "modern": ["spk_main_heading"]
    }
  }
}
```

Mismatch errors say what was detected and what decided it:

```json
{
  "code": "variant_mismatch",
  "message": "This page has no LAP meta yet and this site uses the modern LAP field set. Use speakeasy/v1/lap-meta/4043 instead.",
  "data": {
    "status": 400,
    "detected_variant": "modern",
    "route_variant": "legacy_v1",
    "detected_from": "site"
  }
}
```

| Field | Meaning |
|---|---|
| `detected_variant` | The variant that contradicts the route |
| `route_variant` | The variant this route serves |
| `detected_from` | `page` when the page's own meta decided it, `site` when the surrounding site did |

`detected_from` is the one to branch on: `page` means this page belongs on the other route, while `site` means the page itself is empty and the rest of the site disagrees with your choice.

---

### Examples

**cURL — read fields**
```bash
curl https://example.com/wp-json/speakeasy/v1/lap-meta/legacy_v1/4043 \
  -H "X-Speakeasy-API-Key: spk_1234567890abcdef"
```

**cURL — update text and an image**
```bash
curl -X POST https://example.com/wp-json/speakeasy/v1/lap-meta/legacy_v1/4043 \
  -H "X-Speakeasy-API-Key: spk_1234567890abcdef" \
  -H "Content-Type: application/json" \
  -d '{
    "spk_mainheading": "Hartford DUI Attorney",
    "spk_calltoactionnumber": "860-555-0100",
    "spk_calltoactionimg": 987,
    "spk_mapsection": true
  }'
```

**Python — confirm the variant, then write**
```python
import requests

BASE = 'https://example.com/wp-json/speakeasy/v1'
HEADERS = {
    'X-Speakeasy-API-Key': 'spk_1234567890abcdef',
    'Content-Type': 'application/json'
}
PAGE = 4043

variant = requests.get(f'{BASE}/lap-variant/{PAGE}', headers=HEADERS).json()['variant']

if variant == 'legacy_v1':
    url = f'{BASE}/lap-meta/legacy_v1/{PAGE}'
elif variant == 'modern':
    url = f'{BASE}/lap-meta/{PAGE}'
else:
    raise SystemExit(f'Page {PAGE} is {variant} — resolve it before writing')

r = requests.post(url, json={'spk_mainheading': 'Hartford DUI Attorney'}, headers=HEADERS)
print('Updated:', r.json()['updated'], 'Failed:', r.json()['failed'])
```

---

### Prerequisites

- Meta Box plugin must be **active** on the site
- The page must use the **`localareapage.php`** page template
- The page must not identify as `modern` or `ambiguous` — a page with no LAP meta yet is accepted, unless the rest of the site is plainly modern
- The plugin API key must be configured (Settings → WP Speakeasy)

### Troubleshooting

**400 variant_mismatch**
The page uses modern keys. Send it to `speakeasy/v1/lap-meta/{page_id}` instead. The error body's `data` carries `detected_variant`, `route_variant`, and `detected_from`.

**400 ambiguous_field_variant**
The page has both key styles, usually from a partial migration or a previous write to the wrong route. The `markers` object names the conflicting keys. Decide which set the template actually renders, clear the other in wp-admin, then retry.

**400 variant_mismatch on a brand-new page**
The page itself has no LAP meta, so the route was checked against the rest of the site — and the site is plainly the other variant. Either you are on the wrong route, or this really is the site's first page of this variant, in which case set one marker field (`spk_mainheading`, `spk_calltoactiontext`, or `spk_videolefttext`) in wp-admin so the page identifies itself, then continue via the API.

Note that creating a page and populating it in one pass **does** work — a fresh page is written on the route's say-so whenever the site agrees, is mixed, or has no other LAP pages to compare against.

**GET returns all empty fields with no error**
The page is `undetermined` — it exists and uses the LAP template but has no content yet. This is a valid read, not an error.

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
