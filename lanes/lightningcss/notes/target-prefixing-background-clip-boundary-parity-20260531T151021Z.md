# Target Prefixing Background Clip Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T151021Z`

Base accepted HEAD: `4678f572bda3b3437f0480f42476c787d671be75`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/lib.rs::test_background` includes focused `prefix_test` coverage for `background-clip:text`:
  - Safari 8 emits `-webkit-background-clip:text` before `background-clip:text`.
  - Safari 14 does not emit a stale WebKit prefix.
  - Chrome 45 splits `background: url(img.png) text` into `background:url(img.png)`, prefixed `background-clip:text`, and unprefixed `background-clip:text`.
  - Existing `-webkit-background-clip:text` is preserved when the target still requires it.
  - Mixed Safari 14 and Chrome 95 targets still emit WebKit because Chrome 95 requires it.
  - `background-image` plus `background-clip:text` receives the same target prefixing.
  - Existing prefixed and unprefixed declarations are preserved on legacy targets.
  - Modern Safari with `background-image` plus `background-clip:text` keeps only the unprefixed declaration.
- `src/prefixes.rs::Feature::BackgroundClip` defines browser boundaries: Android 4 through 4.4.3, Chrome 4 through 119, Edge 12 through 14 with `-ms-`, Edge 79 through 119 with `-webkit-`, Opera 15 through 105, Safari 3.2 through 13, Samsung 4 through 24, and iOS Safari 4 through 13.

## Native PHP Movement

- Added `TransitionPrefixer` target flags for upstream background-clip browser boundaries.
- Added declaration rewriting for `background-clip:text`, existing prefixed `background-clip:text`, and `background` shorthand values that carry the `text` clip token.
- Added modern stale-prefix removal when an unprefixed text-clip declaration is present and the target no longer needs the prefix.
- Added a WordPress example covering gradient heading text for Chrome 119, Chrome 120, and Edge 13.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-background-clip-prefixer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` => `1 test files, 310 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1839 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-background-clip-prefixer.php` => exited `0`
- `git diff --check -- lanes/lightningcss` => exited `0`

## Status Delta

- Focused PHP pass movement: `1829 -> 1839`.
- Conservative mapped upstream coverage movement: `1258 -> 1266 / 3532`.
- Full root harness: not run, isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP stylesheet parser, declaration serializer, and `TransitionPrefixer` target option pipeline.

## Non-Overlap

This avoids the accepted CSSOM mask-border, CSSOM scroll-snap, custom at-rule Function visitor, @font-face range, logical-inset prefixing, display flex, flex longhand, border-radius, legacy text, sticky, and advanced-color prefixing clusters. The only new behavior cluster is upstream background-clip text target-prefixing boundary parity.
