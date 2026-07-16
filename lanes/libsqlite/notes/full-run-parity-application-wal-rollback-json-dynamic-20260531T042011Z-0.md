# full-run-parity-application-wal-rollback-json-dynamic-20260531T042011Z-0

Slice: `full-run-parity-application-wal-rollback-json-dynamic-20260531T042011Z-0`

## Change

- Tightened `SQLiteJsonImportRollbackWalPlan` WAL admission before JSON import/savepoint rollback planning.
- `walState()` now validates the WAL header magic and requires the WAL header page-size field to match the caller's database page size.
- Added two focused assertions to the existing app-WAL rollback JSON dynamic parity test for invalid WAL magic and WAL/database page-size mismatch.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - `1 test files, 2282 assertions, 0 failures`

## Non-Overlap

This does not repeat the accepted app-WAL rollback JSON dynamic matrices, preexisting-WAL rollback, deferred failure, retry, or preexisting retry scenarios. The new behavior is the WAL header admission guard that prevents applying JSON rollback/truncation logic to a WAL byte stream whose header is not a valid WAL header for the selected database page size.

## Dependency Closure

No new support component is needed. This reuses the existing native WAL header constants and rollback JSON planner.
