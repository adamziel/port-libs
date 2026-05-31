# LightningCSS Color Function Color-Mix Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T180013Z`

Accepted base: `e83ba68ab62e3e93ee2dcf9fc87ea144ffeb366d`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | sed -n '21940,22480p'`.
- Mapped six static `src/lib.rs::test_color_mix` helper invocations from the `color()` interpolation-space loop: same-space srgb-linear/xyz/xyz-d50/xyz-d65 color stops, leading/trailing stop weights, premultiplied alpha component interpolation, sub-100% weight alpha scaling, xyz-d65 output canonicalization to xyz, and none-component carry-forward.

Native PHP delta:

- `CssMinifier` now accepts `srgb-linear`, `xyz`, `xyz-d50`, and `xyz-d65` as non-hue `color-mix()` interpolation spaces.
- Added native parsing for same-space `color(...)` color-mix stops, shared stop weight handling, alpha resolution, component interpolation, and color() result serialization.
- `wordpress-color-value-minifier.php` now includes a wide-gamut block overlay smoke for xyz/xyz-d65/srgb-linear color mixes.

Red-first evidence:

- Before the implementation, the new focused cases serialized unresolved functions such as `.foo{color:color-mix(in xyz,color(xyz .1 .2 .3),color(xyz .5 .6 .7))}` instead of upstream's `.foo{color:color(xyz .3 .4 .5)}`.

Verification:

- `php -l lanes/lightningcss/src/CssMinifier.php && php -l lanes/lightningcss/tests/CssMinifierTest.php && php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 1087 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 2849 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> exits 0 and emits expected xyz/srgb-linear mixed overlay CSS.
- `git diff --check -- lanes/lightningcss` -> pass.

Non-overlap:

- Does not repeat accepted sRGB/HSL/HWB/Lab/OKLab/LCH/OKLCH `color-mix()` normalization, relative `rgb(from ...)`, custom-property color calc minification, aspect-ratio minification, accepted font/font-face/font-palette/font-feature clusters, or accepted grid shorthand/auto-flow/placement clusters.
- This slice is only the bounded `color()` interpolation-space branch from `src/lib.rs::test_color_mix`.

Dependency closure:

- No new support component is needed. This reuses the bounded native `CssMinifier` color parser, top-level splitter, color-mix weight handling, alpha resolver, and color-number serializer.

Root harness status: not run - isolated micro-slice.
