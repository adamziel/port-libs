# Target Prefixing Rule Merge Parity - 2026-06-01T02:29Z

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T022900Z`

Source truth:

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Upstream cases: `src/lib.rs::test_transitions` backdrop-filter `transition-property` and `transition` prefix tests merge `.foo` and `.bar` once Safari target prefixing makes their rewritten declarations identical.
- Extra oracle: direct native addon transform for Chrome 53 merges stale `-webkit-filter` removal with the following identical unprefixed `filter` rule.

Behavior added:

- `TransitionPrefixer` now keeps a narrow post-prefix merge state for adjacent single style rules.
- Adjacent rules merge only when the rewritten declaration body matches and at least one side changed during target prefixing. Generic untouched duplicate rules remain outside this slice.
- The merge state resets at at-rules or multi-rule rewrites, so support-rule and selector-fallback expansions are not folded accidentally.

Evidence:

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-target-prefix-rule-merge.php`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 888 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-target-prefix-rule-merge.php --self-test`
- `git diff --check -- lanes/lightningcss`

Dependency closure:

- No new support component is needed. This reuses the existing CSS parser/minifier and target-prefixing declaration rewrite pipeline.

Non-overlap:

- Does not repeat the accepted cursor target-prefix boundary slice from source `38285e381`.
- Does not edit custom-media/import graph rework areas from the stale current-rebase note.
- Conservative mapped coverage remains `2297 / 3532`; this strengthens a weakly mapped transition-prefix helper cluster rather than claiming a new denominator row.
