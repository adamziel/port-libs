# trigger-recursive-view-upsert-current-source-next241

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
UPSERT current-source close seals after accepted next237 action receipts.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext241Plan`.
After the accepted next237 current-source UPSERT action receipts are complete,
the current recursive view source must still close against the current source
generation, view cookie, trigger cookie, and close token before next-source
rows can be published. Stale source generations, stale view/trigger cookies,
missing close receipts, unexpected receipts, and out-of-order close receipts
hold attempted next-source rows while current rows remain visible.

Application path: `application-trigger-recursive-view-upsert-current-source-next241.php`
models a copied `wp_options` import view where recursive child UPSERT actions
for `blogdescription_child` and `template_child` must close against the current
view/trigger source before the next plugin rows `home` and `next_plugin` become
visible.

Verification:

```bash
$ php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext241Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext241Plan.php
$ php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext241Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext241Test.php
$ php -l lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next241.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next241.php
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext241Test.php
1 test files, 99 assertions, 0 failures
$ php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next241.php
application-trigger-recursive-view-upsert-current-source-next241 self-test passed
```

Expected dashboard movement: `phpPass +99` from the new focused test file.
`benchmarkDenominator.mapped` remains unchanged; this is focused PHP behavior
over already mapped trigger/view/UPSERT current-source inventory, not a newly
hydrated upstream Tcl inventory unit.

Dependency closure: no new support component is needed. The slice reuses the
lane-local recursive view UPSERT action receipt chain and adds a bounded
current-source close-seal gate.

Non-overlap: avoids accepted next237 recursive view UPSERT action receipts,
next235 yield tickets, next236 row images, recursive view RETURNING,
row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner,
encoding, and B-tree clusters. The narrower behavior is current-source
close/generation admission after recursive view UPSERT action receipts.

Next task: wire this close-seal gate into parser-level trigger bytecode once
native view-trigger UPSERT execution owns current-source cursor closure
directly.
