# LightningCSS HWB Advanced-Origin Color-Mix Parity

- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T010037Z`
- Source truth: upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_color_mix`.
- Focused upstream cluster: 9 `minify_test` helpers for `color-mix(in hwb, <advanced-origin> 100%, rgb(0, 0, 0) 0%)` with `color(display-p3 ...)`, `lab()`, `lch()`, `oklab()`, and `oklch()` origins.

## Behavior

Before this slice, the PHP minifier preserved these HWB `color-mix()` functions unresolved when the color stop was an advanced color function, for example:

```css
.foo{color:color-mix(in hwb,color(display-p3 0 1 0) 100%,rgb(0,0,0) 0%)}
```

Upstream canonicalizes those origins through sRGB channels before computing HWB components. The PHP port now reuses the existing relative sRGB origin conversion for HWB color-mix stops, matching the upstream results such as `#00f942`, `#2a0022`, `#fff`, and `#000`.

## Evidence

- Red-first spot check before implementation: the five sampled advanced-origin HWB color-mix cases failed and remained unresolved.
- Focused PHP test after implementation: `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` passed `1 test files, 1642 assertions, 0 failures`.
- WordPress smoke added: `examples/wordpress-hwb-color-mix-advanced-origin.php` covers block-theme duotone/accent declarations using advanced color origins without Node/WASM.
- Conservative mapped coverage: `2241 / 3532 -> 2250 / 3532`.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local native CSS color parsing/minification and relative color conversion helpers already present in `CssMinifier`.

## Non-Overlap

This does not repeat the accepted display-p3/LCH color-mix slice, named non-SRGB color-mix slice, HSL/HWB alpha-weighted slice, or relative-color origin batches. It specifically closes the remaining upstream HWB color-mix advanced-origin conversion cases from `src/lib.rs::test_color_mix`.
