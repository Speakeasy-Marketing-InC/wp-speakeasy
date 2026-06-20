# Code Style — WP Speakeasy

Documentation rules for all code in this project.
Read this before writing any function, class, or module.

---

## The Two Layers of Documentation

### Layer 1 — Block documentation (what this is)

Every exported function, class, interface, and type gets a documentation block following WordPress PHPDoc standards.
Internal (non-exported) functions get a block only if their purpose is not immediately obvious.

**Format (PHP/WordPress):**

```php
/**
 * [One-sentence description of what this does from the caller's perspective.]
 *
 * [Optional second paragraph: when to use it, what to watch out for.]
 *
 * @since 1.0.0
 * @param string $name What this param is. Include constraints, allowed values.
 * @param array  $options {
 *     Optional. What the options array controls.
 *
 *     @type string $key1 Description of key1.
 *     @type int    $key2 Description of key2. Default 0.
 * }
 * @return array|WP_Error User data on success, WP_Error on failure.
 */
```

Rules:
- The first line is always a single sentence. No "This function...". Start with the verb: "Fetches", "Creates", "Returns", "Validates".
- `@since` is required on every function, class, and method. Use the version when the code was introduced (e.g., 1.0.0).
- `@param` is required for every parameter. Type hint comes first, then variable name, then description.
- `@return` is required on every function that returns a value. Describe both success and error paths.
- For WP_Error returns, describe the error codes: `@return array|WP_Error Data on success, WP_Error with codes 'invalid_id' or 'not_found' on failure.`
- Use `@global` when accessing global variables.
- Use hash notation for array parameters that have specific keys.

### Layer 2 — Inline comments (why this decision was made)

Inline comments explain decisions, not code.

**Good:** `// Retry once — the API returns 429 on cold start ~20% of the time`
**Bad:** `// Increment counter by 1`

Rules:
- Comment above the line it explains, not at the end.
- Use inline comments when: a magic number appears, a library is used non-obviously, a performance trade-off was made, a guard clause prevents a non-obvious bug, a workaround exists for a known issue.
- Never comment what the code does. If the code needs a comment to explain what it does, the code should be rewritten to be self-explanatory.
- Known issues get a comment with a ticket reference: `// TODO(#123): Remove after upstream fixes their pagination`

---

## File-Level Documentation

Every file gets a top-of-file PHPDoc block:

```php
<?php
/**
 * [Module name]
 *
 * [One paragraph: what this file is responsible for and what it is NOT responsible for.]
 *
 * @package    WP_Speakeasy
 * @subpackage WP_Speakeasy/includes
 * @since      1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

Rules:
- Every PHP file must start with `<?php` and a PHPDoc block.
- Every PHP file must include the "prevent direct access" check.
- Use `@package` and `@subpackage` to organize code.
- Never close PHP tags (`?>`) at the end of files — leave them open.

---

## Class Documentation

```php
/**
 * [Class name and purpose]
 *
 * [Detailed description of what this class does, when to use it, and any important
 * constraints or relationships with other classes.]
 *
 * @since 1.0.0
 */
class WP_Speakeasy_Feature {

    /**
     * The unique identifier of this plugin.
     *
     * @since  1.0.0
     * @access protected
     * @var    string $plugin_name The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * Initialize the class and set its properties.
     *
     * @since 1.0.0
     * @param string $plugin_name The name of this plugin.
     */
    public function __construct( $plugin_name ) {
        $this->plugin_name = $plugin_name;
    }

    /**
     * [Method description]
     *
     * @since  1.0.0
     * @access public
     * @param  int $id Item ID.
     * @return array|WP_Error Item data on success, WP_Error on failure.
     */
    public function get_item( $id ) {
        // Implementation...
    }
}
```

Rules:
- Every class gets a PHPDoc block with `@since`.
- Every property gets a PHPDoc block with `@since`, `@access`, and `@var`.
- Every method gets a PHPDoc block with `@since`, `@access`, `@param`, and `@return`.
- Use `@access` to indicate visibility: public, protected, private.

---

## Hook Documentation

Document all custom WordPress hooks (actions and filters):

```php
/**
 * Fires before saving speaker data.
 *
 * @since 1.0.0
 * @param array $data Speaker data to be saved.
 * @param int   $id   Speaker ID.
 */
do_action( 'speakeasy_before_save_speaker', $data, $id );

/**
 * Filters the speaker display name.
 *
 * @since 1.0.0
 * @param string $name    Speaker name.
 * @param int    $id      Speaker ID.
 * @param array  $context Additional context data.
 * @return string Filtered speaker name.
 */
$name = apply_filters( 'speakeasy_speaker_display_name', $name, $id, $context );
```

Rules:
- Use "Fires" for actions, "Filters" for filters.
- Document all parameters passed to the hook.
- For filters, document the `@return` type.
- Use descriptive, namespaced hook names: `speakeasy_*`

---

## What Good Documentation Looks Like

```php
<?php
/**
 * Speaker management functionality.
 *
 * Handles CRUD operations for speakers. Does not handle speaker profiles —
 * that lives in class-speakeasy-profile.php.
 *
 * @package    WP_Speakeasy
 * @subpackage WP_Speakeasy/includes
 * @since      1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Speaker management class.
 *
 * @since 1.0.0
 */
class WP_Speakeasy_Speaker {

    /**
     * Get speaker data by ID.
     *
     * Returns NOT_FOUND if the speaker does not exist.
     * Returns DB_ERROR if the database query fails.
     *
     * @since  1.0.0
     * @param  int $speaker_id Speaker ID. Must be a positive integer.
     * @return array|WP_Error Speaker data on success, WP_Error on failure.
     *                        Error codes: 'invalid_id', 'not_found', 'db_error'.
     */
    public function get_speaker( $speaker_id ) {
        // Validate before hitting the DB — avoids a round-trip on obviously bad input.
        if ( ! $speaker_id || ! is_numeric( $speaker_id ) || $speaker_id < 1 ) {
            return new WP_Error( 'invalid_id', __( 'Invalid speaker ID.', 'wp-speakeasy' ) );
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'speakeasy_speakers';

        // Use prepare() to prevent SQL injection.
        $speaker = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $speaker_id
            ),
            ARRAY_A
        );

        if ( null === $speaker ) {
            return new WP_Error( 'not_found', __( 'Speaker not found.', 'wp-speakeasy' ) );
        }

        if ( false === $speaker ) {
            return new WP_Error( 'db_error', __( 'Database error.', 'wp-speakeasy' ) );
        }

        return $speaker;
    }
}
```

---

## What Bad Documentation Looks Like — Do Not Write This

```php
// BAD: no file-level PHPDoc, no prevent-direct-access check
class Speaker {

    // BAD: no PHPDoc on property
    public $name;

    // BAD: no PHPDoc, no @param, no @return, no @since
    public function get( $id ) {
        // BAD: describes what, not why
        // Get the speaker from the database
        $speaker = $wpdb->get_row( "SELECT * FROM speakers WHERE id = $id" );
        return $speaker;  // BAD: direct variable interpolation in SQL, no WP_Error
    }
}
```

---

## WordPress-Specific Documentation Rules

1. **Always use translation functions** with text domain:
   ```php
   __( 'Text to translate', 'wp-speakeasy' )
   esc_html__( 'Text to translate and escape', 'wp-speakeasy' )
   ```

2. **Document WordPress globals when used**:
   ```php
   /**
    * @global wpdb $wpdb WordPress database abstraction object.
    */
   global $wpdb;
   ```

3. **Use WordPress function naming conventions**:
   - Prefix all functions: `speakeasy_function_name()`
   - Use underscores, not camelCase
   - Classes use uppercase words: `WP_Speakeasy_Class_Name`

4. **Follow WordPress coding standards for spacing and braces**:
   ```php
   // Good
   if ( condition ) {
       do_something();
   }

   // Bad
   if (condition) {
       do_something();
   }
   ```

---

## Documentation Anti-Patterns

1. **Describing the code.** The code is the description. Comments explain what the code cannot.

2. **Stale comments.** A comment that contradicts the code is worse than no comment. If you change code, update its comment in the same edit.

3. **`// TODO` without a ticket.** Undated, untracked TODOs accumulate and rot. Every TODO gets a ticket reference or a date.

4. **Over-documenting internals.** Not every helper function needs a block. Obvious private helpers do not.

5. **Under-documenting the error contract.** Every exported function's `@return` must describe the failure paths and WP_Error codes.

6. **Missing `@since` tags.** WordPress standards require `@since` on all classes, methods, functions, and hooks.

7. **Untranslatable strings.** All user-facing strings must use `__()`, `_e()`, or similar i18n functions with the 'wp-speakeasy' text domain.

8. **Missing prevent-direct-access checks.** Every PHP file must check `if ( ! defined( 'ABSPATH' ) ) { exit; }` to prevent direct file access.
