# trigger-recursive-view-upsert-current-source-next244

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger UPSERT current-source handoff.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext244Plan`. It composes the accepted next241 current-source UPSERT close-seal handoff and adds a statement-level commit watermark gate. Next-source rows are not published until current UPSERT rows acknowledge deterministic commit receipts tied to the statement id, current view cookie, trigger cookie, watermark, and per-row close receipts.

WordPress path: copied `wp_options` imports through an `INSTEAD OF` recursive view trigger can finish current-source UPSERT statement commit admission before exposing rows from the staged next source. The smoke is `wordpress-trigger-recursive-view-upsert-current-source-next244.php`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext244Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext244Test.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next244.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext244Test.php`
  - `1 test files, 100 assertions, 0 failures`
  - `100` focused PASS lines
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next244.php`
  - `wordpress-trigger-recursive-view-upsert-current-source-next244 self-test passed`

Expected dashboard movement: `phpPass +100` from focused passing test lines. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped trigger/view/UPSERT inventory rather than a newly hydrated upstream row.

Non-overlap: adds statement-level UPSERT commit watermark admission after accepted next241 current-source close seals. It avoids next241 close-seal duplication, accepted trigger recursive/view RETURNING cursor/ticket/generation surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters.

Dependency closure: no new support component is needed. This reuses lane-local recursive view-trigger RETURNING and UPSERT close-seal behavior and adds a bounded native PHP statement commit watermark gate.
