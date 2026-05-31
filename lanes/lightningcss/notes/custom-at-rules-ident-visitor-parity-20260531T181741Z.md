# Custom At-Rule Identifier Visitor Parity 2026-05-31T18:17Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, specifically:

- `node/test/visitor.test.mjs` `dashed idents`, where a `DashedIdent`
  visitor rewrites a custom property declaration name and the matching
  `var(--foo)` reference.
- `node/test/visitor.test.mjs` `custom idents`, where a `CustomIdent`
  visitor rewrites a `@keyframes` name and the matching `animation` reference.

Native PHP delta:

- `CustomAtRuleTransformer::composeVisitors()` now composes `DashedIdent` and
  `CustomIdent` visitors.
- `DashedIdent` now applies to emitted custom property names and to dashed
  names in `var()` and `env()` references.
- `CustomIdent` now applies to `@keyframes` preludes and to `animation-name` /
  `animation` declaration references.
- Added `wordpress-custom-at-rule-ident-visitor.php`, which converts a custom
  `@motion` at-rule into keyframes while identifier visitors namespace block
  animation names and custom property tokens.

Evidence:

- Red-first probe before implementation:
  `php -r 'require "tools/bootstrap.php"; $t=new PortLibs\LightningCSS\CustomAtRuleTransformer(); echo $t->transform(".foo { --foo: #ff0; color: var(--foo); }", [], ["DashedIdent" => static fn(string $ident): string => "--prefix-" . substr($ident, 2)]) . PHP_EOL; echo $t->transform("@keyframes test { from { color: red } to { color: green } } .foo { animation: test; }", [], ["CustomIdent" => static fn(string $ident): string => "prefix-" . $ident]) . PHP_EOL;'`
  returned unchanged identifiers.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 78 assertions, 0 failures`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 2928 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rule-ident-visitor.php --self-test`
  exits `0`.
- `php -l` passed on changed PHP source, test, and example files.
- `git diff --check -- lanes/lightningcss` passed.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.
- Root harness status: not run - isolated micro-slice.

Coverage accounting:

- Conservative mapped coverage moves from `1645 / 3532` to `1647 / 3532`.
- Local LightningCSS PHP evidence moves from `2923` to `2928` assertions.

Dependency closure:

- No new support component is needed. The slice reuses the existing native
  `CustomAtRuleTransformer`, `DeclarationBlock`, `CssMinifier`, value scanners,
  and visitor composition helpers; no Node, Rust, WASM, browser service, parser
  generator, or external CSS engine is introduced.

Non-overlap:

- This avoids accepted custom at-rule parser basics, composed custom/unknown/
  token/function/FunctionExit/env/var/Declaration/StyleSheet/Selector/Url
  visitors, returned rule AST serialization, native media visitors, CSS
  Modules dashed identifier scoping, media-query range/layer behavior,
  SourceMap offsets, bundler import graph, target-prefix, property-value, and
  CSSOM read/write slices.
- The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and
  left untouched because this handoff stays on custom at-rule identifier
  visitor parity.

Next task:

- Continue visitor parity with `SupportsCondition`/`MediaQuery`, richer
  returned-rule AST nodes such as supports rules, style-attribute dependency
  visitor behavior, or pivot to a non-overlapping property-value/font/grid/
  color, CSS Modules, source-map, bundler, media-query, target-prefix, or
  CSSOM cluster.
