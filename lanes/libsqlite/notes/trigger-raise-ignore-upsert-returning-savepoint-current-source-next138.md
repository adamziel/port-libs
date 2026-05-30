# Trigger Raise Ignore UPSERT RETURNING Savepoint Current Source Next138

Slice: `trigger-returning-upsert-savepoint-current-source-next138`

This slice extends the existing trigger/UPSERT/RETURNING savepoint planner with `RAISE(IGNORE)` handling from BEFORE triggers. Ignored insert and update rows are recorded separately, yield no `RETURNING` row, do not increment changes, and do not roll back the savepoint. Later incoming rows continue to execute and can still produce current and next `RETURNING` output.

Application path: `application-trigger-raise-ignore-upsert-returning-savepoint-current-source-next138.php` models a copied `wp_options` import where triggers protect `siteurl` and suppress a transient import-lock option while allowing later option rows to import in the same savepoint.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerUpsertSavepointReturningCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteTriggerRaiseIgnoreUpsertReturningSavepointCurrentSourceNext138Test.php
php -l lanes/libsqlite/examples/application-trigger-raise-ignore-upsert-returning-savepoint-current-source-next138.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRaiseIgnoreUpsertReturningSavepointCurrentSourceNext138Test.php
php lanes/libsqlite/examples/application-trigger-raise-ignore-upsert-returning-savepoint-current-source-next138.php --self-test
git diff --check -- lanes/libsqlite
```

Dashboard delta: update `phpPass` by the focused PASS-line delta from the new test file. `benchmarkDenominator.mapped` is unchanged; this is additional PHP behavior coverage over an already mapped trigger/UPSERT/RETURNING surface.

Non-overlap: avoids accepted next132 trigger UPSERT RETURNING savepoint rollback, next135 deferred UPSERT RETURNING, next136 RETURNING savepoint view, DML trigger RETURNING conflict handling, FK/deferred trigger clusters, row-value RETURNING savepoint clusters, and WAL/pager savepoint storage clusters. The new surface is specifically `RAISE(IGNORE)` suppression before a RETURNING row is yielded while the multi-row UPSERT savepoint continues.

Dependency closure: no new support component is needed. The slice reuses the lane-local trigger effect projection, UPSERT conflict routing, RETURNING projection, and savepoint current/next model.

Next task: wire this ignored-row state into parser-level trigger execution once native trigger bytecode execution owns `RAISE()` control flow directly.
