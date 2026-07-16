# LightningCSS LCH/Oklch Color-Mix Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T153012Z`

Accepted base: `a7ecc1c03f47b919bbd97dfd951b936133999f9f`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs`, focused on `src/lib.rs::test_color_mix` lch/oklch generated helper loops.
- Mapped 56 focused upstream-aligned checks: 38 lch/oklch same-space polar value-normalization cases plus 18 hue interpolation mode cases.

Native PHP delta:

- `CssMinifier` now resolves same-space `color-mix(in lch, ...)` and `color-mix(in oklch, ...)` when both color stops are direct polar color functions.
- Weight handling matches the accepted lab/oklab path for implicit weights, leading/trailing percentages, scale-down above 100%, and sub-100% alpha scaling.
- Lightness and chroma interpolate with alpha premultiplication; hue interpolation remains weight-based and supports upstream `shorter`, `longer`, `increasing`, `decreasing`, and `specified hue` modes.
- `none` lightness/chroma/hue and `/none` alpha are preserved through the color-mix resolver. The declaration color scanner now treats `color-mix(...)` as an atomic function so nested `none` hue tokens are not normalized to zero before interpolation.
- `wordpress-color-value-minifier.php` now includes a polar overlay block using lch/oklch `color-mix()` without Node.

Evidence:

- `php -l lanes/lightningcss/src/CssMinifier.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 930 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 1974 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> exits 0 and emits the expected minified CSS.
- `git diff --check -- lanes/lightningcss` -> exits 0.

Non-overlap:

- Does not repeat accepted sRGB color-mix normalization, same-space lab/oklab rectangular color-mix, Color 4 color-function syntax minification, advanced-color target fallback layers, font-family/font-shorthand/font-face/font-palette/font-feature slices, grid shorthand/longhand composition, dashed-ident bundle graph behavior, custom at-rule visitors, background-clip target prefixing, or CSSOM shorthand slices.
- This slice is only direct lch/oklch polar color-mix value resolution. HSL/HWB color-mix, `color()` color-space mixes, and conversion of named/sRGB colors into lch/oklch interpolation spaces remain follow-up work.

Dependency closure:

- No new support component is needed. This reuses the bounded native `CssMinifier` declaration scanner, top-level splitter, color-function parser, color-mix weight normalization, and numeric serializer.

Root harness status: not run - isolated micro-slice.
