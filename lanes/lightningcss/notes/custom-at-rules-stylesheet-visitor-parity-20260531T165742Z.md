# Custom At-Rule Stylesheet Visitor Parity 2026-05-31T16:57Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, specifically:

- `node/test/composeVisitors.test.mjs` `StyleSheet`, where composed
  `StyleSheet` and `StyleSheetExit` visitors are both invoked.
- `node/test/visitor.test.mjs` `visit stylesheet`, where `StyleSheetExit`
  mutates `stylesheet.rules` order before serialization.

Native PHP delta:

- `CustomAtRuleTransformer::composeVisitors()` now composes bounded
  `StyleSheet` and `StyleSheetExit` callbacks.
- `transformWithDependencies()` now exposes a stylesheet rule array before
  transformation for entry callbacks and after minification for exit callbacks.
- `StyleSheetExit` can reorder top-level style rules and append
  upstream-shaped style rules with selector component arrays and declaration
  blocks.
- The exit serializer does not re-run value visitors over already-transformed
  declaration values, matching upstream post-transform visitor behavior.
- The WordPress custom at-rule smoke appends a final block-state rule through
  `StyleSheetExit`.

Evidence:

- Baseline focused run before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 51 assertions, 0 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 54 assertions, 0 failures`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 2364 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php --self-test`
  exits `0`.
- `php -l` passed on changed PHP source, test, and example files.
- `git diff --check -- lanes/lightningcss` passed.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.

Coverage accounting:

- Conservative mapped coverage moves from `1458 / 3532` to `1460 / 3532`.
- Local LightningCSS PHP assertions move from `2361` to `2364`.

Dependency closure:

- No new support component is needed. This reuses `CustomAtRuleTransformer`,
  `DeclarationBlock`, `CssMinifier`, and lane-local scanner/serializer helpers;
  no external parser, browser, Node, Rust, or shell-out is introduced.

Non-overlap:

- This avoids accepted custom at-rule parser basics, composed custom/unknown/
  token/function/style/FunctionExit/env/var/Declaration visitors, visitor
  factory dependencies, SourceProvider reads, container CSSOM, media qualifier
  fallback serialization, clip-path target prefixing, and SourceMap overflow
  guards. The stale 2026-05-25 CustomMedia rework note was inspected and left
  untouched because this handoff stays on stylesheet visitor parity.

Next task:

- Continue visitor parity with `DashedIdent`, `CustomIdent`, `Url`, `Selector`,
  or richer stylesheet-rule AST shapes, or pivot to a non-overlapping
  property-value/font/grid/color, CSS Modules, source-map, bundler, media-query,
  target-prefix, or CSSOM cluster.
