# trigger-recursive-view-upsert-current-source-next245

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
UPSERT current-source conflict-target receipt admission after accepted next241
close seals.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext245Plan`.
After the current recursive view source has closed, next-source rows are still
held until the current UPSERT conflict target, excluded-column set, target
token, view cookie, trigger cookie, and per-current-row target receipts match.
Missing, unexpected, or out-of-order receipts keep attempted next-source rows
held while current rows remain visible.

Application path: `application-trigger-recursive-view-upsert-current-source-next245.php`
models a copied `wp_options` import view where recursive child UPSERT rows for
`blogdescription_child` and `template_child` must publish only after the
current `ON CONFLICT(option_name) DO UPDATE SET option_value=excluded...`
target receipts match the current source.

Verification:

```bash
$ php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext245Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext245Plan.php
$ php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext245Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext245Test.php
$ php -l lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next245.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next245.php
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext245Test.php
1 test files, 90 assertions, 0 failures
$ php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next245.php
application-trigger-recursive-view-upsert-current-source-next245 self-test passed
```

Expected dashboard movement: `phpPass +90` from the new focused test file.
`benchmarkDenominator.mapped` remains unchanged; this is focused PHP behavior
over already mapped trigger/view/UPSERT current-source inventory, not a newly
hydrated upstream Tcl inventory unit.

Dependency closure: no new support component is needed. The slice reuses the
lane-local recursive view UPSERT close-seal chain and adds bounded
conflict-target/excluded-column receipt admission.

Non-overlap: avoids accepted next241 recursive view UPSERT close seals,
next237 action receipts, recursive view RETURNING, row-value RETURNING
savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and
B-tree clusters. The narrower behavior is current-source UPSERT
conflict-target receipt admission before next-source publication.

Next task: wire this target receipt gate into parser-level trigger bytecode
when native view-trigger UPSERT execution owns conflict target validation
directly.
