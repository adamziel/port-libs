# LightningCSS Property Values: Residual Grid Value Parity

## Source Truth

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T051422Z`
- Upstream: `parcel-bundler/lightningcss` pinned by `UPSTREAM_TEST_MANIFEST.json` at `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Focused upstream function: `src/lib.rs::test_grid`

## Delta

- Added focused PHP assertions for residual upstream grid value minifier rows:
  - standalone `grid-auto-columns` track-list compaction;
  - `grid-template-areas` dot-row compaction for the `".... foot"` case;
  - column-side `grid` `auto-flow` shorthand minification, including `dense auto-flow` canonical ordering;
  - direct `grid-auto-flow: row` and `grid-auto-flow: column`;
  - grid line placement normalization for `auto`, named lines, integer lines, and `span` count/name ordering;
  - `grid-row` and `grid-area` edge-value collapse boundaries.
- No production source component was needed; the existing native PHP grid value path already matches these pinned upstream cases, and the slice locks that behavior with focused tests.

## Non-Overlap

- This slice only deepens the already represented upstream `src/lib.rs::test_grid` property-value minifier cluster.
- It does not touch accepted color-mix, relative color, advanced-color fallback, font target fallback, font-face, grid longhand composition, CSSOM, media-query, custom-at-rule, source-map, bundle/import, or target-prefix clusters.
- The stale May 25 custom-media rework note is unrelated to this property-value slice.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` passed: `1 test files, 1789 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 6162 assertions, 0 failures`.
- Conservative mapped coverage remains `2353 / 3532`; this patch adds `+27` PHP assertions inside an already represented upstream grid minifier cluster.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `CssMinifier` grid value normalization path.

## Follow-Up

Full upstream Rust/Node/WASM runners were not run in this isolated lane. Continue property-value parity on non-overlapping font/color/grid gaps or pivot to CSSOM, bundle/import graph, source-map, media-query, custom at-rule, and target-prefix parity.
