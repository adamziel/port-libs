# Custom At-Rules RuleExit Visitor Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260531T221701Z`

Base: `b5eea1a41c2dcbd3b034814e155f2555fc5c0b4e`

Upstream source truth:

- Pinned upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Files inspected:
  - `node/index.d.ts` declares `RuleExit?: RuleVisitor | RuleVisitors<C>` with the same mapped visitor shape as `Rule`.
  - `node/composeVisitors.js` composes `RuleExit` through the same object visitor path as `Rule`, including custom and unknown at-rules.
  - `napi/src/transformer.rs` wires both rule-enter and rule-exit visitor refs through `VisitorsRef::new`.
  - `src/visitor.rs` backs the native rule exit traversal model used by the Node/NAPI surface.

Behavior implemented:

- `CustomAtRuleTransformer` now configures and dispatches bounded `RuleExit` visitors for custom, unknown, style, and media rules.
- Custom at-rule `RuleExit` callbacks run after parser traversal, so they can inspect parsed body aliases such as `declaration-list` and generated declaration arrays.
- Unknown at-rule `RuleExit` callbacks receive parsed prelude tokens and can replace the rule with style/media/raw output.
- Composed `RuleExit.style` visitors can clone or mutate style rules after body declaration traversal.
- `RuleExit.media` visitors run after media body traversal and can rewrite the media query AST before serialization.

Red-first evidence:

- Baseline `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` on the accepted base passed at `1 test files, 137 assertions, 0 failures`, with no RuleExit custom/unknown/style/media assertions.
- The focused test now passes at `1 test files, 146 assertions, 0 failures`, adding 9 assertions for the RuleExit cluster.

Mapped coverage:

- Conservative denominator movement: `2163 / 3532` -> `2163 / 3532`.
- This deepens the already represented custom at-rule visitor/parser cluster rather than claiming a new denominator row.

Verification:

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php && php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-custom-at-rule-exit-visitor.php`
  - no syntax errors detected
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `1 test files, 146 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 4626 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-exit-visitor.php --self-test`
  - `OK`
- `python -m json.tool lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json >/dev/null && python -m json.tool lanes/lightningcss/lane-status.json >/dev/null`
  - exit `0`
- `git diff --check -- lanes/lightningcss`
  - exit `0`

WordPress smoke:

- `wordpress-custom-at-rule-exit-visitor.php` models a block token custom at-rule plus an unknown asset at-rule that are converted by `RuleExit` callbacks after parsing, then runs `RuleExit.style` on the surviving block rule.

Dependency closure:

- No new support component is needed. The slice reuses `CustomAtRuleTransformer`, `DeclarationBlock`, `MediaQueryParser`, `CssMinifier`, and existing bounded token/body parsers. No Node, Rust, WASM, browser, or parser-generator dependency is introduced.

Non-overlap:

- This does not touch the stale custom-media rework note or accepted declaration-list, mixin, rule-list, repeated prelude, stylesheet visitor, FunctionExit/Length, env/var, native media Rule visitor, style-rule visitor, or target-prefix/property-value/source-map/CSS Modules clusters.
- Root harness status: not run - isolated micro-slice.
