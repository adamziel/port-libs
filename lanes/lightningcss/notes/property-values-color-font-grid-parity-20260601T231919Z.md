# LightningCSS Grid Formatter Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T231919Z`

Base accepted HEAD: `e0225757d2b557bc0e76a31989620cff5bf99d9b`

Upstream source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_grid`.

## Behavior

This slice ports the grid pretty-printer parity boundary where `grid-template-*` longhands appear with `grid-auto-flow`, `grid-auto-rows`, and `grid-auto-columns`.

- Default `grid-auto-flow: row`, `grid-auto-rows: auto`, and `grid-auto-columns: auto` now compose with template longhands into the `grid` shorthand.
- `grid-template-areas: none` plus defaulted one axis now composes row/column auto-flow dense forms such as `grid: auto-flow dense 1fr / ...` and `grid: ... / auto-flow dense 1fr`.
- Non-composable area templates keep `grid-template` and emit auto rows, auto columns, then auto flow to match upstream formatting.
- Variable `grid-auto-flow` values remain uncomposed and preserve the original longhand order.

## Evidence

- `php -l lanes/lightningcss/src/CssFormatter.php`: passed.
- `php -l lanes/lightningcss/tests/CssFormatterTest.php`: passed.
- `php -l lanes/lightningcss/examples/wordpress-grid-template-formatter.php`: passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssFormatterTest.php`: `1 test files, 31 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-grid-template-formatter.php --self-test`: passed.
- `php tools/run-tests.php lanes/lightningcss/tests`: `13 test files, 9108 assertions, 0 failures`.

Status delta: `phpPass` moves `9102 -> 9108` from six new focused formatter assertions. Mapped upstream denominator remains `2439 / 3532`.

## Non-Overlap

This does not touch the accepted source-map empty generated-line offset slice, media range/layer mixed-list formatting, CSS Modules, CSSOM, custom at-rule visitors, bundle/import graph, or target-prefix behavior. The scope is bounded to CSS formatter property-value grid shorthand parity.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP `CssFormatter` declaration composer and formatter helpers. Rust, Node, and WASM upstream runners were not executed for this isolated micro-slice.
