# LightningCSS Property Values Color Font Grid Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T124904Z`

Accepted base: `61cc313d453fe87e1a4493db81110abc78909b94`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '18292,18440p'`.
- Mapped 23 upstream `src/lib.rs::test_color` minifier helpers: rgb/hsl/hsla alpha hex serialization, hwb pure-hue/white/black/gray conversion, transparent/currentColor/system-color keyword boundaries, identical `light-dark(#FFF,#FFF)` collapse, and none-component defaults for hsl/hwb/rgb.

Native PHP delta:

- `CssMinifier` now recognizes `hwb()` alongside rgb/hsl functions, parses bare percentage components and `none` color components, serializes HWB values through upstream shortest-color boundaries, compacts hex tokens in declaration values, lowercases known CSS system color keywords, and keeps arbitrary identifiers unchanged.
- Added `wordpress-color-value-minifier.php` to model block cover/button colors using HWB overlays, `ButtonBorder`, `light-dark()` identical arms, and `hsl(none none none)` without Node.

Evidence:

- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 736 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 1280 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> exits 0 and emits the expected minified block CSS.

Non-overlap:

- Does not repeat accepted `src/lib.rs::test_grid` track/area/placement minifier cases, accepted font-family/font-shorthand/font-face/font-palette/font-feature slices, accepted color-scheme/light-dark fallback-prefix slices, or accepted advanced-color fallback layers.

Dependency closure:

- No new support component is needed. This reuses the bounded native `CssMinifier` declaration scanner, top-level splitter, color serializer, and existing `light-dark()` minifier path.
