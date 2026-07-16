# Custom At-Rule Unknown Token Visitor Parity 2026-05-31T13:44Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, specifically
`node/test/composeVisitors.test.mjs` `unknown rules`.

Native PHP delta:

- `CustomAtRuleTransformer::composeVisitors()` now carries a
  `Rule.unknown` replacement of upstream shape `type: unknown` into later
  composed `Rule.unknown` visitors instead of returning it immediately.
- `CustomAtRuleTransformer` now composes `Token` visitors for declaration
  value `at-keyword` tokens such as `@blue`.
- Declaration value rewriting now applies those token visitors after existing
  function visitor rewrites, preserving the existing custom function behavior.
- The WordPress custom at-rule smoke now models a block color alias collected
  from an unknown at-rule and consumed by a later declaration token.

Evidence:

- Red-first focused run before the token visitor closure fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  failed the new upstream-shaped case with actual
  `.menu_link{background:@#00f}`.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 22 assertions, 0 failures`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 1558 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php --self-test`
  exits `0`.
- Full upstream Rust/Node runners: not run for this isolated micro-slice.

Coverage accounting:

- Conservative mapped coverage moves from `1133 / 3532` to `1134 / 3532`.
- Local LightningCSS PHP assertions move from `1556` to `1558`.

Dependency closure:

- No new support component is needed. This reuses the existing bounded
  `CustomAtRuleTransformer`, visitor composition, declaration parser,
  minifier, and native scanner helpers; no external parser, browser, Node,
  Rust, or shell-out is introduced.

Next task:

- Continue visitor/custom parser parity with known-rule visitor composition or
  declaration/value visitor chaining beyond the accepted custom/unknown/token
  subset, while keeping full upstream Rust/Node parity as a separate
  runner-evidence task.
