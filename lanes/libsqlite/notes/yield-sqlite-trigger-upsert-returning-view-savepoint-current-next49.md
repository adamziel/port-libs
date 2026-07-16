# yield-sqlite-trigger-upsert-returning-view-savepoint-current-next49

Status: focused PHP corpus growth for `INSTEAD OF INSERT` view trigger rows that
drive UPSERT RETURNING execution inside the current savepoint.

Behavior added:

- `SQLiteViewUpsertReturningSavepointPlan` resolves a schema trigger and
  requires a resolved `INSTEAD OF` trigger on a view before applying mapped view
  rows to the underlying UPSERT executor.
- View rows are projected through explicit view-column to table-column mapping,
  preserving attempted view rows and the underlying incoming rows for current
  and next diagnostics.
- Successful view rows expose committed RETURNING rows plus view-tagged
  RETURNING evidence; skipped `DO UPDATE WHERE` rows remain attempted yields
  without RETURNING output.
- Immediate trigger/FK failure rolls the underlying parent and child rows back
  to the current savepoint image, suppresses committed RETURNING rows, and keeps
  prior attempted view-returning evidence as diagnostics.
- Deferred FK violations stay visible for the outer transaction check while the
  current savepoint remains active.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteViewUpsertReturningSavepointCurrentNext49Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 59 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-view-upsert-returning-savepoint-current-next49.php
```

Non-overlap:

This avoids accepted standalone UPSERT RETURNING savepoint current-next38,
UPSERT trigger/FK yield current-next23, recursive view RETURNING current-next37,
view trigger name-resolution edges, trigger recursive savepoint UPSERT
current-next27, VFS/WAL savepoint application, JSON table, B-tree, planner, and
status-only clusters. The new surface is their uncovered intersection:
parser/schema-resolved view trigger rows mapped into an underlying UPSERT
RETURNING statement under a current savepoint.

Dependency closure:

No new support component is needed. This reuses lane-local schema trigger
resolution and the existing native PHP UPSERT RETURNING savepoint executor; no
ext/sqlite, upstream binary, network, or provider secret is required.
