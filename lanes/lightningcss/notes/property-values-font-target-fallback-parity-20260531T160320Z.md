# LightningCSS Font Target Fallback Parity - 2026-05-31

## Source Truth

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T160320Z`
- Base accepted HEAD: `babccb1e8657d71e59b3c627c9000c66f8705d7f`
- Upstream: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Focused upstream function: `src/lib.rs::test_font`, target-aware system-ui fallback expansion and fallback-pruning prefix tests.

## Native Delta

- Added target-aware font fallback handling in `TransitionPrefixer`:
  - expands `system-ui` font-family fallbacks for legacy Safari and Firefox targets;
  - prunes older fallback declarations once all requested targets support cqw font sizes, `xxx-large`, variable font weights, `system-ui`, and oblique-angle font-style;
  - handles the same pruning in `font` shorthand while preserving custom-property fallback guards.
- Updated the `TransitionPrefixer` minifier pre-pass so a later target-sensitive `font` shorthand does not erase an earlier `font` fallback before target rewriting runs.
- Added a WordPress block typography smoke for shared-hosting CSS delivery without Node.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 345 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-font-target-fallback.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 2041 assertions, 0 failures`
- `php -l lanes/lightningcss/src/CssMinifier.php`
  - no syntax errors
- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - no syntax errors
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-font-target-fallback.php`
  - no syntax errors
- `php -r '<json validation one-liner>'`
  - `UPSTREAM_TEST_MANIFEST.json: OK`
  - `lane-status.json: OK`
- `git diff --check -- lanes/lightningcss`
  - passed

## Coverage Movement

- PHP lane assertions: `2025 -> 2041`
- Conservative mapped denominator: `1340 / 3532 -> 1356 / 3532`
- Newly mapped checks: 16 focused upstream `test_font` target-boundary cases.

## Non-Overlap

- Avoided accepted pure CSS Modules selector, alpha-color fallback, outline CSSOM, FunctionExit visitor, malformed resolver-shape, custom-media import-tail/scanner, font-face, font-feature, font-palette, grid, and color-mix slices.
- The old `port-lightningcss-current-rebase-20260525T053931Z-02383337.needs-lane-rework.md` custom-media import-tail note is stale for this base; the lane status already carries accepted custom-media import-tail and comment-scanner behavior, so this patch stays on font target fallback parity.

## Dependency Closure

No new support component is needed. The slice reuses existing PHP parsing/minification/prefixing utilities under `lanes/lightningcss/src`.

## Next

Continue with non-overlapping LightningCSS property-value parity: remaining color/font/grid edge cases, CSSOM shorthand gaps, and source-map/bundler/CSS Modules integration behavior not already accepted.
