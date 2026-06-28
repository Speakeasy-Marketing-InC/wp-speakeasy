## FEATURE: REST API endpoint for setting SEO meta fields across multiple SEO plugins

## OBJECTIVE

Provide a REST API endpoint that allows external applications to set SEO title and meta description for WordPress pages and posts. The endpoint writes meta fields for all major SEO plugins (Yoast SEO, RankMath, AIOSEO, SEOPress) simultaneously, ensuring compatibility regardless of which plugin is active. When complete, external applications can programmatically set SEO metadata using the same authentication mechanism as other Speakeasy endpoints.

## CONTEXT

- **Starting state**: Plugin has LAP Meta endpoint (`modules/lap-meta/`) that provides GET/POST for custom fields. Plugin uses `X-Speakeasy-API-Key` header authentication. REST API documentation exists at `docs/REST-API.md`.
- **Ending state**: New `modules/seo-meta/` directory with endpoint class. Endpoint registered at `POST /speakeasy/v1/seo-meta/{page_id}`. Documentation updated in `docs/REST-API.md`.
- **Related existing code**:
  - `modules/lap-meta/class-speakeasy-lap-meta-endpoint.php` (pattern to follow)
  - `wp-speakeasy.php` (requires endpoint class)
- **Open decisions**: None — implementation details are specified in this PRP.

## IMPLEMENTATION REQUIREMENTS

### Must Do
- Create endpoint at `POST /speakeasy/v1/seo-meta/{page_id}`
- Accept `page_id` (required, integer), `seo_title` (optional, string), `seo_description` (optional, string)
- Validate page/post exists using `get_post()`
- Work on any WordPress page or post (no template restriction)
- Require at least one field (`seo_title` or `seo_description`) in request body
- Sanitize input: `sanitize_text_field()` for title, `sanitize_textarea_field()` for description
- Write to all four major SEO plugins' meta keys:
  - **Yoast SEO**: `_yoast_wpseo_title`, `_yoast_wpseo_metadesc`
  - **RankMath**: `rank_math_title`, `rank_math_description`
  - **AIOSEO**: `_aioseo_title`, `_aioseo_description` (stored as JSON objects)
  - **SEOPress**: `_seopress_titles_title`, `_seopress_titles_desc`
- Use `update_post_meta()` for all meta writes
- Handle AIOSEO JSON format: store as `{"title":"..."}` and `{"description":"..."}`
- Return JSON response: `{"page_id": 123, "updated": ["seo_title", "seo_description"]}`
- Use existing `X-Speakeasy-API-Key` authentication (same pattern as LAP Meta endpoint)
- Follow LAP Meta endpoint architecture: dedicated class with `register_routes()` and `verify_api_key()` methods

### Must NOT Do
- Do NOT restrict to specific page templates (works on any page/post)
- Do NOT detect which SEO plugin is active — write to all plugins unconditionally (WordPress ignores meta for inactive plugins)
- Do NOT enforce character limits on title/description (let SEO plugins handle truncation)
- Do NOT provide GET endpoint (write-only for now)
- Do NOT modify core WordPress tables directly — use `update_post_meta()` API

## ERROR HANDLING REQUIREMENTS

- **404 page_not_found**: `get_post($page_id)` returns null or post type is not page/post
- **400 missing_fields**: Neither `seo_title` nor `seo_description` provided in request body
- **401 missing_api_key**: `X-Speakeasy-API-Key` header not present
- **401 invalid_api_key**: Provided API key does not match stored key (use `hash_equals()`)
- **500 api_key_not_configured**: Plugin API key not set in WordPress options

All errors return `WP_Error` with appropriate status codes in `data['status']`.

## SECURITY CONSIDERATIONS

- **Input validation**: Validate `page_id` is integer using `absint()`. Verify page exists before any meta writes.
- **Input sanitization**: Use `sanitize_text_field()` for title, `sanitize_textarea_field()` for description before writing to database.
- **Authentication**: Require valid API key via `X-Speakeasy-API-Key` header. Use timing-safe comparison (`hash_equals()`) to prevent timing attacks.
- **Capability checks**: Not required — API key authentication is sufficient (endpoint is service-to-service, not user-facing).
- **Nonce verification**: Not required — REST API uses different authentication (API key header).
- **Data exposure**: Do not log SEO content (may contain sensitive business information). Only log page IDs and error codes.
- **SQL injection**: Prevented by using `update_post_meta()` WordPress API (uses `$wpdb->prepare()` internally).

This feature does not involve authentication, authorization, cryptography, or payment processing, so human security review is not mandatory.

## WORDPRESS INTEGRATION

- **Hooks**: `rest_api_init` action to register endpoint via `register_rest_route()`
- **WordPress APIs**:
  - `get_post()` — validate page/post exists
  - `update_post_meta()` — write SEO meta fields
  - `get_option('speakeasy_api_key')` — retrieve stored API key
  - `rest_ensure_response()` — format successful responses
  - `register_rest_route()` — register endpoint
- **Database tables**: `wp_postmeta` (via `update_post_meta()`)
- **Admin UI**: None — endpoint only, no admin interface
- **Frontend integration**: None — backend API only

## TESTS TO WRITE

- [ ] **Happy path**: POST with valid API key, valid page ID, both title and description → returns 200 with `{"page_id": N, "updated": ["seo_title", "seo_description"]}`
- [ ] **Happy path**: POST with only `seo_title` → returns 200 with `{"updated": ["seo_title"]}`
- [ ] **Happy path**: POST with only `seo_description` → returns 200 with `{"updated": ["seo_description"]}`
- [ ] **Error path**: POST without API key → returns 401 `missing_api_key`
- [ ] **Error path**: POST with invalid API key → returns 401 `invalid_api_key`
- [ ] **Error path**: POST with non-existent page ID → returns 404 `page_not_found`
- [ ] **Error path**: POST with neither title nor description → returns 400 `missing_fields`
- [ ] **Error path**: Plugin API key not configured → returns 500 `api_key_not_configured`
- [ ] **Data persistence**: POST writes to all 8 meta keys (4 plugins × 2 fields)
- [ ] **Data persistence**: Verify AIOSEO fields stored as JSON objects
- [ ] **Sanitization**: POST with HTML in title/description → HTML is sanitized/stripped
- [ ] **Works on pages**: POST to page post type → succeeds
- [ ] **Works on posts**: POST to post post type → succeeds

## ROLLBACK PLAN

If this feature needs to be abandoned mid-implementation:
- **Branch to return to**: `main` (current branch)
- **Database changes to reverse**: None — endpoint only writes standard post meta (no schema changes)
- **State the codebase should be in**: Remove `modules/seo-meta/` directory, remove `require_once` from `wp-speakeasy.php`, remove tests from `tests/test-seo-meta-endpoint.php`, remove documentation from `docs/REST-API.md`

## ACCEPTANCE CRITERIA
- [ ] Endpoint accepts POST requests at `speakeasy/v1/seo-meta/{page_id}`
- [ ] Authentication via `X-Speakeasy-API-Key` header works correctly
- [ ] Meta fields written to all 8 SEO plugin keys (Yoast, RankMath, AIOSEO, SEOPress)
- [ ] AIOSEO fields stored as JSON objects, not plain strings
- [ ] Works on both pages and posts (any post type)
- [ ] Returns proper error codes for all failure cases (401, 404, 400, 500)
- [ ] Input sanitization prevents XSS/injection attacks
- [ ] All existing tests pass
- [ ] New tests written and passing (13+ test cases)
- [ ] PHP CodeSniffer passes (WordPress Coding Standards)
- [ ] PHPStan static analysis passes
- [ ] No undocumented exports (every function/class has a PHPDoc block)
- [ ] CHANGELOG.md updated
- [ ] `docs/REST-API.md` updated with endpoint documentation

## VALIDATION
Run these commands to verify completion:
```bash
composer phpcs              # Check coding standards
composer phpstan            # Run static analysis
composer test               # Run PHPUnit tests
wp plugin activate wp-speakeasy  # Verify plugin activates without errors

# Manual test
curl -X POST https://site.com/wp-json/speakeasy/v1/seo-meta/123 \
  -H "X-Speakeasy-API-Key: your_key" \
  -H "Content-Type: application/json" \
  -d '{"seo_title": "Test Title", "seo_description": "Test description"}'

# Verify meta was written
wp post meta list 123 | grep -E '(yoast|rank_math|aioseo|seopress)'
```
