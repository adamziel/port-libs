# Target Prefixing Filter Supports Boundary Parity - 2026-06-01T07:47Z

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T074737Z`

Base accepted HEAD: `96bb58b60d20f5361c38e55e8dbf9b1e1adc5570`

## Source Truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Source read: `src/prefixes.rs::Feature::Filter`, whose WebKit target range covers Chrome 18-52 and Safari 6-9, among other legacy WebKit browsers.
- Native-addon probes at the pinned commit confirmed:
  - Chrome 52: `@supports (filter: blur(5px))` lowers to `@supports ((-webkit-filter:blur(5px)) or (filter:blur(5px)))`.
  - Chrome 53: an existing `(-webkit-filter) or (filter)` guard is pruned back to `(filter)`.
  - Safari 9/10 follow the same WebKit boundary.

## Implemented

- `TransitionPrefixer::supportsDeclarationPrefixGroups()` now includes `filter` with the existing `filterNeedsWebkit` target flag.
- Added focused assertions for Chrome 52/53 and Safari 9/10 support-condition boundaries, plus an `and` composition case.
- Updated `examples/wordpress-filter-prefixer.php` so the WordPress glass-header smoke checks both direct filter declarations and `@supports (filter: ...)` guard rewriting.

## Evidence

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1076 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-filter-prefixer.php`
  - exited `0` and printed the expected legacy/modern outputs
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6798 assertions, 0 failures`
- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - no syntax errors
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-filter-prefixer.php`
  - no syntax errors
- `git diff --check -- lanes/lightningcss`
  - passed with no output

## Dependency Closure

No new support component is needed. This reuses the native PHP `@supports` condition parser, declaration-prefix group rewriter, existing filter browser-boundary target flag, and lane-local tests/examples.

## Non-Overlap

This does not repeat the accepted direct `filter` declaration browser-boundary slice or the broader accepted `@supports` declaration-prefix slice. It only closes the missing `filter` support-condition family explicitly left for future work in the prior supports note. It also avoids accepted placeholder, flex, logical spacing, media-query, source-map, CSSOM, CSS Modules, bundle/import, and custom-at-rule clusters.

## Follow-Up

Remaining support-condition prefix families such as `animation`, `transition`, and `background-clip` should be handled in separate pinned-upstream slices so their body-prefix and support-guard behavior can be verified independently.
