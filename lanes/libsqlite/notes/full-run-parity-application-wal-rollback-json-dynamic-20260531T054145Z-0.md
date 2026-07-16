# full-run-parity-application-wal-rollback-json-dynamic-20260531T054145Z-0

Implemented bounded WAL frame-header validation for the application JSON rollback planner. `SQLiteJsonImportRollbackWalPlan` now rejects frame page number `0` and frame salts that do not match the WAL header before admitting a JSON batch rollback/truncation plan.

Focused behavior:

- Added `dynamicFrameHeaderMismatchScenarios()` with 18 deterministic current-batch WAL corruptions over 512 and 1024 byte pages.
- Extended `SQLiteApplicationWalRollbackJsonDynamicParityTest.php` with corrupt-frame assertions for salt mismatch, zero page numbers, aligned WAL bytes, and deterministic target offsets.
- Extended `application-wal-rollback-json-dynamic-parity.php --self-test` to summarize and assert frame-header mismatch diagnostics.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php` -> `1 test files, 2904 assertions, 0 failures`.

Non-overlap:

This does not repeat accepted WAL byte truncation, VFS savepoint rollback application, rollback-journal commit/apply, WAL checkpoint transactions, readonly-SHM refresh, JSON table source/cursor/constraint work, or pager WAL dynamic corpus rows. The new surface is malformed frame-header admission inside the existing app-WAL JSON rollback planner.

Dependency closure:

No new support component is needed. The slice reuses the existing native WAL byte parser, JSON mutation planner, and savepoint rollback planner.
