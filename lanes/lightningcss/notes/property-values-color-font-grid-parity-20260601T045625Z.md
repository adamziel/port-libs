# LightningCSS Property Values: HSL Color-Mix Remaining Hue Parity

## Source Truth

- Upstream pinned `parcel-bundler/lightningcss` commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source behavior: `src/lib.rs::test_color_mix` HSL `color-mix()` helper cases for remaining hue-direction variants and `none` component interpolation.

## Delta

- Added 28 focused `CssMinifierTest.php` assertions for HSL `color-mix()` remaining `shorter hue`, `longer hue`, `increasing hue`, `decreasing hue`, `specified hue`, and missing-channel interpolation parity.
- Updated `wordpress-color-value-minifier.php` with decreasing-hue and `none`-channel HSL `color-mix()` values so the user-visible WordPress smoke covers the same native PHP minifier path.
- Updated lane status and the upstream manifest from conservative mapped coverage `2345 / 3532` to `2373 / 3532`, with full-lane PHP evidence at `13 files / 6087 assertions / 0 failures`.

## Non-Overlap

- This slice is limited to color property-value parity in the existing native `CssMinifier` path; it does not change bundle/import graph, source-map, CSS Modules, CSSOM, custom at-rule, media-query, target-prefixing, grid, or font implementation.
- The stale May 25 `CustomMediaTransformer` rework note is unrelated to this property-value cluster and was not replayed.

## Verification

- `php -l lanes/lightningcss/tests/CssMinifierTest.php`
- `php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` => `1 test files, 1790 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php --self-test`
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 6087 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `CssMinifier` color interpolation/minification path.

## Follow-Up

Full upstream Rust, Node, and WASM runners were not executed for this isolated micro-slice. Continue with non-overlapping color/font/grid property-value parity, especially cases that still lack direct PHP test coverage.
