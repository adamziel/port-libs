# LightningCSS custom at-rule number token prelude parity - 2026-06-01T092522Z

## Slice

- Lane: `lightningcss`
- Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T092522Z`
- Worktree base: `5d3833db5349181ff1e32c459b4c7ae4edd1837e`
- Behavior: universal custom at-rule preludes, unknown at-rule prelude token lists, and `FunctionExit` arguments now expose bare numeric CSS tokens as upstream-style `Token.number` values instead of PHP-local raw text.

## Upstream Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Pristine reads used:
  - `node/index.d.ts`
  - `node/ast.d.ts`
- `node/index.d.ts` includes `number` in `VisitableTokenTypes`, so `Token.number` visitors are part of the public visitor surface.
- `node/ast.d.ts` defines custom at-rule preludes and custom function arguments as `TokenOrValue[]`; `Token` includes `{ type: "number", value: number }`.

## Implementation

- `CustomAtRuleTransformer::parseComponentValue()` now parses standalone signed and decimal numeric CSS tokens as `['type' => 'token', 'value' => ['type' => 'number', ...]]`.
- `CustomAtRuleTransformer::parseSingleFunctionArgumentValue()` now applies the same parser path for `FunctionExit` argument ASTs.
- A shared `parseNumberTokenValue()` helper keeps the prelude and function-argument token shape consistent.
- Added a focused regression for `@plugin 2 theme(4) -1.5;` proving `Token.number` visitors run before the custom rule visitor and run again after a `FunctionExit` replacement reenters the prelude visitor pipeline.
- Extended unknown at-rule prelude token-list coverage to assert number token AST preservation.
- Added `wordpress-custom-at-rule-number-prelude.php` as a build-free WordPress design-scale smoke.

## Verification

- Pre-change probe:
  - `php -r 'require "tools/bootstrap.php"; use PortLibs\\LightningCSS\\CustomAtRuleTransformer; ... @plugin 2 theme(4) ...'`
  - Output showed `["function::","rule:2 4"]` and a prelude AST containing raw `2` and `4` entries.
- PHP lint:
  - `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`
  - `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-number-prelude.php`
- Focused test:
  - `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `1 test files, 351 assertions, 0 failures`
- Full lane check:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7120 assertions, 0 failures`
- Example smoke:
  - `php lanes/lightningcss/examples/wordpress-custom-at-rule-number-prelude.php --self-test`
  - `OK`

## Non-overlap

This slice is limited to bare numeric token parsing in custom at-rule universal preludes, unknown at-rule prelude token lists, and function-exit arguments. It does not touch bundle/import graph resolution, source maps, CSS Modules, CSSOM read/write behavior, media-query parsing, escaped custom at-rule names, env/var visitor traversal, returned rule arrays, style-block parser bodies, or target prefixing.

## Dependency Closure

No new support component is needed. The existing native PHP custom at-rule parser, visitor composition, token serializer, and WordPress example harness are reused.
