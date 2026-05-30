# trigger-recursive-view-upsert-current-source-next248

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
UPSERT current-source `DO UPDATE WHERE` guard receipt admission after accepted
next245 conflict-target receipts.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext248Plan`.
After the current recursive view UPSERT conflict target is accepted, attempted
next-source rows are still held until the current `DO UPDATE WHERE` guard
token, guard-column list, per-current-row guard outcomes, and per-current-row
where receipts match. Missing, unexpected, or out-of-order guard receipts keep
next-source rows held while current rows remain visible.

Application path: `application-trigger-recursive-view-upsert-current-source-next248.php`
models copied `wp_options` recursive import behavior where current rows from
an `INSTEAD OF` view trigger must finish `ON CONFLICT(option_name) DO UPDATE
... WHERE excluded.option_value <> ''` guard admission before next-source rows
for `home` and `next_plugin` can publish.

Verification:

```bash
$ php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext248Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext248Plan.php
$ php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext248Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext248Test.php
$ php -l lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next248.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next248.php
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext248Test.php
1 test files, 66 assertions, 0 failures
$ php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next248.php
application-trigger-recursive-view-upsert-current-source-next248 self-test passed
```

Expected dashboard movement: `phpPass +66` from the new focused test file
(`127481 -> 127547`). `benchmarkDenominator.mapped` remains `654 / 1589`;
this is focused PHP behavior over already mapped trigger/view/UPSERT
current-source inventory, not a newly hydrated upstream Tcl inventory unit.

Dependency closure: no new support component is needed. The slice reuses the
lane-local recursive view UPSERT target receipt chain and adds bounded
`DO UPDATE WHERE` guard receipt admission.

Non-overlap: avoids accepted next245 recursive view UPSERT conflict-target and
excluded-column receipts, next241 close seals, next237 action receipts,
recursive view RETURNING, row-value RETURNING savepoints, schema reparse,
WAL/VFS, JSON table, planner, encoding, and B-tree clusters. The narrower
behavior is current-source UPSERT `DO UPDATE WHERE` guard receipt admission
before next-source publication.

Next task: wire this guard receipt gate into parser-level trigger bytecode
when native view-trigger UPSERT execution owns `DO UPDATE WHERE` predicate
validation directly.
