# trigger-recursive-view-upsert-current-source-next253

Status: focused PHP behavior growth for recursive `INSTEAD OF` view UPSERT current-source publication.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext253Plan`, layered after the accepted next250 rowid-provenance gate. Current recursive view UPSERT `RETURNING` rows must be materialized through the current view/trigger cookies, materialization cursor, selected projection columns, and ordered materialization receipts before attempted next-source rows can publish.

Application path: `application-trigger-recursive-view-upsert-current-source-next253.php` models a copied `wp_options` recursive import view where `siteurl` and `current_plugin` UPSERTs recursively materialize `blogdescription_child` and `template_child` rows. The next-source `home` and `next_plugin` rows stay held until the current materialized view receipts are acknowledged.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext253Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext253Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next253.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext253Test.php`
  - `1 test files, 72 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next253.php`
  - `application-trigger-recursive-view-upsert-current-source-next253 self-test passed`

Expected dashboard movement: `phpPass +72`, from `133054` to `133126`. Mapped upstream coverage remains `669 / 1589`; this is current-source PHP behavior over existing trigger/view/UPSERT inventory rather than a fresh manifest-backed upstream row.

Non-overlap: avoids accepted next250 rowid-provenance receipts, next247 statement sequence, next246 conflict images, next244 commit watermarks, next241 close/generation, recursive view RETURNING-only clusters, deferred FK/RETURNING clusters, WAL/VFS, JSON table, planner, encoding, B-tree, and suite evidence surfaces. The added behavior is specifically the current-source materialized view projection receipt fence for recursive view UPSERT rows.

Dependency closure: no new support component is needed; this reuses native recursive view UPSERT current-source publication and adds lane-local materialization receipt validation.
