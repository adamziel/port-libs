# LightningCSS Lab/Oklab Color-Mix Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T150848Z`

Accepted base: `5042ee5a640251937d88ffe1e25c7b681010f72f`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '22119,22310p'`.
- Mapped 38 upstream `src/lib.rs::test_color_mix` `minify_test` helpers for same-space `lab` and `oklab` rectangular color mixing.

Native PHP delta:

- `CssMinifier` now resolves `color-mix(in lab, ...)` and `color-mix(in oklab, ...)` when both stops are in the same rectangular color space.
- Weight handling matches upstream for implicit weights, leading/trailing percentages, scale-down above 100%, and sub-100% alpha scaling.
- Channel interpolation uses premultiplied alpha for numeric alphas and preserves upstream `none` component and `/none` alpha behavior.
- `wordpress-color-value-minifier.php` now covers perceptual block overlay colors using `lab` and `oklab` `color-mix()` without Node.

Evidence:

- `php -l lanes/lightningcss/src/CssMinifier.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 856 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 1797 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> exits 0 and emits expected minified block CSS.

Non-overlap:

- Does not repeat accepted sRGB `color-mix()` normalization, Color 4 color-function syntax minification, advanced-color target fallback layers, font-family/font-shorthand/font-face/font-palette/font-feature slices, or grid shorthand/longhand composition.
- This slice is only same-space rectangular `lab`/`oklab` color-mix value resolution; `lch`/`oklch` hue interpolation and `color()` color-space mixes remain follow-up work.

Dependency closure:

- No new support component is needed. This reuses the bounded native `CssMinifier` declaration scanner, top-level splitter, color-function parser, numeric serializer, and existing color-mix weight normalization.

Root harness status: not run - isolated micro-slice.
