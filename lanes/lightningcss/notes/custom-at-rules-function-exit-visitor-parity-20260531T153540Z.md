# Custom At-Rule FunctionExit Visitor Parity 2026-05-31T15:35Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, specifically
`node/test/composeVisitors.test.mjs` `tokens and functions`.

Native PHP delta:

- `CustomAtRuleTransformer::composeVisitors()` now composes bounded
  `FunctionExit` visitors by function name or generic callback.
- Returned length values pass through a later composed `Length` visitor, so
  nested function replacements can chain like upstream.
- The WordPress custom at-rule smoke now exercises a nested `wp-size()` /
  `wp-rem()` value pipeline for a block spacing token.

Evidence:

- Baseline focused run before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 30 assertions, 0 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 34 assertions, 0 failures`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 1922 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php --self-test`
  exits `0`.
- Full upstream Rust/Node runners: not run for this isolated micro-slice.

Coverage accounting:

- Conservative mapped coverage moves from `1311 / 3532` to `1312 / 3532`.
- Local LightningCSS PHP assertions move from `1918` to `1922`.

Dependency closure:

- No new support component is needed. This reuses the existing bounded
  `CustomAtRuleTransformer`, declaration parser, minifier, and native scanner
  helpers; no external parser, browser, Node, Rust, or shell-out is introduced.

Non-overlap:

- This does not repeat accepted custom at-rule declaration-list, mixin/apply,
  rule-list, unknown-token, dashed-ident, composed Function, or style-rule
  visitor behavior. It maps the remaining upstream `FunctionExit` plus value
  visitor chain from the Node compose-visitors suite.

Next task:

- Continue visitor/custom parser parity with `EnvironmentVariable` and
  `Variable` visitor chains, or move to source-map/bundler/CSS Modules/media
  query/CSSOM property clusters with focused PHP evidence.
