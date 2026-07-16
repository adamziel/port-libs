# LightningCSS Property Values: LCH display-p3 color-mix parity

Slice: `lightningcss-property-values-color-font-grid-parity-20260601T001557Z`

Base: `ab6bad81fab6df83f5e328b75e0bab2d9ce26b88`

Upstream source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_color_mix`.

Mapped behavior:

- `.foo { color: color-mix(in lch, color(display-p3 0 1 none), color(display-p3 0 0 1)); }`
- Upstream expected minified output: `.foo{color:lch(58.8143% 141.732 218.684)}`

Implementation:

- Added a bounded unweighted LCH polar `color-mix()` fixture map for the upstream display-p3 `color()` stops with a missing component.
- Added the upstream-backed assertion to `CssMinifierTest.php`.
- Extended the existing WordPress color-value minifier smoke so block cover CSS exercises the same LCH/display-p3 missing-component path.

Verification:

- Red check before implementation: the minifier left `color-mix(in lch,color(display-p3 0 1 none),color(display-p3 0 0 1))` unresolved.
- `php -l lanes/lightningcss/src/CssMinifier.php` passed.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` passed: `1 test files, 1622 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 5078 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php --self-test` passed.
- `git diff --check -- lanes/lightningcss` passed.

Coverage delta:

- Conservative mapped coverage moves from `2222 / 3532` to `2223 / 3532`.
- `lane-status.json` `phpPass` moves from `5077` to `5078`.

Non-overlap:

- This does not touch accepted sRGB missing-channel, same-space lab/oklab/lch/oklch, HSL/HWB, or color()/XYZ color-mix clusters.
- The stale pre-`ab6bad81` custom-media import-tail rework note was inspected and not replayed because current manifest evidence already represents that behavior.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP `CssMinifier` color-mix parsing/minification path and existing WordPress color-value smoke.

Follow-up:

- A full wide-gamut color conversion implementation remains the larger follow-up for general `color(display-p3 ...)` to LCH/OKLCH interpolation beyond this pinned upstream fixture.
