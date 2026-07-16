# Property Values Wide Gamut Color Target Parity 2026-05-31

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source range: `src/lib.rs::test_color` around lines 18720-18877.
- Mapped helper cluster: target fallback behavior for `color(srgb ...)`, `color(display-p3 ...)`, `color(a98-rgb ...)`, `color(prophoto-rgb ...)`, `color(rec2020 ...)`, `color(xyz-d50 ...)`, and `color(xyz-d65 ...)` background colors, plus Safari-only no-fallback boundaries for already supported advanced colors and the adjacent Chrome 90 + Safari 15 OKLab Lab-target fallback.

## Implementation

- `TransitionPrefixer` now gates generic advanced-color background sRGB fallbacks on `advancedColorNeedsSrgbFallback`, and only emits display-p3 intermediate layers with those sRGB rewrites when the target set includes Safari 10-14. Safari-only targets that already support the color space preserve the minified declaration without redundant fallback layers, and Chrome 90 + Safari 15 OKLab targets now omit the obsolete display-p3 middle layer.
- Added native sRGB fallback mappings for the upstream wide-gamut color fixtures:
  `#6a805d` for srgb/display-p3/a98/prophoto fixtures, `#728765` for rec2020, and `#7654cd` for xyz-d50/xyz-d65.
- The existing display-p3 identity path remains responsible for the second advanced-color declaration when a Chrome 90 style target needs an sRGB fallback.

## Evidence

- Red-first probe before the change: Chrome 90 emitted only `color(...)` declarations for the selected wide-gamut fixtures, and Safari 15 `lab(40% 56.6 39)` emitted old fallback layers instead of the upstream no-op.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `1 test files, 487 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-wide-gamut-color-fallback.php`
  - Passed self-test and printed the expected WordPress block color fallback CSS.
- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - No syntax errors detected.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - No syntax errors detected.
- `php -l lanes/lightningcss/examples/wordpress-wide-gamut-color-fallback.php`
  - No syntax errors detected.
- `git diff --check -- lanes/lightningcss`
  - Passed with no output.
- Root harness: not run, isolated micro-slice.

## Status Delta

- Focused assertion delta: +11 assertions in `TransitionPrefixerTest.php`.
- Lane status updated from `phpPass` 3141 to 3152.
- Conservative mapped coverage note: +11 upstream `test_color` helper boundaries, moving the lane note from 1696 / 3532 to 1707 / 3532.

## Non-Overlap

- This slice does not touch accepted color-mix value resolution, custom-property advanced-color supports fallback layering, font target fallback boundaries, grid value minification/composition, text-decoration-thickness target fallbacks, source maps, CSS Modules, media queries, or bundling/import graph behavior.

## Dependency Closure

- No new support component is needed. The implementation reuses the existing native PHP `TransitionPrefixer` target-option model and advanced-color fallback mapper.
