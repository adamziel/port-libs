# Custom At-Rules Returned Declaration-List Visitor Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T131724Z`

Base accepted HEAD: `a93e599b8ba28b765620aaefefa98a3cad05be92`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Relevant upstream surfaces:
  - `node/test/customAtRules.mjs` for custom at-rule parser body forms.
  - `node/test/composeVisitors.test.mjs` for composed visitors over custom at rules.
  - `node/test/visitor.test.mjs` for declaration/value visitor traversal and exit visitor sequencing.

## Behavior Ported

Returned custom rule replacements with `bodyType: declaration-list` now reuse the same child traversal path as parsed custom rules before serialization. This lets later composed declaration/value visitors rewrite returned declaration-list bodies and lets `RuleExit.custom` observe the visited body.

Before this patch, a `Rule.custom` visitor returning a custom declaration-list rule serialized the replacement body raw, bypassing `Length`, `Function`, `DeclarationExit`, and `RuleExit.custom` traversal for that returned rule.

## Verification

- Red-first focused failure before implementation:
  - `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - Result: `1 test files, 412 assertions, 1 failures`
  - Failing output preserved the returned body as `gap:16px;color:theme-token('accent')`.
- Focused custom at-rule test after implementation:
  - `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - Result: `1 test files, 414 assertions, 0 failures`
- Full LightningCSS lane:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 7989 assertions, 0 failures`
- Changed example smoke:
  - `php lanes/lightningcss/examples/wordpress-custom-at-rule-returned-declaration-list.php --self-test`
  - Result: `OK`

## Non-Overlap

This slice avoids the accepted CSSOM legacy flex/source-map work and the existing custom at-rule returned media/style/unknown-rule coverage. The new behavior is limited to returned `custom` rule replacements whose body is a declaration-list and whose body needs declaration/value visitor traversal before custom rule exit visitors.

## Dependency Closure

No new support component is needed. The patch reuses the existing PHP `CustomAtRuleTransformer` declaration-list parser, value visitor rewrite pipeline, and custom rule exit visitor plumbing.

## Next

Continue custom at-rule parity around parser/visitor replacement edge cases not covered by this slice, especially returned custom rule prelude token mutation and nested custom replacement ordering if upstream evidence shows remaining divergence.
