# LightningCSS Property Values Color Font Grid Parity - 2026-05-31T17:44:03Z

## Scope

- Implemented one bounded upstream-backed property-value cluster in `CssMinifier`: `aspect-ratio` value minification.
- Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_size` lines 4261-4264.
- Mapped cases:
  - `aspect-ratio: auto`
  - `aspect-ratio: 2 / 3`
  - `aspect-ratio: auto 2 / 3`
  - `aspect-ratio: 2 / 3 auto`

## Delta

- Added top-level slash compaction and trailing `auto` canonicalization for `aspect-ratio`.
- Added four focused PHP assertions in `CssMinifierTest.php`.
- Added `wordpress-aspect-ratio-minifier.php` smoke for block image/video aspect-ratio declarations.
- Conservative upstream mapped coverage moves `1601 -> 1605 / 3532`.
- Full LightningCSS PHP evidence moves `2794 -> 2798 pass / 0 fail`.

## Non-Overlap

- Did not touch the stale custom-media `@import` media-tail rework note.
- Does not overlap accepted color clusters (`basic color`, `relative rgb`, `color-mix`, custom-property color calc), accepted font/font-face/font target fallback clusters, or accepted grid track/template/auto-flow/placement clusters.

## Dependency Closure

- No new support component needed. This reuses the existing `CssMinifier` declaration pipeline, top-level splitter, and number token minifier.

## Verification

- `php -l lanes/lightningcss/src/CssMinifier.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-aspect-ratio-minifier.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` => `1 test files, 1054 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2798 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-aspect-ratio-minifier.php` => matched expected minified CSS and exited 0.
- Root harness: not run - isolated micro-slice.
