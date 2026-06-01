# LightningCSS property values color/font/grid parity - 2026-06-01 09:28:15Z

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T092815Z`
- Accepted base: `c5d5f0d16396d91eb61e17860e23daa5d67075e3`
- Upstream source truth: `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

This slice locks a bounded color-value cluster from upstream `src/lib.rs`:

- `test_color` lines 18299-18302: comma `rgba(123, 255, 255, 0.5)`, slash-alpha `rgb(123 255 255 / .5)`, and percent alpha forms serialize to `#7bffff80`.
- `test_color` lines 18499-18509: zero-alpha `rgba(255, 0, 0, 0)` preserves the upstream short alpha-hex behavior when alpha hex is available.
- `test_color` lines 18519-18576: clamped `rgba(123, 456, 789, 0.5)` and direct `#7bffff80` paths preserve the same 8-digit alpha color serialization boundary.

## Changes

- Added five direct `CssMinifierTest.php` assertions for upstream alpha color value serialization:
  comma rgba alpha, clamped rgba alpha, slash alpha rgb, zero-alpha rgba, and direct 8-digit hex.
- Extended `wordpress-color-value-minifier.php` with a block overlay `text-decoration-color` alpha value and expected `#7bffff80` output.
- Updated `lane-status.json` to record `7147` passing LightningCSS assertions and the current accepted-base evidence.

No production PHP source change was needed; the current minifier implementation already matched the pinned upstream output for this cluster.

## Verification

- `php -l lanes/lightningcss/tests/CssMinifierTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/CssMinifierTest.php`
- `php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-color-value-minifier.php`
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php --self-test`
  - Passed; emitted minified block CSS containing `text-decoration-color:#7bffff80`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php`
  - `1 test files, 1934 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7147 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - Passed with no output.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The existing PHP `CssMinifier` color parser/serializer is reused.

## Non-overlap

This slice does not repeat the accepted SVG paint advanced-color fallback, relative HSL byte parity, font oblique default-angle, grid auto-flow shorthand, or CSS Regions target-prefixing slices. It is limited to direct upstream alpha color-value serialization and one WordPress-facing block overlay smoke.

## Next Task

Continue remaining non-overlapping property-value parity in color/font/grid only where upstream-backed rows are not already represented, or pivot to the current higher-priority LightningCSS surfaces: source maps, CSS Modules, bundle/import graph, media queries, CSSOM read/write, custom at-rules, and target-prefix behavior.
