# Custom At-Rule Token Array Visitor Re-Entry

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T043009Z`

Base accepted HEAD: `a9f4989344098e67e1082ce806a8270acd26ace6`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `node/composeVisitors.js` composes `Function`, `Token`, `Variable`, and `EnvironmentVariable` through `composeTokenVisitors()`.
- Upstream `createTokenVisitor()` routes returned token/value arrays through `createArrayVisitor()`, so replacement list items restart visitor composition and can be observed by later value/structured visitors.
- `node/index.d.ts` defines `TokenReturnValue = TokenOrValue | TokenOrValue[] | RawValue | void`, which covers array replacements from Function and Token visitors.

## Red-First Evidence

Before this patch, a composed Function visitor returning a list of `[Length, var(--gap)]` serialized the list directly:

```text
.card{margin:16px var(--gap)}
```

The later composed `Length` and `Variable` visitors were not invoked for the inserted list items. Upstream behavior is for those inserted token/value items to re-enter the composed token visitor pipeline.

## Implementation

- `CustomAtRuleTransformer::applyValueVisitors()` now traverses list replacements item-by-item.
- Replacement traversal now re-enters structured `var()` and `env()` visitors before serialization, with a same-structured-type skip guard to avoid cycles when a visitor returns its original node shape.
- `callStructuredValueVisitor()` accepts the skip guard and uses it when applying visitor-returned replacement values.
- `callFunctionVisitor()` applies replacement value traversal with a `function` skip guard before serializing Function-returned arrays.
- Added focused tests for Function-returned `[Length, var()]` lists and Token-returned `[Length, env()]` lists flowing through later `Variable`, `EnvironmentVariable`, and `Length` visitors.
- Added a WordPress block-style example for token-array design token expansion without Node/WASM.

## Verification

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php` passed.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-token-array-visitor.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed: `1 test files, 262 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 5982 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-token-array-visitor.php --self-test` passed: `OK`.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status OK\n";'` passed.
- `git diff --check -- lanes/lightningcss` passed.

## Status Delta

- Focused custom at-rule assertions: `258 -> 262` (`+4`).
- `phpPass`: `5978 -> 5982` after the focused assertion delta.
- Conservative mapped coverage remains `2336 / 3532`; this deepens the already represented custom at-rule visitor composition cluster.
- Full upstream Rust/Node/WASM runners were not executed in this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP custom at-rule visitor composition, value serialization, and structured var/env parsing support.

## Non-Overlap

This does not repeat accepted Function typed replacement traversal, FunctionExit/Length visitor composition, Declaration/Rule array re-entry, StyleSheet enter replacements, or RuleExit returned-rule re-entry. The behavior is specifically token/value array replacements from Function and Token visitors flowing into later structured and value visitors.

## Next Task

Continue upstream `createTokenVisitor()` parity for `FunctionExit`, `VariableExit`, and `EnvironmentVariableExit` array replacements, plus raw env/var spacing cases from `node/test/visitor.test.mjs`.
