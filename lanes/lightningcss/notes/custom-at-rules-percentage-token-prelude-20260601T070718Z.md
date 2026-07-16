# LightningCSS custom at-rule percentage token prelude parity - 2026-06-01T070718Z

## Slice

- Lane: `lightningcss`
- Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T070718Z`
- Worktree base: `0c72e2d3dc6140f90e575fbd71aef1cf0d69e30f`
- Behavior: universal custom at-rule preludes and `FunctionExit` arguments now expose `25%` as upstream-style `Token` `percentage` values instead of PHP-local `%` lengths.

## Upstream Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Pristine reads used:
  - `/home/claude/port-libs/.upstream-cache/lightningcss/node/ast.d.ts`
  - `/home/claude/port-libs/.upstream-cache/lightningcss/node/index.d.ts`
  - `/home/claude/port-libs/.upstream-cache/lightningcss/node/composeVisitors.js`
- `node/ast.d.ts` defines `TokenOrValue` with raw `token` entries and `Token` includes `percentage`, whose numeric value is divided by 100.
- `node/ast.d.ts` defines custom `Function.arguments` as `TokenOrValue[]`, so an untyped percentage function argument remains a percentage token.
- `node/index.d.ts` exposes `Token` visitors for `percentage`; `node/composeVisitors.js` composes `FunctionExit` and `Token` visitors in the token visitor pipeline.

## Implementation

- `CustomAtRuleTransformer::parseComponentValue()` now parses standalone percentages before length-like dimensions, producing `['type' => 'token', 'value' => ['type' => 'percentage', ...]]`.
- `CustomAtRuleTransformer::parseSingleFunctionArgumentValue()` now does the same for `FunctionExit` arguments.
- Added a focused regression covering `@plugin 25% theme(50%);` with a percentage `Token` visitor and a `FunctionExit` visitor that returns its percentage argument for visitor revisiting.
- Added `wordpress-custom-at-rule-percentage-prelude.php` as a build-free WordPress custom at-rule smoke.

## Verification

- Pre-change probe:
  - `php -r 'require "tools/bootstrap.php"; use PortLibs\\LightningCSS\\CustomAtRuleTransformer; ... @tokens 25% theme(50%) ...'`
  - Output showed `["function:length","rule:25% 50%"]` and a prelude AST containing `length` nodes with unit `%`.
- PHP lint:
  - `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`
  - `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-percentage-prelude.php`
- Focused test:
  - `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `1 test files, 317 assertions, 0 failures`
- Full lane check:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6660 assertions, 0 failures`
- Example smoke:
  - `php lanes/lightningcss/examples/wordpress-custom-at-rule-percentage-prelude.php --self-test`
  - `OK`
- Diff hygiene:
  - `git diff --check -- lanes/lightningcss`
  - no output

## Non-overlap

This slice does not touch bundle/import graph resolution, source maps, CSS Modules, CSSOM read/write behavior, media-query parsing, escaped custom at-rule names, env/var visitor traversal, returned rule arrays, style-block custom parser bodies, or target prefixing. It is limited to untyped percentage token parsing in custom-at-rule universal preludes and function-exit arguments.

## Dependency Closure

No new support component is needed. The existing PHP custom at-rule parser, visitor composition, token serializer, and WordPress example harness are reused.
