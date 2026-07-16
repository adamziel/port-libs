# trigger-recursive-view-returning-current-source-next178

## Behavior

Adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext178Plan`, extending the accepted recursive view trigger RETURNING current-source/savepoint model with a current-source snapshot token and view schema-cookie fence.

The plan flattens paged RETURNING rows into statement order, tags every row with the snapshot token and schema cookie, publishes current-source rows before next-source rows after savepoint release, and restarts/fences next-source rows when the snapshot token or schema cookie is stale.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext178Test.php`
  - `1 test files, 72 assertions, 0 failures`
  - 72 PASS lines

## Application Smoke

- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next178.php`
  - `application-trigger-recursive-view-returning-current-source-next178 self-test passed`

## Non-Overlap

This slice builds on next175 savepoint fencing but does not repeat next174 duplicate-key watermarking, next175 savepoint release/rollback decisions, deferred FK trigger behavior, UPSERT/DELETE RETURNING conflict handling, schema reparse trigger work, or WAL/VFS storage slices.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP recursive view RETURNING current-source savepoint and schema-cookie model.
