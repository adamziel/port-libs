# LightningCSS Property Values Color Font Grid Parity - 2026-06-01T15:36Z

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T153600Z`
- Accepted base: `58f1b15e81ee03d64915f36a0a94fc3dd31fae09`
- Upstream source truth: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Parity Added

Added focused PHP coverage for `src/lib.rs::test_relative_color` same-space `color()` rows that were not pinned in the lane tests:

- Out-of-gamut RGB-family `color()` relative origins keep unbounded channels while clamping alpha.
- Percentage constants in same-space `color()` relative components serialize as unbounded numeric channel values.
- XYZ relative colors omit alpha when the relative component list does not request it.
- XYZ relative colors map `none` source channels to zero during same-space readback.

The PHP implementation already matched these upstream behaviors, so this slice adds acceptance-grade focused assertions and updates the WordPress XYZ relative-color smoke without changing production source.

## Evidence

- Before focused file: `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 2062 assertions, 0 failures`
- After focused file: `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 2101 assertions, 0 failures`
- Updated example: `php lanes/lightningcss/examples/wordpress-xyz-relative-color-minifier.php --self-test` -> passed
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8471 assertions, 0 failures`

## Non-Overlap

This does not repeat the latest accepted CSS Modules dangling-combinator, prefixed transform CSSOM, or source-map generated-only child pruning work. It also avoids the earlier color/font/grid slices that covered relative Oklab black-origin behavior, font target fallbacks, font palette fallbacks, grid shorthand values, and grid placement values.

## Dependency Closure

No new support component is needed. The existing native PHP `CssMinifier` relative color evaluator and color serializer are reused.

## Next Task

Continue property/value parity with uncovered upstream rows that are not already represented in PHP, preferably with red-first behavior gaps in font descriptors, grid CSSOM/read-write, advanced color fallback target prefixing, or parser recovery boundaries.
