# LightningCSS Relative HSL Channel Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T025107Z`

Accepted base: `846bc1f5f2d625b546a4c52f04a021ba713d41de`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '19233,21080p'`.
- Targeted implementation read: `src/values/color.rs` `RelativeComponentParser`, where HSL/HWB hue channel `h` is typed as `Angle` and is rejected by number/percentage component parsing.

Native PHP delta:

- `CssMinifier` now rejects HSL/HWB relative hue-channel references in number/percentage channels, including inside simple `calc()` expressions.
- This preserves upstream-invalid `hsl(from rebeccapurple s h l)` instead of incorrectly folding it to an sRGB color.
- Valid adjacent upstream cases such as `hsl(from rebeccapurple s s s / s)` and `hsl(from rebeccapurple calc(alpha * 100) calc(alpha * 100) calc(alpha * 100) / alpha)` still minify to concrete colors.
- Added a WordPress block-cover smoke for preserving an invalid theme relative-HSL token while still minifying valid relative-HSL tokens.

Evidence:

- `php -l lanes/lightningcss/src/CssMinifier.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-relative-hsl-channel-guard.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 1741 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 5663 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-relative-hsl-channel-guard.php --self-test` -> exits 0 and emits the expected minified block-cover CSS.
- `git diff --check -- lanes/lightningcss` -> no whitespace errors.

Non-overlap:

- Does not repeat accepted grid value minifier, font value/prefix fallback, color-mix, relative RGB/HSL/HWB valid sRGB-origin, relative non-SRGB origin, CSSOM, source-map, CSS Modules, or bundle/import graph clusters.
- A stale pre-current-base custom-media rework note exists in the main handoff directory, but it targets `CustomMediaTransformer.php` at an older base and is unrelated to this property-value channel typing slice.

Dependency closure:

- No new support component is needed. This reuses the native `CssMinifier` color parser, relative channel evaluator, and bounded calc evaluator.

Root harness status: not run - isolated micro-slice.
