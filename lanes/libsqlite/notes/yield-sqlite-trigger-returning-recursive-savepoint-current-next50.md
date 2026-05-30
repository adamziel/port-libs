# Trigger RETURNING recursive savepoint current-next50

## Delta

- Added `SQLiteRecursiveTriggerReturningSavepointPlan` for bounded recursive
  INSERT trigger execution with RETURNING projection and current/next yield
  diagnostics over an existing savepoint image.
- The plan reuses the accepted recursive trigger conflict/savepoint behavior,
  then projects RETURNING rows only for committed changed rows while preserving
  attempted-yield diagnostics for savepoint rollback, ignored conflicts, and
  replacement conflicts.
- Added the Application smoke
  `examples/application-trigger-returning-recursive-savepoint-current-next50.php`
  for copied `wp_options` import rows that recursively create plugin seed
  descendants under a savepoint without requiring ext/sqlite.

## Focused evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningRecursiveSavepointCurrentNext50Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 58 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +1 for the new focused PHP test file
(`17920 -> 17921`). Mapped upstream denominator is unchanged because this is a
focused native behavior slice, not a newly hydrated upstream Tcl inventory row.

## Non-overlap

Avoids accepted UPSERT RETURNING savepoint rollback, recursive view RETURNING,
trigger/FK RETURNING, savepoint page-image rollback, WAL byte truncation, VFS
rollback/savepoint application, and batch38 trigger/upsert RETURNING savepoint
clusters. This slice is specifically recursive trigger INSERT RETURNING yield
diagnostics across current savepoint rollback/current-next rowid state.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP
recursive trigger conflict/savepoint primitives and adds a bounded projection
wrapper.
