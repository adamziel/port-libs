# LightningCSS Target Prefixing: Object Fit Opera Boundary

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream behavior: `src/prefixes.rs` maps `Feature::ObjectFit | Feature::ObjectPosition` to `VendorPrefix::O` only for Opera target versions `10.6` through `12.1` (`656896..=786688`).
- Red-first local probe before this patch showed Opera `10.6` and `12.1` still emitted only unprefixed `object-fit` and `object-position`.

## Implementation

- `TransitionPrefixer` now computes `objectFitNeedsO` from the same Opera `10.6` through `12.1` range.
- Style-rule rewriting now inserts `-o-object-fit` and `-o-object-position` before matching unprefixed declarations when that target range is active.
- Modern targets remove stale `-o-` declarations when the matching unprefixed declaration is present, preserving existing stale-prefix cleanup behavior.

## Focused Evidence

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - `No syntax errors detected in lanes/lightningcss/src/TransitionPrefixer.php`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-object-fit-prefixer.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-object-fit-prefixer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 434 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 2802 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-object-fit-prefixer.php`
  - passed; emits no `-o-` prefixes for Opera `10.5` and `13`, and emits `-o-object-fit` plus `-o-object-position` for Opera `10.6` and `12.1`.
- `git diff --check -- lanes/lightningcss`
  - passed

## Coverage Delta

- `TransitionPrefixerTest.php` increased from 426 to 434 assertions.
- Full LightningCSS lane evidence increased from 2794 to 2802 assertions.
- Conservative mapped coverage moves from `1601 / 3532` to `1605 / 3532` for four focused object-fit/object-position browser-boundary checks.

## Non-Overlap

This slice does not touch the accepted target-prefixing clusters for mask, filter/backdrop-filter, box-sizing, display/flex, transform, logical inset, background-clip, clip-path, legacy text/sticky, print-color-adjust, UI appearance/user-select, image-set, or keyframes. The stale custom-media rework note under the main handoff directory is unrelated to this target-prefixing surface and was not modified.

## Dependency Closure

No new support component is needed. This reuses the existing bounded target-version helpers, declaration scanner, minifier, and vendor-prefixed declaration rewrite group inside `TransitionPrefixer`.

Root harness status: not run - isolated micro-slice.
