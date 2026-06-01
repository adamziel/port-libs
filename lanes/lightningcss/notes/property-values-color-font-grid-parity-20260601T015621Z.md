# LightningCSS Property Values Color/Font/Grid Parity 2026-06-01T01:56:21Z

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T015621Z`
- Upstream source truth: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Upstream function: `src/lib.rs::test_color_mix`

## Behavior

This slice ports the remaining upstream `color-mix()` behavior for `color()` interpolation spaces where alpha values outside the computed range still parse, clamp, and participate in component mixing:

- `srgb-linear`
- `xyz`
- `xyz-d50`
- `xyz-d65` serialized as `xyz`

Red-first local checks showed existing PHP left these cases unresolved:

- `color-mix(in xyz, color(xyz 2 3 4 / 5), color(xyz 4 6 8 / 10))`
- `color-mix(in xyz, color(xyz -2 -3 -4 / -5), color(xyz -4 -6 -8 / -10))`

The implementation now parses unbounded color-mix alpha components, clamps non-`none` alpha channels before premultiplied component mixing, and clamps result alpha before serialization. This matches upstream output such as `color(xyz 3 4.5 6)` and `color(xyz 0 0 0/0)`.

## WordPress Path

Updated `wordpress-non-srgb-color-mix.php` with duotone custom-property tokens that exercise high positive and negative `xyz` alpha mixes during block theme CSS minification.

## Evidence

- `php -l lanes/lightningcss/src/CssMinifier.php` -> no syntax errors
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-non-srgb-color-mix.php` -> no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> 1 test files, 1734 assertions, 0 failures
- `php lanes/lightningcss/examples/wordpress-non-srgb-color-mix.php --self-test` -> passed and printed expected CSS
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 5458 assertions, 0 failures

## Non-Overlap

This does not repeat the accepted XYZ fallback, HWB advanced-origin, HSL/HWB alpha-weighted, non-SRGB named, relative color, font, or grid property-value slices. It targets only `src/lib.rs::test_color_mix` `color()` interpolation-space alpha clamping cases.

## Dependency Closure

No new support component is needed. The existing native PHP `CssMinifier` color-mix parser and serializer were reused.
