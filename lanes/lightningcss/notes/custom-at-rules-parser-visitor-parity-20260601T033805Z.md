# Custom At-Rule Function Visitor Value Pipeline

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T033805Z`

Base accepted HEAD: `86e2d14305df2668712f30216ab52d92b6b533a7`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `node/composeVisitors.js` composes `Function` through `composeTokenVisitors(res, visitors, 'Function', 'function', false)`.
- `createTokenVisitor()` routes token/value replacements through `createArrayVisitor()`, whose replacement loop restarts later visitors across token/value types while skipping already-seen visitors.
- `node/index.d.ts` declares `Function?: FunctionVisitor | { [name: string]: FunctionVisitor }` and `FunctionVisitor` returns `TokenReturnValue`, so a Function visitor may return a typed value such as Length or Color that later value visitors should see.

## Red-First Evidence

Before this patch, a composed Function visitor returning a Length typed value serialized before the later Length visitor:

```text
php -r '... theme("space") returns ["type"=>"length","unit"=>"px","value"=>32] and later Length px -> rem ...'
.card{width:32px}
```

Expected upstream-composed behavior is `.card{width:2rem}` because the typed Function replacement must continue through value visitors.

## Implementation

- `CustomAtRuleTransformer::callFunctionVisitor()` now normalizes array replacements, preserves raw replacement recursion, and applies the composed value visitor pipeline before serialization.
- Declaration color fallback is guarded when a Function replacement has already entered the Color visitor path, preventing non-idempotent Color visitors from running twice on `color: theme(...)`.
- Added a focused regression where `theme("space")` returns a Length and `theme("accent")` returns a Color, and later Length/Color visitors rewrite them to `2rem` and `#0f0`.
- Added `wordpress-custom-at-rule-function-value-visitor.php` as a WordPress-facing smoke for build-free design-token Function visitors.

## Verification

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php` passed.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-function-value-visitor.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed: `1 test files, 246 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 5797 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-function-value-visitor.php --self-test` passed: `OK`.
- `git diff --check -- lanes/lightningcss` passed.

## Status Delta

- `phpPass`: `5793 -> 5797`.
- Conservative mapped coverage remains `2320 / 3532`; this deepens the already represented custom at-rule visitor composition cluster rather than adding a new denominator row.
- Full upstream Rust/Node/WASM runners were not executed in this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP visitor composition, declaration parsing, and value serialization support.

## Non-Overlap

This does not repeat the accepted FunctionExit/Length visitor slice, raw Function visitor replacement cases, custom at-rule Ratio visitor traversal, or custom at-rule style/rule visitor batches. The behavior is specifically `Function` typed replacements flowing into later value visitors.

## Next Task

Continue custom at-rule visitor parity around token-array replacements and declaration/rule replacement arrays where upstream `createArrayVisitor()` restarts composition across later visitors.
