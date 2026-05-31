# Custom At-Rule Selector Visitor Parity 2026-05-31T17:24Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, specifically:

- `node/test/visitor.test.mjs` `selector prefix`, where a `Selector` visitor
  prepends a `.prefix` class and descendant combinator to every selector.
- `node/test/visitor.test.mjs` `nth of S to nth-of-type`, where a `Selector`
  visitor sees an `nth-child(even of a)` pseudo-class, removes the `of`
  selector-list, changes the kind to `nth-of-type`, and serializes `2n`.

Native PHP delta:

- `CustomAtRuleTransformer::composeVisitors()` now composes bounded
  `Selector` visitors alongside the existing custom/unknown/style/function/
  declaration/value visitor pipeline.
- Style rule emission now dispatches `Selector` visitors before `Rule.style`
  visitors, so top-level rules, nested rules, and custom at-rule
  `style-block` replacements share the same selector visitor path.
- Selector AST parsing/serialization now covers the bounded upstream shapes
  needed for class/type selectors, descendant/explicit combinators, functional
  pseudo-classes, and `nth-child(... of S)` selector-list payloads.
- Added `wordpress-custom-at-rule-selector-visitor.php` to model editor-scope
  selector prefixing plus `nth-child(even of a)` to `nth-of-type(2n)` mutation
  while token values still flow through custom at-rule and function visitors.

Evidence:

- Baseline focused run before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 54 assertions, 0 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 60 assertions, 0 failures`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 2665 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rule-selector-visitor.php --self-test`
  exits `0`.
- `php -l` passed on changed PHP source, test, and example files.
- `git diff --check -- lanes/lightningcss` passed.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.
- Root harness status: not run - isolated micro-slice.

Coverage accounting:

- Conservative mapped coverage moves from `1566 / 3532` to `1568 / 3532`.
- Local LightningCSS PHP assertions move from `2659` to `2665`.

Dependency closure:

- No new support component is needed. This reuses `CustomAtRuleTransformer`,
  `DeclarationBlock`, `CssMinifier`, and lane-local selector/value scanner
  helpers; no external CSS parser, browser, Node, Rust, or shell-out is
  introduced.

Non-overlap:

- This avoids accepted custom at-rule parser basics, composed custom/unknown/
  token/function/style/FunctionExit/env/var/Declaration/StyleSheet visitors,
  CSS Modules selector scoping, media-query range/layer behavior, SourceMap
  offsets, filter target boundaries, color calc, and CSSOM read/write slices.
  The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and
  left untouched because this handoff stays on custom at-rule selector visitor
  parity.

Next task:

- Continue visitor parity with `Url`, `DashedIdent`, `CustomIdent`,
  media/supports rule visitors, or richer selector AST shapes, or pivot to a
  non-overlapping property-value/font/grid/color, CSS Modules, source-map,
  bundler, media-query, target-prefix, or CSSOM cluster.
