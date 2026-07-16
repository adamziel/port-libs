# full-run-parity-application-wal-rollback-json-dynamic-20260531T060404Z-0

## Scope

- Extended `SQLiteJsonImportRollbackWalPlan` with nonzero WAL frame checksum validation before application WAL rollback truncation.
- Added `dynamicFrameChecksumMismatchScenarios()` to generate deterministic generic application WAL fixtures where the first current-batch frame after a committed WAL prefix has a corrupted checksum field.
- Updated the focused dynamic parity test and smoke to cover 18 checksum-mismatch scenarios across 512/1024 byte page sizes and varied prefix frame counts.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - `1 test files, 3470 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
- `git diff --check -- lanes/libsqlite`

## Delta

- Focused assertion movement: `3393 -> 3470`, `+77`.
- Expected dashboard movement: `phpPass 2408856 -> 2408933` if accepted as one focused PASS-file increment.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted application WAL rollback JSON parity, missing-tail, partial-tail, frame-header mismatch, savepoint retry, VFS writer, rollback-journal, JSON table, or B-tree clusters. It adds checksum validation for real/checksummed WAL frame payloads while preserving existing zero-checksum synthetic fixtures.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `SQLiteWal::checksumPair()` implementation and the current JSON import rollback/savepoint planner.
