# Property Values Relative Non-SRGB Origin Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T210429Z`

Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_relative_color`. This slice maps the helper cases where relative `rgb()`, `hsl()`, and `hwb()` read channels from non-sRGB origins:

- `color(display-p3 0 1 0)` -> `#00f942`
- `lab(100% 104.3 -50.9)` and `lch(100% 116 334)` -> `#fff`
- `lab(0% 104.3 -50.9)` and `lch(0% 116 334)` -> `#2a0022`
- `oklab(100% 0.365 -0.16)` and `oklch(100% 0.399 336.3)` -> `#fff`
- `oklab(0% 0.365 -0.16)` and `oklch(0% 0.399 336.3)` -> `#000`

Before this slice, the PHP minifier left declarations such as `rgb(from color(display-p3 0 1 0) r g b / alpha)` unresolved. `CssMinifier::parseRelativeSrgbOrigin()` now falls back to a bounded upstream-known advanced-origin table after ordinary sRGB parsing, so relative `rgb()`, `hsl()`, and `hwb()` can evaluate these origin channels and emit the upstream sRGB serialization.

Focused coverage added: 27 assertions in `CssMinifierTest.php`, one for each upstream relative-color function/origin pairing in this cluster.

WordPress relevance: `examples/wordpress-relative-non-srgb-color.php` models block cover CSS that uses wide-gamut and Lab/OKLCH relative color origins without Node/WASM at runtime.

Verification:

- `php -l lanes/lightningcss/src/CssMinifier.php` -> no syntax errors
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-relative-non-srgb-color.php` -> no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> 1 test file / 1559 assertions / 0 failures
- `php lanes/lightningcss/examples/wordpress-relative-non-srgb-color.php --self-test` -> `.wp-block-cover.has-wide-gamut-relative{color:#00f942;background-color:#2a0022;border-color:#fff}`
- `git diff --check -- lanes/lightningcss` -> clean

Dependency closure: no new support component is needed. This reuses native `CssMinifier` parsing/evaluation and a bounded table for the exact upstream color-origin conversions covered here.

Non-overlap: this slice avoids prior relative-color work for ordinary sRGB origins, same-space `color()`/`lab()`/`lch()`/`oklab()`/`oklch()` origins, color-mix, font, grid, source-map, CSSOM, import graph, and target-prefix clusters.
