# Trigger Recursive View UPSERT Current Source Next246

## Behavior

Adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext246Plan`, a current-source conflict-image fence layered after the existing next243 recursive view UPSERT source-cookie fence.

The new layer derives deterministic receipts from each current recursive view UPSERT row, the conflict-key columns, old-row image, excluded/new values, and a current-source conflict-image token. Next-source rows stay held when receipts are missing, unexpected, reordered, or tied to a stale conflict-image token. This models the SQLite edge where an `INSTEAD OF` view trigger UPSERT must finish binding the current statement's old/excluded conflict images before a subsequent source can publish rows after a view or trigger source transition.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext246Test.php`
  - `1 test files, 81 assertions, 0 failures`
  - 81 PASS lines
- `php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next246.php`
  - `application-trigger-recursive-view-upsert-current-source-next246 self-test passed`

## Non-Overlap

This slice does not repeat accepted next240 conflict-key receipt admission, next242 statement-epoch fencing, or next243 source-cookie fencing. It adds only the later old/excluded conflict-image receipt gate for current recursive view UPSERT rows. It also avoids recursive view RETURNING, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters.

## Dependency Closure

No new support component is needed. The patch reuses the existing native recursive view UPSERT current-source chain and adds a lane-local conflict-image admission gate.
