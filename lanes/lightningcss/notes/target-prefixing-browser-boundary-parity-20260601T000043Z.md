# Target Prefixing Browser Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T000043Z`
Base accepted HEAD: `0e78c232d5f671d5140ddac2287b4ff3c85d5779`

## Source Truth

Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Targeted source reads:

```bash
git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '12584,12634p'
git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/compat.rs | nl -ba | sed -n '3513,3545p'
```

The mapped upstream cluster is `src/lib.rs::test_animation`: Safari 16 splits `animation: .2s ease-in-out bar scroll()` into `animation` plus `animation-timeline`, Chrome 120 preserves the shorthand, and Safari 6 splits the timeline before emitting `-webkit-animation`. `src/compat.rs` marks `Feature::AnimationTimelineShorthand` as supported only for Chrome/Edge/Android 115+, Opera 77+, and Samsung 23+ among the represented browser families.

## Implementation

`TransitionPrefixer` now computes `animationTimelineShorthandNeedsFallback` from the upstream browser thresholds. For unsupported targets it removes top-level `scroll(...)` and `view(...)` timeline-function tokens from `animation` shorthand layers and appends `animation-timeline`, using `auto` for parallel layers without an explicit timeline. This runs before legacy animation prefixing, so old WebKit targets receive prefixed animation declarations without unsupported timeline tokens.

Red-first probe before implementation showed Safari 16 and Safari 6 preserving `scroll()` inside `animation`; Safari 6 also emitted `-webkit-animation` with the unsupported token. The same probe after implementation produced the upstream-shaped split output, while Chrome 120 preserved the shorthand.

## Evidence

Focused verification:

```bash
php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
# 1 test files, 794 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-animation-prefixer.php --self-test
# OK

php -l lanes/lightningcss/src/TransitionPrefixer.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-animation-prefixer.php
# No syntax errors detected in all changed PHP files

php tools/run-tests.php lanes/lightningcss/tests
# 13 test files, 4993 assertions, 0 failures
```

`git diff --check -- lanes/lightningcss` passes for this handoff.

Focused assertion growth: `TransitionPrefixerTest.php` adds 10 assertions, moving the full LightningCSS PHP lane from 4983 to 4993 assertions. Conservative mapped coverage moves from `2216 / 3532` to `2219 / 3532` for the three upstream `test_animation` prefix-test browser-boundary cases.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This slice only covers animation timeline shorthand target fallback. It avoids the already accepted animation declaration prefixing, keyframes prefixing/minification, text-decoration target boundary, object-fit target boundary, filter/backdrop-filter target boundary, and property-value minification clusters.

## Dependency Closure

No new support component is needed. The implementation reuses `TransitionPrefixer` target-version routing, declaration parsing, top-level list splitting, whitespace token splitting, minifier output, and existing animation prefix emission.
