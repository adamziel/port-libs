# Custom At-Rule Exit-Array Function Visitor Parity

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T061618Z`

Base accepted HEAD: `cc1b0ff669a7347b4e43610b8425ed481a9b7e5c`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream files: `node/composeVisitors.js`, `node/index.d.ts`, and `napi/src/transformer.rs`.
- Behavior: `TokenReturnValue` includes token/value arrays, and upstream `createArrayVisitor()` flattens replacement arrays and continues visitor composition across later token/value visitors. This includes arrays returned from `VariableExit` and `EnvironmentVariableExit` before later `FunctionExit` and `Length` visitors serialize the final declaration value.

## Red-First Evidence

Before the source patch, the new custom at-rule declaration-list regression failed:

```text
Expected: '@tokens wp{gap:0.5rem 1rem;padding:2rem 1.5rem}'
Actual: '@tokens wp{gap:wp-var-step() 1rem;padding:wp-env-step() 1.5rem}'
```

Focused command:

```text
php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
1 test files, 308 assertions, 1 failures
```

`VariableExit` and `EnvironmentVariableExit` arrays were traversed for direct `Length` values, but function-shaped replacement values did not re-enter `FunctionExit`.

## Implementation

- `CustomAtRuleTransformer::applyValueVisitors()` now flattens list replacements when nested replacements also return arrays.
- Function-shaped replacement values now re-enter structured, `Function`, and `FunctionExit` visitor dispatch before serialization.
- `visitFunctionExit()` applies replacement traversal with the existing `function` skip guard, preventing a `FunctionExit` replacement from immediately re-triggering itself.
- Added focused coverage for a registered custom at-rule declaration-list body where `VariableExit` and `EnvironmentVariableExit` each return `[Function, Length]`, and later `FunctionExit` plus `Length` visitors produce final rem values.
- Added `wordpress-custom-at-rule-exit-array-functions.php` as a WordPress-facing smoke for design-token exit arrays without Node/WASM.

## Verification

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php` passed.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-exit-array-functions.php` passed.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-exit-array-functions.php --self-test` passed: `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed: `1 test files, 309 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 6431 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` passed.

## Status Delta

- Focused custom at-rule assertions: `307 -> 309` (`+2`) for this micro-slice.
- `lane-status.json` `phpPass`: `6429 -> 6431`, matching the current full lane test output in this worktree.
- Conservative mapped coverage remains `2359 / 3532`; this deepens the already represented custom at-rule parser/visitor cluster rather than adding a new upstream manifest denominator row.
- Full upstream Rust/Node/WASM runners were not executed in this isolated micro-slice.
- Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP custom at-rule transformer, declaration-list parser, visitor composition, structured value dispatch, and serializer pipeline.

## Non-Overlap

This does not repeat accepted custom at-rule SyntaxString component coverage, token-list prelude visitor coverage, comma token-list parsing, Function/Token array replacement re-entry, single `VariableExit`/`EnvironmentVariableExit` replacement coverage, raw env/var spacing, returned rule traversal, or stylesheet enter/exit visitor parity. It is scoped to function-shaped values inside exit-array replacements from custom at-rule declaration-list bodies.

## Next Task

Continue LightningCSS parity in distinct source-map, CSS Modules, bundle/import graph, media-query, property-value, CSSOM, target-prefix, or remaining custom at-rule parser/visitor body behavior that is not exit-array function re-entry.
