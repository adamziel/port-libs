# Media Query Firefox Opera Range Boundary Parity - 2026-06-01T03:06Z

Slice: `lightningcss-media-query-range-layer-parity-20260601T030654Z`

Source truth:

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Upstream file: `src/compat.rs`
- `Feature::MediaRangeSyntax` falls back for Firefox `< 63` and Opera `< 71`.
- `Feature::MediaIntervalSyntax` falls back for Firefox `< 102` and Opera `< 71`.

Red-first evidence:

- Before this patch, `TransitionPrefixer` kept Firefox 62 simple ranges modern, kept Firefox 101 interval syntax modern, and kept Opera 71 simple/interval ranges in fallback form.
- Direct probe outputs before the change included:
  - Firefox 62 simple range: `@media (width>=240px)`
  - Firefox 101 interval range: `@media (100px<=width<=200px)`
  - Opera 71 simple range: `@media (min-width:240px)`
  - Opera 71 interval range: `@media (min-width:100px) and (max-width:200px)`

Behavior added:

- `TransitionPrefixer` now uses the pinned upstream Firefox and Opera target boundaries for media range syntax.
- Simple layer-contained range queries fall back through Firefox 62 and Opera 70, then preserve modern syntax at Firefox 63 and Opera 71.
- Interval range queries fall back through Firefox 101 and Opera 70, then preserve modern syntax at Firefox 102 and Opera 71.
- The WordPress media range layer example now self-tests those four browser boundary pairs.

Evidence:

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `2 test files, 1324 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5722 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
- `git diff --check -- lanes/lightningcss`

Dependency closure:

- No new support component is needed. This reuses the existing media-query parser, `TransitionPrefixer` target normalization, and WordPress layer prefixing example.

Non-overlap:

- Does not repeat accepted Safari/iOS media range boundary parity, resolution media prefixing, `x` unit resolution serialization, include/exclude media range flags, typed media ranges, unknown media ranges, custom media, import graph, source map, CSS Modules, CSSOM, or target-prefix declaration slices.
- The stale May 25 `CustomMediaTransformer` rework note is unrelated to this media-query range fallback boundary cluster.
- Conservative mapped coverage remains `2315 / 3532`; this strengthens a represented media-query target-boundary cluster rather than claiming a new denominator row.

Next task:

- Continue with non-overlapping media-query parser recovery or target-boundary gaps that are not covered by the current Firefox/Opera range/interval, Safari/iOS range, resolution prefix, or media include/exclude replay.
