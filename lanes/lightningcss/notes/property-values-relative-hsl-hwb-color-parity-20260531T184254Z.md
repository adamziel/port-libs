# LightningCSS Relative HSL/HWB Color Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T184254Z`

Accepted base: `b770b54260891c34e04fa8a0ea0f7730a47953d7`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine reads:
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '19220,20080p'`
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '20080,21080p'`
- Mapped 73 focused upstream `src/lib.rs::test_relative_color` checks covering bounded `hsl(from ...)` and `hwb(from ...)` relative colors from sRGB-compatible origins.

Native PHP delta:

- `CssMinifier` now resolves bounded relative `hsl(from ...)` and `hwb(from ...)` color functions when the origin can be parsed through the existing sRGB-compatible color path.
- The implementation supports named/rgb/hsl/hwb origins, nested relative HSL/HWB origins, channel reuse, `none` components, alpha preservation, direct hue/percentage replacements, and simple `calc()` arithmetic over `h`, `s`, `l`, `w`, `b`, and `alpha`.
- `wordpress-color-value-minifier.php` now includes a block overlay smoke that resolves relative HSL/HWB declarations without Node/WASM.

Red-first evidence:

- Before the implementation, a local probe left the focused functions unresolved:
  - `hsl(from rebeccapurple h s l) => .foo{color:hsl(from rebeccapurple h s l)}`
  - `hsl(from rebeccapurple h 20% l / alpha) => .foo{color:hsl(from rebeccapurple h 20% l/alpha)}`
  - `hwb(from rebeccapurple h w b) => .foo{color:hwb(from rebeccapurple h w b)}`
  - `hwb(from rebeccapurple h w 20% / alpha) => .foo{color:hwb(from rebeccapurple h w 20%/alpha)}`

Verification:

- `php -l lanes/lightningcss/src/CssMinifier.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 1203 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 3181 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> exits 0 and emits the expected relative HSL/HWB block overlay CSS.
- `git diff --check -- lanes/lightningcss` -> pass.

Non-overlap:

- Does not repeat accepted relative `rgb(from ...)`, color-mix normalization, gradient minification, font/grid value slices, target-prefix fallback clusters, CSSOM declaration-block work, media-query parsing, custom-media rewriting, CSS Modules, source-map, bundler import-graph, or custom at-rule visitor slices.
- This slice is only the bounded sRGB-origin relative HSL/HWB property-value cluster from `src/lib.rs::test_relative_color`.

Exclusions:

- Non-sRGB relative-color gamut conversion and exact half-channel tie rows remain future advanced property-value parity work.

Dependency closure:

- No new support component is needed. This reuses the native `CssMinifier` color parser, sRGB origin parsing, existing relative-color `calc()` evaluator, and shortest sRGB serializer.

Root harness status: not run - isolated micro-slice.
