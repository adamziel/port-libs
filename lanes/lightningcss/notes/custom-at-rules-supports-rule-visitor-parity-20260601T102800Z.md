# LightningCSS Custom At-Rules Supports Rule Visitor Parity - 2026-06-01 10:28 UTC

## Slice

- Lane: `lightningcss`
- Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T102800Z`
- Accepted base: `9fdbbaf081786bb1d6389d15e519a76f8a24a31c`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted files:
  - `node/index.d.ts`: `Visitor` exposes mapped known-rule visitors for every `Rule['type']`, including `Rule.supports` and `RuleExit.supports`.
  - `node/test/visitor.test.mjs`: existing supports-rule returned-rule and supports-condition visitor behavior establishes the expected AST shape for `supports` rules with `condition`, `rules`, and `loc`.

## Implementation

- Added native `Rule.supports` and `RuleExit.supports` callbacks in `CustomAtRuleTransformer`.
- `@supports` now follows the same visitor lifecycle as native `@media`: direct known-rule enter visitor, child traversal, known-rule exit visitor, and generic `Rule` / `RuleExit` fallback handling.
- Composed visitors now route `Rule.supports` and `RuleExit.supports` through the same replacement normalization used for `Rule.media`.
- Added `wordpress-custom-at-rule-supports-visitor.php` to model a block CSS supports gate whose child declarations are visited before the supports exit visitor rewrites the condition.

## Verification

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-supports-visitor.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> `1 test files, 371 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-supports-visitor.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7408 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

## Coverage Delta

- Full lane PHP assertions move from `7402` to `7408` (`+6`) on this isolated worktree.
- Conservative upstream mapped coverage remains `2369 / 3532`; this deepens the already represented Node visitor/custom-at-rule cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. The patch reuses the native `CustomAtRuleTransformer`, returned-rule serializer, `SupportsCondition` parser/visitor, declaration value visitors, and lane-local WordPress smoke. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is introduced.

## Non-Overlap

This does not repeat accepted custom at-rule declaration-list/mixin/rule-list parser behavior, returned supports-rule objects from style/custom visitors, `SupportsCondition` enter/exit visitor traversal, media-rule visitors, raw Function returns, token-array visitor behavior, source-map, CSS Modules, bundle/import graph, CSSOM, property-value, media-query, or target-prefixing slices. The change is limited to direct known-rule `supports` enter/exit visitor parity.

## Follow-Up

Remaining custom at-rule visitor work should stay on non-overlapping mapped-rule visitor types or other high-priority LightningCSS gaps such as source maps, CSS Modules, bundle/import graph, media-query recovery, property values, CSSOM, and target-prefixing.
