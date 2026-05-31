# LightningCSS Color Calc Custom Property Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T170531Z`

Accepted base: `261654988ed05a567ee0c91d111919c7b0fe6e36`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '19210,19229p'`.
- Mapped one upstream `src/lib.rs::test_color` `test()` helper with 5 focused PHP assertions for custom-property token streams containing `calc()` inside `rgb()`, `hsl()`, `oklab()`, `color()`, and comma-form `rgb()`.

Native PHP delta:

- `CssMinifier` now detects custom-property color functions whose authored token stream contains `calc()`.
- After existing math folding, those bounded color functions are canonicalized to LightningCSS-style colors: `#80808080`, `#40bfbf`, compact `oklab()`, compact `color(display-p3 ...)`, and `gray`.
- Non-calc custom advanced-color values remain untouched so accepted target-prefix fallback serialization is preserved.
- `wordpress-color-value-minifier.php` now includes a calculated overlay token smoke without Node/WASM.

Evidence:

- `php -l lanes/lightningcss/src/CssMinifier.php && php -l lanes/lightningcss/tests/CssMinifierTest.php && php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 1017 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 2494 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> exits 0 and emits expected minified block CSS with calculated custom color tokens.
- `git diff --check -- lanes/lightningcss` -> pass.

Non-overlap:

- Does not repeat accepted relative `rgb(from ...)` sRGB-origin color evaluation, basic/advanced color normalization, color-mix normalization, grid shorthand/auto-flow composition, font family/face/palette/feature slices, target-prefix fallback clusters, or CSSOM declaration-block work.
- This slice is only the calc-in-color custom-property token-stream helper from `src/lib.rs::test_color`.

Dependency closure:

- No new support component is needed. This reuses the bounded native `CssMinifier` declaration scanner, math folding helpers, color function parsers, and color serializers.

Root harness status: not run - isolated micro-slice.
