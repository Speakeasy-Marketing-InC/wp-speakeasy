# Quick Start Guide — WP Speakeasy

Welcome to the WP Speakeasy WordPress plugin repository. This project uses a context engineering system to maintain consistency across AI coding sessions.

---

## Repository Structure

```
wp-speakeasy/
├── CLAUDE.md              # Behavioral rules for AI assistants (READ FIRST)
├── MEMORY.md              # Resolved architectural decisions
├── CONTEXT.md             # Session handoff log
├── DECISIONS.md           # Pending decisions register
├── CHANGELOG.md           # Shipping history
├── .llmignore             # Protected files list
├── PRPs/                  # Feature templates
│   ├── TEMPLATE.md        # PRP template for new features
│   └── DISCOVERY.md       # Discovery interview protocol
├── docs/
│   ├── CODE_STYLE.md      # PHP documentation guidelines
│   └── source/            # Raw context (meetings, research, etc.)
│       ├── meetings/
│       ├── research/
│       ├── stakeholder/
│       └── constraints/
└── reports/               # End-of-day summaries
```

---

## Starting a New AI Coding Session

Paste this into your AI assistant at the start of every session:

```
Before anything else: append a new session entry to CONTEXT.md with state `open` and the current branch name. Commit it. Do not read any other file or write any code until this is done.

Then read CLAUDE.md, MEMORY.md, DECISIONS.md, and CONTEXT.md — in that order.
Confirm you've read them by summarizing: current stack, last thing built, any open decisions blocking today's work, and what we're doing this session.
```

---

## Ending a Session

Paste this at the end of every session:

```
Before we finish: write a session handoff to CONTEXT.md covering:
1. What we accomplished (prose summary)
2. Every file modified or created (with one-line description of the change)
3. Tests written and what behavior they cover
4. Decisions made and why
5. Any new pending decisions added to DECISIONS.md
6. What is still open or broken
7. The exact next step for the next session

Then update CHANGELOG.md with anything that shipped.
Then mark this session `closed`, commit, and push.
```

---

## Before Writing Any Code

1. **Check DECISIONS.md** — Ensure no open decisions block your feature
2. **Create a PRP** — Write a feature brief in PRPs/[feature-name].md using PRPs/TEMPLATE.md
3. **Get approval** — Present the PRP to stakeholders before implementing
4. **Write tests first** — Every feature starts with failing tests

---

## Key Rules (from CLAUDE.md)

- **Session handoff is mandatory** — Every session must have an entry in CONTEXT.md
- **No code without a PRP** — Feature requests trigger discovery interviews first
- **One feature at a time** — Never implement multiple PRPs in one session
- **Tests before implementation** — Write failing tests, then make them pass
- **Security is non-negotiable** — Nonce verification, capability checks, input sanitization, output escaping
- **WordPress standards** — Follow WordPress Coding Standards, use WordPress APIs exclusively
- **Error handling** — Use WP_Error for expected failures, never throw exceptions
- **Documentation** — Every function needs PHPDoc with @since, @param, @return

---

## WordPress-Specific Guidelines

### Security Checklist
- [ ] Verify nonces for all form submissions and AJAX requests
- [ ] Check user capabilities before privileged operations
- [ ] Sanitize all input: `sanitize_text_field()`, `absint()`, etc.
- [ ] Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()`
- [ ] Use `$wpdb->prepare()` for all database queries
- [ ] Never hardcode credentials or API keys

### Code Quality Commands
```bash
composer phpcs              # Check coding standards
composer phpcbf             # Auto-fix coding standards
composer phpstan            # Run static analysis
composer test               # Run PHPUnit tests
composer test:coverage      # Run tests with coverage
```

---

## File Naming Conventions

- **PHP files**: `class-speakeasy-feature.php` (lowercase with hyphens)
- **Classes**: `WP_Speakeasy_Feature` (uppercase with underscores)
- **Functions**: `speakeasy_function_name()` (lowercase with underscores, prefixed)
- **Hooks**: `speakeasy_hook_name` (namespaced)

---

## Protected Files (Never Modify)

- `.env` files and WordPress configuration
- `composer.lock`, `package-lock.json`
- `wp-config.php`, `.htaccess`
- WordPress core directories (`wp-admin/`, `wp-includes/`)

See [.llmignore](.llmignore) for the complete list.

---

## Need Help?

- **Behavioral rules**: [CLAUDE.md](CLAUDE.md)
- **Architectural decisions**: [MEMORY.md](MEMORY.md)
- **Current session status**: [CONTEXT.md](CONTEXT.md)
- **Pending questions**: [DECISIONS.md](DECISIONS.md)
- **Code style guide**: [docs/CODE_STYLE.md](docs/CODE_STYLE.md)
- **Feature template**: [PRPs/TEMPLATE.md](PRPs/TEMPLATE.md)
- **Discovery protocol**: [PRPs/DISCOVERY.md](PRPs/DISCOVERY.md)

---

## What Makes This System Work

This repository uses a context engineering framework that:

1. **Prevents context drift** — CLAUDE.md defines immutable behavioral rules
2. **Maintains state** — CONTEXT.md ensures every session picks up where the last left off
3. **Forces planning** — PRPs eliminate ambiguity before code is written
4. **Blocks guessing** — DECISIONS.md surfaces architectural questions explicitly
5. **Preserves rationale** — MEMORY.md records why decisions were made
6. **Protects critical files** — .llmignore prevents accidental destructive edits
7. **Enforces testing** — Tests-first rule catches errors before they ship

Every file serves a specific purpose. Remove one, and a specific failure mode returns.

---

## Next Steps

The repository is now ready for development. Start your first session by:

1. Creating a PRP for the plugin's core bootstrap structure
2. Getting approval
3. Writing tests
4. Implementing the feature
5. Updating CONTEXT.md and CHANGELOG.md
6. Committing and pushing

Happy coding!
