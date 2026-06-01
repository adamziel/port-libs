# Property Values Color Font Grid Parity - 2026-06-01T071548Z

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T071548Z`
- Source truth: upstream `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_color`.
- Cluster: basic `rgb()` / `rgba()` color value minification in declaration values.

## Behavior

The pinned upstream `test_color` cases serialize:

- `rgb(255, 255, 0)` and opaque `rgba(255, 255, 0, 1)` to `#ff0`.
- `rgba(255, 255, 0, 0.8)` to `#ff0c`.
- `rgb(128, 128, 128)` to `gray`.
- comma and space separated `rgb(123 ... 255)` forms to `#7bffff`.

The native PHP minifier already matched those outputs on this base, so this slice adds focused parity assertions and extends the WordPress color-value smoke instead of changing production code.

## Evidence

- `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> no syntax errors.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php --self-test` -> passed and printed the expected minified CSS including `.wp-block-cover.has-rgb-overlay{color:#ff0;background-color:#ff0c;border-color:gray;outline-color:#7bffff}`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 1901 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6661 assertions, 0 failures`.

## Status Delta

- Focused lane assertion count moved from `6655` to `6661`.
- `lane-status.json` now records `phpPass: 6661`.
- Mapped denominator remains unchanged because these are additional assertions for an already mapped upstream `test_color` family.

## Dependency Closure

No new support component is needed. The existing native PHP color parser/minifier path handles this cluster without Node, WASM, or upstream Rust runner execution.

## Non-overlap

This does not repeat the recent grid line-name, color-mix LCH/OKLCH tail, font target fallback, or container-style color important slices. It only locks the basic RGB/RGBA rows from upstream `test_color` that were not directly asserted in the PHP lane.
