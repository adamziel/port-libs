# full-run-parity-application-wal-rollback-json-dynamic-20260530T221959Z-0

Base accepted HEAD: `2b1cf655ef1be10ae886e50a15d966f7036573f3`.

## Behavior

Fixed the focused application schema JSON WAL import parity blocker in
`SQLiteApplicationSchemaJsonWalImportCurrentNext41Test.php`. The current source
JSON import savepoint planner accepts source-neutral `app_settings` rows keyed
by `setting_id`, `key_name`, `key_value`, and `load_policy`; this fixture and
the directly coupled schema/WAL import wrapper still exposed old option-shaped
row and metadata names.

The planner now emits generic WAL/yield metadata:

- `json_setting`
- `discarded_json_setting`
- `key_name`
- `write_json_setting_pages`

The test schema now uses generic `app_settings` and `app_import_log` objects.

## Evidence

Before fix:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationImportRollbackWalJsonCurrentNext38Test.php lanes/libsqlite/tests/SQLiteApplicationJsonImportInsertWalCurrentNext50Test.php lanes/libsqlite/tests/SQLiteApplicationJsonImportSavepointCurrentNext48Test.php lanes/libsqlite/tests/SQLiteApplicationJsonSavepointSchemaCurrentTest.php lanes/libsqlite/tests/SQLiteApplicationSchemaJsonSavepointWalTest.php lanes/libsqlite/tests/SQLiteApplicationSchemaJsonWalImportCurrentNext41Test.php
6 test files, 480 assertions, 59 failures
```

After fix:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationSchemaJsonWalImportCurrentNext41Test.php
1 test files, 62 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationImportRollbackWalJsonCurrentNext38Test.php lanes/libsqlite/tests/SQLiteApplicationJsonImportInsertWalCurrentNext50Test.php lanes/libsqlite/tests/SQLiteApplicationJsonImportSavepointCurrentNext48Test.php lanes/libsqlite/tests/SQLiteApplicationJsonSavepointSchemaCurrentTest.php lanes/libsqlite/tests/SQLiteApplicationSchemaJsonSavepointWalTest.php lanes/libsqlite/tests/SQLiteApplicationSchemaJsonWalImportCurrentNext41Test.php
6 test files, 539 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
1 test files, 3 assertions, 0 failures
```

PHP lint passed for:

- `lanes/libsqlite/src/SQLiteSchemaJsonWalImportPlan.php`
- `lanes/libsqlite/tests/SQLiteApplicationSchemaJsonWalImportCurrentNext41Test.php`

`git diff --check -- lanes/libsqlite` passed.

## Non-Overlap

This does not add new generated corpus rows or repeat accepted JSON import
savepoint/WAL behavior. It is a bounded parity and source-neutral fix for the
directly failing next41 application schema JSON WAL wrapper on the current
accepted base.

## Dependency Closure

No new support component is needed. This reuses existing schema bulk import,
JSON import savepoint, WAL import yield, checkpoint-admission, and savepoint
rollback behavior.
