# DECISIONS.md — WP Speakeasy

Tracks architectural and design questions that are open, deferred, or resolved.

Rules:
- Every open decision blocks implementation of the code it affects.
- The AI must not implement anything that depends on an open decision.
- When a decision is resolved, move it to the RESOLVED section and record the outcome.
- Once resolved, copy the outcome to MEMORY.md as an architectural decision.

---

## OPEN — Requires human input before implementation

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

### Create-and-populate in one pass, and guard symmetry — resolved 2026-08-17 (session 9)

**Question:** The legacy_v1 write route refuses any write to a page with no LAP meta
(`variant_undetermined`), so a page cannot be created and populated in one pass. What should be
added so the create flow works without reintroducing guessing?

**Outcome:** Every variant route behaves identically — if one has a guard they all do, and if one
can populate a fresh page they all can. The route itself is the caller's declaration of variant, so
on a page with no meta there is nothing to contradict it. Site-level detection is consulted only in
that case, and refuses when the site's variant contradicts the route.

This gives the modern route the variant guards it never had, which is a breaking change: legacy and
ambiguous pages addressed on the modern route now return 400 instead of succeeding at nothing.

Supersedes the session 7 answer to "what should POST write when the page has no meta yet"
(then: reject with an explicit error) and the PRP constraint that the modern endpoint's behavior
must not change. Specified in `PRPs/legacy-lap-variant-endpoints.md` § Amendment 1.

**Copied to:** MEMORY.md § 6.
