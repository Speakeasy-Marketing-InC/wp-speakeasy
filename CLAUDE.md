# CLAUDE.md — WP Speakeasy

Behavioral instructions for AI coding assistants. Each rule exists to prevent a specific mistake.

---

## SESSION HANDOFF RULE — NON-NEGOTIABLE

Every session in `CONTEXT.md` must have:
- A **name** — short descriptive title of what the session accomplished
- A **state** — `open` while work is in progress, `closed` once handoff is done
- A **branch** — the git branch this session's work lives on

Format:
```
## SESSION {n} — {YYYY-MM-DD} — {Name} — {state}
Branch: {branch-name}
```

Rules:
- **Step 1 of every session, no exceptions:** append a new session entry to `CONTEXT.md` with state `open` and the current branch name. Do this before reading any other file, before planning, before writing any code. The entry must exist and be committed before any other work begins.
- Mark it `closed` only after CONTEXT.md is updated and committed and pushed.
- Never leave a session `open` at the end of a turn.
- Never start a new session without closing the previous one first.
- The NEXT SESSION START POINT block is always rewritten at the end of every session.
- Sessions are never deleted — the full history stays in this file.

The open entry looks like this — write it immediately:
```
## SESSION {n} — {YYYY-MM-DD} — {Name} — open
Branch: {branch-name}
```

Replace `{Name}` with a short title for what this session intends to do.
Commit this entry before proceeding.

---

## PRP RULE — NON-NEGOTIABLE

Never write code for a new feature without a PRP file in /PRPs.

If a feature request is given without a PRP:
1. Do not write any code.
2. Run a discovery interview — one question at a time.
3. Cover: what it does, who it affects, edge cases, error states, what files it touches, what it must not touch.
4. Write the PRP to /PRPs/[feature-name].md.
5. Present it to the user for approval.
6. Only build after explicit approval.

A vague prompt is not a starting point. It is the beginning of a discovery.

---

## SCOPE RULE — NON-NEGOTIABLE

One PRP at a time. Never implement more than one feature's scope in a single session.

If mid-implementation you discover the scope is larger than the PRP described:
1. Stop immediately. Do not continue implementing.
2. Document what was discovered.
3. Update or create a new PRP for the expanded scope.
4. Get approval before continuing.

The model does not decide that something is "small enough to add." The human decides.

---

## TESTING RULE — NON-NEGOTIABLE

Write the test before writing the implementation. No exceptions.

Rules:
- For every new function, write a failing test first. Then write the minimum code to make it pass.
- Tests live in `tests/`. Mirror the source tree: `includes/class-speakeasy.php` → `tests/test-class-speakeasy.php`.
- What to test: behavior visible to callers — inputs, outputs, and error paths.
- What NOT to test: implementation internals, private functions, third-party library behavior, WordPress core behavior.
- Every new exported function must have at least one test covering its happy path and one covering each error path.
- After any non-trivial change, run the full test suite before considering the task done.

If the PRP does not describe what to test, add the test cases to the PRP before writing any code.

---

## SECURITY RULE — NON-NEGOTIABLE

Never implement the following without explicit human review and approval:
- Authentication or session management
- Authorization / permission checks (beyond WordPress core capabilities)
- Cryptographic operations (hashing, signing, encrypting)
- Payment processing
- Secrets or credential handling

For everything else:
- Never hardcode credentials, API keys, tokens, or secrets. Not even in comments or example values.
- Never construct SQL queries by string concatenation. Use $wpdb->prepare() for all database queries.
- Never trust input from users, $_GET, $_POST, or external APIs without validation and sanitization first.
- Never log sensitive data (passwords, tokens, PII).
- Never expose internal error details to the client — map them to a generic user-facing message.
- When generating code that accepts external input, add nonce verification and input validation before any processing.
- Always escape output: use esc_html(), esc_attr(), esc_url(), wp_kses() as appropriate.
- Always check user capabilities before performing privileged operations.

If you are unsure whether something has a security implication, stop and ask before implementing.

---

## CODE DOCUMENTATION RULE — NON-NEGOTIABLE

Read `docs/CODE_STYLE.md` before writing any function, class, or module.

Every exported function, class, and type must have a documentation block following WordPress PHP documentation standards. Every non-obvious decision inside a function must have an inline comment explaining *why*, not what. The what is in the code. The why is what degrades when context is lost.

---

## PENDING DECISIONS RULE — NON-NEGOTIABLE

Before writing any code that depends on an unresolved architectural question, check `DECISIONS.md`.

- If the decision is listed as `open`, stop. Do not implement. Ask the user to resolve it first.
- If the decision is listed as `resolved`, follow the outcome recorded there — do not re-litigate it.
- If you encounter a new unresolved question mid-implementation, add it to `DECISIONS.md` as `open` and stop. Do not guess.

Never make an architectural choice silently. If you are guessing, you are making a decision that belongs in DECISIONS.md.

---

## FILE SIZE RULE

No file exceeds 500 lines.

When a file reaches the limit:
1. Stop before adding more code.
2. Propose a split to the user — show the proposed new file names and what moves where.
3. Wait for approval.
4. Split, then continue.

Do not ask "should I split this?" — propose the specific split.

---

## PROTECTED FILES — NON-NEGOTIABLE

Never read, modify, or delete the following files or directories under any circumstances:

- `.env` and `.env.*` (any environment file)
- `composer.lock` (lockfile — managed by composer)
- `wp-config.php` (WordPress configuration — contains sensitive credentials)
- `.htaccess` (web server configuration)
- Any file in `wp-admin/`, `wp-includes/`, or WordPress core directories

If a task seems to require touching a protected file, stop and ask the user how to proceed.

See also: `.llmignore` at the project root.

---

## COMMANDS

```bash
# Development
composer install                    # Install PHP dependencies
npm install                          # Install JS/CSS dependencies (if applicable)

# Testing
composer test                        # Run PHPUnit tests
composer test:coverage               # Run tests with coverage report

# Code Quality
composer phpcs                       # Run PHP CodeSniffer
composer phpcbf                      # Auto-fix coding standards
composer phpstan                     # Run static analysis

# WordPress
wp plugin activate wp-speakeasy      # Activate plugin
wp plugin deactivate wp-speakeasy    # Deactivate plugin
```

Run code quality checks and tests after every non-trivial change. Do not consider a task done until both pass.

---

## STACK

- PHP 7.4+ (8.0+ recommended)
- WordPress 5.9+
- Composer for dependency management
- PHPUnit for testing
- PHP CodeSniffer for code standards (WordPress Coding Standards)
- PHPStan for static analysis

---

## ARCHITECTURE RULES

### WordPress Plugin Structure
- All plugin code lives in the plugin directory (wp-speakeasy/)
- Main plugin file: `wp-speakeasy.php` (bootstrap only)
- Classes go in `includes/` with autoloading
- Admin UI code goes in `admin/`
- Public-facing code goes in `public/`
- Assets (CSS/JS) go in `assets/`

### WordPress Integration Rules
- Hook into WordPress via actions and filters, never modify core
- Use WordPress APIs exclusively: $wpdb for database, WP_Query for posts, etc.
- Follow WordPress coding standards for all PHP code
- Use WordPress security functions: nonces, capability checks, data sanitization
- Never access the database directly — always use WordPress functions or $wpdb
- Namespace all functions, classes, and database tables to avoid conflicts

### Error Handling
This project uses WordPress-style error handling with WP_Error for expected failures.

The Rule:
```
Expected failure  →  return new WP_Error('code', 'message')
Truly unexpected  →  let it throw or log with error_log()
```

Pattern:
```php
/**
 * Get user data.
 *
 * @param int $user_id User ID.
 * @return array|WP_Error User data on success, WP_Error on failure.
 */
function get_user_data( $user_id ) {
    if ( ! $user_id || ! is_numeric( $user_id ) ) {
        return new WP_Error( 'invalid_user_id', 'Invalid user ID provided' );
    }

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return new WP_Error( 'user_not_found', 'User not found' );
    }

    return array(
        'id'    => $user->ID,
        'name'  => $user->display_name,
        'email' => $user->user_email,
    );
}

// Usage
$result = get_user_data( $id );
if ( is_wp_error( $result ) ) {
    error_log( 'Error: ' . $result->get_error_message() );
    return;
}
// $result is an array here
```

### WordPress Hooks Pattern
- Use meaningful, namespaced hook names: `speakeasy_before_save`, not `before_save`
- Document all custom hooks with `@since` and `@param` tags
- Never execute SQL in a hook callback — call a service function that returns WP_Error on failure

---

## FILE ORGANIZATION

```
wp-speakeasy/
├── wp-speakeasy.php          # Main plugin file (bootstrap only)
├── includes/                 # PHP classes and core logic
│   ├── class-speakeasy.php  # Main plugin class
│   └── ...                   # Feature-specific classes
├── admin/                    # Admin UI code
│   ├── class-admin.php      # Admin initialization
│   ├── views/               # Admin template files
│   └── ...
├── public/                   # Public-facing code
│   ├── class-public.php     # Public initialization
│   └── ...
├── assets/                   # CSS, JS, images
│   ├── css/
│   ├── js/
│   └── images/
├── languages/                # Translation files (.pot, .po, .mo)
├── tests/                    # PHPUnit tests
├── docs/                     # Documentation
├── PRPs/                     # Product Requirements Prompts
├── CLAUDE.md                 # This file
├── MEMORY.md                 # Architectural decisions
├── CONTEXT.md                # Session handoffs
├── DECISIONS.md              # Pending decisions
├── CHANGELOG.md              # Shipping history
├── composer.json             # PHP dependencies
└── README.md                 # Plugin readme
```

---

## ANTI-PATTERNS

1. **Never directly access superglobals ($_GET, $_POST, $_REQUEST).** Use WordPress functions: `get_query_var()`, `get_post()`, etc. Always sanitize with `sanitize_text_field()`, `absint()`, etc.

2. **Never echo user input without escaping.** Use `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses()`.

3. **Never write SQL queries by string concatenation.** Use `$wpdb->prepare()` for all queries.

4. **Never check user permissions with role names.** Use capabilities: `current_user_can('edit_posts')`, not `is_user_role('editor')`.

5. **Never modify WordPress core files.** Use hooks and filters exclusively.

6. **Never assume a function exists.** Check with `function_exists()` or `class_exists()` when dealing with optional dependencies.

7. **Never use `extract()` on user input or unknown data.** It creates variables in the current scope, which is a security risk.

8. **Never output JSON with `echo json_encode()`.** Use `wp_send_json()` or `wp_send_json_error()`.

9. **Never perform privileged operations without nonce verification.** All form submissions and AJAX actions must verify nonces.

10. **Never use `eval()`, `system()`, `exec()`, or similar functions.** They are security vulnerabilities.

---

## KNOWN ISSUES — DO NOT FIX

[None yet — add issues here when they arise]
