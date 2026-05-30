# Application Schema Bulk Import Current Next33

Status: focused PHP corpus growth for bounded Application schema dump replay.

This slice adds `SQLiteSchemaBulkImportPlan`, a native PHP preflight
for copied Application schema imports. It safely splits SQL dump text, classifies
`CREATE TABLE`, `CREATE INDEX`, `CREATE VIEW`, and `CREATE TRIGGER` statements,
preserves table/index/view/trigger dependency ordering, assigns bounded
rootpages to table and index objects, accounts for table autoindexes, handles
`IF NOT EXISTS` duplicates, and reports schema/data-version movement.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaBulkImportCurrentNext33Test.php
# Focused test run: 1 selected test files (root lock skipped)
# ...
# 1 test files, 61 assertions, 0 failures

php -l lanes/libsqlite/src/SQLiteSchemaBulkImportPlan.php
php -l lanes/libsqlite/tests/SQLiteSchemaBulkImportCurrentNext33Test.php
php -l lanes/libsqlite/examples/application-schema-bulk-import-current-next33.php
php lanes/libsqlite/examples/application-schema-bulk-import-current-next33.php --self-test
git diff --check -- lanes/libsqlite
```

Application smoke: `examples/application-schema-bulk-import-current-next33.php`
prints copied `wp_options` schema-import ordering, applied object count, and
schema cookie movement without requiring `ext/sqlite`.

Non-overlap: this avoids accepted SELECT SQL text/JOIN/GROUP/subquery/ORDER
execution, JSON table source/cursor/constraint work, VFS writer/lock/sync,
rollback/WAL commit/apply, B-tree page move/root collapse/interior merge, and
overflow freelist/freeblock clusters. The new behavior is schema dump bulk
classification and version/rootpage planning for Application imports.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local `SQLiteCreateTable` and `SQLiteCreateIndex` parsers.
