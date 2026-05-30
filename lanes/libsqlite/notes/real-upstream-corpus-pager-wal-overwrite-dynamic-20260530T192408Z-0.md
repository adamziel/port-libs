# real-upstream-corpus-pager-wal-overwrite-dynamic-20260530T192408Z-0

Base accepted HEAD: `de394d1a2a5407b1856e89f4b996c5ea3450f50d`.

Added `SQLiteRealUpstreamPagerWalOverwriteDynamicTest.php` with 1,001 focused TestRunner PASS cases and 25,001 behavior assertions.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/waloverwrite.test`
- `waloverwrite.test` sections `1.1.1` through `1.1.6`: repeated dirty-page overwrites keep WAL recovery bounded.
- `waloverwrite.test` sections `1.2.1` through `1.2.6`: same overwrite sequence when the WAL already contains a transaction.
- `waloverwrite.test` sections `1.1.7` through `1.1.9`: savepoint rollback excludes rolled-back blob updates from recovered state.
- `waloverwrite.test` sections `1.2.7` through `1.2.9`: savepoint rollback remains recoverable after a preexisting WAL transaction.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalOverwriteDynamicTest.php`
- Result: `1 test files, 25001 assertions, 0 failures`.

Non-overlap:

- Avoids accepted pager/WAL noop checkpoint, exclusive/recovery/restart-overwrite, WAL checkpoint transaction, WAL byte truncation, VFS file writer/apply, and hot-journal publication clusters.
- This slice specifically covers high-churn dirty page overwrite plus savepoint rollback recovery from `waloverwrite.test`.

Dependency closure:

- No new support component needed.
- Reuses native PHP `SQLiteWal`, `SQLiteWalHeader`, WAL checksum validation, transaction recovery boundary, checkpoint mode result, and reader snapshot helpers.
