# LightningCSS Property Values: Grid Template Line-Name Parity

## Source Truth

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T062458Z`
- Upstream: `parcel-bundler/lightningcss` pinned by `UPSTREAM_TEST_MANIFEST.json` at `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Focused upstream function: `src/lib.rs::test_grid`

## Delta

- Added focused PHP assertions for direct upstream grid minifier rows:
  - `grid-template` shorthand with line-name groups on both axes and `repeat(auto-fit, ...)`;
  - direct `grid-template: auto 1fr / auto 1fr auto`;
  - direct `grid: none`;
  - direct `grid-auto-flow: column dense` canonicalization.
- Extended the WordPress grid value example with a block-query line-name template map that exercises the same native `grid-template` shorthand minifier path.
- No production source component was needed; the existing native PHP grid value path already matches these pinned upstream rows, and this slice locks the behavior with focused tests.

## Non-Overlap

- This only deepens direct `src/lib.rs::test_grid` property-value minifier coverage.
- It does not repeat accepted color-mix, relative color, advanced-color fallback, font target fallback, font-face, grid longhand composition, CSSOM, media-query, custom-at-rule, source-map, bundle/import, or target-prefix clusters.
- The main handoff rework scan found no current LightningCSS lane rework notes for this micro-slice.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` passed: `1 test files, 1824 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 6433 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-grid-value-minifier.php --self-test` passed.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-grid-value-minifier.php` passed.
- `git diff --check -- lanes/lightningcss` passed.
- Conservative mapped coverage remains `2359 / 3532`; this patch adds `+4` PHP assertions inside an already represented upstream grid helper cluster.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `CssMinifier` grid shorthand and track-list normalization path.

## Follow-Up

Full upstream Rust/Node/WASM runners were not run in this isolated lane. Continue non-overlapping property-value parity on remaining color/font/grid edges or pivot to source-map, CSS Modules, bundle/import graph, media-query, CSSOM, custom-at-rule, and target-prefix parity.
