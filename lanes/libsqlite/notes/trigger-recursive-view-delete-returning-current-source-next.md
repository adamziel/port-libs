# trigger-recursive-view-delete-returning-current-source-next

Status: focused PHP behavior growth for recursive `INSTEAD OF DELETE` view triggers with `RETURNING` at the current/next source boundary.

This slice adds `SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan`. It models a Application copied-options cleanup view where a recursive delete drains current-source `RETURNING` rows, can roll back to the savepoint after a trigger blocker, can release the current delete set while holding the next view source, and can admit the next view source later with its own trigger-source cookie.

Application path: `application-trigger-recursive-view-delete-returning-current-source-next.php` covers plugin option cleanup through a recursive view delete over `wp_options`-style parent/child rows.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextTest.php
php -l lanes/libsqlite/examples/application-trigger-recursive-view-delete-returning-current-source-next.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextTest.php
php lanes/libsqlite/examples/application-trigger-recursive-view-delete-returning-current-source-next.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 51 assertions, 0 failures`.

Expected dashboard movement: `phpPass +51`, from `75459` to `75510`. `benchmarkDenominator.mapped` remains unchanged; this is additional current-source PHP behavior over already mapped trigger/view/RETURNING surfaces, not a newly hydrated upstream Tcl unit.

Dependency closure: no new support component is needed. The slice reuses lane-local row-array trigger/view/savepoint/RETURNING modeling.

Non-overlap: avoids accepted next164 recursive view INSERT/UPSERT-style RETURNING admission, next163 snapshot barrier trigger-generated seed behavior, next134/next136 view trigger savepoint RETURNING, deferred FK trigger RETURNING clusters, row-value RETURNING savepoints, WAL/pager savepoint application, schema reparse, and attach trigger/view cache invalidation. The narrower surface is recursive view `DELETE ... RETURNING` current-source draining and rollback/release/admit behavior.

Next task: wire this bounded delete source boundary into the parser-level trigger executor when native view trigger bytecode owns `INSTEAD OF DELETE` and savepoint rollback directly.
