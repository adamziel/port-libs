# LightningCSS Property Values Color/Font/Grid Parity 2026-06-01T14:42:06Z

## Scope

Ported a bounded, pinned upstream grid placement value cluster into focused PHP
coverage for `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`.

Source truth: `src/lib.rs::test_grid` placement minification rows for:

- `grid-row-start: 2 some-line` and `grid-row-start: some-line 2`
- `grid-row-start: span some-line 1` and `grid-row-start: span 5 some-line`
- `grid-row: 1`, `grid-row: 1 / auto`, and same-start/end reduction
- `grid-column: 1 / auto`
- `grid-area` reductions such as `a / b / a / b`, `a / b / c / b`, and
  `1 / 1 / 1 / 1`

The native PHP `CssMinifier` already produced the upstream outputs for these
rows. This slice makes the remaining direct upstream placement rows explicit
and countable, and extends `wordpress-grid-value-minifier.php` with query-block
placement permutations using the same reduction behavior.

## Verification

- `php -l lanes/lightningcss/tests/CssMinifierTest.php` - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-grid-value-minifier.php` - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` - 1 test file / 2058 assertions / 0 failures
- `php lanes/lightningcss/examples/wordpress-grid-value-minifier.php --self-test` - passed and emitted the minified WordPress grid CSS
- `php tools/run-tests.php lanes/lightningcss/tests` - 13 test files / 8303 assertions / 0 failures
- `git diff --check -- lanes/lightningcss` - passed

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused lane PHP evidence moves `8290 -> 8303` assertions with 13 new
  upstream `test_grid` placement checks.
- Conservative mapped coverage remains `2393 / 3532`; this deepens an already
  represented `src/lib.rs::test_grid` property-value cluster rather than
  claiming new denominator rows.

## Non-Overlap

This does not repeat accepted direct `grid` shorthand value coverage, grid
auto-flow default-row shorthand compaction, grid-template area composition,
CSSOM grid placement read/write behavior, font oblique/family slices, relative
color/color-mix slices, target-prefixing, source-map, bundle/import graph,
CSS Modules, custom at-rule, media-query, or parser recovery work.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
`CssMinifier` grid value minifier, line-value normalizer, shorthand reducer, and
declaration composer.

## Next Task

Continue property-value parity with another non-overlapping upstream color,
font, or grid value cluster, or move to higher-priority LightningCSS gaps in
source maps, bundle/import graph, CSSOM, media queries, selectors, parser
recovery, or target prefixing.
