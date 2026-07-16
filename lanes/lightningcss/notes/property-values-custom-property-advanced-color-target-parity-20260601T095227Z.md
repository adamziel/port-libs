# LightningCSS Property Values: Custom Property Advanced Color Target Parity

Slice: `lightningcss-property-values-color-font-grid-parity-20260601T095227Z`

Source truth:
- Upstream `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted `src/lib.rs::test_custom_properties` custom-property advanced color fallback cases.

Behavior implemented:
- Direct custom properties containing `lab(...)` now receive Safari 10-14 `display-p3` base declarations plus guarded Lab overrides.
- Direct custom properties containing `oklab(...)` / `oklch(...)` now receive Safari 10-14 `display-p3` base declarations plus guarded Lab overrides.
- Safari 15 target fallback now downgrades unsupported OKLab/OKLCH custom-property values to Lab without converting already-supported Lab/LCH paint values.
- Mixed target sets with Safari 15 plus native advanced-color browsers keep the Safari Lab fallback instead of short-circuiting on native support.
- Important custom-property advanced color declarations now preserve `!important` through sRGB/Lab fallback emission.

Focused evidence:
- `php -l lanes/lightningcss/src/TransitionPrefixer.php` - pass
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` - pass
- `php -l lanes/lightningcss/examples/wordpress-advanced-color-var-fallback.php` - pass
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` - `1 test files, 1187 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-advanced-color-var-fallback.php --self-test` - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 7252 assertions, 0 failures`

Status delta:
- Adds 5 focused PHP assertions over the accepted lane baseline.
- Updates conservative mapped coverage from `2365 / 3532` to `2369 / 3532`.

Non-overlap:
- This is not the earlier SVG paint advanced-color fallback slice, alpha color serialization slice, custom-property color-token minifier slice, or direct property cleanup slice.
- The new behavior is limited to custom-property target fallback parity from `test_custom_properties`.

Dependency closure:
- No new support component is needed. The slice reuses the existing PHP transition prefixer, color fallback lookup table, and WordPress example smoke path.

Next:
- Continue with non-overlapping property-value parity in remaining font/grid/color gaps, especially cases that are still source-backed by upstream helper invocations and not only formatting/status movement.
