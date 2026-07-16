# SELECT Window Frame Exclude Current Next31

2026-05-27 isolated slice `yield-sqlite-select-window-frame-exclude-current-next31`.

## Behavior

- Adds focused parser/executor coverage for SQLite aggregate window frames using `ROWS`, `RANGE`, and `GROUPS` with `BETWEEN CURRENT ROW AND N FOLLOWING EXCLUDE CURRENT ROW`.
- Covers `count(*)`, `count(expr)`, `sum()`, and `group_concat()` over copied `wp_options` rowsets, including peer groups, partitions, filtered source rows, final `LIMIT`/`OFFSET`, direct query-plan execution, and malformed framed ranking guards.
- Adds a Application smoke showing following-row option-byte previews and peer-group option-name summaries without requiring `ext/sqlite`.

## Verification

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectWindowFrameExcludeCurrentNext31Test.php
```

Verified focused movement: 64 new PASS lines in a new lane-scoped test file.

Example smoke:

```sh
php lanes/libsqlite/examples/application-select-window-frame-exclude-current-next31.php
```

## Non-overlap

This slice avoids accepted JSON table cursor/source/hidden/visible constraints, B-tree page move/root collapse/interior merge/overflow freelist release, WAL byte truncation/checkpoint/savepoint/rollback application, VFS lock/file-writer/sync/rollback-journal/super-journal clusters, SELECT SQL subquery/comma-LIMIT/GROUP BY/expression ORDER BY/JOIN text, scalar operator, Unicode GLOB, JSON aggregate window regression, and window FILTER aggregate current-next21 surfaces. It is limited to parser-level aggregate window frame `EXCLUDE CURRENT ROW` behavior for current-to-following frames.

## Dependency Closure

No new support component is needed. The slice reuses existing `SQLiteSelectSql`, `SQLiteSelectQuery`, and `SQLiteWindowFunction` parser/executor primitives.
