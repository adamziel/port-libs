# Custom At-Rule Parser Visitor Parity 2026-05-31T14:53Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, specifically:

- `node/test/customAtRules.mjs` `declaration list`, where a custom
  declaration-list at-rule feeds a later `Function` visitor.
- `node/test/composeVisitors.test.mjs` `tokens and functions`, where composed
  visitors forward `Function` handlers by name/generic handler.
- `node/test/visitor.test.mjs` `apply` plus `node/ast.d.ts`, where unknown
  at-rule preludes expose `<dashed-ident>` token values to visitors.

Native PHP delta:

- `CustomAtRuleTransformer::composeVisitors()` now composes `Function`
  visitors in the same bounded style as existing custom, unknown, and token
  visitors.
- Unknown at-rule prelude tokenization now exposes dashed identifiers as
  upstream-shaped `dashed-ident` token-or-value entries instead of raw strings.
- Declaration value token rewriting now recognizes dashed at-keyword tokens
  like `@--wp-accent`, so a later composed `Token.at-keyword` visitor can
  consume aliases captured from unknown at-rule preludes.
- The WordPress custom at-rule smoke now keeps the `token()` replacement inside
  the composed visitor and adds a dashed design-token alias consumed by a token
  visitor.

Evidence:

- Baseline focused run before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 22 assertions, 0 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 28 assertions, 0 failures`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 1765 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php --self-test`
  exits `0`.
- Full upstream Rust/Node runners: not run for this isolated micro-slice.

Coverage accounting:

- Conservative mapped coverage moves from `1232 / 3532` to `1234 / 3532`.
- Local LightningCSS PHP assertions move from `1759` to `1765`.

Dependency closure:

- No new support component is needed. This reuses the existing bounded
  `CustomAtRuleTransformer`, visitor composition, declaration parser,
  minifier, and native scanner helpers; no external parser, browser, Node,
  Rust, or shell-out is introduced.

Next task:

- Continue visitor/custom parser parity with broader returned-value token
  shapes, `FunctionExit`/value chaining, or known-rule visitor composition
  beyond the accepted custom/unknown/token/function subset, while keeping full
  upstream Rust/Node parity as a separate runner-evidence task.
