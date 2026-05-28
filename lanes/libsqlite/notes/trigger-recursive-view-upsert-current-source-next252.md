# trigger-recursive-view-upsert-current-source-next252

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
UPSERT current-source `DO UPDATE ... WHERE` decision receipts.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext252Plan`. It
wraps accepted next249 assignment receipts and adds a narrower gate for current
UPSERT predicate decisions. Next-source rows remain held until the current
where-token matches, every current predicate decision receipt is acknowledged,
no unexpected receipt appears, and optional `require true` admission accepts the
decision set.

WordPress path: `wordpress-trigger-recursive-view-upsert-current-source-next252.php`
models a copied `wp_options` recursive import view where current rows evaluate
their `DO UPDATE ... WHERE` decisions before plugin migration rows from the
next view source are published.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext252Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext252Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext252Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext252Test.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next252.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next252.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext252Test.php`
  - `1 test files, 77 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next252.php`
  - `wordpress-trigger-recursive-view-upsert-current-source-next252 self-test passed`

Expected dashboard movement: `phpPass +77` from focused lane-local PASS lines.
Mapped upstream coverage remains unchanged; this is current-source PHP behavior
over already mapped trigger/view/UPSERT inventory, not a newly hydrated upstream
row.

Non-overlap: avoids accepted next249 assignment receipt fencing, next246
conflict images, next240/next243 source and conflict-key fences, recursive view
RETURNING, row-value RETURNING, schema reparse, WAL/VFS, JSON table, planner,
encoding, B-tree, and suite-admission clusters. The new surface is specifically
current-source `DO UPDATE ... WHERE` decision receipt fencing after assignment
receipts are already complete.

Dependency closure: no new support component is needed; this reuses native
recursive view UPSERT rows, current-source assignment receipts, and existing
row-array RETURNING payloads.
