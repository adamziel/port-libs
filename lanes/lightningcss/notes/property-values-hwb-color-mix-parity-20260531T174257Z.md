# LightningCSS HWB Color-Mix Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T174257Z`

Accepted base: `b1feedb755e93656cf717884940e8c64724c26f1`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | sed -n '21440,21670p'`.
- Mapped 9 upstream `src/lib.rs::test_color_mix` HWB helper cases covering `color-mix(in hwb, ...)` stop weighting, leading/trailing weight syntax, scale-up alpha when total weights are below 100%, and shortest sRGB hex serialization.

Native PHP delta:

- `CssMinifier` now accepts `hwb` as a `color-mix()` interpolation space.
- Added bounded HWB stop parsing for `hwb(...)` colors, shared leading/trailing percentage weight handling, alpha scaling, hue interpolation, HWB white/black component interpolation, and HWB-to-sRGB serialization.
- `wordpress-color-value-minifier.php` now includes a block cover HWB mixed overlay smoke without Node/WASM.

Red-first evidence:

- With only the new tests added, `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` failed at `css minifier maps upstream hwb color-mix value normalization`: expected `#93b334`, actual unresolved `color-mix(in hwb,...)`.

Verification:

- `php -l lanes/lightningcss/src/CssMinifier.php && php -l lanes/lightningcss/tests/CssMinifierTest.php && php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 1059 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 2803 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> exits 0 and emits expected HWB mixed block overlay CSS.
- `git diff --check -- lanes/lightningcss` -> pass.

Non-overlap:

- Does not repeat accepted sRGB/HSL/Lab/OKLab/LCH/OKLCH `color-mix()` normalization, relative `rgb(from ...)`, custom-property calc color minification, grid shorthand/auto-flow composition, font family/face/palette/feature slices, target-prefix fallback clusters, or CSSOM declaration-block work.
- This slice is only the bounded HWB `color-mix()` stop-normalization cluster from `src/lib.rs::test_color_mix`.

Dependency closure:

- No new support component is needed. This reuses the bounded native `CssMinifier` color function parser, color-mix weight handling, hue interpolation, alpha resolver, and sRGB serializer.

Root harness status: not run - isolated micro-slice.
