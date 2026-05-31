# Custom At-Rules Parser Visitor Parity 2026-05-31T18:43Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, specifically:

- `node/index.d.ts` `Visitor.MediaQuery`, `Visitor.MediaQueryExit`,
  `Visitor.SupportsCondition`, and `Visitor.SupportsConditionExit`, where
  visitor callbacks may replace native query/condition AST objects.
- `node/ast.d.ts` `MediaQuery`, `MediaList`, and `SupportsCondition` returned
  shapes, including raw returned media queries, declaration supports
  conditions, selector conditions, unknown conditions, and boolean/range media
  features.
- `node/test/visitor.test.mjs` `media query raw`, which demonstrates returned
  raw media query serialization from custom parser visitors, and `100vh fix`,
  which demonstrates returned supports-rule serialization from visitor output.

Native PHP delta:

- `CustomAtRuleTransformer::composeVisitors()` now composes `MediaQuery`,
  `MediaQueryExit`, `SupportsCondition`, and `SupportsConditionExit` visitors.
- Native `@media` rules route their parsed media query AST through query
  visitors while preserving existing environment-variable visitor substitution
  in media preludes.
- Native `@supports` rules route parsed declaration, selector, `not`, `and`,
  `or`, and unknown condition ASTs through supports-condition visitors.
- Returned media and supports rule objects from custom parser visitors also
  pass through these condition-level visitors before serialization.
- Added `wordpress-custom-at-rule-condition-visitor.php`, which rewrites a
  block hover media guard and display support guard without Node, Rust, WASM,
  browser APIs, or network access.

Evidence:

- Red-first media query probe before implementation:
  `php -r 'require "tools/bootstrap.php"; $t = new PortLibs\LightningCSS\CustomAtRuleTransformer(); echo $t->transform("@media (hover) {.card { color: red; }}", [], ["MediaQuery" => static fn(array $query): array => ["raw" => "screen"]]) . PHP_EOL;'`
  returned `@media (hover){.card{color:red}}`.
- Red-first supports condition probe before implementation:
  `php -r 'require "tools/bootstrap.php"; $t = new PortLibs\LightningCSS\CustomAtRuleTransformer(); echo $t->transform("@supports (display: grid) {.card { color: red; }}", [], ["SupportsCondition" => static fn(array $condition): array => ["type" => "declaration", "propertyId" => ["property" => "display"], "value" => "flex"]]) . PHP_EOL;'`
  returned `@supports (display:grid){.card{color:red}}`.
- Focused baseline:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 82 assertions, 0 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 88 assertions, 0 failures`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 3114 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rule-condition-visitor.php --self-test`
  exits `0`.
- `php -l` passed on changed PHP source, test, and example files.
- `git diff --check -- lanes/lightningcss` passed.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.
- Root harness status: not run - isolated micro-slice.

Coverage accounting:

- Conservative mapped coverage moves from `1689 / 3532` to `1691 / 3532`.
- Local LightningCSS PHP evidence moves from `3108` to `3114` assertions.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `CustomAtRuleTransformer`, `MediaQueryParser`, `DeclarationBlock`,
  `CssMinifier`, and bounded scanner/serializer helpers; no external parser,
  browser, Node, Rust, WASM, or shell-out is introduced.

Non-overlap:

- This avoids accepted custom at-rule parser basics, returned media/style/
  supports rule object serialization, native `Rule.media` visitors,
  declaration/function/env/var/selector/url/identifier visitors, CSS Modules,
  media-query minifier/range-layer behavior, bundler import graph, SourceMap,
  target-prefix, property-value, and CSSOM read/write slices.
- The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and
  left untouched because this handoff stays on condition-level custom
  at-rule visitor parity.

Next task:

- Continue non-overlapping visitor parity with style-attribute visitor
  factories/dependencies, richer condition AST nodes, or move to source-map,
  CSS Modules, bundler import graph, property-value, target-prefix, media-query,
  or CSSOM gaps.
