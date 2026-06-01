# LightningCSS Property Values: Grid Auto-Flow Row Shorthand Parity

## Source Truth

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T073235Z`
- Upstream: `parcel-bundler/lightningcss` pinned by `UPSTREAM_TEST_MANIFEST.json` at `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Focused upstream function: `src/lib.rs::test_grid`

## Delta

- Fixed `CssMinifier` grid shorthand minification so direct row auto-flow forms keep `grid:auto-flow/...` instead of being rewritten to `grid:none/...`.
- Updated the existing focused assertions for the pinned upstream row auto-flow plus later `grid-template-areas` cases:
  - `grid: auto-flow / 1fr 2fr 1fr`;
  - `grid: auto-flow auto / 100px 100px`;
  - `grid: dense auto-flow / 1fr 2fr`.
- Updated the WordPress grid value example so block layout CSS preserves row auto-placement semantics for simple and area-bearing auto-flow grids.

## Red-First Evidence

- Current-base probe before the fix produced `.test-auto-flow-row-1{grid:none/1fr 2fr 1fr;grid-template-areas:".one."}` for the upstream row auto-flow shorthand case.
- Pinned upstream `src/lib.rs::test_grid` preserves the row auto-flow shorthand and only removes the default `auto` row size, yielding `grid:auto-flow/...`.

## Non-Overlap

- This slice only corrects direct row auto-flow `grid` shorthand property-value minification.
- It does not repeat accepted grid longhand composition, grid-template area composition for concrete row tracks, CSSOM grid auto-flow read/write behavior, font value parity, color-mix/relative color slices, source-map, CSS Modules, bundle/import graph, media-query, custom-at-rule, or target-prefix clusters.
- The main handoff rework scan found no current LightningCSS lane rework notes for this micro-slice.

## Verification

- `php -l lanes/lightningcss/src/CssMinifier.php` passed.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-grid-value-minifier.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` passed: `1 test files, 1902 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-grid-value-minifier.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 6722 assertions, 0 failures`.
- Conservative mapped coverage remains `2360 / 3532`; this patch adds `+1` PHP assertion inside an already represented upstream grid helper cluster.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `CssMinifier` grid shorthand, top-level splitter, and declaration value normalization path.

## Follow-Up

Full upstream Rust/Node/WASM runners were not run in this isolated lane. Continue non-overlapping property-value parity on remaining color/font/grid edges or pivot to source-map, CSS Modules, bundle/import graph, media-query, CSSOM, custom-at-rule, and target-prefix parity.
