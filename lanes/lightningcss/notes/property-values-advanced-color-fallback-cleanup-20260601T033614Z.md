# LightningCSS Property Values - Advanced Color Fallback Cleanup

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T032700Z`

Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_color` advanced color target fallback cases around lines 18982-19057.

## Behavior Ported

This slice maps the remaining upstream Safari target behavior for advanced color fallback declarations:

- Safari 14 preserves an authored fallback before direct `lab(...)` because Lab is still not natively supported.
- Safari 16 drops earlier same-property fallbacks before a supported `lab(...)` declaration.
- Safari 16 also drops a previous `var(...)` fallback before the supported `lab(...)` declaration.
- Safari 14 rewrites `var(--foo, lab(...))` to a `color(display-p3 ...)` fallback and emits the original Lab fallback inside `@supports (color: lab(0% 0 0))`.
- Existing advanced color declarations inside a Lab `@supports` guard remain unchanged.

Red-first probes before the implementation kept the Safari 16 fallback declarations and left the Safari 14 `var(..., lab(...))` value unresolved.

## Implementation

`TransitionPrefixer::rewriteAdvancedColorFallbackEntries()` now:

- tracks native advanced-color support separately from sRGB fallback needs;
- drops previous same-property fallbacks only when the target already supports the advanced color declaration;
- emits the Safari 10-14 P3 fallback plus Lab support rule only for custom-property references that contain advanced colors;
- leaves direct Safari 14 `lab(...)` declarations and their authored fallbacks unchanged, matching upstream.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php` - pass.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` - pass.
- `php -l lanes/lightningcss/examples/wordpress-advanced-color-var-fallback.php` - pass.
- `php lanes/lightningcss/examples/wordpress-advanced-color-var-fallback.php --self-test` - pass.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` - 1 file, 935 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests` - 13 files, 5761 assertions, 0 failures.

## Dependency Closure

No new support component is needed. This reuses the native `TransitionPrefixer` declaration parser, target option normalization, advanced-color fallback maps, and `@supports` rule emission.

## Non-Overlap

This does not repeat accepted alpha-hex fallbacks, custom-property RGB/HSL variable-alpha fallbacks, wide-gamut direct background-color fallbacks, XYZ color-mix target fallback, font target fallback, grid value minification, CSSOM, source-map, CSS Modules, bundle/import graph, media-query, or custom at-rule visitor slices. The stale May 2025 custom-media import-tail rework note is unrelated to this property-value target fallback behavior.
