# Custom At-Rules Composed Rule Re-Entry Parity - 2026-06-01T03:01Z

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T030133Z`

Source truth:

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Upstream files: `node/composeVisitors.js`, `node/index.d.ts`, and `node/test/customAtRules.mjs`
- Behavior: upstream `createArrayVisitor()` restarts composed rule visitor traversal after a visitor returns replacement rule nodes, skipping the visitor that produced the current replacement to avoid cycles. This lets later `Rule.style` and `Rule.media` visitors see style/media rules returned by an earlier custom or unknown at-rule visitor.

Behavior added:

- `CustomAtRuleTransformer::composeVisitors()` now routes composed `Rule.custom`, `Rule.unknown`, `RuleExit.custom`, and `RuleExit.unknown` replacements through a shared rule re-entry helper.
- Returned custom, unknown, style, and media rule arrays are normalized and re-fed through later composed rule visitors, matching upstream visitor composition semantics for custom at-rule parser replacements.
- Unknown at-rule visitors still accept name-keyed callbacks and can return unknown-rule wrappers, style rules, media rules, lists, strings, or removals through the same composed rule path.

Evidence:

- Red-before spot check: an earlier `Rule.custom` replacement returning a style rule did not receive a later composed `Rule.style` mutation before this patch.
- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`
  - `No syntax errors detected in lanes/lightningcss/src/CustomAtRuleTransformer.php`
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-composed-rule-reentry.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-custom-at-rule-composed-rule-reentry.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `1 test files, 242 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5687 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-composed-rule-reentry.php --self-test`
  - `.wp-block-card{color:#ff0;outline-color:#056ef0}@media (width>=48rem){.wp-block-card__media{color:red}}`
- `git diff --check -- lanes/lightningcss`

Dependency closure:

- No new support component is needed. This reuses the native PHP custom at-rule parser, serializer, and visitor composition pipeline under `CustomAtRuleTransformer`.
- No Node, Rust, WASM, browser, network, or parser-generator dependency is introduced.

Non-overlap:

- This does not repeat accepted custom at-rule parser/body/prelude traversal, Length/Ratio/Image/Token/Function visitor, variable-exit visitor, or CSS Modules replay slices.
- This slice is limited to upstream `composeVisitors` returned-rule re-entry for composed custom and unknown at-rule visitor replacements.
- Conservative mapped coverage remains `2315 / 3532`; the patch deepens the represented custom at-rule visitor cluster rather than adding a new denominator row.
