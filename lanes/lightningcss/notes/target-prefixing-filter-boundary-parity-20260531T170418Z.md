# Filter Target Boundary Parity

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T170418Z`

Base accepted HEAD: `261654988ed05a567ee0c91d111919c7b0fe6e36`

## Source Truth

Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`

Source reads:

- `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs | nl -ba | sed -n '502,532p'`
- `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '28272,28418p'`

Upstream `Feature::Filter` requires WebKit filter prefixes for Android 4.4-4.4.3, Chrome 18-52, iOS Safari 6-9, Opera 15-39, Safari 6-9, and Samsung 4-6.2. Upstream `test_filter` also keeps the Chrome 4 custom-property Lab filter fallback unprefixed while preserving the sRGB base declaration and Lab `@supports` override.

## Implemented

- Corrected `TransitionPrefixer` filter target ranges to match `Feature::Filter`.
- Added boundary assertions for Chrome 17/18/52/53, Safari 9/10, iOS Safari 9/10, and Samsung 6.2/6.3.
- Corrected advanced-color custom-property filter fallback expectations so Chrome 4 remains unprefixed for `filter: var(...) drop-shadow(... lab(...))`.
- Updated `examples/wordpress-filter-prefixer.php` to self-check Chrome 52/Safari 14 and Chrome 53/Safari 14 sticky glass-header outputs.

## Evidence

- Red-first: `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` failed with `1 test files, 377 assertions, 2 failures` before the range correction.
- Syntax and metadata: `php -l` passes for `TransitionPrefixer.php`, `TransitionPrefixerTest.php`, and `wordpress-filter-prefixer.php`; `jq empty` passes for `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json`.
- Focused: `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` passes with `1 test files, 387 assertions, 0 failures`.
- Full lane-focused: `php tools/run-tests.php lanes/lightningcss/tests` passes with `13 test files, 2500 assertions, 0 failures`.
- Example: `php lanes/lightningcss/examples/wordpress-filter-prefixer.php` exits 0 and prints both target-boundary outputs.
- Diff hygiene: `git diff --check -- lanes/lightningcss` passes.

## Coverage Delta

Expected lane-status movement: `phpPass` 2489 -> 2500 (+11), `phpFail` remains 0.

Conservative mapped coverage remains 1539 / 3532 because this corrects and deepens the already represented upstream `test_filter` cluster rather than adding a new denominator unit.

## Dependency Closure

No new support component is needed. This reuses `TransitionPrefixer` target-version routing, declaration scanning, duplicate-prefix removal, and advanced-color fallback helpers.

## Non-Overlap

This slice avoids accepted mask, clip-path, background-clip, image-set, display/flex, logical inset, text-decoration, text-emphasis, print-color-adjust, UI, keyframes, light-dark, media range, CSSOM, CSS Modules, source-map, and bundler clusters. It only corrects `Feature::Filter` browser-boundary parity.
