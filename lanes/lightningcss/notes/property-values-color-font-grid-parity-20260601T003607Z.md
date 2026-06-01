# LightningCSS Property Values - XYZ relative color values

Slice: `lightningcss-property-values-color-font-grid-parity-20260601T003607Z`

Source truth:

- Upstream: `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream location: `src/lib.rs::test_relative_color`, XYZ-family `color(from color(${colorSpace} ...) ${colorSpace} ...)` loop for `xyz`, `xyz-d50`, and `xyz-d65`.
- Behavior cluster: same-space relative `color()` value minification for `x/y/z` channels, alpha, permutation, `calc()`, `none`, percentages, numeric replacement, and `xyz-d65` serialization through canonical `xyz`.

Red-first evidence:

- Before the implementation, these probes stayed serialized as raw relative color functions instead of upstream-minified color values:
  - `color(from color(xyz 7 -20.5 100) xyz x y z)`
  - `color(from color(xyz-d50 7 -20.5 100 / 40%) xyz-d50 calc(x) calc(y) calc(z) / calc(alpha))`
  - `color(from color(xyz-d65 7 -20.5 100) xyz-d65 y z x)`

Implementation:

- `CssMinifier::parseRelativeColorSpaceArguments()` now admits `xyz`, `xyz-d50`, and `xyz-d65` target spaces for relative `color()` values.
- `CssMinifier::parseRelativeColorSpaceOrigin()` matches target/origin spaces through the existing `xyz-d65` to `xyz` canonical spelling and maps XYZ-family origins to `x`, `y`, and `z` channel names.
- Existing relative color component evaluation handles replacement constants, percentages, `calc()`, `none`, alpha clamping, and serialization; RGB-like `color()` spaces keep the existing `r/g/b` path.

Verification evidence:

- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 1668 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 5171 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-xyz-relative-color-minifier.php --self-test` -> pass.
- `php -l` on changed PHP files and `git diff --check -- lanes/lightningcss` are required final gates for this handoff.

Coverage delta:

- `CssMinifierTest.php` adds 36 focused TestRunner assertions.
- Conservative mapped coverage moves `2238 / 3532` to `2250 / 3532` by counting 12 upstream `src/lib.rs::test_relative_color` helper templates, while PHP expands them across `xyz`, `xyz-d50`, and `xyz-d65`.
- `lane-status.json` `phpPass` moves `5135` to `5171`.

Non-overlap:

- This does not repeat accepted relative RGB/HSL/HWB sRGB-origin colors, non-sRGB origin conversion, same-space `srgb`/`srgb-linear`/`a98-rgb`/`rec2020`/`prophoto-rgb` relative `color()` cases, lab/oklab/lch/oklch relative colors, color-mix batches, font descriptor/shorthand slices, grid value slices, CSSOM, CSS Modules, source-map, media-query, or target-prefix work.
- The stale main-repo rework note `port-lightningcss-current-rebase-20260525T053931Z-02383337.needs-lane-rework.md` covers an old custom-media/import-tail conflict and is unrelated to this property-value slice.

Dependency closure:

- No new support component is needed. The slice reuses the native PHP `CssMinifier` parser and color serializer; no Node, Rust, WASM, browser, external service, or support-library runner is required.

Root harness:

- not run - isolated micro-slice
