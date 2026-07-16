# Break Supports Target Prefix Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T124514Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Pristine upstream read: `src/prefixes.rs` groups `Feature::BreakBefore`, `Feature::BreakAfter`, and `Feature::BreakInside` with WebKit prefixes for Android `2.1` through `4.4.3`, Chrome `4` through `49`, iOS Safari `3.2` through `8.1`, Opera `15` through `36`, Safari `3.1` through `8`, and Samsung through `4`.
- The PHP port already used those ranges for direct `break-*` declarations through `breakNeedsWebkit`.

## Red-First Gap

Before this change, direct declarations were prefixed for legacy targets, but `@supports` declaration guards were not:

```text
legacy=@supports (break-before:page){.foo{-webkit-break-before:page;break-before:page}}
modern=@supports (-webkit-break-before:page) or (break-before:page){.foo{break-before:page}}
compound=@supports (break-after:column) and (break-inside:avoid){.foo{-webkit-break-after:column;break-after:column;-webkit-break-inside:avoid;break-inside:avoid}}
```

Legacy Chrome/Safari should guard both prefixed and unprefixed declarations, and modern targets should prune stale prefixed support conditions.

## Implementation

- Added `break-before`, `break-after`, and `break-inside` to `TransitionPrefixer::supportsDeclarationPrefixGroups()` using the existing `breakNeedsWebkit` target option.
- Added focused assertions for legacy Chrome guard insertion, modern Chrome stale guard pruning, and combined Safari `break-after` plus `break-inside` support conditions.
- Extended `wordpress-break-prefixer.php` so the same block pagination example covers both direct declarations and `@supports` guards.

## Verification

```text
php -l lanes/lightningcss/src/TransitionPrefixer.php
No syntax errors detected in lanes/lightningcss/src/TransitionPrefixer.php

php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php

php -l lanes/lightningcss/examples/wordpress-break-prefixer.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-break-prefixer.php

php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
1 test files, 1287 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-break-prefixer.php --self-test
printed expected legacy/modern direct declaration output and legacy/modern @supports output

php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json OK\n";'
lane-status json OK

git diff --check -- lanes/lightningcss
passed with no output
```

## Status Delta

- `TransitionPrefixerTest.php` gains three focused assertions in the existing support-declaration target-prefix boundary test.
- `lane-status.json` `phpPass` moves from `7843` to `7846` from the verified focused assertion delta. Full lane and upstream Rust/Node/WASM runners were not run in this isolated micro-slice.
- Conservative mapped coverage remains `2392 / 3532`; this deepens an existing target-prefix/supports behavior family rather than claiming a new upstream denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `@supports` condition scanner, declaration-prefix group rewriter, existing browser target option encoder, and existing WordPress example harness.

## Non-Overlap

This does not repeat accepted direct break declaration prefixing, transition/filter/font/backdrop supports-condition work, scroll-snap declaration parity, selector/media/source-map/CSSOM/CSS Modules/bundle/import/custom-at-rule work, or full upstream runner evidence. It is limited to break-family `@supports` declaration target-prefix boundaries.

## Next Task

Continue non-overlapping `@supports` target-prefix parity for remaining declaration families, or pivot to the next unmapped LightningCSS target-prefix/source-map/CSSOM gap if another worker covers those support guards.
