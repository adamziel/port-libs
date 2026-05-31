# LightningCSS Relative RGB Color Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T163709Z`

Accepted base: `6b3dbcd9ba83baf454581e5cfdd21849ee67aa00`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '19233,19442p'`.
- Mapped 64 focused upstream `src/lib.rs::test_relative_color` `rgb(from ...)` sRGB-origin cases: no-op channel reads, nested relative rgb origins, zero/number/percentage replacements, alpha replacement, channel permutations, mixed number/percentage channels, simple `calc()` channel arithmetic, `none` components, and `rgb()`/`hsl()` origins.

Native PHP delta:

- `CssMinifier` now resolves bounded `rgb(from ...)` relative colors when the origin is already representable as sRGB.
- Relative channel evaluation supports `r`, `g`, `b`, `alpha`, numeric constants, percentages, `none`, and simple arithmetic inside `calc()`.
- Unsupported relative color forms remain untouched for later slices.
- `wordpress-color-value-minifier.php` now includes a block cover relative-color overlay smoke without Node/WASM.

Evidence:

- `php -l lanes/lightningcss/src/CssMinifier.php && php -l lanes/lightningcss/tests/CssMinifierTest.php && php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 1005 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 2377 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> exits 0 and emits expected minified block CSS with relative color outputs.

Non-overlap:

- Does not repeat accepted hwb/basic color minification, advanced Color 4 value normalization, srgb/lab/lch/oklab/oklch `color-mix()` normalization, grid shorthand/longhand/auto-flow placement composition, font-family/font-shorthand/font-face/font-palette/font-feature slices, or target-prefix fallback clusters.
- This slice is only the `rgb(from ...)` sRGB-origin relative color subcluster.

Dependency closure:

- No new support component is needed. This reuses the bounded native `CssMinifier` declaration scanner, top-level splitter, color serializer, basic rgb/hsl/hwb parser, and math normalization helpers.

Root harness status: not run - isolated micro-slice.
