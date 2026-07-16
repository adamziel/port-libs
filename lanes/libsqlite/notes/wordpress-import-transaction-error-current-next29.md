# Application Import Transaction Error Current/Next29

2026-05-27 isolated slice `yield-sqlite-application-import-transaction-error-current-next29`.

## Behavior

Adds `SQLiteImportTransactionErrorYieldPlan`, a bounded row-yield model
for copied `wp_options` import transactions. The planner reports each staged row
with current/next option-id state, records a Application-style `WP_Error` payload
for statement failures, and either rolls the whole transaction back on first
error or continues with statement-only errors when configured.

This is not another VFS rollback/savepoint byte-application slice. It reuses
accepted transaction begin semantics and models the import executor's visible
current/next row and error envelope before durable pager/VFS application.

## Verification

Focused test output:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteImportTransactionErrorCurrentNext29Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 70 assertions, 0 failures
```

Final verification:

```sh
php -l lanes/libsqlite/src/SQLiteImportTransactionErrorYieldPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteImportTransactionErrorYieldPlan.php

php -l lanes/libsqlite/tests/SQLiteImportTransactionErrorCurrentNext29Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteImportTransactionErrorCurrentNext29Test.php

php -l lanes/libsqlite/examples/application-import-transaction-error-current-next29.php
No syntax errors detected in lanes/libsqlite/examples/application-import-transaction-error-current-next29.php

php lanes/libsqlite/examples/application-import-transaction-error-current-next29.php
{
    "status": "rolled_back",
    "yieldedStatuses": [
        "applied",
        "error"
    ],
    "wpErrorCodes": [
        "sqlite_constraint"
    ]
}

git diff --check -- lanes/libsqlite
# no output
```

## Dependency Closure

No new support component is needed. The slice reuses existing transaction begin
planning and local row-array import planning. Durable pager/VFS transaction
application remains owned by the WAL/VFS slices already accepted or queued.
