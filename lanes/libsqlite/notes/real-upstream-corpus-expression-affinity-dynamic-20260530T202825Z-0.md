# real-upstream-corpus-expression-affinity-dynamic-20260530T202825Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
  - `expr-1.*`: arithmetic expression operator dispatch for `+`, `-`, `*`,
    `/`, and `%`.

## Change

Added `SQLiteRealUpstreamExpressionArithmeticDynamicTest.php`, a real
upstream-backed dynamic shard with exactly 1,000 distinct focused TestRunner
operator cases plus one ownership/citation case.

The shard checks `quote()` and `typeof()` for a 10 x 10 integer literal matrix
across the five upstream arithmetic operators. Each case compares libsqlite's
`SQLiteSelectSql` constant-expression execution against the local `sqlite3`
oracle.

## Non-overlap

This slice does not repeat the accepted `e_expr-8` NULL comparison shard,
`types2` affinity matrix shards, `affinity2` / `affinity3` storage and join
shards, expression ORDER BY, grouped SELECT SQL text, or source-neutral cleanup
work. It owns only the real upstream `expr.test` `expr-1.*` arithmetic operator
family over integer literal pairs.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionArithmeticDynamicTest.php`
  - `1 test files, 1006 assertions, 0 failures`
  - 1,001 focused PASS lines

## Dependency closure

No new support component is needed. The slice reuses the existing
`SQLiteSelectSql` constant SELECT executor and the local `sqlite3` oracle path
already used by other real upstream corpus expression tests.
