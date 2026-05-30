# Application bulk import savepoint current-next28

Status: focused PHP corpus growth for copied `wp_options` bulk import batches
inside named savepoints.

This slice adds `SQLiteBulkImportSavepointPlan`, a bounded row-array
planner for Application option imports that release successful savepoint batches
and roll back the current failing batch while preserving earlier released rows
for the next batch. It reuses the accepted current import transaction planner
for per-batch row effects and `SQLiteSavepointStack` for current-savepoint
rollback metadata, but does not touch accepted page-image restore, WAL byte
truncation, VFS savepoint rollback application, rollback-journal commit/apply,
super-journal, checkpoint transaction, or file writer surfaces.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBulkImportSavepointCurrentNext28Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 61 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-bulk-import-savepoint-current-next28.php
```

Expected dashboard movement: `phpPass +61` for the new focused PASS cases. No
new upstream denominator row is claimed.

Dependency closure: no new support component is needed. The slice reuses
lane-local import transaction and savepoint primitives and stays bounded to
native PHP row planning for copied Application option import batches.
