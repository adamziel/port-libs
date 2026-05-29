# attach-wal-temp-current-next

## Scope

- Adds `SQLiteAttachWalTempCurrentNextPlan` for aborted mixed attached transactions.
- Covers temp rollback-journal restore, attached rollback-journal restore, WAL frame truncation back to the current frame count, temp-store memory discard, read-only guards, and schema-cache invalidation when page 1 or a DDL write was touched.
- WordPress smoke: copied `wp_options` import staging across `temp`, `main`, and an attached archive database rolls back without advancing the durable page count, change counter, or WAL frame count.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempCurrentNext68Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 80 assertions, 0 failures
```

Smoke command:

```text
php lanes/libsqlite/examples/wordpress-attach-wal-temp-current-next.php --self-test
wordpress-attach-wal-temp-current-next self-test passed
```

Syntax:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempCurrentNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachWalTempCurrentNext68Test.php
php -l lanes/libsqlite/examples/wordpress-attach-wal-temp-current-next.php
```

## Non-Overlap

This slice does not repeat accepted ATTACH WAL/temp commit routing from current-next, savepoint statement-journal rollback/retry from batch66, WAL byte truncation, VFS savepoint rollback application, rollback-journal commit application, or super-journal commit paths. It models the distinct transaction-abort boundary before commit for mixed attached schemas.

## Dependency Closure

No new support component is needed. The slice reuses lane-local PHP planning data structures and does not require external SQLite, VFS, lock, or upstream runner dependencies.
