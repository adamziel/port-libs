# LightningCSS Target Prefixing Browser Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T174552Z`

## Source Truth

- Upstream pinned cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream `src/lib.rs` scroll navigation tests around `test_pseudo_classes` cover `:target-current`, `:target-before`, and `:target-after`, including the browser target boundary where Chrome 130 lowers `a:target-before, a:target-after` to `:is(a:target-before,a:target-after)` while Chrome 150 preserves the list.
- Upstream `src/compat.rs` defines `Feature::TargetCurrent` as supported by Chrome/Edge/Android from 135, and `Feature::TargetBeforeAfter` as supported by Chrome/Edge/Android from 142. Firefox, IE, iOS Safari, Opera, Safari, and Samsung are not marked supported for these features.

## Patch

- Added `TransitionPrefixer` target option flags for upstream scroll navigation pseudo-class compatibility:
  - `targetCurrentNeedsSelectorListFallback`
  - `targetBeforeAfterNeedsSelectorListFallback`
- Extended unsupported selector-list isolation so mixed selector lists containing unsupported `:target-current`, `:target-before`, or `:target-after` use upstream-style `:is(...)` wrapping when the target supports `:is`, or split into independent rules for older targets.
- Added focused PHP assertions for Chrome/Edge 141/142, Chrome 134/135, Chrome 80 split fallback, and Safari unsupported fallback.
- Extended the WordPress selector target-prefixer example with navigation scroll target pseudo boundaries for Chrome 141 and Chrome 142.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `1 test files, 1408 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-selector-target-prefixer.php --self-test`
  - Result: `selector target prefixer example self-test passed`
- `php -l lanes/lightningcss/src/TransitionPrefixer.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-selector-target-prefixer.php`
  - Result: no syntax errors in all three files.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 8881 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - Result: passed.

## Status Delta

- `phpPass`: `8873 -> 8881` (`+8`)
- Conservative mapped coverage remains `2399 / 3532`; this deepens an already represented target-prefix selector boundary cluster.

## Dependency Closure

No new support component is needed. The slice reuses the existing `TransitionPrefixer` target normalization, feature fallback, selector specificity, and selector-list isolation helpers.

## Non-Overlap

This does not repeat accepted focus-visible/focus-within selector-list fallback behavior, selector pseudo vendor prefixes, stale selector-prefix pruning, scroll-snap declaration prefixing, image-rendering, text-spacing/overscroll, supports declaration prefixing, media-query, source-map, CSSOM, CSS Modules, bundle/import graph, custom at-rule, or property/value parity work. The patch is limited to upstream scroll-navigation target pseudo browser-boundary selector-list fallback parity.
