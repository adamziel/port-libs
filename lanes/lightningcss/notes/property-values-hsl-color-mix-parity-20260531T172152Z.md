# LightningCSS HSL Color-Mix Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T172152Z`

Accepted base: `629821655cf6e1a021b6ef13725146c72cabed56`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '21181,21424p'`.
- Mapped 21 focused upstream `src/lib.rs::test_color_mix` HSL helpers: 14 value/weight/alpha `color-mix(in hsl, ...)` cases plus 7 shorter/longer/increasing/decreasing/specified hue-interpolation cases.

Native PHP delta:

- `CssMinifier` now accepts `hsl` as a `color-mix()` interpolation space.
- Direct `hsl()`/`hsla()` stops support leading and trailing percentages, omitted weights, explicit alpha, and `none` component parsing.
- HSL saturation and lightness use the existing alpha-premultiplied component mixer; hue uses the existing polar interpolation modes.
- Resolved HSL mixes serialize to LightningCSS-style compact sRGB hex values.
- `wordpress-color-value-minifier.php` now models an HSL mixed overlay and legacy hue fallback for build-free block CSS.

Evidence:

- Red-first current-base probe before implementation returned unresolved `color-mix(in hsl,...)` for the upstream `hsl(120deg 10% 20%)` plus `hsl(30deg 30% 40%)` case.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 1048 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 2680 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> exits 0 and emits expected minified block CSS.
- `git diff --check -- lanes/lightningcss` -> pass.

Non-overlap:

- Does not repeat accepted sRGB color-mix, Lab/Oklab rectangular color-mix, LCH/Oklch polar color-mix, relative `rgb(from ...)`, custom-property calc color functions, color fallback/prefix slices, font slices, grid shorthand/auto-flow/area slices, or CSSOM declaration-block work.
- This slice is only direct HSL `color-mix()` interpolation from upstream `test_color_mix`. HWB, XYZ, and cross-space color-mix conversion remain follow-up work.

Dependency closure:

- No new support component is needed. This reuses the bounded native `CssMinifier` color function parser, color-mix stop/weight normalization, alpha-premultiplied component mixer, hue interpolation helper, and compact RGB serializer.

Root harness status: not run - isolated micro-slice.
