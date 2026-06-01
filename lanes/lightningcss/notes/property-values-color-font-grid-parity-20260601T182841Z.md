# LightningCSS Property Values Color/Font/Grid Parity - 2026-06-01T18:28:41Z

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T182841Z`
- Upstream source truth: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Focused upstream rows: `src/lib.rs::test_grid` minifier rows for explicit grid track lists:
  - `[first nav-start] 150px [main-start] 1fr [last]`;
  - `repeat(auto-fill, [col-start] minmax(100px, 1fr) [col-end])`;
  - multi-track `grid-auto-rows` and `grid-auto-columns` values containing `minmax()`, percentages, `.5fr`, and `fit-content()`.

## Changes

- Added four focused `CssMinifierTest` assertions for the pinned upstream grid track-list tail rows.
- Extended `wordpress-grid-value-minifier.php` with a navigation grid example that preserves named line tracks and multi-track implicit column sizing.
- Updated lane status and manifest evidence. `phpPass` moves `8934 -> 8938` for `+4` focused PHP assertions. Conservative mapped coverage remains `2399 / 3532` because this deepens an already represented upstream `src/lib.rs::test_grid` cluster.

## Evidence

- Before focused test: `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 2123 assertions, 0 failures`.
- After focused test: `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 2127 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8938 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-grid-value-minifier.php` -> exited `0` and emitted the expected minified CSS.
- PHP lint: `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors.
- PHP lint: `php -l lanes/lightningcss/examples/wordpress-grid-value-minifier.php` -> no syntax errors.
- JSON validation: `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` decode successfully.
- Diff hygiene: `git diff --check -- lanes/lightningcss` -> passed.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice avoids accepted source-map, bundle/import graph, CSS Modules, CSSOM read/write, media-query recovery, custom at-rule visitor, selector/parser recovery, target-prefixing, color/color-mix, font formatter/minifier, grid formatter, grid auto-flow shorthand, and grid-template area composition clusters. It only makes the remaining explicit grid track-list minifier rows countable in PHP.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `CssMinifier` grid value normalizer, the lane-local PHP TestRunner, and the existing WordPress grid example. No Node, Rust, WASM, network, browser, or external CSS engine dependency is introduced.

## Follow-Up

Continue property-value parity by auditing remaining upstream color/font/grid rows against accepted PHP coverage, or move to higher-priority LightningCSS gaps in source maps, bundle/import graph, CSS Modules, media-query recovery, CSSOM, custom at-rules, selectors, and parser recovery.
