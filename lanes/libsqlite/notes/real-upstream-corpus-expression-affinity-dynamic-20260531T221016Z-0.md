# Real Upstream Corpus Expression Affinity Dynamic 20260531T221016Z 0

## Source Truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/existsexpr2.test`
- Ported sections: `do_execsql_test 1.1`, `1.3`, `2.1`, and `2.2`
- Behavior: correlated `EXISTS` row admission, `IN`-list correlated subquery filtering, `WITHOUT ROWID` source rows, and index-shaped NUMERIC/TEXT affinity predicates.

## Patch

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExistsExpr2Real20260531T221016ZTest.php`.
- The test builds 200 source-neutral dynamic datasets and exercises 5 real upstream case shapes per dataset.
- Focused growth: 1001 TestRunner PASS cases and 3008 behavior/source assertions.
- Oracle: local `sqlite3 -batch :memory:` row signatures generated from per-case SQL setup.

## Non-Overlap

This slice owns `existsexpr2.test` correlated `EXISTS` row admission and index-shaped expression-affinity behavior. It avoids accepted `e_expr` scalar `EXISTS` result checks, `existsexpr.test` composite `EXISTS`, `expr-7` WHERE matrices, parser-level subquery text dispatch, CASE, LIKE/GLOB, CAST, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup batches.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExistsExpr2Real20260531T221016ZTest.php` - passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExistsExpr2Real20260531T221016ZTest.php` - passed, `1 test files, 3008 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The slice reuses `SQLiteSelectSql` correlated `EXISTS`, `IN`-list predicate dispatch, comparison affinity metadata on row arrays, `SQLiteRealExpressionAffinityCorpusPlan` row-signature helpers, and the local `sqlite3` oracle.
