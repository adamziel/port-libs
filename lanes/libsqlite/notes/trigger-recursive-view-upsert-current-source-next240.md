# trigger-recursive-view-upsert-current-source-next240

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger UPSERT current-source handoff.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext240Plan`. It composes the existing recursive view-trigger RETURNING current-source cursor-close handoff and adds a narrower UPSERT conflict-source gate: next-source rows are not published until current-source UPSERT conflict keys have deterministic receipts tied to the current view cookie, trigger cookie, cursor, and conflict columns.

Application path: copied `wp_options` imports through an `INSTEAD OF` recursive view trigger can acknowledge current-source UPSERT keys before exposing next-source rows from a changed view/trigger definition. The smoke is `application-trigger-recursive-view-upsert-current-source-next240.php`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext240Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext240Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next240.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext240Test.php`
  - `1 test files, 88 assertions, 0 failures`
  - `88` focused PASS lines
- `php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next240.php`
  - `application-trigger-recursive-view-upsert-current-source-next240 self-test passed`

Expected dashboard movement: `phpPass +88` from focused passing test lines. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped trigger/view/UPSERT inventory rather than a newly hydrated upstream row.

Non-overlap: avoids accepted trigger recursive/view RETURNING next157-next231 cursor, ticket, close, drain, epoch, and generation surfaces, accepted next237 trigger recursive/view UPSERT behavior mentioned by the live status, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters. The narrower surface is current-source UPSERT conflict-key admission after the current RETURNING cursor close.

Dependency closure: no new support component is needed. This reuses lane-local recursive view-trigger RETURNING handoff behavior and adds a bounded native PHP UPSERT conflict-source gate.
