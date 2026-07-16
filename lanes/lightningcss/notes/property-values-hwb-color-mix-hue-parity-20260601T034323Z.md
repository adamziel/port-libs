# LightningCSS HWB Color-Mix Hue Parity

- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T034323Z`
- Source truth: upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_color_mix`.
- Focused upstream cluster: 16 active `minify_test` helpers for `color-mix(in hwb ...)` default, `shorter hue`, `longer hue`, `increasing hue`, and `decreasing hue` interpolation.

## Behavior

This slice adds parity coverage for HWB hue interpolation in `color-mix()`.
The upstream helpers canonicalize midpoint hues that cross the 0deg boundary
or force the long way around the color wheel:

- `hwb(50deg 30% 40%)` mixed with `hwb(330deg 30% 40%)` resolves through `hwb(10deg 30% 40%)` and minifies to `#99594d`.
- `hwb(20deg 30% 40%)` mixed with `hwb(320deg 30% 40%)` resolves through `hwb(350deg 30% 40%)` and minifies to `#994d59`.
- `hwb(40deg 30% 40%)` mixed with `hwb(60deg 30% 40%)` in longer/decreasing or reversed increasing modes resolves through `hwb(230deg 30% 40%)` and minifies to `#4d5999`.

Pre-edit probes showed the native PHP minifier already emitted these upstream
outputs, so no source implementation change was required. The gap was missing
focused assertions and a WordPress-facing smoke for the active upstream helper
cases.

## WordPress Path

Added `wordpress-hwb-color-mix-hue-parity.php`, which covers block-style cover
color, background, border, outline, and text-decoration declarations that use
HWB hue interpolation modes without Node/WASM.

## Evidence

- Upstream source read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs` around `test_color_mix`.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-hwb-color-mix-hue-parity.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> 1 test files, 1759 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-hwb-color-mix-hue-parity.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 5821 assertions, 0 failures.

## Coverage

- New focused PHP assertions: 16.
- `lane-status.json` `phpPass`: `5805 -> 5821`.
- Conservative mapped coverage: `2320 / 3532 -> 2336 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the existing native
`CssMinifier` color parsing, HWB color conversion, and `color-mix()` hue
interpolation support.

## Non-Overlap

This does not repeat accepted HWB value normalization, HWB advanced-origin,
HSL hue interpolation, LCH/OKLCH hue interpolation, relative-color, alpha
clamping, font, or grid property-value slices. It only covers the active HWB
hue interpolation helper cases from upstream `src/lib.rs::test_color_mix`.
