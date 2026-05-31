# Custom At-Rule Unknown Visitor Parity 2026-05-31T13:42Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, specifically
`node/test/composeVisitors.test.mjs` `visitor function`, where composed
`Rule.unknown` visitors handle `@dep` and `@dep2` statement at-rules, collect
their string preludes, return empty replacements, and leave only the style rule
in generated CSS.

Native PHP delta:

- `CustomAtRuleTransformer::composeVisitors()` now composes `Rule.unknown`
  visitors in the same bounded style as existing custom at-rule visitors.
- `CustomAtRuleTransformer` now dispatches unknown at-rule statements and
  blocks to `Rule.unknown` visitors, with string/ident/raw prelude tokens,
  original prelude text, body text, context, and parent selector metadata.
- The WordPress custom-at-rule smoke now models `@asset` and `@asset-style`
  markers that are collected by composed visitors and removed before the CSS is
  emitted.

Evidence:

- Red-first ad hoc check before implementation left `@dep "foo.js"` in output
  and collected no dependency.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 20 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 1386 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php --self-test`
  exits `0`.
- Full upstream Rust/Node runners: not run for this isolated micro-slice.

Coverage accounting:

- Conservative mapped coverage moves from `1046 / 3532` to `1047 / 3532`.
- Local LightningCSS PHP assertions move from `1382` to `1386`.

Dependency closure:

- No new support component is needed. This reuses the existing bounded
  `CustomAtRuleTransformer`, visitor composition, declaration parser, minifier,
  and native scanner helpers; no external parser, browser, Node, Rust, or
  shell-out is introduced.

Next task:

- Continue visitor/custom parser parity with broader known-rule visitor
  composition or custom parser token-shape coverage, while keeping full
  upstream Rust/Node parity as a separate runner-evidence task.
