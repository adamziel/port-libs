# real-upstream-corpus-expression-affinity-dynamic-like-glob-dispatch-20260531T153924Z

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T153924Z-0`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Upstream scenarios:
  - `e_expr-15.1.1` through `e_expr-15.1.4`: LIKE calls application-defined `like(Y,X)` or `like(Y,X,Z)`.
  - `e_expr-17.3.1` through `e_expr-17.3.4`: GLOB calls application-defined `glob(Y,X)`.

## Change

- Updated `SQLiteSelectPredicate` so `LIKE`, `NOT LIKE`, `GLOB`, and `NOT GLOB` accept an optional predicate `callback`, mirroring the existing `MATCH`/`REGEXP` callback path.
- Preserves SQLite operand order:
  - `LIKE`: `callback(pattern, value)` or `callback(pattern, value, escape)`.
  - `GLOB`: `callback(pattern, value)`.
- Preserves SQLite truth coercion, `NOT` inversion, non-callable callback diagnostics, and existing NULL short-circuit behavior.
- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicLikeGlobFunctionDispatch20260531T153924ZTest.php` with `1602` focused PASS cases and `3224` focused assertions.

Red-first probe before the source edit:

```text
php -r 'require "tools/bootstrap.php"; use PortLibs\LibSqlite\SQLiteSelectPredicate; $calls=[]; $result=SQLiteSelectPredicate::evaluate([], ["operator"=>"LIKE", "left"=>["type"=>"literal","value"=>"abc"], "right"=>["type"=>"literal","value"=>"def"], "callback"=>static function(...$args) use (&$calls){$calls[]=$args; return true;}]); var_export([$result,$calls]); echo "\n";'
array (
  0 => false,
  1 =>
  array (
  ),
)
```

After the patch, the same behavior is covered by the focused corpus file and returns callback truth with `like(pattern,value)` order.

## Non-Overlap

This slice owns only application-defined LIKE/GLOB function override dispatch from `e_expr.test`.
It does not repeat accepted built-in LIKE/GLOB pattern truth, Unicode GLOB ranges, MATCH/REGEXP callback dispatch, CASE/iif, CAST, scalar subqueries, affinity3 REAL joins, JSON, WAL, VFS, B-tree, PRAGMA, trigger, or source-neutral cleanup batches.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteSelectPredicate.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSelectPredicate.php

php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicLikeGlobFunctionDispatch20260531T153924ZTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicLikeGlobFunctionDispatch20260531T153924ZTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicLikeGlobFunctionDispatch20260531T153924ZTest.php
1 test files, 3224 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicLikeGlobFunctionDispatch20260531T153924ZTest.php
2 test files, 3227 assertions, 0 failures

git diff --check -- lanes/libsqlite
passed
```

## Dependency Closure

No new support component is needed. This reuses `SQLiteSelectPredicate`, existing SQLite truth coercion, and the hydrated upstream `e_expr.test` source-truth file.

## Expected Dashboard Movement

- Focused PHP PASS cases: `+1602`.
- Focused assertions: `+3224`.
- Expected selected libsqlite evidence: `3020960 -> 3022562 pass / 0 fail`.
- Mapped coverage: unchanged at `1589 / 1589`; the denominator is already fully mapped.
