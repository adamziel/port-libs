# LightningCSS Vertical Align Property-Value Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T222054Z`

Base accepted HEAD: `6cff27008844e2e4a3255962746465ff174dc963`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | sed -n '6660,6690p'`.
- Mapped helpers: `src/lib.rs::test_vertical_align` has two `minify_test` cases for `vertical-align: middle` and `vertical-align: 0.3em`.

## Native PHP Delta

- `CssMinifier` now applies single-token numeric dimension minification to `vertical-align` values, covering upstream `0.3em -> .3em` while preserving keyword values such as `middle`.
- `CssMinifierTest.php` adds the two pinned upstream vertical-align minifier cases.
- `wordpress-vertical-align-minifier.php` models block media/text CSS emitted without Node/WASM.

## Verification

- `php -l lanes/lightningcss/src/CssMinifier.php` passed.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-vertical-align-minifier.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` passed: `1 test files, 1578 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 4651 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-vertical-align-minifier.php` passed and emitted `.wp-block-media-text__media{vertical-align:.3em}.wp-block-media-text__content{vertical-align:middle}`.
- `git diff --check -- lanes/lightningcss` passed.

## Non-Overlap

This slice is limited to `vertical-align` property-value minification. It does not repeat accepted grid, font, color, border-spacing, aspect-ratio, target-prefix, source-map, CSSOM, CSS Modules, bundle/import graph, media-query, or custom at-rule clusters.

## Dependency Closure

No new support component is needed. The slice reuses the existing `CssMinifier` tokenizer and numeric dimension minifier.
