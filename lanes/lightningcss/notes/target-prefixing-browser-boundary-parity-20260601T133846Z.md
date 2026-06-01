# Target Prefixing Browser Boundary Parity - 2026-06-01 13:38 UTC

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T133846Z`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/prefixes.rs` maps `Feature::TouchAction` to `-ms-` only for IE 10.
- `src/prefixes.rs` maps `Feature::TextOrientation` to `-webkit-` for Safari 10.1 through 13.1.

## Patch

- Added `touch-action` and `text-orientation` to the native PHP `@supports` declaration-prefix rewrite map.
- The body declaration prefixer already handled these browser boundaries; this slice brings the supports prelude into parity so legacy targets gain prefixed alternatives and modern targets prune stale prefixed branches.
- Extended `TransitionPrefixerTest.php` with four boundary assertions covering IE 10/11 `touch-action` and Safari 10.1/13.2 `text-orientation`.
- Extended the WordPress touch/orientation smoke with supports-guarded block CSS output for IE 10 and Safari 10.1.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-touch-orientation-prefixer.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 1306 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-touch-orientation-prefixer.php` -> expected WordPress smoke output matched.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8074 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP target option table, supports-condition parser, and declaration-prefix prelude rewriter.

## Non-Overlap

This does not repeat the accepted touch-action/text-orientation declaration boundary work. It only fills the missing `@supports` prelude parity for the same upstream browser ranges.
