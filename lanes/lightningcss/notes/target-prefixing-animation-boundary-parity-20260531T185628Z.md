# LightningCSS Target Prefixing Animation Boundary Parity 2026-05-31T18:56:28Z

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream behavior cluster:
  - `src/lib.rs::test_animation` prefix helpers for animation shorthand insertion, stale `-webkit-` / `-moz-` removal, and `var()` shorthand minification before prefixing.
  - `src/prefixes.rs` `Feature::Animation`, `AnimationName`, `AnimationDuration`, `AnimationDelay`, `AnimationDirection`, `AnimationFillMode`, `AnimationIterationCount`, `AnimationPlayState`, and `AnimationTimingFunction`.
- Upstream browser prefix ranges:
  - WebKit: Android 2.1-4.4.3, Chrome 4-42, iOS Safari 3.2-8.1, Opera 15-29, Safari 4-8.
  - Mozilla: Firefox 5-15.
  - Opera: Opera 12 only.

## Implementation Delta

- `TransitionPrefixer` now computes animation declaration target options from the upstream browser ranges.
- Animation shorthand and upstream-prefixed longhands now emit needed `-webkit-`, `-moz-`, and `-o-` declarations before the unprefixed declaration.
- Matching stale prefixed animation declarations are removed when the target no longer needs that prefix and an unprefixed declaration follows.
- Added `wordpress-animation-prefixer.php` to model block/theme animation prefix output and modern stale-prefix cleanup without Node/WASM.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/examples/wordpress-animation-prefixer.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 515 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 3222 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-animation-prefixer.php --self-test`
  - `OK`
- `git diff --check -- lanes/lightningcss`
  - passed with no output

## Coverage / Status

- Focused assertion growth: `TransitionPrefixerTest.php` moves from `499` to `515` assertions.
- Full LightningCSS PHP evidence moves from `3206` to `3222` assertions with `0` failures.
- Conservative mapped denominator moves from `1721` to `1737` / `3532` by counting 3 explicit upstream animation `prefix_test` helpers plus 13 boundary/longhand checks from the upstream animation feature table.
- No full Rust/Node/WASM upstream runner was executed for this isolated PHP slice.

## Non-Overlap / Rework Notes

- Avoided accepted target-prefix surfaces for keyframes, intrinsic sizing, mask, legacy text/sticky, display/flex, flex longhands, logical inset, object-fit, background-clip, clip-path, filter, box-sizing, border-image, transform, text-decoration, and text-emphasis.
- The main handoff directory contains a stale 2026-05-25 custom-media rework note; it targets import media-tail parsing and is unrelated to this micro-slice, so this patch does not touch custom-media code.
- Upstream `animation-timeline` lowering is intentionally excluded and remains a separate property/value follow-up.

## Dependency Closure

- No new support component is needed. The slice reuses the existing PHP CSS minifier, declaration parser, target-version encoder, and vendor-prefixed declaration-group machinery.
