# LightningCSS Target Prefixing Browser Boundary Parity 2026-05-31T18:27:30Z

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream behavior cluster: `src/lib.rs::test_size` prefix-test loop for intrinsic size values on `width`, `height`, logical size aliases, and min/max size aliases:
  - `stretch`
  - authored `-webkit-fill-available`
  - fallback plus `-webkit-fill-available`
  - `fit-content`
  - `fit-content(50%)`
  - `min-content`
  - `max-content`
  - static fallback plus `max-content`
  - `var()` fallback plus `max-content`
- Upstream browser ranges: `src/prefixes.rs` `Feature::MinContent`, `Feature::MaxContent`, `Feature::FitContent`, and `Feature::Stretch`.

## Implementation Delta

- `TransitionPrefixer` now emits value prefixes for intrinsic sizing keywords:
  - `min-content` / `max-content`: `-webkit-*` for Android 4.4.x, Chrome 22-45, iOS Safari 7-13.4, Opera 15-32, Safari 6-10.1, Samsung <= 4; `-moz-*` for Firefox 3-65.
  - `fit-content`: same WebKit window and `-moz-fit-content` for Firefox 3-93.
  - `stretch`: `-webkit-fill-available` for the upstream WebKit/Blink stretch window and `-moz-available` for Firefox >= 3.
- Legacy logical size declarations map to physical properties for this cluster when logical property fallback is required (`inline-size` -> `width`, `block-size` -> `height`, etc.).
- Existing fallback declarations before a sizing keyword suppress additional generated prefixes, matching upstream fallback preservation.
- Stale `-webkit-` / `-moz-` prefixed min/max/fit values are removed when the target no longer needs that prefix and an unprefixed keyword follows.
- Added `wordpress-intrinsic-size-prefixer.php` to model block/media intrinsic sizing fallbacks without Node/WASM.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/examples/wordpress-intrinsic-size-prefixer.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 479 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 3083 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-intrinsic-size-prefixer.php --self-test`
  - `OK`
- `git diff --check -- lanes/lightningcss`
  - passed with no output

## Coverage / Status

- Full LightningCSS PHP evidence moves from `3060` to `3083` pass / `0` fail.
- Conservative mapped denominator moves from `1684` to `1693` / `3532` by counting the nine upstream `src/lib.rs::test_size` intrinsic sizing `prefix_test` helper loops.
- No full Rust/Node/WASM upstream runner was executed for this isolated PHP slice.

## Non-Overlap / Rework Notes

- Avoided accepted target-prefix surfaces for transform/perspective/backface, mask, legacy text/sticky, display/flex, logical inset, object-fit, background-clip, clip-path, filter, box-sizing, and border-image.
- The main handoff directory contains a stale 2026-05-25 custom-media rework note; it targets import media-tail parsing and is unrelated to this micro-slice, so this patch does not touch custom-media code.

## Dependency Closure

- No new support component is needed. The slice reuses the existing PHP CSS minifier/declaration parser and `TransitionPrefixer` target-option machinery.
