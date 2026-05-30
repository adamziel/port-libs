# Recursive CTE UNION cycle current next32

2026-05-27 isolated slice `yield-sqlite-select-recursive-union-cycle-current-next32`.

- Behavior: `SQLiteSelectSql::recursiveCteCycleTrace()` now exposes bounded
  current/next traversal diagnostics for parser-level `WITH RECURSIVE`
  `UNION` CTEs. The trace records the current row, queue before/after,
  generated rows, accepted next rows, skipped UNION duplicate cycle rows,
  LIMIT/OFFSET emission state, and dependency tags while reusing the native
  recursive CTE executor.
- Focused tests: `SQLiteRecursiveCteUnionCycleCurrentNext32Test.php` adds 54
  PASS cases for ascending/descending queue order, cycle de-duplication,
  depth-state rows that remain distinct, LIMIT/OFFSET emission, named and
  positional bind roots, ordinary CTE roots, Application `wp_options` joins and
  predicates, and malformed trace admission.
- Application smoke:
  `examples/application-recursive-cte-union-cycle-current-next32.php --self-test`
  reports copied `wp_options` option graph traversal through a cyclic recursive
  UNION CTE, visited/current ids, skipped cycle ids, and dependency tags
  without requiring `ext/sqlite`.
- Non-overlap: this avoids accepted recursive CTE materialization, queue
  ordering, LIMIT/OFFSET, batch26 materialized CTE, SELECT SQL subqueries,
  JSON table sources, VFS/WAL/B-tree, and trigger recursion clusters. The new
  surface is current/next cycle observability for UNION duplicate suppression
  inside the parser-level recursive CTE executor.
- Dependency closure: no new support component is needed. The slice reuses
  lane-local `SQLiteSelectSql`, `SQLiteSelectQuery`, row-array tables, and the
  existing recursive CTE executor.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRecursiveCteUnionCycleCurrentNext32Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 54 assertions, 0 failures

php lanes/libsqlite/examples/application-recursive-cte-union-cycle-current-next32.php --self-test
application-recursive-cte-union-cycle-current-next32 self-test passed
```
