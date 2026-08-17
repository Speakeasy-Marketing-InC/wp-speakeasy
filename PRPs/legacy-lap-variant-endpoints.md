# PRP — Legacy LAP Variant Endpoints

**Status:** awaiting approval
**Session:** 7 (2026-08-17)
**Depends on:** nothing open in DECISIONS.md

---

## PROBLEM

Sites running the legacy LAP plugin get a silently broken `/lap-meta/{id}` endpoint.

The endpoint's field list (`define_fields()`, `modules/lap-meta/class-speakeasy-lap-meta-endpoint.php:72`)
describes the **modern** LAP field set. The legacy template on those sites reads a
completely different set of meta keys. The overlap is empty — modern keys are
underscore-separated (`spk_main_heading`), legacy keys are squashed lowercase
(`spk_mainheading`).

Both variants ship a template named `localareapage.php`, so
`detect_lap_templates()` (`modules/lap-meta/class-lap-meta-module.php:151`) resolves both to the
same schema basename. The filename carries no variant information.

Two failure modes on legacy sites:

1. **GET returns blanks.** Every requested key is absent on the post.
2. **POST reports success and changes nothing visible.** Writes to `spk_main_heading`
   persist as real post meta, so the session 6 round-trip check
   (`write_failed_to_persist()`, line 307) passes cleanly. The legacy template
   simply never reads that key. Green response, zero visible effect.

Failure mode 2 is the dangerous one — it is indistinguishable from success at the API layer.

### Possible bearing on the session 6 Mancebo report

CONTEXT.md session 6 concluded that Mancebo's gridbox failure was a Meta Box
field-group misconfiguration. If that site is on legacy LAP, `spk_gridbox_repeater`
does not exist there at all and that conclusion is wrong. **Hypothesis, not a finding** —
confirm by calling the new variant endpoint against page 4043 before acting on it.

---

## APPROACH — route per variant

Rejected: one endpoint that normalizes legacy keys to canonical modern names.
Legacy and modern differ in **shape**, not just spelling, so normalization needs shape
conversion and has gaps in both directions:

- `spk_calltoactionnumber` (string) vs `spk_add_phone_number` (repeater array of objects)
- legacy's three fixed content blocks (`spk_bottomsectioncontent`/`2`/`3`) have no modern
  counterpart; `spk_gridbox_repeater` is variable-length with no legacy counterpart
- legacy images are bare attachment IDs; modern image fields are arrays

Chosen: **one route per variant, each speaking its template's native keys as stored.**
No rename map, no shape conversion, no reconciliation. Scales to `legacy_v2` when another
old template surfaces. The modern endpoint is not modified, so sites working today carry
no regression risk.

The template pasted in session 7 is designated **`legacy_v1`**.

---

## SCOPE

### New: variant discovery

| Route | Returns |
|---|---|
| `GET speakeasy/v1/lap-variant` | site-level dominant variant + `mixed` flag |
| `GET speakeasy/v1/lap-variant/{page_id}` | that page's variant |

Site-level response:

```json
{
  "variant": "legacy_v1",
  "mixed": false,
  "counts": { "legacy_v1": 12, "modern": 0, "undetermined": 1 },
  "total_lap_pages": 13
}
```

`mixed: true` when more than one determinate variant is present. `variant` is the
dominant one; callers that care resolve per page.

Detection is a probe of marker meta keys, one grouped `$wpdb->prepare()` query over
`postmeta` restricted to LAP page IDs — not a per-page loop.

Marker keys (unambiguous, present on any non-blank page of that variant):

- legacy_v1: `spk_mainheading`, `spk_calltoactiontext`, `spk_videolefttext`
- modern: `spk_main_heading`, `spk_call_to_action_box_text`, `spk_video_section_left_text`

Page verdicts: `legacy_v1` | `modern` | `ambiguous` (both present) | `undetermined` (neither).

### New: legacy_v1 read/write

| Route | Method |
|---|---|
| `speakeasy/v1/lap-meta/legacy_v1/{page_id}` | GET, POST |

Keys returned and accepted exactly as stored — legacy names, legacy shapes. Response
carries `"variant": "legacy_v1"` so callers can assert what they're talking to.

Route form is nested rather than suffixed (`lap-meta/legacy_v1/{id}`, not
`lap-meta-legacy_v1/{id}`) — the existing `lap-meta/(?P<page_id>\d+)` regex requires
digits, so there is no collision. **My judgment call, trivially changed if you prefer the suffix.**

Modern stays where it is: `speakeasy/v1/lap-meta/{page_id}`, untouched.

### legacy_v1 field set — 26 fields

Read via `rwmb_meta()` (19):

`spk_mainheading`, `spk_videolefttext`, `spk_videocode`, `spk_selectvideo` (enum: `Youtube`, `Vimeo`),
`spk_calltoactiontext`, `spk_calltoactionnumber`,
`spk_bottomsectionheading`, `spk_bottomsectionheading2`, `spk_bottomsectionheading3`,
`spk_bottomsectioncontent`, `spk_bottomsectioncontent2`, `spk_bottomsectioncontent3`,
`spk_bottomsectioncall2` (bool), `spk_mapsection` (bool),
`spk_mapheading`, `spk_mapaddress`, `spk_mapphone`, `spk_mapfax`, `spk_mapiframe`

Read via `get_post_meta( $id, $key, true )` → bare attachment ID (7):

`spk_bannerbgimg`, `spk_videoimg`, `spk_calltoactionimg`, `spk_mapimg`,
`spk_bottomsectioncontentimg`, `spk_bottomsectioncontentimg2`, `spk_bottomsectioncontentimg3`

**The split matters.** The legacy template reads images with
`get_post_meta( $post->ID, 'spk_bannerbgimg', 'true' )` and feeds the result to
`wp_get_attachment_url()` — a bare ID, not a Meta Box array. The endpoint must mirror the
template's access path per field. Reading or writing images through `rwmb_meta()` /
`rwmb_set_meta()` risks storing a shape the template cannot read — which is exactly the
silent-failure class session 6 was about.

### Error states

| Code | Status | When |
|---|---|---|
| `ambiguous_field_variant` | 400 | page has both key styles; message names the conflicting pairs |
| `variant_mismatch` | 400 | modern-shaped page addressed on the legacy_v1 route (and vice versa) |
| `variant_undetermined` | 400 | POST to a page with no `spk_` meta at all |
| `page_not_found` | 404 | existing behavior, reused |
| `not_lap_page` | 400 | existing behavior, reused |
| `metabox_unavailable` | 503 | existing behavior, reused |

Per your decisions: refuse to guess in every ambiguous case. A blank page on the create
flow fails loudly rather than writing a variant it inferred.

Auth is unchanged — same `X-Speakeasy-API-Key` timing-safe check as the modern endpoint.

---

## MUST NOT TOUCH

- `define_fields()` or any behavior of the existing modern endpoint
- `modules/lap-meta/schemas/localareapage.php` field list
- `modules/seo-meta/`, `modules/app-passwords/`
- Anything in the PROTECTED FILES list

---

## FILES

New:

- `modules/lap-meta/class-speakeasy-lap-variant-endpoint.php` — discovery
- `modules/lap-meta/class-speakeasy-lap-meta-legacy-v1-endpoint.php` — legacy read/write
- `modules/lap-meta/schemas/localareapage-legacy-v1.php` — legacy field definitions
- `tests/test-lap-variant-endpoint.php`
- `tests/test-lap-meta-legacy-v1-endpoint.php`

Modified:

- `modules/lap-meta/class-lap-meta-module.php` — register the two new endpoints
- `CHANGELOG.md`

---

## TESTS

Written before implementation, per the testing rule.

**Variant detection**

1. Page with only legacy markers → `legacy_v1`
2. Page with only modern markers → `modern`
3. Page with both → `ambiguous`
4. Page with neither → `undetermined`
5. Site with all-legacy pages → `mixed: false`, correct counts
6. Site with legacy + modern pages → `mixed: true`, dominant variant returned
7. Site with zero LAP pages → empty counts, no error
8. Detection runs one query, not one per page

**legacy_v1 GET**

9. Returns all 26 keys with stored values
10. Image fields return bare attachment IDs, not arrays
11. Response includes `"variant": "legacy_v1"`
12. Modern page on legacy route → `variant_mismatch`
13. Ambiguous page → `ambiguous_field_variant`
14. Non-LAP page → `not_lap_page`
15. Missing page → `page_not_found`
16. Bad/missing API key → 401

**legacy_v1 POST**

17. Partial update writes only supplied keys, leaves others untouched
18. Unknown key → `unknown_field`, nothing written
19. `spk_selectvideo` outside enum → `invalid_field_value`
20. Image field write persists as a bare ID readable by `get_post_meta(..., true)`
21. Round-trip verification (session 6 behavior) reports non-persisting writes in `failed`
22. Page with no `spk_` meta → `variant_undetermined`, nothing written
23. Ambiguous page → `ambiguous_field_variant`, nothing written
24. Modern page on legacy route → `variant_mismatch`, nothing written

Note, as in prior sessions: PHPUnit needs a live WordPress + Meta Box environment that
isn't available locally. Tests will be written to run in CI/staging and verified by
inspection here. **Test 20 is the one that genuinely needs a real legacy site** — it is the
assertion that guards against repeating session 6.

---

## RESOLVED — shared code between variant endpoints

**Extract a shared base class** (option b). Recorded in DECISIONS.md § RESOLVED and
MEMORY.md § 6.

`verify_api_key()`, `validate_lap_page()` and `is_metabox_available()` move from the modern
endpoint to a new abstract base class that both variant endpoints extend. Future variants
inherit auth and validation rather than copying them.

Constraint on the extraction: **the modern endpoint's observable behavior must not change.**
The existing `tests/test-lap-meta-endpoint.php` suite must pass untouched — no edits to those
tests as part of this work. If a test there needs changing to stay green, the extraction is
wrong and I stop and report rather than adjusting the test.

> **Superseded by Amendment 1 (2026-08-17).** The modern endpoint's behavior *does* now change:
> it gains the same variant guards as the legacy route. The constraint above applied to the
> base-class extraction only, and held for it.

Adds to FILES:

- new: `modules/lap-meta/class-speakeasy-lap-endpoint-base.php`
- modified: `modules/lap-meta/class-speakeasy-lap-meta-endpoint.php` (extends base;
  helpers removed, behavior identical)

---

## AMENDMENT 1 — Uniform guards, and create-and-populate in one pass

**Added:** 2026-08-17 (session 9). **Status:** awaiting approval.

### Why

Two problems with what shipped, both from the same root:

1. **The create flow is blocked.** The legacy route refuses any write to a page with no LAP meta
   (`variant_undetermined`), including a page the caller just created. wordpress-mcp's
   `create_lap_legacy_v1` creates the page and cannot populate it.
2. **The two routes behave differently.** The legacy route guards its variant; the modern route has
   no guard at all. So a legacy page addressed on the modern route still gets the original silent
   failure — the write persists under keys the template never reads and returns `200`.

The original refusal was justified as "a blank page gives no signal, so writing means guessing."
That reasoning was wrong in one respect: **the route itself is the caller's declaration of variant.**
`POST /lap-meta/legacy_v1/{id}` says the page is legacy. On a page with no meta there is nothing to
contradict it. The residual risk is a caller wrong about the site — and site-level detection, which
already exists, catches exactly that.

### Rule

One rule, applied identically by every variant route:

> Refuse when the page's own variant contradicts the route. When the page has no variant of its own,
> trust the route unless the **site's** variant contradicts it.

| Page variant | Site variant | Result |
|---|---|---|
| matches route | any | proceed |
| other variant | any | `variant_mismatch` |
| `ambiguous` | any | `ambiguous_field_variant` |
| `undetermined` | matches route | proceed — route and site agree |
| `undetermined` | `mixed` | proceed — no majority evidence either way |
| `undetermined` | no other LAP pages | proceed — first LAP page on the site |
| `undetermined` | contradicts route | `variant_mismatch` |

Reads stay permitted on `undetermined` pages regardless of site variant, as now.

`variant_undetermined` is no longer reachable and is removed. Its 400 case is now covered by
`variant_mismatch`, whose message names the route the caller should have used.

### Scope

- Guard logic moves to `Speakeasy_LAP_Endpoint_Base` as a shared method taking the route's variant;
  both endpoints call it. The legacy endpoint's private `guard_request()` collapses into it.
- `Speakeasy_LAP_Meta_Endpoint` declares `VARIANT = modern` and gains the guard.
- Site-level detection is consulted only when the page is `undetermined`, so the common path costs
  no extra queries.

### Breaking changes

The modern route stops being permissive. Both are deliberate — each one is a silent failure being
converted into a loud one — but they are behavior changes to a live endpoint:

- A **legacy** page addressed on the modern route now returns `400 variant_mismatch` instead of
  `200` with empty fields (GET) or a successful-looking no-op write (POST).
- An **ambiguous** page on the modern route now returns `400 ambiguous_field_variant`.

Any caller currently pointing at the modern route for legacy pages will start seeing 400s. Those
calls were already doing nothing; they will now say so.

### Tests

Added to the modern suite (`tests/test-lap-meta-endpoint.php` — now edited, per the note above):

25. Legacy page on the modern route → `variant_mismatch` on GET and POST, nothing written
26. Ambiguous page on the modern route → `ambiguous_field_variant`, nothing written
27. Undetermined page on a modern-dominant site → write proceeds
28. Undetermined page on a legacy-dominant site → `variant_mismatch`, nothing written
29. Existing modern tests still pass unmodified — blank LAP page with no other LAP pages on the
    site is the "first page" case and must keep writing

Added to the legacy suite:

30. Undetermined page on a legacy-dominant site → write proceeds (was `variant_undetermined`)
31. Undetermined page on a modern-dominant site → `variant_mismatch`, nothing written
32. Undetermined page on a mixed site → write proceeds
33. Undetermined page, no other LAP pages on the site → write proceeds

Test 22 (`variant_undetermined` on write) is removed — the error no longer exists.

---

## VERIFICATION BEFORE CLOSE

- `composer phpcs` and `composer phpstan` clean on new/modified files
- No file over 500 lines
- Call `GET speakeasy/v1/lap-variant` against a known legacy site and against page 4043
  to settle the session 6 hypothesis
- CONTEXT.md session 7 updated and closed; DECISIONS.md records the base-class outcome
