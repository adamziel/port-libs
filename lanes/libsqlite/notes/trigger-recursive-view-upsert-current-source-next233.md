# trigger-recursive-view-upsert-current-source-next233

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
UPSERT current-source admission.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext233Plan`.
It layers a current-source UPSERT seal after the accepted recursive view
RETURNING generation handoff, binding the current view source, trigger source,
conflict target, and update-column set before attempted next-source rows can
be published.

Application path: `application-trigger-recursive-view-upsert-current-source-next233.php`
models a copied `wp_options` import view where recursive trigger-generated
UPSERT rows must be sealed against the current view/trigger source before a
plugin migration's next source can expose `home` and `next_plugin` rows.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext233Test.php`
- Result: `1 test files, 88 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next233.php`
- Result: `application-trigger-recursive-view-upsert-current-source-next233 self-test passed`

Dashboard delta: `phpPass +88` from the new focused test file. Mapped coverage
is unchanged; this is current-source PHP behavior over already mapped
trigger/view/UPSERT/RETURNING inventory, not a newly hydrated upstream row.

Dependency closure: no new support component is needed. The slice reuses
lane-local recursive view RETURNING generation/source sealing and adds a
bounded current UPSERT source seal over conflict target and update columns.

Non-overlap: avoids accepted recursive view RETURNING cursor, epoch, source,
snapshot, and generation handoffs through next229; accepted trigger recursive
view RETURNING next157-next229 surfaces; row-value RETURNING savepoints; DML
RETURNING conflicts; schema reparse; WAL/VFS; JSON table; planner; encoding;
and B-tree clusters. The new behavior is specifically current-source view
UPSERT admission before next-source publication.
