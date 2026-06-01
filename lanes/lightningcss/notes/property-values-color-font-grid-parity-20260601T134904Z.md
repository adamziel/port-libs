# Property Values Color Font Grid Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T134218Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Pristine upstream reads:
  - `src/properties/font.rs`: `FontStyle::to_css` omits an oblique angle only when the parsed `Angle` equals the default `Angle::Deg(14.0)`.
  - `src/rules/font_face.rs`: `@font-face` oblique ranges use `Size2D<Angle>`, which collapses only when both angle values preserve the same angle variant/value identity.
  - `src/values/angle.rs`: radian angles serialize through degree output, while `turn` and `grad` preserve their original units.

## Red-First Gap

Before this change, PHP compared oblique angles by computed degrees, so default-equivalent non-`deg` units were incorrectly treated as the upstream default angle:

```text
.foo { font-style: oblique 0.24434609527920614rad; } -> .foo{font-style:oblique}
@font-face { font-style: oblique 0deg -0rad; } -> @font-face{font-style:oblique 0deg}
@font-face { font-style: oblique 0.24434609527920614rad 0.24434609527920614rad; } -> @font-face{font-style:oblique}
```

Upstream preserves the parsed angle identity for default omission and range collapse, so these serialize as `oblique 14deg`, `oblique 0deg 0deg`, and `oblique 14deg`.

## Implementation

- Added `CssMinifier::fontStyleObliqueAngleIdentity()` to track parsed oblique angle unit/value identity.
- Changed default oblique-angle omission to require an actual `deg` default, not only a computed 14 degree value.
- Changed `@font-face` oblique range collapse to require matching angle unit/value identity, while preserving existing radian-to-degree serialization.
- Extended the WordPress font-face range smoke with a radian default-axis font-face that now emits `font-style:oblique 14deg`.

## Verification

```text
php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php
1 test files, 2020 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 8075 assertions, 0 failures

php -l lanes/lightningcss/src/CssMinifier.php
No syntax errors detected in lanes/lightningcss/src/CssMinifier.php

php -l lanes/lightningcss/tests/CssMinifierTest.php
No syntax errors detected in lanes/lightningcss/tests/CssMinifierTest.php

php -l lanes/lightningcss/examples/wordpress-font-face-src-range-minifier.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-font-face-src-range-minifier.php

php lanes/lightningcss/examples/wordpress-font-face-src-range-minifier.php
printed the expected minified @font-face CSS including oblique 14deg from same-unit radian default-axis input

git diff --check -- lanes/lightningcss
passed with no output
```

## Status Delta

- `CssMinifierTest.php` gains 5 focused assertions for upstream font oblique angle identity.
- `lane-status.json` `phpPass` moves from `8070` to `8075` from the verified full LightningCSS lane run.
- Conservative mapped coverage remains `2393 / 3532`; this deepens the already represented upstream `src/lib.rs::test_font` / `@font-face` angle serialization cluster.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS minifier, existing font shorthand/descriptor parsing, and the existing WordPress font-face example harness.

## Non-Overlap

This does not repeat accepted font-stretch range minification, font target fallback boundaries, radian output conversion, grid property-value minification, color target fallbacks, CSSOM font reads/writes, media-query fallbacks, source-map work, bundle/import graph work, CSS Modules work, custom-at-rule visitor work, or target-prefixing slices. It is limited to upstream-compatible oblique angle identity for font property values.

## Next Task

Continue non-overlapping property-value parity for color/font/grid serialization gaps, or pivot to source maps, bundle/import graph, media query, CSSOM, CSS Modules, custom-at-rule, and target-prefix behavior if property values are covered by another current worker.
