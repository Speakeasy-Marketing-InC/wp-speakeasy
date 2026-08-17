# DECISIONS.md — WP Speakeasy

Tracks architectural and design questions that are open, deferred, or resolved.

Rules:
- Every open decision blocks implementation of the code it affects.
- The AI must not implement anything that depends on an open decision.
- When a decision is resolved, move it to the RESOLVED section and record the outcome.
- Once resolved, copy the outcome to MEMORY.md as an architectural decision.

---

## OPEN — Requires human input before implementation

[None yet — open decisions will be added here as they arise during development]

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
