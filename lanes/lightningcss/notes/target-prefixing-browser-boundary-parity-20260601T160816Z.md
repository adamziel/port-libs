# Target Prefixing Browser Boundary Parity - 2026-06-01 16:16 UTC

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T160816Z`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/mod.rs` lines 1507-1510 define `text-emphasis-style`, `text-emphasis-color`, `text-emphasis`, and `text-emphasis-position` as WebKit-prefixed properties.
- `src/prefixes.rs` lines 1964-1995 map `Feature::TextEmphasis*` to WebKit prefixes for Android 4.4.x, Chrome 25-98, Edge 79-98, Opera 15-85, Safari 6.1-7, and Samsung 4-17.
- `src/rules/supports.rs` lines 153-165 applies target-derived prefixes to declaration conditions in `@supports` preludes.

## Patch

- Added all four text-emphasis properties to the native PHP `@supports` declaration-prefix rewrite map.
- Reused the existing `textEmphasisNeedsWebkit` browser-boundary flag already used by declaration prefixing.
- Extended `TransitionPrefixerTest.php` with six boundary assertions:
  - Chrome 98 adds `-webkit-text-emphasis-style` to `@supports` and declarations.
  - Chrome 99 prunes stale `-webkit-text-emphasis-style` support/declaration branches.
  - Edge 98 adds `-webkit-text-emphasis` to `@supports` and declarations.
  - Edge 99 prunes stale `-webkit-text-emphasis` support/declaration branches.
  - Safari 7 adds `-webkit-text-emphasis-color` to `@supports` and declarations.
  - Safari 8 prunes stale `-webkit-text-emphasis-color` support/declaration branches.
- Extended `wordpress-text-emphasis-prefixer.php` with a supports-guarded annotation rule so WordPress block annotation styles exercise prelude rewriting, not only declaration rewriting.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-text-emphasis-prefixer.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 1374 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-text-emphasis-prefixer.php --self-test` -> exit 0, expected smoke output matched.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8631 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

## Dependency Closure

No new support component is needed. This patch reuses the existing target option table, the supports-condition parser, and the declaration-prefix prelude rewriter.

## Non-Overlap

No `port-lightningcss-*.needs-lane-rework.md` note existed for this lane. This does not repeat accepted declaration-only text-emphasis prefixing or neighboring supports-prefix slices for touch-action, text-orientation, text-decoration, font, transition, filter, break, and color-adjust. It fills the missing text-emphasis `@supports` prelude parity for the upstream WebKit browser ranges.
