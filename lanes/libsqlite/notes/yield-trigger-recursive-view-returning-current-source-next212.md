# Trigger Recursive View RETURNING Current Source Next212

## Behavior

Adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext212Plan`, a bounded current-source yield gate layered after the accepted next209 drain-watermark behavior. The new gate records ordered trigger-yield receipts for current-source recursive view RETURNING rows and keeps attempted next-source RETURNING rows held until the current source has yielded in order.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext212Test.php`
  - `1 test files, 82 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next212.php`
  - `application-trigger-recursive-view-returning-current-source-next212 self-test passed`
- PHP lint for changed PHP files passes.
- `git diff --check -- lanes/libsqlite` passes.

## Non-Overlap

This slice avoids accepted next157-next209 trigger recursive view RETURNING surfaces by adding only the ordered current-source trigger-yield receipt gate after next209 drain watermarks. It does not touch row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, or B-tree clusters.

## Dependency Closure

No new support component is needed. The patch reuses native PHP recursive view RETURNING planning and current-source drain metadata already present in the lane.

## Next

The integrator can accept this as focused PHP PASS growth only; mapped upstream coverage remains unchanged because no fresh manifest-backed upstream inventory row is claimed.
