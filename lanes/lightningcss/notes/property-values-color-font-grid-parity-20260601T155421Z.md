# Property Values Color/Font/Grid Parity - 2026-06-01T15:54:21Z

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T155421Z`
- Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_color`.
- Cluster: direct named color minification for `color: yellow`.

## Behavior

Upstream has an explicit helper row:

- `.foo { color: yellow }` -> `.foo{color:#ff0}`

The native PHP minifier already produced the upstream output through the shared color serializer. This slice makes that direct row countable in the focused `CssMinifierTest.php` basic color block and adds the same authored named-color path to the WordPress color-value smoke.

## Evidence

- `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> no syntax errors.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php --self-test` -> passed and included `.wp-block-cover.has-named-overlay{color:#ff0}`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 2064 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8551 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

## Status Delta

- `phpPass`: `8550 -> 8551`.
- `phpFail`: `0`.
- Conservative mapped coverage remains `2398 / 3532`; this deepens an already represented upstream `test_color` property-value cluster.

## Non-overlap

This avoids the accepted basic RGB/RGBA rows, advanced color functions, color-mix families, relative-color non-sRGB/currentColor rows, font-family/font-face/font shorthand, grid shorthand/auto-flow/template, target-prefix, CSSOM, source-map, bundle/import graph, CSS Modules, media-query, and custom at-rule clusters. It only locks the direct named-color `yellow` row from upstream `test_color`.

## Dependency Closure

No new support component is needed. The existing native PHP `CssMinifier` declaration-value scanner, named-color table, and color serializer cover this row without Node, WASM, Rust runners, or network access.

## Next Task

Continue with non-overlapping property-value parity only where a remaining upstream color/font/grid row is not already represented by focused PHP assertions.
