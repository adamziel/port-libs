# trigger-recursive-view-upsert-current-source-next235

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger UPSERT current-source yield tickets.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext235Plan`, a current-source yield-ticket gate layered after the accepted next232 UPSERT conflict seals. Current recursive view UPSERT rows must be acknowledged against the current yield-ticket source, resume cursor, view source, trigger program, conflict seal, event, depth, ordinal, trigger name, option name, and option value before the next source can publish rows.

Application path: `application-trigger-recursive-view-upsert-current-source-next235.php` models a copied `wp_options` import view where a `siteurl` UPSERT recursively yields `home` and `rewrite_rules` rows. Plugin migration rows from the next source stay held until the current recursive yield tickets are acknowledged.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext235Test.php`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next235.php`

Expected dashboard movement: `phpPass` increases by the verified focused PASS-line count from the new test file. `benchmarkDenominator.mapped` remains unchanged; this is focused PHP behavior over already mapped trigger/view/UPSERT inventory, not a newly hydrated upstream row.

Dependency closure: no new support component is needed. The slice reuses the lane-local recursive view UPSERT executor and accepted next232 current-source conflict seals.

Non-overlap: avoids accepted recursive view RETURNING next157-next231, accepted next232 recursive view UPSERT conflict seals, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters. The narrower behavior is current-source yield-ticket fencing after recursive `INSTEAD OF` view-trigger UPSERT conflict seals.
