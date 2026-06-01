# LightningCSS Grid Default Auto-Flow Shorthand Parity

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T081748Z`
- Accepted base: `cd382f66a9c80c833a3567dcc34622923a1e8fb9`
- Upstream source truth: pinned LightningCSS `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_grid`

## Behavior

Pinned upstream minifies direct default row auto-flow grid shorthands without an explicit row track:

- `.foo { grid: auto-flow / 200px }` -> `.foo{grid:none/200px}`
- `.foo { grid: auto-flow auto / 100px }` -> `.foo{grid:none/100px}`
- `.foo { grid: auto-flow / auto }` -> `.foo{grid:none/auto}`
- `.foo { grid: auto-flow / none }` -> `.foo{grid:none}`
- `.foo { grid: auto-flow / minmax(0, 1fr) }` -> `.foo{grid:none/minmax(0,1fr)}`

The upstream native transform also preserves `grid:auto-flow/...` when a following `grid-template-areas` declaration needs row auto-placement semantics. This slice keeps that accepted preservation path and only elides the default row auto-flow form when no later `grid-template-areas` in the declaration block depends on it.

## Implementation

- `CssMinifier::composeGridDeclarationList()` now runs grid composition for direct `grid:` declarations, not only `grid-*` longhands.
- Added a bounded grid shorthand rewrite that converts `grid:auto-flow / <columns>` and `grid:auto-flow auto / <columns>` to the upstream `grid:none/<columns>` form.
- The rewrite skips important declarations and skips any `grid` shorthand with a later `grid-template-areas` declaration before the next `grid` or `grid-template`.
- `wordpress-grid-value-minifier.php` now expects the native PHP minifier to emit `grid:none/minmax(0,1fr)` for a simple block grid default-row auto-flow shorthand.

## Verification

- Red probe before the fix:
  - `php -r 'require "tools/bootstrap.php"; $m = new PortLibs\LightningCSS\CssMinifier(); echo $m->minify(".foo { grid: auto-flow / 200px }"), PHP_EOL;'`
  - returned `.foo{grid:auto-flow/200px}`.
- Pinned upstream native oracle:
  - `.foo { grid: auto-flow / 200px }` returned `.foo{grid:none/200px}`.
  - `.foo { grid: auto-flow / 1fr 2fr 1fr; grid-template-areas: "  .   one  .  "; }` returned `.foo{grid:auto-flow/1fr 2fr 1fr;grid-template-areas:".one."}`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` passed: `1 test files, 1929 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 6898 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/CssMinifier.php` passed.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-grid-value-minifier.php` passed.
- `php lanes/lightningcss/examples/wordpress-grid-value-minifier.php --self-test` passed.
- `git diff --check -- lanes/lightningcss` passed.

## Non-Overlap

This does not repeat accepted grid row auto-flow preservation for area-bearing shorthands. It complements that patch by applying upstream default-row elision only to standalone direct `grid` shorthands. It also avoids font oblique default angles, color-mix/relative color, media range/layer recovery, CSS Modules, source maps, bundle/import graph, CSSOM, custom at-rule, and target-prefixing surfaces.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `CssMinifier` declaration-entry composer and top-level value splitter.

## Follow-Up

Full upstream Rust, Node, and WASM runners were not run in this isolated lane. Continue with non-overlapping property-value/font/grid cases or pivot to source-map, CSS Modules, bundle/import graph, media-query, CSSOM, custom-at-rule, and target-prefix parity.
