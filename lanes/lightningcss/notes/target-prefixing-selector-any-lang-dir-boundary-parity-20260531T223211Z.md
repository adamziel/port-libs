# Target Prefixing Selector Any/Lang/Dir Boundary Parity

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T223211Z`

Base: `457d8df75c82fef3de304d8652d979a0fd3d1346`

## Source Truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Pinned commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Pristine reads used:
  - `src/lib.rs::test_selectors` selector `prefix_test` cluster for selector-list `:not()`, simple `:is()`, multi-argument `:lang()`, and `:dir()` browser-boundary output.
  - `src/prefixes.rs` `Feature::AnyPseudo` browser prefix ranges for `:-webkit-any` and `:-moz-any`.

## Behavior

`TransitionPrefixer` now emits bounded upstream-compatible selector fallback variants:

- Safari 8 selector-list `:not(.foo, .bar)` lowers to `:not(:-webkit-any(...))` and `:not(:is(...))`.
- Simple `:is(.foo, .bar)` lowers to `:-webkit-any`, `:-moz-any`, and native `:is` when targets require both prefixes, while complex `:is(.foo > .bar)` stays native.
- Multi-argument `:lang(en, fr)` falls back through prefixed any-pseudo forms or `:is(:lang(...), ...)` depending on target boundaries.
- `:dir(rtl/ltr)` falls back to the upstream RTL language set, including nested selector cases such as `:where(:dir(rtl))`, `:has(:dir(rtl))`, `:not(:dir(rtl))`, pseudo-elements, and descendant selectors.

The slice conservatively maps 19 upstream `src/lib.rs::test_selectors` `prefix_test` helper cases, moving mapped coverage from `2171 / 3532` to `2190 / 3532`.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-selector-target-prefixer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 714 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 4682 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-selector-target-prefixer.php --self-test`
  - `selector target prefixer example self-test passed`

## Dependency Closure

No new support component is needed. The implementation reuses the existing PHP minifier, target-range helpers, and selector-list splitting utilities inside `TransitionPrefixer`.

## Non-Overlap

This slice avoids the accepted transition/property target-prefix clusters, logical selector direction variants for inline transitions, media query fallback work, and the stale May 25 custom media import-tail rework note. It is limited to the selector target-prefix boundary cluster from upstream `test_selectors`.

## Next

Potential follow-up selector work should target non-overlapping parser recovery, selector specificity, or unsupported pseudo-class serialization cases; do not repeat the any/lang/dir target-boundary cluster.
