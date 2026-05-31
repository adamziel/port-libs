# real-upstream-corpus-expression-affinity-dynamic-scalar-subquery-arity-20260531T113414Z

## Source Truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Upstream section: `e_expr-35.2.1` through `e_expr-35.2.6`
- Behavior: scalar subquery expressions that produce more than one visible column are rejected with SQLite's arity diagnostic, `sub-select returns N columns - expected 1`.

## Patch

- Updated `SQLiteSelectExpression::subqueryValue()` so scalar subquery arity checks ignore internal `__sqlite_*` metadata keys and report the upstream-style visible column count.
- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicScalarSubqueryArity20260531T113414ZTest.php` with 125 value rows across 8 expression forms for 1000 real upstream arity-rejection cases, plus sqlite3 oracle-message checks and source/non-overlap/dependency guard tests.
- The first red run of the initial shard failed the 1000 dynamic cases before the source fix; it exposed the generic `SQLite SELECT scalar subquery expression must return one column` message and one unsupported WHERE-wrapper form. The final matrix keeps the upstream e_expr-35.2 arity behavior in supported scalar expression contexts.

## Non-Overlap

This owns only `e_expr.test` scalar subquery arity rejection for multi-column scalar expressions. It avoids the accepted `e_expr-35.1` and `e_expr-36` valid scalar first-row/NULL behavior, EXISTS, IN subqueries, CASE/iif, LIKE/GLOB, CAST, REAL affinity, expression ORDER BY, grouped SELECT, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and row-value DML batches.

## Evidence

- Red-first before source fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicScalarSubqueryArity20260531T113414ZTest.php` -> `1 test files, 2017 assertions, 1000 failures`.
- `php -l lanes/libsqlite/src/SQLiteSelectExpression.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicScalarSubqueryArity20260531T113414ZTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicScalarSubqueryArity20260531T113414ZTest.php` -> `1 test files, 2017 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicScalarSubquery20260531T023054ZTest.php` -> `1 test files, 4009 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` -> `lane-status json ok`.
- `git diff --check -- lanes/libsqlite` -> passed with no output.

## Expected Movement

- New focused TestRunner PASS cases: +1002 (`1000` dynamic arity cases plus sqlite3/source guard cases).
- `phpPass`: `2900340 -> 2901342` when accepted.
- Mapped coverage: unchanged at `1589 / 1589`; this is behavior depth over an already mapped upstream script.
- Root harness: not run; isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses `SQLiteSelectSql` scalar-subquery execution and `SQLiteSelectExpression` visible-column arity validation.
