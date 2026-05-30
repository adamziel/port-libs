# trigger-recursive-view-returning-current-source-next166

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger `RETURNING` rows at the current-source to next-source admission boundary.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext166Plan`. It reuses the accepted recursive view RETURNING source barriers, then records the statement RETURNING drain timeline: current view rows are visible first, trigger-generated rows remain outside the current source snapshot, and a released next source can expose its seed rows only after the current RETURNING stream has drained. When the next source is held, the attempted next RETURNING key is reported as suppressed.

Application path: `application-trigger-recursive-view-returning-current-source-next166.php` covers copied `wp_options` imports through a recursive autoload view where an `INSTEAD OF` trigger emits current RETURNING rows and creates audit rows that seed the next view source after admission.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext166Plan.php
php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext166Test.php
php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next166.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext166Test.php
php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next166.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +56` from the new focused test file. `benchmarkDenominator.mapped` is unchanged; this is additional current-source PHP behavior over already mapped trigger/view/RETURNING surfaces, not a newly hydrated upstream Tcl inventory unit.

Dependency closure: no new support component is needed. The slice reuses lane-local recursive view RETURNING source barriers and trigger row projection.

Non-overlap: avoids accepted next163 trigger-generated snapshot seeding, next162 staged next-source queues, next160 recursive view RETURNING barriers, view-trigger savepoint/rollback slices, row-value/UPSERT RETURNING slices, deferred FK trigger slices, WAL/pager/B-tree/VFS/JSON/encoding clusters, and suite evidence handoffs. The new surface is specifically the RETURNING drain order that prevents a released next view source from becoming visible before all current-source RETURNING rows are yielded.

Next task: wire this drain timeline into the parser-level trigger executor once native view trigger bytecode owns current/next source admission directly.
