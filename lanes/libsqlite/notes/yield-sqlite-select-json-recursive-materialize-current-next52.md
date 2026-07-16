# SELECT JSON Recursive Materialize Current/Next 52

## Behavior

- Adds recursive CTE emitted current/next queue materialization to `SQLiteSelectRecursiveJsonMaterialization`.
- Covers recursive `WITH RECURSIVE ... AS MATERIALIZED` JSON expansion where queue `ORDER BY`, `LIMIT`, and `OFFSET` affect emitted rows while the trace still records generated rows.
- Preserves SQLite `UNION` duplicate handling over the full recursive row: repeated option IDs reached through distinct route values remain distinct, while exact duplicate rows are skipped with `union-duplicate-cycle` trace evidence.
- Reuses existing parser-level `json_each()` / `json_tree()` execution and derived SELECT materialization; this patch does not duplicate accepted JSON source/cursor/hidden/visible-constraint work.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectJsonRecursiveMaterializeCurrentNext52Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 80 assertions, 0 failures
PASS_LINES=71
```

```text
php lanes/libsqlite/examples/application-select-json-recursive-materialize-current-next52.php --self-test
application-select-json-recursive-materialize-current-next52 self-test passed
```

## Dashboard Delta

- `phpPass`: `19277 -> 19348` (`+71` verified PASS lines).
- `benchmarkDenominator.mapped`: unchanged at `462 / 1589`; this is focused PHP behavior growth, not a new hydrated upstream inventory unit.

## Dependency Closure

No new support component is needed. The slice composes existing native PHP `SQLiteSelectSql`, `SQLiteJsonTablePlan`, JSONB, recursive CTE trace, and derived JSON index helpers.

## Non-Overlap

Avoids accepted batch49 recursive lateral JSON materialization by adding recursive queue current/next trace materialization and focused queue-order/limit/offset/UNION-cycle evidence. Also avoids accepted JSON table cursor, parser-level JSON table SELECT source, hidden/visible constraint pushdown, grouped SELECT text, expression ORDER BY, VFS, WAL, and B-tree clusters.
