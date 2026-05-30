# Recursive UPSERT Conflict Yield Current Next30

## Behavior

Adds `SQLiteRecursiveUpsertConflictYieldPlan`, a bounded native PHP executor for
row-by-row `INSERT ... ON CONFLICT DO UPDATE` work where fired triggers can
recursively UPSERT into the current target table and yield statement/trigger
row effects in SQLite statement order.

Covered behavior:

- current-row conflict updates see changes made earlier in the same statement;
- AFTER UPDATE triggers recursively UPSERT conflicting current rows;
- `recursive_triggers = off` suppresses recursive body DML while preserving the
  firing diagnostic;
- `ON CONFLICT IGNORE` skips current conflicts without firing triggers;
- NULL values in a unique target do not conflict;
- depth limits, malformed trigger rows, unsupported conflict actions, bad WHEN
  operators, missing columns, and INSERT-time `OLD` references are rejected.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRecursiveUpsertConflictYieldCurrentNext30Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 57 assertions, 0 failures
```

Focused PASS-line delta: `+57` new `TestRunner` PASS cases.

Lane status delta: `phpPass` `10028 -> 10085`, `phpFail` remains `0`.

Mapped upstream denominator delta: none. This is a current-source focused
behavior slice and does not claim a fresh upstream Tcl inventory unit.

## Application Smoke

`lanes/libsqlite/examples/application-recursive-upsert-conflict-yield-current-next30.php`
previews copied `wp_options` imports where an UPSERT conflict on `siteurl`
recursively UPSERTs the current `home` row, yielding both statement and trigger
rows without requiring `ext/sqlite`.

## Non-Overlap

This avoids accepted UPSERT trigger/FK yield Next23, recursive trigger depth,
recursive trigger savepoint, savepoint rollback, UPSERT RETURNING,
SELECT/JSON/B-tree/VFS/WAL clusters, and recent batch25 trigger-depth coverage.
The new surface is recursive current-table UPSERT conflict yield behavior, not
foreign-key timing, RETURNING projection, savepoint rollback, or depth-only
trigger diagnostics.

## Dependency Closure

No new support component is needed. The slice reuses lane-local row-array
execution and focused PHP test infrastructure.
