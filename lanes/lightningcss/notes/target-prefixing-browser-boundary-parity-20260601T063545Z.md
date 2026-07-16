# LightningCSS target-prefixing browser boundary parity - 2026-06-01T063545Z

## Slice

- Lane: `lightningcss`
- Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T063545Z`
- Worktree base: `263ff1b299519d64e76087161433531b7a3e8cf2`
- Behavior: `@keyframes` target-prefixing now covers Opera 12 `@-o-keyframes`, removes stale `@-o-keyframes` for Opera 13+, and preserves Opera 15+ WebKit keyframes parity.

## Upstream Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Pristine reads used:
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show HEAD:src/prefixes.rs`
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show HEAD:src/parser.rs`
- `src/prefixes.rs` maps `Feature::AtKeyframes` to `VendorPrefix::O` for Opera `12`, to `VendorPrefix::WebKit` for Opera `15..29`, to `VendorPrefix::Moz` for Firefox `5..15`, and to `VendorPrefix::WebKit` for the existing legacy Chrome/Safari/Android ranges.
- `src/parser.rs` recognizes `@-o-keyframes` as a vendor-prefixed keyframes rule; this slice implements the O-prefix branch already represented in the upstream parser/prefix table.

## Implementation

- `TransitionPrefixer` now recognizes `@-o-keyframes` as a keyframes at-rule.
- Target options add `keyframesNeedsO` for Opera `12`.
- Keyframes rewriting emits `@-o-keyframes` when required, removes stale `@-o-keyframes` when not required, and uses the existing emitted-keyframes de-duplication so source CSS containing both prefixed and unprefixed rules does not duplicate output.
- `wordpress-keyframes-prefixer.php` now has a self-test for Safari 8, Opera 12, and Opera 13 block animation delivery.

## Verification

- Pre-change probe:
  - `php -r 'require "tools/bootstrap.php"; $p = new PortLibs\\LightningCSS\\TransitionPrefixer(); echo $p->prefixForTargets("@keyframes test { from { opacity: 0 } to { opacity: 1 } }", ["opera" => 12]), "\n"; echo $p->prefixForTargets("@-o-keyframes test { from { opacity: 0 } to { opacity: 1 } } @keyframes test { from { opacity: 0 } to { opacity: 1 } }", ["opera" => 13]), "\n";'`
  - Output showed Opera 12 emitted only unprefixed `@keyframes`, and Opera 13 preserved stale `@-o-keyframes`.
- Focused test:
  - `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1049 assertions, 0 failures`
- Full lane:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6463 assertions, 0 failures`
- Example smoke:
  - `php lanes/lightningcss/examples/wordpress-keyframes-prefixer.php --self-test`
  - exit `0`
- Additional required gates are recorded in the final handoff.

## Non-overlap

This slice does not touch source maps, CSS Modules, bundle/import graph, media-query range/layer fallback, selector autofill/pseudo prefixing, animation declaration shorthand boundaries, or WebKit/Mozilla keyframes behavior already accepted. It is limited to the upstream `Feature::AtKeyframes` Opera O-prefix browser boundary and the WordPress keyframes example.

## Dependency Closure

No new support component is needed. The existing PHP minifier, target option normalization, at-rule traversal, and keyframes de-duplication machinery are reused.
