# full-run-parity-application-wal-rollback-json-dynamic-20260531T063648Z-0

Slice: `full-run-parity-application-wal-rollback-json-dynamic-20260531T063648Z-0`

Base accepted HEAD: `e80280ab3ef4a3dc0e83a28a18647e19ca0381e1`

## Behavior

- Fixed `SQLiteJsonImportRollbackWalPlan::appendSuccessfulWalFrames()` so a materialized successful application JSON import writes a SQLite-style commit marker on the final appended WAL frame.
- Intermediate appended frames keep commit `0`; the final appended frame stores the post-import database page count.
- This extends the existing application WAL rollback JSON dynamic parity cluster without repeating malformed JSON rollback, preexisting WAL truncation, missing/partial WAL-tail rejection, frame/header checksum mismatch, inserted-setting rollback, tenant-collision rollback, or retry materialization checksum coverage.

## Focused Test Movement

- Added 72 focused dynamic assertions:
  - 18 retry-after-rollback scenarios x 2 final commit-marker assertions.
  - 18 preexisting-WAL retry scenarios x 2 final commit-marker assertions.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - `1 test files, 4552 assertions, 0 failures`

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This reuses the existing lane-local WAL frame/header encoding, checksum chaining, JSON import savepoint, and application WAL rollback dynamic parity helpers.

## Next

Continue app-WAL work only on a non-overlapping full-run parity gap, preferably a broad runner/root blocker around remaining app-WAL conflicts or pager/WAL default-memory pressure.
