# yield-sqlite-trigger-returning-upsert-view-current-next52

Status: focused PHP corpus growth for view-trigger UPSERT RETURNING current/next
yield streams.

Behavior added:

- `SQLiteTriggerReturningUpsertViewCurrentNextPlan` resolves an `INSTEAD OF`
  trigger on a view, projects view rows into the underlying table shape, and
  executes the existing native UPSERT/trigger/FK yield executor one view row at
  a time.
- Each view input row records a yield-stream row with the view row, projected
  incoming table row, current matching row before the trigger body, next row
  after UPSERT plus before/after trigger effects, RETURNING payload, event, and
  skipped/changed status.
- `DO UPDATE WHERE` skips preserve the attempted view row but keep RETURNING
  null and next equal to current.
- Statement rollback restores parent/child rows to the current savepoint image,
  suppresses committed RETURNING rows, and keeps prior successful yield-stream
  diagnostics for the failing statement.
- Deferred FK violations remain visible in the yield stream and child rows for
  the outer transaction check.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningUpsertViewCurrentNext52Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 67 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-trigger-returning-upsert-view-current-next52.php
```

Non-overlap:

This avoids accepted view UPSERT RETURNING savepoint current-next49, view
trigger RETURNING current-next48, standalone UPSERT trigger/FK yield
current-next23, recursive view RETURNING current-next37, WAL/VFS savepoint
application, JSON table, B-tree, planner, and status-only clusters. The new
surface is current/next row-image yield diagnostics for view-trigger UPSERT
RETURNING streams.

Dependency closure:

No new support component is needed. This reuses lane-local schema trigger
resolution and native PHP UPSERT trigger/FK yield execution; no ext/sqlite,
upstream binary, network, or provider secret is required.
