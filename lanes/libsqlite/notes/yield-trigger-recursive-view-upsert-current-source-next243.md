# trigger-recursive-view-upsert-current-source-next243

Status: focused PHP behavior growth for recursive `INSTEAD OF` view UPSERT current-source fencing.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext243Plan`, layered after the accepted next240 current UPSERT receipt admission. It models the remaining current-source boundary where the current view and trigger source cookies must still match the prepared source before attempted next-source rows are published. If a plugin migration reparses the view or its trigger after the current UPSERT receipts but before next-source drain, the current rows remain visible and next rows are held with explicit stale-cookie reasons.

WordPress path: `wordpress-trigger-recursive-view-upsert-current-source-next243.php` models copied `wp_options` imports through an autoloaded-options view trigger. The smoke proves next import rows are published only when current UPSERT receipts and view/trigger source cookies all match.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext243Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext243Test.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next243.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext243Test.php`
- Result: `1 test files, 73 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next243.php --self-test`
- Result: `wordpress-trigger-recursive-view-upsert-current-source-next243 self-test passed`

Dashboard delta: `phpPass +73`, from `122940` to `123013`. `benchmarkDenominator.mapped` remains `647 / 1589`; this is additional current-source PHP behavior over already mapped trigger/view/UPSERT inventory.

Non-overlap: this adds a source-cookie fence after next240 UPSERT receipt admission. It avoids accepted next240 trigger recursive view UPSERT behavior, next205/next231 recursive view RETURNING/cursor fencing, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters.

Dependency closure: no new support component is needed. The patch reuses lane-local recursive view UPSERT current-source plans and adds a bounded view/trigger source-cookie admission guard.

Next task: wire the next243 cookie fence into a broader prepared-statement executor once trigger bytecode execution owns view-trigger reprepare directly.
