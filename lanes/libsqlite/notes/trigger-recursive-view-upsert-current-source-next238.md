# trigger-recursive-view-upsert-current-source-next238

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger UPSERT current-source resume receipts.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext238Plan`, a current-source resume-receipt gate layered after the accepted next235 UPSERT yield tickets. Current recursive view UPSERT rows must be acknowledged against the resume source, resume cursor, resume epoch, existing next235 yield ticket, view source, trigger program, event, depth, ordinal, trigger name, old value, option name, and option value before next-source rows can publish.

Application path: `application-trigger-recursive-view-upsert-current-source-next238.php` models a copied `wp_options` import view where a `siteurl` UPSERT recursively yields `home` and `rewrite_rules` before plugin migration rows from the next source become visible. The next source remains held until the current resume receipts are acknowledged.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext238Test.php`
  - `1 test files, 94 assertions, 0 failures`
  - 94 PASS lines
- `php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next238.php --self-test`
  - `application-trigger-recursive-view-upsert-current-source-next238 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - passed

Expected dashboard movement: `phpPass` increases by the verified focused PASS-line count from the new test file. `benchmarkDenominator.mapped` remains unchanged; this is focused PHP behavior over already mapped trigger/view/UPSERT inventory, not a newly hydrated upstream row.

Dependency closure: no new support component is needed. The slice reuses the lane-local recursive view UPSERT executor, next232 conflict seals, and next235 yield tickets.

Non-overlap: avoids accepted recursive view RETURNING next157-next231, accepted next232 recursive view UPSERT conflict seals, accepted next235 yield tickets, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters. The narrower behavior is current-source resume-receipt fencing after recursive `INSTEAD OF` view-trigger UPSERT yield tickets.
