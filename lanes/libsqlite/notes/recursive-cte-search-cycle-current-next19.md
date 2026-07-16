# Recursive CTE Search Cycle Current/Next19

- Added SQLite-style current-row queue execution for recursive CTEs instead of evaluating each recursive term against the whole frontier batch.
- Recursive-arm `ORDER BY` now controls queue extraction priority for bounded depth-first/breadth-first graph searches, including cyclic `UNION` and bounded `UNION ALL` traversals.
- Added `SQLiteRecursiveCteSearchCycleCurrentNext19Test.php` with 28 new PASS lines covering ordinal/column queue priority, FIFO ties, NULL ordering, cyclic graph convergence, outer joins/predicates/aggregates/compound SELECTs, and malformed recursive queue `ORDER BY` guards.
- Added `application-recursive-cte-search-cycle.php`, a copied `wp_options` dependency traversal smoke that joins recursive option dependency rows back to option metadata without ext/sqlite.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRecursiveCteCurrentSourceTest.php lanes/libsqlite/tests/SQLiteRecursiveCteCycleDmlEdgeTest.php lanes/libsqlite/tests/SQLiteRecursiveCteSearchCycleCurrentNext19Test.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 87 assertions, 0 failures
```

Status delta:

- `phpPass`: 6444 -> 6472 (+28 verified PASS lines from the new focused test file).
- `benchmarkDenominator.mapped`: unchanged; no new upstream manifest unit was admitted.
- Non-overlap: avoids accepted SELECT SQL subqueries, expression `ORDER BY`, grouped SELECT text, JSON table source/cursor/constraints, VFS/WAL/B-tree clusters, and older recursive CTE current-source/cycle tests by adding recursive queue priority/current-row traversal behavior.
- Dependency closure: no new support component needed; this reuses existing `SQLiteSelectSql`/`SQLiteSelectQuery` row-array execution.
