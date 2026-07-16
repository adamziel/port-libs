# Custom At-Rule Url Visitor Parity 2026-05-31T17:59Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, specifically:

- `node/test/visitor.test.mjs` `url`, where a `Url` visitor receives
  `url(foo.png)`, rewrites `url.url`, and serializes the new URL in the
  declaration value.
- `node/index.d.ts` `Visitor.Url?(url: Url): Url | void`, where URL visitors
  participate in the same visitor surface as length, function, declaration,
  selector, variable, and environment-variable visitors.
- `node/ast.d.ts` `Url`, where the visitor shape exposes a `url` string and
  source location metadata.

Native PHP delta:

- `CustomAtRuleTransformer` now configures a `Url` visitor and composes it via
  `composeVisitors()`.
- Declaration value `url(...)` functions are parsed into upstream-shaped URL
  arrays, passed through the visitor, and serialized back to CSS.
- Composed URL visitors chain replacements, so later visitors see the URL
  returned by earlier visitors.
- Added `wordpress-custom-at-rule-url-visitor.php`, where an unknown
  `@asset-base` at-rule supplies a theme asset root and a `Url` visitor rewrites
  a block background image without Node, Rust, WASM, browser APIs, or network
  access.

Evidence:

- Baseline focused run before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 65 assertions, 0 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 67 assertions, 0 failures`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 2827 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rule-url-visitor.php --self-test`
  exits `0`.
- `php -l` passed on changed PHP source, test, and example files.
- `git diff --check -- lanes/lightningcss` passed.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.
- Root harness status: not run - isolated micro-slice.

Coverage accounting:

- Conservative mapped coverage moves from `1616 / 3532` to `1617 / 3532`.
- Local LightningCSS PHP assertions move from `2825` to `2827`.

Dependency closure:

- No new support component is needed. This reuses the bounded native
  `CustomAtRuleTransformer`, `CssMinifier`, visitor composition, and value
  scanner helpers; no external parser, browser, Node, Rust, WASM, or shell-out
  is introduced.

Non-overlap:

- This avoids accepted custom at-rule parser basics, composed custom/unknown/
  token/function/FunctionExit/env/var/declaration/stylesheet/selector visitors,
  returned rule AST serialization, CSS Modules selector scoping, media-query
  range/layer behavior, SourceMap offsets, bundler import graph, target-prefix,
  property-value, and CSSOM read/write slices.
- The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and
  left untouched because this handoff stays on custom at-rule URL visitor
  parity.

Next task:

- Continue visitor parity with `DashedIdent`, `CustomIdent`, `MediaQuery`,
  `SupportsCondition`, richer returned-rule AST shapes, or pivot to a
  non-overlapping property-value/font/grid/color, CSS Modules, source-map,
  bundler, media-query, target-prefix, or CSSOM cluster.
