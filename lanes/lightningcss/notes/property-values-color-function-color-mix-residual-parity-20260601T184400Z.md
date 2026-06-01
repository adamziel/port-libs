# LightningCSS property-values color-function color-mix residual parity

Session: `port-dev-lightningcss-property-values-20260601T184400Z`
Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T184400Z`
Accepted base: `251e6c15aa22f4f06aae4aa9f10b34fd233b85dd`
Upstream source truth: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_color_mix` lines 22323-22549.

## Behavior

Mapped 40 residual upstream `color()` interpolation-space `color-mix()` helper rows across `srgb-linear`, `xyz`, `xyz-d50`, and `xyz-d65`:

- explicit `25%` / `75%` stop weights and `30%` / `90%` scale-down normalization;
- zero-weight left stops for opaque and alpha-weighted mixes;
- trailing right-side alpha stop weights matching the leading-weight form;
- all-`none`, right-side all-`none`, and left-alpha-`none` component propagation.

The current native PHP color-mix parser/mixer already passed the pre-edit probe for these rows; this handoff locks the behavior in the focused upstream parity test and the WordPress duotone example instead of changing runtime code unnecessarily.

WordPress relevance: `examples/wordpress-non-srgb-color-mix.php` now models block duotone custom-property tokens that use explicit stop weights, zero-weight alpha handling, and all-`none` color components without requiring Node/WASM.

## Non-overlap

This does not revisit accepted HSL/HWB/Lab/Oklab/LCH/Oklch color-mix slices, the accepted `xyz` transparent fallback slice, non-sRGB named color-mix normalization, relative-color origin slices, grid-template/font shorthand clusters, target-prefix color fallbacks, or source-map/bundle/CSS Modules/custom-at-rule work. It is limited to remaining `src/lib.rs::test_color_mix` `color(<space> ...)` rows in the `srgb-linear`/`xyz`/`xyz-d50`/`xyz-d65` loop.

## Verification

- `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-non-srgb-color-mix.php` -> no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> 1 file / 2163 assertions / 0 failures
- `php lanes/lightningcss/examples/wordpress-non-srgb-color-mix.php --self-test` -> passed and printed expected minified CSS
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 files / 8974 assertions / 0 failures
- `git diff --check -- lanes/lightningcss` -> passed

## Dependency closure

No new support component is needed. This reuses the existing `CssMinifier` color-function color-mix parser and serializer; no external runner, Rust, Node, WASM, or browser dependency is introduced.
