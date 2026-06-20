# Discovery Interview Protocol

Use when a feature request arrives without a PRP.

## When to Run a Discovery Interview

Any time a feature is described in one or two sentences without specifying:
- What triggers it and what it produces
- Who is affected and how
- What the error and edge-case behavior should be
- Which existing files it touches
- What it must not touch
- Which open decisions in DECISIONS.md are relevant
- WordPress integration points (hooks, admin pages, shortcodes, etc.)

## Question Sequence

Ask one question at a time. Do not batch questions. Wait for the answer before continuing.

Cover in order:
1. What does it do — input, processing, output
2. Who uses it and when (WordPress admin, frontend user, both?)
3. What happens when it fails — user-facing error on each failure path
4. Edge cases — empty states, concurrent requests, invalid input
5. Which existing files it reads from or writes to
6. What it must never modify
7. Are there any open entries in DECISIONS.md this feature depends on?
8. What are the security implications:
   - Does it accept external input? (sanitization required)
   - Does it require user authentication/authorization? (capability checks required)
   - Does it process form submissions? (nonce verification required)
   - Does it output user-generated content? (escaping required)
9. WordPress integration:
   - Which WordPress hooks (actions/filters) will be used?
   - Does it need admin UI? (menu item, settings page, meta box)
   - Does it need frontend output? (shortcode, block, widget, template)
   - Does it interact with WordPress database? (posts, users, options, custom tables)
10. What does rollback look like if this needs to be abandoned?
11. How success is verified — what commands prove it works?

## WordPress-Specific Questions

Additional questions for WordPress plugin features:

12. Where in the WordPress admin should this appear? (Dashboard, Settings, Tools, custom menu)
13. Does this feature add any custom post types, taxonomies, or database tables?
14. Should this be available via REST API or AJAX?
15. Does this need to be translation-ready (i18n)?
16. Are there any plugin conflicts to be aware of?
17. What user roles/capabilities should have access?
18. Does this modify the WordPress database schema? (requires activation/deactivation hooks)

## After the Interview

Write the completed PRP to `/PRPs/[feature-name].md` using the template.
Present it to the user.
Wait for explicit approval before writing any code.
