# Custom At-Rules Layer Parser Visitor Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T162416Z`

Source truth:
- Upstream cache `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `node/ast.d.ts` exposes `Rule` variants `layer-statement` and `layer-block`, with `LayerStatementRule.names: String[][]` and `LayerBlockRule.name?: String[] | null` plus child `rules`.
- `node/index.d.ts` maps all non-custom/non-unknown `Rule['type']` entries through `MappedRuleVisitors`, so `Rule` and `RuleExit` visitors should receive layer statements and blocks as typed native rules.

Implemented behavior:
- `CustomAtRuleTransformer` now parses native `@layer` statements as `layer-statement` visitor rules.
- Native `@layer` blocks are parsed as `layer-block` visitor rules, including hierarchical layer names and child rule lists.
- `StyleSheet` visitors now see both layer rule variants through the stylesheet parser path.
- `RuleExit.layer-block` runs after child value/rule visitors, matching existing media/supports/container traversal.
- Returned and composed layer rules serialize back to CSS and can participate in `composeVisitors`.

Focused evidence:
- Before this slice: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> `1 test files, 470 assertions, 0 failures`.
- After this slice: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> `1 test files, 478 assertions, 0 failures`.
- Added WordPress smoke: `php lanes/lightningcss/examples/wordpress-custom-at-rule-layer-visitor.php --self-test` -> `OK`.
- Changed PHP lint passed for `CustomAtRuleTransformer.php`, `CustomAtRuleTransformerTest.php`, and `wordpress-custom-at-rule-layer-visitor.php`.
- `git diff --check -- lanes/lightningcss` passed.

Dependency closure:
- No new support component is needed. The slice reuses the existing PHP custom at-rule transformer, rule-list parser, declaration/value visitors, and minifier.

Non-overlap:
- This does not alter accepted custom parser prelude/body behavior, returned media/supports/container rules, CSS Modules, source maps, target-prefixing, or CSSOM read/write surfaces. It fills the remaining native layer rule visitor parity gap in the custom-at-rule visitor path.
