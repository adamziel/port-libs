# full-run-parity-application-wal-rollback-json-dynamic-20260531T033907Z-0

Status: focused PHP behavior growth for generic application WAL rollback JSON
dynamic parity.

Behavior covered:

- Extends `SQLiteJsonImportRollbackWalPlan` with dynamic retry-after-rollback
  scenarios.
- Each scenario first runs a malformed JSON batch that rolls back the current
  savepoint, restores the original database image, and truncates the WAL to the
  saved header prefix.
- The same scenario then retries with corrected JSON input from the restored
  database/WAL state and proves the retry applies all corrected statements with
  tenant isolation, JSONB/text coverage, ordered WAL frame previews, and
  corrected page rollback previews.

Focused growth:

- `SQLiteApplicationWalRollbackJsonDynamicParityTest.php` grows from 1022 to
  1407 assertions, a net +385 focused assertions.
- Mapped denominator coverage is unchanged; this is already-mapped PHP
  behavior growth over the app-WAL rollback/JSON parity cluster.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - `1 test files, 1407 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP JSON
  mutation, JSONB encoding, savepoint statement rollback, WAL byte rollback, and
  application key-value row planning components.

Non-overlap:

- This adds only retry-after-rollback behavior for the existing generic
  application WAL rollback JSON dynamic parity file. It does not repeat
  accepted row-value, expression, SELECT, date, B-tree, PRAGMA, trigger/FK,
  VFS, source-neutral cleanup, metadata-only suite evidence, or standalone JSON
  table/source/cursor clusters.
