# LightningCSS Color Mix Missing Component And Advanced Origin Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T231213Z`

Accepted base: `b77f76b33ac877becd8fb58514949f334f0fbc0d`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine reads:
  - `src/lib.rs::test_color_mix` lines 21127-21145 for sRGB missing-channel mixes and `color-mix(in xyz, transparent, green 65%)`.
  - `src/lib.rs::test_color_mix` lines 21425-21460 for HSL interpolation using display-p3, Lab/LCH, Oklab/Oklch origins.
- Mapped 13 focused upstream `minify_test` helpers in native PHP.

Native PHP delta:

- `CssMinifier` now resolves missing sRGB channels across both color stops before mixing, matching upstream `rgb(... none)` component carry-over behavior.
- HSL `color-mix()` can now convert already bounded advanced-color origins through the existing sRGB-origin mapping table before HSL interpolation.
- `color-mix(in xyz, transparent, green 65%)` now maps CSS keywords into XYZ components and preserves the upstream alpha-weighted result precision.
- `wordpress-color-value-minifier.php` includes a WordPress-style smoke for missing-channel sRGB, XYZ keyword alpha, and HSL display-p3 origin color mixes.

Evidence:

- `php -l lanes/lightningcss/src/CssMinifier.php && php -l lanes/lightningcss/tests/CssMinifierTest.php && php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 1611 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 4803 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> exits 0 and emits the expected minified WordPress color-value CSS.
- `jq empty lanes/lightningcss/lane-status.json && jq empty lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json` -> pass.
- `git diff --check -- lanes/lightningcss` -> pass.

Non-overlap:

- Does not repeat accepted basic sRGB color-mix normalization, Lab/Oklab/LCH/Oklch same-space color-mix helpers, HSL/HWB alpha-weighted interpolation, grid track-list value minification, font target fallbacks, media-query/selector/source-map/custom-at-rule/bundle import work, or the stale custom-media `@import` rework note.
- The remaining neighboring upstream case `color-mix(in lch, color(display-p3 0 1 none), color(display-p3 0 0 1))` is intentionally left for a separate LCH/display-p3 missing-component slice.

Dependency closure:

- No new support component is needed. This reuses `CssMinifier` color function parsing, existing advanced-origin sRGB mapping, HSL conversion, and color serialization helpers.

Root harness status: not run - isolated micro-slice.
