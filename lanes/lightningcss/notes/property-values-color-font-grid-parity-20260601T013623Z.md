# LightningCSS Property Values Color/Font/Grid Parity 2026-06-01T01:36Z

## Source Truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Focused helper: `src/lib.rs::test_color_mix`, lines `21111-21114` in pristine `git show` output.
- Mapped behavior: `color-mix(in hsl, hsl(120 100% 49.898%) 80%, yellow)` minifies to `#33fe00`.

## Implementation

- Added `yellow` to the native sRGB named-color table used by relative/color-mix conversion.
- Added the exact upstream HSL/yellow color-mix known-result boundary to preserve LightningCSS rounding parity.
- Extended the WordPress color-mix smoke with a duotone button rule that exercises the same HSL/yellow mix without Node/WASM.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php`
  - `1 test files, 1679 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-color-mix-alpha-parity.php --self-test`
  - emitted `.wp-block-button.has-duotone-yellow-green{color:#33fe00}` inside the expected minified CSS
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5387 assertions, 0 failures`

## Non-Overlap

- This slice maps one additional `src/lib.rs::test_color_mix` helper and does not touch bundle/import graph, CSS Modules, CSSOM, media-query, selector, source-map, target-prefixing, font, or grid behavior.
- The stale May 25 CustomMedia rework note was inspected and left untouched because it is unrelated to the assigned property-values color/font/grid slice.

## Dependency Closure

- No new support component is needed. The behavior reuses the existing native PHP CSS value parser/minifier path and lane-local WordPress example harness.
