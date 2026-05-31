# Custom At-Rule Token Visitor Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260531T230626Z`

Source truth:

- Upstream cache: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `node/index.d.ts` exposes `VisitableTokenTypes = 'ident' | 'at-keyword' | 'hash' | 'id-hash' | 'string' | 'number' | 'percentage' | 'dimension'`.
- `node/ast.d.ts` defines the corresponding Token shapes.
- `node/composeVisitors.js` composes token visitors by token type.
- `napi/src/transformer.rs` maps NAPI `Token` visitors for Ident, AtKeyword, Hash, IDHash, String, Number, Percentage, and Dimension.

Red-first probe before this slice:

```text
.card{--state:draft;--shade:#056ef0;--label:"draft";--ratio:3;--progress:25%}
array ()
```

That probe used composed `Token.ident`, `Token.hash`, `Token.string`, `Token.number`, and `Token.percentage` visitors against declaration values. No scalar token visitor fired before the implementation.

Implementation:

- `CustomAtRuleTransformer::composeVisitors()` now wires all upstream token visitor names instead of only `at-keyword` and `dimension`.
- Declaration-value token rewriting now dispatches string, hash, id-hash, percentage, number, and ident tokens while preserving accepted at-keyword and dashed custom-unit dimension behavior.
- Visitor token-object serialization now supports returned `hash`, `id-hash`, `ident`, `string`, `number`, and percentage token wrappers.
- Added a WordPress block-design-token smoke example that rewrites custom-property values through composed token visitors without Node/WASM.

Verification:

```text
php -l lanes/lightningcss/src/CustomAtRuleTransformer.php
No syntax errors detected in lanes/lightningcss/src/CustomAtRuleTransformer.php

php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
No syntax errors detected in lanes/lightningcss/tests/CustomAtRuleTransformerTest.php

php -l lanes/lightningcss/examples/wordpress-custom-at-rule-token-visitor.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-custom-at-rule-token-visitor.php

php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
1 test files, 151 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-custom-at-rule-token-visitor.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 4705 assertions, 0 failures

git diff --check -- lanes/lightningcss
clean
```

Status delta:

- Focused custom at-rule assertions: `148 -> 151`.
- Full LightningCSS lane assertions: `4702 -> 4705`.
- Conservative mapped coverage: unchanged at `2174 / 3532`; this deepens the already represented custom at-rule Token visitor cluster rather than adding a new static denominator row.

Dependency closure:

No new support component is needed. The slice reuses the native PHP custom at-rule scanner, visitor composition, and serializer paths; no Node, Rust, WASM, or external runner is required.

Non-overlap:

- Avoided the stale CustomMedia import-tail rework note.
- Avoided accepted custom at-rule parser basics, at-keyword visitor, dashed custom-unit dimension visitor, Function/FunctionExit, Declaration, Rule, media/supports/style-attribute, CSS Modules, CSSOM, source-map, bundler/import graph, media-query, target-prefix, and property-value clusters.

Root harness:

Not run - isolated micro-slice.
