# LightningCSS Logical Inset Target Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T144840Z`

Accepted base: `a187757827b58c999a1fc6cda2f4be5e163b73e9`

## Upstream Source Truth

- Pinned upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine reads:
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | sed -n '17200,17530p'`
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/compat.rs | sed -n '2096,2140p'`
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/targets.rs | sed -n '220,280p'`
- Mapped six additional `src/lib.rs::test_position` logical inset `prefix_test` helpers:
  - `inset-inline-start` to LTR `left` and RTL `right` selector variants.
  - `inset-inline-start` plus `inset-inline-end` to direction-sensitive `left`/`right` variants.
  - single-value `inset-inline` to physical `left` and `right` without selector splitting.
  - `inset-block-start` to `top`.
  - `inset-block-end` to `bottom`.
  - physical `inset` shorthand expansion to `top`, `bottom`, `left`, and `right` when LogicalInset is unsupported.

## Native PHP Delta

- `TransitionPrefixer` now lowers logical inset declarations when target browsers lack `LogicalInset` support.
- Browser boundary checks cover Safari 14.0/14.1, iOS Safari 14.4/14.5, Chrome 86/87, Firefox 62/63, IE, and `include`/`exclude` `LogicalProperties` feature flags.
- Direction-sensitive inline fallbacks reuse the existing LTR/RTL selector variant machinery.
- `wordpress-logical-inset-prefixer.php` models cover-block background and inner-container offsets for old Safari versus modern Safari targets.
- Conservative manifest coverage moves from `1232 / 3532` to `1238 / 3532`.

## Red-First Evidence

- Before implementation, the new focused assertions failed:
  - `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `1 test files, 285 assertions, 2 failures`.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - `No syntax errors detected in lanes/lightningcss/src/TransitionPrefixer.php`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-logical-inset-prefixer.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-logical-inset-prefixer.php`
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "OK\n";'`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 300 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 1776 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-logical-inset-prefixer.php --self-test`
  - `OK`
- `git diff --check -- lanes/lightningcss`
  - no whitespace errors

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted legacy text/sticky prefixes, display-flex prefixes, flex longhand/box-alignment prefixes, UI prefixes, print-color-adjust, image-set, keyframes, light-dark, media range/resolution, CSSOM, grid, source-map, bundle/CSS Modules, or custom at-rule visitor/parser clusters. It also leaves the two known display-flex cascade-order edge cases for a separate target-prefixing handoff.

## Dependency Closure

No new support component is needed. The implementation reuses the existing browser target-version encoder, feature include/exclude parsing, declaration scanner, minifier normalization, and LTR/RTL selector-variant machinery.
