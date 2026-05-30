# trigger-recursive-view-returning-current-source-next187

## Behavior

Adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext187Plan`, extending the accepted next184 recursive view trigger RETURNING checkpoint handoff with a current-source drain ticket. The ticket is derived from the acknowledged current checkpoint tokens, so a stale or mismatched ticket keeps attempted next-source RETURNING rows quarantined even when the base checkpoint handoff would otherwise expose them.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext187Test.php`
  - `1 test files, 59 assertions, 0 failures`
  - 59 PASS lines

## Application Smoke

- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next187.php`
  - `application-trigger-recursive-view-returning-current-source-next187 self-test passed`

## Non-Overlap

This slice builds on next184 checkpoint acknowledgement but does not repeat next183 rollback reset behavior, next184 checkpoint exposure, row-value RETURNING savepoint work, schema reparse trigger/view work, deferred FK trigger behavior, JSON table behavior, WAL/VFS pager durability, or B-tree current-source slices.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP recursive view trigger RETURNING rows, checkpoint tokens, and current-source handoff metadata.
