# Isolated LightningCSS Page Rule Formatter Slice

- Worktree: `/tmp/port-libs-lightningcss-page-rule-formatter-20260524T220041Z`
- Base commit: `cd6a6b6997a20283b1797b2c4bcb157fa76ccf1a`
- Main checkout HEAD observed after artifact write: `03e27292c37167d151a99c5c9e6916b6f51cbbbc`
- Patch: `.tmux-team/tmp/isolate-lightningcss-page-rule-formatter-20260524T220041Z.patch`
- Ready marker: `.tmux-team/tmp/handoff-candidates/port-isolate-lightningcss-page-rule-formatter.ready`

## Scope

Touched files in the isolated patch:

- `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json`
- `lanes/lightningcss/src/CssFormatter.php`
- `lanes/lightningcss/tests/CssFormatterTest.php`
- `lanes/lightningcss/examples/wordpress-page-rule-formatter.php`
- `lanes/lightningcss/examples/wordpress-page-rule-validation.php`

The patch rebuilds only the upstream `src/lib.rs::test_page_rule` pretty-printer and validation slice. It adds a narrow `CssFormatter` that accepts `@page` rules, formats page declarations and page margin boxes with upstream-style indentation/blank-line separation, rejects unknown nested page at-rules, and rejects a page margin box nested inside another page margin box.

Excluded dirty-main changes:

- Did not copy the dirty main-checkout `CssFormatter.php` or `CssFormatterTest.php` whole-file additions because they contained unrelated formatter behaviors.
- Did not include `@property`, background-image URL formatting/minification, charset formatting, custom visitors, SVG helpers, source-map/package-resolution/browser-target behavior, or unrelated LightningCSS examples.
- Did not edit `lanes/lightningcss/lane-status.json`, `lanes/lightningcss/notes/upstream-inventory.md`, or `lanes/lightningcss/notes/wordpress-scenarios.md` after scope scan showed the status prose would pull unrelated existing feature names into the patch context.
- Did not activate support-library rows; paged media/PDF engines and source-map/package-resolution/browser-target rows remain inactive.

## Upstream Evidence

Targeted pristine upstream read:

- Command: `git -C /home/claude/port-libs/.upstream-cache/lightningcss rev-parse HEAD`
- Exit: 0
- Output: `22bdda3d190f1cd321d98026225cfc964af64ad9`

- Command: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show HEAD:src/lib.rs | sed -n '14975,15079p'`
- Exit: 0
- Evidence: upstream `test_page_rule` contains 10 `minify_test` calls, 2 pretty-printer `test` calls, and 2 `error_test` calls.

- Command: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show HEAD:src/lib.rs | sed -n '14975,15079p' | rg -c '\b(minify_test|test|error_test)\('`
- Exit: 0
- Count: `14`

## Verification

- Command: `php -l lanes/lightningcss/src/CssFormatter.php && php -l lanes/lightningcss/tests/CssFormatterTest.php && php -l lanes/lightningcss/examples/wordpress-page-rule-formatter.php && php -l lanes/lightningcss/examples/wordpress-page-rule-validation.php`
- Exit: 0
- Result: no syntax errors in all 4 touched PHP files.

- Command: `php lanes/lightningcss/examples/wordpress-page-rule-formatter.php`
- Exit: 0
- Result: emitted formatted `@page chapter:right` CSS with separated `@bottom-left-corner` and `@bottom-right-corner` margin boxes.

- Command: `php lanes/lightningcss/examples/wordpress-page-rule-validation.php`
- Exit: 0
- Result: emitted `Invalid @page nested at-rule: bottom-left`.

- Command: `php tools/run-tests.php lanes/lightningcss/tests/CssFormatterTest.php`
- Exit: 0
- Assertions: 5 assertions, 0 failures.

- Command: `php tools/run-tests.php lanes/lightningcss/tests`
- Exit: 0
- Assertions: 9 test files, 1042 assertions, 0 failures.

- Command: `jq empty lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json`
- Exit: 0

- Command: `git diff --check -- lanes/lightningcss`
- Exit: 0

- Command: `git diff --binary -- lanes/lightningcss > /home/claude/port-libs/.tmux-team/tmp/isolate-lightningcss-page-rule-formatter-20260524T220041Z.patch`
- Exit: 0
- Patch length: 656 lines.

- Command: `git -C /tmp/port-libs-lightningcss-page-rule-formatter-check-20260524T220041Z apply --check /home/claude/port-libs/.tmux-team/tmp/isolate-lightningcss-page-rule-formatter-20260524T220041Z.patch`
- Exit: 0
- Check base: `cd6a6b6997a20283b1797b2c4bcb157fa76ccf1a`

- Command: `git -C /tmp/port-libs-lightningcss-page-rule-formatter-check-current-20260524T220041Z apply --check /home/claude/port-libs/.tmux-team/tmp/isolate-lightningcss-page-rule-formatter-20260524T220041Z.patch`
- Exit: 0
- Check base: current main `HEAD` at worktree creation, `03e27292`.

## Decision

The patch is clean and verified. A ready marker was created. Integrator should accept this isolated slice, subject to the usual integrator-owned root aggregate gate.
