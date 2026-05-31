# LightningCSS Advanced Color Function Minifier Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T131129Z`

Accepted base: `d5dbe191672720b0a5319ee4d2279a13620992e7`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '18313,18442p'`.
- Count evidence: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | sed -n '18313,18442p' | rg -c 'minify_test\('` returned `34`.
- Mapped behavior: advanced `src/lib.rs::test_color` minifier helpers for `lab()`, `lch()`, `oklab()`, `oklch()`, and `color()`.

Native PHP delta:

- `CssMinifier` now normalizes advanced Color 4 functions: Lab/LCH lightness percent insertion, Lab/LCH percentage axis conversion, OKLab/OKLCH unitless lightness to percent conversion, OKLab/OKLCH percentage axis conversion, hue unit folding, `color()` channel percentage folding, `xyz-d65` to `xyz`, and slash alpha elision/compaction.
- `TransitionPrefixer` fallback lookup now accepts the minified advanced-color spellings so existing target fallback parity remains intact after declaration-value minification.
- `wordpress-color-value-minifier.php` now covers a wide-gamut block overlay using `lch()`, `oklab()`, and `color(display-p3 ...)` without Node.

Evidence:

- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 772 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 192 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 1373 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> exits 0 and emits the expected minified block CSS.

Non-overlap:

- Does not repeat accepted Color 4 basic rgb/hsl/hwb/system-color/identical-light-dark value minification from `b7f6d299`.
- Does not repeat accepted grid track/area/placement value minification, accepted font-family/font-shorthand/font-face/font-palette/font-feature slices, accepted color-scheme/light-dark fallback-prefix slices, or accepted advanced-color fallback layer generation. This slice only adds direct minifier normalization for advanced color functions and preserves existing prefixer fallback behavior.

Dependency closure:

- No new support component is needed. This reuses the native `CssMinifier` declaration scanner, top-level token splitters, numeric serializer, and existing `TransitionPrefixer` precomputed advanced-color fallback tables.
