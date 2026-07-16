# LightningCSS Target Prefixing Clip-Path Boundary Parity

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T163828Z`

Accepted base: `6b3dbcd9ba83baf454581e5cfdd21849ee67aa00`

Source truth:

- Pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/lib.rs::test_mask` has four `clip-path` `prefix_test` helper cases for Chrome 30/80 and Safari 8/14.
- `src/prefixes.rs::Feature::ClipPath` defines WebKit prefix ranges covering Chrome 24-54 and Safari 7-9.

Implemented behavior:

- `TransitionPrefixer` now emits `-webkit-clip-path` before `clip-path` for WebKit-prefix target ranges.
- Modern targets keep only the unprefixed declaration.
- Focused tests also assert the exact upper cutoffs at Chrome 54/55 and Safari 9/10.
- `wordpress-target-prefix-boundaries.php` now includes a cropped cover block clip-path smoke alongside existing image-set/backdrop-filter/print/text/UI boundary checks.

Verification:

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` => `1 test files, 373 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2321 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-target-prefix-boundaries.php` => exit 0
- `php -l` and `git diff --check -- lanes/lightningcss` were run before handoff.

Dashboard expectation:

- PHP pass evidence moves from `2313` to `2321`.
- Conservative mapped coverage moves from `1446 / 3532` to `1450 / 3532`.

Dependency closure:

- No new support component is needed. This reuses `TransitionPrefixer` target-option routing, declaration scanning, and the existing prefixed declaration group helper.

Non-overlap:

- This slice does not repeat accepted mask/mask-border, background-clip, image-set, display/flex, logical inset, text-decoration, text-emphasis, print-color-adjust, or UI prefix boundary clusters.
