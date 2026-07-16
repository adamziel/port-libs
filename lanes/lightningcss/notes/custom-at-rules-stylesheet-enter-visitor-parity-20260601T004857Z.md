# Custom At-Rules StyleSheet Enter Visitor Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T004857Z`
Base: `5b87111468b46af8cd72097f10d11bf759b0ca92`

## Source Truth

- Upstream `parcel-bundler/lightningcss` pinned commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `napi/src/transformer.rs` `visit_stylesheet` calls `StyleSheet` before child traversal and `StyleSheetExit` after child traversal.
- `node/index.d.ts` allows `Visitor.StyleSheet` to return a replacement `StyleSheet`.
- `napi/src/at_rule_parser.rs` exposes custom at-rules as typed `AtRule` values with `name`, parsed `prelude`, and parsed `body`.
- `node/test/customAtRules.mjs` covers declaration-list custom at-rule parser bodies.
- `node/test/visitor.test.mjs` covers stylesheet visitor entry/exit behavior.

## Patch

- `CustomAtRuleTransformer::transformWithDependencies()` now applies a returned `StyleSheet` replacement before normal rule/declaration/value traversal.
- The stylesheet visitor input now exposes top-level custom, unknown, media, and supports at-rules as typed visitor-rule objects instead of raw CSS-only placeholders.
- The stylesheet replacement serializer preserves typed at-rule AST output without running child visitors during serialization, so child visitors run once during the subsequent transform pass.
- Added focused coverage proving a `StyleSheet` enter replacement consumes a typed custom declaration-list body and a later `Length` visitor transforms both the replacement rule and the remaining original style rule.
- Added `wordpress-custom-at-rule-stylesheet-enter-visitor.php` to model block token extraction in a StyleSheet enter visitor without Node/WASM.

## Verification

- Baseline focused run before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 184 assertions, 0 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 192 assertions, 0 failures`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 5143 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rule-stylesheet-enter-visitor.php --self-test`
  => `OK`.
- `php -l` passed on changed PHP source, test, and example files.
- `git diff --check -- lanes/lightningcss` passed.
- Root harness: not run for this isolated micro-slice.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.

## Coverage Delta

- `phpPass`: `5135 -> 5143`.
- Focused `CustomAtRuleTransformerTest.php`: `184 -> 192` assertions.
- `benchmarkDenominator.mapped`: `2238 -> 2240 / 3532`, conservatively counting StyleSheet enter replacement traversal and typed custom at-rule body exposure to StyleSheet visitors.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `CustomAtRuleTransformer`, declaration parser, visitor value serializers, and lane-local scanners.

## Non-Overlap

This avoids the stale 2026-05-25 CustomMedia rework note and recent accepted custom at-rule clusters for image preludes, RuleExit, Function/FunctionExit, Length/Color/Token, DashedIdent/CustomIdent, MediaQuery/SupportsCondition, selector, URL, variable/env visitors, and style-attribute behavior. The new behavior is specifically StyleSheet enter replacement application plus typed top-level custom at-rule exposure to that enter visitor.

## Next Task

Continue custom at-rule visitor parity with nested StyleSheet replacement edges across media/supports, or source-location exposure for visitor rule objects.
