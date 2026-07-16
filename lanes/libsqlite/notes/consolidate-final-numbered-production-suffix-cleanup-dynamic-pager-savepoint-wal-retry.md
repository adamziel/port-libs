# Pager Savepoint WAL Retry Suffix Cleanup

This consolidation pass renames the direct pager savepoint WAL retry test and
Application smoke away from the old `current-next64` file names. Production now
emits the stable `sqlite-pager-savepoint-wal-retry-current` dependency marker
while preserving the old `sqlite-pager-current-next-wal-frame64` marker as an
observable compatibility alias for existing receipts.

Verification:

- `php -l lanes/libsqlite/src/SQLiteSavepointStack.php`
- `php -l lanes/libsqlite/tests/SQLitePagerSavepointCurrentWalRetryTest.php`
- `php -l lanes/libsqlite/examples/application-pager-savepoint-wal-retry.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointCurrentWalRetryTest.php`
  - `1 test files, 53 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepoint*Test.php`
  - `20 test files, 1452 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-savepoint-wal-retry.php --self-test`

Dependency closure: no new support component is needed; this reuses the
existing `SQLiteSavepointStack` WAL rollback-to-current retry behavior.
