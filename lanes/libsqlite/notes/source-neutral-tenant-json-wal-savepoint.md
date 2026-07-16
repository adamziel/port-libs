# Source-Neutral Tenant JSON WAL Savepoint

## Summary

- Reworked `SQLiteTenantJsonWalSavepointPlan` so tenant identity, table naming,
  dependency keys, WAL frame metadata, and default database paths are generic
  tenant/key-value concepts instead of application-specific source names.
- Preserved the JSON import, savepoint rollback, and tenant WAL frame behavior
  with focused coverage under `SQLiteTenantJsonWalSavepointCurrentNext47Test`.
- Replaced the application smoke with a generic tenant JSON WAL savepoint smoke.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteTenantJsonWalSavepointPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTenantJsonWalSavepointCurrentNext47Test.php`
- `php -l lanes/libsqlite/examples/application-tenant-json-wal-savepoint.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTenantJsonWalSavepointCurrentNext47Test.php`
  - `1 test files, 74 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-tenant-json-wal-savepoint.php --self-test`
  - self-test passed

## Dependency Closure

No new support component is needed. This reuses the existing native JSON import
planner, SQLite savepoint WAL rollback bookkeeping, and tenant WAL frame
aggregation.

## Non-Overlap

This slice does not add new WordPress-specific API, smoke, or source names and
does not duplicate current root-gate suite evidence, window frame, WAL hot
journal checkpoint, or numbered helper consolidation work.
