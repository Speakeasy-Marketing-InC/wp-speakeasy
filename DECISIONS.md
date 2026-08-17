# DECISIONS.md — WP Speakeasy

Tracks architectural and design questions that are open, deferred, or resolved.

Rules:
- Every open decision blocks implementation of the code it affects.
- The AI must not implement anything that depends on an open decision.
- When a decision is resolved, move it to the RESOLVED section and record the outcome.
- Once resolved, copy the outcome to MEMORY.md as an architectural decision.

---

## OPEN — Requires human input before implementation

### How should a caller create and populate a legacy_v1 page in one pass?

**Raised:** 2026-08-17 (session 8)

A brand-new page has no LAP meta, so its variant cannot be detected and the legacy_v1 write route
refuses it (`variant_undetermined`). The page gets created; its content does not. See CLAUDE.md
§ KNOWN ISSUES. Refusing is correct given what the endpoint can currently know — the open question
is what to add so the create flow works without reintroducing guessing.

Options:
- **(a)** Accept an explicit `variant` parameter on write, trusted only when the page is
  `undetermined`. Unblocks create; moves the risk of being wrong to the caller.
- **(b)** A dedicated create route that takes the variant as part of page creation, so the variant is
  established at the same moment the page is.
- **(c)** Leave it. The manual marker-field step stays the documented workaround.

Blocks: any change to `Speakeasy_LAP_Meta_Legacy_V1_Endpoint::guard_request()` write path.

### Should variant detection probe more than three marker keys?

**Raised:** 2026-08-17 (session 8)

`Speakeasy_LAP_Variant_Detector::MARKERS` probes three keys per variant. A legacy page that has, say,
only map fields and images filled in reads as `undetermined` even though it is plainly legacy, and
both its reads and writes are affected accordingly.

Widening to the full 26-key set would make detection near-exhaustive at the cost of a larger `IN`
clause in one query. It does **not** fix the create-flow issue above — a page with no meta at all
stays undetermined under any marker set. These are separate problems and should not be conflated.

Blocks: any change to `Speakeasy_LAP_Variant_Detector::MARKERS`.

---

## DEFERRED — Acknowledged, not yet needed

[None yet — deferred decisions will be tracked here]

---

## RESOLVED

### Shared code between LAP variant endpoints — resolved 2026-08-17 (session 7)

**Question:** The new `legacy_v1` endpoint needs `verify_api_key()`, `validate_lap_page()` and
`is_metabox_available()`, which already exist on the modern LAP endpoint. Duplicate them into the
new class (zero risk to live code, ~60 lines duplicated), or extract a shared base class/trait
(no duplication, inherited by future variants, but modifies an endpoint running in production)?

**Outcome:** Extract a shared base class. The variant family is expected to grow to `legacy_v2`
and beyond, so duplicating auth and validation per variant compounds. The modern endpoint's
observable behavior must not change as part of the extraction — existing tests for it must pass
untouched.

**Copied to:** MEMORY.md § 6.
