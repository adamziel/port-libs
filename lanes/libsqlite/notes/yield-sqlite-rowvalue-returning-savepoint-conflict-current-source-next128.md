# rowvalue-returning-savepoint-conflict-current-source-next128

## Behavior

- Added bounded `UPDATE OR IGNORE` / `UPDATE OR REPLACE` / `UPDATE OR ROLLBACK`
  parsing and unique-conflict application to `SQLiteUpdateDeleteReturningSql`
  when callers provide unique constraints.
- `OR IGNORE` restores the attempted row to its pre-update image and suppresses
  its `RETURNING` row. `OR REPLACE` deletes conflicting peer rows and yields the
  updated row. `OR ABORT` / `OR FAIL` / `OR ROLLBACK` raise a conflict error for
  savepoint wrappers to roll back.
- Added a next128 savepoint wrapper that records current-source tables,
  attempted next-source tables, yielded/attempted RETURNING streams, ignored
  rows, replacement deletes, conflict metadata, and rollback status for
  row-value selected Application option imports.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueReturningSavepointConflictCurrentSourceNext128Test.php`
  - `1 test files, 55 assertions, 0 failures`
- Application smoke:
  - `php lanes/libsqlite/examples/application-rowvalue-returning-savepoint-conflict-current-source-next128.php --self-test`

## Non-Overlap

This slice avoids accepted next117/next125 row-value UPDATE/DELETE RETURNING
predicate and assignment coverage, accepted next126 row-value savepoint
rollback coverage, and accepted trigger recursive UPSERT RETURNING behavior.
The new behavior is the combined `UPDATE OR ...` unique-conflict outcome for
row-value selected UPDATE RETURNING statements inside savepoint-style
current-source/next-source tracking.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP
row-value UPDATE/DELETE RETURNING execution, unique-key checks, and savepoint
current-source rollback bookkeeping already present in the libsqlite lane.
