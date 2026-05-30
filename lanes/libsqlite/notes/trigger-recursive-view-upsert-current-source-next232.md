# trigger-recursive-view-upsert-current-source-next232

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger UPSERT current-source admission.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext232Plan`, a current-source conflict-seal gate layered on the existing recursive view UPSERT executor. Current UPSERT rows, recursive trigger side effects, conflict old/new values, view source, and trigger-program token are sealed before a next source can publish rows. Missing, unexpected, reordered, stale-view, and stale-trigger acknowledgements keep the next source held while current rows remain visible.

Application path: `application-trigger-recursive-view-upsert-current-source-next232.php` models a copied `wp_options` import view where a `siteurl` UPSERT recursively updates `home` and `rewrite_rules` before a plugin migration attempts the next source.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext232Test.php`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next232.php`

Expected dashboard movement: `phpPass` increases by the verified focused PASS-line count from the new test file. `benchmarkDenominator.mapped` remains `634 / 1589`; this is focused PHP behavior over already mapped trigger/view/UPSERT inventory, not a newly hydrated upstream row.

Dependency closure: no new support component is needed. The slice reuses the lane-local recursive view UPSERT executor and adds bounded current-source conflict sealing.

Non-overlap: avoids accepted recursive view RETURNING next157-next229, trigger/view RETURNING, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters. The narrower behavior is UPSERT conflict sealing for recursive `INSTEAD OF` view triggers before next-source admission.
