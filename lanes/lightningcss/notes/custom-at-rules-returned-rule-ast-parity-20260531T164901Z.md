# Custom At-Rule Returned Rule AST Parity 2026-05-31T16:49Z

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream files:
  - `node/test/visitor.test.mjs` `media query raw`
  - `node/test/visitor.test.mjs` `returning string values`
  - `node/test/visitor.test.mjs` `apply`
  - `node/index.d.ts` / `node/ast.d.ts` returned-rule and visitor type shapes

## Implementation

- `CustomAtRuleTransformer` now exposes parsed custom at-rule `bodyRules` for `rule-list` and `style-block` bodies as lightweight returned-rule objects.
- Custom and unknown visitors can return upstream-style `type: style`, `type: media`, and `type: ignored` rule objects.
- Returned media rules support raw media queries and nested returned style rules.
- Returned style rules support selector component serialization, raw declaration values with CSS escape decoding, vendor-prefixed declarations, and important declaration buckets.
- `wordpress-custom-at-rules-transformer.php` now exercises the returned media-rule path for the responsive block custom at-rule instead of using helper-only replacement.

## Verification

- Baseline before this slice: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed with `1 test files, 43 assertions, 0 failures`.
- After implementation: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed with `1 test files, 48 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` passed with `13 test files, 2318 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php --self-test` exited `0`.
- Syntax checks passed for:
  - `lanes/lightningcss/src/CustomAtRuleTransformer.php`
  - `lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php`
- `git diff --check -- lanes/lightningcss` passed.

## Counting

- PHP assertion delta: `+5` focused assertions.
- Conservative mapped coverage delta: `+3`, from `1446 / 3532` to `1449 / 3532`.
- Counted checks: returned raw media rule object, returned raw/vendor style rule object, and returned ignored rule object.

## Non-Overlap

This slice does not repeat accepted custom at-rule declaration-list/mixin/rule-list parser coverage, composed custom/unknown/token/function visitors, FunctionExit/Length chaining, environment-variable/variable visitors, style-rule visitor composition, visitor factory dependencies, or the accepted CustomMediaTransformer import-tail scanner rework. It only adds returned-rule AST object serialization and parsed custom body rule exposure.

## Dependency Closure

No new support component is needed. The patch reuses the bounded native `CustomAtRuleTransformer`, `DeclarationBlock`, `CssMinifier`, selector scanner, and media-query normalization paths. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is introduced.

Root harness status: not run - isolated micro-slice.
