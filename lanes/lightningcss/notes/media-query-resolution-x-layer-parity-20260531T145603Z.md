# Media Query Resolution X Unit Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T145603Z`

Base: `a187757827b58c999a1fc6cda2f4be5e163b73e9`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream cases:
  - `src/lib.rs::test_resolution` has two `prefix_test` helpers for `@media (resolution: 1dppx)`: Chrome 50 preserves `dppx`, while Chrome 95 serializes the compatible `x` resolution unit.
  - `src/values/resolution.rs::Resolution::to_css` emits `x` for DPPX values when `Feature::XResolutionUnit` is compatible with the active browser targets.
  - `src/compat.rs::Feature::XResolutionUnit` gates compatibility by browser target versions.

## Native Behavior

- `TransitionPrefixer` now tracks whether the requested browser targets all support the `x` resolution unit.
- Media preludes continue to run through the existing range fallback and resolution-prefix paths, then rewrite only resolution DPPX tokens to `x` for compatible targets.
- The rewrite applies inside nested `@layer` blocks, so layered block-theme CSS matches upstream target serialization without changing old Safari/Firefox prefixed fallbacks.
- `wordpress-media-range-layer-prefixer.php` now covers Chrome 95 layered responsive CSS output in addition to Safari/Firefox range and resolution fallbacks.

## Red-First Evidence

- After adding the focused assertions and before implementation:
  - `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `1 test files, 285 assertions, 1 failures`
  - Failing expectation: Chrome 95 should output `@media (resolution:1x)` instead of `@media (resolution:1dppx)`.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `1 test files, 287 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `2 test files, 363 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 1763 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Result: exited 0 and emitted Safari/Firefox/Chrome media range and resolution target output.

## Coverage Delta

- PHP assertion delta: `+4` (`1759 -> 1763`).
- Conservative mapped coverage delta: `+2` (`1232 / 3532 -> 1234 / 3532`) for the two upstream `src/lib.rs::test_resolution` helper cases.

## Non-overlap

This avoids repeating accepted media range target fallbacks, media range include/exclude feature flags, invalid media range validation, resolution vendor-prefix emission for old Safari/Firefox, cascade-layer merge/minifier behavior, flex longhand prefixing, grid value composition, custom-media scanner/import-tail behavior, source-map, bundler, CSS Modules, and CSSOM shorthand slices.

## Dependency Closure

No new support component is needed. The slice reuses the native `TransitionPrefixer` target-version encoder, existing `MediaQueryParser` media prelude normalization/lowering, and lane-local CSS scanner/minifier paths. No upstream binary, browser service, parser generator, or external CSS engine is required.
