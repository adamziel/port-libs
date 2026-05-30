# Attach Temp View Trigger Yield Current Next27

## Behavior

Adds bounded trigger-body yield planning for TEMP, main, and attached view triggers. `SQLiteAttachTempViewTriggerYieldPlan` expands supported trigger body statements into schema-qualified `INSERT`, `UPDATE`, `DELETE`, and `SELECT` operations while binding `new.*` and `old.*` values from supplied rows.

This is intentionally narrower than executing the SQL engine. It covers the current closure gap after name resolution: once a TEMP/main/attached view trigger resolves, the port can now preview which schema receives each yielded write and which OLD/NEW values flow into copied Application import operations.

## Evidence

Focused new test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempViewTriggerYieldCurrentNext27Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 55 assertions, 0 failures
```

Regression with accepted attach/temp resolver:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempViewTriggerYieldCurrentNext27Test.php lanes/libsqlite/tests/SQLiteAttachTempViewTriggerCurrentNext17Test.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 114 assertions, 0 failures
```

## Non-Overlap

Avoided accepted clusters: ATTACH temp/view/trigger name resolution from current-next17, trigger/FK yield behavior, VFS/open and file writer work, WAL checkpoint/savepoint/rollback slices, B-tree page-move/freelist/rebalance slices, JSON table cursor/source/constraint slices, and SELECT SQL text execution.

## Dependency Closure

No new support component is needed. The slice reuses the existing `SQLiteAttachedSchemaCatalog`, `SQLiteSchemaRecord`, and `SQLiteAttachTempViewTriggerResolution` lane-local components.
