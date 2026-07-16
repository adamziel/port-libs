# Trigger RETURNING Nested Savepoint Current/Next 68

## Behavior

Adds a bounded native PHP current/next planner for nested trigger `RETURNING`
savepoints:

- a child `INSERT ... RETURNING` savepoint released into the outer transaction
  preserves inserted rows and next `RETURNING` rows;
- a later child `UPDATE ... RETURNING` savepoint that is aborted by an AFTER
  trigger `RAISE(ROLLBACK)` preserves attempted current diagnostics but
  suppresses those rows from the next/committed `RETURNING` stream;
- rollback restores the child savepoint image while keeping previously
  released rows visible in the outer savepoint.

This is intentionally disjoint from the accepted batch64/batch65
trigger-RETURNING savepoint rollback/commit diagnostics, which model whole
statement rollback boundaries. This slice covers nested child RELEASE followed
by child ROLLBACK preservation.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningSavepointCurrentNext68Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 60 assertions, 0 failures
```

Local Application smoke:

```sh
php lanes/libsqlite/examples/application-trigger-returning-nested-savepoint-current-next68.php
```

Expected dashboard movement: `phpPass +60`, `phpFail` unchanged at `0`, mapped
upstream denominator unchanged because this is focused native behavior coverage
without a new hydrated upstream inventory row.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local PHP
row-array trigger/savepoint planning patterns and does not require ext/sqlite,
VFS support, or live provider state.

## Next

Continue trigger/savepoint work only if it proves a different SQL edge, such
as DELETE RETURNING nested savepoint behavior or parser-level execution wiring,
without repeating batch64/batch65 whole-statement trigger rollback coverage.
