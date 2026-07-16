# Property Values Color Font Grid Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T130722Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Pristine upstream read: `src/properties/font.rs` serializes `FontStyle::Oblique(Angle)` through `Angle::to_css`.
- Pristine upstream read: `src/values/angle.rs` converts `rad` angles to degrees before emitting CSS and canonicalizes negative zero to `0deg`.

## Red-First Gap

Before this change, the PHP minifier kept radian units in oblique font values where upstream serializes degrees:

```text
.foo { font-style: oblique 3.141592653589793rad } -> .foo{font-style:oblique 3.141593rad}
.foo { font-style: oblique -0rad } -> .foo{font-style:oblique 0rad}
.foo { font: oblique 1.5707963267948966rad 22px Helvetica } -> .foo{font:oblique 1.570796rad 22px Helvetica}
```

The existing default-angle equivalence already collapsed `0.24434609527920614rad` to bare `oblique`; the missing parity was non-default angle serialization.

## Implementation

- Reused the existing `CssMinifier::fontStyleObliqueAngleDegrees()` conversion path from the oblique default-angle check.
- Updated `CssMinifier::minifyFontStyleObliqueAngleToken()` so `rad` font-style oblique angles serialize as minified degree values.
- Added focused assertions for negative zero radians, quarter-turn radians, half-turn radians, `@font-face` oblique ranges, and `font` shorthand values.
- Extended the WordPress font-face range example with a radian oblique axis so the local smoke covers user-visible `@font-face` output.

## Verification

```text
php -l lanes/lightningcss/src/CssMinifier.php
No syntax errors detected in lanes/lightningcss/src/CssMinifier.php

php -l lanes/lightningcss/tests/CssMinifierTest.php
No syntax errors detected in lanes/lightningcss/tests/CssMinifierTest.php

php -l lanes/lightningcss/examples/wordpress-font-face-src-range-minifier.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-font-face-src-range-minifier.php

php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php
1 test files, 2015 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 7968 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-font-face-src-range-minifier.php
printed the expected minified @font-face CSS including oblique 0deg 90deg from radian input
```

## Status Delta

- `CssMinifierTest.php` gains five focused assertions in the upstream oblique default-angle/font-value parity test.
- `lane-status.json` `phpPass` moves from `7963` to `7968` from the verified full-lane assertion delta.
- Conservative mapped coverage remains `2392 / 3532`; this deepens an existing property-value/font serialization surface rather than claiming a new upstream denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP CSS minifier, existing numeric minification, existing oblique-angle degree conversion helper, and the existing WordPress font-face example harness.

## Non-Overlap

This does not repeat accepted font-stretch range minification, grid property-value minification, color target fallbacks, CSSOM font shorthand reads/writes, media-query fallbacks, source-map work, bundle/import graph work, CSS Modules work, custom-at-rule visitor work, or target-prefixing slices. It is limited to upstream-compatible radian angle serialization for `font-style: oblique` property values.

## Next Task

Continue non-overlapping property-value parity for color/font/grid serialization gaps, or pivot to source maps, bundle/import graph, media query, CSSOM, CSS Modules, custom-at-rule, and target-prefix behavior if another worker covers property values.
