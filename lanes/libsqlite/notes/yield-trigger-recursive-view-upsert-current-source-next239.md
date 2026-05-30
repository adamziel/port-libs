# Trigger Recursive View UPSERT Current Source Next239

## Behavior

Adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext239Plan`, a bounded current-source UPSERT target admission layer on top of the accepted next231 recursive view RETURNING cursor-close handoff.

The new layer derives per-row target receipts from the current source rows, UPSERT target, policy, cursor, generation, next231 close receipt, RETURNING payload, and trigger source alias. Subsequent next-source rows remain fenced until the current-source UPSERT target receipts are acknowledged, ordered when required, and bound to the expected generation.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext239Test.php`
  - `1 test files, 79 assertions, 0 failures`
  - 79 PASS lines
- `php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next239.php`
  - `application-trigger-recursive-view-upsert-current-source-next239 self-test passed`

## Non-Overlap

This slice does not repeat accepted next203-next231 recursive view RETURNING generation, drain, yield, epoch, ticket, reset, following-current, or cursor-close behavior. It adds only the next239 UPSERT target receipt gate after next231. It also avoids row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters.

## Dependency Closure

No new support component is needed. The patch reuses the existing native recursive view RETURNING plans and adds a lane-local current-source UPSERT target receipt planner.
