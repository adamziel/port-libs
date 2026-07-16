# trigger-recursive-view-returning-current-source-next193

Status: focused current-source behavior growth for recursive `INSTEAD OF` view-trigger `RETURNING` source handoff sealing.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext193Plan`. It builds on the accepted next189 row-ack admission model, but does not repeat it: after the current post-reset `RETURNING` rows are acknowledged and next-source rows are admitted, next193 requires a matching handoff token, source sequence token, next-row count, and source signature before those next-source `RETURNING` rows are published to the Application import stream. Mismatched tokens, row counts, or signatures keep the next source quarantined.

Application smoke: `application-trigger-recursive-view-returning-current-source-next193.php` models a copied `wp_options` recursive import view. It verifies that next-source rows for `home` and `next_plugin` are only visible after the current source is drained, acknowledged, and sealed.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext193Test.php`
  - `1 test files, 78 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext193Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext193Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next193.php`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next193.php`
  - `application-trigger-recursive-view-returning-current-source-next193 self-test passed`

Expected dashboard movement: `phpPass +78` from `92140` to `92218`. `benchmarkDenominator.mapped` is unchanged; this is additional current-source PHP behavior over already mapped trigger/view/RETURNING surfaces, not a newly hydrated upstream Tcl row.

Dependency closure: no new support component is needed. The slice reuses lane-local recursive view-trigger `RETURNING`, current/next source metadata, and next189 row-ack admission primitives.

Non-overlap: this extends accepted next189 row-ack next-source admission with a source handoff seal. It avoids next181 checkpoint visibility, next186 post-reset rebind, next189 row acknowledgement, accepted row-value RETURNING savepoint behavior, UPSERT/RETURNING, deferred-FK trigger behavior, schema reparse, WAL/VFS/pager durability, JSON table, planner, encoding, and B-tree current-source slices.
