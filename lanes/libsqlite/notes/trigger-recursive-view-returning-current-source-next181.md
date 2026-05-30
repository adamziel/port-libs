# Trigger Recursive View RETURNING Current Source Next181

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger `RETURNING` cursor checkpoints at the current-source to next-source boundary.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext181Plan`. It builds on the accepted next177 resume-token behavior without changing row production: current-source `RETURNING` pages become visible/durable checkpoints, while attempted next-source pages stay pending until the next view/trigger source is admitted with the expected reprepare token.

Application path: `application-trigger-recursive-view-returning-current-source-next181.php` models copied `wp_options` import rows flowing through a recursive view trigger. It proves a caller can resume after the last current-source checkpoint and keep plugin-created next-source rows blocked at the first next checkpoint.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext181Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext181Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next181.php`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next181.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext181Test.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +68` from the new focused test file. `benchmarkDenominator.mapped` is unchanged; this is current-source PHP behavior over already mapped trigger/view/RETURNING inventory rather than a newly hydrated upstream Tcl unit.

Non-overlap: avoids accepted next172 source pinning, next177 resume-token admission, batch166 trigger recursive/view RETURNING current-source coverage, DML trigger RETURNING conflict handling, deferred FK trigger slices, view UPSERT source pinning, schema trigger/view invalidation, and WAL/VFS/B-tree/JSON/encoding surfaces. The new behavior is specifically page-checkpoint visibility/durability for the recursive view `RETURNING` cursor.

Dependency closure: no new support component is needed. The slice reuses lane-local recursive view-trigger `RETURNING` rows, current/next source metadata, and resume-token cursor modeling.

Next task: wire these checkpoint rows into the broader parser-level trigger executor once native prepared statements own cursor checkpoint persistence directly.
