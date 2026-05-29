# transaction-savepoint-trigger-rollback-current-source-next106

Status: focused current-source PHP behavior growth for transaction savepoint trigger rollback.

This slice adds `SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan` for SQLite trigger `RAISE(ROLLBACK)` behavior inside a transaction savepoint. It covers DELETE and UPDATE source-cursor diagnostics where the attempted next source sees partial row changes, while rollback restores the current source rows to the savepoint image and suppresses durable changes.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteTransactionSavepointTriggerRollbackCurrentSourceNext106Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTransactionSavepointTriggerRollbackCurrentSourceNext106Test.php
php -l lanes/libsqlite/examples/wordpress-transaction-savepoint-trigger-rollback-current-source-next106.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-transaction-savepoint-trigger-rollback-current-source-next106.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTransactionSavepointTriggerRollbackCurrentSourceNext106Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 59 assertions, 0 failures
php lanes/libsqlite/examples/wordpress-transaction-savepoint-trigger-rollback-current-source-next106.php
status=rolled-back, savepoint_preserved=true, attempted_changes=3, changes=0
git diff --check -- lanes/libsqlite
passed with no output
```

Dashboard impact: `phpPass` moves from `40990` to `41049` from the verified focused PASS-line delta. Mapped upstream coverage is unchanged at `601 / 1589`; this is focused native PHP behavior coverage against already mapped transaction/trigger/savepoint families.

Non-overlap: avoids accepted trigger RETURNING savepoint current-next65, recursive trigger conflict rollback, WAL/savepoint byte truncation/restart reader behavior, pager statement-journal savepoint handling, attach/schema trigger reprepare, B-tree/VFS/JSON/UTF-16 current-source batch102/103 surfaces, and queued next104/105 pager/WAL/trigger-adjacent work. The new behavior is the source-cursor transaction rollback boundary for trigger `RAISE(ROLLBACK)`.

Dependency closure: no new support component is needed. The slice reuses lane-local PHP row arrays and trigger/savepoint diagnostics only.
