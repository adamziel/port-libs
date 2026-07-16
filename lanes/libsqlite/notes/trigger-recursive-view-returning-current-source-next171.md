# trigger-recursive-view-returning-current-source-next171

Status: focused PHP behavior growth for recursive INSTEAD OF view triggers with RETURNING rows where the current-source RETURNING cursor is only partially acknowledged.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext171Plan`. It reuses the accepted next164/next167 recursive view trigger and RETURNING row planner, then adds cursor-close gating: when the next view/trigger source is admitted by schema state but the current RETURNING cursor still has unacknowledged pages, next-source RETURNING pages are fenced and reported as blocked until the current cursor is closed. Once all current pages are acknowledged, next-source pages become visible in current-then-next order.

Application path: `application-trigger-recursive-view-returning-current-source-next171.php` models a copied `wp_options` import through an INSTEAD OF view trigger. A plugin/plugin-migration cursor has yielded the first current RETURNING page while recursive retry rows are still pending, so the next import view definition must not become observable until the current cursor closes.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext171Test.php
# 1 test files, 56 assertions, 0 failures

php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next171.php --self-test
# application-trigger-recursive-view-returning-current-source-next171 self-test passed
```

Expected dashboard movement: `phpPass +56` from the new focused test file. `benchmarkDenominator.mapped` is unchanged; this is current-source PHP behavior over already mapped trigger/view/RETURNING inventory, not a newly hydrated upstream Tcl row.

Non-overlap: avoids accepted next164/next167 full current-drain page yield, accepted batch159 trigger recursive/view RETURNING behavior, deferred FK trigger/view RETURNING, savepoint view trigger rollback, recursive UPSERT RETURNING, row-value RETURNING, schema view/trigger reparse, WAL/VFS/B-tree/JSON/encoding clusters, and suite evidence handoffs. The narrower behavior is an open RETURNING cursor preventing a newly admitted next view/trigger source from becoming visible until the current cursor is closed.

Dependency closure: no new support component is needed. The slice reuses lane-local recursive view trigger, RETURNING projection, current/next source, and page-drain primitives.
