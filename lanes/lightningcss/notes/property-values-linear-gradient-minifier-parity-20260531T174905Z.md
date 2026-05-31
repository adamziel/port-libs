# Property Values Linear Gradient Minifier Parity - 2026-05-31T17:49:05Z

Source truth: pinned upstream parcel-bundler/lightningcss `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_gradients`.

Mapped behavior: 9 focused upstream linear-gradient minify helper cases:

- default `to bottom` and equivalent `180deg` / `.5turn` direction elision
- `to top` / `0` reversal for safe color stops
- percent stop inversion while reversing
- non-percent stop preservation with `0deg`
- default 50% interpolation hint omission
- adjacent same-color stop compaction (`red 30%, red 40%` to `red 30% 40%`)

Implementation: `CssMinifier::minifyGradientFunctions()` normalizes unprefixed `linear-gradient()` / `repeating-linear-gradient()` values after image-set normalization and before color keyword minification. Direction rewrites are gated to safe color tokens so styled-jsx placeholder recovery keeps authored `to bottom` gradients.

Evidence:

- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php`: 1 file / 1059 assertions / 0 failures
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files / 2803 assertions / 0 failures
- `php lanes/lightningcss/examples/wordpress-gradient-value-minifier.php`: exit 0
- `php -l lanes/lightningcss/src/CssMinifier.php`: no syntax errors
- `php -l lanes/lightningcss/tests/CssMinifierTest.php`: no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-gradient-value-minifier.php`: no syntax errors
- `git diff --check -- lanes/lightningcss`: clean

Root harness: not run - isolated micro-slice.

Dependency closure: no new support component is needed; reused bounded `CssMinifier` scanner, math/color normalization, and native declaration-value parsing. No Rust/Node/WASM runner executed.

Non-overlap: avoids accepted HSL/LCH/OKLCH color-mix, font face/palette/feature, grid-template/grid-auto-flow, target-prefix, CSSOM, custom-at-rule, media-query, source-map, bundler, and CSS Modules clusters. It only extends `src/lib.rs::test_gradients` linear-gradient minifier parity.

Follow-up: radial/conic gradient minification and legacy prefixed gradient prefixer parity remain separate upstream clusters.
