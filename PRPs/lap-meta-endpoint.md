# PRP: LAP Meta Fields REST Endpoint

## Summary

Add a REST API endpoint under `speakeasy/v1` that reads and writes Local Area Page (LAP) meta fields for pages using the `localareapage.php` template. Uses Meta Box's own API (`rwmb_meta` / `rwmb_set_meta`) to handle field serialization correctly, bypassing the need to model Meta Box's internal storage format in `register_meta` schemas.

---

## Background

Meta fields on LAP pages are managed by a Meta Box plugin instance using the `spk_` prefix. Meta Box serializes group/clone fields (e.g. `spk_gridbox_repeater`) in its own internal format, which does not map cleanly to `register_meta` REST schemas. Rather than reverse-engineering that format, this endpoint delegates read/write to Meta Box's own API functions.

---

## Endpoints

### GET `speakeasy/v1/lap-meta/{page_id}`

Returns all LAP meta field values for the given page.

**Auth:** `X-Speakeasy-API-Key` header (existing `verify_api_key` callback).

**Success response (200):**
```json
{
  "page_id": 42,
  "fields": {
    "spk_main_heading": "...",
    "spk_upload_video_image": [...],
    "spk_hide_video_image": false,
    "spk_video_section_left_text": "...",
    "spk_video_code": "...",
    "spk_select_video": "Youtube",
    "spk_gridbox_repeater": [...],
    "spk_upload_call_to_action_phone_image": [...],
    "spk_call_to_action_box_text": "...",
    "spk_add_phone_number": [...],
    "spk_show_map_section": false,
    "spk_cta_bg_color": "...",
    "spk_cta_bg_hvr_color": "...",
    "spk_heading_hide": false,
    "spk_hide_banner_image": false
  }
}
```

---

### POST `speakeasy/v1/lap-meta/{page_id}`

Partially updates LAP meta fields. Only fields present in the request body are written. Omitted fields are left unchanged.

**Auth:** `X-Speakeasy-API-Key` header.

**Request body (JSON):** Any subset of the fields listed above.

**Success response (200):**
```json
{
  "page_id": 42,
  "updated": ["spk_main_heading", "spk_cta_bg_color"]
}
```

---

## Fields

All fields use the `spk_` prefix (set by the Meta Box generator). Full list:

| Field key | Meta Box type | REST value type |
|---|---|---|
| `spk_main_heading` | text | string |
| `spk_upload_video_image` | image_advanced | array of integers (attachment IDs) |
| `spk_hide_video_image` | checkbox | boolean |
| `spk_video_section_left_text` | wysiwyg | string |
| `spk_video_code` | text | string |
| `spk_select_video` | select | string — one of: `Youtube`, `Vimeo`, `Image` |
| `spk_gridbox_repeater` | group/clone | array of objects `{spk_heading, spk_image, spk_content}` |
| `spk_upload_call_to_action_phone_image` | image_advanced | array of integers |
| `spk_call_to_action_box_text` | text | string |
| `spk_add_phone_number` | group/clone | array of objects `{spk_call_to_action_phone_number}` |
| `spk_show_map_section` | checkbox | boolean |
| `spk_cta_bg_color` | text | string |
| `spk_cta_bg_hvr_color` | text | string |
| `spk_heading_hide` | checkbox | boolean |
| `spk_hide_banner_image` | checkbox | boolean |

---

## Validation

- `page_id` must be a positive integer
- The page must exist (`get_post()` returns non-null)
- The page must use the `localareapage.php` template (`get_page_template_slug()`)
- On POST: unknown field keys in the request body are rejected (400)
- On POST: `spk_select_video` must be one of the allowed enum values
- Meta Box must be available (`function_exists('rwmb_set_meta')`) — return 503 if not

---

## Error responses

| Scenario | Status | Code |
|---|---|---|
| Missing/invalid API key | 401 | `missing_api_key` / `invalid_api_key` |
| Page not found | 404 | `page_not_found` |
| Page is not a LAP page | 400 | `not_lap_page` |
| Unknown field key in POST body | 400 | `unknown_field` |
| Invalid field value | 400 | `invalid_field_value` |
| Meta Box not available | 503 | `metabox_unavailable` |

---

## Implementation plan

### New file: `modules/lap-meta/class-lap-meta-endpoint.php`

A new class `Speakeasy_LAP_Meta_Endpoint` responsible for:
- Registering GET and POST routes under `speakeasy/v1/lap-meta/(?P<page_id>\d+)`
- Reusing `Speakeasy_REST_API::verify_api_key` for permission checks (or duplicating the check if coupling is undesirable — to be decided)
- Reading fields via `rwmb_meta( $field_key, ['object_type' => 'post'], $page_id )`
- Writing fields via `rwmb_set_meta( $page_id, $field_key, $value )`
- Validating page exists and uses `localareapage.php` template

### Modified file: `modules/lap-meta/class-lap-meta-module.php`

Add instantiation and `init()` call for `Speakeasy_LAP_Meta_Endpoint` inside the module's own `init()` method.

### No changes to `class-rest-api.php`

The new endpoint is self-contained within the lap-meta module.

---

## What this does NOT touch

- `class-rest-api.php` (existing endpoints unchanged)
- `modules/lap-meta/schemas/localareapage.php` (schema file remains; `register_meta` calls remain for any consumers that rely on them)
- Any WordPress core files or protected files

---

## Open questions

None — all decisions resolved by discovery.

---

## Tests to write (before implementation)

File: `tests/test-lap-meta-endpoint.php`

- GET returns 401 with missing API key
- GET returns 401 with invalid API key
- GET returns 404 for non-existent page
- GET returns 400 for page not using localareapage.php template
- GET returns 503 when Meta Box is unavailable
- GET returns 200 with all field keys present for valid LAP page
- POST returns 400 for unknown field key
- POST returns 400 for invalid `spk_select_video` value
- POST returns 200 and updates only provided fields
- POST does not modify fields not included in request body
