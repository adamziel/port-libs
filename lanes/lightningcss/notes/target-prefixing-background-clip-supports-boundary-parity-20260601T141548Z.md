# Target Prefixing Browser Boundary Parity - Background Clip Supports

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T141548Z`

## Scope

This slice closes the missing `@supports` declaration-prefix behavior for
`background-clip:text`. Direct declarations already used the pinned upstream
browser windows, but the supports prelude stayed unprefixed for legacy targets
and kept stale prefixed branches for modern targets.

Source truth:

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at
  `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/prefixes.rs` `Feature::BackgroundClip` maps WebKit prefixes for legacy
  Android, Chrome, Edge, Opera, Safari, and Samsung ranges, and MS prefixes for
  Edge 12 through 14.
- `src/rules/supports.rs` applies target prefixes to supported declaration
  property IDs in supports conditions and serializes prefixed alternatives with
  `or`.

## Red-First Probe

Before the patch:

```text
@supports (background-clip:text){.foo{-webkit-background-clip:text;background-clip:text}}
@supports (-webkit-background-clip:text) or (background-clip:text){.foo{background-clip:text}}
@supports (background-clip:text){.foo{-ms-background-clip:text;background-clip:text}}
```

The body declarations were correct, but the supports conditions did not match
the target prefix boundary.

## Patch

- Added `background-clip` to the native PHP supports declaration-prefix map,
  using the existing `backgroundClipNeedsWebkit` and `backgroundClipNeedsMs`
  target options.
- Added five focused assertions for Chrome 119/120, Edge 13, and Safari 13/14
  support-condition insertion/pruning boundaries.
- Extended `wordpress-background-clip-prefixer.php` to smoke direct and
  supports-guarded gradient-text CSS for legacy WebKit, modern WebKit pruning,
  and legacy MS Edge.

## Verification

```text
php -l lanes/lightningcss/src/TransitionPrefixer.php
No syntax errors detected in lanes/lightningcss/src/TransitionPrefixer.php

php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php

php -l lanes/lightningcss/examples/wordpress-background-clip-prefixer.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-background-clip-prefixer.php

php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
1 test files, 1323 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-background-clip-prefixer.php
matched direct and @supports WordPress background-clip outputs

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 8189 assertions, 0 failures

php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json OK\n";'
lane-status json OK

git diff --check -- lanes/lightningcss
No output.
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `TransitionPrefixerTest.php` adds 5 focused assertions.
- `lane-status.json` now records the verified full lane PHP evidence at
  `8189 pass / 0 fail`.
- Conservative mapped coverage remains `2393 / 3532`; this deepens an already
  represented target-prefix/supports family rather than adding a new denominator
  row.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP target
option table, supports-condition parser, declaration-prefix prelude rewriter,
test harness, and WordPress example smoke.

## Non-Overlap

This does not repeat accepted direct background-clip declaration prefixing,
filter/backdrop-filter/transition/font/break/touch-action/text-orientation
supports-condition work, print-color-adjust stale pruning, selector, media
query, CSSOM, CSS Modules, source-map, bundle/import graph, or custom at-rule
clusters. It is limited to `background-clip` support-condition target-prefix
browser-boundary parity.

## Next Task

Continue non-overlapping LightningCSS target-prefix support-condition parity
for another declaration family, or pivot to source-map, CSSOM, bundle/import
graph, CSS Modules, media-query, property/value, or custom-at-rule parity.
