# trigger-recursive-view-returning-current-source-next209

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
`RETURNING` rows at the current-source to next-source boundary.

## Behavior

- Adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext209Plan`.
- Extends the accepted next203 generation handoff with current-source drain
  watermarks derived from current recursive `RETURNING` rows, the current view
  cookie, and the current trigger cookie.
- Next-source `RETURNING` rows stay held when current drain watermarks are
  missing, unexpected, or tied to stale view/trigger cookies, even if next203
  generation receipts are otherwise valid.
- The current-source rows remain visible while attempted next-source rows are
  quarantined for reprepare/debug evidence.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext209Test.php`
  - `1 test files, 85 assertions, 0 failures`
  - 85 PASS lines
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next209.php`
  - `application-trigger-recursive-view-returning-current-source-next209 self-test passed`

## Non-Overlap

This avoids accepted and queued recursive view trigger `RETURNING` surfaces
through next203, including current-generation receipt fencing, child-row drain,
checkpoint handoff, reset-barrier visibility, view-trigger savepoint rollback,
DML trigger `RETURNING` conflict handling, row-value `RETURNING` savepoints,
schema view/trigger reparse, deferred FK trigger behavior, and all WAL/VFS,
JSON table, planner, encoding, PRAGMA, and B-tree clusters. The new surface is
specifically the current-source drain watermark that must be acknowledged before
the next view source can publish its `RETURNING` rows.

## Dependency Closure

No new support component is needed. This reuses lane-local recursive
view-trigger `RETURNING` row arrays plus next203 current-generation handoff
metadata.
