# LightningCSS Property Values Color/Font/Grid Parity - 2026-06-01

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T174741Z`
- Upstream source truth: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Upstream rows: `src/lib.rs::test_color_mix`, rectangular Lab/Oklab `color-mix()` alpha-weight and missing-channel tail cases

This handoff makes a bounded property-value color cluster countable in PHP. It extends the accepted Lab/Oklab `color-mix()` minifier coverage with upstream rows for:

- leading percentage syntax before the first color;
- explicit `25% / 75%` and overweight `30% / 90%` alpha normalization;
- zero-weight first color with alpha preservation from the second color;
- missing rectangular channels inherited from the opposite color;
- missing alpha inherited through the mixed result.

The native PHP minifier already matched these upstream outputs on the current accepted base, so the patch adds focused regression coverage and extends the WordPress color-value smoke without changing production source.

## Evidence

- Before focused test: `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 2109 assertions, 0 failures`
- After focused test: `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 2123 assertions, 0 failures`
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8887 assertions, 0 failures`
- PHP lint: `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors
- PHP lint: `php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> no syntax errors
- Example smoke: `php lanes/lightningcss/examples/wordpress-color-value-minifier.php --self-test` -> exited `0` and emitted the expected minified CSS
- Diff hygiene: `git diff --check -- lanes/lightningcss` -> passed

Status delta: `phpPass` moves `8873 -> 8887` for `+14` focused assertions. Mapped upstream denominator remains `2399 / 3532`; these rows deepen an existing mapped property-value source file rather than adding a new manifest unit.

## Non-Overlap

This slice avoids accepted source-map, bundle/import graph, CSS Modules, CSSOM read/write, media query, custom at-rule visitor, selector/parser recovery, target-prefixing, font shorthand, grid formatter, relative color, HSL/HWB/LCH/OKLCH color-mix, and currentColor fallback clusters. It only touches the rectangular Lab/Oklab color-mix tail rows named above.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `CssMinifier` color parser/interpolator, the existing PHP TestRunner, and the existing WordPress color-value example. No Node, Rust, WASM, network, or external service dependency is introduced.

## Follow-Up

Continue property-value parity by auditing the remaining focused color/font/grid manifest rows against accepted PHP coverage. If a remaining row is already behaviorally green, make it countable with a focused assertion and a small WordPress-relevant smoke only when it exercises a user-visible declaration path.
