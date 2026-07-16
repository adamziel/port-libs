# Trigger Recursive View UPSERT Current Source Next242

## Behavior

Adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext242Plan`, a current-source statement-epoch fence layered after the existing next239 recursive view UPSERT target receipts.

The new layer derives statement receipts from the current source UPSERT rows, statement epoch, view program, trigger program, schema cookie, SQL hash, next239 target receipt, RETURNING payload, ordinal, and trigger source alias. Next-source rows stay held when acknowledgements are missing, unexpected, reordered, or bound to a stale statement epoch. This models the SQLite edge where an INSTEAD OF view trigger UPSERT must not release a next prepared source after the current view/trigger program has been invalidated or reparsed.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext242Test.php`
  - `1 test files, 83 assertions, 0 failures`
  - 83 PASS lines
- `php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next242.php`
  - `application-trigger-recursive-view-upsert-current-source-next242 self-test passed`

## Non-Overlap

This slice does not repeat accepted next238 resume receipts or next239 UPSERT target receipts. It adds only the later statement-epoch/source-program fence for current recursive view UPSERT rows. It also avoids recursive view RETURNING, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters.

## Dependency Closure

No new support component is needed. The patch reuses the existing native recursive view UPSERT current-source target receipt chain and adds a lane-local statement-epoch admission gate.
