# Target Prefixing Legacy Text Supports Boundary Parity - 2026-06-01T20:18Z

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T201826Z`

Base accepted HEAD: `889f1d709734867fa2d1b9d74be494ea9a1e87a1`

Source truth:

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Upstream files inspected:
  - `src/prefixes.rs`: `Feature::TabSize`, `Feature::TextAlignLast`, `Feature::TextDecorationSkipInk`, and `Feature::BoxDecorationBreak` browser ranges.
  - `src/properties/prefix_handler.rs`: generated prefix handler for the same declaration families.
  - `src/properties/mod.rs`: prefixed property metadata for tab size, text alignment, text decoration skip ink, and box decoration break.

Behavior covered:

- `@supports (tab-size: 4)` now has explicit focused coverage for Firefox 90 requiring `-moz-tab-size`, Firefox 91 pruning it, Opera 12.1 requiring `-o-tab-size`, and Opera 13 pruning stale tab-size alternatives.
- `@supports (text-align-last: center)` now has explicit coverage for Firefox 48 requiring `-moz-text-align-last` and Firefox 49 pruning it.
- `@supports (text-decoration-skip-ink: all)` now has explicit coverage for Safari 12 requiring `-webkit-text-decoration-skip-ink` and Safari 12.1 pruning it.
- `@supports (box-decoration-break: clone)` now has explicit coverage for Chrome 129 and Safari 17 requiring `-webkit-box-decoration-break`, with Chrome 130 pruning stale alternatives.
- Compound `and` conditions keep their logical structure while each declaration condition expands through the same target-prefix map used for declaration output.

Files changed:

- `lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `lanes/lightningcss/examples/wordpress-supports-legacy-text-prefixer.php`
- `lanes/lightningcss/lane-status.json`
- `lanes/lightningcss/notes/target-prefixing-legacy-text-supports-boundary-parity-20260601T201826Z.md`

Evidence:

- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-supports-legacy-text-prefixer.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-supports-legacy-text-prefixer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1446 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 9064 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-supports-legacy-text-prefixer.php --self-test`
  - `OK`
- `git diff --check -- lanes/lightningcss`
  - Passed with no output.

Status delta:

- Focused `TransitionPrefixerTest.php`: `1434 -> 1446` assertions (`+12`).
- Full LightningCSS PHP lane: `9052 -> 9064` assertions (`+12`).
- Conservative mapped coverage remains `2439 / 3532`; this deepens an already represented target-prefixing supports-declaration cluster rather than claiming new denominator rows.

Dependency closure:

- No new support component is needed. This reuses the native PHP target option flags, declaration prefix group rewriter, and existing `@supports` condition parser. No Node, Rust, WASM, browser service, or external package support is required.

Non-overlap:

- This slice avoids the accepted direct legacy text/sticky browser-boundary coverage, the earlier generic `@supports` declaration-prefix slice, print/color-adjust, appearance/user-select, clip-path, hyphens/text-size-adjust, animation supports, filter/background-clip supports, selector, media-query, CSSOM, SourceMap, CSS Modules, bundle/import graph, property-value, and custom at-rule clusters.
- The only new countable behavior is focused support-condition browser-boundary coverage for `tab-size`, `text-align-last`, `text-decoration-skip-ink`, and `box-decoration-break`.

Follow-up:

- Remaining target-prefixing browser-boundary work should prefer unsupported or weakly tested generated prefix families not already represented by accepted direct declaration, support-condition, selector, media-query, or value-prefix slices.
