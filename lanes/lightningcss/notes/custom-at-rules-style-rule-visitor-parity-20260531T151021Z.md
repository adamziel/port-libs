# Custom At-Rule Style Rule Visitor Parity 2026-05-31T15:10Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, specifically
`node/test/composeVisitors.test.mjs` `known rules`.

Native PHP delta:

- `CustomAtRuleTransformer::composeVisitors()` now composes bounded
  `Rule.style` visitors in order.
- Style visitors receive parsed selector and declaration arrays, can rewrite
  declaration values, can remove a rule, or can return multiple style-rule
  replacements.
- The WordPress custom at-rule smoke now exercises this path by resolving
  `@margin-left` from a sibling declaration and cloning `:focus-visible` to a
  `.focus-visible` fallback after custom at-rule style-block expansion.

Evidence:

- Baseline focused run before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 28 assertions, 0 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 30 assertions, 0 failures`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 1831 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php --self-test`
  exits `0`.
- Full upstream Rust/Node runners: not run for this isolated micro-slice.

Coverage accounting:

- Conservative mapped coverage moves from `1258 / 3532` to `1259 / 3532`.
- Local LightningCSS PHP assertions move from `1829` to `1831`.

Dependency closure:

- No new support component is needed. This reuses the existing bounded
  `CustomAtRuleTransformer`, declaration parser, minifier, and native scanner
  helpers; no external parser, browser, Node, Rust, or shell-out is introduced.

Non-overlap:

- This does not repeat accepted custom at-rule parser cases, composed
  `Rule.custom` / `Rule.unknown` visitors, dashed `Token.at-keyword` visitors,
  composed `Function` visitors, CSSOM mask-border/scroll-snap behavior,
  @font-face minifier completion, or logical inset prefixing.

Next task:

- Continue visitor parity with `FunctionExit` / value chaining,
  environment-variable visitors, or broader known-rule visitor shapes while
  keeping full upstream Rust/Node parity as a separate runner-evidence task.
