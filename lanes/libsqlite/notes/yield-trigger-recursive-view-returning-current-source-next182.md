# trigger-recursive-view-returning-current-source-next182

## Behavior

Adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext182Plan`, extending the accepted next178 recursive view trigger RETURNING current-source snapshot fence with current view-source and trigger-source generation checks.

The plan tags flattened RETURNING rows with the current view generation, trigger generation, and RETURNING cursor generation. If either generation is stale while the current cursor is draining, next-source RETURNING rows stay quarantined and the statement reports a restart instead of mixing rows from a changed source.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext182Test.php`
  - `1 test files, 76 assertions, 0 failures`
  - 76 PASS lines

## Application Smoke

- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next182.php`
  - `application-trigger-recursive-view-returning-current-source-next182 self-test passed`

## Non-Overlap

This slice builds on next178 snapshot/schema-cookie fencing but does not repeat next174 duplicate-key watermarking, next175 savepoint release/rollback decisions, schema reparse trigger work, deferred FK trigger behavior, UPSERT/DELETE/row-value RETURNING conflict handling, or WAL/VFS storage slices.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP recursive view RETURNING current-source generation, trigger-source cookie, and savepoint/snapshot model.
