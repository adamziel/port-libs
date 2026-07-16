# LightningCSS Property Values Color Font Grid Parity 2026-06-01T10:30:40Z

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Bounded cluster: `src/lib.rs::test_grid` direct `minify_test` cases for `grid-auto-flow-row-auto-rows-multiple`, `grid-auto-flow-column-auto-rows`, and `grid-auto-flow-column-auto-rows-multiple`.
- Non-overlap: avoided already accepted/recent color alpha, custom-property advanced-color, font target fallback, font oblique default-angle, basic grid track-list, grid auto-row track-list, and default row `grid:auto-flow` shorthand clusters.

## Delta

- Added three focused PHP assertions for direct `grid` shorthand auto-flow row/column values followed by `grid-template-areas`.
- Updated the WordPress grid value minifier smoke with a query layout using `grid: 1fr / auto-flow 40px max-content` plus a later template area map.
- Updated `lane-status.json` from `7402` to `7405` PHP assertions after the full focused LightningCSS lane run.

## Verification

- `php -l lanes/lightningcss/tests/CssMinifierTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/CssMinifierTest.php`
- `php -l lanes/lightningcss/examples/wordpress-grid-value-minifier.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-grid-value-minifier.php`
- `php lanes/lightningcss/examples/wordpress-grid-value-minifier.php --self-test`
  - Passed; emitted the expected minified WordPress grid CSS including `.wp-block-query.is-style-auto-flow-column-areas`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php`
  - `1 test files, 1943 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7405 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The existing native PHP `CssMinifier` grid shorthand and declaration-composition path covers the upstream behavior; this slice makes the parity countable and keeps the WordPress smoke current.

## Root Harness

Not run - isolated micro-slice.
