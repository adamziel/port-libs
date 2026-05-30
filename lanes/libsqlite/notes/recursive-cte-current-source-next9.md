# Recursive CTE Current Source Next9

## Behavior

This slice adds bounded parser-level `WITH RECURSIVE` materialization for
single `anchor UNION [ALL] recursive-arm` CTE bodies in `SQLiteSelectSql`.
The recursive arm is evaluated against the current frontier rows, not the full
accumulated CTE table, so simple SQLite sequences such as:

```sql
WITH RECURSIVE seq(x) AS (
  VALUES (1)
  UNION ALL
  SELECT x + 1 FROM seq WHERE x < 5
)
SELECT x FROM seq
```

produce `1,2,3,4,5` exactly once and converge instead of repeatedly expanding
from already-consumed rows.

## Evidence

Focused test command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRecursiveCteCurrentSourceTest.php
```

Result:

```text
1 test files, 31 assertions, 0 failures
31 PASS lines
```

Smoke command:

```sh
php lanes/libsqlite/examples/application-select-recursive-cte-current-source.php
```

Result: copied `wp_options` rows are filtered through a recursive generated id
set and return `siteurl`, `home`, `blogname`, and `_transient_feed`.

## Non-Overlap

This avoids accepted SELECT SQL text, JOIN text, GROUP BY/HAVING text,
subquery, expression ORDER BY, JSON table, VFS, WAL, B-tree page move/root
collapse/overflow, and Unicode GLOB clusters. It specifically replaces the
older recursive-CTE rejection path and fixes the current-source/frontier
behavior needed for bounded recursive CTE execution.

## Dependency Closure

No new support component is required. The implementation reuses the existing
`SQLiteSelectSql`, `SQLiteSelectQuery`, expression, predicate, join, compound,
and VALUES clause executors.
