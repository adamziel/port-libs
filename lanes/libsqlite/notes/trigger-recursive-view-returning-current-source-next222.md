# trigger-recursive-view-returning-current-source-next222

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
`RETURNING` rows at the current/next source boundary.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext222Plan`.
It builds on the accepted next218 epoch handoff and requires a current-source
ticket tied to the current view source and trigger source before attempted
next-source `RETURNING` rows become visible. Current rows remain visible while
next rows are held for missing, unexpected, reversed, or stale source tickets.

Application path: `application-trigger-recursive-view-returning-current-source-next222.php`
models a copied `wp_options` import view whose recursive trigger yields current
`RETURNING` rows before plugin DDL changes the next view/trigger source.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext222Test.php`
- Result: `1 test files, 86 assertions, 0 failures` with 86 PASS lines.
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next222.php`
- Result: `application-trigger-recursive-view-returning-current-source-next222 self-test passed`.

Dashboard delta: update `phpPass` by the focused PASS-line delta verified for
this test file (`+86`, from `107451` to `107537`). `benchmarkDenominator.mapped`
is unchanged at `624 / 1589`; this is current-source PHP behavior over already
mapped trigger/view/RETURNING inventory, not a newly hydrated upstream row.

Dependency closure: no new support component is needed. The slice reuses
lane-local recursive view trigger, RETURNING, current-source drain/yield/epoch,
and Application row-array primitives.

Non-overlap: avoids accepted trigger recursive view RETURNING next157-next218
surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK
triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree
clusters. The narrower behavior is source-ticket admission after the accepted
next218 epoch handoff.
