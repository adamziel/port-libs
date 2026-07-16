# LightningCSS Property Values Color Mix Alpha Rounding Parity

Slice: `lightningcss-property-values-color-font-grid-parity-20260531T183430Z`

Base accepted HEAD: `1d7de15e4e85a2b8dbfd1c80922d2921091d0371`

## Source Truth

- Upstream: `parcel-bundler/lightningcss`
- Pinned commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Source file: `src/lib.rs`
- Upstream test: `test_color_mix`
- Targeted evidence: pristine `git show` reads of the HSL alpha-weighted cases around lines `21210-21255` plus adjacent HWB alpha-weighted cases.

This slice maps 13 additional upstream helper expectations for alpha-weighted HSL/HWB `color-mix()` normalization:

- HSL leading and explicit weights with alpha stops, including normalized over-100% weights and sub-100% alpha scaling.
- HWB alpha-weighted stop normalization, including left/right weighted alpha stops, over-100% normalization, sub-100% alpha scaling, and zero-left-weight right-only preservation.

## Implementation

- `CssMinifier` now applies a small RGB byte rounding bias only for alpha-weighted HSL/HWB color-mix paths where both stops contribute.
- The zero-weight boundary keeps the prior upstream-compatible right-only result, avoiding the earlier red-channel over-rounding regression.
- Added focused PHP assertions in `CssMinifierTest.php`.
- Added `wordpress-color-mix-alpha-parity.php` as a local WordPress-relevant smoke for block cover HSL/HWB alpha color mixes.

## Verification

- `php -l lanes/lightningcss/src/CssMinifier.php`
  - `No syntax errors detected in lanes/lightningcss/src/CssMinifier.php`
- `php -l lanes/lightningcss/tests/CssMinifierTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/CssMinifierTest.php`
- `php -l lanes/lightningcss/examples/wordpress-color-mix-alpha-parity.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-color-mix-alpha-parity.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php`
  - `1 test files, 1143 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 3073 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-color-mix-alpha-parity.php`
  - `.wp-block-cover.has-alpha-hsl-mix{color:#797245b3;border-color:#79724559}.wp-block-cover.has-alpha-hwb-mix{background-color:#8faa3c99;outline-color:#a0954659}`

Root harness: not run - isolated micro-slice.

## Coverage Delta

- Conservative mapped denominator: `1684 -> 1697 / 3532`.
- PHP lane pass count: `3060 -> 3073`.
- Full upstream Rust/Node/WASM runners were not executed for this isolated slice.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `CssMinifier` color parser and HSL/HWB color-mix math; no Node, Rust, WASM, external parser, or generated dependency is required.

## Non-Overlap

This is limited to alpha-weighted HSL/HWB `color-mix()` rounding parity. It avoids the accepted radial/conic gradient, HWB non-alpha baseline, HSL hue-interpolation baseline, color()/XYZ interpolation-space, font, grid, CSS Modules, source-map, CSSOM, custom-at-rule, media-query, and target-prefix clusters.
