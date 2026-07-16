# SELECT SQL Window Text Next15

## Scope

Adds bounded parser-level `SELECT ... OVER (...)` dispatch for row-array SQL execution. The slice covers `row_number`, `rank`, `dense_rank`, `percent_rank`, `cume_dist`, `ntile`, `lag`, `lead`, `first_value`, `last_value`, and `nth_value` with optional `PARTITION BY` and `ORDER BY`.

This is intentionally separate from accepted window helper primitives and JSON table window-ranking metadata: the new behavior wires window functions through `SQLiteSelectSql` / `SQLiteSelectQuery` text execution over copied `wp_options` rows, including WHERE-before-window filtering, DISTINCT, CTE sources, joined sources, and final ORDER BY/LIMIT/OFFSET.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectSqlWindowTextTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 52 assertions, 0 failures
47 PASS lines
```

Expected dashboard movement from this isolated worktree: `phpPass` increases by the verified PASS-line delta only, `4362 -> 4409`. No mapped upstream denominator change is claimed.

## Application Smoke

Command:

```bash
php lanes/libsqlite/examples/application-select-sql-window-text.php
```

The smoke emits copied `wp_options` rows ranked within `autoload` partitions and reports the previous option in each partition without requiring `ext/sqlite`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP primitives: `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectExpression`, `SQLiteSelectResult`, and `SQLiteWindowFunction`.

## Non-Overlap

Avoided accepted and queued clusters named by the supervisor: expression `ORDER BY`, `GROUP BY` / `HAVING`, correlated subqueries, JSON table SELECT source/cursor/hidden/visible constraints, VFS writer/sync/lock/rollback paths, B-tree page moves/root collapse/overflow freelist release, Unicode GLOB, and rollback-journal commit application.
