# real-upstream-corpus-expression-affinity-dynamic-20260531T065044Z-0

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T065044Z-0`

Base accepted HEAD: `598504695c988ec41a0063207004e700089f5af7`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Covered upstream `expr.test` `expr-7.2` through `expr-7.74` WHERE expression row-selection predicates.

## Changes

- Added `SQLiteRealUpstreamExpressionAffinityExpr7WhereDynamic20260531T065044ZTest.php`.
- The test builds a sqlite3 oracle for 63 real upstream `expr-7.*` predicate shapes across 30 shifted dynamic tables, then verifies parser-level `SQLiteSelectSql` row selection.
- Fixed `SQLiteSelectSql` parsing for postfix `ISNULL` / `NOTNULL` and scalar `LIKE(pattern,value)` / `GLOB(pattern,value)` calls in WHERE truth contexts.

## Focused Count

- New focused TestRunner PASS cases: `1892`.
- Focused assertions: `3787`.

## Non-Overlap

This owns the parser-level `expr.test` `expr-7.2..7.74` WHERE row-selection family for this session. It avoids accepted expression ORDER BY, e_expr arithmetic/result-storage, BETWEEN, IN-list, LIKE/GLOB exact scalar predicate, CASE evaluation, likely()/likelihood(), expr2 truthiness, affinity2/affinity3/types2/types3, date-affinity, JSON, WAL, VFS, B-tree, PRAGMA, trigger/FK, row-value, and source-neutral cleanup batches.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityExpr7WhereDynamic20260531T065044ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityExpr7WhereDynamic20260531T065044ZTest.php`
  - `1 test files, 3787 assertions, 0 failures`
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing `SQLiteSelectSql` parser/executor, `SQLiteSelectPredicate`, `SQLiteCoreScalarFunction` LIKE/GLOB helpers, and local sqlite3 oracle comparison.
