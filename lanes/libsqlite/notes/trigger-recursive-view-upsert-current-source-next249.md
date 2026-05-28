# trigger-recursive-view-upsert-current-source-next249

Status: focused current-source behavior growth for recursive `INSTEAD OF` view
UPSERT rows.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext249Plan`. It
wraps the accepted next246 conflict-image fence and adds a separate release
gate for current-source `DO UPDATE` assignment receipts. The next-source rows
remain held until the assignment token matches, every current assignment
receipt is acknowledged, no unexpected receipt appears, and the required order
is preserved unless unordered admission is explicitly requested.

WordPress path: `wordpress-trigger-recursive-view-upsert-current-source-next249.php`
models copied `wp_options` imports through a recursive view trigger. Current
`siteurl`/plugin rows recursively yield option updates, seal their assignment
receipts, and only then expose plugin migration rows from the next view source.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext249Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext249Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext249Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext249Test.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next249.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next249.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext249Test.php`
  - `1 test files, 79 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next249.php`
  - `wordpress-trigger-recursive-view-upsert-current-source-next249 self-test passed`

Expected dashboard movement: `phpPass +79` from the new focused test file.
Mapped upstream coverage remains `657 / 1589`; this is current-source PHP
behavior over already mapped trigger/view/UPSERT inventory, not a newly
hydrated upstream row.

Non-overlap: avoids accepted next240 conflict-key receipts, next242 statement
epoch, next243 source-cookie fencing, next246 old/excluded conflict-image
receipts, recursive view RETURNING, schema reparse, WAL/VFS, JSON table,
planner, encoding, B-tree, and suite-admission clusters. The new surface is
the current-source `DO UPDATE` assignment receipt fence after conflict images
have already been accepted.

Dependency closure: no new support component is needed; this reuses native
recursive view UPSERT current-source rows, conflict images, and existing
row-array RETURNING payloads.
