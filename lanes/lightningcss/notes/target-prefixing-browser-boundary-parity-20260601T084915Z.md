# Selector Stale Prefix Browser-Boundary Parity

Source truth: upstream `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_selectors` cases for adjacent stale `:-moz-read-only` / `:read-only` pruning, separated same-body rule preservation, and fullscreen selector prefix browser boundaries.

Behavior ported:
- Adjacent stale prefixed selector rules with the same body are replaced by the unprefixed canonical selector when the target browser no longer needs the prefix.
- Adjacent stale prefixed selector rules separated by a different body remain separate, matching upstream adjacency-sensitive pruning.
- Legacy targets that still need the prefix keep exactly one prefixed selector plus the unprefixed selector when an existing prefixed rule is followed by an unprefixed rule that expands to generated prefix variants.
- Fullscreen and file-selector selector prefixes use the same adjacent stale-prefix pruning path as read-only/read-write selector pseudos.

Red-first evidence:
- Before this patch, Firefox 85 kept `.foo:-moz-read-only{color:red}` before adjacent `.foo:read-only{color:red}`.
- Before this patch, Firefox 36 generated duplicate `.foo:-moz-read-only` rules when the existing prefixed rule was adjacent to the unprefixed rule.
- Before this patch, Chrome 96 kept stale fullscreen prefixed selector rules beside `:fullscreen`.
- Before the final dedupe fix, legacy fullscreen targets duplicated generated `:-webkit-full-screen`, `:-moz-full-screen`, and `:-ms-fullscreen` variants.

Focused evidence:
- `php -l lanes/lightningcss/src/TransitionPrefixer.php` passed.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-selector-stale-prefix-boundaries.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` passed with 1 test file, 1134 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-selector-stale-prefix-boundaries.php --self-test` passed.
- `php tools/run-tests.php lanes/lightningcss/tests` passed with 13 test files, 7004 assertions, 0 failures.

WordPress smoke:
- `wordpress-selector-stale-prefix-boundaries.php` models block search input `:read-only` and cover `:fullscreen` CSS where modern frontend targets prune stale prefixed selectors and legacy editor targets keep required prefixed variants without duplication.

Dependency closure: no new support component is needed; this reuses the native PHP `TransitionPrefixer`, target-version routing, selector rewriting, minifier output, and lane-local WordPress example coverage.

Non-overlap: avoids accepted unicode-bidi, selector pseudo generation, selector any/lang/dir, focus selector-list isolation, supports declaration, media range, logical property, CSSOM, source-map, CSS Modules, and bundle/import graph slices. This slice is limited to adjacent stale selector prefix pruning and duplicate suppression for browser boundary parity.
