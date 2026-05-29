# trigger-deferred-upsert-returning-current-source-next135

Status: focused PHP behavior growth for `UPSERT ... RETURNING` rows yielded before deferred foreign-key commit validation rejects the next source.

This slice adds `SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan`. It composes the accepted trigger/UPSERT/RETURNING statement model with a deferred FK commit gate:

- statement `RETURNING` rows are captured from the current row image before AFTER-trigger target mutations;
- deferred FK violations are evaluated at commit, after all UPSERT and trigger side effects have produced the statement image;
- a blocked commit suppresses the next-source RETURNING stream while preserving current yielded rows as evidence;
- transaction rollback restores the pre-statement current source and clears inserted/updated row visibility;
- a held failed transaction can keep the statement image visible for diagnostics, matching SQLite's failed-COMMIT transaction boundary.

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-trigger-deferred-upsert-returning-current-source-next135.php --self-test
wordpress-trigger-deferred-upsert-returning-current-source-next135 self-test passed
```

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerDeferredUpsertReturningCurrentSourceNext135Test.php
```

Dashboard delta: update `phpPass` by the focused PASS-line delta verified for the new test file. `benchmarkDenominator.mapped` is unchanged; this is additional current-source PHP behavior over already mapped trigger, UPSERT, RETURNING, and deferred-FK surfaces, not a newly hydrated upstream Tcl inventory row.

Non-overlap: avoids accepted next132 trigger UPSERT savepoint RETURNING rollback, next128 recursive deferred view RETURNING, next126 recursive UPSERT RETURNING, next121/next125 deferred recursive RETURNING, next119 deferred RETURNING savepoint, next111 deferred FK recursive RETURNING, DML trigger RETURNING conflict next106, row-value RETURNING, VFS/WAL/B-tree/JSON/encoding/SELECT clusters, and status-only movement. The new behavior is specifically the deferred FK commit boundary after trigger UPSERT RETURNING current-source rows have already yielded.

Dependency closure: no new support component is needed. The slice reuses the lane-local trigger/UPSERT/RETURNING statement planner and adds bounded deferred FK commit validation in native PHP.

Next task: wire this deferred commit gate into parser-level INSERT/UPSERT execution once the broader native DML executor owns foreign-key queues directly.
