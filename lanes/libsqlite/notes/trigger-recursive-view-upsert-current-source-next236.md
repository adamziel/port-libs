# trigger-recursive-view-upsert-current-source-next236

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
UPSERT current-source row-image admission.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext236Plan`.
It layers row-image receipts after the accepted next233 conflict-target and
update-column seal, binding each current recursive view UPSERT row to the
current view source, trigger source, returning generation, row ordinal,
returned value, event, depth, and trigger alias before attempted next-source
rows can be published.

Application path: `application-trigger-recursive-view-upsert-current-source-next236.php`
models a copied `wp_options` import view where recursive trigger-generated
UPSERT rows for `blogdescription_child` and `template_child` must have current
row images sealed before a plugin migration's next-source `home` and
`next_plugin` rows become visible.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext236Test.php`
- Result: `1 test files, 87 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next236.php`
- Result: `application-trigger-recursive-view-upsert-current-source-next236 self-test passed`

Dashboard delta: expected `phpPass +87` from the focused PASS lines in
`SQLiteTriggerRecursiveViewUpsertCurrentSourceNext236Test.php`. Mapped coverage
is unchanged at `639 / 1589`; this is current-source PHP behavior over already
mapped trigger/view/UPSERT/RETURNING inventory, not a newly hydrated upstream
row.

Dependency closure: no new support component is needed. The slice reuses
lane-local recursive view RETURNING generation/source sealing and accepted
next233 current UPSERT source seals, then adds bounded row-image receipts.

Non-overlap: avoids accepted next233 UPSERT conflict-target/update-column
source seals, recursive view RETURNING cursor/epoch/source/snapshot/generation
handoffs, row-value RETURNING savepoints, DML RETURNING conflicts, schema
reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters. The new
behavior is specifically current-source recursive view UPSERT row-image
admission before next-source publication.
