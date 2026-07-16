# trigger-recursive-view-returning-current-source-next206

Adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext206Plan`, a current-source yield watermark fence for recursive `INSTEAD OF` view-trigger `RETURNING` rows.

The slice builds on accepted next203 generation receipts. It does not change row production. Instead, it derives stable batch keys from the current-source rows, source token, cursor, and statement token, then blocks attempted next-source rows until the expected and acknowledged current-source watermark match and the current row count is stable.

Application path: copied `wp_options` import previews can now distinguish a fully yielded recursive current-source RETURNING batch from a stale cursor/watermark before exposing rows from a migrated next view/trigger source.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext206Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext206Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next206.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext206Test.php`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next206.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted next203 generation handoff, next196 recursive child drain, next195 receipt fences, next191 fingerprint fencing, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, and B-tree clusters. The new surface is the watermark that confirms current-source RETURNING rows have actually yielded before next-source rows become visible.

Dependency closure: no new support component is needed; this reuses native recursive view trigger RETURNING plans and adds a bounded yield watermark admission layer.
