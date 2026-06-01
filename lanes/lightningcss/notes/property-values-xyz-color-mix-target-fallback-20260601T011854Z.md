# XYZ color-mix target fallback parity

Slice: `lightningcss-property-values-color-font-grid-parity-20260601T011854Z`

Source truth:
- Upstream pinned commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream file: `src/lib.rs::test_color_mix`, lines 21147-21159.
- Behavior: `prefix_test` for `.foo { color: color-mix(in xyz, transparent, green 65%); }` on Chrome 95 emits an sRGB alpha fallback before the normalized `color(xyz ... / .65)` declaration.

Implementation:
- `TransitionPrefixer` now includes the generated normalized XYZ color value in the advanced-color fallback map.
- Generic advanced-color fallback eligibility now includes plain `color` declarations, but excludes `light-dark(...)` values so the existing dedicated light-dark fallback path remains unchanged.
- Added a WordPress block-cover smoke for the Chrome 95 XYZ color-mix fallback path.

Verification:
- `php -l lanes/lightningcss/src/TransitionPrefixer.php`: pass.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`: pass.
- `php -l lanes/lightningcss/examples/wordpress-xyz-color-mix-fallback.php`: pass.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`: 1 file / 833 assertions / 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files / 5270 assertions / 0 failures.
- `php lanes/lightningcss/examples/wordpress-xyz-color-mix-fallback.php --self-test`: pass.

Manifest/status delta:
- `lane-status.json` `phpPass`: 5268 -> 5270.
- `UPSTREAM_TEST_MANIFEST.json` mapped coverage: 2250 / 3532 -> 2251 / 3532.

Dependency closure:
- No new support component is needed. The slice reuses the existing native PHP `CssMinifier` color-mix normalization and `TransitionPrefixer` advanced-color fallback path.

Non-overlap:
- This does not revisit accepted display-p3/lch color-mix minification, non-sRGB named color-mix normalization, light-dark color-scheme fallback, or wide-gamut background/outline fallback slices. It is limited to the unmapped `src/lib.rs::test_color_mix` Chrome 95 target fallback for `color-mix(in xyz, transparent, green 65%)`.
