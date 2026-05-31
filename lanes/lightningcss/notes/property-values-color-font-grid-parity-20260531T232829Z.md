# LightningCSS Property Values - sRGB color-mix missing RGB components

Slice: `lightningcss-property-values-color-font-grid-parity-20260531T232829Z`

Source truth:

- Upstream: `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream location: `src/lib.rs::test_color_mix`.
- Behavior cluster: three `minify_test` helpers where `color-mix(in srgb, ...)` receives `rgb()` stops with `none` in complementary RGB channels. LightningCSS carries the missing channel from the paired stop instead of interpolating it as zero.

Red-first evidence:

- Before the implementation, these three probes all serialized as `#404040` instead of upstream `gray`:
  - `color-mix(in srgb, rgb(128 128 none), rgb(none none 128))`
  - `color-mix(in srgb, rgb(50% 50% none), rgb(none none 50%))`
  - `color-mix(in srgb, rgb(none 50% none), rgb(50% none 50%))`

Implementation:

- `CssMinifier::parseSrgbColorMixRgbFunction()` preserves `none` RGB components as null while keeping normal numeric/percentage RGB component parsing.
- `CssMinifier::mixSrgbColorMixByteComponent()` carries a missing channel from the other color stop before falling back to premultiplied byte interpolation when both stops define the channel.
- The change is bounded to sRGB `rgb()`/`rgba()` color-mix inputs; HSL/HWB/Lab/LCH/OKLab/OKLCH/color() mix behavior is unchanged.

Verification evidence:

- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 1601 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 4824 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-color-mix-missing-rgb-channels.php --self-test` -> pass.
- `php -l` on changed PHP files and `git diff --check -- lanes/lightningcss` are required final gates for this handoff.

Coverage delta:

- Conservative mapped coverage moves `2198 / 3532` to `2201 / 3532`.
- `lane-status.json` `phpPass` moves `4821` to `4824`.

Non-overlap:

- This does not repeat the accepted concrete sRGB color-mix normalization batch, HSL/HWB/Lab/LCH/OKLab/OKLCH/color() color-mix batches, relative-color batches, grid-track value slices, font descriptor slices, CSSOM, CSS Modules, source-map, media-query, or target-prefix slices.
- Remaining advanced `color-mix()` named-color and non-sRGB edge cases are intentionally left for a later bounded property-value slice.

Dependency closure:

- No new support component is needed. The slice reuses the native PHP `CssMinifier` parser and color-mix serializer; no Node, Rust, WASM, browser, or external service runner is required.

Root harness:

- not run - isolated micro-slice
