# LightningCSS Border-Image Target Prefix Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T181107Z`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted source:
  - `src/properties/mod.rs` declares `border-image` as a shorthand that accepts `VendorPrefix::WebKit`, `VendorPrefix::Moz`, and `VendorPrefix::O`.
  - `src/prefixes.rs`, `Feature::BorderImage`, defines browser ranges for those prefixes.
- Upstream boundary table maps:
  - WebKit prefix for Android 2.1 through 4.2, Chrome 4 through 14, iOS Safari 3.2 through 5, and Safari 3.1 through 5.1.
  - Mozilla prefix for Firefox 3.5 through 14.
  - Opera prefix for Opera 11 through 12.1.

## Implementation

- `TransitionPrefixer` now emits `-webkit-border-image`, `-moz-border-image`, and `-o-border-image` declarations only for upstream-required browser target ranges.
- Modern targets remove equivalent stale prefixed `border-image` declarations when an unprefixed declaration is present.
- Added `examples/wordpress-border-image-prefixer.php` to model a block theme cover-frame asset that needs old editor/WebKit/Mozilla/Opera output without Node/WASM.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - `No syntax errors detected in lanes/lightningcss/src/TransitionPrefixer.php`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-border-image-prefixer.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-border-image-prefixer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 451 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 2898 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-border-image-prefixer.php`
  - exit 0, emitted the expected legacy-prefixed and modern border-image outputs.
- `git diff --check -- lanes/lightningcss`
  - clean.

## Non-Overlap

- This does not touch the stale `CustomMediaTransformer` rework note, which is unrelated to this target-prefixing slice.
- This avoids accepted text/sticky, box-sizing, object-fit/object-position, transform, display/flex, background-clip, clip-path, mask/mask-border, filter/backdrop-filter, print-color-adjust, UI, text-decoration/emphasis, image-set, keyframes, and media-range target-prefix clusters.
- Existing border-image coverage was CSSOM read/write/remove behavior; this slice targets `TransitionPrefixer` browser-boundary prefixing.

## Dependency Closure

No new support component is needed. This reuses the lane-local native target-version encoder, declaration parser, minifier, and generic vendor-prefixed declaration group helper; no Node, Rust, browser service, external prefix database, or new shared dependency is introduced.
