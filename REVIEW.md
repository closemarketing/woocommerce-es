# Review instructions

These rules override the default review calibration for this repository.
This is a WordPress plugin/theme codebase built by a professional agency;
review it against WordPress.org plugin guideline standards, not generic
web-app conventions.

## What "Important" means here

Reserve 🔴 Important for issues that would break a site, leak data, or get
the plugin rejected/pulled from WordPress.org:

- Missing or incorrect nonce verification on any form submission, AJAX
  handler, or REST endpoint that changes state
- Missing capability checks (`current_user_can()`) before a privileged
  action (settings changes, data deletion, user management)
- Unescaped output: any variable printed without `esc_html()`, `esc_attr()`,
  `esc_url()`, or `wp_kses()` where user- or DB-sourced data could reach it
- Unsanitized input: `$_POST`/`$_GET`/`$_REQUEST` used without
  `sanitize_text_field()`, `sanitize_key()`, etc. before storage or use
- Raw SQL or `$wpdb` queries built with string concatenation instead of
  `$wpdb->prepare()`
- Direct file access not guarded (missing `if ( ! defined( 'ABSPATH' ) ) exit;`
  at the top of a file that shouldn't be loaded standalone)
- `eval()`, `extract()` on superglobals, or dynamic `include`/`require` with
  unsanitized paths
- Missing `wp_verify_nonce()` / `check_ajax_referer()` / `check_admin_referer()`
  where applicable
- Backward-incompatible changes to public hooks, filters, or documented
  function signatures

Everything else — formatting, variable naming, minor refactors, missing
docblocks — is 🟡 Nit at most.

## Cap the nits

Report at most five Nits per review. If there are more, summarize the rest
as a count ("plus 6 similar style items") instead of posting them inline.
If nothing Important was found, lead the summary with "No blocking issues."

## Do not report

- Anything already enforced by WPCS/PHPCS or the project's linter/formatter
- Generated files: `languages/*.pot`, `build/`, `dist/`, minified `*.min.js`
  / `*.min.css`
- `vendor/` and any bundled third-party library
- Test fixtures under `tests/` that intentionally use unsafe patterns to
  test sanitization functions

## Always check

- New AJAX actions (`wp_ajax_*`) and REST routes verify nonce/permission
  callbacks before touching data
- New settings fields are sanitized in their `register_setting()` callback
- User-facing strings are wrapped in translation functions (`__()`,
  `_e()`, `esc_html__()`, etc.) with the correct, consistent text domain
- Database schema changes include an upgrade routine (versioned via
  `dbDelta()` or an options-based version check), not just a fresh
  `CREATE TABLE`
- Enqueued scripts/styles use `wp_enqueue_script()`/`wp_enqueue_style()`
  with proper dependencies and versioning, not hardcoded `<script>` tags
- Any new option/meta key is prefixed to avoid collisions with core or
  other plugins

## Verification bar

Behavior claims need a `file:line` citation showing the actual code path,
not an inference from a function name. If you can't point to the missing
check, don't flag it as Important — downgrade to Nit or skip.

## Re-review convergence

After the first review on a PR, suppress new Nits on unchanged lines and
report Important findings only, so a small follow-up fix doesn't trigger a
fresh wave of style comments.

## Summary shape

Open the review body with a one-line tally, e.g. `1 Important, 3 Nit`, and
lead with "No security issues found" when that's true — the author wants
the shape of the result before the details.