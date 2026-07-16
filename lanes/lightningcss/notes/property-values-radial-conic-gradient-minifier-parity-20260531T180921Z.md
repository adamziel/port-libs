# Property Values Radial and Conic Gradient Minifier Parity - 2026-05-31T18:09:21Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_gradients` lines 13518-13652.

Mapped behavior: 33 focused upstream gradient minifier helper cases:

- radial-gradient color shortening and default prelude omission
- radial `at top left` and default center/50% position canonicalization
- radial `circle` / `ellipse` shape omission when implied by sizes
- default `farthest-corner` extent omission and `farthest-side` order normalization
- repeated/prefixed radial gradients, including `-webkit-`, `-moz-`, and `-o-` forms
- legacy `-webkit-gradient(radial, ...)` center point and from/to color normalization
- conic-gradient default `from 0deg` and `at 50% 50%` prelude elision
- conic and repeating-conic color-stop color/position compaction

Implementation: `CssMinifier::minifyGradientFunctions()` now recognizes radial, repeating-radial, conic, repeating-conic, prefixed radial, and legacy radial `-webkit-gradient()` values in addition to the accepted linear-gradient cluster. New bounded helpers normalize radial preludes/positions and conic preludes while reusing the existing color, math, and stop serialization helpers.

Evidence:

- `php -l lanes/lightningcss/src/CssMinifier.php`: no syntax errors
- `php -l lanes/lightningcss/tests/CssMinifierTest.php`: no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-gradient-value-minifier.php`: no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php`: 1 test file / 1106 assertions / 0 failures
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 test files / 2915 assertions / 0 failures
- `php lanes/lightningcss/examples/wordpress-gradient-value-minifier.php`: exit 0
- `git diff --check -- lanes/lightningcss`: clean

Root harness: not run - isolated micro-slice.

Dependency closure: no new support component is needed; this reuses the native `CssMinifier` declaration-value scanner, math folding, color serializers, and top-level function/list parsing helpers. No Rust/Node/WASM runner executed.

Non-overlap: avoids the stale custom-media rework note and does not repeat accepted linear-gradient, HSL/HWB/LCH/OKLCH color-mix, font face/palette/feature, grid-template/grid-auto-flow, target-prefix, CSSOM, custom-at-rule, media-query, source-map, bundler, and CSS Modules clusters. This slice only extends `src/lib.rs::test_gradients` radial/conic minifier parity.

Follow-up: legacy gradient prefixer generation/removal and broader gradient transform/prefix cases remain separate upstream clusters.
