# LightningCSS Box-Sizing Target Prefix Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T171148Z`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted source:
  - `src/prefixes.rs`, `Feature::BoxSizing` browser prefix table.
  - `src/properties/mod.rs`, `box-sizing` property allows `VendorPrefix::WebKit` and `VendorPrefix::Moz`.
- Upstream boundary table maps:
  - WebKit prefix for Android 2.1 through 3, Chrome 4 through 9, iOS Safari 3 through 4.2, and Safari 3.1 through 5.
  - Mozilla prefix for Firefox 2 through 28.

## Implementation

- `TransitionPrefixer` now adds `-webkit-box-sizing` and `-moz-box-sizing` declarations only for upstream-required target ranges.
- Modern targets remove stale WebKit/Mozilla `box-sizing` declarations when an equivalent unprefixed declaration is present.
- Added `wordpress-box-sizing-prefixer.php` to model block-theme reset and layout container `box-sizing` output without Node/WASM.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - `No syntax errors detected in lanes/lightningcss/src/TransitionPrefixer.php`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-box-sizing-prefixer.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-box-sizing-prefixer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 410 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-box-sizing-prefixer.php`
  - exit 0, emitted the expected Chrome 9 WebKit, Firefox 28 Mozilla, and modern stale-prefix-removal outputs.

## Non-Overlap

- This does not touch the stale `CustomMediaTransformer` rework note, which is unrelated to this target-prefixing slice.
- This avoids accepted transition, transform, print-color-adjust, UI user-select/appearance, legacy text, sticky, background-clip, clip-path, logical inset, display/flex, border-radius, mask, filter/backdrop-filter, box-shadow, text-decoration/emphasis, caret, list-style, image-set, keyframes, and media-range prefix clusters.

## Dependency Closure

No new support component is needed. This reuses the lane-local native target-version encoder, declaration parser, minifier, and generic vendor-prefixed declaration group helper; no Node, Rust, browser service, or external prefix database is introduced.
