# LightningCSS Property Values: Supports-guarded color fallback parity

## Source truth

- Upstream: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Covered cluster: `src/lib.rs::test_skip_generating_unnecessary_fallbacks`.
- Mapped behavior: positive `@supports` guards for Lab and display-p3 skip redundant legacy color fallbacks, while `or` and `not` guards remain uncertain and still receive sRGB fallback declarations. `@supports (color: light-dark(...))` preserves nested `light-dark()` values instead of lowering them to `--lightningcss-*` variables.

## Implementation

- `TransitionPrefixer` now tracks nested `@supports` context separately for advanced colors and `light-dark()`.
- Advanced-color fallback suppression now applies only for positive support conditions. Conditions containing `or` or `not` no longer suppress fallback generation.
- Added the pinned upstream display-p3 color fallback for `color(display-p3 .643308 .192455 .167712)` to match the Lab fallback color used by the upstream helper cases.

## Evidence

Red-first probes on the accepted base showed:

- `@supports (color: light-dark(#f00, #00f))` lowered the body value to `var(--lightningcss-light,...) var(--lightningcss-dark,...)`; upstream preserves `light-dark(...)` inside the guard.
- `@supports (color: lab(...)) and (not (color: color(display-p3 ...)))` and the matching `or` guard skipped needed sRGB fallbacks.
- The display-p3 body color did not have the upstream `#b32323` fallback mapping.

Focused verification after the patch:

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` => `1 test files, 1016 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 6243 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-supports-color-fallback-prefixer.php --self-test` => `OK`.

## Dependency Closure

No new support component is needed. This reuses the lane-local PHP supports-condition tokenizer, declaration rewriter, advanced-color fallback maps, and light-dark fallback path.

## Non-overlap

This does not repeat accepted background gradient fallback, custom-property advanced color support guards, wide-gamut direct fallback, color-mix fallback, font target fallback, grid minification, CSSOM, source-map, CSS Modules, bundle/import graph, media-query, or custom at-rule visitor slices. The stale May 2025 custom-media import-tail rework note was inspected and remains unrelated to this property-value target-fallback slice.
