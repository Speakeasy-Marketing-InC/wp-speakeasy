## FEATURE: [one sentence]

## OBJECTIVE
[2–3 sentences describing what "done" looks like from a user perspective]

## CONTEXT

- Starting state: [which files currently exist and are relevant]
- Ending state: [which files will be created or modified]
- Related existing code: [specific file paths to read before starting]
- Open decisions that must be resolved first: [list any DECISIONS.md entries that block this feature]

## IMPLEMENTATION REQUIREMENTS

### Must Do
- [specific requirement]
- [specific requirement]

### Must NOT Do
- [explicit exclusion — be specific about why]
- [explicit exclusion]

## ERROR HANDLING REQUIREMENTS

- [Which errors this feature must surface and how]
- [Which errors it can silently ignore and why]
- [What the caller receives on each failure path — use WordPress WP_Error]

## SECURITY CONSIDERATIONS

- [Input validation requirements — what must be validated before processing]
- [Nonce verification — which forms/AJAX actions require nonces]
- [Capability checks — which user capabilities are required]
- [Output escaping — which functions to use: esc_html(), esc_attr(), esc_url(), wp_kses()]
- [Data exposure risks — what must never appear in logs or client responses]
- [If any of the restricted categories apply (auth, crypto, payments), note that human review is required before merging]

## WORDPRESS INTEGRATION

- [Which WordPress hooks (actions/filters) will be used]
- [Which WordPress APIs will be called (WP_Query, $wpdb, get_option, etc.)]
- [Database tables: existing WordPress tables or custom tables with prefix]
- [Admin UI integration: menu location, settings pages, meta boxes]
- [Frontend integration: shortcodes, blocks, widgets, or direct output]

## TESTS TO WRITE

List the specific test cases before any implementation begins:
- [ ] Happy path: [describe]
- [ ] Error path: [describe each WP_Error variant]
- [ ] Edge case: [describe]
- [ ] WordPress integration: [test hooks, filters, or WordPress-specific behavior]

## ROLLBACK PLAN

If this feature needs to be abandoned mid-implementation:
- Branch to return to: [branch name]
- Database changes to reverse: [table/option names, or "none"]
- State the codebase should be in: [describe]

## ACCEPTANCE CRITERIA
- [ ] [testable criterion]
- [ ] [testable criterion]
- [ ] All existing tests pass
- [ ] New tests written and passing
- [ ] PHP CodeSniffer passes (WordPress Coding Standards)
- [ ] PHPStan static analysis passes
- [ ] No undocumented exports (every function/class has a PHPDoc block)
- [ ] CHANGELOG.md updated

## VALIDATION
Run these commands to verify completion:
```bash
composer phpcs              # Check coding standards
composer phpstan            # Run static analysis
composer test               # Run PHPUnit tests
wp plugin activate wp-speakeasy  # Verify plugin activates without errors
```
