# full-run-parity-application-wal-rollback-json-dynamic-20260531T043354Z-0

Micro-slice: `full-run-parity-application-wal-rollback-json-dynamic-20260531T043354Z-0`

Behavior added:
- `SQLiteJsonImportRollbackWalPlan` now rejects rollback-required app JSON WAL batches when rollback metadata references current-batch WAL frames that are not present in the supplied WAL byte stream.
- Added dynamic missing-tail scenarios over preexisting WAL prefixes, one-frame and two-frame truncation, both 512 and 1024 byte pages, and generic tenant streams.
- Extended the existing application WAL rollback JSON parity smoke summary to cover the new missing-tail guard.

Focused evidence:
- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - Result: `1 test files, 2361 assertions, 0 failures`

Expected selected movement:
- Adds 77 focused PASS lines to the existing app-WAL rollback JSON dynamic parity file.
- `lane-status.json` moves `phpPass` from `2098495` to `2098572`; mapped coverage remains `1589 / 1589`.

Non-overlap:
- This is a corruption-prevention guard for missing WAL tail bytes during application JSON rollback. It does not repeat accepted rollback-journal apply/commit, WAL checkpoint transactions, VFS writer/sync/lock/savepoint rollback, WAL byte truncation, JSON table source/cursor/constraint work, B-tree page relocation/freeblock materialization, SELECT SQL text/group/order/subquery clusters, row-value dynamic parity, or source-neutral cleanup.

Dependency closure:
- No new support component is needed. The slice reuses the existing native PHP JSON import savepoint plan, savepoint/WAL rollback metadata, WAL header/frame parsing, and dynamic app-WAL parity fixture generation.
