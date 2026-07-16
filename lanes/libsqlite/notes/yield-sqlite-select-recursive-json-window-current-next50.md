# yield-sqlite-select-recursive-json-window-current-next50

## Behavior

- Implemented SQLite optional join-constraint behavior in `SQLiteSelectSql::consumeJoin()`.
- `JOIN` and `INNER JOIN` without `ON`/`USING` now execute as cartesian joins.
- `LEFT`/`FULL` joins without `ON`/`USING` now use an always-true predicate so left-join null extension remains available when the right side is empty.
- Focused coverage combines the behavior with recursive CTE current-row execution, dynamic `json_each()` traversal of copied `wp_options.option_value`, and SELECT window projection over the recursive result.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectRecursiveJsonWindowCurrentNext50Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 50 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-select-recursive-json-window-current-next50.php --self-test
```

Result:

```text
application-select-recursive-json-window-current-next50 self-test passed
```

## Status Delta

- `phpPass`: `17920 -> 17970` (`+50` verified focused PASS lines).
- `benchmarkDenominator.mapped`: unchanged. This maps focused PHP executor behavior only and does not claim a fresh upstream inventory unit.
- Root harness: not run; isolated micro-slice policy forbids no-argument root verification here.

## Non-Overlap

This slice avoids accepted parser-level JSON table source/cursor wiring, visible/hidden JSON constraints, JSON host joins, recursive CTE cycle-only coverage, grouped SELECT text, expression `ORDER BY`, WAL/VFS/B-tree apply clusters, and accepted window helper-only clusters. The new surface is optional JOIN constraint execution when that join is used inside recursive JSON traversal and then consumed by SELECT window projections.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP JSON table, recursive CTE, SELECT expression, and window executor components.
