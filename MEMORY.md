# MEMORY.md — WP Speakeasy

Records resolved architectural decisions and current project state.
Read this at the start of every session before writing any code.

---

## ARCHITECTURAL DECISIONS

### 1. WordPress Plugin Architecture

**Decision:** WP Speakeasy will be built as a WordPress plugin following standard WordPress plugin architecture patterns.

**Why:** WordPress provides a robust plugin system with hooks, filters, and APIs that allow extending functionality without modifying core. This ensures compatibility with WordPress updates and other plugins.

**Rules out:** Standalone PHP application, theme-based implementation, WordPress multisite-specific solutions.

---

### 2. Error Handling with WP_Error

**Decision:** Use WordPress's WP_Error class for all expected failures in plugin functions.

**Why:** WP_Error is the WordPress-standard way to handle errors. It provides structured error information (code, message, data) that integrates with WordPress core and is familiar to WordPress developers. It allows callers to check `is_wp_error()` and handle failures gracefully.

**Rules out:** PHP exceptions for expected failures, returning null/false to signal errors, custom error handling classes.

---

### 3. Database Interactions

**Decision:** All database operations will use WordPress's $wpdb class with prepared statements via $wpdb->prepare().

**Why:** $wpdb is WordPress's database abstraction layer. Using it ensures compatibility with different database configurations and prevents SQL injection vulnerabilities through prepared statements. It also respects WordPress's table prefix system.

**Rules out:** Direct PDO/mysqli usage, raw SQL queries, ORMs that bypass WordPress APIs.

---

### 4. Security: Nonces and Capability Checks

**Decision:** All form submissions and AJAX requests must verify nonces. All privileged operations must check user capabilities.

**Why:** Nonces protect against CSRF attacks. Capability checks ensure users only perform actions they're authorized for. This is WordPress security best practice.

**Rules out:** Cookie-based CSRF protection, role-based authorization, security through obscurity.

---

### 5. Code Standards

**Decision:** Follow WordPress Coding Standards for all PHP code, enforced via PHP_CodeSniffer.

**Why:** Consistency with WordPress core and the broader WordPress ecosystem makes the code more maintainable and familiar to WordPress developers.

**Rules out:** PSR-2, PSR-12, or other PHP standards that conflict with WordPress conventions.

---

### 6. LAP Endpoint Variants: Route Per Version, Shared Base Class

**Decision:** Each LAP plugin version gets its own REST route speaking that version's native
meta keys and shapes (`lap-meta/{id}` for modern, `lap-meta/legacy_v1/{id}` for legacy), with a
separate discovery endpoint reporting which version a site or page uses. Shared request-handling
concerns (API key verification, LAP page validation, Meta Box availability) are extracted into a
common base class that every variant endpoint inherits.

**Why:** The LAP plugin renamed all meta keys between versions (`spk_mainheading` →
`spk_main_heading`) and changed the shape of several fields — the phone number went from string to
repeater, three fixed content blocks became one variable-length repeater. A single normalizing
endpoint would need per-field shape conversion with genuine gaps in both directions. Separate
routes mean the two shapes never meet. The base class is extracted rather than duplicated because
the variant family is explicitly expected to grow, and `legacy_v2` should inherit auth and
validation rather than copy it a third time.

**Rules out:** A single endpoint that translates legacy keys to canonical modern names;
detecting the variant from the template filename (both versions ship `localareapage.php`);
inferring a variant when a page's meta is ambiguous — that case returns an explicit error instead.

**Amended 2026-08-17 (session 9):** every variant route enforces the same guard, and the route
itself counts as the caller's declaration of variant. A page whose own meta identifies a different
variant is refused; a page with no meta at all is written on the route's say-so unless the site's
variant contradicts it. This is what lets a page be created and populated in one pass, and it gives
the modern route the guards it originally lacked — a legacy page addressed on the modern route now
fails loudly instead of accepting writes that render nothing. Consistency across routes is the
governing principle: if one route guards, all do; if one can populate a fresh page, all can.

## CURRENT PROJECT STATE

### Fully Working
- Repository initialization
- Documentation structure setup (CLAUDE.md, MEMORY.md)

### In Progress
- Setting up core project files and structure
- Creating initial documentation framework

### Not Started
- Plugin bootstrap file
- Core plugin classes
- Admin UI
- Public-facing features
- Testing infrastructure
- Build/deployment scripts

---

## NEXT SESSION START POINT

Read CLAUDE.md, MEMORY.md, CONTEXT.md, and DECISIONS.md in that order. Review the current repository structure. Begin implementing the plugin's core structure: main plugin file, autoloader, and primary class files as defined in PRPs.
