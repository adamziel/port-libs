# Custom At-Rules Returned Prelude Visitor Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T174536Z`

Source truth:
- Upstream cache `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `node/index.d.ts` exposes `RuleVisitor` as returning `ReturnedRule | ReturnedRule[] | void`, and exposes `Length`, `DashedIdent`, and `Token` visitors over visitor-returned AST values.
- `node/ast.d.ts` models raw at-rule preludes as `TokenOrValue[]`, which means visitor-returned prelude token lists should re-enter value visitors before serialization and `RuleExit`.

Implemented behavior:
- Returned custom rule objects with changed token-list preludes are re-entered through custom prelude value visitors before child body traversal, `RuleExit.custom`, and serialization.
- Source custom rules retain their existing visitor-visible shape; only visitor-returned array preludes are re-entered.
- The returned prelude AST is refreshed after traversal so `RuleExit.custom` sees the visited prelude string and corresponding AST.

Focused evidence:
- Baseline before this slice: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> `1 test files, 482 assertions, 0 failures`.
- Red-first after adding the focused case before implementation: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> `1 test files, 483 assertions, 1 failures`; output still contained `--wp-slot 16px`.
- After implementation: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> `1 test files, 485 assertions, 0 failures`.
- Added WordPress smoke: `php lanes/lightningcss/examples/wordpress-custom-at-rule-returned-prelude-visitor.php --self-test` -> `OK`.
- Changed PHP lint passed for `CustomAtRuleTransformer.php`, `CustomAtRuleTransformerTest.php`, and `wordpress-custom-at-rule-returned-prelude-visitor.php`.
- `git diff --check -- lanes/lightningcss` passed.

Dependency closure:
- No new support component is needed. The slice reuses the existing PHP custom at-rule transformer, token-list parser, value visitors, `RuleExit` visitors, and minifier.

Non-overlap:
- This does not alter CSS Modules, source maps, target-prefixing, media query parsing, CSSOM read/write, unknown-rule blocks, or native layer visitor surfaces.
- It avoids the accepted returned media/body traversal clusters and fills the narrower returned custom-rule prelude re-entry gap noted by earlier custom at-rule parity work.

Follow-up:
- Nested `Token` visitors inside function arguments constructed directly in returned custom preludes remain a separate candidate; this slice is intentionally limited to returned custom prelude token-list re-entry for top-level value visitors.
