# trigger-recursive-view-returning-snapshot-ack

Status: focused consolidation for recursive `INSTEAD OF` view-trigger
`RETURNING` rows at the current-source snapshot boundary.

This slice removes the numbered production entry method and private helper
names for the snapshot acknowledgement layer in
`SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan`. The canonical
entry point is now `currentReturningSnapshotAcknowledgement()`, with
descriptive private helpers for snapshot acknowledgements, row tagging,
blocked reasons, status, and token validation. Payload keys remain stable for
the accepted behavior assertions.

Application path: `application-trigger-recursive-view-returning-snapshot-ack.php`
models a copied `wp_options` recursive import view where plugin DDL changes the
next view/trigger source while current `RETURNING` rows are still visible.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningSnapshotAcknowledgementTest.php`
- Result: `1 test files, 95 assertions, 0 failures`
- PASS-line delta: `+95`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-snapshot-ack.php`
- Result: `application-trigger-recursive-view-returning-snapshot-ack self-test passed`

Dashboard delta: no `phpPass` or mapped-coverage counter change; this is a
production-method consolidation pass over already accepted behavior.

Dependency closure: no new support component is needed. The slice reuses the
lane-local recursive view trigger, RETURNING, current-source epoch, and next224
source-seal machinery.

Non-overlap: avoids accepted trigger behavior changes, row-value RETURNING
savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse,
WAL/VFS, JSON table, planner, encoding, and B-tree clusters. The changed
surface is only the direct trigger/RETURNING snapshot acknowledgement method
family and its direct test/example references.
